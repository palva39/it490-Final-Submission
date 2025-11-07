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


?>