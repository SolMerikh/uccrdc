<?php
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/db.php';

start_session();

function find_reset_row(string $token): ?array
{
    if ($token === '') {
        return null;
    }

  try {
    $tokenHash = hash('sha256', $token);
    $stmt = db()->prepare('SELECT pr.*, a.email FROM password_resets pr JOIN authors a ON a.id = pr.author_id WHERE pr.token_hash = ? AND pr.used_at IS NULL AND pr.expires_at > NOW() LIMIT 1');
    $stmt->execute([$tokenHash]);
    $row = $stmt->fetch();
    return $row ?: null;
  } catch (Throwable $e) {
    return null;
  }
}

$token = sanitize_string($_GET['token'] ?? ($_POST['token'] ?? ''));
$resetRow = null;
$mode = 'form';
$error = '';
$success = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
  // HTML-safe CSRF validation (csrf_verify_or_die() returns JSON)
  $postedCsrf = (string)($_POST['_csrf'] ?? '');
  if ($postedCsrf === '' || !hash_equals($_SESSION['_csrf'] ?? '', $postedCsrf)) {
    $error = 'Invalid CSRF token. Please refresh the page and try again.';
  }

    $newPassword = (string)($_POST['password'] ?? '');
    $confirmPassword = (string)($_POST['confirm_password'] ?? '');

    if ($error === '') {
      // Continue only if CSRF is valid
      if ($token === '') {
        $error = 'Invalid reset token.';
      } elseif ($newPassword === '' || strlen($newPassword) < 8) {
        $error = 'Password must be at least 8 characters.';
      } elseif ($newPassword !== $confirmPassword) {
        $error = 'Passwords do not match.';
      } else {
        try {
          $resetRow = find_reset_row($token);
          if (!$resetRow) {
            $error = 'This password reset link is invalid or expired.';
          } else {
            $hash = password_hash($newPassword, PASSWORD_DEFAULT);

            db()->beginTransaction();
            $upd = db()->prepare('UPDATE authors SET password_hash = ? WHERE id = ?');
            $upd->execute([$hash, (int)$resetRow['author_id']]);

            $used = db()->prepare('UPDATE password_resets SET used_at = NOW() WHERE id = ?');
            $used->execute([(int)$resetRow['id']]);
            db()->commit();

            $mode = 'success';
            $success = 'Your password has been reset. You can now log in.';
          }
        } catch (Throwable $e) {
          if (db()->inTransaction()) {
            db()->rollBack();
          }
          $error = 'Reset failed. Please try again.';
        }
      }
    }
} else {
    $resetRow = find_reset_row($token);
    if (!$resetRow) {
        $mode = 'invalid';
        $error = 'This password reset link is invalid or expired.';
    }
}

$pageTitle = 'Reset Password';
$bodyClass = 'bg-light';
$hideNav = true;
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container" style="max-width: 520px; padding-top: 4rem; padding-bottom: 4rem;">
  <div class="card shadow-sm border-0 rounded-4">
    <div class="card-body p-4 p-md-5">
      <div class="text-center mb-3">
        <i class="fa-solid fa-key" style="font-size: 36px; color: #16a34a;"></i>
        <h1 class="h4 fw-bold mt-2 mb-1">Reset Password</h1>
        <div class="text-muted small">UCC – RDC ISSN Application Portal</div>
      </div>

      <?php if ($error !== ''): ?>
        <div class="alert alert-danger" role="alert"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <?php if ($mode === 'success'): ?>
        <div class="alert alert-success" role="alert"><?= htmlspecialchars($success) ?></div>
        <div class="d-grid gap-2">
          <a class="btn btn-success" href="/uccrdc/index.php">Go to Login</a>
        </div>

      <?php elseif ($mode === 'form'): ?>
        <form method="post" action="">
          <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
          <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

          <div class="mb-3">
            <label class="form-label">New password</label>
            <div class="input-group">
              <input class="form-control" type="password" id="resetPassword" name="password" autocomplete="new-password" required>
              <button class="btn btn-outline-secondary js-toggle-password" type="button" data-target="#resetPassword" aria-label="Show password">
                <i class="fa-regular fa-eye"></i>
              </button>
            </div>
            <div class="form-text">Minimum 8 characters.</div>
          </div>

          <div class="mb-3">
            <label class="form-label">Confirm password</label>
            <div class="input-group">
              <input class="form-control" type="password" id="resetConfirmPassword" name="confirm_password" autocomplete="new-password" required>
              <button class="btn btn-outline-secondary js-toggle-password" type="button" data-target="#resetConfirmPassword" aria-label="Show password">
                <i class="fa-regular fa-eye"></i>
              </button>
            </div>
          </div>

          <div class="d-grid gap-2">
            <button class="btn btn-success" type="submit">Set new password</button>
            <a class="btn btn-outline-secondary" href="/uccrdc/index.php">Back to portal</a>
          </div>
        </form>
      <?php else: ?>
        <div class="d-grid gap-2">
          <a class="btn btn-success" href="/uccrdc/index.php">Back to portal</a>
        </div>
      <?php endif; ?>

    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php';
