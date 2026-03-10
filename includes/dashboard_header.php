<?php
// Sidebar dashboard layout header
// Usage: require auth + require_login + require_role in the page, then include this file.

$bodyClass = $bodyClass ?? 'dashboard';
$hideNav = true;
$inlineStyles = <<<'CSS'
/* ===== Dashboard sidebar layout (scoped) ===== */
body.dashboard{
  background: #f8f9fa;
  font-family: "Segoe UI", system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
  transition: background 0.3s;
  overflow-x: hidden;
}

body.dashboard .sidebar{
  width: 300px;
  background-color: #0D4715;
  min-width: 220px;
  box-shadow: 4px 0 12px rgba(0,0,0,0.15);
  height: 100vh;
  position: fixed;
  top: 0;
  left: 0;
  z-index: 1040;
  display: flex;
  flex-direction: column;
  padding: 1.5rem 1rem;
  transition: transform 0.3s ease;
  overflow-y: auto;
  overflow-x: hidden;
}

body.dashboard .sidebar.mobile-hidden{
  transform: translateX(-100%);
}

body.dashboard .sidebar-header{
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  color: #fff;
  text-decoration: none;
  padding-bottom: 1.5rem;
  border-bottom: 1px solid rgba(255,255,255,0.2);
  margin-bottom: 1rem;
}

body.dashboard .sidebar-header h4{
  margin: 0.5rem 0 0 0;
  font-size: 1.1rem;
  font-weight: 800;
  letter-spacing: .04em;
}

body.dashboard .nav-section-title{
  font-size: 0.85rem;
  color: rgba(255,255,255,0.8);
  margin: 0 0.5rem 1rem;
  font-weight: 700;
  letter-spacing: 1px;
  text-transform: uppercase;
}

body.dashboard .sidebar-menu{
  list-style: none;
  padding: 0;
  margin: 0;
  flex-grow: 1;
}

body.dashboard .sidebar-menu li{ margin-bottom: 0.2rem; }

body.dashboard .sidebar-menu a{
  font-weight: 600;
  color: #fff;
  padding: 0.6rem 1rem;
  border-radius: 6px;
  display: flex;
  align-items: center;
  gap: 0.7rem;
  text-decoration: none;
  transition: all 0.2s;
}

body.dashboard .sidebar-menu a.active,
body.dashboard .sidebar-menu a:hover{
  background: #198754;
  color: #fff !important;
}

body.dashboard .sidebar-menu a i{ width: 20px; }

body.dashboard .sidebar-submenu{
  list-style: none;
  padding-left: 1.5rem;
  margin-top: 0.25rem;
  display: none;
}

body.dashboard .sidebar-submenu.show{ display: block; }
body.dashboard .sidebar-submenu a{ font-size: 0.92rem; padding: 0.45rem 1rem; }

body.dashboard .sidebar-footer{
  margin-top: auto;
  padding-top: 1rem;
  border-top: 1px solid rgba(255,255,255,0.2);
  border-radius: 8px;
}

body.dashboard .user-card{
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 1rem;
}

body.dashboard .user-avatar{
  width: 45px;
  height: 45px;
  background-color: #198754;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 800;
  font-size: 14px;
  color: #fff;
}

body.dashboard .user-info{ flex: 1; color: #fff; }
body.dashboard .user-info small{ color: rgba(255,255,255,0.7); }

body.dashboard .content-wrapper{
  margin-left: 280px;
  min-height: 100vh;
  padding-left: 10px;
}

body.dashboard .content-wrapper{
  transition: margin-left 0.3s ease;
}

body.dashboard .dashboard-hero{
  background: linear-gradient(135deg, rgba(34,197,94,0.15), rgba(14,116,144,0.08));
  border: 1px solid rgba(148,163,184,0.3);
  border-radius: 16px;
  padding: 18px 20px;
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

body.dashboard .stat-card{
  border-radius: 16px;
  box-shadow: 0 10px 30px rgba(15,23,42,0.08);
  position: relative;
  overflow: hidden;
}

body.dashboard .stat-card .card-body{
  padding: 18px 20px;
}

body.dashboard .stat-card .stat-icon{
  width: 44px;
  height: 44px;
  border-radius: 12px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 8px;
  font-size: 18px;
}

body.dashboard .stat-total .stat-icon{ background: rgba(59,130,246,0.15); color: #2563eb; }
body.dashboard .stat-pending .stat-icon{ background: rgba(234,179,8,0.18); color: #ca8a04; }
body.dashboard .stat-approved .stat-icon{ background: rgba(34,197,94,0.18); color: #16a34a; }
body.dashboard .stat-rejected .stat-icon{ background: rgba(239,68,68,0.18); color: #dc2626; }

body.dashboard .stat-total{ background: linear-gradient(160deg, #fff, rgba(59,130,246,0.08)); }
body.dashboard .stat-pending{ background: linear-gradient(160deg, #fff, rgba(234,179,8,0.08)); }
body.dashboard .stat-approved{ background: linear-gradient(160deg, #fff, rgba(34,197,94,0.08)); }
body.dashboard .stat-rejected{ background: linear-gradient(160deg, #fff, rgba(239,68,68,0.08)); }

body.dashboard #sidebarToggle{
  display: none;
}

@media (max-width: 991.98px){
  body.dashboard #sidebarToggle{
    display: block;
    position: fixed;
    top: 1rem;
    left: 1rem;
    z-index: 2100;
    background: #fff;
    border: none;
    border-radius: 50%;
    width: 44px;
    height: 44px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    color: #2C3E50;
    font-size: 1.2rem;
    cursor: pointer;
  }
  body.dashboard .sidebar{ transform: translateX(-100%); }
  body.dashboard .sidebar.show{ transform: translateX(0); }
  body.dashboard .content-wrapper{ margin-left: 0; }
}

body.dashboard .sidebar-overlay{
  position: fixed;
  inset: 0;
  background: rgba(2,6,23,0.35);
  z-index: 1039;
}
CSS;
require_once __DIR__ . '/header.php';

$currentPage = basename($_SERVER['PHP_SELF'] ?? '');
$fullName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
if ($fullName === '') {
    $fullName = $user['email'] ?? 'User';
}
$role = $user['role'] ?? 'Author';

function sidebar_active_files(array $files, string $currentPage): string {
  return in_array($currentPage, $files, true) ? 'active' : '';
}

function sidebar_any_active(array $files, string $currentPage): bool {
    return in_array($currentPage, $files, true);
}

$menu = [];
if ($role === 'Admin') {
    $menu = [
    ['label' => 'Dashboard', 'href' => '/uccrdc/admin/dashboard.php', 'files' => ['dashboard.php'], 'icon' => 'fa-solid fa-house'],
    ['label' => 'Applications', 'href' => '/uccrdc/admin/applications.php', 'files' => ['applications.php','view_submission.php'], 'icon' => 'fa-solid fa-folder-open'],
    ['type' => 'submenu', 'label' => 'Settings', 'icon' => 'fa-solid fa-gear', 'id' => 'settingsMenu', 'files' => ['formats.php','users.php','colleges.php'], 'items' => [
      ['label' => 'Publication Formats', 'href' => '/uccrdc/admin/formats.php', 'files' => ['formats.php'], 'icon' => 'fa-solid fa-sliders'],
      ['label' => 'User Accounts', 'href' => '/uccrdc/admin/users.php', 'files' => ['users.php'], 'icon' => 'fa-solid fa-users'],
      ['label' => 'Colleges & Courses', 'href' => '/uccrdc/admin/colleges.php', 'files' => ['colleges.php'], 'icon' => 'fa-solid fa-school'],
        ]],
    ];
} elseif ($role === 'Staff') {
    $menu = [
    ['label' => 'Dashboard', 'href' => '/uccrdc/staff/dashboard.php', 'files' => ['dashboard.php','view_submission.php'], 'icon' => 'fa-solid fa-house'],
  ['label' => 'Account Settings', 'href' => '/uccrdc/account/settings.php', 'files' => ['settings.php'], 'icon' => 'fa-solid fa-user-gear'],
    ];
} else {
    $menu = [
    ['label' => 'Dashboard', 'href' => '/uccrdc/author/dashboard.php', 'files' => ['dashboard.php','view_submission.php'], 'icon' => 'fa-solid fa-house'],
    ['label' => 'New Submission', 'href' => '/uccrdc/author/new_submission.php', 'files' => ['new_submission.php'], 'icon' => 'fa-solid fa-file-circle-plus'],
  ['label' => 'Account Settings', 'href' => '/uccrdc/account/settings.php', 'files' => ['settings.php'], 'icon' => 'fa-solid fa-user-gear'],
    ];
}

$initials = strtoupper(substr(preg_replace('/\s+/', '', $fullName), 0, 2));
if ($initials === '') {
    $initials = 'U';
}
?>

<button id="sidebarToggle" type="button" aria-label="Toggle sidebar"><i class="fa-solid fa-bars"></i></button>

<aside class="sidebar" id="sidebar">
  <a class="sidebar-header" href="#" onclick="return false;">
    <img src="/uccrdc/ucc.png" alt="UCC" style="width: 96px; height: 96px; object-fit: contain;" onerror="this.style.display='none'">
    <h4>UCC – RDC ISSN PORTAL</h4>
  </a>

  <div class="nav-section-title">
    <?= htmlspecialchars($role) ?>
  </div>

  <nav class="nav flex-column" aria-label="Sidebar">
    <ul class="sidebar-menu">
      <?php foreach ($menu as $item): ?>
        <?php if (($item['type'] ?? '') === 'submenu'): ?>
          <?php
            $files = $item['files'] ?? [];
            $isActive = sidebar_any_active($files, $currentPage);
            $submenuId = $item['id'] ?? 'submenu';
          ?>
          <li>
            <a href="javascript:void(0)" class="<?= $isActive ? 'active' : '' ?>" data-submenu-toggle="<?= htmlspecialchars($submenuId) ?>">
              <i class="<?= htmlspecialchars($item['icon']) ?>"></i>
              <?= htmlspecialchars($item['label']) ?>
              <i class="fa-solid fa-chevron-down ms-auto"></i>
            </a>
            <ul class="sidebar-submenu <?= $isActive ? 'show' : '' ?>" id="<?= htmlspecialchars($submenuId) ?>">
              <?php foreach (($item['items'] ?? []) as $sub): ?>
                <li>
                  <a href="<?= htmlspecialchars($sub['href']) ?>" class="<?= sidebar_active_files($sub['files'] ?? [], $currentPage) ?>">
                    <i class="<?= htmlspecialchars($sub['icon']) ?>"></i>
                    <?= htmlspecialchars($sub['label']) ?>
                  </a>
                </li>
              <?php endforeach; ?>
            </ul>
          </li>
        <?php else: ?>
          <li>
            <a href="<?= htmlspecialchars($item['href']) ?>" class="<?= sidebar_active_files($item['files'] ?? [], $currentPage) ?>">
              <i class="<?= htmlspecialchars($item['icon']) ?>"></i>
              <?= htmlspecialchars($item['label']) ?>
            </a>
          </li>
        <?php endif; ?>
      <?php endforeach; ?>
    </ul>
  </nav>

  <div class="sidebar-footer">
    <div class="user-card">
      <div class="user-avatar"><?= htmlspecialchars($initials) ?></div>
      <div class="user-info">
        <div class="fw-semibold"><?= htmlspecialchars($fullName) ?></div>
        <small><?= htmlspecialchars($role) ?></small>
      </div>
    </div>
    <a href="/uccrdc/logout.php" class="btn btn-light w-100 mt-2">
      <i class="fa-solid fa-right-from-bracket me-2"></i>Logout
    </a>
  </div>
</aside>

<div class="content-wrapper">
<script>
  document.addEventListener('DOMContentLoaded', function () {
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebarToggle');
    if (!sidebar) return;

    function handleResize() {
      if (window.innerWidth <= 991.98) {
        if (sidebarToggle) sidebarToggle.style.display = 'block';
        sidebar.classList.add('mobile-hidden');
      } else {
        if (sidebarToggle) sidebarToggle.style.display = 'none';
        sidebar.classList.remove('mobile-hidden');
        sidebar.classList.remove('show');
        const overlay = document.querySelector('.sidebar-overlay');
        if (overlay) overlay.remove();
      }
    }

    function toggleSidebar() {
      sidebar.classList.toggle('show');
      const existing = document.querySelector('.sidebar-overlay');
      if (sidebar.classList.contains('show')) {
        if (!existing) {
          const overlay = document.createElement('div');
          overlay.className = 'sidebar-overlay';
          overlay.addEventListener('click', () => {
            sidebar.classList.remove('show');
            overlay.remove();
          });
          document.body.appendChild(overlay);
        }
      } else if (existing) {
        existing.remove();
      }
    }

    handleResize();
    window.addEventListener('resize', handleResize);
    if (sidebarToggle) sidebarToggle.addEventListener('click', toggleSidebar);
  });
</script>
