<?php
require_once __DIR__ . '/../app/auth.php';

require_login();

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    http_response_code(400);
    exit('Invalid request.');
}

$user = current_user();

$stmt = db()->prepare('SELECT s.id, s.author_id, s.pdf_path, s.title FROM submissions s WHERE s.id = ?');
$stmt->execute([$id]);
$row = $stmt->fetch();

if (!$row) {
    http_response_code(404);
    exit('Not found.');
}

// Authorization
if ($user['role'] === 'Author' && (int)$row['author_id'] !== (int)$user['id']) {
    http_response_code(403);
    exit('Forbidden');
}
if (!in_array($user['role'], ['Author', 'Staff', 'Admin'], true)) {
    http_response_code(403);
    exit('Forbidden');
}

$pdfPath = (string)$row['pdf_path'];
if (!str_starts_with($pdfPath, '/uploads/pubs/')) {
    http_response_code(500);
    exit('Invalid file path.');
}

$projectRoot = realpath(__DIR__ . '/..');
if (!$projectRoot) {
    http_response_code(500);
    exit('Server error.');
}

$fullPath = realpath($projectRoot . $pdfPath);
if (!$fullPath || strpos($fullPath, $projectRoot) !== 0 || !is_file($fullPath)) {
    http_response_code(404);
    exit('File not found.');
}

$filename = preg_replace('/[^a-zA-Z0-9._-]+/', '_', (string)$row['title']);
$filename = trim($filename, '._-');
if ($filename === '') {
    $filename = 'publication';
}
$filename .= '.pdf';

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $filename . '"');
header('X-Content-Type-Options: nosniff');
header('Content-Length: ' . filesize($fullPath));

readfile($fullPath);
exit;
