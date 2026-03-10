<?php
require_once __DIR__ . '/../app/auth.php';
require_login();
require_role(['Staff']);

$pageTitle = 'Staff Dashboard';
require_once __DIR__ . '/../includes/dashboard_header.php';

$user = current_user();

$status = sanitize_string($_GET['status'] ?? '');
$allowed = ['Pending','Approved','Rejected'];

$countsStmt = db()->prepare('SELECT
    COUNT(*) AS total,
    SUM(CASE WHEN status = "Pending" THEN 1 ELSE 0 END) AS pending,
    SUM(CASE WHEN status = "Approved" THEN 1 ELSE 0 END) AS approved,
    SUM(CASE WHEN status = "Rejected" THEN 1 ELSE 0 END) AS rejected
  FROM submissions');
$countsStmt->execute();
$counts = $countsStmt->fetch() ?: ['total' => 0, 'pending' => 0, 'approved' => 0, 'rejected' => 0];

$sql = 'SELECT s.id, s.title, s.status, s.created_at, a.email, a.first_name, a.last_name
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
?>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">

<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h2 class="mb-1 fw-bold text-success">Welcome, <?= htmlspecialchars(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''))) ?>!</h2>
      <p class="text-muted mb-0">Staff Dashboard</p>
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
      <h5 class="mb-0"><i class="fa-solid fa-folder-open me-2"></i>Submissions</h5>
    </div>
    <div class="card-body">
      <div class="table-responsive">
        <table id="staffSubmissionsTable" class="table table-hover mb-0">
          <thead class="table-success">
            <tr>
              <th>Title</th>
              <th>Author</th>
              <th style="width:140px">Status</th>
              <th style="width:180px">Submitted</th>
            </tr>
          </thead>
          <tbody>
          <?php if (!$rows): ?>
            <tr><td colspan="5" class="text-center text-muted py-5">No submissions found.</td></tr>
          <?php else: ?>
            <?php foreach ($rows as $r): ?>
              <tr>
                <td class="fw-semibold"><?= htmlspecialchars($r['title']) ?></td>
                <td class="text-muted small"><?= htmlspecialchars($r['first_name'] . ' ' . $r['last_name']) ?><br><?= htmlspecialchars($r['email']) ?></td>
                <td>
                  <?php $badge = match ($r['status']) { 'Approved'=>'success','Rejected'=>'danger', default=>'warning' }; ?>
                  <span class="badge text-bg-<?= $badge ?>"><?= htmlspecialchars($r['status']) ?></span>
                </td>
                <td class="text-muted small"><?= htmlspecialchars($r['created_at']) ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
          </tbody>
        </table>
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
      jQuery('#staffSubmissionsTable').DataTable({
        order: [[3, 'desc']],
        pageLength: 10,
        language: {
          search: 'Search submissions:',
          lengthMenu: 'Show _MENU_ submissions per page',
          info: 'Showing _START_ to _END_ of _TOTAL_ submissions',
          emptyTable: 'No submissions found.'
        }
      });
    }
  });
</script>

<?php require_once __DIR__ . '/../includes/dashboard_footer.php'; ?>
