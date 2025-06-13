<?php
const APP_DIR = '/var/www/html/admin/modules/telnyx_sms';
require_once APP_DIR . '/TelnyxMessage.class.php';

if (str_starts_with($_SERVER['REMOTE_ADDR'], '127.') || $_SERVER['REMOTE_ADDR'] == '::1') {
  $from = $_GET["from"];
  $to = $_GET["to"];
  $body = file_get_contents("php://input");

  echo "TelnyxMessage::send($from, $to, $body)";
} else {
  http_response_code(403);
}
