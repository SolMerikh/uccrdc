<?php
require_once __DIR__ . '/../app/auth.php';

require_login();
require_role(['Author']);

start_session();
$user = current_user();
$draft = $_SESSION['draft_submission'] ?? null;

if (!$draft || (int)($draft['author_id'] ?? 0) !== (int)$user['id']) {
    http_response_code(404);
    exit('Not found.');
}

$pdfPath = (string)($draft['pdf_path'] ?? '');
if (!str_starts_with($pdfPath, '/uploads/pubs/')) {
    http_response_code(404);
    exit('Not found.');
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

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="draft_publication.pdf"');
header('X-Content-Type-Options: nosniff');
header('Content-Length: ' . filesize($fullPath));

readfile($fullPath);
exit;
