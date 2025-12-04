<?php

require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');

$logFile = "/var/log/test/allErrors.log"; //change depending on path

while ($line = fgets(STDIN)) {
    $lower = strtolower($line);

    $request = [
        'type'     => 'log_error',
        'line'     => 'Web server: ' . trim($line)
    ];

    try {
        $client = new rabbitMQClient("testRabbitMQ.ini", "logServerWeb");
            
        $client->publish($request);
    } catch (Exception $e) { 
        echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
    }
}

?>

