<?php
require_once __DIR__ . '/../app/db.php';

$id = (int)($_GET['id'] ?? 0);

$stmt = db()->prepare("SELECT editorialPage_path FROM submissions WHERE id=?");
$stmt->execute([$id]);
$row = $stmt->fetch();

if (!$row || !$row['editorialPage_path']) {
    http_response_code(404);
    exit('Editorial page not found');
}

$file = __DIR__ . '/../uploads/pubs/' . basename($row['editorialPage_path']);

if (!file_exists($file)) {
    http_response_code(404);
    exit('File missing');
}

header("Content-Type: application/pdf");
readfile($file);