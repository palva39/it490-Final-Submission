<?php
declare(strict_types=1);
error_reporting(E_ALL);

require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');


$config = parse_ini_file('bundler.ini', true);
if (!$config) {
    echo "Error: Could not read bundler.ini\n";
    exit(1);
}

$dir = rtrim($config['deploy']['artifact_folder'], '/');
$rabbitINI = $config['deploy']['rabbit_ini'];
$queueName = $config['deploy']['deploy_queue'];

$bundleName = $config['bundle']['name'];
$files  = $config['bundle']['file.1'];
$description = $config['bundle']['description'] ?? 'No description';


if (!file_exists($files)) {
    //need to add a checker for all files we are bundling for now one file for testing
    echo "Error: File $files does not exist.\n";
    exit(1);
}


//checks with deployment system for the next version so we dont have to add a version number in our bunlder
$client = new rabbitMQClient($rabbitINI, $queueName);
$response = $client->send_request([
    'type' => 'next_version',
    'name' => $bundleName
]);

$nextVersion = $response['version'] ?? 1;
echo "Next version for $bundleName is $nextVersion\n";


?>