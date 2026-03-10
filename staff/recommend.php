<?php
require_once __DIR__ . '/../app/auth.php';

require_login();
require_role(['Staff']);
require_post();
csrf_verify_or_die();

$id = (int)($_POST['id'] ?? 0);
$recommendation = trim((string)($_POST['staff_recommendation'] ?? ''));

if (!$id) {
    http_response_code(400);
    exit('Invalid request.');
}

$stmt = db()->prepare('UPDATE submissions SET staff_recommendation = ? WHERE id = ?');
$stmt->execute([$recommendation !== '' ? $recommendation : null, $id]);

header('Location: /uccrdc/staff/view_submission.php?id=' . $id);
exit;
