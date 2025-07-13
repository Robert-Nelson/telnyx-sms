<?php
namespace FreePBX\modules;
/*
 * Class stub for BMO Module class
 * In _Construct you may remove the database line if you don't use it
 * In getActionbar change extdisplay to align with whatever variable you use to decide if the page is in edit mode.
 *
 */

use BMO;
use FreePBX_Helpers;

require_once(__DIR__ . '/TelnyxMessage.class.php');
require_once(__DIR__ . '/functions.inc.php');

class Telnyx_sms extends FreePBX_Helpers implements BMO {
  protected object $FreePBX;
  protected object $db;

  public function __construct($freepbx){
    parent::__construct($freepbx);
    // $this->FreePBX = $freepbx;
    // $this->db = $freepbx->Database();
  }

  //Install method. use this or install.php using both may cause weird behavior
  public function install() {
    global $amp_conf;

    // Retrieve database and table name if defined, otherwise use FreePBX default
    $db_name = !empty($amp_conf['TSMSDBNAME'])?$amp_conf['TSMSDBNAME']:"telnyx_messages";

    // if TSMSDBHOST and TSMSDBTYPE are not empty then we assume an external connection and don't use the default connection
    //
    if (!empty($amp_conf["TSMSDBHOST"]) && !empty($amp_conf["TSMSDBTYPE"])) {
      $db_hash = ['mysql' => 'mysql', 'postgres' => 'pgsql'];
      $db_type = $db_hash[$amp_conf["TSMSDBTYPE"]];
      $db_host = $amp_conf["TSMSDBHOST"];
      $db_port = empty($amp_conf["TSMSDBPORT"]) ? '' :  ':' . $amp_conf["TSMSDBPORT"];
      $db_user = empty($amp_conf["TSMSDBUSER"]) ? $amp_conf["AMPDBUSER"] : $amp_conf["TSMSDBUSER"];
      $db_pass = empty($amp_conf["TSMSDBPASS"]) ? $amp_conf["AMPDBPASS"] : $amp_conf["TSMSDBPASS"];
      $datasource = $db_type . '://' . $db_user . ':' . $db_pass . '@' . $db_host . $db_port . '/' . $db_name;
      $dbtsms = DB::connect($datasource); // attempt connection
      if (DB::isError($dbtsms)) {
        die_freepbx($dbtsms->getDebugInfo());
      }
      $dbtsms = null;
    }

// if (! function_exists("out")) {
//   function out($text):void {
//      echo $text."<br />";
//   }
// }

    global $amp_conf;

    $db_hash = ['mysql' => 'mysql', 'postgres' => 'pgsql'];
    $dbt = !empty($dbt) ? $dbt : 'mysql';
    $db_type = $db_hash[$dbt];
    $db_name = !empty($db_name) ? $db_name : "telnyx_messages";
    $db_host = empty($db_host) ?  $amp_conf['AMPDBHOST'] : $db_host;
    $db_port = empty($db_port) ? '' :  ';port=' . $db_port;
    $db_user = empty($db_user) ? $amp_conf['AMPDBUSER'] : $db_user;
    $db_pass = empty($db_pass) ? $amp_conf['AMPDBPASS'] : $db_pass;

//$pdo = new Database($db_type.':host='.$db_host.$db_port.';dbname='.$db_name,$db_user,$db_pass);

    $pdo = new $this->FreePBX->Database($db_type.':host='.$db_host.$db_port.';dbname='.$db_name);

    $CreateTables = array(
        "CREATE TABLE IF NOT EXISTS TelnyxMessageProfile (
          id INTEGER AUTO_INCREMENT PRIMARY KEY,
          value TEXT UNIQUE);",
        "CREATE TABLE IF NOT EXISTS TelnyxMessageDirection (
          id INTEGER AUTO_INCREMENT PRIMARY KEY,
          value TEXT UNIQUE);",
        "CREATE TABLE IF NOT EXISTS TelnyxCarrier (
          id INTEGER AUTO_INCREMENT PRIMARY KEY,
          value TEXT UNIQUE);",
        "CREATE TABLE IF NOT EXISTS TelnyxLineType (
          id INTEGER AUTO_INCREMENT PRIMARY KEY,
          value TEXT UNIQUE);",
        "CREATE TABLE IF NOT EXISTS TelnyxEndpoint (
          id INTEGER AUTO_INCREMENT PRIMARY KEY,
          phone_number TEXT UNIQUE,
          carrier_id INTEGER,
          line_type_id INTEGER,
          INDEX (carrier_id),
          INDEX (line_type_id),
          CONSTRAINT FOREIGN KEY (carrier_id) REFERENCES TelnyxCarrier (id)
            ON DELETE RESTRICT,
          CONSTRAINT FOREIGN KEY (line_type_id) REFERENCES TelnyxLineType (id)
            ON DELETE RESTRICT);",
        "CREATE TABLE IF NOT EXISTS TelnyxMessage (
          id INTEGER AUTO_INCREMENT PRIMARY KEY,
          profile_id INTEGER NOT NULL,
          message_id CHAR(36) NOT NULL UNIQUE,
          direction_id INTEGER NOT NULL,
          cost_amount REAL,
          cost_currency TEXT,
          received_time INTEGER,
          sent_time INTEGER,
          completed_time INTEGER,
          body_text TEXT,
          INDEX (profile_id),
          INDEX (direction_id),
          CONSTRAINT FOREIGN KEY (profile_id) REFERENCES TelnyxMessageProfile (id)
            ON DELETE RESTRICT,
          CONSTRAINT FOREIGN KEY (direction_id) REFERENCES TelnyxMessageDirection (id)
            ON DELETE RESTRICT);",
        "CREATE TABLE IF NOT EXISTS TelnyxEndpointUsage (
          id INTEGER AUTO_INCREMENT PRIMARY KEY,
          value TEXT UNIQUE);",
        "CREATE TABLE IF NOT EXISTS TelnyxEndpointMap (
          id INTEGER AUTO_INCREMENT PRIMARY KEY,
          usage_id INTEGER,
          message_id INTEGER,
          endpoint_id INTEGER,
          delivery_status TEXT,
          INDEX (usage_id),
          INDEX (message_id),
          INDEX (endpoint_id),
          CONSTRAINT FOREIGN KEY (usage_id) REFERENCES TelnyxEndpointUsage (id)
            ON DELETE RESTRICT,
          CONSTRAINT FOREIGN KEY (message_id) REFERENCES TelnyxMessage (id)
            ON DELETE RESTRICT,
          CONSTRAINT FOREIGN KEY (endpoint_id) REFERENCES TelnyxEndpoint (id)
            ON DELETE RESTRICT,
          CONSTRAINT unique_endpoint UNIQUE (usage_id, message_id, endpoint_id));",
        "CREATE TABLE IF NOT EXISTS TelnyxMessageError (
          id INTEGER AUTO_INCREMENT PRIMARY KEY,
          message_id INTEGER,
          error_text TEXT,
          INDEX (message_id),
          CONSTRAINT FOREIGN KEY (message_id) REFERENCES TelnyxMessage (id)
            ON DELETE RESTRICT);"
    );

    foreach ($CreateTables as $tabledef) {
      $count = $pdo->exec($tabledef);

      if ($count === false) {
        out("Unable to create table, definition = $tabledef, code = ".$pdo->errorCode()."(".$pdo->errorInfo().")", true);
        return 1;
      }
    }

    $pmsg = new \TelnyxMessage();

    $pmsg->init_lookup_table();

    return 0;
  }

  //Uninstall method. use this or install.php using both may cause weird behavior
  public function uninstall() {}

  //Not yet implemented
  public function backup() {}

  //not yet implimented
  public function restore($backup) {}

  // Dialplan hooks
  public static function myDialplanHooks() {
    return true;
  }

// 'true' references the default priority of '500'
  public function doDialplanHook(&$ext, $engine, $priority) {
    $id = 'telnyx-sms';
    $ext->addSectionNoCustom($id, true);
//    $ext->addSectionComment($id, 'This is a local 3-digit extension so we just want to send it internally');
    $ext->add($id, '_XXX', '', new \ext_goto('1', 'local-${EXTEN}'));
    // Deliver to PSTN - adjust pattern to match your needs
    // These are normalized so that we are working with 10-digit US/CAN numbers and then reformatted
    $ext->add($id, '_+1NXXNXXXXXX', '', new \ext_goto(1, '${EXTEN:2}'));
    $ext->add($id, '_1NXXNXXXXXX', '', new \ext_goto(1, '${EXTEN:1}'));
//    $ext->add($id, '_NXXNXXXXXX', '', new \ext_verbose(0, 'Sending SMS to ${EXTEN} from ${MESSAGE(from)}'));
    $ext->add($id, '_NXXNXXXXXX', '', new \ext_noop('Sending SMS to ${EXTEN} from ${MESSAGE(from)}'));
    $ext->add($id, '_NXXNXXXXXX', '', new \ext_set('FROMUSER', '${CUT(MESSAGE(from),<,2)}'));
    $ext->add($id, '_NXXNXXXXXX', '', new \ext_set('FROMUSER', '${CUT(FROMUSER,@,1)}'));
    $ext->add($id, '_NXXNXXXXXX', '', new \ext_set('FROMUSER', '${CUT(FROMUSER,:,2)}'));
    $ext->add($id, '_NXXNXXXXXX', '', new \ext_set('CALLERID(num)', '${FROMUSER}'));
    $ext->add($id, '_NXXNXXXXXX', '', new \ext_set('SMSCID', '${DB(TELNYX-SMS/${CALLERID(num)}/cid)}'));
    $ext->add($id, '_NXXNXXXXXX', '', new \ext_execif('$["foo${SMSCID}" == "foo"]', 'Goto', 'nocid,1', 'Set', 'FROM=${SMSCID}'));
//    $ext->add($id, '_NXXNXXXXXX', '', new \ext_verbose(0, 'Using external caller ID of ${FROM}'));
    $ext->add($id, '_NXXNXXXXXX', '', new \ext_noop('Using external caller ID of ${FROM}'));
    $ext->add($id, '_NXXNXXXXXX', '', new \ext_set('MESSAGE_BODY','${URIENCODE(${MESSAGE(body)})}'));
    $ext->add($id, '_NXXNXXXXXX', '', new \ext_system('php '.__DIR__.'/telnyx-send.php ${EXTEN} ${FROM} ${MESSAGE_BODY}'));
    $ext->add($id, '_NXXNXXXXXX', '', new \ext_hangup());
    //
    $ext->add($id, 'nocid', '', new \ext_set('MESSAGE(body)', 'This extension is not configured for sending SMS messages.'));
    $ext->add($id, 'nocid', '', new \ext_messagesend('pjsip:${CALLERID(num)}','${MESSAGE(from)}'));
    $ext->add($id, 'nocid', '', new \ext_hangup());
    //
    // Deliver to local 4-digit extension. If you use 3, 5 or other length extensions, adjust accordingly.
    // to +1 E164 in the outbound script. Rework it according to your preferences.
    $ext->add($id, '_local-X.', '', new \ext_set('FROMUSER', '${CUT(MESSAGE(from),<,2)}'));
    $ext->add($id, '_local-X.', '', new \ext_set('FROMUSER', '${CUT(FROMUSER,@,1)}'));
    $ext->add($id, '_local-X.', '', new \ext_set('FROMUSER', '${CUT(FROMUSER,:,2)}'));
    $ext->add($id, '_local-X.', '', new \ext_set('FROMUSER', '${REPLACE(FROMUSER,+)}'));
    $ext->add($id, '_local-X.', '', new \ext_set('TODEVICE', '${DB(DEVICE/${EXTEN:6}/dial)}'));
    $ext->add($id, '_local-X.', '', new \ext_set('TODEVICE', '${TOLOWER(${STRREPLACE(TODEVICE,"/",":")})}'));
    $ext->add($id, '_local-X.', '', new \ext_messagesend('${TODEVICE}', '${FROMUSER}'));
    $ext->add($id, '_local-X.', '', new \ext_execif('$["${MESSAGE_SEND_STATUS}" == "FAILURE"]', 'Goto', 'mail-${EXTEN:6},1'));
    $ext->add($id, '_local-X.', '', new \ext_hangup());
    // This could be improved. Any undeliverable SMS just gets sent to a catch-all email address. You
    // could look up the extension user's email and send the message to their specific address instead.
//    $ext->add($id, '_mail-X.', '', new \ext_verbose(0, 'Sending mail'));
    $ext->add($id, '_mail-X.', '', new \ext_noop('Sending mail'));
    $ext->add($id, '_mail-X.', '',new \ext_system('echo "Text message from ${MESSAGE(from)} to ${EXTEN:5} - ${MESSAGE(body)}" | mail -s "New text received while offline" robert-sms@nelson.house'));
    $ext->add($id, '_mail-X.', '', new \ext_hangup());
  }

  // File Hooks
  public function genConfig() {
    global $core_conf, $amp_conf, $version;

    $conf = [];

    if (isset($core_conf) && is_a($core_conf, "core_conf")) {
      $ResOdbc['enabled'] = 'yes';
      $ResOdbc['dsn'] = 'MySQL-telnyx_messages';
      $ResOdbc['pre-connect'] = 'yes';
      if ((version_compare($version, "14.0", "lt") && version_compare($version, "13.14.0", "ge")) || (version_compare($version, "14.0", "ge") && version_compare($version, "14.3.0", "ge"))) {
        $ResOdbc['max_connections'] = '5';
      } else {
        $ResOdbc['pooling'] = 'no';
        $ResOdbc['limit'] = '1';
      }

      $ResOdbc['username'] = !empty($amp_conf['TSMSDBUSER']) ? $amp_conf['TSMSDBUSER'] : $amp_conf['AMPDBUSER'];
      $ResOdbc['password'] = !empty($amp_conf['TSMSDBPASS']) ? $amp_conf['TSMSDBPASS'] : $amp_conf['AMPDBPASS'];
      $ResOdbc['database'] = !empty($amp_conf['TSMSDBNAME']) ? $amp_conf['TSMSDBNAME'] : 'telnyx_messages';

      $conf['ResOdbc'] = ['Telnyxsmsdb' => $ResOdbc ];
    }

    global $db, $astman;

    $sql = "SELECT Exten, Phone FROM smsnumbers INNER JOIN smscid ON smscid.Phone_ID = smsnumbers.ID;";
    $stmt = $db->prepare($sql);
    $result = $stmt->execute();
    $rows = $stmt->fetchall();

    $conf['telnyx-sms'] = $rows;

    dbug("genConfig", $conf);
    return $conf;
  }

  public function writeConfig($conf) {
    if (isset($core_conf) && is_a($core_conf, "core_conf")) {
      $section = 'telnyxsmsdb';
      foreach ($conf['ResOdbc'] as $section => $settings) {
        foreach ($settings as $key => $value) {
          $core_conf->addResOdbc($section, [$key => $value]);
        }
      }
    }

    global $astman;

    if ($astman->connected()) {
      $astman->database_deltree("TELNYX-SMS");
      foreach ($conf['telnyx-sms'] as $row) {
        $astman->database_put("TELNYX-SMS", "$row[0]/cid", $row[1]);
      }
    }
    return $conf;
  }

  //process form
  public function doConfigPageInit($page) {
    $this->doGeneralPost();
  }

  public function getTelnyxToken():string {
    try {
      return $this->getConfig("telnyx-token");
    } catch (Exception $e) {

    }
    return "";
  }

  //This shows the submit buttons
  public function getActionBar($request):array {
    $buttons = array();
    if ($_GET['display'] == 'telnyx_sms') {
      $buttons = array(
        'reset' => array(
          'name' => 'reset',
          'id' => 'reset',
          'value' => _('Reset')
        ),
        'submit' => array(
          'name' => 'submit',
          'id' => 'submit',
          'value' => _('Submit')
        )
      );
    }
    return $buttons;
  }

  public function doGeneralPost():void
  {
    dbug("doGeneralPost", $_POST, 1);

    if (!isset($_REQUEST['Submit'])) {
      dbug("Missing Submit");
      return;
    }

    if (isset($_REQUEST['action'])) {
      if ($_REQUEST['action'] == 'smsnum') {
        if (isset($_POST['telnyx_token'])) {
          try {
            $this->setConfig("telnyx-token", $_POST['telnyx_token']);
          } catch (Exception $e) {
          }
        }

        if (isset($_POST['addNumbers'])) {
          $newNumbers = $_POST['addNumbers'];
          foreach ($newNumbers as $number) {
            $this->addNumber($number);
          }
        }

        if (isset($_POST['deleteNumbers'])) {
          $deletedIds = $_POST['deleteNumbers'];
          foreach ($deletedIds as $id) {
            $this->delNumber($id);
          }
        }
      } else if ($_REQUEST['action'] == 'smsext') {
        if (isset($_POST['extcid'])) {
          dbug("action = smsext - cid", $_POST['extcid'], 1);
          $cid = json_decode($_POST['extcid'], true);
          dbug("object_vars", $cid, 1);
          $this->writeCID($cid);
        }
        if (isset($_POST['extnumbers'])) {
          dbug("action = smsext - numbers", $_POST['extnumbers'], 1);
          $num = json_decode($_POST['extnumbers'], true);
          $this->writeExtNumbers($num);
        }
      }
    }
  }

  public function writeCID($extCID) {
    $extens = array_keys($extCID);
    $this->db->beginTransaction();
    $place_holders = '?' . str_repeat(', ?', count($extens) - 1);
    $sql = "DELETE FROM smscid WHERE Exten NOT IN ($place_holders);";
    $stmt = $this->db->prepare($sql);
    dbug($sql);
    $result = $stmt->execute($extens);
    dbug("result", $result);

    $sql = "UPDATE sip SET data = '' WHERE keyword = 'message_context' AND data = 'telnyx-sms' AND id NOT IN ($place_holders);";
    $stmt = $this->db->prepare($sql);
    dbug($sql);
    $result = $stmt->execute($extens);
    dbug("result", $result);

    $sql = "REPLACE INTO smscid (Exten, Phone_ID) VALUES ";
    foreach ($extens as $ext) {
      $sql .= "(\"$ext\", $extCID[$ext]), ";
    }
    $sql = substr($sql, 0, -2);
    $sql .= ";";
    $stmt = $this->db->prepare($sql);
    dbug($sql);
    $result = $stmt->execute();
    dbug("result", $result);

    $sql = "UPDATE sip SET data = 'telnyx-sms' WHERE keyword = 'message_context' AND id IN ($place_holders);";
    $stmt = $this->db->prepare($sql);
    dbug($sql);
    $result = $stmt->execute($extens);
    dbug("result", $result);

    $this->db->commit();
    needreload();
    return $result;
  }

  public function writeExtNumbers($extnumbers) {
    $extens = array_keys($extnumbers);
    $this->db->beginTransaction();
    $sql = "DELETE FROM smsextens";
    if (count($extens) > 0) {
      $place_holders = '?' . str_repeat(', ?', count($extens) - 1);
      $sql .= " WHERE (Exten NOT IN ($place_holders)) OR ";
      foreach ($extens as $ext) {
        if (count($extnumbers[$ext]) > 0) {
          $phoneList = implode(",", $extnumbers[$ext]);
          $sql .= "(Exten = $ext AND Phone_id NOT IN ($phoneList)) OR ";
        } else {
          $sql .= "(Exten = $ext) OR ";
        }
      }
      $sql = substr($sql, 0, -3);
    }
    $sql .= ";";
    $stmt = $this->db->prepare($sql);
    dbug($sql);
    $result = $stmt->execute($extens);
    dbug("result", $result);

    if (count($extens) > 0) {
      $sql = null;
      foreach ($extens as $ext) {
        foreach ($extnumbers[$ext] as $phoneID) {
          if (is_null($sql)) {
            $sql = 'REPLACE INTO smsextens (Exten, Phone_ID) VALUES ';
          }

          $sql .= "($ext, $phoneID), ";
        }
      }
      $sql = substr($sql, 0, -2);
      $sql .= ";";
      $stmt = $this->db->prepare($sql);
      dbug($sql);
      $result = $stmt->execute();
      dbug("result", $result);
    }
    $this->db->commit();
    return $result;
  }

  public function addNumber($number):bool {
    $sql = 'REPLACE INTO smsnumbers (Phone) Values (?)';
    $bindvalues = array($number);

    $sth = $this->db->prepare($sql);
    return $sth->execute($bindvalues);
  }

  public function delNumber($id):bool {
    $sql = 'DELETE FROM`smsnumbers` WHERE ID = ?';
    $bindvalues = array($id);

    $sth = $this->db->prepare($sql);
    return $sth->execute($bindvalues);
  }

  public function getDetails():array {
    dbug("getDetails");
    $sql = 'SELECT * FROM smsnumbers';
    $bindvalues = array();
    $sql .= ' ORDER BY phone';
    dbug("sql", $sql);
    $sth = $this->db->prepare($sql);
    $sth->execute($bindvalues);
    dbug('execute', $sth, 1);
    $res = $sth->fetchAll();
    dbug('fetchall', $res, 1);
    return is_array($res) ? $res : array();
  }

  public function ajaxRequest($req, $setting):bool {
    dbug('ajaxRequest - req', $req, 1);
    dbug('ajaxRequest - setting', $setting, 1);
    return match ($req) {
      'getJSON', 'addNumber', 'delNumber' => true,
      default => false
    };
  }

  public function ajaxHandler(): bool|array {
    dbug("ajaxHandler - command", $_REQUEST['command']);
    if ($_REQUEST['command'] === 'getJSON') {
      dbug("ajaxHandler - jdata", $_REQUEST['jdata']);
      if ($_REQUEST['jdata'] == 'grid') {
        $phones = $this->getDetails();
        $ret = array();
        foreach ($phones as $r) {
          $ret[] = array(
              'phone' => $r['Phone'],
              'id' => $r['ID'],
              'link' => array($r['ID'], $r['Phone'])
          );
        }
        $ret[] = array(
          'phone' => '',
          'id' => 0,
          'link' => array(0, '')
        );
        dbug('ret', $ret, 1);
        return $ret;
      }
    }
    return false;
  }

/*
    public function getRightNav($request):string {
    $html = 'your custom html';
    return $html;
  }
*/

  public function showPage(): false|string
  {
    $vars = array();
    try {
      $vars['telnyx_token'] = $this->getConfig('telnyx-token');
    } catch (Exception $e) {

    }
    $vars['db'] = $this->db;
    return load_view(__DIR__.'/views/grid.php', $vars);
  }
}
