<?php
require_once __DIR__ . '/../app/auth.php';
require_login();
require_role(['Author', 'Staff']);

$pageTitle = 'Account Settings';
require_once __DIR__ . '/../includes/dashboard_header.php';

$user = current_user();
$userId = (int)($user['id'] ?? 0);

$stmt = db()->prepare('SELECT id, first_name, last_name, email, contact_number, course, address, postal_code, region, role, valid_id_path, password_hash FROM authors WHERE id = ? LIMIT 1');
$stmt->execute([$userId]);
$profile = $stmt->fetch();
if (!$profile) {
    http_response_code(404);
    exit('User not found');
}

$flash = null;
$errors = [];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_verify_or_die();

  $first = sanitize_string($_POST['first_name'] ?? '');
  $last = sanitize_string($_POST['last_name'] ?? '');
  $email = sanitize_string($_POST['email'] ?? '');
  $contact = sanitize_string($_POST['contact_number'] ?? '');
  $course = sanitize_string($_POST['course'] ?? '');
  $address = sanitize_string($_POST['address'] ?? '');
  $postal = sanitize_string($_POST['postal_code'] ?? '');
  $region = sanitize_string($_POST['region'] ?? '');

    $currentPassword = trim((string)($_POST['current_password'] ?? ''));
    $newPassword = trim((string)($_POST['new_password'] ?? ''));
    $confirmPassword = trim((string)($_POST['confirm_password'] ?? ''));

    // Only validate fields that changed (so saving password/ID doesn't require re-validating everything).
    $updates = [];
    $params = [];

    $currentFirst = (string)($profile['first_name'] ?? '');
    $currentLast = (string)($profile['last_name'] ?? '');
    $currentEmail = (string)($profile['email'] ?? '');
    $currentContact = (string)($profile['contact_number'] ?? '');
    $currentAddress = (string)($profile['address'] ?? '');
    $currentPostal = (string)($profile['postal_code'] ?? '');
    $currentRegion = (string)($profile['region'] ?? '');

    if ($first !== $currentFirst) {
      if ($first === '') $errors[] = 'First name cannot be empty.';
      else { $updates[] = 'first_name = ?'; $params[] = $first; }
    }
    if ($last !== $currentLast) {
      if ($last === '') $errors[] = 'Last name cannot be empty.';
      else { $updates[] = 'last_name = ?'; $params[] = $last; }
    }
    if ($email !== $currentEmail) {
      if ($email === '' || !validate_email($email)) {
        $errors[] = 'A valid email address is required.';
      } else {
        // Email uniqueness (exclude self)
        $chk = db()->prepare('SELECT id FROM authors WHERE email = ? AND id != ? LIMIT 1');
        $chk->execute([$email, $userId]);
        if ($chk->fetch()) {
          $errors[] = 'Email is already in use.';
        } else {
          $updates[] = 'email = ?';
          $params[] = $email;
        }
      }
    }
    if ($contact !== $currentContact) {
      if ($contact === '') $errors[] = 'Contact number cannot be empty.';
      else { $updates[] = 'contact_number = ?'; $params[] = $contact; }
    }
    if ($address !== $currentAddress) {
      if ($address === '') $errors[] = 'Address cannot be empty.';
      else { $updates[] = 'address = ?'; $params[] = $address; }
    }
    if ($postal !== $currentPostal) {
      if ($postal === '') $errors[] = 'Postal code cannot be empty.';
      else { $updates[] = 'postal_code = ?'; $params[] = $postal; }
    }
    if ($region !== $currentRegion) {
      if ($region === '') $errors[] = 'Region cannot be empty.';
      else { $updates[] = 'region = ?'; $params[] = $region; }
    }

    if (($profile['role'] ?? '') === 'Author') {
      $currentCourse = (string)($profile['course'] ?? '');
      $newCourseValue = ($course === '' ? '' : $course);
      if ($newCourseValue !== $currentCourse) {
        $updates[] = 'course = ?';
        $params[] = ($course === '' ? null : $course);
      }
    }

    // Only trigger password change when user enters a new password.
    // (Browsers may autofill current password; that should not force password validation.)
    $wantsPasswordChange = ($newPassword !== '' || $confirmPassword !== '');
    $newHash = null;
    if ($wantsPasswordChange) {
        if ($currentPassword === '') {
            $errors[] = 'Current password is required to change your password.';
        } elseif (!password_verify($currentPassword, (string)($profile['password_hash'] ?? ''))) {
            $errors[] = 'Current password is incorrect.';
        }

        if ($newPassword === '' || strlen($newPassword) < 8) {
            $errors[] = 'New password must be at least 8 characters.';
        }
        if ($newPassword !== $confirmPassword) {
            $errors[] = 'New password and confirmation do not match.';
        }

        if (!$errors) {
            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
        }
    }

    // Optional: valid ID upload (image)
    $newValidIdPath = null;
    if (isset($_FILES['valid_id']) && is_array($_FILES['valid_id']) && (int)($_FILES['valid_id']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $file = $_FILES['valid_id'];
        if (($file['tmp_name'] ?? '') !== '' && @getimagesize($file['tmp_name']) === false) {
            $errors[] = 'Valid ID must be an image file (JPG/PNG).';
        }

        if (!$errors) {
            $config = app_config();
            $uploads = $config['uploads'] ?? [];
            $newValidIdPath = move_uploaded_file_validated(
                $file,
                (string)($uploads['valid_id_dir'] ?? ''),
                (array)($uploads['valid_id_extensions'] ?? ['jpg', 'jpeg', 'png']),
                (int)($uploads['max_valid_id_bytes'] ?? (5 * 1024 * 1024))
            );
        }
    }

    if ($errors) {
        $flash = ['type' => 'danger', 'msg' => implode(' ', $errors)];
    } else {
        try {
        if ($newValidIdPath !== null) {
          $updates[] = 'valid_id_path = ?';
          $params[] = $newValidIdPath;
        }
        if ($newHash !== null) {
          $updates[] = 'password_hash = ?';
          $params[] = $newHash;
        }

        if (!$updates) {
          $flash = ['type' => 'success', 'msg' => 'No changes to save.'];
        } else {
          $sql = 'UPDATE authors SET ' . implode(', ', $updates) . ' WHERE id = ? LIMIT 1';
          $params[] = $userId;
          $upd = db()->prepare($sql);
          $upd->execute($params);

          $flash = ['type' => 'success', 'msg' => 'Account settings updated.'];
        }

            // Refresh profile data
            $stmt = db()->prepare('SELECT id, first_name, last_name, email, contact_number, course, address, postal_code, region, role, valid_id_path, password_hash FROM authors WHERE id = ? LIMIT 1');
            $stmt->execute([$userId]);
            $profile = $stmt->fetch() ?: $profile;

            // Keep sidebar name/email in sync
            start_session();
            if (isset($_SESSION['user']) && is_array($_SESSION['user'])) {
                $_SESSION['user']['first_name'] = $profile['first_name'] ?? $_SESSION['user']['first_name'] ?? '';
                $_SESSION['user']['last_name'] = $profile['last_name'] ?? $_SESSION['user']['last_name'] ?? '';
                $_SESSION['user']['email'] = $profile['email'] ?? $_SESSION['user']['email'] ?? '';
            }

        } catch (Throwable $e) {
            $flash = ['type' => 'danger', 'msg' => 'Failed to update account settings.'];
        }
    }
}

function h($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES);
}

$currentValidId = (string)($profile['valid_id_path'] ?? '');
$currentValidIdLabel = $currentValidId !== '' ? basename(str_replace('\\', '/', $currentValidId)) : 'None uploaded';
?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<div class="container-fluid p-3 p-md-4">
  <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-4">
    <div>
      <div class="d-flex align-items-center gap-2">
        <i class="fa-solid fa-gear text-success"></i>
        <h2 class="fw-bold text-success mb-0">Account Settings</h2>
      </div>
      <div class="text-muted">Update your profile information, valid ID, and password.</div>
    </div>
  </div>

  <?php if ($flash): ?>
    <script>
      document.addEventListener('DOMContentLoaded', function () {
        if (!window.Swal) return;
        Swal.fire({
          icon: '<?= ($flash['type'] ?? '') === 'success' ? 'success' : 'error' ?>',
          title: '<?= ($flash['type'] ?? '') === 'success' ? 'Success' : 'Error' ?>',
          text: '<?= h($flash['msg'] ?? '') ?>'
        });
      });
    </script>
  <?php endif; ?>

  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">

    <div class="row g-3 g-lg-4">
      <div class="col-lg-7">
        <div class="card shadow-sm">
          <div class="card-header bg-white">
            <div class="d-flex align-items-center justify-content-between">
              <div class="fw-semibold"><i class="fa-solid fa-id-card me-2"></i>Profile</div>
              <div class="text-muted small">Edit any field you want</div>
            </div>
          </div>
          <div class="card-body">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label fw-semibold">First Name</label>
                <input class="form-control" name="first_name" value="<?= h($profile['first_name'] ?? '') ?>">
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold">Last Name</label>
                <input class="form-control" name="last_name" value="<?= h($profile['last_name'] ?? '') ?>">
              </div>
              <div class="col-12">
                <label class="form-label fw-semibold">Email</label>
                <input class="form-control" type="email" name="email" value="<?= h($profile['email'] ?? '') ?>">
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold">Contact Number</label>
                <input class="form-control" name="contact_number" value="<?= h($profile['contact_number'] ?? '') ?>">
              </div>

              <?php if (($profile['role'] ?? '') === 'Author'): ?>
                <div class="col-md-6">
                  <label class="form-label fw-semibold">Course <span class="text-muted small">(optional)</span></label>
                  <input class="form-control" name="course" value="<?= h($profile['course'] ?? '') ?>">
                </div>
              <?php endif; ?>

              <div class="col-12">
                <label class="form-label fw-semibold">Municipality</label>
                <select class="form-control" name="address" id="settingsMunicipalitySelect">
                  <option value="" disabled>Select Municipality</option>
                  <option value="<?= h($profile['address'] ?? '') ?>" selected><?= h($profile['address'] ?? '') ?></option>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold">Postal Code</label>
                <input class="form-control" name="postal_code" id="settingsPostalCodeInput" value="<?= h($profile['postal_code'] ?? '') ?>" readonly>
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold">Region</label>
                <select class="form-control" name="region" id="settingsRegionSelect">
                  <option value="" disabled>Select Region</option>
                  <option value="NCR" <?= ($profile['region'] ?? '') === 'NCR' ? 'selected' : '' ?>>NCR – National Capital Region</option>
                  <option value="CAR" <?= ($profile['region'] ?? '') === 'CAR' ? 'selected' : '' ?>>CAR – Cordillera Administrative Region</option>
                  <option value="Region I" <?= ($profile['region'] ?? '') === 'Region I' ? 'selected' : '' ?>>Region I – Ilocos Region</option>
                  <option value="Region II" <?= ($profile['region'] ?? '') === 'Region II' ? 'selected' : '' ?>>Region II – Cagayan Valley</option>
                  <option value="Region III" <?= ($profile['region'] ?? '') === 'Region III' ? 'selected' : '' ?>>Region III – Central Luzon</option>
                  <option value="Region IV-A" <?= ($profile['region'] ?? '') === 'Region IV-A' ? 'selected' : '' ?>>Region IV-A – CALABARZON</option>
                  <option value="Region IV-B" <?= ($profile['region'] ?? '') === 'Region IV-B' ? 'selected' : '' ?>>Region IV-B – MIMAROPA</option>
                  <option value="Region V" <?= ($profile['region'] ?? '') === 'Region V' ? 'selected' : '' ?>>Region V – Bicol Region</option>
                  <option value="Region VI" <?= ($profile['region'] ?? '') === 'Region VI' ? 'selected' : '' ?>>Region VI – Western Visayas</option>
                  <option value="Region VII" <?= ($profile['region'] ?? '') === 'Region VII' ? 'selected' : '' ?>>Region VII – Central Visayas</option>
                  <option value="Region VIII" <?= ($profile['region'] ?? '') === 'Region VIII' ? 'selected' : '' ?>>Region VIII – Eastern Visayas</option>
                  <option value="Region IX" <?= ($profile['region'] ?? '') === 'Region IX' ? 'selected' : '' ?>>Region IX – Zamboanga Peninsula</option>
                  <option value="Region X" <?= ($profile['region'] ?? '') === 'Region X' ? 'selected' : '' ?>>Region X – Northern Mindanao</option>
                  <option value="Region XI" <?= ($profile['region'] ?? '') === 'Region XI' ? 'selected' : '' ?>>Region XI – Davao Region</option>
                  <option value="Region XII" <?= ($profile['region'] ?? '') === 'Region XII' ? 'selected' : '' ?>>Region XII – SOCCSKSARGEN</option>
                  <option value="Region XIII" <?= ($profile['region'] ?? '') === 'Region XIII' ? 'selected' : '' ?>>Region XIII – Caraga</option>
                  <option value="BARMM" <?= ($profile['region'] ?? '') === 'BARMM' ? 'selected' : '' ?>>BARMM – Bangsamoro Autonomous Region in Muslim Mindanao</option>
                </select>
              </div>
            </div>
          </div>
        </div>

        <div class="card shadow-sm mt-3">
          <div class="card-header bg-white">
            <div class="d-flex align-items-center justify-content-between">
              <div class="fw-semibold"><i class="fa-solid fa-image me-2"></i>Valid ID</div>
              <span class="badge text-bg-light border"><?= h($currentValidIdLabel) ?></span>
            </div>
          </div>
          <div class="card-body">
            <div class="text-muted small mb-2">Upload a clear photo of your valid ID (JPG/PNG).</div>
            <label class="form-label fw-semibold">Replace / Upload Valid ID <span class="text-muted small">(JPG/PNG, max 5MB)</span></label>
            <input class="form-control" type="file" name="valid_id" accept="image/jpeg,image/png,.jpg,.jpeg,.png">
          </div>
        </div>
      </div>

      <div class="col-lg-5">
        <div class="card shadow-sm">
          <div class="card-header bg-white">
            <div class="fw-semibold"><i class="fa-solid fa-lock me-2"></i>Change Password</div>
          </div>
          <div class="card-body">
            <div class="text-muted small mb-3">Leave blank if you don't want to change your password.</div>
            <div class="row g-3">
              <div class="col-12">
                <label class="form-label fw-semibold">Current Password</label>
                <div class="input-group">
                  <input class="form-control" type="password" id="currentPassword" name="current_password" autocomplete="current-password">
                  <button class="btn btn-outline-secondary js-toggle-password" type="button" data-target="#currentPassword" aria-label="Show password">
                    <i class="fa-regular fa-eye"></i>
                  </button>
                </div>
              </div>
              <div class="col-12">
                <label class="form-label fw-semibold">New Password</label>
                <div class="input-group">
                  <input class="form-control" type="password" id="newPassword" name="new_password" autocomplete="new-password">
                  <button class="btn btn-outline-secondary js-toggle-password" type="button" data-target="#newPassword" aria-label="Show password">
                    <i class="fa-regular fa-eye"></i>
                  </button>
                </div>
                <div class="form-text">Minimum 8 characters.</div>
              </div>
              <div class="col-12">
                <label class="form-label fw-semibold">Confirm New Password</label>
                <div class="input-group">
                  <input class="form-control" type="password" id="confirmNewPassword" name="confirm_password" autocomplete="new-password">
                  <button class="btn btn-outline-secondary js-toggle-password" type="button" data-target="#confirmNewPassword" aria-label="Show password">
                    <i class="fa-regular fa-eye"></i>
                  </button>
                </div>
              </div>
            </div>
          </div>
          <div class="card-footer bg-white d-flex justify-content-end gap-2">
            <button type="submit" class="btn btn-success px-4"><i class="fa-solid fa-save me-2"></i>Save Changes</button>
          </div>
        </div>

        <div class="card border mt-3">
          <div class="card-body py-3">
            <div class="fw-semibold mb-1"><i class="fa-solid fa-shield-halved me-2"></i>Security tip</div>
            <div class="text-muted small mb-0">Use a strong password and don’t share it with anyone.</div>
          </div>
        </div>
      </div>
    </div>
  </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const settingsRegionSelect = document.getElementById('settingsRegionSelect');
  const settingsMunicipalitySelect = document.getElementById('settingsMunicipalitySelect');
  const settingsPostalCodeInput = document.getElementById('settingsPostalCodeInput');
  const currentMunicipalityValue = '<?= h($profile['address'] ?? '') ?>';
  const currentRegionValue = '<?= h($profile['region'] ?? '') ?>';

  // Function to populate municipalities when region changes
  async function populateMunicipalities(region, selectMunicipality = null) {
    if (!region) {
      settingsMunicipalitySelect.innerHTML = '<option value="" disabled selected>Select Municipality</option>';
      settingsPostalCodeInput.value = '';
      return;
    }

    settingsMunicipalitySelect.innerHTML = '<option value="" disabled selected>Loading...</option>';

    try {
      const response = await fetch(`/uccrdc/files/get_municipalities.php?region=${encodeURIComponent(region)}`);
      const result = await response.json();

      if (result.ok && Array.isArray(result.data)) {
        settingsMunicipalitySelect.innerHTML = '<option value="" disabled selected>Select Municipality</option>';
        result.data.forEach(municipality => {
          const option = document.createElement('option');
          option.value = municipality.name;
          option.textContent = municipality.name;
          option.dataset.postalCode = municipality.postal_code;
          
          if (selectMunicipality === municipality.name) {
            option.selected = true;
          }
          
          settingsMunicipalitySelect.appendChild(option);
        });

        // If a municipality should be selected, do it now
        if (selectMunicipality) {
          settingsMunicipalitySelect.value = selectMunicipality;
          const selectedOption = settingsMunicipalitySelect.options[settingsMunicipalitySelect.selectedIndex];
          settingsPostalCodeInput.value = selectedOption.dataset.postalCode || '';
        }
      } else {
        console.error('API response error:', result);
        settingsMunicipalitySelect.innerHTML = '<option value="" disabled selected>No municipalities found</option>';
      }
    } catch (error) {
      console.error('Error fetching municipalities:', error);
      settingsMunicipalitySelect.innerHTML = '<option value="" disabled selected>Error loading municipalities</option>';
    }
  }

  // Handle region change
  if (settingsRegionSelect) {
    settingsRegionSelect.addEventListener('change', (e) => {
      const region = e.target.value;
      populateMunicipalities(region);
    });
  }

  // Handle municipality change to auto-fill postal code
  if (settingsMunicipalitySelect) {
    settingsMunicipalitySelect.addEventListener('change', (e) => {
      const selectedOption = e.target.options[e.target.selectedIndex];
      settingsPostalCodeInput.value = selectedOption.dataset.postalCode || '';
    });
  }

  // Initialize on page load: populate municipalities if a region is already selected
  if (currentRegionValue && currentMunicipalityValue) {
    populateMunicipalities(currentRegionValue, currentMunicipalityValue);
  }
});
</script>

<?php require_once __DIR__ . '/../includes/dashboard_footer.php'; ?>
