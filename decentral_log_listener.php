#!/usr/bin/php
<?php
declare(strict_types=1);
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');


error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);


$logFile = "/home/craig/git/it490-Final-Submission/allErrors.log"; //change depending on path


/* ===== MQ request router ===== */


function requestProcessor(array $request) {
 echo "Received request:\n";
 var_dump($request);


 if (!isset($request['type'])) return ['success' => false, 'message' => 'ERROR: unsupported message type'];


 //test and edit
 switch ($request['type']) {
   case 'log_error':             
        $line = trim($request['line']);
       if ($line === "") {
           return ['success' => false, 'message' => 'Empty error line'];
       }




       // Append to log file
       file_put_contents("/home/craig/git/it490-Final-Submission/allErrors.log", "$line\n", FILE_APPEND);


       return ['success' => true];
   //end of test and edit


   default:
     return ['success' => false, 'message' => 'ERROR: unknown type'];
 }
}


$server = new rabbitMQServer("testRabbitMQ.ini", "logServerDMZ");
echo "testRabbitMQServer BEGIN\n";
$server->process_requests('requestProcessor');
echo "testRabbitMQServer END\n";
?>



