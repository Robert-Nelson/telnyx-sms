#!/usr/bin/env php
<?php
require "TelnyxMessage.class.php";

$options = getopt("d:s:c:f:h", [ "database:", "skip:", "count:", "file:", "headings"]);

$database = $options['d'] ?? $options['database'] ?? "TelnyxMessages.db";
$skip = $options['s'] ?? $options['skip'] ?? 0;
$count = $options['c'] ?? $options['count'] ?? 10;
$file = $options['f'] ?? $options['file'] ?? null;
$headings = !($options['h'] ?? $options['headings'] ?? 1);

$tm = new TelnyxMessage($database);

$rows = $tm->export($skip, $count, true, $headings);

if ($file != null) {
  $fp = fopen($file, "w");
} else {
  $fp = STDOUT;
}

if ($fp !== false) {
  fwrite($fp, implode("\n", $rows)."\n");
}

if ($fp != STDOUT) {
  fclose($fp);
}
