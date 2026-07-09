<?php
/**
 * Shared includes for dashboard layout
 * Usage: include APP_ROOT . '/includes/layout.php';
 *
 * Required globals before include:
 *   $pageTitle  - string
 *   $activeMenu - string (e.g. 'dashboard', 'appointments')
 */

// ── Sidebar navigation per role ────────────────────────────────
function getSidebarNav(string $role): array {
    $adminNav = [
        ['section' => 'Main'],
        ['href' => '/admin/dashboard.php',       'icon' => 'bi-speedometer2',    'label' => 'Dashboard',     'key' => 'dashboard'],
        ['section' => 'Hospital'],
        ['href' => '/admin/manage_doctors.php',  'icon' => 'bi-person-badge',    'label' => 'Doctors',       'key' => 'doctors'],
        ['href' => '/admin/manage_patients.php', 'icon' => 'bi-people',          'label' => 'Patients',      'key' => 'patients'],
        ['href' => '/admin/departments.php',     'icon' => 'bi-building',        'label' => 'Departments',   'key' => 'departments'],
        ['section' => 'Operations'],
        ['href' => '/admin/appointments.php',    'icon' => 'bi-calendar-check',  'label' => 'Appointments',  'key' => 'appointments'],
        ['href' => '/admin/prescriptions.php',   'icon' => 'bi-file-medical',    'label' => 'Prescriptions', 'key' => 'prescriptions'],
        ['href' => '/admin/billing.php',         'icon' => 'bi-receipt',         'label' => 'Billing',       'key' => 'billing'],
        ['section' => 'Analytics'],
        ['href' => '/admin/reports.php',         'icon' => 'bi-bar-chart-line',  'label' => 'Reports',         'key' => 'reports'],
        ['section' => 'Settings'],
        ['href' => '/admin/email_settings.php',  'icon' => 'bi-envelope-gear',   'label' => 'Email Settings',  'key' => 'email_settings'],
        ['href' => '/notifications.php',         'icon' => 'bi-bell',            'label' => 'Notifications',   'key' => 'notifications'],
        ['section' => 'Account'],
        ['href' => '/admin/profile.php',         'icon' => 'bi-person-circle',   'label' => 'Profile',         'key' => 'profile'],
        ['href' => '/logout.php',                'icon' => 'bi-box-arrow-right', 'label' => 'Logout',          'key' => 'logout'],
    ];
    $doctorNav = [
        ['section' => 'Main'],
        ['href' => '/doctor/dashboard.php',      'icon' => 'bi-speedometer2',    'label' => 'Dashboard',     'key' => 'dashboard'],
        ['section' => 'Clinical'],
        ['href' => '/doctor/appointments.php',   'icon' => 'bi-calendar-check',  'label' => 'Appointments',  'key' => 'appointments'],
        ['href' => '/doctor/prescriptions.php',  'icon' => 'bi-file-medical',    'label' => 'Prescriptions', 'key' => 'prescriptions'],
        ['href' => '/doctor/patients.php',       'icon' => 'bi-people',          'label' => 'My Patients',   'key' => 'patients'],
        ['section' => 'Account'],
        ['href' => '/notifications.php',         'icon' => 'bi-bell',            'label' => 'Notifications',   'key' => 'notifications'],
        ['href' => '/doctor/profile.php',        'icon' => 'bi-person-circle',   'label' => 'Profile',       'key' => 'profile'],
        ['href' => '/logout.php',                'icon' => 'bi-box-arrow-right', 'label' => 'Logout',        'key' => 'logout'],
    ];
    $patientNav = [
        ['section' => 'Main'],
        ['href' => '/patient/dashboard.php',         'icon' => 'bi-speedometer2',  'label' => 'Dashboard',       'key' => 'dashboard'],
        ['section' => 'My Health'],
        ['href' => '/patient/book_appointment.php',  'icon' => 'bi-calendar-plus', 'label' => 'Book Appointment', 'key' => 'book'],
        ['href' => '/patient/appointments.php',      'icon' => 'bi-calendar-check','label' => 'My Appointments',  'key' => 'appointments'],
        ['href' => '/patient/prescriptions.php',     'icon' => 'bi-file-medical',  'label' => 'Prescriptions',    'key' => 'prescriptions'],
        ['href' => '/patient/medical_records.php',   'icon' => 'bi-folder-heart',  'label' => 'Medical Records',  'key' => 'records'],
        ['href' => '/patient/billing.php',           'icon' => 'bi-receipt',       'label' => 'Bills',            'key' => 'billing'],
        ['section' => 'Account'],
        ['href' => '/notifications.php',             'icon' => 'bi-bell',          'label' => 'Notifications',    'key' => 'notifications'],
        ['href' => '/patient/profile.php',           'icon' => 'bi-person-circle', 'label' => 'Profile',          'key' => 'profile'],
        ['href' => '/logout.php',                    'icon' => 'bi-box-arrow-right','label' => 'Logout',          'key' => 'logout'],
    ];
    return match($role) {
        'admin'  => $adminNav,
        'doctor' => $doctorNav,
        default  => $patientNav,
    };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($pageTitle ?? 'Dashboard') ?> — Smart Hospital</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="<?= APP_URL ?>/assets/css/style.css" rel="stylesheet">
</head>
<body>

<!-- Page Loader -->
<div id="page-loader">
  <div class="loader-logo"><i class="bi bi-heart-pulse-fill"></i></div>
  <div class="loader-text">Smart Hospital Management System</div>
  <div class="loader-bar"><div class="loader-bar-fill"></div></div>
</div>

<!-- Sidebar Overlay (mobile) -->
<div class="sidebar-overlay"></div>

<!-- Sidebar -->
<aside class="sidebar">
  <a href="<?= APP_URL ?>" class="sidebar-logo">
    <div class="sidebar-logo-icon"><i class="bi bi-heart-pulse-fill"></i></div>
    <div>
      <div class="sidebar-logo-text">Smart Hospital</div>
      <div class="sidebar-logo-sub">Management System</div>
    </div>
  </a>
  <nav class="sidebar-nav">
    <?php foreach(getSidebarNav($_SESSION['role']) as $item): ?>
      <?php if (isset($item['section'])): ?>
        <div class="nav-section-label"><?= e($item['section']) ?></div>
      <?php else: ?>
        <a href="<?= APP_URL . $item['href'] ?>"
           class="nav-link <?= ($activeMenu ?? '') === $item['key'] ? 'active' : '' ?>">
          <i class="bi <?= e($item['icon']) ?>"></i>
          <?= e($item['label']) ?>
        </a>
      <?php endif; ?>
    <?php endforeach; ?>
  </nav>
</aside>

<!-- Main Content -->
<div class="main-content">
  <!-- Top Bar -->
  <header class="topbar">
    <button class="sidebar-toggle"><i class="bi bi-list"></i></button>
    <div class="topbar-title"><?= e($pageTitle ?? 'Dashboard') ?></div>

    <!-- Live Search -->
    <div class="topbar-search d-none d-md-block">
      <i class="bi bi-search"></i>
      <input type="search" id="global-search" placeholder="Search patients, doctors…">
    </div>

    <!-- Notifications -->
    <div class="dropdown">
      <button class="topbar-icon-btn" data-bs-toggle="dropdown" onclick="loadNotifications()">
        <i class="bi bi-bell"></i>
        <span class="notif-dot"></span>
      </button>
      <div class="dropdown-menu dropdown-menu-end shadow-lg border-0" style="width:340px;border-radius:16px;padding:0;overflow:hidden">
        <div style="padding:14px 16px;border-bottom:1px solid #F1F5F9;font-weight:700;font-size:.9rem">Notifications</div>
        <div id="notif-list" style="max-height:320px;overflow-y:auto">
          <div style="padding:20px;text-align:center;color:#94A3B8">Loading…</div>
        </div>
        <div style="padding:10px 16px;border-top:1px solid #F1F5F9;text-align:center">
          <a href="<?= APP_URL ?>/notifications.php" style="font-size:.82rem;color:var(--primary);font-weight:600;text-decoration:none">View All</a>
        </div>
      </div>
    </div>

    <!-- User Dropdown -->
    <div class="dropdown">
      <div class="topbar-avatar" data-bs-toggle="dropdown" title="<?= e($_SESSION['name'] ?? '') ?>">
        <?= strtoupper(substr($_SESSION['name'] ?? 'U', 0, 2)) ?>
      </div>
      <ul class="dropdown-menu dropdown-menu-end shadow border-0" style="border-radius:12px;min-width:200px">
        <li><div style="padding:12px 16px;border-bottom:1px solid #F1F5F9">
          <div style="font-weight:700;font-size:.9rem"><?= e($_SESSION['name'] ?? '') ?></div>
          <div style="font-size:.78rem;color:#64748B;text-transform:capitalize"><?= e($_SESSION['role'] ?? '') ?></div>
        </div></li>
        <li><a class="dropdown-item" href="<?= APP_URL ?>/<?= $_SESSION['role'] ?>/profile.php"><i class="bi bi-person me-2"></i>Profile</a></li>
        <li><hr class="dropdown-divider"></li>
        <li><a class="dropdown-item text-danger" href="<?= APP_URL ?>/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
      </ul>
    </div>
  </header>

  <!-- Page Body -->
  <div class="page-content">
