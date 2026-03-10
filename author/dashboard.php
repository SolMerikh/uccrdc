<?php
require_once __DIR__ . '/../app/auth.php';
require_login();
require_role(['Author']);

$pageTitle = 'Author Dashboard';
require_once __DIR__ . '/../includes/dashboard_header.php';

$user = current_user();

$formats = db()->query('SELECT id, name FROM publication_formats WHERE is_active = 1 ORDER BY name')->fetchAll();

$countsStmt = db()->prepare('SELECT
    COUNT(*) AS total,
    SUM(CASE WHEN status = "Pending" THEN 1 ELSE 0 END) AS pending,
    SUM(CASE WHEN status = "Approved" THEN 1 ELSE 0 END) AS approved,
    SUM(CASE WHEN status = "Rejected" THEN 1 ELSE 0 END) AS rejected
  FROM submissions
  WHERE author_id = ?');
$countsStmt->execute([$user['id']]);
$counts = $countsStmt->fetch() ?: ['total' => 0, 'pending' => 0, 'approved' => 0, 'rejected' => 0];

$status = sanitize_string($_GET['status'] ?? '');
$allowed = ['Pending','Approved','Rejected'];

$sql = 'SELECT id, title, status, created_at FROM submissions WHERE author_id = ?';
$params = [$user['id']];
if (in_array($status, $allowed, true)) {
  $sql .= ' AND status = ?';
  $params[] = $status;
} else {
  $status = '';
}
$sql .= ' ORDER BY created_at DESC';

$stmt = db()->prepare($sql);
$stmt->execute($params);
$submissions = $stmt->fetchAll();
?>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">

<div class="container-fluid p-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h2 class="mb-1 fw-bold text-success">Welcome, <?= htmlspecialchars(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''))) ?>!</h2>
      <p class="text-muted mb-0">Author Dashboard</p>
    </div>
    <div class="text-muted">
      <i class="fas fa-calendar-alt me-2"></i><?= date('F d, Y') ?>
    </div>
  </div>

  <div class="row g-3 mb-4">
    <div class="col-md-3">
      <div class="card stat-card stat-total border-0">
        <div class="card-body">
          <div class="stat-icon"><i class="fa-solid fa-layer-group"></i></div>
          <div class="text-muted small">Total Applications</div>
          <div class="h3 mb-0"><?= (int)$counts['total'] ?></div>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card stat-card stat-pending border-0">
        <div class="card-body">
          <div class="stat-icon"><i class="fa-solid fa-hourglass-half"></i></div>
          <div class="text-muted small">Pending</div>
          <div class="h3 mb-0"><?= (int)$counts['pending'] ?></div>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card stat-card stat-approved border-0">
        <div class="card-body">
          <div class="stat-icon"><i class="fa-solid fa-circle-check"></i></div>
          <div class="text-muted small">Approved</div>
          <div class="h3 mb-0"><?= (int)$counts['approved'] ?></div>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card stat-card stat-rejected border-0">
        <div class="card-body">
          <div class="stat-icon"><i class="fa-solid fa-circle-xmark"></i></div>
          <div class="text-muted small">Rejected</div>
          <div class="h3 mb-0"><?= (int)$counts['rejected'] ?></div>
        </div>
      </div>
    </div>
  </div>

  <div class="card shadow-sm" id="submissions">
    <div class="card-header bg-success text-white">
      <h5 class="mb-0"><i class="fa-solid fa-inbox me-2"></i>My Submissions</h5>
    </div>
    <div class="card-body">
      <div class="table-responsive">
        <table id="authorSubmissionsTable" class="table table-hover mb-0">
          <thead class="table-success">
            <tr>
              <th>Title</th>
              <th style="width:140px">Status</th>
              <th style="width:180px">Submitted</th>
            </tr>
          </thead>
          <tbody>
          <?php if ($submissions): ?>
            <?php foreach ($submissions as $s): ?>
              <tr>
                <td class="fw-semibold"><?= htmlspecialchars($s['title']) ?></td>
                <td>
                  <?php
                    $badge = match ($s['status']) {
                      'Approved' => 'success',
                      'Rejected' => 'danger',
                      default => 'warning',
                    };
                  ?>
                  <span class="badge text-bg-<?= $badge ?>"><?= htmlspecialchars($s['status']) ?></span>
                </td>
                <td class="text-muted small"><?= htmlspecialchars($s['created_at']) ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <!-- New Submission Modal -->
  <div class="modal fade" id="newSubmissionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-fullscreen-sm-down">
      <div class="modal-content">
        <div class="modal-header bg-success text-white">
          <h5 class="modal-title"><i class="fa-solid fa-file-circle-plus me-2"></i>Submit New Publication</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form method="post" action="/uccrdc/author/submit.php" enctype="multipart/form-data">
          <div class="modal-body">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrf_token()) ?>">

            <div class="row g-3">
              <div class="col-12">
                <label class="form-label">Title of Publication</label>
                <input class="form-control" name="title" required>
              </div>
              <div class="col-md-4">
                <label class="form-label">Starting Date</label>
                <input class="form-control" type="date" name="starting_date" required>
              </div>
              <div class="col-md-4">
                <label class="form-label">Frequency</label>
                <input class="form-control" name="frequency" required placeholder="e.g., Monthly">
              </div>
              <div class="col-md-4">
                <label class="form-label">Language</label>
                <input class="form-control" name="language" required placeholder="e.g., English">
              </div>
              <div class="col-md-4">
                <label class="form-label">Suggested Retail Price</label>
                <input class="form-control" type="number" name="suggested_retail_price" step="1" min="0" placeholder="e.g., 199">
              </div>

              <div class="col-12">
                <div class="border rounded-3 p-3 bg-light">
                  <div class="fw-semibold mb-2">Former Publication</div>
                  <div class="text-muted small mb-2">Was this publication formerly published under a different title?</div>
                  <div class="d-flex gap-4">
                    <div class="form-check">
                      <input class="form-check-input" type="radio" name="formerly_published" id="modal_formerly_no" value="0" checked>
                      <label class="form-check-label" for="modal_formerly_no">No</label>
                    </div>
                    <div class="form-check">
                      <input class="form-check-input" type="radio" name="formerly_published" id="modal_formerly_yes" value="1">
                      <label class="form-check-label" for="modal_formerly_yes">Yes</label>
                    </div>
                  </div>

                  <div id="modalFormerPublicationSection" class="row g-3 mt-2 d-none">
                    <div class="col-12">
                      <label class="form-label">Former Title</label>
                      <input class="form-control" name="former_title" disabled>
                    </div>
                    <div class="col-md-6">
                      <label class="form-label">Form of Publication (text)</label>
                      <input class="form-control" name="former_form_of_publication" disabled placeholder="e.g., Print">
                    </div>
                    <div class="col-md-3">
                      <label class="form-label">Starting Date</label>
                      <input class="form-control" type="date" name="former_starting_date" disabled>
                    </div>
                    <div class="col-md-3">
                      <label class="form-label">Ending Date</label>
                      <input class="form-control" type="date" name="former_ending_date" disabled>
                    </div>
                  </div>
                </div>
              </div>

              <div class="col-12">
                <div class="border rounded-3 p-3 bg-light">
                  <div class="fw-semibold mb-2">Form of Publication</div>
                  <div class="text-muted small mb-2">Select at least one format.</div>
                  <div class="row">
                    <?php foreach ($formats as $f): ?>
                      <div class="col-md-4">
                        <div class="form-check">
                          <input class="form-check-input" type="checkbox" name="format_ids[]" id="modal_fmt<?= (int)$f['id'] ?>" value="<?= (int)$f['id'] ?>">
                          <label class="form-check-label" for="modal_fmt<?= (int)$f['id'] ?>"><?= htmlspecialchars($f['name']) ?></label>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  </div>
                </div>
              </div>

              <div class="col-12">
                <label class="form-label">PDF File of Publication</label>
                <input class="form-control" type="file" name="publication_pdf" accept="application/pdf,.pdf" required>
                <div class="form-text">PDF only. Max 20MB.</div>
              </div>
            </div>
          </div>
          <div class="modal-footer bg-white d-flex justify-content-end gap-2">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button class="btn btn-success" type="submit"><i class="fa-solid fa-paper-plane me-2"></i>Submit</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
<script>
  document.addEventListener('DOMContentLoaded', function () {
    if (window.jQuery && jQuery.fn?.DataTable) {
      jQuery('#authorSubmissionsTable').DataTable({
        order: [[2, 'desc']],
        pageLength: 10,
        language: {
          search: 'Search submissions:',
          lengthMenu: 'Show _MENU_ submissions per page',
          info: 'Showing _START_ to _END_ of _TOTAL_ submissions',
          emptyTable: 'No submissions found.'
        }
      });
    }

    const modal = document.getElementById('newSubmissionModal');
    const yes = document.getElementById('modal_formerly_yes');
    const no = document.getElementById('modal_formerly_no');
    const section = document.getElementById('modalFormerPublicationSection');

    function toggleFormerFields(isYes) {
      if (!section) return;
      section.classList.toggle('d-none', !isYes);
      section.querySelectorAll('input').forEach((input) => {
        input.disabled = !isYes;
      });
    }

    if (yes && no) {
      yes.addEventListener('change', () => toggleFormerFields(true));
      no.addEventListener('change', () => toggleFormerFields(false));
      toggleFormerFields(false);
    }

    if (modal && new URLSearchParams(window.location.search).get('new') === '1') {
      const bsModal = bootstrap.Modal.getOrCreateInstance(modal);
      bsModal.show();
    }
  });
</script>

<?php require_once __DIR__ . '/../includes/dashboard_footer.php'; ?>
