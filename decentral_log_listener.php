#!/usr/bin/php
<?php
declare(strict_types=1);
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

$logFile = "/var/log/test/allErrors.log"; //change depending on path

/* ===== MQ request router ===== */

function requestProcessor(array $request) {
  echo "Received request:\n";
  var_dump($request);

  if (!isset($request['type'])) return ['success' => false, 'message' => 'ERROR: unsupported message type'];

  switch ($request['type']) {
    case 'log_error':              
         $line = trim($request['line']);
        if ($line === "") {
            return ['success' => false, 'message' => 'Empty error line'];
        }


        // Append to log file
        file_put_contents("/var/log/test/allErrors.log", "$line\n", FILE_APPEND); //change depending on where error log is

        return ['success' => true];

    default:
      return ['success' => false, 'message' => 'ERROR: unknown type'];
  }
}

$server = new rabbitMQServer("testRabbitMQ.ini", "logServerWeb");
echo "testRabbitMQServer BEGIN\n";
$server->process_requests('requestProcessor');
echo "testRabbitMQServer END\n";
?>