<?php
require_once "../include/TelnyxMessage.class.php";

$signature = $_SERVER['HTTP_TELNYX_SIGNATURE_ED25519'];
$timestamp = $_SERVER['HTTP_TELNYX_TIMESTAMP'];
$body = file_get_contents("php://input");

$result = TelnyxMessage::webhook($body, $signature, $timestamp);

// Respond to Telnyx
if ($result)
{
  $httpStatusCode = 202;
  $httpStatusMsg  = 'Request accepted and in process';
}
else
{
  $httpStatusCode = 400;
  $httpStatusMsg  = 'Request rejected due to incorrect or missing signature';
}

$phpSapiName = substr(php_sapi_name(), 0, 3);
if ($phpSapiName == 'cgi' || $phpSapiName == 'fpm') {
  header('Status: '.$httpStatusCode.' '.$httpStatusMsg);
} else {
  $protocol = $_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.0';
  header($protocol.' '.$httpStatusCode.' '.$httpStatusMsg);
}
