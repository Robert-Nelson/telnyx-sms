<?php

class AMI_Client
{
  protected $ami_connection;
  protected int $action_id;
  protected static string $Logfile = '/var/log/apache2/sms.log'; // all SMSes and errors will be logged here for debugging purposes

  public function __construct()
  {
    $this->ami_connection = null;
    $this->action_id = 1;
  }

  static function set_logfile($filename)
  {
    self::$Logfile = $filename;
  }

  static function log_message(string $message, ?string $extra = null)
  {
    $fh = fopen(self::$Logfile, "a");
    fwrite($fh, date(DATE_W3C).": ".$message."\n");
    if ($extra != null) {
      fwrite($fh, "\n$extra\n\n");
    }
    fclose($fh);
  }

  protected static function notify_cb(
      int $notification_code,
      int $severity,
      string $message,
      int $message_code,
      int $bytes_transferred,
      int $bytes_max
  ):void
  {
    self::log_message( "code: $notification_code, severity: $severity, message_code: $message_code, message: $message, transferred: $bytes_transferred/$bytes_max");
  }

  public function ami_connect(string $host_url):bool
  {
    $errno = null;
    $errmsg = null;

    $stream_context = stream_context_create(null, [ 'notification'=>'notify_cb' ]);

    $this->ami_connection = stream_socket_client($host_url, $errno, $errmsg, null, STREAM_CLIENT_CONNECT, $stream_context);

    if (($line = stream_get_line($this->ami_connection, 4096, "\r\n")) !== false) {
      self::log_message($line);
    }

    return true;
  }

  function ami_login(string $user, string $secret):bool
  {
    $headers = [
        "Username" => $user,
        "Secret" => $secret
    ];

    $response = [];
    $result = $this->process_action("Login", $headers, $response);

    if ($response['Response'] == "Success") {
      $result = true;
    } else {
      $this->dump_response($response);
    }

    return $result;
  }

  function ami_logoff():bool
  {
    $headers = [];

    $response = [];
    $success = $this->process_action("logoff", $headers, $response);

    if ($response['Response'] == "Goodbye") {
      $success = true;
    } else {
      $this->dump_response($response);
    }

    return $success;
  }

  function ami_message_send(string $from, string $to, string $body):bool
  {
    $headers = [
        "To" => $to,
        "From" => $from
    ];

    if (strpbrk($body, "\r\n")) {
      $headers['Base64Body'] = base64_encode($body);
    } else {
      $headers['Body'] = $body;
    }

    $response = [];
    $success = $this->process_action("MessageSend", $headers, $response);

    if ($response['Response'] == "Success") {
      $success = true;
    } else {
      $this->dump_response($response);
    }

    return $success;
  }

  protected function process_action(string $action, array $headers, array &$response):bool
  {
    $msg = "Action: $action\r\nActionID: ".$this->action_id."\r\n";
    $this->action_id += 1;

    foreach (array_keys($headers) as $key) {
      $msg = $msg.$key.": ".$headers[$key]."\r\n";
    }
    $msg .= "\r\n";

    fwrite($this->ami_connection, $msg);

    $response = [];
    while (($line = stream_get_line($this->ami_connection, 4096, "\r\n")) !== false) {
      if ($line == "") {
        break;
      }
      // self::log_message("AMI: response: ".$line);
      $resp_parts = explode(": ", $line);
      if (sizeof($resp_parts) == 2) {
        $response[$resp_parts[0]] = $resp_parts[1];
      } else {
        self::log_message("Invalid response line format ".$line);
      }
    }

    $success = $response['Response'] == "Success";

    return $success;
  }

  protected function dump_response(array $response):void
  {
    foreach (array_keys($response) as $key) {
      self::log_message($key.": ".$response[$key]."\r\n");
    }
  }
}
