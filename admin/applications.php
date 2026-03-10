<?php
require_once __DIR__ . '/../app/auth.php';
require_login();
require_role(['Admin', 'Staff']);

$pageTitle = 'All Applications';
require_once __DIR__ . '/../includes/dashboard_header.php';

$user = current_user();
$isAdmin = $user && ($user['role'] ?? '') === 'Admin';

$createErrors = [];
$createOld = [];
$showCreateModal = false;

// Manual admin/staff submission creation
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && (($_POST['action'] ?? '') === 'create_submission')) {
  require_post();
  csrf_verify_or_die();

  $createOld = [
    'author_id' => (string)($_POST['author_id'] ?? ''),
    'author_query' => (string)($_POST['author_query'] ?? ''),
    'title' => (string)($_POST['title'] ?? ''),
    'starting_date' => (string)($_POST['starting_date'] ?? ''),
    'frequency' => (string)($_POST['frequency'] ?? ''),
    'language' => (string)($_POST['language'] ?? ''),
    'suggested_retail_price' => (string)($_POST['suggested_retail_price'] ?? ''),
    'formerly_published' => (string)($_POST['formerly_published'] ?? '0'),
    'former_title' => (string)($_POST['former_title'] ?? ''),
    'former_form_of_publication' => (string)($_POST['former_form_of_publication'] ?? ''),
    'former_starting_date' => (string)($_POST['former_starting_date'] ?? ''),
    'former_ending_date' => (string)($_POST['former_ending_date'] ?? ''),
    'format_ids' => $_POST['format_ids'] ?? [],
  ];

  $authorId = (int)($_POST['author_id'] ?? 0);
  $authorQuery = trim((string)($_POST['author_query'] ?? ''));
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

  $formatIds = $_POST['format_ids'] ?? [];
  if (!is_array($formatIds)) {
    $formatIds = [];
  }
  $formatIds = array_values(array_unique(array_map('intval', $formatIds)));

  if ($authorId <= 0 && $authorQuery === '') $createErrors[] = 'Author is required.';
  if ($title === '') $createErrors[] = 'Title of Publication is required.';
  if ($startingDate === '') $createErrors[] = 'Starting Date is required.';
  if ($frequency === '') $createErrors[] = 'Frequency is required.';
  if ($language === '') $createErrors[] = 'Language is required.';
  if (($_POST['suggested_retail_price'] ?? '') !== '' && $retailPrice === null) $createErrors[] = 'Suggested Retail Price must be a whole number.';
  if (count($formatIds) < 1) $createErrors[] = 'Please select at least one Form of Publication.';

  if ($formerly === 1) {
    if ($formerTitle === '') $createErrors[] = 'Former Title is required when formerly published is YES.';
    if ($formerForm === '') $createErrors[] = 'Former Form of Publication is required when formerly published is YES.';
  }

  if (!isset($_FILES['publication_pdf'])) {
    $createErrors[] = 'Publication PDF is required.';
  }

  // Resolve typed author (email or "Last, First") when author_id isn't set.
  if (!$createErrors && $authorId <= 0 && $authorQuery !== '') {
    $resolvedId = 0;

    // If the input contains an email anywhere (e.g., "Last, First — email"), prefer that.
    $emailFound = '';
    if (preg_match('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', $authorQuery, $m)) {
      $emailFound = strtolower(trim((string)$m[0]));
    }

    if ($emailFound !== '') {
      $stmt = db()->prepare("SELECT id FROM authors WHERE LOWER(email) = ? AND role = 'Author' AND status = 'Active'");
      $stmt->execute([$emailFound]);
      $row = $stmt->fetch();
      $resolvedId = $row ? (int)$row['id'] : 0;
    } else {
      // Accept "Last, First" optionally with trailing "— email".
      $namePart = trim(explode('—', $authorQuery)[0]);
      $pieces = array_map('trim', explode(',', $namePart));
      $last = $pieces[0] ?? '';
      $first = $pieces[1] ?? '';
      if ($last !== '' && $first !== '') {
        $stmt = db()->prepare("SELECT id FROM authors WHERE LOWER(last_name) = LOWER(?) AND LOWER(first_name) = LOWER(?) AND role = 'Author' AND status = 'Active'");
        $stmt->execute([$last, $first]);
        $matches = $stmt->fetchAll();
        if (count($matches) === 1) {
          $resolvedId = (int)$matches[0]['id'];
        } elseif (count($matches) > 1) {
          $createErrors[] = 'Multiple authors match that name. Please type the author\'s email instead.';
        }
      }
    }

    if (!$createErrors) {
      if ($resolvedId > 0) {
        $authorId = $resolvedId;
      } else {
        $createErrors[] = 'Author not found. Please select a suggestion or type the author\'s email.';
      }
    }
  }

  if (!$createErrors && $authorId > 0) {
    $stmt = db()->prepare("SELECT id FROM authors WHERE id = ? AND role = 'Author' AND status = 'Active'");
    $stmt->execute([$authorId]);
    if (!$stmt->fetch()) {
      $createErrors[] = 'Selected author is invalid or inactive.';
    }
  }

  $formatRows = [];
  if (!$createErrors && $formatIds) {
    $in = implode(',', array_fill(0, count($formatIds), '?'));
    $stmt = db()->prepare("SELECT id, name FROM publication_formats WHERE is_active = 1 AND id IN ($in)");
    $stmt->execute($formatIds);
    $formatRows = $stmt->fetchAll();
    if (count($formatRows) !== count($formatIds)) {
      $createErrors[] = 'One or more selected formats are invalid.';
    }
  }

  $config = app_config();
  $uploads = $config['uploads'];
$pdfRelativePath = null;
$manuscriptRelativePath = null;
$editorialRelativePath = null;

if (!$createErrors) {

  try {

    /* COVER PDF */
    if (!empty($_FILES['publication_pdf']['name'])) {

      $file = $_FILES['publication_pdf'];
      $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

      if ($ext !== 'pdf') {
        $createErrors[] = 'Publication file must be a PDF.';
      } else {
        $pdfRelativePath = move_uploaded_file_validated(
          $file,
          $uploads['publication_pdf_dir'],
          ['pdf'],
          $uploads['max_pdf_bytes']
        );
      }
    }


    /* MANUSCRIPT FILE */
    if (!empty($_FILES['manuscript_file']['name'])) {

      $file = $_FILES['manuscript_file'];

      $manuscriptRelativePath = move_uploaded_file_validated(
        $file,
        $uploads['publication_pdf_dir'],
        ['pdf'],
        $uploads['max_pdf_bytes']
      );
    }


    /* EDITORIAL PAGE FILE */
    if (!empty($_FILES['editorial_page_file']['name'])) {

      $file = $_FILES['editorial_page_file'];

      $editorialRelativePath = move_uploaded_file_validated(
        $file,
        $uploads['publication_pdf_dir'],
        ['pdf'],
        $uploads['max_pdf_bytes']
      );
    }

  } catch (Throwable $e) {
    $createErrors[] = 'Failed to upload files.';
  }
}

  if (!$createErrors) {
    try {
      db()->beginTransaction();

      $stmt = db()->prepare('INSERT INTO submissions
        (author_id, title, starting_date, frequency, language, suggested_retail_price,
         formerly_published, former_title, former_form_of_publication, former_starting_date, former_ending_date,
         pdf_path, manuscript_path, editorialPage_path, status)
         VALUES
        (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "Pending")
      ');

      $stmt->execute([
        $authorId,
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
        $pdfRelativePath,
        $manuscriptRelativePath,
        $editorialRelativePath
      ]);

      $submissionId = (int)db()->lastInsertId();

      if ($formatIds) {
        $stmt = db()->prepare('INSERT INTO submission_formats (submission_id, format_id) VALUES (?, ?)');
        foreach ($formatIds as $fid) {
          $stmt->execute([$submissionId, (int)$fid]);
        }
      }

      db()->commit();

      header('Location: /uccrdc/admin/applications.php?created=1');
      exit;
    } catch (Throwable $e) {
      if (db()->inTransaction()) {
        db()->rollBack();
      }
      $createErrors[] = 'Failed to create application. Please try again.';
    }
  }

  $showCreateModal = true;
}

$status = sanitize_string($_GET['status'] ?? '');
$allowed = ['Pending','Approved','Rejected'];

$authors = db()->query("SELECT id, first_name, last_name, email FROM authors WHERE role = 'Author' AND status = 'Active' ORDER BY last_name, first_name")->fetchAll();
$formatsList = db()->query('SELECT id, name FROM publication_formats WHERE is_active = 1 ORDER BY name')->fetchAll();

$sql = 'SELECT
  s.id,
  s.title,
  s.status,
  s.created_at,
  s.pdf_path,
  s.manuscript_path,
  s.editorialPage_path,
  s.starting_date,
  s.frequency,
  s.language,
  s.suggested_retail_price,
  s.formerly_published,
  s.former_title,
  s.former_form_of_publication,
  s.former_starting_date,
  s.former_ending_date,
  s.staff_recommendation,
  s.admin_comment,
  a.email,
  a.first_name,
  a.last_name,
  a.contact_number,
  (SELECT GROUP_CONCAT(pf.name ORDER BY pf.name SEPARATOR "||")
     FROM submission_formats sf
     JOIN publication_formats pf ON pf.id = sf.format_id
    WHERE sf.submission_id = s.id) AS format_names
  FROM submissions s
  JOIN authors a ON a.id = s.author_id';
$params = [];

if (in_array($status, $allowed, true)) {
  $sql .= ' WHERE s.status = ?';
  $params[] = $status;
} else {
  $status = '';
}

$sql .= ' ORDER BY s.created_at DESC';

$stmt = db()->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$csrf = csrf_token();
?>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">

<div class="container-fluid p-4">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
      <h1 class="h4 mb-0">All Applications</h1>
      <div class="text-muted small">Review and approve/reject submissions.</div>
    </div>
    <div class="d-flex gap-2">
      <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#createApplicationModal">
        <i class="fa-solid fa-plus me-1"></i> Add Application
      </button>
      <form method="get" class="d-flex gap-2 align-items-center">
        <select class="form-select form-select-sm" name="status" onchange="this.form.submit()" style="width: 160px">
          <option value="" <?= $status==='' ? 'selected' : '' ?>>All statuses</option>
          <option value="Pending" <?= $status==='Pending' ? 'selected' : '' ?>>Pending</option>
          <option value="Approved" <?= $status==='Approved' ? 'selected' : '' ?>>Approved</option>
          <option value="Rejected" <?= $status==='Rejected' ? 'selected' : '' ?>>Rejected</option>
        </select>
      </form>
    </div>
  </div>

  <?php if (!empty($createErrors)): ?>
    <div class="alert alert-danger">
      <div class="fw-semibold mb-1">Please fix the following:</div>
      <ul class="mb-0">
        <?php foreach ($createErrors as $err): ?>
          <li><?= htmlspecialchars($err) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <div class="card shadow-sm">
    <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
      <h2 class="mb-0 h5"><i class="fa-solid fa-folder-open me-2"></i>All Applications</h2>
    </div>
    <div class="card-body p-3">
      <div class="table-responsive d-none d-md-block">
        <table id="applicationsTable" class="table table-hover mb-0">
          <thead class="table-success">
            <tr>
              <th>Title</th>
              <th>Author</th>
              <th style="width:140px">Status</th>
              <th style="width:180px">Submitted</th>
              <th class="text-center" style="width:140px">Actions</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($rows as $r): ?>
            <tr data-submission-id="<?= (int)$r['id'] ?>">
              <td class="fw-semibold"><?= htmlspecialchars($r['title']) ?></td>
              <td class="text-muted small"><?= htmlspecialchars($r['first_name'] . ' ' . $r['last_name']) ?><br><?= htmlspecialchars($r['email']) ?></td>
              <td>
                <?php $badge = match ($r['status']) { 'Approved'=>'success','Rejected'=>'danger', default=>'warning' }; ?>
                <span class="badge text-bg-<?= $badge ?>"><?= htmlspecialchars($r['status']) ?></span>
              </td>
              <td class="text-muted small"><?= htmlspecialchars($r['created_at']) ?></td>
              <td class="text-center">
                <button
                  type="button"
                  class="btn btn-outline-secondary btn-sm view-application-btn"
                  data-bs-toggle="modal"
                  data-bs-target="#viewApplicationModal"
                  data-id="<?= (int)$r['id'] ?>"
                  data-title="<?= htmlspecialchars($r['title'], ENT_QUOTES) ?>"
                  data-status="<?= htmlspecialchars($r['status'], ENT_QUOTES) ?>"
                  data-submitted="<?= htmlspecialchars($r['created_at'], ENT_QUOTES) ?>"
                  data-author="<?= htmlspecialchars($r['first_name'] . ' ' . $r['last_name'], ENT_QUOTES) ?>"
                  data-email="<?= htmlspecialchars($r['email'], ENT_QUOTES) ?>"
                  data-contact="<?= htmlspecialchars((string)($r['contact_number'] ?? ''), ENT_QUOTES) ?>"
                  data-starting="<?= htmlspecialchars((string)($r['starting_date'] ?? ''), ENT_QUOTES) ?>"
                  data-pdf="<?= htmlspecialchars((string)($r['pdf_path'] ?? ''), ENT_QUOTES) ?>"
                  data-manuscript="<?= htmlspecialchars((string)($r['manuscript_path'] ?? ''), ENT_QUOTES) ?>"
                  data-editorial="<?= htmlspecialchars((string)($r['editorialPage_path'] ?? ''), ENT_QUOTES) ?>"
                  data-frequency="<?= htmlspecialchars((string)($r['frequency'] ?? ''), ENT_QUOTES) ?>"
                  data-language="<?= htmlspecialchars((string)($r['language'] ?? ''), ENT_QUOTES) ?>"
                  data-price="<?= htmlspecialchars((string)($r['suggested_retail_price'] ?? ''), ENT_QUOTES) ?>"
                  data-formats="<?= htmlspecialchars((string)($r['format_names'] ?? ''), ENT_QUOTES) ?>"
                  data-formerly="<?= (int)($r['formerly_published'] ?? 0) ?>"
                  data-former-title="<?= htmlspecialchars((string)($r['former_title'] ?? ''), ENT_QUOTES) ?>"
                  data-former-form="<?= htmlspecialchars((string)($r['former_form_of_publication'] ?? ''), ENT_QUOTES) ?>"
                  data-former-start="<?= htmlspecialchars((string)($r['former_starting_date'] ?? ''), ENT_QUOTES) ?>"
                  data-former-end="<?= htmlspecialchars((string)($r['former_ending_date'] ?? ''), ENT_QUOTES) ?>"
                  data-staff="<?= htmlspecialchars((string)($r['staff_recommendation'] ?? ''), ENT_QUOTES) ?>"
                  data-comment="<?= htmlspecialchars((string)($r['admin_comment'] ?? ''), ENT_QUOTES) ?>"
                >
                  <i class="fa-solid fa-eye"></i> View
                </button>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Create Application Modal -->
<div class="modal fade" id="createApplicationModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-fullscreen-sm-down">
    <div class="modal-content">
      <div class="modal-header bg-success text-white">
        <div>
          <h5 class="modal-title mb-0"><i class="fa-solid fa-plus me-2"></i>Add Application</h5>
          <div class="small opacity-75">Create a submission manually for an author.</div>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="post" enctype="multipart/form-data" id="createSubmissionForm">
        <div class="modal-body">
          <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">
          <input type="hidden" name="action" value="create_submission">

          <div class="alert alert-success border-0 py-2 px-3 mb-3">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
              <div class="fw-semibold"><i class="fa-solid fa-circle-info me-2"></i>Tip</div>
              <div class="small">Type the author name/email, upload the PDF, then click <b>Create</b>.</div>
            </div>
          </div>

          <div class="row g-3">
            <div class="col-lg-6">
              <div class="card shadow-sm">
                <div class="card-header bg-white fw-semibold"><i class="fa-solid fa-user me-2"></i>Author</div>
                <div class="card-body">
                  <label class="form-label fw-semibold">Author Name / Email</label>
                  <?php
                    $oldAuthorId = (string)($createOld['author_id'] ?? '');
                    $oldAuthorQuery = (string)($createOld['author_query'] ?? '');
                    if ($oldAuthorQuery === '' && $oldAuthorId !== '') {
                      foreach ($authors as $a) {
                        if ((string)$a['id'] === $oldAuthorId) {
                          $oldAuthorQuery = trim((string)$a['last_name'] . ', ' . (string)$a['first_name']);
                          $email = (string)($a['email'] ?? '');
                          if ($email !== '') $oldAuthorQuery .= ' — ' . $email;
                          break;
                        }
                      }
                    }
                  ?>
                  <input type="hidden" name="author_id" id="createAuthorId" value="<?= htmlspecialchars($oldAuthorId, ENT_QUOTES) ?>">
                  <div class="input-group">
                    <span class="input-group-text"><i class="fa-solid fa-magnifying-glass"></i></span>
                    <input
                      class="form-control"
                      type="text"
                      name="author_query"
                      id="createAuthorQuery"
                      list="authorsDatalist"
                      placeholder="Type author name or email"
                      autocomplete="off"
                      value="<?= htmlspecialchars($oldAuthorQuery, ENT_QUOTES) ?>"
                      required
                    >
                    <button class="btn btn-outline-secondary" type="button" id="createAuthorClearBtn" title="Clear">
                      <i class="fa-solid fa-xmark"></i>
                    </button>
                  </div>
                  <datalist id="authorsDatalist">
                    <?php foreach ($authors as $a): ?>
                      <?php
                        $aLabel = trim((string)$a['last_name'] . ', ' . (string)$a['first_name']);
                        $aEmail = (string)($a['email'] ?? '');
                        $full = $aLabel . ($aEmail !== '' ? ' — ' . $aEmail : '');
                      ?>
                      <option value="<?= htmlspecialchars($full, ENT_QUOTES) ?>"></option>
                      <?php if ($aEmail !== ''): ?>
                        <option value="<?= htmlspecialchars($aEmail, ENT_QUOTES) ?>"></option>
                      <?php endif; ?>
                    <?php endforeach; ?>
                  </datalist>
                  <div class="form-text">
                    Start typing to see suggestions. If names are duplicated, type the email.
                  </div>
                  <div class="mt-2 d-flex align-items-center gap-2">
                    <span class="badge text-bg-light border" id="createAuthorStatus">Not selected</span>
                    <span class="text-muted small" id="createAuthorHint">Pick from suggestions to auto-fill the author.</span>
                  </div>
                </div>
              </div>
            </div>

            <div class="col-lg-6">
                <div class="card shadow-sm">
                  <div class="card-header bg-white fw-semibold">
                    <i class="fa-solid fa-file-pdf me-2"></i>Publication PDF
                  </div>

                  <div class="card-body">
                    <div class="form-text mb-3">PDF only. Make sure it matches the title.</div>
                    <label class="form-label fw-semibold">Upload Cover File</label>
                    <input class="form-control mb-3" type="file" name="publication_pdf" accept="application/pdf" required>
                    

                    <label class="form-label fw-semibold">Upload Manuscript File</label>
                    <input class="form-control mb-3" type="file" name="manuscript_file" accept="application/pdf">

                    <label class="form-label fw-semibold">Upload Editorial Page File</label>
                    <input class="form-control" type="file" name="editorial_page_file" accept="application/pdf">

                  </div>
                </div>
              </div>

            <div class="col-12">
              <div class="card shadow-sm">
                <div class="card-header bg-white fw-semibold"><i class="fa-solid fa-file-lines me-2"></i>Publication Details</div>
                <div class="card-body">
                  <div class="row g-3">
                    <div class="col-lg-6">
                      <label class="form-label fw-semibold">Title of Publication</label>
                      <input class="form-control" type="text" name="title" value="<?= htmlspecialchars((string)($createOld['title'] ?? '')) ?>" required>
                    </div>
                    <div class="col-lg-3">
                      <label class="form-label fw-semibold">Starting Date</label>
                      <input class="form-control" type="date" name="starting_date" value="<?= htmlspecialchars((string)($createOld['starting_date'] ?? '')) ?>" required>
                    </div>
                    <div class="col-lg-3">
                      <label class="form-label fw-semibold">Suggested Retail Price <span class="text-muted small">(optional)</span></label>
                      <input class="form-control" type="number" name="suggested_retail_price" inputmode="numeric" step="1" min="0" placeholder="Whole number" value="<?= htmlspecialchars((string)($createOld['suggested_retail_price'] ?? '')) ?>">
                    </div>

                    <div class="col-lg-6">
                      <label class="form-label fw-semibold">Frequency</label>
                      <input class="form-control" type="text" name="frequency" value="<?= htmlspecialchars((string)($createOld['frequency'] ?? '')) ?>" required>
                    </div>
                    <div class="col-lg-6">
                      <label class="form-label fw-semibold">Language</label>
                      <input class="form-control" type="text" name="language" value="<?= htmlspecialchars((string)($createOld['language'] ?? '')) ?>" required>
                    </div>

                    <div class="col-12">
                      <label class="form-label fw-semibold">Form of Publication</label>
                      <div class="row g-2">
                        <?php
                          $oldFormatIds = $createOld['format_ids'] ?? [];
                          if (!is_array($oldFormatIds)) $oldFormatIds = [];
                          $oldFormatIds = array_map('strval', $oldFormatIds);
                        ?>
                        <?php foreach ($formatsList as $f): ?>
                          <?php $fid = (int)$f['id']; $checked = in_array((string)$fid, $oldFormatIds, true) ? 'checked' : ''; ?>
                          <div class="col-md-4">
                            <div class="form-check border rounded-3 p-2 h-100">
                              <input class="form-check-input" type="checkbox" name="format_ids[]" id="createFormat<?= $fid ?>" value="<?= $fid ?>" <?= $checked ?>>
                              <label class="form-check-label" for="createFormat<?= $fid ?>"><?= htmlspecialchars((string)$f['name']) ?></label>
                            </div>
                          </div>
                        <?php endforeach; ?>
                      </div>
                    </div>

                    <div class="col-12">
                      <label class="form-label fw-semibold">Formerly Published?</label>
                      <?php $oldFormerly = (string)($createOld['formerly_published'] ?? '0'); ?>
                      <div class="d-flex gap-3">
                        <div class="form-check">
                          <input class="form-check-input" type="radio" name="formerly_published" id="createFormerNo" value="0" <?= $oldFormerly !== '1' ? 'checked' : '' ?>>
                          <label class="form-check-label" for="createFormerNo">No</label>
                        </div>
                        <div class="form-check">
                          <input class="form-check-input" type="radio" name="formerly_published" id="createFormerYes" value="1" <?= $oldFormerly === '1' ? 'checked' : '' ?>>
                          <label class="form-check-label" for="createFormerYes">Yes</label>
                        </div>
                      </div>
                    </div>

                    <div class="col-12" id="createFormerBlock">
                      <div class="border rounded-3 p-3 bg-light">
                        <div class="fw-semibold mb-2">Former Publication Details</div>
                        <div class="row g-3">
                          <div class="col-lg-6">
                            <label class="form-label fw-semibold">Former Title</label>
                            <input class="form-control" type="text" name="former_title" value="<?= htmlspecialchars((string)($createOld['former_title'] ?? '')) ?>">
                          </div>
                          <div class="col-lg-6">
                            <label class="form-label fw-semibold">Former Form of Publication</label>
                            <input class="form-control" type="text" name="former_form_of_publication" value="<?= htmlspecialchars((string)($createOld['former_form_of_publication'] ?? '')) ?>">
                          </div>
                          <div class="col-lg-6">
                            <label class="form-label fw-semibold">Former Starting Date <span class="text-muted small">(optional)</span></label>
                            <input class="form-control" type="date" name="former_starting_date" value="<?= htmlspecialchars((string)($createOld['former_starting_date'] ?? '')) ?>">
                          </div>
                          
                          <div class="col-lg-6">
                            <label class="form-label fw-semibold">Former Ending Date <span class="text-muted small">(optional)</span></label>
                            <input class="form-control" type="date" name="former_ending_date" value="<?= htmlspecialchars((string)($createOld['former_ending_date'] ?? '')) ?>">
                          </div>
                        </div>
                        <div class="col-lg-6">
                          <label class="form-label fw-semibold">Former URL <span class="text-muted small">(optional)</span></label>
                          <input class="form-control" type="url" name="former_url" value="<?= htmlspecialchars((string)($createOld['former_url'] ?? '')) ?>">
                        </div>
                      </div>
                    </div>

                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-success"><i class="fa-solid fa-check me-1"></i> Create</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- View Application Modal -->
<div class="modal fade" id="viewApplicationModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-fullscreen-sm-down">
    <div class="modal-content">
      <div class="modal-header bg-success text-white">
        <div>
          <h5 class="modal-title mb-0"><i class="fa-solid fa-eye me-2"></i>Review Submission</h5>
          <div class="small opacity-75">Check details, add a comment, then approve or reject.</div>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-lg-8">
            <div class="card shadow-sm">
              <div class="card-header bg-white d-flex align-items-center justify-content-between">
                <div class="fw-semibold"><i class="fa-solid fa-file-lines me-2"></i>Publication Details</div>
                <div class="text-end">
                  <div class="text-muted small">Status</div>
                  <span class="badge" id="appStatusBadge">-</span>
                </div>
              </div>
              <div class="card-body">
                <div class="mb-3">
                  <div class="text-muted small">Title</div>
                  <div class="h5 mb-0" id="appTitle">-</div>
                </div>

                <div class="row g-3">
                  <div class="col-md-3">
                    <div class="text-muted small"><i class="fa-regular fa-calendar me-1"></i>Starting Date</div>
                    <div class="fw-semibold" id="appStarting">-</div>
                  </div>
                  <div class="col-md-3">
                    <div class="text-muted small"><i class="fa-solid fa-rotate me-1"></i>Frequency</div>
                    <div class="fw-semibold" id="appFrequency">-</div>
                  </div>
                  <div class="col-md-3">
                    <div class="text-muted small"><i class="fa-solid fa-language me-1"></i>Language</div>
                    <div class="fw-semibold" id="appLanguage">-</div>
                  </div>
                  <div class="col-md-3">
                    <div class="text-muted small"><i class="fa-solid fa-tag me-1"></i>Suggested Retail Price</div>
                    <div class="fw-semibold" id="appPrice">-</div>
                  </div>

                  <div class="col-12">
                    <div class="text-muted small"><i class="fa-solid fa-layer-group me-1"></i>Format</div>
                    <div class="mt-1" id="appFormats"></div>
                  </div>

                  <div class="col-12">
                    <div class="text-muted small"><i class="fa-solid fa-clock-rotate-left me-1"></i>Former Publication</div>
                    <div class="fw-semibold" id="appFormerFlag">-</div>
                  </div>

                  <div class="col-12 d-none" id="appFormerBlock">
                    <div class="border rounded-3 p-3 bg-light">
                      <div class="fw-semibold mb-2">Former Publication Details</div>

                      <div class="text-muted small">Former Title</div>
                      <div class="fw-semibold" id="appFormerTitle">-</div>

                      <div class="row g-3 mt-1">
                        <div class="col-md-6 d-none" id="appFormerFormBlock">
                          <div class="text-muted small">Former Form of Publication</div>
                          <div class="fw-semibold" id="appFormerForm">-</div>
                        </div>
                        <div class="col-md-3 d-none" id="appFormerStartBlock">
                          <div class="text-muted small">Former Start</div>
                          <div class="fw-semibold" id="appFormerStart">-</div>
                        </div>
                        <div class="col-md-3 d-none" id="appFormerEndBlock">
                          <div class="text-muted small">Former End</div>
                          <div class="fw-semibold" id="appFormerEnd">-</div>
                        </div>
                      </div>
                    </div>
                  </div>

                  <div class="col-12">
                  <div class="row g-3">

                    <div class="col-md-4">
                      <div class="text-muted small">
                        <i class="fa-solid fa-file-pdf me-1"></i>Cover
                      </div>
                      <a id="appPdfLink" href="#" target="_blank" class="btn btn-outline-secondary w-100">
                        <i class="fa-solid fa-file-pdf text-danger me-1"></i> View Cover
                      </a>
                    </div>

                    <div class="col-md-4">
                      <div class="text-muted small">
                        <i class="fa-solid fa-file-lines me-1"></i>Manuscript File
                      </div>
                      <a id="appManuscriptLink" href="#" target="_blank" class="btn btn-outline-secondary w-100">
                        <i class="fa-solid fa-file-pdf text-danger me-1"></i> View Manuscript
                      </a>
                    </div>

                    <div class="col-md-4">
                      <div class="text-muted small">
                        <i class="fa-solid fa-users me-1"></i>Editorial Page
                      </div>
                      <a id="appEditorialLink" href="#" target="_blank" class="btn btn-outline-secondary w-100">
                        <i class="fa-solid fa-file-pdf text-danger me-1"></i> View Editorial Page
                      </a>
                    </div>

                  </div>
                </div>


                </div>
              </div>

              <div class="card-footer bg-white">
                <?php if ($isAdmin): ?>
                  <form class="w-100" id="appActionForm" onsubmit="return false;">
                    <input type="hidden" name="_csrf" id="appCsrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">
                    <input type="hidden" name="id" id="appSubmissionId" value="">

                    <div class="d-flex align-items-center justify-content-between mb-2">
                      <div class="fw-semibold"><i class="fa-solid fa-gavel me-2"></i>Admin Decision</div>
                      <div class="text-muted small">Approve or reject this submission.</div>
                    </div>

                    <div class="row g-2 align-items-end">
                      <div class="col-lg-8">
                        <label class="form-label fw-semibold mb-1">Comment to Author <span class="text-muted small">(optional)</span></label>
                        <textarea class="form-control" name="admin_comment" rows="2" placeholder="Add comment or required changes..." id="appCommentInput"></textarea>
                      </div>
                      <div class="col-lg-4">
                        <div class="d-grid gap-2 d-lg-flex justify-content-lg-end">
                          <button class="btn btn-success" type="button" id="appApproveBtn"><i class="fa-solid fa-check"></i> Approve</button>
                          <button class="btn btn-danger" type="button" id="appRejectBtn"><i class="fa-solid fa-xmark"></i> Reject</button>
                        </div>
                      </div>
                    </div>
                  </form>
                <?php else: ?>
                  <div class="text-muted small">Admin-only: approval/rejection is not available for Staff accounts.</div>
                <?php endif; ?>
              </div>
            </div>
          </div>

          <div class="col-lg-4">
            <div class="card shadow-sm">
              <div class="card-header bg-white fw-semibold"><i class="fa-solid fa-user me-2"></i>Author</div>
              <div class="card-body">
                <div class="fw-semibold" id="appAuthor">-</div>
                <div class="mt-2">
                  <small class="text-muted d-block">Email</small>
                  <div id="appEmail">-</div>
                </div>
                <div class="mt-2">
                  <small class="text-muted d-block">Contact</small>
                  <div id="appContact">-</div>
                </div>
                <hr>
                <div class="text-muted small">Submitted</div>
                <div class="fw-semibold" id="appSubmitted">-</div>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
  document.addEventListener('DOMContentLoaded', function () {
    const shouldShowCreateModal = <?= $showCreateModal ? 'true' : 'false' ?>;
    const wasCreated = <?= isset($_GET['created']) && $_GET['created'] === '1' ? 'true' : 'false' ?>;

    const authorsList = <?= json_encode(array_map(function ($a) {
        $label = trim((string)$a['last_name'] . ', ' . (string)$a['first_name']);
        $email = (string)($a['email'] ?? '');
        $full = $label;
        if ($email !== '') {
          $full = $label . ' — ' . $email;
        }
        return [
          'id' => (int)$a['id'],
          'label' => $label,
          'email' => $email,
          'full' => $full,
        ];
      }, $authors), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

    if (wasCreated && window.Swal) {
      Swal.fire({ icon: 'success', title: 'Created', text: 'Application has been created.' });
    }

    const createAuthorQuery = document.getElementById('createAuthorQuery');
    const createAuthorId = document.getElementById('createAuthorId');
    const createAuthorStatus = document.getElementById('createAuthorStatus');
    const createAuthorHint = document.getElementById('createAuthorHint');
    const createAuthorClearBtn = document.getElementById('createAuthorClearBtn');

    const authorLookup = (function () {
      const map = {};
      for (let i = 0; i < authorsList.length; i++) {
        const a = authorsList[i];
        if (a.full) map[a.full.toLowerCase()] = a.id;
        if (a.email) map[a.email.toLowerCase()] = a.id;
        if (a.label) map[a.label.toLowerCase()] = a.id;
      }
      return map;
    })();

    function setAuthorStatus(isSelected) {
      if (!createAuthorStatus) return;
      if (isSelected) {
        createAuthorStatus.className = 'badge text-bg-success';
        createAuthorStatus.textContent = 'Selected';
        if (createAuthorHint) createAuthorHint.textContent = 'Author matched and ready.';
      } else {
        createAuthorStatus.className = 'badge text-bg-light border';
        createAuthorStatus.textContent = 'Not selected';
        if (createAuthorHint) createAuthorHint.textContent = 'Pick from suggestions to auto-fill the author.';
      }
    }

    function syncAuthorIdFromQuery() {
      if (!createAuthorQuery || !createAuthorId) return;
      const key = (createAuthorQuery.value || '').trim().toLowerCase();
      const id = authorLookup[key] || '';
      createAuthorId.value = String(id);
      setAuthorStatus(!!id);
    }

    if (createAuthorQuery) {
      createAuthorQuery.addEventListener('input', syncAuthorIdFromQuery);
      createAuthorQuery.addEventListener('change', syncAuthorIdFromQuery);
      syncAuthorIdFromQuery();
    }
    if (createAuthorClearBtn) {
      createAuthorClearBtn.addEventListener('click', function () {
        if (createAuthorQuery) createAuthorQuery.value = '';
        if (createAuthorId) createAuthorId.value = '';
        setAuthorStatus(false);
        if (createAuthorQuery) createAuthorQuery.focus();
      });
    }

    function toggleCreateFormerBlock() {
      const yes = document.getElementById('createFormerYes');
      const block = document.getElementById('createFormerBlock');
      if (!block) return;
      const show = !!(yes && yes.checked);
      block.classList.toggle('d-none', !show);
    }

    document.querySelectorAll('input[name="formerly_published"]').forEach((el) => {
      el.addEventListener('change', toggleCreateFormerBlock);
    });
    toggleCreateFormerBlock();

    function formatWholePrice(value) {
      const v = (value ?? '').toString().trim();
      if (v === '') return '';
      const num = Number(v);
      if (!Number.isFinite(num)) return v;
      return String(Math.trunc(num));
    }

    const createPriceInput = document.querySelector('input[name="suggested_retail_price"]');
    if (createPriceInput) {
      createPriceInput.addEventListener('input', function () {
        const fixed = formatWholePrice(createPriceInput.value);
        if (createPriceInput.value !== fixed) {
          createPriceInput.value = fixed;
        }
      });
    }

    if (shouldShowCreateModal && window.bootstrap && bootstrap.Modal) {
      const el = document.getElementById('createApplicationModal');
      if (el) {
        bootstrap.Modal.getOrCreateInstance(el).show();
      }
    }

    if (window.jQuery && jQuery.fn && jQuery.fn.DataTable) {
      jQuery('#applicationsTable').DataTable({
        order: [[0, 'desc']],
        pageLength: 10,
        columnDefs: [
          { orderable: false, targets: -1 }
        ],
        language: {
          search: 'Search applications:',
          lengthMenu: 'Show _MENU_ applications per page',
          info: 'Showing _START_ to _END_ of _TOTAL_ applications',
          infoEmpty: 'No applications available',
          infoFiltered: '(filtered from _MAX_ total applications)',
          emptyTable: 'No submissions found.'
        }
      });
    }

    let activeTriggerBtn = null;

    document.querySelectorAll('.view-application-btn').forEach((btn) => {
      btn.addEventListener('click', function () {
        activeTriggerBtn = btn;
        const status = btn.dataset.status || '-';
        const badgeMap = { Approved: 'text-bg-success', Rejected: 'text-bg-danger', Pending: 'text-bg-warning' };
        const badgeClass = badgeMap[status] || 'text-bg-secondary';

        document.getElementById('appTitle').textContent = btn.dataset.title || '-';
        document.getElementById('appSubmitted').textContent = btn.dataset.submitted || '-';
        document.getElementById('appStarting').textContent = btn.dataset.starting || '-';
        document.getElementById('appFrequency').textContent = btn.dataset.frequency || '-';
        document.getElementById('appLanguage').textContent = btn.dataset.language || '-';
        document.getElementById('appPrice').textContent = formatWholePrice(btn.dataset.price) || '-';

        const statusBadge = document.getElementById('appStatusBadge');
        statusBadge.className = `badge ${badgeClass}`;
        statusBadge.textContent = status;

        const formatsWrap = document.getElementById('appFormats');
        formatsWrap.innerHTML = '';
        const formats = (btn.dataset.formats || '').split('||').filter(Boolean);
        if (formats.length) {
          formats.forEach((name) => {
            const badge = document.createElement('span');
            badge.className = 'badge text-bg-secondary me-1';
            badge.textContent = name;
            formatsWrap.appendChild(badge);
          });
        } else {
          formatsWrap.textContent = '-';
        }

        const isFormer = btn.dataset.formerly === '1';
        document.getElementById('appFormerFlag').textContent = isFormer ? 'Yes' : 'No';
        document.getElementById('appFormerBlock').classList.toggle('d-none', !isFormer);
        document.getElementById('appFormerFormBlock').classList.toggle('d-none', !isFormer);
        document.getElementById('appFormerStartBlock').classList.toggle('d-none', !isFormer);
        document.getElementById('appFormerEndBlock').classList.toggle('d-none', !isFormer);
        document.getElementById('appFormerTitle').textContent = btn.dataset.formerTitle || '-';
        document.getElementById('appFormerForm').textContent = btn.dataset.formerForm || '-';
        document.getElementById('appFormerStart').textContent = btn.dataset.formerStart || '-';
        document.getElementById('appFormerEnd').textContent = btn.dataset.formerEnd || '-';

        const pdfLink = document.getElementById('appPdfLink');
        pdfLink.href = `/uccrdc/files/submission_pdf.php?id=${btn.dataset.id}`;

        const manuscriptLink = document.getElementById('appManuscriptLink');
        manuscriptLink.href = `/uccrdc/files/submission_manuscript.php?id=${btn.dataset.id}`;

        const editorialLink = document.getElementById('appEditorialLink');
        editorialLink.href = `/uccrdc/files/submission_editorial.php?id=${btn.dataset.id}`;
        const submissionIdInput = document.getElementById('appSubmissionId');
        if (submissionIdInput) submissionIdInput.value = btn.dataset.id || '';

        const staffEl = document.getElementById('appStaff');
        if (staffEl) staffEl.textContent = btn.dataset.staff || '—';
        const comment = btn.dataset.comment || '';
        const commentEl = document.getElementById('appComment');
        if (commentEl) commentEl.textContent = comment === '' ? '—' : comment;
        const commentInputEl = document.getElementById('appCommentInput');
        if (commentInputEl) commentInputEl.value = comment;

        const authorEl = document.getElementById('appAuthor');
        if (authorEl) authorEl.textContent = btn.dataset.author || '-';
        const emailEl = document.getElementById('appEmail');
        if (emailEl) emailEl.textContent = btn.dataset.email || '-';
        const contactEl = document.getElementById('appContact');
        if (contactEl) contactEl.textContent = btn.dataset.contact || '-';

        const approveBtn = document.getElementById('appApproveBtn');
        const rejectBtn = document.getElementById('appRejectBtn');
        const actionForm = document.getElementById('appActionForm');
        const actionFooter = actionForm ? actionForm.closest('.card-footer') : null;

        if (approveBtn) approveBtn.disabled = status === 'Approved';
        if (rejectBtn) rejectBtn.disabled = status === 'Rejected';

        const hideActions = status === 'Approved' || status === 'Rejected';
        if (actionForm) actionForm.classList.toggle('d-none', hideActions);
        if (actionFooter) actionFooter.classList.toggle('d-none', hideActions);
      });
    });

    const approveBtn = document.getElementById('appApproveBtn');
    const rejectBtn = document.getElementById('appRejectBtn');

    function updateRowStatus(id, newStatus) {
      const row = document.querySelector(`tr[data-submission-id="${id}"]`);
      if (!row) return;
      const badgeEl = row.querySelector('td:nth-child(3) .badge');
      if (!badgeEl) return;

      const badgeMap = { Approved: 'text-bg-success', Rejected: 'text-bg-danger', Pending: 'text-bg-warning' };
      const badgeClass = badgeMap[newStatus] || 'text-bg-secondary';
      badgeEl.className = `badge ${badgeClass}`;
      badgeEl.textContent = newStatus;
    }

    async function performAction(isApprove) {
      const actionLabel = isApprove ? 'approve' : 'reject';
      const desiredStatus = isApprove ? 'Approved' : 'Rejected';
      const confirmedIcon = isApprove ? 'success' : 'info';

      if (!activeTriggerBtn) {
        return;
      }

      const id = activeTriggerBtn.dataset.id;
      const csrfEl = document.getElementById('appCsrf');
      const commentEl = document.getElementById('appCommentInput');
      const csrf = csrfEl ? (csrfEl.value || '') : '';
      const comment = commentEl ? (commentEl.value || '') : '';

      if (!id) {
        if (window.Swal) {
          await Swal.fire({ icon: 'error', title: 'Missing ID', text: 'Unable to determine submission id.' });
        }
        return;
      }

      const confirmProceed = async () => {
        if (!window.Swal) {
          return confirm(`Are you sure you want to ${actionLabel} this submission?`);
        }
        const result = await Swal.fire({
          icon: 'warning',
          title: 'Please confirm',
          text: `Are you sure you want to ${actionLabel} this submission?`,
          showCancelButton: true,
          confirmButtonText: `Yes, ${actionLabel}`,
          cancelButtonText: 'Cancel'
        });
        return !!result.isConfirmed;
      };

      const ok = await confirmProceed();
      if (!ok) return;

      const approveBtnEl = document.getElementById('appApproveBtn');
      const rejectBtnEl = document.getElementById('appRejectBtn');
      if (approveBtnEl) approveBtnEl.disabled = true;
      if (rejectBtnEl) rejectBtnEl.disabled = true;

      try {
        const body = new URLSearchParams();
        body.set('_csrf', csrf);
        body.set('id', id);
        body.set('action', actionLabel);
        body.set('admin_comment', comment);
        body.set('ajax', '1');

        const res = await fetch('/uccrdc/admin/submission_action.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
            'Accept': 'application/json'
          },
          body
        });

        const data = await res.json().catch(() => null);
        if (!res.ok || !data || !data.ok) {
          const message = data?.message || 'Failed to update submission.';
          if (window.Swal) {
            await Swal.fire({ icon: 'error', title: 'Error', text: message });
          } else {
            alert(message);
          }
          return;
        }

        // Update local UI + datasets
        activeTriggerBtn.dataset.status = desiredStatus;
        activeTriggerBtn.dataset.comment = data.admin_comment || '';
        updateRowStatus(id, desiredStatus);

        const statusBadge = document.getElementById('appStatusBadge');
        if (statusBadge) {
          const badgeMap = { Approved: 'text-bg-success', Rejected: 'text-bg-danger', Pending: 'text-bg-warning' };
          const badgeClass = badgeMap[desiredStatus] || 'text-bg-secondary';
          statusBadge.className = `badge ${badgeClass}`;
          statusBadge.textContent = desiredStatus;
        }

        // Hide actions after final decision
        const actionForm = document.getElementById('appActionForm');
        const actionFooter = actionForm ? actionForm.closest('.card-footer') : null;
        if (actionForm) actionForm.classList.add('d-none');
        if (actionFooter) actionFooter.classList.add('d-none');

        if (window.Swal) {
          await Swal.fire({ icon: confirmedIcon, title: desiredStatus, text: 'Submission status updated.' });
        }
      } finally {
        // If it succeeded we hid the form anyway; if it failed, re-enable.
        const currentStatus = (activeTriggerBtn && activeTriggerBtn.dataset && activeTriggerBtn.dataset.status) ? activeTriggerBtn.dataset.status : '';
        const isFinal = currentStatus === 'Approved' || currentStatus === 'Rejected';
        if (!isFinal) {
          if (approveBtnEl) approveBtnEl.disabled = false;
          if (rejectBtnEl) rejectBtnEl.disabled = false;
        }
      }
    }

    if (approveBtn) {
      approveBtn.addEventListener('click', function () {
        performAction(true);
      });
    }
    if (rejectBtn) {
      rejectBtn.addEventListener('click', function () {
        performAction(false);
      });
    }
  });
</script>

<?php require_once __DIR__ . '/../includes/dashboard_footer.php'; ?>
