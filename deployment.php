<?php
declare(strict_types=1);
error_reporting(E_ALL);

require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');


/*--------------DB info------------*/
const DB_HOST = '127.0.0.1';
const DB_NAME = 'bundles';
const DB_USER = 'deploy';
const DB_PASS = 'deploy123'; 

if ($argc < 3) {
    echo "Usage: php {$argv[0]} <version#> <queue>\n";
    exit(1);
}

?>
