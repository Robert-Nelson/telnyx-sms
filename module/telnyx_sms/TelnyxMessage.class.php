<?php
$bootstrap_settings = array();
$bootstrap_settings['freepbx_auth'] = false;

$restrict_mods = array();

include_once '/etc/freepbx.conf';

global $amp_conf;
global $astman;

const APP_LOG_DIR = "/var/log/asterisk/";

class TelnyxMessage
{
  protected static bool $debug = true;
  protected static string $Logfile = APP_LOG_DIR.'telnyx-sms.log'; // all SMSes and errors will be logged here for debugging purposes

  protected const TelnyxUrl = 'https://api.telnyx.com/v2/messages';
  protected const TelnyxPublicKey = 'dyoI+5gwA41N0qDAH2SLqW2ro8xUsCT/UmzKrmDHZVQ=';	// Public key for verifying signature - download from Telnyx Mission Control Panel

  function __construct()
  {
  }

  function finalize():void
  {
  }

  static function set_logfile($filename): void
  {
    self::$Logfile = $filename;
  }

  static function log_message(string $message, ?string $extra = null): void
  {
    $fh = fopen(self::$Logfile, "a");
    fwrite($fh, date(DATE_W3C).": ".$message."\n");
    if ($extra != null) {
      fwrite($fh, "\n$extra\n\n");
    }
    fclose($fh);
  }

  static function debug_message(string $message, ?string $extra = null): void
  {
    if (self::$debug) {
      self::log_message($message, $extra);
    }
  }

  protected function open_message_db(): false|PDO
  {
    self::debug_message('Opening Message database');

// Retrieve database and table name if defined, otherwise use FreePBX default
    $db_name = !empty($amp_conf['TSMSDBNAME'])?$amp_conf['TSMSDBNAME']:"telnyx_messages";

// if TSMSDBHOST and TSMSDBTYPE are not empty then we assume an external connection and don't use the default connection
//
    $db_hash = ['mysql' => 'mysql', 'postgres' => 'pgsql'];

    if (!empty($amp_conf["TSMSDBHOST"]) && !empty($amp_conf["TSMSDBTYPE"])) {
      $db_type = $db_hash[$amp_conf["TSMSDBTYPE"]];
      $db_host = $amp_conf["TSMSDBHOST"];
      $db_port = empty($amp_conf["TSMSDBPORT"]) ? '' :  ':' . $amp_conf["TSMSDBPORT"];
      $db_user = empty($amp_conf["TSMSDBUSER"]) ? $amp_conf["AMPDBUSER"] : $amp_conf["TSMSDBUSER"];
      $db_pass = empty($amp_conf["TSMSDBPASS"]) ? $amp_conf["AMPDBPASS"] : $amp_conf["TSMSDBPASS"];
      $datasource = $db_type . '://' . $db_user . ':' . $db_pass . '@' . $db_host . $db_port . '/' . $db_name;
      try {
        $dbtsms = DB::connect($datasource); // attempt connection
        if (DB::isError($dbtsms)) {
          die_freepbx($dbtsms->getDebugInfo());
        }
      } catch (Exception $e) {

      }
      $dbtsms = null;
    }

//    if (! function_exists("out")) {
//      function out($text) {
//        echo $text."<br />";
//      }
//    }

    global $amp_conf;

    $dbt = !empty($dbt) ? $dbt : 'mysql';
    $db_type = $db_hash[$dbt];
    $db_name = !empty($db_name) ? $db_name : "telnyx_messages";
    $db_host = empty($db_host) ?  $amp_conf['AMPDBHOST'] : $db_host;
    $db_port = empty($db_port) ? '' :  ';port=' . $db_port;
    $db_user = empty($db_user) ? $amp_conf['AMPDBUSER'] : $db_user;
    $db_pass = empty($db_pass) ? $amp_conf['AMPDBPASS'] : $db_pass;

    try {
      return new Database($db_type . ':host=' . $db_host . $db_port . ';dbname=' . $db_name, $db_user, $db_pass);
    } catch (Exception $e) {

    }
    return false;
  }

  protected function close_message_db(PDO $db): void
  {
  }

  public function init_lookup_table():bool
  {
    $db = $this->open_message_db();

    $this->lookup_id($db, "TelnyxLineType", "Wireline");
    $this->lookup_id($db, "TelnyxLineType", "Wireless");
    $this->lookup_id($db, "TelnyxLineType", "VoWiFi");
    $this->lookup_id($db, "TelnyxLineType", "VoIP");
    $this->lookup_id($db, "TelnyxLineType", "Pre-Paid Wireless");

    $this->lookup_id($db, "TelnyxMessageDirection", "inbound");
    $this->lookup_id($db, "TelnyxMessageDirection", "outbound");

    $this->lookup_id($db, "TelnyxEndpointUsage", "from");
    $this->lookup_id($db, "TelnyxEndpointUsage", "to");
    $this->lookup_id($db, "TelnyxEndpointUsage", "cc");

    $this->close_message_db($db);

    return true;
  }

  public static function send(string $from, string $to, string $body):string
  {
    // Record the message
    self::log_message(
        "Received from PBX, SMS to " . $_GET["to"] . ", from " . $_GET["from"],
        self::$debug ? $body : null);
    // set up curl and send message
    $http_body = json_encode(
        array(
            "from" => "+1" . $from,
            "to"   => "+1" . $to,
            "text" => $body
        )
    );

    $telnyxToken = Freepbx::Telnyx_sms()->getTelnyxToken();

    $http_opts = array('http' =>
        array(
            'method' => 'POST',
            'header' => array(
                'Content-type: application/json',
                'Accept: application/json',
                'Authorization: Bearer '.$telnyxToken,
            ),
            'timeout' => '3',
            'content' => $http_body
        )
    );
    $http_context = stream_context_create($http_opts);
    try {
      $result = file_get_contents(self::TelnyxUrl, false, $http_context);
    } catch (Exception $e) {
      dbug("file_get_contents exception", $e, 1);
      $result = "Error processing request";
    }
    return $result;
  }

  public static function webhook(string $body, string $signature, string $timestamp):string
  {
    // Verify the message
    $valid = false;

    try {
      $valid = self::verifyHeader($body, $signature, $timestamp, self::TelnyxPublicKey);
    } catch (SodiumException $e) {
      self::log_message("Sodium Exception ".$e->getCode().": ".$e->getMessage()." (".$e->getFile().":".$e->getLine().")");
    }

    $message = json_decode($body);
    $message_data = $message->data;

    if (!$valid) {
      self::$debug = true;
    }

    self::log_message(
      "Received from Telnyx, Event = ".$message_data->event_type.", Signature = ".($valid ? 'OK' : 'ERROR'),
      (self::$debug || !$valid) ? $body : null);

    if ($valid)
    {
      $sms = $message_data->payload;

      $telnyxMessage = new TelnyxMessage();

      try {
        $new_message = $telnyxMessage->update($sms);
      } catch (Exception $e) {
        self::log_message(
            "Exception processing update() ".$e->getCode().": ".$e->getMessage()." (".$e->getFile().":".$e->getLine().")", $body);
        return false;
      }

      if ($message_data->event_type == "message.received" && $new_message)
      {
        $telnyxMessage->send_message($sms);
      }
    }
    return $valid;
  }

  protected function send_message(object $sms): void
  {
    global $astman;
    if (!$astman->connected()) {
      $out = $astman->Command('sip show registry');
      echo $out['data'];
    } else {
      self::log_message("no asterisk manager connection");
      return;
    }

    self::debug_message("Received a message ".$sms->id);
    if (preg_match("/\+1([2-9]\d{2}[2-9]\d{6})/", $sms->to[0]->phone_number, $matches)) {
      // Find the recipient in astdb
      $to = $matches[1];

      $output = $astman->Command('database showkey accountcode');

      $count = preg_match_all("#AMPUSER/(\d+)/accountcode.*: ([\d,]*)\s*$#m", $output['data'], $extensions, PREG_SET_ORDER);

      self::debug_message($count." extensions");
      if ($count) {
        self::debug_message("To: ".$to);
        $msgFrom = str_replace("+", "", $sms->from->phone_number);
        foreach ($extensions as $ext) {
          $user=$ext[1];
          self::debug_message("User ".$user);
          $codes=explode(',', $ext[2]);
          foreach ($codes as $code) {
            self::debug_message("Code ".$code);
            if ($code == $to) {
              self::debug_message("Found a match, sending to ext: ".$user);
              $astman->MessageSend("pjsip:$user@127.0.0.1", $msgFrom, $sms->text);
            }
          }
        }
      } else {
        self::debug_message("Database entries:\n".$output);
        self::debug_message("Nowhere to send ".$sms->id);
      }
    }
  }

  const DEFAULT_TOLERANCE = 300;

  /**
   * Verifies the signature header sent by Telnyx.
   *
   * Throws an Exception\SignatureVerificationException exception if the verification fails for any reason.
   * (Exceptions commented out in this version)
   *
   * @param string $payload the payload sent by Telnyx
   * @param string $signature_header the contents of the signature header sent by Telnyx
   * @param string $timestamp
   * @param string $public_key secret used to generate the signature
   * @param int $tolerance maximum difference allowed between the header's timestamp and the current time
   *
   * @return bool
   *
   * @throws SodiumException
   */
  protected static function verifyHeader(string $payload, string $signature_header, string $timestamp, string $public_key, int $tolerance = self::DEFAULT_TOLERANCE) : bool
  {
    // Typecast timestamp to int for comparisons
    $timestamp = (int)$timestamp;

    // Check if timestamp is within tolerance
    if (($tolerance > 0) && (abs(time() - $timestamp) > $tolerance)) {
//      throw Exception\SignatureVerificationException::factory(
//          'Timestamp outside the tolerance zone',
//          $payload,
//          $signature_header
//      );
      return false;
    }

    // Convert base64 string to bytes for sodium crypto functions
    $public_key_bytes = base64_decode($public_key);
    $signature_header_bytes = base64_decode($signature_header);

    // Construct a message to test against the signature header using the timestamp and payload
    $signed_payload = $timestamp.'|'.$payload;

    if (!sodium_crypto_sign_verify_detached($signature_header_bytes, $signed_payload, $public_key_bytes)) {
//      throw Exception\SignatureVerificationException::factory(
//          'Signature is invalid and does not match the payload',
//          $payload,
//          $signature_header
//      );
      return false;
    }

    return true;
  }

  /**
   * @throws Exception
   */
  public function update(object $message):bool
  {
    $msgDB = $this->open_message_db();

    $msgDB->exec("BEGIN;");

    $is_new = $this->lookup_message_id($msgDB, $message->id) === null;

    try {
      $stmtInsert = $msgDB->prepare("
        INSERT INTO TelnyxMessage
          (profile_id, message_id, direction_id, cost_amount, cost_currency,
           received_time, sent_time, completed_time, body_text)
        VALUES
          (:profile_id, :message_id, :direction_id, :cost_amount, :cost_currency,
           :received_time, :sent_time, :completed_time, :body_text);");

/*
        ON CONFLICT(message_id) DO UPDATE SET
          cost_amount = IFNULL(excluded.cost_amount, cost_amount),
          cost_currency = IFNULL(excluded.cost_currency, cost_currency),
          received_time = IFNULL(excluded.received_time, received_time),
          sent_time = IFNULL(excluded.sent_time, sent_time),
          completed_time = IFNULL(excluded.completed_time, completed_time),
          body_text = IFNULL(excluded.body_text, body_text);");
*/

      if ($stmtInsert !== false) {
        $stmtInsert->bindValue(":profile_id", $this->lookup_id($msgDB, "TelnyxMessageProfile", $message->messaging_profile_id));
        $stmtInsert->bindValue(":message_id", $message->id);
        $stmtInsert->bindValue(":direction_id", $this->lookup_id($msgDB, "TelnyxMessageDirection", $message->direction));
        if ($message->cost != null) {
          $stmtInsert->bindValue(":cost_amount", $message->cost->amount);
          $stmtInsert->bindValue(":cost_currency", $message->cost->currency);
        } else {
          $stmtInsert->bindValue(":cost_amount", null);
          $stmtInsert->bindValue(":cost_currency", null);
        }

        $stmtInsert->bindValue(":received_time", $message->received_at != null ? strtotime($message->received_at) : null);
        $stmtInsert->bindValue(":sent_time", $message->sent_at != null ? strtotime($message->sent_at) : null);
        $stmtInsert->bindValue(":completed_time", $message->completed_at != null ? strtotime($message->completed_at) : null);

        $stmtInsert->bindValue(":body_text", $message->text);

        $stmtInsert->execute();
      }
    } catch (Exception $e) {
      if (preg_match('/unique/i', $e->getMessage()) != 1) {
        self::debug_message("PDO Exception ".$e->getCode().": ".$e->getMessage()." (".$e->getFile().":".$e->getLine().")");
        $msgDB->exec("ROLLBACK;");
        throw $e;
      }
      $stmtModify = $msgDB->prepare("UPDATE TelnyxMessage SET ".
          substr(
            ($message->cost != null ? "cost_amount=:cost_amount,cost_currency=:cost_currency," : "").
            ($message->received_at != null ? "received_time=:received_time," : "").
            ($message->sent_at != null ? "sent_time=:sent_time," : "").
            ($message->completed_at != null ? "completed_time=:completed_time," : "").
            ($message->text != null ? "body_text=:body_text," : ""), 0, -1).
          " WHERE message_id = :message_id;");

      if ($stmtModify !== false) {
        $stmtModify->bindValue(":message_id", $message->id);
        if ($message->cost != null) {
          $stmtModify->bindValue(":cost_amount", $message->cost->amount);
          $stmtModify->bindValue(":cost_currency", $message->cost->currency);
        }

        if ($message->received_at != null) {
          $stmtModify->bindValue(":received_time", strtotime($message->received_at));
        }
        if ($message->sent_at != null) {
          $stmtModify->bindValue(":sent_time", strtotime($message->sent_at));
        }
        if ($message->completed_at != null) {
          $stmtModify->bindValue(":completed_time", strtotime($message->completed_at));
        }

        if ($message->text != null) {
          $stmtModify->bindValue(":body_text", $message->text);
        }

        $stmtModify->execute();
      }
    }

    $msg_id = $this->lookup_message_id($msgDB, $message->id);

    if ($msg_id != null) {
      $this->process_endpoints($msgDB, $msg_id, "from", array($message->from));
      $this->process_endpoints($msgDB, $msg_id, "to", $message->to);
      $this->process_endpoints($msgDB, $msg_id, "cc", $message->cc);
    }

    $msgDB->exec("COMMIT;");

    $this->close_message_db($msgDB);

    return $is_new;
  }

  /**
   * @throws Exception
   */
  protected function process_endpoints(PDO $msgDB, string $message_id, string $usage, array $endpoints):bool
  {
    $success = true;

    $insertEndpointMapSql = "
        INSERT INTO TelnyxEndpointMap 
          (usage_id, message_id, endpoint_id, delivery_status) 
        VALUES 
          (:usage_id, :message_id, :endpoint_id, :delivery_status);";

    /*
        ON CONFLICT(usage_id, message_id, endpoint_id) DO UPDATE SET 
          delivery_status = CASE WHEN excluded.delivery_status NOT NULL THEN excluded.delivery_status ELSE delivery_status END;";
    */

    $stmtMapInsert = $msgDB->prepare($insertEndpointMapSql);
    if ($stmtMapInsert !== false) {
      $usage_id = $this->lookup_id($msgDB, "TelnyxEndpointUsage", $usage);

      $stmtMapInsert->bindValue(":message_id", $message_id);
      $stmtMapInsert->bindValue(":usage_id", $usage_id);

      foreach ($endpoints as $endpoint) {
        $endpoint_id = $this->lookup_endpoint_id($msgDB, $endpoint->phone_number, $endpoint->carrier, $endpoint->line_type);

        $stmtMapInsert->bindValue(":endpoint_id", $endpoint_id);
        $stmtMapInsert->bindValue(":delivery_status", property_exists($endpoint, "status") ? $endpoint->status : null);

        try {
          $result = $stmtMapInsert->execute();

          $success = $result !== false;

          if (!$success) {
            break;
          }
        } catch (Exception $e) {
          if (preg_match('/unique/i', $e->getMessage()) != 1) {
            self::log_message("PDO Exception ".$e->getCode().": ".$e->getMessage()." (".$e->getFile().":".$e->getLine().")",
            "Message ID: $message_id, Usage: $usage, Endpoint ID: $endpoint_id");

            throw $e;
          }

          if (property_exists($endpoint, "status") && $endpoint->status != null) {
            $stmtMapModify = $msgDB->prepare("
                UPDATE TelnyxEndpointMap 
                SET delivery_status = :delivery_status 
                WHERE message_id = :message_id and usage_id = :usage_id and endpoint_id = :endpoint_id;");

            if ($stmtMapModify !== false) {
              $stmtMapModify->bindValue(":message_id", $message_id);
              $stmtMapModify->bindValue(":usage_id", $usage_id);
              $stmtMapModify->bindValue(":endpoint_id", $endpoint_id);
              $stmtMapModify->bindValue(":delivery_status", $endpoint->status);

              try {
                $stmtMapModify->execute();
              } catch (Exception $e) {
                self::log_message("PDO Exception " . $e->getCode() . ": " . $e->getMessage() . " (" . $e->getFile() . ":" . $e->getLine() . ")",
                    "Message ID: $message_id, Usage: $usage, Endpoint ID: $endpoint_id");
              }
            }
          }
        }
      }
    }
    return $success;
  }

  public function export(int &$skip, int $count, $csv=false, $headings=true):array
  {
    $msgDB = $this->open_message_db();

    $fetchSQL = "
    SELECT TMP.value AS profile_id, TM.message_id, TMD.value AS direction, phone_number AS from_number, to_number, 
        cost_amount, cost_currency,
        datetime(received_time, 'unixepoch', 'localtime') AS 'received_time',
        datetime(sent_time, 'unixepoch', 'localtime') AS 'sent_time',
        datetime(completed_time, 'unixepoch', 'localtime') AS 'completed_time',
        to_delivery_status AS delivery_status 
        FROM TelnyxEndpointMap FTEM
        INNER JOIN TelnyxEndpointUsage FTEU on FTEM.usage_id = FTEU.id and FTEU.value = 'from'
        INNER JOIN TelnyxEndpoint FTE ON FTEM.endpoint_id = FTE.id
        INNER JOIN TelnyxMessage TM on FTEM.message_id = TM.id
        INNER JOIN TelnyxMessageProfile TMP on TMP.id = TM.profile_id
        INNER JOIN TelnyxMessageDirection TMD on TMD.id = direction_id
        INNER JOIN (SELECT message_id AS to_message_id, phone_number AS to_number, delivery_status AS to_delivery_status FROM TelnyxEndpointMap TTEM
            INNER JOIN TelnyxEndpointUsage TTEU on TTEM.usage_id = TTEU.id and TTEU.value = 'to'
            INNER JOIN TelnyxEndpoint TTE ON TTEM.endpoint_id = TTE.id) ON TM.id = to_message_id
    ORDER BY received_time, sent_time, completed_time LIMIT :skip, :count;";

    $stmtQuery = $msgDB->prepare($fetchSQL);

    $result_rows = array();

    if ($stmtQuery !== false) {
      $stmtQuery->bindValue(":skip", $skip);
      $stmtQuery->bindValue(":count", $count);

      $result = $stmtQuery->execute();
      if ($result !== false) {
      while (($row = $stmtQuery->fetch(PDO::FETCH_ASSOC)) !== false) {
          $result_rows[] = $row;
        }
      }

      $headings = $headings && $skip == 0;
      $skip += count($result_rows);

      if ($csv && count($result_rows) > 0) {
        $result_rows = $this->ArrayToCSV($result_rows, $headings);
      }
    }

    $this->close_message_db($msgDB);

    return $result_rows;
  }

  public function export_message(string $message_id):string
  {
    $msgDB = $this->open_message_db();

    $fetchSQL = "
    SELECT body_text
        FROM TelnyxMessage
        WHERE message_id = :id;";

    $stmtQuery = $msgDB->prepare($fetchSQL);

    $body_text = "";

    if ($stmtQuery !== false) {
      $stmtQuery->bindValue(":id", $message_id);

      $result = $stmtQuery->execute();
      if ($result !== false) {
        if (($row = $stmtQuery->fetch(PDO::FETCH_NUM)) !== false) {
          $body_text = $row[0];
        }
      }
    }

    $this->close_message_db($msgDB);

    return $body_text;
  }

  protected function ArrayToCSV(array $source, bool $headings=true):?array
  {
    $result = array();

    if (count($source) > 0) {
      if ($headings) {
        $result[] = implode(",", array_keys($source[0]));
      }

      foreach ($source as $row) {
        $result[] = implode(",", array_map("self::MapCSVValues", $row));
      }
    }

    return $result;
  }

  protected static function MapCSVValues($value)
  {
    if (is_string($value)) {
      $value = str_replace('\\"', '"', $value);
      $value = str_replace('"', '\"', $value);
      $value = '"'.$value.'"';
    }

    return $value;
  }

  protected function lookup_id(PDO $msgDB, string $table, string $value):?int
  {
    $id = null;

    $stmtQuery = $msgDB->prepare("SELECT id FROM $table WHERE value = :value;");
    if ($stmtQuery !== false) {
      $stmtQuery->bindValue(":value", $value);
      $result = $stmtQuery->execute();
      if ($result !== false) {
        $row = $stmtQuery->fetch(PDO::FETCH_NUM);

        if ($row === false) {
          $stmtInsert = $msgDB->prepare("INSERT INTO $table ( value ) VALUES (:value);");
          if ($stmtInsert !== false) {
            $stmtInsert->bindValue(":value", $value);
            $result = $stmtInsert->execute();

            if ($result !== false) {
              $id = $msgDB->lastInsertID();
            }
          }
        } else {
          $id = $row[0];
        }
      }
    }
    return $id;
  }

  protected function lookup_value(PDO $msgDB, string $table, int $id):?string
  {
    $querySql = "SELECT value FROM $table WHERE id = $id;";

    $stmtQuery = $msgDB->query($querySql,PDO::FETCH_NUM);

    $row = $stmtQuery->fetch(PDO::FETCH_NUM);

    return $row[0];
  }

  /** @noinspection DuplicatedCode */
  public function lookup_message_id(PDO $msgDB, string $telnyx_message_id):?int
  {
    $id = null;

    $stmtQuery = $msgDB->prepare("SELECT id FROM TelnyxMessage WHERE message_id = :message_id;");
    $stmtQuery->bindValue(":message_id", $telnyx_message_id);
    $result = $stmtQuery->execute();
    if ($result) {
      $row = $stmtQuery->fetch(PDO::FETCH_NUM);

      if ($row !== false) {
        $id = $row[0];
      }
    }
    return $id;
  }

  protected function lookup_endpoint_id(PDO $msgDB, $phone_number, $carrier, $line_type):?int
  {
    $id = null;

    $stmtQuery = $msgDB->prepare("SELECT id FROM TelnyxEndpoint WHERE phone_number = :phone_number;");
    if ($stmtQuery !== false) {
      $stmtQuery->bindValue(":phone_number", $phone_number);
      $result = $stmtQuery->execute();
      if ($result !== false) {
        $row = $stmtQuery->fetch(PDO::FETCH_NUM);

        if ($row === false) {
          $stmtInsert = $msgDB->prepare("INSERT INTO TelnyxEndpoint ( phone_number, carrier_id, line_type_id ) VALUES (:phone_number, :carrier_id, :line_type_id);");
          if ($stmtInsert !== false) {
            $stmtInsert->bindValue(":phone_number", $phone_number);
            $stmtInsert->bindValue(":carrier_id", $this->lookup_id($msgDB,"TelnyxCarrier", $carrier));
            $stmtInsert->bindValue(":line_type_id", $this->lookup_id($msgDB,"TelnyxLineType", $line_type));
            $result = $stmtInsert->execute();

            if ($result !== false) {
              $id = $msgDB->lastInsertID();
            }
          }
        } else {
          $id = $row[0];
        }
      }
    }
    return $id;
  }
}
