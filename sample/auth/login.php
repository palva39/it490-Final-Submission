<?php

error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '/var/log/php/login_php_errors.log');
error_log("logger alive: " . date('c'));

require_once('/home/craig/git/it490-Final-Submission/path.inc');
require_once('/home/craig/git/it490-Final-Submission/get_host_info.inc');
require_once('/home/craig/git/it490-Final-Submission/rabbitMQLib.inc');


header('Content-Type: application/json');


if (!isset($_POST['uname']) || !isset($_POST['pword'])) {
    echo json_encode(['success' => false, 'message' => 'Missing username or password']);
    exit;
}


$request = [
    'type'     => 'login',
    'username' => $_POST['uname'],
    'password' => $_POST['pword'],
];


try {
    $client = new rabbitMQClient("testRabbitMQ.ini", "loginServer");
    $response = $client->send_request($request);

    // Make sure the output is clean JSON only
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>