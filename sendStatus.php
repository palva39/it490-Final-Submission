#!/usr/bin/php
<?php
declare(strict_types=1);
error_reporting(E_ALL);

require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');


echo "-------List of QA Queues-------------\n";
echo "1. QA_Webserver\n";
echo "2. QA_Backend\n";
echo "3. QA_DMZ\n\n";

echo "-------List of PROD Queues-------------\n";
echo "1. PROD_Webserver\n";
echo "2. PROD_Backend\n";
echo "3. PROD_DMZ\n";

$location = trim(readline("Enter current QueueName (from lists above): "));
if ($location === '') {
    echo "Error: QueueName cannot be empty.\n";
    exit(1);
}

$client = new rabbitMQClient('testRabbitMQ.ini', 'deployServer');
    $listResp = $client->send_request(['type' => 'list_bundle_names']);

    echo "\n--------Bundles in DB---------\n";
    if (($listResp['ok'] ?? false) && !empty($listResp['names'])) {
        foreach ($listResp['names'] as $n) {
            echo " - $n\n";
        }
    } else {
        echo " (none found)\n";
    }

$bundleName = trim(readline("Enter bundle name you're working on: "));
if ($bundleName === '') {
    echo "Error: bundle name cannot be empty.\n";
    exit(1);
}

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