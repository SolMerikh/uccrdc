<?php
require_once __DIR__ . '/../app/auth.php';
require_login();
require_role(['Staff']);

$pageTitle = 'Staff Review';
require_once __DIR__ . '/../includes/dashboard_header.php';

$id = (int)($_GET['id'] ?? 0);

$stmt = db()->prepare('SELECT s.*, a.first_name, a.last_name, a.email, a.contact_number
                       FROM submissions s
                       JOIN authors a ON a.id = s.author_id
                       WHERE s.id = ?');
$stmt->execute([$id]);
$s = $stmt->fetch();

if (!$s) {
    http_response_code(404);
    exit('Submission not found.');
}

$stmt = db()->prepare('SELECT pf.name FROM submission_formats sf JOIN publication_formats pf ON pf.id = sf.format_id WHERE sf.submission_id = ?');
$stmt->execute([$id]);
$formats = array_map(fn($r) => $r['name'], $stmt->fetchAll());

$badge = match ($s['status']) { 'Approved'=>'success','Rejected'=>'danger', default=>'warning' };
?>

<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h1 class="h4 mb-0">Staff Review</h1>
      <div class="text-muted small">Reference ID: #<?= (int)$s['id'] ?></div>
    </div>
    <a class="btn btn-outline-secondary" href="/uccrdc/staff/dashboard.php"><i class="fa-solid fa-arrow-left"></i> Back</a>
  </div>

  <div class="card">
    <div class="card-body">
      <div class="d-flex justify-content-between align-items-start">
        <div>
          <div class="text-muted small">Title</div>
          <div class="h5 mb-2"><?= htmlspecialchars($s['title']) ?></div>
        </div>
        <div class="text-end">
          <div class="text-muted small">Status</div>
          <span class="badge text-bg-<?= $badge ?>"><?= htmlspecialchars($s['status']) ?></span>
        </div>
      </div>

      <div class="row g-3 mt-1">
        <div class="col-md-4">
          <div class="text-muted small">Author</div>
          <div class="fw-semibold"><?= htmlspecialchars($s['first_name'] . ' ' . $s['last_name']) ?></div>
          <div class="text-muted small"><?= htmlspecialchars($s['email']) ?></div>
        </div>
        <div class="col-md-8">
          <div class="text-muted small">Formats</div>
          <div>
            <?php foreach ($formats as $name): ?>
              <span class="badge text-bg-secondary me-1"><?= htmlspecialchars($name) ?></span>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="col-12">
          <div class="text-muted small">PDF</div>
          <a href="/uccrdc/files/submission_pdf.php?id=<?= (int)$s['id'] ?>" target="_blank" class="btn btn-outline-secondary btn-sm">
            <i class="fa-solid fa-file-pdf text-danger"></i> View PDF
          </a>
        </div>
      </div>

      <hr>

      <form method="post" action="/uccrdc/staff/recommend.php">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
        <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
        <label class="form-label fw-semibold">Recommendation (optional)</label>
        <textarea class="form-control" name="staff_recommendation" rows="4" placeholder="Add review notes or recommendation..."><?= htmlspecialchars($s['staff_recommendation'] ?? '') ?></textarea>
        <div class="d-flex justify-content-end mt-3">
          <button class="btn btn-primary" type="submit"><i class="fa-solid fa-floppy-disk"></i> Save Recommendation</button>
        </div>
      </form>

    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/dashboard_footer.php'; ?>
