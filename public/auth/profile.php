<?php
// public/auth/profile.php
require_once __DIR__ . '/../../app/config/config.php';
Auth::requireLogin();

$user   = Auth::currentUser();
$uid    = Auth::id();
$errors = [];
$tab    = Utils::get('tab', 'profile');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = Utils::post('action');

    if ($action === 'update_profile') {
        $fullName = Utils::post('fullName');
        $email    = Utils::post('email');
        if (!$fullName) $errors[] = 'Full name is required.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required.';
        $dup = Database::fetchOne('SELECT userID FROM users WHERE email = ? AND userID != ?', 'si', $email, $uid);
        if ($dup) $errors[] = 'That email is already in use by another account.';

        if (empty($errors)) {
            Database::query('UPDATE users SET fullName = ?, email = ? WHERE userID = ?', 'ssi', $fullName, $email, $uid);
            $_SESSION['fullName'] = $fullName;
            $_SESSION['email']    = $email;
            Utils::flash('success', 'Profile updated successfully.');
            Utils::redirect('public/auth/profile.php?tab=profile');
        }
    }

    elseif ($action === 'change_password') {
        $current = Utils::post('current_password');
        $new     = Utils::post('new_password');
        $confirm = Utils::post('confirm_password');
        if (!$current)          $errors[] = 'Current password is required.';
        if (strlen($new) < 8)   $errors[] = 'New password must be at least 8 characters.';
        if ($new !== $confirm)   $errors[] = 'Passwords do not match.';

        if (empty($errors)) {
            if (Auth::hashPassword($current, $user['salt']) !== $user['password']) {
                $errors[] = 'Current password is incorrect.';
            } else {
                $salt = Auth::generateSalt();
                Database::query(
                    'UPDATE users SET password = ?, salt = ?, lastPasswordChangedAt = NOW() WHERE userID = ?',
                    'ssi', Auth::hashPassword($new, $salt), $salt, $uid
                );
                Utils::flash('success', 'Password changed successfully.');
                Utils::redirect('public/auth/profile.php?tab=security');
            }
        }
        $tab = 'security';
    }
}

$myNoticeCount = Database::fetchOne('SELECT COUNT(*) AS c FROM notices WHERE createdBy = ? AND deletedAt IS NULL', 'i', $uid)['c'] ?? 0;
$totalViews    = Database::fetchOne('SELECT COALESCE(SUM(viewCount),0) AS v FROM notices WHERE createdBy = ? AND deletedAt IS NULL', 'i', $uid)['v'] ?? 0;

$pageTitle = 'My Profile'; $pageSubtitle = 'Account settings';
require_once ROOT_PATH . 'app/core/header.php';
?>

<div class="page-header fade-up">
  <div class="page-header-left">
    <h1>My Profile</h1>
    <div class="breadcrumb"><a href="<?= BASE_URL ?>public/dashboard.php">Dashboard</a><i class="fa-solid fa-chevron-right" style="font-size:9px"></i>Profile</div>
  </div>
</div>

<?php $f = Utils::flash('success'); if ($f): ?><div class="alert alert-success fade-up"><i class="fa-solid fa-circle-check"></i><?= Utils::sanitize($f) ?></div><?php endif; ?>
<?php if ($errors): ?><div class="alert alert-error fade-up"><i class="fa-solid fa-circle-exclamation"></i><div><?php foreach ($errors as $e) echo '<div>'.Utils::sanitize($e).'</div>'; ?></div></div><?php endif; ?>

<div style="display:grid;grid-template-columns:280px 1fr;gap:24px;align-items:start;" class="fade-up">
  <!-- Profile card -->
  <div>
    <div class="card">
      <div style="background:var(--navy);padding:32px 24px;text-align:center;">
        <div style="width:72px;height:72px;border-radius:50%;background:var(--amber);color:var(--navy);display:flex;align-items:center;justify-content:center;font-size:28px;font-weight:700;margin:0 auto 14px;"><?= Utils::initials($user['fullName']) ?></div>
        <div style="font-family:var(--font-serif);font-size:18px;color:var(--white);margin-bottom:4px;"><?= Utils::sanitize($user['fullName']) ?></div>
        <div style="font-size:12px;color:rgba(255,255,255,.5);"><?= Utils::sanitize($user['email']) ?></div>
        <div style="margin-top:10px;"><span class="tag" style="background:rgba(232,168,49,.2);color:var(--amber);"><?= $user['role'] ?></span></div>
      </div>
      <div style="padding:20px;">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;text-align:center;">
          <div style="background:var(--ivory);border-radius:var(--r-sm);padding:14px;">
            <div style="font-family:var(--font-serif);font-size:24px;color:var(--navy);"><?= $myNoticeCount ?></div>
            <div style="font-size:11px;color:var(--text-muted);margin-top:2px;">Notices</div>
          </div>
          <div style="background:var(--ivory);border-radius:var(--r-sm);padding:14px;">
            <div style="font-family:var(--font-serif);font-size:24px;color:var(--navy);"><?= $totalViews ?></div>
            <div style="font-size:11px;color:var(--text-muted);margin-top:2px;">Views</div>
          </div>
        </div>
        <div style="margin-top:16px;font-size:12px;color:var(--text-muted);">
          <div style="display:flex;justify-content:space-between;padding:7px 0;border-bottom:1px solid var(--border-lt);"><span>Member since</span><strong><?= date('d M Y', strtotime($user['createdAt'])) ?></strong></div>
          <?php if ($user['lastLoginAt']): ?><div style="display:flex;justify-content:space-between;padding:7px 0;border-bottom:1px solid var(--border-lt);"><span>Last login</span><strong><?= Utils::timeAgo($user['lastLoginAt']) ?></strong></div><?php endif; ?>
          <?php if ($user['lastPasswordChangedAt']): ?><div style="display:flex;justify-content:space-between;padding:7px 0;"><span>Password changed</span><strong><?= date('d M Y', strtotime($user['lastPasswordChangedAt'])) ?></strong></div><?php endif; ?>
        </div>
      </div>
    </div>
    <div class="card" style="margin-top:16px;padding:8px;">
      <a href="?tab=profile"   class="sidebar-link <?= $tab==='profile'  ?'active':'' ?>" style="border-radius:var(--r-sm);"><i class="fa-solid fa-circle-user"></i> Edit Profile</a>
      <a href="?tab=security"  class="sidebar-link <?= $tab==='security' ?'active':'' ?>" style="border-radius:var(--r-sm);"><i class="fa-solid fa-lock"></i> Change Password</a>
    </div>
  </div>

  <!-- Forms -->
  <div>
    <?php if ($tab === 'profile'): ?>
    <div class="card">
      <div class="card-header"><h2><i class="fa-solid fa-pen" style="color:var(--amber);margin-right:8px;"></i>Edit Profile</h2></div>
      <form method="POST">
        <input type="hidden" name="action" value="update_profile">
        <div class="card-body">
          <div class="form-row">
            <div class="form-group"><label class="form-label">Full Name *</label><div class="input-group"><i class="fa-solid fa-user input-icon"></i><input type="text" name="fullName" class="form-control" value="<?= Utils::sanitize($user['fullName']) ?>" required></div></div>
            <div class="form-group"><label class="form-label">Email *</label><div class="input-group"><i class="fa-solid fa-envelope input-icon"></i><input type="email" name="email" class="form-control" value="<?= Utils::sanitize($user['email']) ?>" required></div></div>
          </div>
          <div class="form-group"><label class="form-label">Role</label><input type="text" class="form-control" value="<?= $user['role'] ?>" disabled style="background:var(--ivory);color:var(--text-muted);cursor:not-allowed;"><div style="font-size:12px;color:var(--text-light);margin-top:5px;">Contact an administrator to change your role.</div></div>
        </div>
        <div class="card-footer"><button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Save Changes</button></div>
      </form>
    </div>

    <?php elseif ($tab === 'security'): ?>
    <div class="card">
      <div class="card-header"><h2><i class="fa-solid fa-lock" style="color:var(--amber);margin-right:8px;"></i>Change Password</h2></div>
      <form method="POST">
        <input type="hidden" name="action" value="change_password">
        <div class="card-body">
          <div class="form-group"><label class="form-label">Current Password *</label><div class="input-group"><i class="fa-solid fa-lock input-icon"></i><input type="password" name="current_password" id="cp0" class="form-control" required><button type="button" class="input-eye" data-toggle-pw="cp0"><i class="fa-regular fa-eye"></i></button></div></div>
          <div class="form-group"><label class="form-label">New Password *</label><div class="input-group"><i class="fa-solid fa-lock input-icon"></i><input type="password" name="new_password" id="np1" class="form-control" required minlength="8"><button type="button" class="input-eye" data-toggle-pw="np1"><i class="fa-regular fa-eye"></i></button></div></div>
          <div class="form-group"><label class="form-label">Confirm New Password *</label><div class="input-group"><i class="fa-solid fa-lock input-icon"></i><input type="password" name="confirm_password" id="cp2" class="form-control" required minlength="8"><button type="button" class="input-eye" data-toggle-pw="cp2"><i class="fa-regular fa-eye"></i></button></div></div>
          <div class="alert alert-info" style="margin-top:0;"><i class="fa-solid fa-circle-info"></i>Password must be at least 8 characters long.</div>
        </div>
        <div class="card-footer"><button type="submit" class="btn btn-primary"><i class="fa-solid fa-key"></i> Update Password</button></div>
      </form>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php require_once ROOT_PATH . 'app/core/footer.php'; ?>
