<?php
if (!defined('FREEPBX_IS_AUTH')) { die('No direct script access allowed'); }

//	License for all code of this FreePBX module can be found in the license file inside the module directory
//  Portions Copyright (C) 2011 Igor Okunev
//  Portions Copyright (C) 2011 Mikael Carlsson
//	Copyright 2013 Schmooze Com Inc.
//
function telnyx_sms_get_config($engine): void
{
  global $core_conf, $amp_conf, $version;

  if (isset($core_conf) && is_a($core_conf, "core_conf")) {
    $section = 'telnyxsmsdb';
    $core_conf->addResOdbc($section, ['enabled' => 'yes']);
    $core_conf->addResOdbc($section, ['dsn' => 'MySQL-telnyx_messages']);
    $core_conf->addResOdbc($section, ['pre-connect' => 'yes']);
    if ((version_compare($version, "14.0", "lt") && version_compare($version, "13.14.0", "ge")) || (version_compare($version, "14.0", "ge") && version_compare($version, "14.3.0", "ge"))) {
      $core_conf->addResOdbc($section, ['max_connections' => '5']);
    } else {
      $core_conf->addResOdbc($section, ['pooling' => 'no']);
      $core_conf->addResOdbc($section, ['limit' => '1']);
    }
    $core_conf->addResOdbc($section, ['username' => !empty($amp_conf['TSMSDBUSER']) ? $amp_conf['TSMSDBUSER'] : $amp_conf['AMPDBUSER']]);
    $core_conf->addResOdbc($section, ['password' => !empty($amp_conf['TSMSDBPASS']) ? $amp_conf['TSMSDBPASS'] : $amp_conf['AMPDBPASS']]);
    $core_conf->addResOdbc($section, ['database' => !empty($amp_conf['TSMSDBNAME']) ? $amp_conf['TSMSDBNAME'] : 'telnyx_messages']);
  }

  global $db, $astman;

  if ($astman->connected()) {
    $astman->database_deltree("TELNYX-SMS");
    $sql = "SELECT Exten, Phone FROM smsnumbers INNER JOIN smscid ON smscid.Phone_ID = smsnumbers.ID;";
    $stmt = $db->prepare($sql);
    $result = $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_NUM, PDO::FETCH_ORI_NEXT)) {
      $astman->database_put("TELNYX-SMS", "$row[0]/cid", $row[1]);
    }
  }
}
