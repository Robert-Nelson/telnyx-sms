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
use SimplePie\Exception;

class Telnyx_sms extends FreePBX_Helpers implements BMO {
  protected object $FreePBX;
  protected object $db;

  public function __construct($freepbx){
    parent::__construct($freepbx);
    $this->FreePBX = $freepbx;
    $this->db = $freepbx->Database();
  }

  //Install method. use this or install.php using both may cause weird behavior
  public function install() {}
  //Uninstall method. use this or install.php using both may cause weird behavior
  public function uninstall() {}
  //Not yet implemented
  public function backup() {}
  //not yet implimented
  public function restore($backup) {}
  //process form
  public function doConfigPageInit($page) {
    $this->doGeneralPost();
  }

  public function getTelnyxToken():string {
    try {
      return $this->getConfig("telnyx-token");
    } catch (\Exception $e) {

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
          } catch (\Exception $e) {
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
          $cid = json_decode($_POST['extcid']);
          dbug("object_vars", get_object_vars($cid), 1);
          $this->writeCID(get_object_vars($cid));
        }
        if (isset($_POST['extnumbers'])) {
          dbug("action = smsext - numbers", $_POST['extnumbers'], 1);
          $num = json_decode($_POST['extnumbers']);
          $this->writeExtNumbers(get_object_vars($num));
        }
      }
    }
  }

  public function writeCID($extCID) {
    $extens = array_keys($extCID);
    $place_holders = '?' . str_repeat(', ?', count($extens) - 1);
    $sql = "DELETE FROM smscid WHERE Exten NOT IN ($place_holders);";
    $stmt = $this->db->prepare($sql);
    dbug($sql);
    $result = $stmt->execute($extens);
    dbug("result", $result);
    $sql = 'REPLACE INTO smscid (Exten, Phone_ID) VALUES ';
    foreach ($extens as $ext) {
      $sql .= "(\"$ext\", $extCID[$ext]), ";
    }
    $sql = substr($sql, 0, -2);
    $sql .= ";";
    $this->db->prepare($sql);
    dbug($sql);
    $result = $stmt->execute();
    dbug("result", $result);
    return $result;
  }

  public function writeExtNumbers($extnumbers) {
    $extens = array_keys($extnumbers);
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

    $sql = 'REPLACE INTO smsextens (Exten, Phone_ID) VALUES ';
    foreach ($extens as $ext) {
      foreach ($extnumbers[$ext] as $phoneID) {
        $sql .= "($ext, $phoneID), ";
      }
    }
    $sql = substr($sql, 0,-2);
    $sql .= ";";
    $stmt = $this->db->prepare($sql);
    dbug($sql);
    $result = $stmt->execute();
    dbug("result", $result);
    return $result;
  }

  public function addNumber($number):bool {
    $sql = 'INSERT INTO `smsnumbers` (Phone) Values (?)';
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
    $res = null;
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
    } catch (\Exception $e) {

    }
    $vars['db'] = $this->db;
    return load_view(__DIR__.'/views/grid.php', $vars);
  }
}
