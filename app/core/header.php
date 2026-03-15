<?php
// =============================================================
// app/core/header.php — App shell: <head>, sidebar, top header
// Usage: Set $pageTitle / $pageSubtitle, then require this file
// =============================================================

$__user = Auth::currentUser();
if (!$__user) { Utils::redirect('public/auth/login.php'); }

$__totalNotices = count(
    Auth::role() === 'Admin'
        ? NoticeHelper::getAllNotices()
        : NoticeHelper::getVisibleNotices(Auth::id(), Auth::role())
);
$__currentFile = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= Utils::sanitize($pageTitle ?? 'Dashboard') ?> — <?= SITE_NAME ?></title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500;600&family=DM+Mono:wght@400;500&display=swap">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
</head>
<body>
<div class="app-layout">

<!-- ═══════════════════════════════════════════════════ SIDEBAR -->
<nav class="sidebar" id="sidebar">

  <div class="sidebar-logo">
    <div class="logo-mark"><i class="fa-solid fa-bell-school"></i></div>
    <div>
      <div class="logo-name"><?= SITE_NAME ?></div>
      <div class="logo-school"><?= SITE_SCHOOL ?></div>
    </div>
  </div>

  <div class="sidebar-user">
    <div class="user-avatar"><?= Utils::initials($__user['fullName']) ?></div>
    <div class="user-info">
      <div class="user-name"><?= Utils::sanitize($__user['fullName']) ?></div>
      <div class="user-role"><?= $__user['role'] ?></div>
    </div>
  </div>

  <div class="sidebar-nav">

    <?php
    // Helper: render a sidebar link
    $navLink = function(string $href, string $icon, string $label) use ($__currentFile): void {
        $file   = basename(parse_url($href, PHP_URL_PATH));
        $active = ($file === $__currentFile) ? 'active' : '';
        echo "<a href=\"{$href}\" class=\"sidebar-link {$active}\">
                <i class=\"fa-solid {$icon}\"></i> {$label}
              </a>";
    };
    ?>

    <div class="sidebar-section-label">Main</div>
    <?php $navLink(BASE_URL . 'public/dashboard.php',         'fa-grid-2',         'Dashboard'); ?>
    <?php $navLink(BASE_URL . 'public/notices/index.php',     'fa-newspaper',      'All Notices'); ?>

    <?php if (in_array(Auth::role(), ['Admin', 'Teacher'])): ?>
      <div class="sidebar-section-label" style="margin-top:10px;">Notices</div>
      <?php $navLink(BASE_URL . 'public/notices/create.php',  'fa-circle-plus',    'New Notice'); ?>
      <?php $navLink(BASE_URL . 'public/notices/manage.php',  'fa-file-lines',     'Manage Notices'); ?>
    <?php endif; ?>

    <?php if (Auth::role() === 'Admin'): ?>
      <div class="sidebar-section-label" style="margin-top:10px;">Admin</div>
      <?php $navLink(BASE_URL . 'public/admin/users.php',      'fa-users',         'Users'); ?>
      <?php $navLink(BASE_URL . 'public/admin/categories.php', 'fa-tags',          'Categories'); ?>
    <?php endif; ?>

    <div class="sidebar-section-label" style="margin-top:10px;">Account</div>
    <?php $navLink(BASE_URL . 'public/auth/profile.php', 'fa-circle-user', 'My Profile'); ?>

  </div><!-- /.sidebar-nav -->

  <div class="sidebar-bottom">
    <a href="<?= BASE_URL ?>public/auth/logout.php" class="sidebar-link" style="color:rgba(255,255,255,.5);">
      <i class="fa-solid fa-arrow-right-from-bracket"></i> Sign Out
    </a>
  </div>

</nav><!-- /.sidebar -->

<!-- ════════════════════════════════════════════════ TOP HEADER -->
<header class="app-header">

  <button class="header-btn" id="sidebarToggle"
          onclick="document.getElementById('sidebar').classList.toggle('open')"
          style="display:none;">
    <i class="fa-solid fa-bars"></i>
  </button>

  <div class="header-title">
    <?= Utils::sanitize($pageTitle ?? 'Dashboard') ?>
    <?php if (!empty($pageSubtitle)): ?>
      <small><?= Utils::sanitize($pageSubtitle) ?></small>
    <?php endif; ?>
  </div>

  <div class="header-actions">
    <form method="GET" action="<?= BASE_URL ?>public/notices/index.php" class="header-search">
      <i class="fa-solid fa-magnifying-glass search-icon"></i>
      <input type="text" name="search" placeholder="Search notices…"
             value="<?= Utils::sanitize($_GET['search'] ?? '') ?>">
    </form>

    <a href="<?= BASE_URL ?>public/notices/index.php" class="header-btn" data-tip="All Notices">
      <i class="fa-solid fa-bell"></i>
      <span class="badge"><?= $__totalNotices ?></span>
    </a>

    <a href="<?= BASE_URL ?>public/auth/profile.php" class="header-btn" data-tip="My Profile">
      <i class="fa-solid fa-circle-user"></i>
    </a>

    <a href="<?= BASE_URL ?>public/auth/logout.php" class="header-btn" data-tip="Sign Out">
      <i class="fa-solid fa-arrow-right-from-bracket"></i>
    </a>
  </div>

</header><!-- /.app-header -->

<!-- MAIN CONTENT AREA OPENS -->
<main class="app-main">
