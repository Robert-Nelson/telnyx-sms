<?php
if (!defined('FREEPBX_IS_AUTH')) { die('No direct script access allowed'); }
//	License for all code of this FreePBX module can be found in the license file inside the module directory
//  Portions Copyright (C) 2011 Mikael Carlsson
//	Copyright 2013 Schmooze Com Inc.
//
// Update cdr database with did field
//
require_once(__DIR__ . '/TelnyxMessage.class.php');
require_once(__DIR__ . '/functions.inc.php');
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
  if(DB::isError($dbtsms)) {
    die_freepbx($dbtsms->getDebugInfo());
  }
  $dbtsms = null;
}

if (! function_exists("out")) {
  function out($text) {
    echo $text."<br />";
  }
}

global $amp_conf;

$db_hash = ['mysql' => 'mysql', 'postgres' => 'pgsql'];
$dbt = !empty($dbt) ? $dbt : 'mysql';
$db_type = $db_hash[$dbt];
$db_table_name = !empty($db_table) ? $db_table : "cdr";
$db_name = !empty($db_name) ? $db_name : "telnyx_messages";
$db_host = empty($db_host) ?  $amp_conf['AMPDBHOST'] : $db_host;
$db_port = empty($db_port) ? '' :  ';port=' . $db_port;
$db_user = empty($db_user) ? $amp_conf['AMPDBUSER'] : $db_user;
$db_pass = empty($db_pass) ? $amp_conf['AMPDBPASS'] : $db_pass;

//$pdo = new \Database($db_type.':host='.$db_host.$db_port.';dbname='.$db_name,$db_user,$db_pass);
$pdo = new \Database($db_type.':host='.$db_host.$db_port);

$createdb = "
CREATE DATABASE IF NOT EXISTS $db_name
 CHARACTER SET = 'utf8mb4'
 COLLATE = 'utf8mb4_general_ci';
CREATE USER IF NOT EXISTS '$db_user'@'localhost' IDENTIFIED BY '$db_pass';
GRANT ALL ON `$db_name`.* TO '$db_user'@'localhost';
FLUSH PRIVILEGES;";

$cmd = "mysql";

$descriptorspec = array(
    0 => array("pipe", "r"),
    1 => array("pipe", "w"),
    2 => array("file", "/tmp/mysql.err", "w")
);

$process = proc_open($cmd, $descriptorspec, $pipes);

if (is_resource($process)) {

  //row2xfdf is made-up function that turns HTML-form data to XFDF
  fwrite($pipes[0], $createdb);
  fclose($pipes[0]);

  $pdf_content = stream_get_contents($pipes[1]);
  fclose($pipes[1]);

  $return_value = proc_close($process);

  echo $pdf_content;

  $descriptorspec = array(
      0 => array('file', 'TElnyX_messages.sql', 'r'),
      1 => array('pipe', "w"),
      2 => array("file", "/tmp/mysql.err", "a")
  );

  $cmd = "mysql $db_name";

  $process = proc_open($cmd, $descriptorspec, $pipes);

  if (is_resource($process)) {

    //row2xfdf is made-up function that turns HTML-form data to XFDF
    $pdf_content = stream_get_contents($pipes[1]);
    fclose($pipes[1]);

    $return_value = proc_close($process);
    echo $pdf_content;

    $pmsg = new TelnyxMessage();

    $pmsg->init_lookup_tables($pmsg);
  }
}
