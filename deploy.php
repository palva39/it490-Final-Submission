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

      //this listes the bundlenames when bundle.php is called so the user can see whats already in the database
      case 'list_bundle_names':
        try {
            $stmt = pdo()->query("SELECT DISTINCT name FROM bundles ORDER BY name ASC");
            $names = $stmt->fetchAll(PDO::FETCH_COLUMN);
            return ['ok' => true, 'names' => $names];
        } catch (Throwable $e) {
            return ['ok' => false, 'msg' => $e->getMessage()];
        }

      case 'update_status': {
        $bundleName = trim((string)($req['name'] ?? ''));
        $location = trim((string)($req['location'] ?? ''));
        $status = (string)($req['status'] ?? '');
        
        if ($status === 'fail'){
          //run php script called sendInstall.php with args queunanme and bundle name
          //quename is $location and bundle name is $bundleName
          //this section will send the install back to the queue that sent the status fail so it can rollback to previous last passed bundle 

            logit("update_status: FAIL for {$bundleName} invoking rollback.php for queue {$location}");
            $cmd = escapeshellcmd("php /home/vboxuser/git/it490-Final-Submission/rollback.php {$location} {$bundleName}");

           
            $output = [];
            $returnCode = 0;
            exec($cmd . " 2>&1", $output, $returnCode);

            logit("rollback.php output: " . implode(" | ", $output));

            if ($returnCode !== 0) {
                logit("rollback.php FAILED with exit code {$returnCode}");
            } else {
                logit("rollback.php executed successfully");
            }
        }


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