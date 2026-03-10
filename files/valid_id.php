<?php
require_once __DIR__ . '/../app/auth.php';

// Admin-only: streams an Author's uploaded valid ID (image/PDF) inline.
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    http_response_code(405);
    exit('Method Not Allowed');
}

require_login();
require_role(['Admin']);

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    exit('Bad Request');
}

$stmt = db()->prepare('SELECT valid_id_path FROM authors WHERE id = ? AND role = "Author" LIMIT 1');
$stmt->execute([$id]);
$row = $stmt->fetch();

$stored = (string)($row['valid_id_path'] ?? '');
if (!$row || $stored === '') {
    http_response_code(404);
    exit('Not Found');
}

// Normalize path.
$stored = str_replace('\\', '/', $stored);
$stored = '/' . ltrim($stored, '/');

$config = app_config();
$uploads = $config['uploads'] ?? [];
$validIdDir = (string)($uploads['valid_id_dir'] ?? '');

$projectRoot = realpath(__DIR__ . '/..');
$validIdRoot = $validIdDir !== '' ? realpath($validIdDir) : false;
$full = $projectRoot ? realpath($projectRoot . $stored) : false;

if (!$projectRoot || !$validIdRoot || !$full) {
    http_response_code(404);
    exit('Not Found');
}

// Ensure the file is inside the valid id directory.
if (strpos($full, $validIdRoot) !== 0) {
    http_response_code(404);
    exit('Not Found');
}

if (!is_file($full) || !is_readable($full)) {
    http_response_code(404);
    exit('Not Found');
}

$ext = strtolower(pathinfo($full, PATHINFO_EXTENSION));
$mime = match ($ext) {
    'jpg', 'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'pdf' => 'application/pdf',
    default => 'application/octet-stream',
};

header('X-Content-Type-Options: nosniff');
header('Content-Type: ' . $mime);
header('Content-Disposition: inline; filename="valid_id_' . $id . '.' . $ext . '"');
header('Cache-Control: private, max-age=300');
header('Content-Length: ' . (string)filesize($full));

readfile($full);
exit;
