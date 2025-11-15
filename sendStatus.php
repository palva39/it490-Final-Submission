#!/usr/bin/php
<?php
declare(strict_types=1);
error_reporting(E_ALL);

require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');

if ($argc < 2) {
    echo "Usage: php {$argv[0]} <bundleName>\n";
    exit(1);
}


?>