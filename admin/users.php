<?php
require_once __DIR__ . '/../app/auth.php';
require_login();
require_role(['Admin']);

$pageTitle = 'User Accounts';
require_once __DIR__ . '/../includes/dashboard_header.php';

$flash = null;
$currentUser = current_user();
$currentUserId = (int)($currentUser['id'] ?? 0);

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
  csrf_verify_or_die();

  $action = sanitize_string($_POST['action'] ?? 'create');
  $id = (int)($_POST['user_id'] ?? 0);

  if ($action === 'toggle') {
    if ($id <= 0 || $id === $currentUserId) {
      $flash = ['type' => 'danger', 'msg' => 'Invalid action.'];
    } else {
      $stmt = db()->prepare('SELECT status FROM authors WHERE id = ? AND role IN ("Admin","Staff") LIMIT 1');
      $stmt->execute([$id]);
      $row = $stmt->fetch();
      if (!$row) {
        $flash = ['type' => 'danger', 'msg' => 'User not found.'];
      } else {
        $newStatus = ($row['status'] === 'Active') ? 'Inactive' : 'Active';
        $upd = db()->prepare('UPDATE authors SET status = ? WHERE id = ?');
        $upd->execute([$newStatus, $id]);
        $flash = ['type' => 'success', 'msg' => 'User status updated.'];
      }
    }
  } elseif ($action === 'delete') {
    if ($id <= 0 || $id === $currentUserId) {
      $flash = ['type' => 'danger', 'msg' => 'Invalid action.'];
    } else {
      $del = db()->prepare('DELETE FROM authors WHERE id = ? AND role IN ("Admin","Staff")');
      $del->execute([$id]);
      $flash = ['type' => 'success', 'msg' => 'User deleted.'];
    }
  } else {
    // create or update
    $role = sanitize_string($_POST['role'] ?? '');
    $last = sanitize_string($_POST['last_name'] ?? '');
    $first = sanitize_string($_POST['first_name'] ?? '');
    $email = sanitize_string($_POST['email'] ?? '');
    $contact = sanitize_string($_POST['contact_number'] ?? '');
    $address = sanitize_string($_POST['address'] ?? 'UCC');
    $postal = sanitize_string($_POST['postal_code'] ?? '0000');
    $region = sanitize_string($_POST['region'] ?? 'NCR');
    $password = (string)($_POST['password'] ?? '');

    $errors = [];
    if (!in_array($role, ['Admin','Staff'], true)) $errors[] = 'Invalid role.';
    if ($last === '' || $first === '') $errors[] = 'Name is required.';
    if ($email === '' || !validate_email($email)) $errors[] = 'Valid email is required.';
    if ($contact === '') $errors[] = 'Contact number is required.';

    $isUpdate = ($action === 'update' && $id > 0);
    if (!$isUpdate) {
      if ($password === '' || strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';
    } elseif ($password !== '' && strlen($password) < 8) {
      $errors[] = 'Password must be at least 8 characters.';
    }

    if (!$errors) {
      if ($isUpdate) {
        $stmt = db()->prepare('SELECT id FROM authors WHERE email = ? AND id != ? LIMIT 1');
        $stmt->execute([$email, $id]);
      } else {
        $stmt = db()->prepare('SELECT id FROM authors WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
      }
      if ($stmt->fetch()) {
        $errors[] = 'Email is already in use.';
      }
    }

    if ($errors) {
      $flash = ['type' => 'danger', 'msg' => implode(' ', $errors)];
    } else {
      try {
        if ($isUpdate) {
          $fields = ['last_name' => $last, 'first_name' => $first, 'email' => $email, 'contact_number' => $contact, 'address' => $address, 'postal_code' => $postal, 'region' => $region, 'role' => $role];
          $sql = 'UPDATE authors SET last_name = ?, first_name = ?, email = ?, contact_number = ?, address = ?, postal_code = ?, region = ?, role = ?';
          $params = array_values($fields);
          if ($password !== '') {
            $sql .= ', password_hash = ?';
            $params[] = password_hash($password, PASSWORD_DEFAULT);
          }
          $sql .= ' WHERE id = ? AND role IN ("Admin","Staff")';
          $params[] = $id;
          $upd = db()->prepare($sql);
          $upd->execute($params);
          $flash = ['type' => 'success', 'msg' => 'Account updated.'];
        } else {
          $hash = password_hash($password, PASSWORD_DEFAULT);
          $stmt = db()->prepare('INSERT INTO authors
            (last_name, first_name, email, contact_number, course, address, postal_code, region, valid_id_path, password_hash, status, role)
            VALUES
            (?, ?, ?, ?, NULL, ?, ?, ?, NULL, ?, "Active", ?)
          ');
          $stmt->execute([$last, $first, $email, $contact, $address, $postal, $region, $hash, $role]);
          $flash = ['type' => 'success', 'msg' => $role . ' account created.'];
        }
      } catch (Throwable $e) {
        $flash = ['type' => 'danger', 'msg' => 'Failed to save account.'];
      }
    }
  }
}

$accounts = db()->query("SELECT id, first_name, last_name, email, role, status, created_at, contact_number, address, postal_code, region FROM authors WHERE role IN ('Admin','Staff') ORDER BY created_at DESC")->fetchAll();
$authors = db()->query("SELECT id, first_name, last_name, email, status, created_at, contact_number, course, address, postal_code, region, valid_id_path FROM authors WHERE role = 'Author' ORDER BY created_at DESC")->fetchAll();
?>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">

<div class="container-fluid p-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="fw-bold text-success mb-0">User Management</h2>
    <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#userModal" onclick="resetUserForm()">
      <i class="fa-solid fa-plus me-2"></i>Add New User
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
          <h5 class="mb-0"><i class="fa-solid fa-users me-2"></i>System Users</h5>
        </div>
        <div class="card-body">
          <div class="table-responsive d-none d-md-block">
            <table id="usersTable" class="table table-hover mb-0">
              <thead class="table-success">
                <tr>
                  <th>ID</th>
                  <th>Full Name</th>
                  <th>Email</th>
                  <th>Role</th>
                  <th>Status</th>
                  <th class="text-center" style="width:140px">Actions</th>
                </tr>
              </thead>
              <tbody>
              <?php if ($accounts): ?>
                <?php foreach ($accounts as $a): ?>
                  <tr>
                    <td><?= (int)$a['id'] ?></td>
                    <td><?= htmlspecialchars($a['first_name'] . ' ' . $a['last_name']) ?></td>
                    <td><?= htmlspecialchars($a['email']) ?></td>
                    <td>
                      <span class="badge <?= $a['role']==='Admin' ? 'bg-danger' : 'bg-success' ?>">
                        <?= htmlspecialchars($a['role']) ?>
                      </span>
                    </td>
                    <td>
                      <span class="badge <?= $a['status']==='Active' ? 'bg-success' : 'bg-secondary' ?>">
                        <?= htmlspecialchars($a['status']) ?>
                      </span>
                    </td>
                    <td class="text-center">
                      <button
                        type="button"
                        class="btn btn-sm btn-warning"
                        data-bs-toggle="modal"
                        data-bs-target="#userModal"
                        onclick='editUser(<?= htmlspecialchars(json_encode($a), ENT_QUOTES) ?>)'
                        title="Edit"
                      >
                        <i class="fa-solid fa-pen"></i>
                      </button>
                      <?php $isSelf = ((int)$a['id'] === $currentUserId); ?>
                      <button
                        type="button"
                        class="btn btn-sm <?= $a['status']==='Active' ? 'btn-secondary' : 'btn-success' ?>"
                        onclick="toggleUserStatus(<?= (int)$a['id'] ?>, '<?= htmlspecialchars($a['first_name'] . ' ' . $a['last_name'], ENT_QUOTES) ?>', '<?= htmlspecialchars($a['status'], ENT_QUOTES) ?>')"
                        title="<?= $isSelf ? 'Cannot change your own status' : 'Enable/Disable' ?>"
                        <?= $isSelf ? 'disabled' : '' ?>
                      >
                        <i class="fa-solid <?= $a['status']==='Active' ? 'fa-toggle-on' : 'fa-toggle-off' ?>"></i>
                      </button>
                      <button
                        type="button"
                        class="btn btn-sm btn-danger"
                        onclick="deleteUser(<?= (int)$a['id'] ?>, '<?= htmlspecialchars($a['first_name'] . ' ' . $a['last_name'], ENT_QUOTES) ?>')"
                        title="<?= $isSelf ? 'Cannot delete your own account' : 'Delete' ?>"
                        <?= $isSelf ? 'disabled' : '' ?>
                      >
                        <i class="fa-solid fa-trash"></i>
                      </button>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr><td colspan="6" class="text-center text-muted py-5">No users found.</td></tr>
              <?php endif; ?>
              </tbody>
            </table>
          </div>

          <div class="d-block d-md-none mt-3">
            <?php if ($accounts): ?>
              <?php foreach ($accounts as $a): ?>
                <?php $collapseId = 'userMobile' . (int)$a['id']; ?>
                <div class="card mb-2 border-0 shadow-sm">
                  <button class="btn w-100 text-start p-3 d-flex align-items-center" type="button" data-bs-toggle="collapse" data-bs-target="#<?= $collapseId ?>" aria-expanded="false">
                    <div class="rounded-circle bg-success text-white d-flex align-items-center justify-content-center me-3" style="width: 32px; height: 32px;">
                      <i class="fa-solid fa-chevron-down"></i>
                    </div>
                    <div class="flex-grow-1">
                      <div class="fw-semibold"><?= htmlspecialchars($a['first_name'] . ' ' . $a['last_name']) ?></div>
                    </div>
                  </button>
                  <div id="<?= $collapseId ?>" class="collapse border-top px-3 pt-2 pb-3">
                    <div class="mb-2">
                      <small class="text-muted d-block">Email</small>
                      <span><?= htmlspecialchars($a['email']) ?></span>
                    </div>
                    <div class="mb-2">
                      <small class="text-muted d-block">Role</small>
                      <span><?= htmlspecialchars($a['role']) ?></span>
                    </div>
                    <div class="mb-2">
                      <small class="text-muted d-block">Status</small>
                      <span><?= htmlspecialchars($a['status']) ?></span>
                    </div>
                    <div class="mt-2 d-flex gap-2">
                      <button
                        type="button"
                        class="btn btn-sm btn-warning"
                        data-bs-toggle="modal"
                        data-bs-target="#userModal"
                        onclick='editUser(<?= htmlspecialchars(json_encode($a), ENT_QUOTES) ?>)'
                      >
                        <i class="fa-solid fa-pen"></i>
                      </button>
                      <?php $isSelf = ((int)$a['id'] === $currentUserId); ?>
                      <button
                        type="button"
                        class="btn btn-sm <?= $a['status']==='Active' ? 'btn-secondary' : 'btn-success' ?>"
                        onclick="toggleUserStatus(<?= (int)$a['id'] ?>, '<?= htmlspecialchars($a['first_name'] . ' ' . $a['last_name'], ENT_QUOTES) ?>', '<?= htmlspecialchars($a['status'], ENT_QUOTES) ?>')"
                        title="<?= $isSelf ? 'Cannot change your own status' : 'Enable/Disable' ?>"
                        <?= $isSelf ? 'disabled' : '' ?>
                      >
                        <i class="fa-solid <?= $a['status']==='Active' ? 'fa-toggle-on' : 'fa-toggle-off' ?>"></i>
                      </button>
                      <button
                        type="button"
                        class="btn btn-sm btn-danger"
                        onclick="deleteUser(<?= (int)$a['id'] ?>, '<?= htmlspecialchars($a['first_name'] . ' ' . $a['last_name'], ENT_QUOTES) ?>')"
                        title="<?= $isSelf ? 'Cannot delete your own account' : 'Delete' ?>"
                        <?= $isSelf ? 'disabled' : '' ?>
                      >
                        <i class="fa-solid fa-trash"></i>
                      </button>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php else: ?>
              <p class="text-muted mb-0">No users found.</p>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="row mt-4">
    <div class="col-12">
      <div class="card shadow-sm">
        <div class="card-header bg-success text-white">
          <h5 class="mb-0"><i class="fa-solid fa-user-graduate me-2"></i>Authors</h5>
        </div>
        <div class="card-body">
          <div class="table-responsive d-none d-md-block">
            <table id="authorsTable" class="table table-hover mb-0">
              <thead class="table-success">
                <tr>
                  <th>ID</th>
                  <th>Full Name</th>
                  <th>Email</th>
                  <th>Status</th>
                  <th>Created</th>
                  <th class="text-center" style="width:140px">Actions</th>
                </tr>
              </thead>
              <tbody>
              <?php if ($authors): ?>
                <?php foreach ($authors as $au): ?>
                  <tr>
                    <td><?= (int)$au['id'] ?></td>
                    <td class="fw-semibold"><?= htmlspecialchars($au['first_name'] . ' ' . $au['last_name']) ?></td>
                    <td><?= htmlspecialchars($au['email']) ?></td>
                    <td>
                      <span class="badge <?= ($au['status'] ?? '')==='Active' ? 'bg-success' : 'bg-secondary' ?>">
                        <?= htmlspecialchars($au['status'] ?? '-') ?>
                      </span>
                    </td>
                    <td class="text-muted small"><?= htmlspecialchars((string)($au['created_at'] ?? '')) ?></td>
                    <td class="text-center">
                      <button
                        type="button"
                        class="btn btn-sm btn-outline-secondary view-author-btn"
                        data-bs-toggle="modal"
                        data-bs-target="#authorViewModal"
                        data-id="<?= (int)$au['id'] ?>"
                        data-first="<?= htmlspecialchars((string)($au['first_name'] ?? ''), ENT_QUOTES) ?>"
                        data-last="<?= htmlspecialchars((string)($au['last_name'] ?? ''), ENT_QUOTES) ?>"
                        data-email="<?= htmlspecialchars((string)($au['email'] ?? ''), ENT_QUOTES) ?>"
                        data-contact="<?= htmlspecialchars((string)($au['contact_number'] ?? ''), ENT_QUOTES) ?>"
                        data-course="<?= htmlspecialchars((string)($au['course'] ?? ''), ENT_QUOTES) ?>"
                        data-address="<?= htmlspecialchars((string)($au['address'] ?? ''), ENT_QUOTES) ?>"
                        data-postal="<?= htmlspecialchars((string)($au['postal_code'] ?? ''), ENT_QUOTES) ?>"
                        data-region="<?= htmlspecialchars((string)($au['region'] ?? ''), ENT_QUOTES) ?>"
                        data-status="<?= htmlspecialchars((string)($au['status'] ?? ''), ENT_QUOTES) ?>"
                        data-created="<?= htmlspecialchars((string)($au['created_at'] ?? ''), ENT_QUOTES) ?>"
                        data-valid-id="<?= htmlspecialchars((string)($au['valid_id_path'] ?? ''), ENT_QUOTES) ?>"
                        title="View"
                      >
                        <i class="fa-solid fa-eye"></i> View
                      </button>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr><td colspan="6" class="text-center text-muted py-5">No authors found.</td></tr>
              <?php endif; ?>
              </tbody>
            </table>
          </div>

          <div class="d-block d-md-none mt-3">
            <?php if ($authors): ?>
              <?php foreach ($authors as $au): ?>
                <?php $collapseId = 'authorMobile' . (int)$au['id']; ?>
                <div class="card mb-2 border-0 shadow-sm">
                  <button class="btn w-100 text-start p-3 d-flex align-items-center" type="button" data-bs-toggle="collapse" data-bs-target="#<?= $collapseId ?>" aria-expanded="false">
                    <div class="rounded-circle bg-success text-white d-flex align-items-center justify-content-center me-3" style="width: 32px; height: 32px;">
                      <i class="fa-solid fa-chevron-down"></i>
                    </div>
                    <div class="flex-grow-1">
                      <div class="fw-semibold"><?= htmlspecialchars($au['first_name'] . ' ' . $au['last_name']) ?></div>
                      <div class="text-muted small"><?= htmlspecialchars($au['email']) ?></div>
                    </div>
                  </button>
                  <div id="<?= $collapseId ?>" class="collapse border-top px-3 pt-2 pb-3">
                    <div class="mb-2">
                      <small class="text-muted d-block">Status</small>
                      <span class="badge <?= ($au['status'] ?? '')==='Active' ? 'bg-success' : 'bg-secondary' ?>"><?= htmlspecialchars($au['status'] ?? '-') ?></span>
                    </div>
                    <div class="mb-2">
                      <small class="text-muted d-block">Created</small>
                      <span><?= htmlspecialchars((string)($au['created_at'] ?? '')) ?></span>
                    </div>
                    <div class="mt-2">
                      <button
                        type="button"
                        class="btn btn-sm btn-outline-secondary view-author-btn"
                        data-bs-toggle="modal"
                        data-bs-target="#authorViewModal"
                        data-id="<?= (int)$au['id'] ?>"
                        data-first="<?= htmlspecialchars((string)($au['first_name'] ?? ''), ENT_QUOTES) ?>"
                        data-last="<?= htmlspecialchars((string)($au['last_name'] ?? ''), ENT_QUOTES) ?>"
                        data-email="<?= htmlspecialchars((string)($au['email'] ?? ''), ENT_QUOTES) ?>"
                        data-contact="<?= htmlspecialchars((string)($au['contact_number'] ?? ''), ENT_QUOTES) ?>"
                        data-course="<?= htmlspecialchars((string)($au['course'] ?? ''), ENT_QUOTES) ?>"
                        data-address="<?= htmlspecialchars((string)($au['address'] ?? ''), ENT_QUOTES) ?>"
                        data-postal="<?= htmlspecialchars((string)($au['postal_code'] ?? ''), ENT_QUOTES) ?>"
                        data-region="<?= htmlspecialchars((string)($au['region'] ?? ''), ENT_QUOTES) ?>"
                        data-status="<?= htmlspecialchars((string)($au['status'] ?? ''), ENT_QUOTES) ?>"
                        data-created="<?= htmlspecialchars((string)($au['created_at'] ?? ''), ENT_QUOTES) ?>"
                        data-valid-id="<?= htmlspecialchars((string)($au['valid_id_path'] ?? ''), ENT_QUOTES) ?>"
                      >
                        <i class="fa-solid fa-eye"></i> View
                      </button>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php else: ?>
              <p class="text-muted mb-0">No authors found.</p>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Author View Modal -->
<div class="modal fade" id="authorViewModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-fullscreen-sm-down">
    <div class="modal-content">
      <div class="modal-header bg-success text-white">
        <div class="d-flex align-items-center gap-3">
          <div class="rounded-circle bg-white bg-opacity-25 d-flex align-items-center justify-content-center" style="width:42px;height:42px;">
            <i class="fa-solid fa-user"></i>
          </div>
          <div>
            <h5 class="modal-title mb-0">Author Information</h5>
            <div class="small opacity-75">View author profile and uploaded valid ID.</div>
          </div>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3 g-lg-4">
          <div class="col-12 col-lg-5">
            <div class="card shadow-sm h-100">
              <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <div class="fw-semibold"><i class="fa-solid fa-id-card me-2 text-success"></i>Profile</div>
                <span class="badge text-bg-secondary" id="authorStatusBadge">-</span>
              </div>
              <div class="card-body">
                <div class="mb-1">
                  <div class="text-muted small">Full Name</div>
                  <div class="h5 mb-1" id="authorFullName">-</div>
                </div>
                <div class="row g-3">
                  <div class="col-12">
                    <div class="text-muted small">Email</div>
                    <div class="fw-semibold text-break" id="authorEmail">-</div>
                  </div>
                  <div class="col-12">
                    <div class="text-muted small">Contact Number</div>
                    <div class="fw-semibold" id="authorContact">-</div>
                  </div>
                  <div class="col-12">
                    <div class="text-muted small">Course</div>
                    <div class="fw-semibold" id="authorCourse">-</div>
                  </div>
                  <div class="col-12">
                    <div class="text-muted small">Created</div>
                    <div class="fw-semibold" id="authorCreated">-</div>
                  </div>
                  <div class="col-12">
                    <div class="text-muted small">Address</div>
                    <div class="fw-semibold" id="authorAddress">-</div>
                  </div>
                  <div class="col-12">
                    <div class="text-muted small">Postal Code</div>
                    <div class="fw-semibold" id="authorPostal">-</div>
                  </div>
                  <div class="col-12">
                    <div class="text-muted small">Region</div>
                    <div class="fw-semibold" id="authorRegion">-</div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="col-12 col-lg-7">
            <div class="card shadow-sm h-100">
              <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <div class="fw-semibold"><i class="fa-solid fa-address-card me-2 text-success"></i>Uploaded Valid ID</div>
                <span class="text-muted small" id="authorValidIdText">—</span>
              </div>
              <div class="card-body">
                <div class="border rounded bg-light p-2 d-flex align-items-center justify-content-center" style="min-height: 520px;" id="authorValidIdPreview">
                  <div class="text-muted small">No preview loaded.</div>
                </div>
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

<!-- User Modal (Create) -->
<div class="modal fade" id="userModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title"><i class="fa-solid fa-user-plus me-2"></i>Add New User</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form method="post" id="userForm">
        <div class="modal-body">
          <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
          <input type="hidden" name="action" id="userAction" value="create">
          <input type="hidden" name="user_id" id="userId" value="">

          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Role *</label>
              <select class="form-select" name="role" required>
                <option value="Staff">Staff</option>
                <option value="Admin">Admin</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Contact Number *</label>
              <input class="form-control" name="contact_number" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Last Name *</label>
              <input class="form-control" name="last_name" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">First Name *</label>
              <input class="form-control" name="first_name" required>
            </div>
            <div class="col-12">
              <label class="form-label">Email *</label>
              <input class="form-control" type="email" name="email" required>
            </div>
            <div class="col-12">
              <label class="form-label">Municipality (optional)</label>
              <select class="form-control admin-municipality-select" name="address" placeholder="Select Municipality">
                <option value="">-- None --</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Postal Code</label>
              <input class="form-control admin-postal-code-input" name="postal_code" placeholder="0000" readonly>
            </div>
            <div class="col-md-6">
              <label class="form-label">Region (optional)</label>
              <select class="form-control admin-region-select" name="region">
                <option value="">-- Select Region --</option>
                <option value="NCR">NCR – National Capital Region</option>
                <option value="CAR">CAR – Cordillera Administrative Region</option>
                <option value="Region I">Region I – Ilocos Region</option>
                <option value="Region II">Region II – Cagayan Valley</option>
                <option value="Region III">Region III – Central Luzon</option>
                <option value="Region IV-A">Region IV-A – CALABARZON</option>
                <option value="Region IV-B">Region IV-B – MIMAROPA</option>
                <option value="Region V">Region V – Bicol Region</option>
                <option value="Region VI">Region VI – Western Visayas</option>
                <option value="Region VII">Region VII – Central Visayas</option>
                <option value="Region VIII">Region VIII – Eastern Visayas</option>
                <option value="Region IX">Region IX – Zamboanga Peninsula</option>
                <option value="Region X">Region X – Northern Mindanao</option>
                <option value="Region XI">Region XI – Davao Region</option>
                <option value="Region XII">Region XII – SOCCSKSARGEN</option>
                <option value="Region XIII">Region XIII – Caraga</option>
                <option value="BARMM">BARMM – Bangsamoro Autonomous Region in Muslim Mindanao</option>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label" id="passwordLabel">Password *</label>
              <div class="input-group">
                <input class="form-control" type="password" name="password" id="userPassword">
                <button class="btn btn-outline-secondary js-toggle-password" type="button" data-target="#userPassword" aria-label="Show password">
                  <i class="fa-regular fa-eye"></i>
                </button>
              </div>
              <div class="form-text" id="passwordHelp">Min 8 chars. Share securely.</div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-success"><i class="fa-solid fa-save me-2"></i><span id="submitLabel">Add User</span></button>
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
      jQuery('#usersTable').DataTable({
        order: [[0, 'desc']],
        pageLength: 10,
        language: {
          search: 'Search users:',
          lengthMenu: 'Show _MENU_ users per page',
          info: 'Showing _START_ to _END_ of _TOTAL_ users'
        }
      });
    }

    if (window.jQuery && jQuery.fn?.DataTable) {
      jQuery('#authorsTable').DataTable({
        order: [[0, 'desc']],
        pageLength: 10,
        columnDefs: [
          { orderable: false, targets: -1 }
        ],
        language: {
          search: 'Search authors:',
          lengthMenu: 'Show _MENU_ authors per page',
          info: 'Showing _START_ to _END_ of _TOTAL_ authors',
          emptyTable: 'No authors found.'
        }
      });
    }

    function validIdEndpointUrl(authorId) {
      const id = (authorId ?? '').toString().trim();
      if (id === '') return '';
      return `/uccrdc/files/valid_id.php?id=${encodeURIComponent(id)}`;
    }

    document.querySelectorAll('.view-author-btn').forEach((btn) => {
      btn.addEventListener('click', function () {
        const first = btn.dataset.first || '';
        const last = btn.dataset.last || '';
        const full = `${first} ${last}`.trim() || '-';
        const status = btn.dataset.status || '-';
        const created = btn.dataset.created || '-';

        document.getElementById('authorFullName').textContent = full;
        document.getElementById('authorEmail').textContent = btn.dataset.email || '-';
        document.getElementById('authorContact').textContent = btn.dataset.contact || '-';
        document.getElementById('authorCourse').textContent = btn.dataset.course || '-';
        document.getElementById('authorAddress').textContent = btn.dataset.address || '-';
        document.getElementById('authorPostal').textContent = btn.dataset.postal || '-';
        document.getElementById('authorRegion').textContent = btn.dataset.region || '-';
        document.getElementById('authorCreated').textContent = created;

        const statusBadge = document.getElementById('authorStatusBadge');
        const badgeMap = { Active: 'text-bg-success', Inactive: 'text-bg-secondary' };
        const badgeClass = badgeMap[status] || 'text-bg-secondary';
        statusBadge.className = `badge ${badgeClass}`;
        statusBadge.textContent = status;

        const validIdPath = btn.dataset.validId || '';
        const url = validIdEndpointUrl(btn.dataset.id);
        const text = document.getElementById('authorValidIdText');
        const preview = document.getElementById('authorValidIdPreview');
        if (preview) {
          preview.innerHTML = '<div class="text-muted small">Loading preview…</div>';
        }

        if (!validIdPath || !url) {
          if (text) text.textContent = 'No valid ID uploaded.';
          if (preview) {
            preview.innerHTML = '<div class="text-muted small">No valid ID uploaded.</div>';
          }
          return;
        }

        if (text) text.textContent = validIdPath;

        const lower = validIdPath.toLowerCase();
        const isImage = lower.endsWith('.jpg') || lower.endsWith('.jpeg') || lower.endsWith('.png') || lower.endsWith('.gif') || lower.endsWith('.webp');
        if (isImage) {
          const img = document.createElement('img');
          img.src = url;
          img.alt = 'Valid ID';
          img.className = 'img-fluid rounded border';
          img.style.maxHeight = '520px';
          img.style.objectFit = 'contain';
          img.addEventListener('error', function () {
            const hint = document.createElement('div');
            hint.className = 'text-muted small';
            hint.textContent = 'Unable to load the valid ID image.';
            if (preview) {
              preview.innerHTML = '';
              preview.appendChild(hint);
            }
          });
          if (preview) {
            preview.innerHTML = '';
            preview.appendChild(img);
          }
        } else if (lower.endsWith('.pdf')) {
          const iframe = document.createElement('iframe');
          iframe.src = url;
          iframe.title = 'Valid ID PDF';
          iframe.className = 'w-100 border rounded';
          iframe.style.height = '520px';
          if (preview) {
            preview.innerHTML = '';
            preview.appendChild(iframe);
          }
        } else {
          const hint = document.createElement('div');
          hint.className = 'text-muted small';
          hint.textContent = 'Valid ID file available, but preview is not supported for this file type.';
          if (preview) {
            preview.innerHTML = '';
            preview.appendChild(hint);
          }
        }
      });
    });
  });

  function editUser(user) {
    const form = document.getElementById('userForm');
    if (!form) return;
    form.reset();
    document.getElementById('userAction').value = 'update';
    document.getElementById('userId').value = user.id || '';
    form.querySelector('[name="role"]').value = user.role || 'Staff';
    form.querySelector('[name="contact_number"]').value = '';
    form.querySelector('[name="last_name"]').value = user.last_name || '';
    form.querySelector('[name="first_name"]').value = user.first_name || '';
    form.querySelector('[name="email"]').value = user.email || '';
    
    const regionSelect = form.querySelector('.admin-region-select');
    const municipalitySelect = form.querySelector('.admin-municipality-select');
    const postalCodeInput = form.querySelector('.admin-postal-code-input');
    
    const userRegion = user.region || 'NCR';
    const userMunicipality = user.address || '';
    const userPostalCode = user.postal_code || '0000';
    
    // Set region
    if (regionSelect) {
      regionSelect.value = userRegion;
      // Populate municipalities for the selected region
      if (userRegion) {
        populateAdminMunicipalities(userRegion, userMunicipality, userPostalCode);
      }
    }
    
    const pwd = document.getElementById('userPassword');
    if (pwd) pwd.required = false;
    const label = document.getElementById('passwordLabel');
    const help = document.getElementById('passwordHelp');
    if (label) label.textContent = 'Password (leave blank to keep current)';
    if (help) help.textContent = 'Leave blank to keep existing password.';
    const submitLabel = document.getElementById('submitLabel');
    if (submitLabel) submitLabel.textContent = 'Update User';
  }

  function resetUserForm() {
    const form = document.getElementById('userForm');
    if (!form) return;
    form.reset();
    document.getElementById('userAction').value = 'create';
    document.getElementById('userId').value = '';
    const pwd = document.getElementById('userPassword');
    if (pwd) pwd.required = true;
    const label = document.getElementById('passwordLabel');
    const help = document.getElementById('passwordHelp');
    if (label) label.textContent = 'Password *';
    if (help) help.textContent = 'Min 8 chars. Share securely.';
    const submitLabel = document.getElementById('submitLabel');
    if (submitLabel) submitLabel.textContent = 'Add User';
  }

  function toggleUserStatus(id, name, status) {
    if (!window.Swal) {
      if (!confirm(`Toggle status for ${name}?`)) return;
      submitActionForm('toggle', id);
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
        submitActionForm('toggle', id);
      }
    });
  }

  function deleteUser(id, name) {
    if (!window.Swal) {
      if (!confirm(`Delete ${name}? This cannot be undone.`)) return;
      submitActionForm('delete', id);
      return;
    }
    Swal.fire({
      title: 'Delete user?',
      text: `Delete ${name}? This cannot be undone.`,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Yes, delete',
      cancelButtonText: 'Cancel',
      confirmButtonColor: '#dc3545'
    }).then((result) => {
      if (result.isConfirmed) {
        submitActionForm('delete', id);
      }
    });
  }

  function submitActionForm(action, id) {
    const form = document.createElement('form');
    form.method = 'post';
    form.action = '';
    form.innerHTML = `
      <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
      <input type="hidden" name="action" value="${action}">
      <input type="hidden" name="user_id" value="${id}">
    `;
    document.body.appendChild(form);
    form.submit();
  }

  // Handle municipality and region dropdowns for admin user form
  async function populateAdminMunicipalities(region, selectMunicipality = null, selectPostalCode = null) {
    const form = document.getElementById('userForm');
    if (!form) return;

    const municipalitySelect = form.querySelector('.admin-municipality-select');
    const postalCodeInput = form.querySelector('.admin-postal-code-input');

    if (!region) {
      if (municipalitySelect) municipalitySelect.innerHTML = '<option value="">-- None --</option>';
      if (postalCodeInput) postalCodeInput.value = '';
      return;
    }

    if (municipalitySelect) municipalitySelect.innerHTML = '<option value="">Loading...</option>';

    try {
      const response = await fetch(`/uccrdc/files/get_municipalities.php?region=${encodeURIComponent(region)}`);
      const result = await response.json();

      if (result.ok && Array.isArray(result.data) && municipalitySelect) {
        municipalitySelect.innerHTML = '<option value="">-- None --</option>';
        result.data.forEach(municipality => {
          const option = document.createElement('option');
          option.value = municipality.name;
          option.textContent = municipality.name;
          option.dataset.postalCode = municipality.postal_code;
          
          if (selectMunicipality === municipality.name) {
            option.selected = true;
          }
          
          municipalitySelect.appendChild(option);
        });

        // If a municipality should be selected, fill postal code
        if (selectMunicipality && selectPostalCode) {
          municipalitySelect.value = selectMunicipality;
          if (postalCodeInput) postalCodeInput.value = selectPostalCode;
        }
      } else if (municipalitySelect) {
        console.error('API response error:', result);
        municipalitySelect.innerHTML = '<option value="">No municipalities found</option>';
      }
    } catch (error) {
      console.error('Error fetching municipalities:', error);
      if (municipalitySelect) {
        municipalitySelect.innerHTML = '<option value="">Error loading municipalities</option>';
      }
    }
  }

  // Admin user form region change handler
  const adminForm = document.getElementById('userForm');
  if (adminForm) {
    const adminRegionSelect = adminForm.querySelector('.admin-region-select');
    const adminMunicipalitySelect = adminForm.querySelector('.admin-municipality-select');
    const adminPostalCodeInput = adminForm.querySelector('.admin-postal-code-input');

    if (adminRegionSelect) {
      adminRegionSelect.addEventListener('change', (e) => {
        const region = e.target.value;
        populateAdminMunicipalities(region);
      });
    }

    if (adminMunicipalitySelect) {
      adminMunicipalitySelect.addEventListener('change', (e) => {
        const selectedOption = e.target.options[e.target.selectedIndex];
        if (adminPostalCodeInput) {
          adminPostalCodeInput.value = selectedOption.dataset.postalCode || '';
        }
      });
    }
  }

  window.editUser = editUser;
  window.resetUserForm = resetUserForm;
  window.toggleUserStatus = toggleUserStatus;
  window.deleteUser = deleteUser;
</script>

<?php require_once __DIR__ . '/../includes/dashboard_footer.php'; ?>
