#!/usr/bin/php
<?php
const APP_DIR = '/var/www/html/admin/modules/telnyx_sms';
require_once APP_DIR . '/TelnyxMessage.class.php';

if ($argc !== 4) {
  echo "usage: $argv[0] <to address> <from address> <body of message>\n";
  exit(1);
}

$to = $argv[1];
$from = $argv[2];
$body = urldecode($argv[3]);

$result = TelnyxMessage::send($from, $to, $body);

return $result;
