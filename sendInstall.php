<?php
declare(strict_types=1);
error_reporting(E_ALL);

require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');

const DB_HOST = '127.0.0.1';
const DB_NAME = 'bundles';
const DB_USER = 'deploy';
const DB_PASS = 'deploy123'; 


echo "-------List of QA Queues-------------\n";
echo "1. QA_Webserver\n";
echo "2. QA_Backend\n";
echo "3. QA_DMZ\n\n";

echo "-------List of PROD Queues-------------\n";
echo "1. PROD_Webserver\n";
echo "2. PROD_Backend\n";
echo "3. PROD_DMZ\n";

$queue = trim(readline("Enter QueueName your sending too(from lists above): "));
if ($queue === '') {
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

$bundleName = trim(readline("Enter bundle name you're sending: "));
if ($bundleName === '') {
    echo "Error: bundle name cannot be empty.\n";
    exit(1);
}

function pdo(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    $dsn = 'mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4';
    $opt = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    return $pdo = new PDO($dsn, DB_USER, DB_PASS, $opt);
}

$status= null;
if (stripos($queue, 'QA') === 0) {
    $status = 'new';
} elseif (stripos($queue, 'PROD') === 0) {
    $status= 'pass';
}

if ($status !== null) {
        $stmt = pdo()->prepare("
            SELECT version, filepath 
            FROM bundles 
            WHERE name = ? AND status = ? 
            ORDER BY version DESC 
            LIMIT 1
        ");
        $stmt->execute([$bundleName, $status]);
}

$row = $stmt -> fetch();

if (!$row) {
    echo "Error: No record found for bundle '{$bundleName}' with status '{$status}'.\n";
    exit(1);
}

$version  = (int)$row['version'];
$filePath = $row['filepath'];

 echo "Found bundle: {$bundleName} v{$version} ({$filePath})";
    if ($status !== null) {
        echo " with status '{$status}'";
    }
    echo "\n";


try {
    $client = new rabbitMQClient('testRabbitMQ.ini', $queue);

    $client->publish([
    'type'    => 'install',
    'name'    => $bundleName,
    'version' => $version,
    'path'    => $filePath
    ]);

    echo "Sent install message for {$bundleName} v{$version} to {$queue}\n";

} catch (Throwable $e) {
    echo "Error sending message: {$e->getMessage()}\n";
    exit(1);
}
?>
