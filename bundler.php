<?php
declare(strict_types=1);
error_reporting(E_ALL);

require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');

$tarFiles_stored = '/home/vboxuser/git/it490-Final-Submission/tarFiles';

$config = parse_ini_file('getInfo.ini', true);
if (!$config) {
    echo "Error: Could not read getInfo.ini\n";
    exit(1);
}

$rabbitINI = $config['deploy']['rabbit_ini'];
$queueName = $config['deploy']['deploy_queue'];
$bundleName = $config['bundle']['name'];
$file  = $config['bundle']['file'];
$description = $config['bundle']['description'] ?? 'No description';


if (!file_exists($file)) {
    //need to add a checker for all files we are bundling for now one file for testing
    echo "Error: File $file does not exist.\n";
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

$tarFile = "{$tarFiles_stored }/{$bundleName}_v{$nextVersion}.tar";
$gzFile  = "{$tarFiles_stored }/{$bundleName}_v{$nextVersion}.tar.gz";


try {
    $phar = new PharData($tarFile);
    $phar->addFile($file, basename($file));
    $phar->compress(Phar::GZ);
    unset($phar);
    unlink($tarFile);
    echo "Created bundle: $gzFile\n";
} catch (Throwable $e) {
    echo "Error creating tar.gz: {$e->getMessage()}\n";
    exit(1);
}

$register = $client->send_request([
    'type'     => 'register_bundle',
    'name'     => $bundleName,
    'version'  => $nextVersion,
    'status'   => 'new',
    'filepath' => $gzFile
]);


if ($register['ok'] ?? false) {
    echo "✅ Registered $bundleName v$nextVersion in deployment database.\n";
} else {
    echo "❌ Failed to register bundle.\n";
}
?>