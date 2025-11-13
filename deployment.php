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


//fix so user doesnt enter version number instead only bundle name and database gets the latest version number, only args should queue and bundlename
//add checker for PROD, should get the latest version number and status is pass
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

$status= null;
if (stripos($queue, 'QA') === 0) {
    $statusN= 'new';
} elseif (stripos($queue, 'PROD') === 0) {
    $status= 'pass';
}

try {
    $stmt = pdo()->prepare("SELECT filepath FROM bundles WHERE name=? AND version=? LIMIT 1");
    $stmt->execute([$bundleName, $version]);
    $row = $stmt->fetch();

    if (!$row) {
        echo "Error: No record found for {$bundleName} version {$version}\n";
        exit(1);
    }

    $filePath = $row['filepath'];
    echo "Found bundle: {$filePath}\n";

} catch (Throwable $e) {
    echo "Database error: {$e->getMessage()}\n";
    exit(1);
}


try {
    $client = new rabbitMQClient('testRabbitMQ.ini', $queue);

    $response = $client->send_request([
        'type'    => 'install',
        'name'    => $bundleName,
        'version' => $version,
        'path'    => $filePath
    ]);

    if (is_array($response) && !empty($response['ok'])) {
        echo "Sent install message for {$bundleName} v{$version} to {$queue}\n";
    } else {
        echo "Installer failed to get data.\n";
        print_r($response);
    }

} catch (Throwable $e) {
    echo "Error sending message: {$e->getMessage()}\n";
    exit(1);
}
?>
