#!/usr/bin/php
<?php
declare(strict_types=1);
error_reporting(E_ALL);

require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');

/*--------------DB info------------*/
const DB_HOST = '127.0.0.1';
const DB_NAME = 'bundler';
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

function requestProcessor(array $req) {
  try{
    $type = $req['type'] ?? '';
    if ($type === '') return fail('missing type');

    switch ($type) {
      case 'next_version': {
        $name = trim((string)($req['name'] ?? ''));
        if ($name === '') return fail('missing name');
        $stmt = pdo()->prepare("SELECT COALESCE(MAX(version),0)+1 AS v FROM bundles WHERE name = ?");
        $stmt->execute([$name]);
        $v = (int)($stmt->fetch()['v'] ?? 1);
        return ok(['version' => $v]);
      }
       case 'register_bundle': {
        $name = trim((string)($req['name'] ?? ''));
        $version = (int)($req['version'] ?? 0);
        $status = (string)($req['status'] ?? 'new');
        $filepath = trim((string)($req['filepath'] ?? '')); 

        if ($name === '' || $version <= 0 || $filepath === '') return fail('missing name or version');

        $sql = "INSERT INTO bundles (name, version, status, filepath) VALUES (?,?,?)";
        try {
          $stmt = pdo()->prepare($sql);
          $stmt->execute([$name, $version, $status, $filepath]);
        } catch (PDOException $e) {
          if (strpos($e->getMessage(), 'Duplicate') !== false) {
            return fail('duplicate name+version');
          }
          throw $e;
        }
        return ok(['id' => (int)pdo()->lastInsertId()]);
      }
      case 'set_status': {
        $name = trim((string)($req['name'] ?? ''));
        $version = (int)($req['version'] ?? 0);
        $status = (string)($req['status'] ?? '');
        if ($name === '' || $version <= 0 || !in_array($status, ['new','passed','failed'], true)) {
          return fail('invalid args');
        }
        $stmt = pdo()->prepare("UPDATE bundles SET status = ? WHERE name = ? AND version = ?");
        $stmt->execute([$status, $name, $version]);
        if ($stmt->rowCount() === 0) return fail('not found');
        return ok();
      }

      default:
        return fail('unknown type: '.$type);
    }
    
  }
  catch (Throwable $e) {
    logit('ERR '.$e->getMessage());
    return fail('exception', ['detail' => $e->getMessage()]);
  }
}


logit('starting…');
$server = new rabbitMQServer('testRabbitMQ.ini', 'deployServer');
$server->process_requests('requestProcessor');
?>