<?php
require_once __DIR__ . '/../app/auth.php';

require_login();
require_role(['Author']);

require_post();
csrf_verify_or_die();

start_session();
$draft = $_SESSION['draft_submission'] ?? null;
if ($draft && isset($draft['pdf_path'])) {
    $projectRoot = realpath(__DIR__ . '/..');
    $full = $projectRoot ? realpath($projectRoot . $draft['pdf_path']) : false;
    if ($full && $projectRoot && strpos($full, $projectRoot) === 0) {
        @unlink($full);
    }
}

unset($_SESSION['draft_submission']);
header('Location: /uccrdc/author/new_submission.php');
exit;
