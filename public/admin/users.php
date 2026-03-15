<?php
// public/admin/users.php — Admin: User Management
require_once __DIR__ . '/../../app/config/config.php';
Auth::requireLogin();
Auth::requireRole(['Admin']);

$currentUID = Auth::id();
$errors     = [];
$action     = Utils::post('action');

// ── Handle POST actions ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Create new user
    if ($action === 'create_user') {
        $fullName = Utils::post('fullName');
        $email    = Utils::post('email');
        $role     = Utils::post('role');
        $password = Utils::post('password');

        if (!$fullName)  $errors[] = 'Full name is required.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email is required.';
        if (!in_array($role, ['Admin','Teacher','Student'], true)) $errors[] = 'Please select a valid role.';
        if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';

        if (empty($errors)) {
            $dup = Database::fetchOne('SELECT userID FROM users WHERE email = ?', 's', $email);
            if ($dup) {
                $errors[] = 'That email address is already registered.';
            } else {
                $salt = Auth::generateSalt();
                $hash = Auth::hashPassword($password, $salt);
                Database::query(
                    'INSERT INTO users (fullName, email, password, salt, role) VALUES (?,?,?,?,?)',
                    'sssss', $fullName, $email, $hash, $salt, $role
                );
                Utils::flash('success', "User \"{$fullName}\" created successfully.");
                Utils::redirect('public/admin/users.php');
            }
        }
    }

    // Toggle active/inactive
    elseif ($action === 'toggle_active') {
        $targetID = Utils::postInt('targetID');
        if ($targetID && $targetID !== $currentUID) {
            Database::query('UPDATE users SET active = 1 - active WHERE userID = ?', 'i', $targetID);
            Utils::flash('success', 'User status updated.');
        }
        Utils::redirect('public/admin/users.php');
    }

    // Reset failed login attempts
    elseif ($action === 'reset_attempts') {
        $targetID = Utils::postInt('targetID');
        if ($targetID) {
            Database::query('UPDATE users SET loginAttempts = 0 WHERE userID = ?', 'i', $targetID);
            Utils::flash('success', 'Login attempts reset. User can now sign in.');
        }
        Utils::redirect('public/admin/users.php');
    }
}

// ── Fetch users ─────────────────────────────────────────────────
$search  = Utils::get('search');
$roleF   = Utils::get('role');

$where  = [];
$types  = '';
$params = [];

if ($search) {
    $where[]  = '(fullName LIKE ? OR email LIKE ?)';
    $types   .= 'ss';
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}
if ($roleF && in_array($roleF, ['Admin','Teacher','Student'], true)) {
    $where[]  = 'role = ?';
    $types   .= 's';
    $params[] = $roleF;
}

$whereSQL  = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$usersList = Database::fetchAll(
    "SELECT * FROM users {$whereSQL} ORDER BY role, fullName",
    $types, ...$params
);

// Count per role
$roleCounts = [];
foreach (Database::fetchAll('SELECT role, COUNT(*) AS c FROM users GROUP BY role') as $r) {
    $roleCounts[$r['role']] = $r['c'];
}

$roleColors = ['Admin' => '#7755cc', 'Teacher' => '#4f8ef7', 'Student' => '#2e9e68'];
$roleIcons  = ['Admin' => 'fa-shield-halved', 'Teacher' => 'fa-chalkboard-user', 'Student' => 'fa-graduation-cap'];

$pageTitle    = 'Manage Users';
$pageSubtitle = 'User accounts & access control';
require_once ROOT_PATH . 'app/core/header.php';
?>

<div class="page-header fade-up">
  <div class="page-header-left">
    <h1>Manage Users</h1>
    <div class="breadcrumb">
      <a href="<?= BASE_URL ?>public/dashboard.php">Dashboard</a>
      <i class="fa-solid fa-chevron-right" style="font-size:9px"></i>
      Admin
      <i class="fa-solid fa-chevron-right" style="font-size:9px"></i>
      Users
    </div>
  </div>
  <button class="btn btn-amber"
          onclick="document.getElementById('createModal').classList.add('open')">
    <i class="fa-solid fa-user-plus"></i> Add User
  </button>
</div>

<?php $f = Utils::flash('success'); if ($f): ?>
  <div class="alert alert-success fade-up"><i class="fa-solid fa-circle-check"></i><?= Utils::sanitize($f) ?></div>
<?php endif; ?>
<?php if ($errors): ?>
  <div class="alert alert-error fade-up">
    <i class="fa-solid fa-circle-exclamation"></i>
    <div><?php foreach ($errors as $e) echo '<div>' . Utils::sanitize($e) . '</div>'; ?></div>
  </div>
<?php endif; ?>

<!-- Role stats -->
<div class="stats-row fade-up">
  <?php foreach (['Admin', 'Teacher', 'Student'] as $r): ?>
  <div class="stat-card">
    <div class="stat-icon" style="background:<?= $roleColors[$r] ?>22;">
      <i class="fa-solid <?= $roleIcons[$r] ?>" style="color:<?= $roleColors[$r] ?>"></i>
    </div>
    <div class="stat-body">
      <div class="stat-num"><?= $roleCounts[$r] ?? 0 ?></div>
      <div class="stat-label"><?= $r ?>s</div>
    </div>
  </div>
  <?php endforeach; ?>
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(13,27,42,.07);">
      <i class="fa-solid fa-users" style="color:var(--navy)"></i>
    </div>
    <div class="stat-body">
      <div class="stat-num"><?= array_sum($roleCounts) ?></div>
      <div class="stat-label">Total Users</div>
    </div>
  </div>
</div>

<!-- Filter -->
<form method="GET" class="filter-bar fade-up">
  <div class="filter-search-wrap" style="flex:1;">
    <i class="fa-solid fa-magnifying-glass fs-icon"></i>
    <input type="text" name="search" class="filter-search form-control"
           placeholder="Search by name or email…"
           value="<?= Utils::sanitize($search) ?>" style="padding-left:36px;">
  </div>
  <select name="role" class="filter-select">
    <option value="">All Roles</option>
    <option value="Admin"   <?= $roleF==='Admin'   ?'selected':''?>>Admin</option>
    <option value="Teacher" <?= $roleF==='Teacher' ?'selected':''?>>Teacher</option>
    <option value="Student" <?= $roleF==='Student' ?'selected':''?>>Student</option>
  </select>
  <button type="submit" class="btn btn-primary btn-sm">Search</button>
  <?php if ($search || $roleF): ?><a href="users.php" class="btn btn-outline btn-sm">Clear</a><?php endif; ?>
</form>

<!-- Table -->
<?php if (empty($usersList)): ?>
  <div class="empty-state fade-up">
    <i class="fa-solid fa-users-slash"></i>
    <h3>No Users Found</h3>
    <p>No users match your search criteria.</p>
  </div>
<?php else: ?>
<div class="table-wrap fade-up">
  <table class="data-table">
    <thead>
      <tr>
        <th style="width:48px;">#</th>
        <th>Name</th>
        <th>Email</th>
        <th>Role</th>
        <th>Status</th>
        <th>Login Attempts</th>
        <th>Last Login</th>
        <th>Joined</th>
        <th style="width:130px;">Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($usersList as $u): ?>
      <tr>
        <td style="color:var(--text-light);font-size:12px;font-family:var(--font-mono);"><?= $u['userID'] ?></td>

        <td>
          <div style="display:flex;align-items:center;gap:10px;">
            <div style="width:32px;height:32px;border-radius:50%;background:<?= $roleColors[$u['role']] ?? 'var(--navy)' ?>;
                        color:white;display:flex;align-items:center;justify-content:center;
                        font-size:13px;font-weight:700;flex-shrink:0;">
              <?= Utils::initials($u['fullName']) ?>
            </div>
            <div>
              <div style="font-weight:500;font-size:13.5px;"><?= Utils::sanitize($u['fullName']) ?></div>
              <?php if ($u['userID'] == $currentUID): ?>
                <div style="font-size:11px;color:var(--amber-dk);font-weight:600;">You</div>
              <?php endif; ?>
            </div>
          </div>
        </td>

        <td style="font-size:13px;color:var(--text-muted);"><?= Utils::sanitize($u['email']) ?></td>

        <td>
          <span class="tag" style="background:<?= $roleColors[$u['role']] ?? '#aaa' ?>22;
                                   color:<?= $roleColors[$u['role']] ?? 'var(--text-main)' ?>;
                                   font-size:11px;">
            <i class="fa-solid <?= $roleIcons[$u['role']] ?? 'fa-user' ?>"></i>
            <?= $u['role'] ?>
          </span>
        </td>

        <td>
          <?php if ($u['active']): ?>
            <span class="tag" style="background:rgba(46,158,104,.1);color:#1c7a4e;font-size:11px;">Active</span>
          <?php else: ?>
            <span class="tag tag-expired" style="font-size:11px;">Inactive</span>
          <?php endif; ?>
        </td>

        <td>
          <span style="font-family:var(--font-mono);font-size:13px;
                       <?= $u['loginAttempts'] >= MAX_LOGIN_ATTEMPTS ? 'color:var(--red);font-weight:700;' : 'color:var(--text-muted)' ?>">
            <?= $u['loginAttempts'] ?> / <?= MAX_LOGIN_ATTEMPTS ?>
          </span>
          <?php if ($u['loginAttempts'] >= MAX_LOGIN_ATTEMPTS): ?>
            <span class="tag tag-urgent" style="font-size:10px;margin-left:4px;">Locked</span>
          <?php endif; ?>
        </td>

        <td style="font-size:12.5px;color:var(--text-muted);">
          <?= $u['lastLoginAt'] ? Utils::timeAgo($u['lastLoginAt']) : '<span style="color:var(--text-light)">Never</span>' ?>
        </td>

        <td style="font-size:12.5px;color:var(--text-muted);white-space:nowrap;">
          <?= date('d M Y', strtotime($u['createdAt'])) ?>
        </td>

        <td>
          <div style="display:flex;gap:5px;">
            <?php if ($u['userID'] !== $currentUID): ?>
              <!-- Toggle active -->
              <form method="POST" style="display:inline;">
                <input type="hidden" name="action"   value="toggle_active">
                <input type="hidden" name="targetID" value="<?= $u['userID'] ?>">
                <button type="submit" class="btn btn-outline btn-sm btn-icon"
                        data-tip="<?= $u['active'] ? 'Deactivate' : 'Activate' ?>">
                  <i class="fa-solid <?= $u['active'] ? 'fa-ban' : 'fa-circle-check' ?>"></i>
                </button>
              </form>
              <!-- Reset login attempts -->
              <?php if ($u['loginAttempts'] > 0): ?>
              <form method="POST" style="display:inline;">
                <input type="hidden" name="action"   value="reset_attempts">
                <input type="hidden" name="targetID" value="<?= $u['userID'] ?>">
                <button type="submit" class="btn btn-outline btn-sm btn-icon"
                        data-tip="Reset Login Lock"
                        data-confirm="Reset login attempts for <?= Utils::sanitize($u['fullName']) ?>?">
                  <i class="fa-solid fa-rotate-right"></i>
                </button>
              </form>
              <?php endif; ?>
            <?php else: ?>
              <a href="<?= BASE_URL ?>public/auth/profile.php"
                 class="btn btn-outline btn-sm btn-icon" data-tip="Edit My Profile">
                <i class="fa-solid fa-pen"></i>
              </a>
            <?php endif; ?>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<!-- ── Create User Modal ─────────────────────────────────────── -->
<div class="modal-overlay" id="createModal"
     onclick="if(event.target===this)this.classList.remove('open')">
  <div class="modal">
    <div class="modal-header">
      <h3>
        <i class="fa-solid fa-user-plus" style="color:var(--amber);margin-right:8px;"></i>
        Add New User
      </h3>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="create_user">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Full Name *</label>
          <div class="input-group">
            <i class="fa-solid fa-user input-icon"></i>
            <input type="text" name="fullName" class="form-control"
                   placeholder="e.g. Priya Sharma" required>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Email Address *</label>
          <div class="input-group">
            <i class="fa-solid fa-envelope input-icon"></i>
            <input type="email" name="email" class="form-control"
                   placeholder="user@school.edu" required>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Role *</label>
            <select name="role" class="form-control" required>
              <option value="">Select role</option>
              <option value="Student">Student</option>
              <option value="Teacher">Teacher</option>
              <option value="Admin">Admin</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Password *</label>
            <input type="password" name="password" class="form-control"
                   placeholder="Min 8 characters" required minlength="8">
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline"
                onclick="document.getElementById('createModal').classList.remove('open')">
          Cancel
        </button>
        <button type="submit" class="btn btn-amber">
          <i class="fa-solid fa-user-plus"></i> Create User
        </button>
      </div>
    </form>
  </div>
</div>

<?php if ($errors && $action === 'create_user'): ?>
<script>
document.addEventListener('DOMContentLoaded', () =>
  document.getElementById('createModal').classList.add('open')
);
</script>
<?php endif; ?>

<?php require_once ROOT_PATH . 'app/core/footer.php'; ?>
