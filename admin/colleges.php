<?php
require_once __DIR__ . '/../app/auth.php';
require_login();
require_role(['Admin']);

// 1. HANDLE LOGIC AND REDIRECTS FIRST
$flash = null;
if (!empty($_SESSION['flash'])) {
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_verify_or_die();
    $action = sanitize_string($_POST['action'] ?? '');

    try {
        if ($action === 'college_create') {
            $code = sanitize_string($_POST['code'] ?? '');
            $name = sanitize_string($_POST['name'] ?? '');
            if ($code === '' || $name === '') {
                $flash = ['type' => 'danger', 'msg' => 'College code and name are required.'];
            } else {
                $stmt = db()->prepare('INSERT INTO colleges (code, name, is_active) VALUES (?, ?, 1)');
                $stmt->execute([$code, $name]);
                $flash = ['type' => 'success', 'msg' => 'College added.'];
            }
        }

        if ($action === 'college_update') {
            $id = (int)($_POST['id'] ?? 0);
            $code = sanitize_string($_POST['code'] ?? '');
            $name = sanitize_string($_POST['name'] ?? '');
            if ($id && $code !== '' && $name !== '') {
                $stmt = db()->prepare('UPDATE colleges SET code = ?, name = ? WHERE id = ?');
                $stmt->execute([$code, $name, $id]);
                $flash = ['type' => 'success', 'msg' => 'College updated.'];
            }
        }

        if ($action === 'college_toggle') {
            $id = (int)($_POST['id'] ?? 0);
            $isActive = (int)($_POST['is_active'] ?? 0);
            if ($id) {
                $stmt = db()->prepare('UPDATE colleges SET is_active = ? WHERE id = ?');
                $stmt->execute([$isActive ? 1 : 0, $id]);
                $flash = ['type' => 'success', 'msg' => 'College status updated.'];
            }
        }

        if ($action === 'college_delete') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id) {
                $stmt = db()->prepare('DELETE FROM colleges WHERE id = ?');
                $stmt->execute([$id]);
                $flash = ['type' => 'success', 'msg' => 'College deleted.'];
            }
        }

        if ($action === 'course_create') {
            $code = sanitize_string($_POST['code'] ?? '');
            $name = sanitize_string($_POST['name'] ?? '');
            $collegeId = (int)($_POST['college_id'] ?? 0);
            if ($code === '' || $name === '' || !$collegeId) {
                $flash = ['type' => 'danger', 'msg' => 'Course code, name, and college are required.'];
            } else {
                $stmt = db()->prepare('INSERT INTO courses (college_id, code, name, is_active) VALUES (?, ?, ?, 1)');
                $stmt->execute([$collegeId, $code, $name]);
                $flash = ['type' => 'success', 'msg' => 'Course added.'];
            }
        }

        if ($action === 'course_update') {
            $id = (int)($_POST['id'] ?? 0);
            $code = sanitize_string($_POST['code'] ?? '');
            $name = sanitize_string($_POST['name'] ?? '');
            $collegeId = (int)($_POST['college_id'] ?? 0);
            if ($id && $code !== '' && $name !== '' && $collegeId) {
                $stmt = db()->prepare('UPDATE courses SET college_id = ?, code = ?, name = ? WHERE id = ?');
                $stmt->execute([$collegeId, $code, $name, $id]);
                $flash = ['type' => 'success', 'msg' => 'Course updated.'];
            }
        }

        if ($action === 'course_toggle') {
            $id = (int)($_POST['id'] ?? 0);
            $isActive = (int)($_POST['is_active'] ?? 0);
            if ($id) {
                $stmt = db()->prepare('UPDATE courses SET is_active = ? WHERE id = ?');
                $stmt->execute([$isActive ? 1 : 0, $id]);
                $flash = ['type' => 'success', 'msg' => 'Course status updated.'];
            }
        }

        if ($action === 'course_delete') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id) {
                $stmt = db()->prepare('DELETE FROM courses WHERE id = ?');
                $stmt->execute([$id]);
                $flash = ['type' => 'success', 'msg' => 'Course deleted.'];
            }
        }
    } catch (Throwable $e) {
        $flash = ['type' => 'danger', 'msg' => 'Action failed.'];
    }

    // Post/Redirect/Get pattern: No HTML has been sent yet, so this works!
    $_SESSION['flash'] = $flash;
    header('Location: /uccrdc/admin/colleges.php');
    exit;
}

// 2. NOW FETCH DATA AND START OUTPUTTING HTML
$colleges = db()->query('SELECT * FROM colleges ORDER BY name')->fetchAll();
$courses = db()->query('SELECT c.*, co.name AS college_name FROM courses c JOIN colleges co ON co.id = c.college_id ORDER BY c.name')->fetchAll();

$pageTitle = 'Colleges & Courses';
require_once __DIR__ . '/../includes/dashboard_header.php';
?>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">

<div class="container-fluid p-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="fw-bold text-success mb-0">Colleges & Courses</h2>
    <div class="d-flex gap-2">
      <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#collegeModal" onclick="resetCollegeForm()">
        <i class="fa-solid fa-plus me-2"></i>Add College
      </button>
      <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#courseModal" onclick="resetCourseForm()">
        <i class="fa-solid fa-plus me-2"></i>Add Course
      </button>
    </div>
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

  <div class="row g-4">
    <div class="col-lg-12">
      <div class="card shadow-sm">
        <div class="card-header bg-success text-white">
          <h5 class="mb-0"><i class="fa-solid fa-building-columns me-2"></i>Colleges</h5>
        </div>
        <div class="card-body">
          <div class="table-responsive">
            <table id="collegesTable" class="table table-hover mb-0">
              <thead class="table-success">
                <tr>
                  <th>ID</th>
                  <th>Code</th>
                  <th>Name</th>
                  <th class="text-center" style="width:140px">Actions</th>
                </tr>
              </thead>
              <tbody>
              <?php foreach ($colleges as $c): ?>
                <tr>
                  <td><?= (int)$c['id'] ?></td>
                  <td><span class="badge bg-dark-subtle text-dark"><?= htmlspecialchars($c['code'] ?? '') ?></span></td>
                  <td class="fw-semibold"><?= htmlspecialchars($c['name']) ?></td>
                  <td class="text-center">
                    <button type="button" class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#collegeModal" onclick='editCollege(<?= htmlspecialchars(json_encode($c), ENT_QUOTES) ?>)' title="Edit">
                      <i class="fa-solid fa-pen"></i>
                    </button>
                    <button type="button" class="btn btn-sm <?= (int)$c['is_active']===1 ? 'btn-secondary' : 'btn-success' ?>" onclick="toggleCollege(<?= (int)$c['id'] ?>, '<?= htmlspecialchars($c['name'], ENT_QUOTES) ?>')" title="Enable/Disable">
                      <i class="fa-solid <?= (int)$c['is_active']===1 ? 'fa-toggle-on' : 'fa-toggle-off' ?>"></i>
                    </button>
                    <button type="button" class="btn btn-sm btn-danger" onclick="deleteCollege(<?= (int)$c['id'] ?>, '<?= htmlspecialchars($c['name'], ENT_QUOTES) ?>')" title="Delete">
                      <i class="fa-solid fa-trash"></i>
                    </button>
                    <form method="post" class="d-none" id="collegeToggleForm<?= (int)$c['id'] ?>">
                      <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                      <input type="hidden" name="action" value="college_toggle">
                      <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                      <input type="hidden" name="is_active" value="<?= (int)$c['is_active']===1 ? 0 : 1 ?>">
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <div class="col-lg-12">
      <div class="card shadow-sm">
        <div class="card-header bg-success text-white">
          <h5 class="mb-0"><i class="fa-solid fa-graduation-cap me-2"></i>Courses</h5>
        </div>
        <div class="card-body">
          <div class="table-responsive">
            <table id="coursesTable" class="table table-hover mb-0">
              <thead class="table-success">
                <tr>
                  <th>ID</th>
                  <th>Code</th>
                  <th>Course</th>
                  <th>College</th>
                  <th class="text-center" style="width:140px">Actions</th>
                </tr>
              </thead>
              <tbody>
              <?php foreach ($courses as $c): ?>
                <tr>
                  <td><?= (int)$c['id'] ?></td>
                  <td><span class="badge bg-dark-subtle text-dark"><?= htmlspecialchars($c['code'] ?? '') ?></span></td>
                  <td class="fw-semibold"><?= htmlspecialchars($c['name']) ?></td>
                  <td class="text-muted small"><?= htmlspecialchars($c['college_name']) ?></td>
                  <td class="text-center">
                    <button type="button" class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#courseModal" onclick='editCourse(<?= htmlspecialchars(json_encode($c), ENT_QUOTES) ?>)' title="Edit">
                      <i class="fa-solid fa-pen"></i>
                    </button>
                    <button type="button" class="btn btn-sm <?= (int)$c['is_active']===1 ? 'btn-secondary' : 'btn-success' ?>" onclick="toggleCourse(<?= (int)$c['id'] ?>, '<?= htmlspecialchars($c['name'], ENT_QUOTES) ?>')" title="Enable/Disable">
                      <i class="fa-solid <?= (int)$c['is_active']===1 ? 'fa-toggle-on' : 'fa-toggle-off' ?>"></i>
                    </button>
                    <button type="button" class="btn btn-sm btn-danger" onclick="deleteCourse(<?= (int)$c['id'] ?>, '<?= htmlspecialchars($c['name'], ENT_QUOTES) ?>')" title="Delete">
                      <i class="fa-solid fa-trash"></i>
                    </button>
                    <form method="post" class="d-none" id="courseToggleForm<?= (int)$c['id'] ?>">
                      <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                      <input type="hidden" name="action" value="course_toggle">
                      <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                      <input type="hidden" name="is_active" value="<?= (int)$c['is_active']===1 ? 0 : 1 ?>">
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="collegeModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title"><i class="fa-solid fa-building-columns me-2"></i><span id="collegeModalTitle">Add College</span></h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form method="post" id="collegeForm">
        <div class="modal-body">
          <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
          <input type="hidden" name="action" id="collegeAction" value="college_create">
          <input type="hidden" name="id" id="collegeId" value="">
          <div class="mb-3">
            <label class="form-label">College Code *</label>
            <input class="form-control" name="code" id="collegeCode" required>
          </div>
          <div class="mb-3">
            <label class="form-label">College Name *</label>
            <input class="form-control" name="name" id="collegeName" required>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-success"><i class="fa-solid fa-save me-2"></i><span id="collegeSubmitLabel">Add College</span></button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="courseModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title"><i class="fa-solid fa-graduation-cap me-2"></i><span id="courseModalTitle">Add Course</span></h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form method="post" id="courseForm">
        <div class="modal-body">
          <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
          <input type="hidden" name="action" id="courseAction" value="course_create">
          <input type="hidden" name="id" id="courseId" value="">
          <div class="mb-3">
            <label class="form-label">College *</label>
            <select class="form-select" name="college_id" id="courseCollege" required>
              <option value="">-- Select College --</option>
              <?php foreach ($colleges as $c): ?>
                <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Course Code *</label>
            <input class="form-control" name="code" id="courseCode" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Course Name *</label>
            <input class="form-control" name="name" id="courseName" required>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-success"><i class="fa-solid fa-save me-2"></i><span id="courseSubmitLabel">Add Course</span></button>
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
      jQuery('#collegesTable').DataTable({
        order: [[0, 'desc']],
        pageLength: 10,
        columnDefs: [{ orderable: false, targets: -1 }],
        language: {
          search: 'Search colleges:',
          lengthMenu: 'Show _MENU_ colleges per page',
          info: 'Showing _START_ to _END_ of _TOTAL_ colleges'
        }
      });
      jQuery('#coursesTable').DataTable({
        order: [[0, 'desc']],
        pageLength: 10,
        columnDefs: [{ orderable: false, targets: -1 }],
        language: {
          search: 'Search courses:',
          lengthMenu: 'Show _MENU_ courses per page',
          info: 'Showing _START_ to _END_ of _TOTAL_ courses'
        }
      });
    }
  });

  function editCollege(college) {
    document.getElementById('collegeAction').value = 'college_update';
    document.getElementById('collegeId').value = college.id || '';
    document.getElementById('collegeCode').value = college.code || '';
    document.getElementById('collegeName').value = college.name || '';
    document.getElementById('collegeModalTitle').textContent = 'Edit College';
    document.getElementById('collegeSubmitLabel').textContent = 'Save Changes';
  }

  function resetCollegeForm() {
    document.getElementById('collegeAction').value = 'college_create';
    document.getElementById('collegeId').value = '';
    document.getElementById('collegeCode').value = '';
    document.getElementById('collegeName').value = '';
    document.getElementById('collegeModalTitle').textContent = 'Add College';
    document.getElementById('collegeSubmitLabel').textContent = 'Add College';
  }

  function editCourse(course) {
    document.getElementById('courseAction').value = 'course_update';
    document.getElementById('courseId').value = course.id || '';
    document.getElementById('courseCollege').value = course.college_id || '';
    document.getElementById('courseCode').value = course.code || '';
    document.getElementById('courseName').value = course.name || '';
    document.getElementById('courseModalTitle').textContent = 'Edit Course';
    document.getElementById('courseSubmitLabel').textContent = 'Save Changes';
  }

  function resetCourseForm() {
    document.getElementById('courseAction').value = 'course_create';
    document.getElementById('courseId').value = '';
    document.getElementById('courseCollege').value = '';
    document.getElementById('courseCode').value = '';
    document.getElementById('courseName').value = '';
    document.getElementById('courseModalTitle').textContent = 'Add Course';
    document.getElementById('courseSubmitLabel').textContent = 'Add Course';
  }

  function toggleCollege(id, name) {
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
        document.getElementById(`collegeToggleForm${id}`)?.submit();
      }
    });
  }

  function toggleCourse(id, name) {
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
        document.getElementById(`courseToggleForm${id}`)?.submit();
      }
    });
  }

  function deleteCollege(id, name) {
    Swal.fire({
      title: 'Delete college?',
      text: `Delete ${name}? This cannot be undone.`,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Yes, delete',
      cancelButtonText: 'Cancel',
      confirmButtonColor: '#dc3545'
    }).then((result) => {
      if (result.isConfirmed) {
        submitDelete('college_delete', id);
      }
    });
  }

  function deleteCourse(id, name) {
    Swal.fire({
      title: 'Delete course?',
      text: `Delete ${name}? This cannot be undone.`,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Yes, delete',
      cancelButtonText: 'Cancel',
      confirmButtonColor: '#dc3545'
    }).then((result) => {
      if (result.isConfirmed) {
        submitDelete('course_delete', id);
      }
    });
  }

  function submitDelete(action, id) {
    const form = document.createElement('form');
    form.method = 'post';
    form.action = '';
    form.innerHTML = `
      <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
      <input type="hidden" name="action" value="${action}">
      <input type="hidden" name="id" value="${id}">
    `;
    document.body.appendChild(form);
    form.submit();
  }

  window.editCollege = editCollege;
  window.resetCollegeForm = resetCollegeForm;
  window.editCourse = editCourse;
  window.resetCourseForm = resetCourseForm;
  window.toggleCollege = toggleCollege;
  window.toggleCourse = toggleCourse;
  window.deleteCollege = deleteCollege;
  window.deleteCourse = deleteCourse;
</script>

<?php require_once __DIR__ . '/../includes/dashboard_footer.php'; ?>