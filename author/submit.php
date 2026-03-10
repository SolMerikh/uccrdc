<?php
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/mailer.php';

require_login();
require_role(['Author']);

require_post();
csrf_verify_or_die();

$user = current_user();

$title = sanitize_string($_POST['title'] ?? '');
$startingDate = sanitize_string($_POST['starting_date'] ?? '');
$frequency = sanitize_string($_POST['frequency'] ?? '');
$language = sanitize_string($_POST['language'] ?? '');
$retailPrice = parse_nullable_int($_POST['suggested_retail_price'] ?? null);

$formerly = (int)($_POST['formerly_published'] ?? 0);
$formerTitle = sanitize_string($_POST['former_title'] ?? '');
$formerForm = sanitize_string($_POST['former_form_of_publication'] ?? '');
$formerStart = sanitize_string($_POST['former_starting_date'] ?? '');
$formerEnd = sanitize_string($_POST['former_ending_date'] ?? '');
$formerUrl = sanitize_string($_POST['former_url'] ?? '');

$formatIds = $_POST['format_ids'] ?? [];
if (!is_array($formatIds)) {
    $formatIds = [];
}
$formatIds = array_values(array_unique(array_map('intval', $formatIds)));

$errors = [];
if ($title === '') $errors[] = 'Title of Publication is required.';
if ($startingDate === '') $errors[] = 'Starting Date is required.';
if ($frequency === '') $errors[] = 'Frequency is required.';
if ($language === '') $errors[] = 'Language is required.';
if (($_POST['suggested_retail_price'] ?? '') !== '' && $retailPrice === null) $errors[] = 'Suggested Retail Price must be a whole number.';
if (count($formatIds) < 1) $errors[] = 'Please select at least one Form of Publication.';

if ($formerly === 1) {
    if ($formerTitle === '') $errors[] = 'Former Title is required when formerly published is YES.';
    if ($formerForm === '') $errors[] = 'Former Form of Publication is required when formerly published is YES.';
}

if (!isset($_FILES['publication_pdf'])) {
    $errors[] = 'Publication PDF is required.';
}

if (!isset($_FILES['manuscript_file'])) {
    $errors[] = 'Manuscript file is required.';
}

if (!isset($_FILES['editorial_page_file'])) {
    $errors[] = 'Editorial page file is required.';
}

if ($formatIds) {
    $in = implode(',', array_fill(0, count($formatIds), '?'));
    $stmt = db()->prepare("SELECT id, name FROM publication_formats WHERE is_active = 1 AND id IN ($in)");
    $stmt->execute($formatIds);
    $formatRows = $stmt->fetchAll();
    if (count($formatRows) !== count($formatIds)) {
        $errors[] = 'One or more selected formats are invalid.';
    }
} else {
    $formatRows = [];
}

$config = app_config();
$uploads = $config['uploads'];

$pdfRelativePath = null;
$manuscriptRelativePath = null;
$editorialRelativePath = null;

if (!$errors) {
    try {

    // PUBLICATION PDF
    $pubFile = $_FILES['publication_pdf'];
    $original = (string)($pubFile['name'] ?? '');
    $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));

    if ($ext !== 'pdf') {
        $errors[] = 'Publication file must be a PDF.';
    } else {
        if (($pubFile['tmp_name'] ?? '') === '' || !is_pdf_file($pubFile['tmp_name'])) {
            $errors[] = 'Uploaded publication file is not a valid PDF.';
        }
    }

    if (!$errors) {
        $pdfRelativePath = move_uploaded_file_validated(
            $pubFile,
            $uploads['publication_pdf_dir'],
            ['pdf'],
            $uploads['max_pdf_bytes']
        );
    }


    // MANUSCRIPT FILE
    $manFile = $_FILES['manuscript_file'];
    if (!$errors) {
        $manuscriptRelativePath = move_uploaded_file_validated(
            $manFile,
            $uploads['publication_pdf_dir'],
            ['pdf'],
            $uploads['max_pdf_bytes']
        );
    }


    // EDITORIAL PAGE FILE
    $edFile = $_FILES['editorial_page_file'];
    if (!$errors) {
        $editorialRelativePath = move_uploaded_file_validated(
            $edFile,
            $uploads['publication_pdf_dir'],
            ['pdf'],
            $uploads['max_pdf_bytes']
        );
    }

} catch (Throwable $e) {
    $errors[] = 'Failed to upload files. Please try again.';
}
}

if ($errors) {
    $pageTitle = 'Submit Publication';
    require_once __DIR__ . '/../includes/dashboard_header.php';
    echo '<div class="container py-4">';
    echo '<div class="alert alert-danger"><div class="fw-semibold mb-1">Please fix the following:</div><ul class="mb-0">';
    foreach ($errors as $err) {
        echo '<li>' . htmlspecialchars($err) . '</li>';
    }
    echo '</ul></div>';
    echo '<a class="btn btn-outline-secondary" href="/uccrdc/author/new_submission.php"><i class="fa-solid fa-arrow-left"></i> Back to Form</a>';
    echo '</div>';
    require_once __DIR__ . '/../includes/dashboard_footer.php';
    exit;
}

try {
    db()->beginTransaction();

    $stmt = db()->prepare('INSERT INTO submissions
(author_id, title, starting_date, frequency, language, suggested_retail_price,
 formerly_published, former_title, former_form_of_publication, former_starting_date, former_ending_date, former_url,
 pdf_path, manuscript_path, editorialPage_path, status)
         VALUES
        (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "Pending")
    ');

    $stmt->execute([
        $user['id'],
        $title,
        $startingDate,
        $frequency,
        $language,
        $retailPrice,
        $formerly ? 1 : 0,
        $formerly ? $formerTitle : null,
        $formerly ? $formerForm : null,
        $formerly ? ($formerStart ?: null) : null,
        $formerly ? ($formerEnd ?: null) : null,
        $formerly ? ($formerUrl ?: null) : null,
        $pdfRelativePath,
        $manuscriptRelativePath,
        $editorialRelativePath,
    ]);

    $submissionId = (int)db()->lastInsertId();

    if ($formatIds) {
        $stmt = db()->prepare('INSERT INTO submission_formats (submission_id, format_id) VALUES (?, ?)');
        foreach ($formatIds as $fid) {
            $stmt->execute([$submissionId, (int)$fid]);
        }
    }

    db()->commit();

    // Email notifications (best-effort)
    try {
        // Author confirmation
        $stmt = db()->prepare('SELECT email, first_name, last_name FROM authors WHERE id = ?');
        $stmt->execute([$user['id']]);
        $author = $stmt->fetch();
        if ($author && !empty($author['email'])) {
            $subject = 'ISSN Application Received (#' . $submissionId . ')';
            $body = format_email_wrapper(
                'Application Received',
                '<p>Hi ' . htmlspecialchars(($author['first_name'] ?? '') . ' ' . ($author['last_name'] ?? '')) . ',</p>' .
                '<p>Your ISSN application has been submitted and is now <b>Pending</b> review.</p>' .
                '<p><b>Reference ID:</b> #' . (int)$submissionId . '<br>' .
                '<b>Title:</b> ' . htmlspecialchars((string)$title) . '</p>' .
                '<p>You will receive another email once an admin takes action or leaves a comment.</p>'
            );
            send_email((string)$author['email'], $subject, $body);
        }

        // Admin notification
        $mc = mail_config();
        $override = trim((string)($mc['admin_notify_to'] ?? ''));
        if ($override !== '') {
            $admins = array_map('trim', explode(',', $override));
        } else {
            $stmt = db()->query("SELECT email FROM authors WHERE role = 'Admin' AND status = 'Active' AND email <> ''");
            $admins = array_map(fn($r) => $r['email'], $stmt->fetchAll());
        }

        if (!empty($admins)) {
            $subject = 'New ISSN Application Submitted (#' . $submissionId . ')';
            $body = format_email_wrapper(
                'New Application Submitted',
                '<p>A new ISSN application has been submitted.</p>' .
                '<p><b>Reference ID:</b> #' . (int)$submissionId . '<br>' .
                '<b>Title:</b> ' . htmlspecialchars((string)$title) . '</p>' .
                '<p>Open Admin Dashboard to review.</p>'
            );
            send_email_many($admins, $subject, $body);
        }
    } catch (Throwable $e) {
        // ignore email failures
    }

    header('Location: /uccrdc/author/dashboard.php');
    exit;
} catch (Throwable $e) {
    if (db()->inTransaction()) {
        db()->rollBack();
    }
    http_response_code(500);
    echo 'Failed to submit. Please try again.';
}
