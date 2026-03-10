<?php
require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/helpers.php';

// Simple SMTP test endpoint
$mc = app_config()['mail'];
$driver = $mc['driver'] ?? 'log';

if ($driver !== 'smtp') {
    json_ok(['status' => 'not_configured', 'message' => 'Driver is set to: ' . $driver . ' (not SMTP)']);
}

$smtp = $mc['smtp'] ?? [];

$test_results = [];

// Test 1: Check required config
$test_results[] = ['test' => 'Config Check', 'host' => $smtp['host'], 'port' => $smtp['port'], 'username' => $smtp['username']];

// Test 2: Try DNS resolution
$test_results[] = ['test' => 'DNS Resolution', 'result' => gethostbyname($smtp['host'])];

// Test 3: Try socket connection
$remote = $smtp['host'];
if (($smtp['encryption'] ?? '') === 'ssl') {
    $remote = 'ssl://' . $smtp['host'];
}

$sock = @fsockopen($remote, (int)($smtp['port'] ?? 25), $errno, $errstr, 5);
if ($sock) {
    fclose($sock);
    $test_results[] = ['test' => 'Socket Connection', 'status' => 'SUCCESS'];
} else {
    $test_results[] = ['test' => 'Socket Connection', 'status' => 'FAILED', 'errno' => $errno, 'error' => $errstr];
}

json_ok(['tests' => $test_results]);
