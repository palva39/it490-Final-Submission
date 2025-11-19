<?php
declare(strict_types=1);
error_reporting(E_ALL);

require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');


/*Manual Deployment script

How to use:
php deployment.php <version#> <queue> <bundlename>  
**Check database for exact bundlename and version # that you want to send to installer**

Example:

php deployment.php 1 QA_BackEnd fixingBackEnd
php deployment.php 2 QA_Webserver loginCSSChange

*/

/*--------------DB info------------*/
const DB_HOST = '127.0.0.1';
const DB_NAME = 'bundles';
const DB_USER = 'deploy';
const DB_PASS = 'deploy123'; 

if ($argc < 3) {
    echo "Usage: php {$argv[0]} <queue> <bundleName>\n";
    exit(1);
}

$queue   = $argv[1];
$bundleName = $argv[2];


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

$row          = null;
$actualStatus = null;

// QA: try 'new' first, then 'pass'
// PROD: only 'pass'
if (stripos($queue, 'QA') === 0) {

    $candidates = ['new', 'pass'];

    foreach ($candidates as $candidate) {
        $stmt = pdo()->prepare("
            SELECT version, filepath 
            FROM bundles 
            WHERE name = ? AND status = ? 
            ORDER BY version DESC 
            LIMIT 1
        ");
        $stmt->execute([$bundleName, $candidate]);
        $row = $stmt->fetch();

        if ($row) {
            $actualStatus = $candidate;
            break;
        }
    }

} elseif (stripos($queue, 'PROD') === 0) {

    $actualStatus = 'pass';
    $stmt = pdo()->prepare("
        SELECT version, filepath 
        FROM bundles 
        WHERE name = ? AND status = ? 
        ORDER BY version DESC 
        LIMIT 1
    ");
    $stmt->execute([$bundleName, $actualStatus]);
    $row = $stmt->fetch();
}

if (!$row) {
    if (stripos($queue, 'QA') === 0) {
        echo "Error: No record found for bundle '{$bundleName}' with status 'new' or 'pass'. QA will remain unchanged.\n";
    } else {
        echo "Error: No record found for bundle '{$bundleName}' with status 'pass'.\n";
    }
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
