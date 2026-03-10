<?php
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/mailer.php';

require_login();
require_role(['Admin']);
require_post();
csrf_verify_or_die();

$accept = (string)($_SERVER['HTTP_ACCEPT'] ?? '');
$xrw = (string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '');
$wantsJson = str_contains($accept, 'application/json') || $xrw === 'XMLHttpRequest' || (($_POST['ajax'] ?? '') === '1');

$id = (int)($_POST['id'] ?? 0);
$action = sanitize_string($_POST['action'] ?? '');
$adminComment = trim((string)($_POST['admin_comment'] ?? ''));

$status = match ($action) {
    'approve' => 'Approved',
    'reject' => 'Rejected',
    default => null,
};

if (!$id || !$status) {
    http_response_code(400);
    exit('Invalid request.');
}

$stmt = db()->prepare('UPDATE submissions SET status = ?, admin_comment = ? WHERE id = ?');
$stmt->execute([$status, $adminComment !== '' ? $adminComment : null, $id]);

// Email author (best-effort)
try {
    $stmt = db()->prepare('SELECT s.id, s.title, s.status, s.admin_comment, a.email, a.first_name, a.last_name
                           FROM submissions s JOIN authors a ON a.id = s.author_id
                           WHERE s.id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if ($row && !empty($row['email'])) {
        $subject = 'ISSN Application ' . $row['status'] . ' (#' . (int)$row['id'] . ')';
        $commentHtml = '';
        if (!empty($row['admin_comment'])) {
            $commentHtml = '<p><b>Admin Comment:</b><br>' . nl2br(htmlspecialchars((string)$row['admin_comment'])) . '</p>';
        }
        $body = format_email_wrapper(
            'Application Update',
            '<p>Hi ' . htmlspecialchars(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')) . ',</p>' .
            '<p>Your ISSN application has been updated by the admin.</p>' .
            '<p><b>Reference ID:</b> #' . (int)$row['id'] . '<br>' .
            '<b>Title:</b> ' . htmlspecialchars((string)$row['title']) . '<br>' .
            '<b>Status:</b> <b>' . htmlspecialchars((string)$row['status']) . '</b></p>' .
            $commentHtml .
            '<p>You may log in to the portal to view full details.</p>'
        );
        send_email((string)$row['email'], $subject, $body);
    }
} catch (Throwable $e) {
    // ignore email failures
}

if ($wantsJson) {
    json_ok([
        'id' => $id,
        'status' => $status,
        'admin_comment' => $adminComment !== '' ? $adminComment : null,
    ]);
}

header('Location: /uccrdc/admin/view_submission.php?id=' . $id);
exit;
