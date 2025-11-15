#!/usr/bin/php
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
function fail($msg, $extra = []) {
  logit('FAIL: ' . $msg);
  return ['ok' => false, 'error' => $msg] + $extra;
}
function logit(string $msg): void { error_log('[deploy-listener] '.$msg); }

function requestProcessor(array $req) {
  try{
    $type = $req['type'] ?? '';
    if ($type === '') return fail('missing type');

    switch ($type) {
      case 'next_version': {
        $bundleName = trim((string)($req['name'] ?? ''));
        if ($bundleName === '') return fail('missing name');
        $stmt = pdo()->prepare("SELECT COALESCE(MAX(version),0)+1 AS v FROM bundles WHERE name = ?");
        $stmt->execute([$bundleName]);
        $v = (int)($stmt->fetch()['v'] ?? 1);
        return ok(['version' => $v]);
      }
       case 'register_bundle': {
        $bundleName = trim((string)($req['name'] ?? ''));
        $version = (int)($req['version'] ?? 0);
        $status = (string)($req['status'] ?? 'new');
        $filepath = trim((string)($req['filepath'] ?? ''));

        $__base = '/home/vboxuser/temp';
        $__file = basename($filepath);
        $filepath = rtrim($__base, '/').($__file !== '' ? '/'.$__file : '');

        if ($bundleName === '' || $version <= 0 || $filepath === '') return fail('missing name or version');

        $sql = "INSERT INTO bundles (name, version, status, filepath) VALUES (?,?,?,?)";
        try {
          $stmt = pdo()->prepare($sql);
          $stmt->execute([$bundleName, $version, $status, $filepath]);
        } catch (PDOException $e) {
          if (strpos($e->getMessage(), 'Duplicate') !== false) {
            return fail('duplicate name+version');
          }
          throw $e;
        }
        return ok(['id' => (int)pdo()->lastInsertId()]);
      }
      case 'update_status': {
        $bundleName = trim((string)($req['name'] ?? ''));
        $status = (string)($req['status'] ?? '');

        if ($bundleName === '' || !in_array($status, ['new','pass','fail'], true)) {
            return fail('invalid args');
        }

        $stmt = pdo()->prepare("SELECT version FROM bundles WHERE name = ? ORDER BY version DESC LIMIT 1");
        $stmt->execute([$bundleName]);
        $row = $stmt->fetch();
        if (!$row) return fail('bundle not found');

        $version = (int)$row['version'];

        $upd = pdo()->prepare("UPDATE bundles SET status = ? WHERE name = ? AND version = ?");
        $upd->execute([$status, $bundleName, $version]);

    return ok(['version' => $version]);
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