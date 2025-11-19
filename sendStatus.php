#!/usr/bin/php
<?php
declare(strict_types=1);
error_reporting(E_ALL);

require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');

if ($argc < 3) {
    echo "Usage: php {$argv[0]} <bundleName>\n";
    echo "Usage: php {$argv[1]} <QueueName>\n"; // QA_Backend or QA_Webserver or PROD_
    exit(1);
}

$bundleName = $argv[1];
$location = $arg[2];

$status = strtolower(trim(readline("Enter status for '{$bundleName}' (pass/fail): ")));
if (!in_array($status, ['pass', 'fail'], true)) {
    echo "Error: status must be 'pass' or 'fail'.\n";
    exit(1);
}

try {
    $client = new rabbitMQClient('testRabbitMQ.ini', 'deployServer');

    $msg = [
        'type'   => 'update_status',
        'name'   => $bundleName,
        'status' => $status,
        'location' => $location,
    ];
    $client->publish($msg);
    echo "Sent status '{$status}' for bundle '{$bundleName}' to deployServer.\n";
} catch (Throwable $e) {
    echo "Error sending status message: {$e->getMessage()}\n";
    exit(1);
}

?>