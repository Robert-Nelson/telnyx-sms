<?php
// if (!defined('FREEPBX_IS_AUTH')) { die('No direct script access allowed'); }
//	License for all code of this FreePBX module can be found in the license file inside the module directory
//  Portions Copyright (C) 2011 Mikael Carlsson
//	Copyright 2013 Schmooze Com Inc.
//
// Update cdr database with did field
//

$id = posix_getuid();

if ($id !== 0) {
  echo "This script must be run as root\n";
  exit(1);
}

include "/etc/freepbx.conf";

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

$db_hash = ['mysql' => 'mysql', 'postgres' => 'pgsql'];
$dbt = !empty($dbt) ? $dbt : 'mysql';
$db_type = $db_hash[$dbt];
$db_name = !empty($db_name) ? $db_name : "telnyx_messages";
$db_host = empty($db_host) ?  $amp_conf['AMPDBHOST'] : $db_host;
$db_port = empty($db_port) ? '' :  ';port=' . $db_port;
$db_user = empty($db_user) ? $amp_conf['AMPDBUSER'] : $db_user;
$db_pass = empty($db_pass) ? $amp_conf['AMPDBPASS'] : $db_pass;

// echo "db_type=$db_type, db_name=$db_name, db_host=$db_host, db_port=$db_port, db_user=$db_user, db_pass=$db_pass\n";

$pdo = new Database($db_type.':host='.$db_host.$db_port, "root");

$createdb = "
CREATE DATABASE IF NOT EXISTS $db_name
  CHARACTER SET = 'utf8mb4'
  COLLATE = 'utf8mb4_general_ci';";

$count = $pdo->exec($createdb);

if ($count === false) {
  echo "Unable to create $db_name, code = ".$pdo->errorCode()."([".implode("],[",$pdo->errorInfo())."])";
  exit(1);
}

$createuser = "CREATE USER IF NOT EXISTS $db_user@$db_host IDENTIFIED BY '$db_pass'";

$count = $pdo->exec($createuser);

if ($count === false) {
  echo "Unable to create user $db_user, code = ".$pdo->errorCode()."([".implode("],[",$pdo->errorInfo())."])";
  exit(1);
}

$grantDB = "GRANT ALL PRIVILEGES ON $db_name.* TO $db_user@$db_host;";

$count = $pdo->exec($grantDB);

if ($count === false) {
  echo "Unable to grant privileges, code = ".$pdo->errorCode()."([".implode("],[",$pdo->errorInfo())."])";
  exit(1);
}

$flush = "flush privileges;";

$count = $pdo->exec($flush);

if ($count === false) {
  echo "Unable to flush privileges, code = ".$pdo->errorCode()."([".implode("],[",$pdo->errorInfo())."])";
  exit(1);
}

$TSMS_settings = "
REPLACE `freepbx_settings` VALUES
    ('TSMSDBHOST','','Remote Telnyx SMS Messages DB Host',3,'DO NOT set this unless you know what you are doing. Only used if you do not use the default values provided by FreePBX.<br>Hostname of db server if not the same as AMPDBHOST.','text','','',1,0,'Remote Telnyx SMS Messages Database','',1,0),
    ('TSMSDBNAME','','Remote Telnyx SMS Messages DB Name',3,'DO NOT set this unless you know what you are doing. Only used if you do not use the default values provided by FreePBX.<br>Name of database used for cdr records.','text','','',1,0,'Remote Telnyx SMS Messages Database','',1,0),
    ('TSMSDBPASS','','Remote Telnyx SMS Messages DB Password',3,'DO NOT set this unless you know what you are doing. Only used if you do not use the default values provided by FreePBX.<br>Password for connecting to db if its not the same as AMPDBPASS.','text','','',1,0,'Remote Telnyx SMS Messages Database','',1,0),
    ('TSMSDBPORT','','Remote Telnyx SMS Messages DB Port',3,'DO NOT set this unless you know what you are doing. Only used if you do not use the default values provided by FreePBX.<br>Port number for db host.','int','1024,65536','',1,0,'Remote Telnyx SMS Messages Database','',1,0),
    ('TSMSDBTYPE','','Remote Telnyx SMS Messages DB Type',3,'DO NOT set this unless you know what you are doing. Only used if you do not use the default values provided by FreePBX. Defaults to your configured AMDBENGINE.','select',',mysql,postgres','',1,0,'Remote Telnyx SMS Messages Database','',1,0),
    ('TSMSDBUSER','','Remote Telnyx SMS Messages DB User',3,'DO NOT set this unless you know what you are doing. Only used if you do not use the default values provided by FreePBX. Username to connect to db with if it is not the same as AMPDBUSER.','text','','',1,0,'Remote Telnyx SMS Messages Database','',1,0);";

$stmt = $db->prepare($TSMS_settings);

if (is_object($stmt) && get_class($stmt) == "DB_Error") {
  echo "Error preparing freepbx_setting, code = ".$stmt->getCode().", message = ".$stmt->getMessage().", ([".implode("],[",$stmt->errorInfo())."])";
  exit(1);
}

$result = $stmt->execute($stmt);

if (is_object($result) && get_class($result) == "DB_Error") {
  echo "Error replacing freepbx_setting, code = ".$stmt->getCode().", message = ".$stmt->getMessage().", ([".implode("],[",$stmt->errorInfo())."])";
  exit(1);
}

exit(0);
