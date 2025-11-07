#!/usr/bin/php
<?php
declare(strict_types=1);
error_reporting(E_ALL);

require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');

const WEB_ROOT    = '/var/www/sample';
const DEPLOY_DIR  = '/deploy';
const BACKUP_DIR  = '/backups';
const DEPLOY_HOST = '10.0.12.2';   // deployment VM IP
const ENV_NAME    = 'QA';         // this is for the QA installer the only change needed for production is changing this equal to PROD

function logit($msg) { error_log("[".ENV_NAME."-Installer] $msg"); }
function runCmd($cmd) { logit("Running: $cmd"); shell_exec($cmd . " 2>&1"); }

function handleDeploy($req) {
    $name = $req['name'] ?? '';
    $version = $req['version'] ?? '';
    $filepath = $req['filepath'] ?? '';

    if ($name === '' || $version === '' || $filepath === '') {
        return ['ok'=>false, 'error'=>'missing args'];
    }

    logit("Deploying $name v$version");

    runCmd("mkdir -p " . DEPLOY_DIR . " " . BACKUP_DIR);

    $remote = "deploy@".DEPLOY_HOST.":${filepath}";
    $local = DEPLOY_DIR . '/' . basename($filepath);
    runCmd("scp -q $remote $local");

    if (!file_exists($local)) return ['ok'=>false, 'error'=>'copy failed'];

    $backup = BACKUP_DIR . '/sample_' . strtolower(ENV_NAME) . '_' . date('Y-m-d_H-i-s') . '.tar.gz';
    runCmd("tar -czf $backup " . WEB_ROOT);

    runCmd("tar -xzf $local -C " . WEB_ROOT);
    runCmd("sudo systemctl restart apache2");

    file_put_contents(WEB_ROOT.'/version.txt', "Version: $version\nEnv: ".ENV_NAME."\nDeployed: ".date('Y-m-d H:i:s')."\n");
    logit("Deployed $name v$version successfully");
    return ['ok'=>true,'version'=>$version,'env'=>ENV_NAME];
}

function requestProcessor($req) {
    $type = $req['type'] ?? '';
    switch ($type) {
        case 'install_bundle_qa':
        case 'install_bundle_prod':
            return handleDeploy($req);
        default:
            return ['ok'=>false,'error'=>'unknown type'];
    }
}

logit(ENV_NAME." listener started");
$server = new rabbitMQServer('testRabbitMQ.ini', 'deployServer');
$server->process_requests('requestProcessor');
?>