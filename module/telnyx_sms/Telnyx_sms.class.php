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

class Telnyx_sms extends FreePBX_Helpers implements BMO {
  protected $Freepbx;
  protected $db;

  public function __construct($freepbx){
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

  public function getTelnyxToken() {
    return $this->getConfig("telnyx-token");
  }

  //This shows the submit buttons
  public function getActionBar($request) {
    $buttons = array();
    switch($_GET['display']) {
      case 'telnyx_sms':
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
      break;
    }
    return $buttons;
  }

  public function doGeneralPost()
  {
    dbug("doGeneralPost", $_POST, 1);

    if (!isset($_REQUEST['Submit'])) {
      return;
    }

    if (isset($_POST['telnyx_token'])) {
      $this->setConfig("telnyx-token", $_POST['telnyx_token']);
    }

    if (isset($_POST['add'])) {
      $newNumbers = $_POST['add'];
      foreach ($newNumbers as $number) {
        $this->addNumber($number);
      }
    }

    if (isset($_POST['delete'])) {
      $deletedIds = $_POST['delete'];
      foreach ($deletedIds as $id) {
        $this->delNumber($id);
      }
    }
  }

  public function addNumber($number) {
    $sql = 'INSERT INTO `smsnumbers` (Phone) Values (?)';
    $bindvalues = array($number);

    $sth = $this->db->prepare($sql);
    return $sth->execute($bindvalues);
  }

  public function delNumber($id) {
    $sql = 'DELETE FROM`smsnumbers` WHERE ID = ?';
    $bindvalues = array($id);

    $sth = $this->db->prepare($sql);
    return $sth->execute($bindvalues);
  }

  public function getDetails() {
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
    $res = is_array($res)?$res:array();
    return $res;
  }

  public function ajaxRequest($req, &$setting) {
    dbug('ajaxRequest - req', $req, 1);
    dbug('ajaxRequest - setting', $setting, 1);
    switch ($req) {
      case 'getJSON':
        return true;
        break;
      case 'addNumber':
      case 'delNumber':
        return true;
        break;
      default:
        return false;
        break;
    }
  }
  public function ajaxHandler(){
    dbug("ajaxHandler - command", $_REQUEST['command']);
    switch ($_REQUEST['command']) {
      case 'getJSON':
        dbug("ajaxHandler - jdata", $_REQUEST['jdata']);
        switch ($_REQUEST['jdata']) {
          case 'grid':
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
       break;

      default:
        return false;
        break;
    }
  }

  public function getRightNav($request) {
    $html = 'your custom html';
    return $html;
  }

  public function showPage()
  {
    $vars['telnyx_token'] = $this->getConfig('telnyx-token');
    return load_view(__DIR__ . '/views/grid.php', $vars);
  }
}
