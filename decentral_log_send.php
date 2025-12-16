<?php


require_once('/home/craig/git/it490-Final-Submission/path.inc');
require_once('/home/craig/git/it490-Final-Submission/get_host_info.inc');
require_once('/home/craig/git/it490-Final-Submission/rabbitMQLib.inc');

//test and edit
while ($line = fgets(STDIN)) {

   $request = [
       'type'     => 'log_error',
       'line'     => 'DMZ: ' . trim($line)
   ];


   try {
       $client = new rabbitMQClient("testRabbitMQ.ini", "logServerDMZ");
          
       $client->publish($request);
   } catch (Exception $e) {
       echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
   }
}
//end of test and edit


?>









