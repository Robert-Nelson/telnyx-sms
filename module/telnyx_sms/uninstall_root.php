<?php
// if (!defined('FREEPBX_IS_AUTH')) { die('No direct script access allowed'); }
//	License for all code of this FreePBX module can be found in the license file inside the module directory
//  Copyright (C) 2025 Robert Nelson
//

$id = posix_getuid();

if ($id !== 0) {
  echo "This script must be run as root\n";
  exit(1);
}

/* Bootstrap Settings:
 *
 * bootstrap_settings['skip_astman']           - legacy $skip_astman, default false
 *
 * bootstrap_settings['astman_config']         - default null, config arguemnt when creating new Astman
 * bootstrap_settings['astman_options']        - default array(), config options creating new Astman
 *                                               e.g. array('cachemode' => true), see astman documentation
 * bootstrap_settings['astman_events']         - default 'off' used when connecting, Astman defaults to 'on'
 *
 * bootstrap_settings['freepbx_error_handler'] - false don't set it, true use default, named use what is passed
 *
 * bootstrap_settings['freepbx_auth']          - true (default) - authorize, false - bypass authentication
 *
 * $restrict_mods: false means include all modules functions.inc.php, true skip all modules
 *                 array of hashes means each module where there is a hash
 *                 e.g. $restrict_mods = array('core' => true, 'dashboard' => true)
 *
 * Settings that are set by bootstrap to indicate the results of what was setup and not:
 *
 * $bootstrap_settings['framework_functions_included'] = true/false;
 * $bootstrap_settings['amportal_conf_initialized'] = true/false;
 * $bootstrap_settings['astman_connected'] = false/false;
 * $bootstrap_settings['function_modules_included'] = true/false true if one or more were included, false if all were skipped;
 * $bootstrap_settings['returnimmediately'] = true; //return right after freepbx.conf is loaded. Essentially only get database connection variables
 * $bootstrap_settings['report_error_link'] = true; //show the report to FreePBX link in page errors
 */

$bootstrap_settings = array();
$bootstrap_settings['skip_astman'] = true;
$bootstrap_settings['freepbx_auth'] = false;

$restrict_mods = array('core' => true);

include "/etc/freepbx.conf";

global $amp_conf, $db;

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

  try {
    $dbtsms = DB::connect($datasource); // attempt connection
    if (DB::isError($dbtsms)) {
      die_freepbx($dbtsms->getDebugInfo());
    }
    $dbtsms = null;
  } catch (Exception $e) {
  }
}


// if (!defined('FREEPBX_IS_AUTH')) { die('No direct script access allowed'); }
//	License for all code of this FreePBX module can be found in the license file inside the module directory
//  Copyright (C) 2025 Robert Nelson
//

$id = posix_getuid();

if ($id !== 0) {
  echo "This script must be run as root\n";
  exit(1);
}

/* Bootstrap Settings:
 *
 * bootstrap_settings['skip_astman']           - legacy $skip_astman, default false
 *
 * bootstrap_settings['astman_config']         - default null, config arguemnt when creating new Astman
 * bootstrap_settings['astman_options']        - default array(), config options creating new Astman
 *                                               e.g. array('cachemode' => true), see astman documentation
 * bootstrap_settings['astman_events']         - default 'off' used when connecting, Astman defaults to 'on'
 *
 * bootstrap_settings['freepbx_error_handler'] - false don't set it, true use default, named use what is passed
 *
 * bootstrap_settings['freepbx_auth']          - true (default) - authorize, false - bypass authentication
 *
 * $restrict_mods: false means include all modules functions.inc.php, true skip all modules
 *                 array of hashes means each module where there is a hash
 *                 e.g. $restrict_mods = array('core' => true, 'dashboard' => true)
 *
 * Settings that are set by bootstrap to indicate the results of what was setup and not:
 *
 * $bootstrap_settings['framework_functions_included'] = true/false;
 * $bootstrap_settings['amportal_conf_initialized'] = true/false;
 * $bootstrap_settings['astman_connected'] = false/false;
 * $bootstrap_settings['function_modules_included'] = true/false true if one or more were included, false if all were skipped;
 * $bootstrap_settings['returnimmediately'] = true; //return right after freepbx.conf is loaded. Essentially only get database connection variables
 * $bootstrap_settings['report_error_link'] = true; //show the report to FreePBX link in page errors
 */

$bootstrap_settings = array();
$bootstrap_settings['skip_astman'] = true;
$bootstrap_settings['freepbx_auth'] = false;

$restrict_mods = array('core' => true);

include "/etc/freepbx.conf";

global $amp_conf, $db;

// Retrieve database and table name if defined, otherwise use FreePBX default
$db_name = !empty($amp_conf['TSMSDBNAME']) ? $amp_conf['TSMSDBNAME'] : "telnyx_messages";

// if TSMSDBHOST and TSMSDBTYPE are not empty then we assume an external connection and don't use the default connection
//
if (!empty($amp_conf["TSMSDBHOST"]) && !empty($amp_conf["TSMSDBTYPE"])) {
  $db_hash = ['mysql' => 'mysql', 'postgres' => 'pgsql'];
  $db_type = $db_hash[$amp_conf["TSMSDBTYPE"]];
  $db_host = $amp_conf["TSMSDBHOST"];
  $db_port = empty($amp_conf["TSMSDBPORT"]) ? '' : ':' . $amp_conf["TSMSDBPORT"];
  $db_user = empty($amp_conf["TSMSDBUSER"]) ? $amp_conf["AMPDBUSER"] : $amp_conf["TSMSDBUSER"];
  $db_pass = empty($amp_conf["TSMSDBPASS"]) ? $amp_conf["AMPDBPASS"] : $amp_conf["TSMSDBPASS"];
  $datasource = $db_type . '://' . $db_user . ':' . $db_pass . '@' . $db_host . $db_port . '/' . $db_name;

  try {
    $dbtsms = DB::connect($datasource); // attempt connection
    if (DB::isError($dbtsms)) {
      die_freepbx($dbtsms->getDebugInfo());
    }
    $dbtsms = null;
  } catch (Exception $e) {
  }
}

try {
  $pdo = new Database($db_type . ':host=' . $db_host . $db_port, "root");
} catch (Exception $e) {
}

$dropdb = "DROP DATABASE IF EXISTS $db_name;";

$count = $pdo->exec($dropdb);

if ($count === false) {
  echo "Unable to drop $db_name, code = ".$pdo->errorCode()."([".implode("],[",$pdo->errorInfo())."])";
}

unlink("/var/log/asterisk/telnyx-sms.log");

$symlinks = array(
    "/etc/logrotate.d/freepbx-telnyx-sms",
    "/var/www/html/telnyx-webhook.php"
);

foreach ($symlinks as $link) {
  if (file_exists($link)) {
    unlink($link);
  }
}

exit(0);
