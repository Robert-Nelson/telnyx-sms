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

$id_out = shell_exec("/usr/bin/id");
dbug("id", $id_out, 1);

$grantAll = "GRANT ALL ON $db_name.* TO $db_user@lcalhost;";

$flush = "FLUSH PRIVILEGES;";

$desc_specs = array(
    0 => array("pipe", "r"),
    1 => array("pipe", "w"),
    2 => array("pipe", "w"),
);

$pipes = array();

$process = proc_open("/usr/bin/mysql". $desc_specs, $pipes);

if (is_resource($process)) {
  fwrite($pipes[0], $grantAll);
  fwrite($pipes[0], $flush);
  fclose($pipes[0]);
}

$output = stream_get_contents($pipes[1]);
fclose($pipes[1]);

$errs = stream_get_contents($pipes[2]);
fclose($pipes[2]);

$return = proc_close($process);

dbug("Privileges - result", $return, 1);
dbug("Privileges - stdout", $output, 1);
dbug("Privileges - stderr", $errs, 1);

//$pdo = new Database($db_type.':host='.$db_host.$db_port.';dbname='.$db_name,$db_user,$db_pass);
$pdo = new Database($db_type.':host='.$db_host.$db_port);

$createdb = "
CREATE DATABASE IF NOT EXISTS $db_name
 CHARACTER SET = 'utf8mb4'
 COLLATE = 'utf8mb4_general_ci';";

$count = $pdo->exec($createdb);

if ($count === false) {
  out("Unable to create $db_name, code = ".$pdo->errorCode()."(".$pdo->errorInfo().")");
  return 1;
}

$pdo = new Database($db_type.':host='.$db_host.$db_port.';dbname='.$db_name);

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
        message_id INTEGER NOT NULL UNIQUE,
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

$pmsg = new TelnyxMessage();

$pmsg->init_lookup_table();
