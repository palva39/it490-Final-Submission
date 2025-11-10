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

$filestoBundle = [];

foreach ($config['bundle'] as $key => $value) {
    if (stripos($key, 'file') === 0 && !empty(trim($value))) {
        $filesToBundle[] = trim($value);
    }
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
    foreach ($filesToBundle as $f) {
        $phar->addFile($f, basename($f));
    }
    $phar->compress(Phar::GZ);
    unset($phar);
    unlink($tarFile);
    echo "Created bundle: $gzFile\n";
} catch (Throwable $e) {
    echo "Error creating tar.gz: {$e->getMessage()}\n";
    exit(1);
}

//scp file to deployment system to the folder /tarFiles
$sourceFilePath = "/home/vboxuser/git/it490-Final-Submission/tarFiles/{$gzfile}"; // Path to the tar.gz file on the PHP-executing VM
$destinationUser = 'vboxuser'; // Username on the destination VM
$destinationHost = '100.76.145.42'; // IP or hostname of the destination VM
$destinationPath = '/home/vboxuser/git/it490-Final-Submission/tarFiles/'; // Directory on the destination VM where the file will be copied

$scpExecute = "scp {$sourceFilePath} {$destinationUser}@{$destinationHost}:{$destinationPath}";

$output = [];
$return_var = 0;
exec($scpCommand, $output, $return_var);

if ($return_var === 0) {
    echo "File transferred successfully.\n";
} else {
    echo "Error transferring file. Return code: {$return_var}\n";
    echo "Output:\n";
    echo implode("\n", $output);
}


$register = $client->send_request([
    'type'     => 'register_bundle',
    'name'     => $bundleName,
    'version'  => $nextVersion,
    'status'   => 'new',
    'filepath' => $gzFile,
]);


if ($register['ok'] ?? false) {
    echo "✅ Registered $bundleName v$nextVersion in deployment database.\n";
} else {
    echo "❌ Failed to register bundle.\n";
}
?>