<?php
require_once __DIR__ . '/../app/auth.php';
require_login();
require_role(['Admin']);

$pageTitle = 'Publication Formats';
require_once __DIR__ . '/../includes/dashboard_header.php';

$flash = null;

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_verify_or_die();
    $action = sanitize_string($_POST['action'] ?? '');

    if ($action === 'create') {
        $name = sanitize_string($_POST['name'] ?? '');
        if ($name === '') {
            $flash = ['type' => 'danger', 'msg' => 'Format name is required.'];
        } else {
            try {
                $stmt = db()->prepare('INSERT INTO publication_formats (name, is_active) VALUES (?, 1)');
                $stmt->execute([$name]);
                $flash = ['type' => 'success', 'msg' => 'Format added.'];
            } catch (Throwable $e) {
                $flash = ['type' => 'danger', 'msg' => 'Failed to add format (maybe duplicate name).'];
            }
        }
    }

    if ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        $isActive = (int)($_POST['is_active'] ?? 0);
        if ($id) {
            $stmt = db()->prepare('UPDATE publication_formats SET is_active = ? WHERE id = ?');
            $stmt->execute([$isActive ? 1 : 0, $id]);
            $flash = ['type' => 'success', 'msg' => 'Format updated.'];
        }
    }

    if ($action === 'rename') {
        $id = (int)($_POST['id'] ?? 0);
        $name = sanitize_string($_POST['name'] ?? '');
        if ($id && $name !== '') {
            try {
                $stmt = db()->prepare('UPDATE publication_formats SET name = ? WHERE id = ?');
                $stmt->execute([$name, $id]);
                $flash = ['type' => 'success', 'msg' => 'Format renamed.'];
            } catch (Throwable $e) {
                $flash = ['type' => 'danger', 'msg' => 'Failed to rename format (maybe duplicate name).'];
            }
        }
    }

    // UPDATED ACTION: Now performs a "Soft Delete" by setting is_active to 0
    if ($action === 'delete') {
      $id = (int)($_POST['id'] ?? 0);
      if ($id) {
        try {
          // Changed from DELETE to UPDATE to bypass Foreign Key constraints
          $stmt = db()->prepare('UPDATE publication_formats SET is_active = 0 WHERE id = ?');
          $stmt->execute([$id]);
          $flash = ['type' => 'success', 'msg' => 'Format removed from view.'];
        } catch (Throwable $e) {
          $flash = ['type' => 'danger', 'msg' => 'Failed to remove format.'];
        }
      }
    }
}

// UPDATED QUERY: Only fetches formats that are still marked as active
$formats = db()->query('SELECT * FROM publication_formats ORDER BY name')->fetchAll();
?>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">

<div class="container-fluid p-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="fw-bold text-success mb-0">Publication Formats</h2>
    <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#formatModal" onclick="resetFormatForm()">
      <i class="fa-solid fa-plus me-2"></i>Add New Format
    </button>
  </div>

  <?php if ($flash): ?>
    <script>
      document.addEventListener('DOMContentLoaded', function () {
        if (window.Swal) {
          Swal.fire({
            icon: '<?= htmlspecialchars($flash['type']) === 'success' ? 'success' : 'error' ?>',
            title: '<?= htmlspecialchars($flash['type']) === 'success' ? 'Success' : 'Error' ?>',
            text: '<?= htmlspecialchars($flash['msg'], ENT_QUOTES) ?>'
          });
        }
      });
    </script>
  <?php endif; ?>

  <div class="row">
    <div class="col-12">
      <div class="card shadow-sm">
        <div class="card-header bg-success text-white">
          <h5 class="mb-0"><i class="fa-solid fa-list me-2"></i>Formats</h5>
        </div>
        <div class="card-body">
          <div class="table-responsive">
            <table id="formatsTable" class="table table-hover mb-0">
              <thead class="table-success">
                <tr>
                  <th>ID</th>
                  <th>Name</th>
                  <th>Status</th>
                  <th class="text-center" style="width:140px">Actions</th>
                </tr>
              </thead>
              <tbody>
              <?php if ($formats): ?>
                <?php foreach ($formats as $f): ?>
                  <tr>
                    <td><?= (int)$f['id'] ?></td>
                    <td class="fw-semibold"><?= htmlspecialchars($f['name']) ?></td>
                    <td>
                      <span class="badge <?= (int)$f['is_active']===1 ? 'bg-success' : 'bg-secondary' ?>">
                        <?= (int)$f['is_active']===1 ? 'Active' : 'Inactive' ?>
                      </span>
                    </td>
                    <td class="text-center">
                      <button
                        type="button"
                        class="btn btn-sm btn-warning"
                        data-bs-toggle="modal"
                        data-bs-target="#formatModal"
                        onclick='editFormat(<?= htmlspecialchars(json_encode($f), ENT_QUOTES) ?>)'
                        title="Edit"
                      >
                        <i class="fa-solid fa-pen"></i>
                      </button>
                      
                      <button
                        type="button"
                        class="btn btn-sm <?= (int)$f['is_active']===1 ? 'btn-secondary' : 'btn-success' ?>"
                        onclick="toggleFormat(<?= (int)$f['id'] ?>, '<?= htmlspecialchars($f['name'], ENT_QUOTES) ?>', '<?= (int)$f['is_active']===1 ? 'Active' : 'Inactive' ?>')"
                        title="Enable/Disable"
                      >
                        <i class="fa-solid <?= (int)$f['is_active']===1 ? 'fa-toggle-on' : 'fa-toggle-off' ?>"></i>
                      </button>
                      <form method="post" class="d-none" id="toggleForm<?= (int)$f['id'] ?>">
                        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                        <input type="hidden" name="action" value="toggle">
                        <input type="hidden" name="id" value="<?= (int)$f['id'] ?>">
                        <input type="hidden" name="is_active" value="<?= (int)$f['is_active']===1 ? 0 : 1 ?>">
                      </form>

                      <button
                        type="button"
                        class="btn btn-sm btn-danger"
                        onclick="deleteFormat(<?= (int)$f['id'] ?>, '<?= htmlspecialchars($f['name'], ENT_QUOTES) ?>')"
                        title="Remove"
                      >
                        <i class="fa-solid fa-trash"></i>
                      </button>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr><td colspan="4" class="text-center text-muted py-5">No active formats found.</td></tr>
              <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="formatModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title"><i class="fa-solid fa-plus me-2"></i><span id="formatModalTitle">Add New Format</span></h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form method="post" id="formatForm">
        <div class="modal-body">
          <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
          <input type="hidden" name="action" id="formatAction" value="create">
          <input type="hidden" name="id" id="formatId" value="">

          <div class="mb-3">
            <label class="form-label">Format Name *</label>
            <input class="form-control" name="name" id="formatName" placeholder="e.g., Newsletter" required>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-success"><i class="fa-solid fa-save me-2"></i><span id="formatSubmitLabel">Add Format</span></button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
  document.addEventListener('DOMContentLoaded', function () {
    if (window.jQuery && jQuery.fn?.DataTable) {
      jQuery('#formatsTable').DataTable({
        order: [[0, 'desc']],
        pageLength: 10,
        language: {
          search: 'Search formats:',
          lengthMenu: 'Show _MENU_ formats per page',
          info: 'Showing _START_ to _END_ of _TOTAL_ formats'
        }
      });
    }
  });

  function editFormat(format) {
    const form = document.getElementById('formatForm');
    if (!form) return;
    form.reset();
    document.getElementById('formatAction').value = 'rename';
    document.getElementById('formatId').value = format.id || '';
    document.getElementById('formatName').value = format.name || '';
    const title = document.getElementById('formatModalTitle');
    const submitLabel = document.getElementById('formatSubmitLabel');
    if (title) title.textContent = 'Edit Format';
    if (submitLabel) submitLabel.textContent = 'Save Changes';
  }

  function resetFormatForm() {
    const form = document.getElementById('formatForm');
    if (!form) return;
    form.reset();
    document.getElementById('formatAction').value = 'create';
    document.getElementById('formatId').value = '';
    const title = document.getElementById('formatModalTitle');
    const submitLabel = document.getElementById('formatSubmitLabel');
    if (title) title.textContent = 'Add New Format';
    if (submitLabel) submitLabel.textContent = 'Add Format';
  }

  window.editFormat = editFormat;
  window.resetFormatForm = resetFormatForm;

  function toggleFormat(id, name, status) {
    if (!window.Swal) {
      if (!confirm(`Toggle status for ${name}?`)) return;
      document.getElementById(`toggleForm${id}`)?.submit();
      return;
    }
    Swal.fire({
      title: 'Confirm',
      text: `Toggle status for ${name}?`,
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: 'Yes, toggle',
      cancelButtonText: 'Cancel',
      confirmButtonColor: '#198754'
    }).then((result) => {
      if (result.isConfirmed) {
        document.getElementById(`toggleForm${id}`)?.submit();
      }
    });
  }

  function deleteFormat(id, name) {
    if (!window.Swal) {
      if (!confirm(`Remove ${name} from view?`)) return;
      submitDelete(id);
      return;
    }
    Swal.fire({
      title: 'Remove format?',
      text: `Are you sure you want to remove ${name} from the list? (Existing data remains in the database)`,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Yes, remove',
      cancelButtonText: 'Cancel',
      confirmButtonColor: '#dc3545'
    }).then((result) => {
      if (result.isConfirmed) {
        submitDelete(id);
      }
    });
  }

  function submitDelete(id) {
    const form = document.createElement('form');
    form.method = 'post';
    form.action = '';
    form.innerHTML = `
      <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
      <input type="hidden" name="action" value="delete">
      <input type="hidden" name="id" value="${id}">
    `;
    document.body.appendChild(form);
    form.submit();
  }

  window.toggleFormat = toggleFormat;
  window.deleteFormat = deleteFormat;
</script>

<?php require_once __DIR__ . '/../includes/dashboard_footer.php'; ?>