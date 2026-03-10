<?php
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/auth.php';
start_session();
$user = current_user();

// Optional layout knobs
$pageTitle = $pageTitle ?? 'UCC – RDC ISSN Application Portal';
$bodyClass = $bodyClass ?? 'bg-light';
$hideNav = (bool)($hideNav ?? false);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($pageTitle) ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet"/>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
  <?php if (!empty($inlineStyles)): ?>
    <style>
<?= $inlineStyles ?>
    </style>
  <?php endif; ?>
</head>
<body class="<?= htmlspecialchars($bodyClass) ?>">
<?php if (!$hideNav): ?>
  <nav class="navbar navbar-expand-lg bg-white border-bottom">
    <div class="container">
      <a class="navbar-brand fw-semibold" href="/uccrdc/index.php">UCC – RDC</a>
      <div class="d-flex gap-2 align-items-center">
        <?php if ($user): ?>
          <span class="text-muted small">Signed in as <?= htmlspecialchars($user['email']) ?></span>
          <a class="btn btn-outline-secondary btn-sm" href="/uccrdc/logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
        <?php endif; ?>
      </div>
    </div>
  </nav>
<?php endif; ?>
