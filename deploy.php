#!/usr/bin/php
<?php
declare(strict_types=1);
error_reporting(E_ALL);

require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');

/*--------------DB info------------*/
const DB_HOST = '127.0.0.1';
const DB_NAME = 'deploydb';
const DB_USER = 'deploy';
const DB_PASS = 'deploy123'; 

function pdo(): PDO {
  static $pdo = null;
  if ($pdo) return $pdo;
  $dsn = 'mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4';
  $opt = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
  ];
  $pdo = new PDO($dsn, DB_USER, DB_PASS, $opt);
  return $pdo;
}

function ok($data = []) { return ['ok' => true] + $data; }
function fail($msg, $extra = []) { return ['ok' => false, 'error' => $msg] + $extra; }
function logit(string $msg): void { error_log('[deploy-listener] '.$msg); }


?>