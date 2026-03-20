<?php
// public/admin/users.php — Admin: User Management
// Admin can create Admin / Teacher / Student accounts.
// No manual password — system generates & emails it.
require_once __DIR__ . '/../../app/config/config.php';
Auth::requireLogin();
Auth::requireRole(['Admin']);

$currentUID = Auth::id();
$errors     = [];
$action     = Utils::post('action');

// ── Handle POST ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ── Create user ───────────────────────────────────────────
    if ($action === 'create_user') {
        $fullName = Utils::post('fullName');
        $email    = Utils::post('email');
        $role     = Utils::post('role');
        $phoneNo  = Utils::post('phoneNo') ?: null;
        $grade    = ($role === 'Student') ? Utils::post('grade') : null;

        // Validation
        if (!$fullName) $errors[] = 'Full name is required.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email address is required.';
        if (!in_array($role, ['Admin','Teacher','Student'], true)) $errors[] = 'Please select a valid role.';
        if ($role === 'Student') {
            if (!$grade || !in_array($grade, NoticeHelper::GRADES, true)) {
                $errors[] = 'Please select a valid grade (1–12) for the student.';
            }
        }

        if (empty($errors)) {
            $dup = Database::fetchOne('SELECT userID FROM users WHERE email = ?', 's', $email);
            if ($dup) {
                $errors[] = 'That email address is already registered.';
            } else {
                // Generate password, hash it, store it, email it
                $plainPwd = Auth::generateRandomPassword();
                $salt     = Auth::generateSalt();
                $hash     = Auth::hashPassword($plainPwd, $salt);

                Database::query(
                    'INSERT INTO users
                        (fullName, email, password, salt, role, grade, phoneNo, createdBy)
                     VALUES (?,?,?,?,?,?,?,?)',
                    'sssssssi',
                    $fullName, $email, $hash, $salt, $role, $grade, $phoneNo, $currentUID
                );

                $mailResult = Auth::sendWelcomeEmail($email, $fullName, $role, $plainPwd, $grade);
                if ($mailResult['ok']) {
                    Utils::flash('success',
                        "Account for \"{$fullName}\" created. Credentials emailed to {$email}."
                    );
                } else {
                    $_SESSION['credential_fallback'] = [
                        'name'     => $fullName,
                        'email'    => $email,
                        'password' => $plainPwd,
                        'role'     => $role,
                        'grade'    => $grade,
                        'smtpError' => $mailResult['error'],
                    ];
                    Utils::flash('warning',
                        "Account created but welcome email could not be sent. "
                      . "Credentials are shown below — share them with the user manually."
                    );
                }
                Utils::redirect('public/admin/users.php');
            }
        }
    }

    // ── Toggle active ─────────────────────────────────────────
    elseif ($action === 'toggle_active') {
        $targetID = Utils::postInt('targetID');
        if ($targetID && $targetID !== $currentUID) {
            Database::query('UPDATE users SET active = 1 - active WHERE userID = ?', 'i', $targetID);
            Utils::flash('success', 'User status updated.');
        }
        Utils::redirect('public/admin/users.php');
    }

    // ── Reset login attempts ──────────────────────────────────
    elseif ($action === 'reset_attempts') {
        $targetID = Utils::postInt('targetID');
        if ($targetID) {
            Database::query('UPDATE users SET loginAttempts = 0 WHERE userID = ?', 'i', $targetID);
            Utils::flash('success', 'Login attempts reset.');
        }
        Utils::redirect('public/admin/users.php');
    }

    // ── Resend welcome email ──────────────────────────────────
    elseif ($action === 'resend_credentials') {
        $targetID = Utils::postInt('targetID');
        $target   = Database::fetchOne('SELECT * FROM users WHERE userID = ?', 'i', $targetID);
        if ($target) {
            // Generate a new password and update
            $plainPwd = Auth::generateRandomPassword();
            $salt     = Auth::generateSalt();
            $hash     = Auth::hashPassword($plainPwd, $salt);
            Database::query(
                'UPDATE users SET password = ?, salt = ?, lastPasswordChangedAt = NOW() WHERE userID = ?',
                'ssi', $hash, $salt, $targetID
            );
            $mailResult = Auth::sendWelcomeEmail(
                $target['email'], $target['fullName'],
                $target['role'], $plainPwd, $target['grade']
            );
            if ($mailResult['ok']) {
                Utils::flash('success', "New credentials generated and emailed to {$target['email']}.");
            } else {
                $_SESSION['credential_fallback'] = [
                    'name'      => $target['fullName'],
                    'email'     => $target['email'],
                    'password'  => $plainPwd,
                    'role'      => $target['role'],
                    'grade'     => $target['grade'],
                    'smtpError' => $mailResult['error'],
                ];
                Utils::flash('warning', "Credentials reset but email failed. Credentials shown below.");
            }
        }
        Utils::redirect('public/admin/users.php');
    }
}

// ── Fetch users ───────────────────────────────────────────────
$search = Utils::get('search');
$roleF  = Utils::get('role');
$gradeF = Utils::get('grade');

$where  = [];
$types  = '';
$params = [];

if ($search) {
    $where[]  = '(fullName LIKE ? OR email LIKE ? OR phoneNo LIKE ?)';
    $types   .= 'sss';
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}
if ($roleF && in_array($roleF, ['Admin','Teacher','Student'], true)) {
    $where[]  = 'role = ?';
    $types   .= 's';
    $params[] = $roleF;
}
if ($gradeF && in_array($gradeF, NoticeHelper::GRADES, true)) {
    $where[]  = 'grade = ?';
    $types   .= 's';
    $params[] = $gradeF;
}

$whereSQL  = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$usersList = Database::fetchAll(
    "SELECT u.*, c.fullName AS createdByName
     FROM users u
     LEFT JOIN users c ON u.createdBy = c.userID
     {$whereSQL}
     ORDER BY u.role, u.fullName",
    $types, ...$params
);

// Role counts
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
      Admin · Users
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
<?php $fw = Utils::flash('warning'); if ($fw): ?>
  <div class="alert alert-warning fade-up"><i class="fa-solid fa-triangle-exclamation"></i><?= Utils::sanitize($fw) ?></div>
<?php endif; ?>
<?php
$__cred = $_SESSION['credential_fallback'] ?? null;
unset($_SESSION['credential_fallback']);
?>
<?php if ($__cred): ?>
<div class="card fade-up" style="border:2px solid var(--amber);margin-bottom:20px;">
  <div class="card-header" style="background:rgba(232,168,49,.08);">
    <h2 style="color:var(--amber-dk);"><i class="fa-solid fa-envelope-open-text" style="margin-right:8px;"></i>Manual Credential Delivery Required</h2>
  </div>
  <div class="card-body">
    <p style="font-size:13.5px;color:var(--text-muted);margin-bottom:16px;">
      The account was created successfully, but the welcome email could not be delivered.
      Please share these credentials with the user directly.
    </p>
    <div style="background:var(--navy);border-radius:var(--r-md);padding:20px 24px;font-family:var(--font-mono);font-size:13.5px;color:var(--white);line-height:2;">
      <div><span style="color:var(--amber);min-width:110px;display:inline-block;">Name</span><?= Utils::sanitize($__cred['name']) ?></div>
      <div><span style="color:var(--amber);min-width:110px;display:inline-block;">Email</span><?= Utils::sanitize($__cred['email']) ?></div>
      <div><span style="color:var(--amber);min-width:110px;display:inline-block;">Password</span><?= Utils::sanitize($__cred['password']) ?></div>
      <div><span style="color:var(--amber);min-width:110px;display:inline-block;">Role</span><?= Utils::sanitize($__cred['role']) ?><?= $__cred['grade'] ? ' · Grade ' . Utils::sanitize($__cred['grade']) : '' ?></div>
      <div><span style="color:var(--amber);min-width:110px;display:inline-block;">Login URL</span><?= BASE_URL ?>public/auth/login.php</div>
    </div>
    <div class="alert alert-error" style="margin-top:16px;margin-bottom:0;">
      <i class="fa-solid fa-circle-exclamation"></i>
      <div><strong>SMTP Error:</strong> <?= Utils::sanitize($__cred['smtpError']) ?><br>
      <small>Gmail requires a 16-character <strong>App Password</strong> (not your Gmail password).<br>
      Go to: <strong>myaccount.google.com → Security → 2-Step Verification → App passwords</strong></small></div>
    </div>
  </div>
</div>
<?php endif; ?>
<?php if ($errors): ?>
  <div class="alert alert-error fade-up">
    <i class="fa-solid fa-circle-exclamation"></i>
    <div><?php foreach ($errors as $e) echo '<div>' . Utils::sanitize($e) . '</div>'; ?></div>
  </div>
<?php endif; ?>

<!-- Stats -->
<div class="stats-row fade-up">
  <?php foreach (['Admin','Teacher','Student'] as $r): ?>
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

<!-- Filters -->
<form method="GET" class="filter-bar fade-up">
  <div class="filter-search-wrap" style="flex:1;">
    <i class="fa-solid fa-magnifying-glass fs-icon"></i>
    <input type="text" name="search" class="filter-search form-control"
           placeholder="Search name, email or phone…"
           value="<?= Utils::sanitize($search) ?>" style="padding-left:36px;">
  </div>
  <select name="role" class="filter-select">
    <option value="">All Roles</option>
    <?php foreach (['Admin','Teacher','Student'] as $r): ?>
      <option value="<?= $r ?>" <?= $roleF===$r?'selected':''?>><?= $r ?></option>
    <?php endforeach; ?>
  </select>
  <select name="grade" class="filter-select">
    <option value="">All Grades</option>
    <?php foreach (NoticeHelper::GRADES as $g): ?>
      <option value="<?= $g ?>" <?= $gradeF===$g?'selected':''?>>Grade <?= $g ?></option>
    <?php endforeach; ?>
  </select>
  <button type="submit" class="btn btn-primary btn-sm">Search</button>
  <?php if ($search || $roleF || $gradeF): ?>
    <a href="users.php" class="btn btn-outline btn-sm">Clear</a>
  <?php endif; ?>
</form>

<!-- Users Table -->
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
        <th>#</th>
        <th>Name</th>
        <th>Email</th>
        <th>Phone</th>
        <th>Role</th>
        <th>Grade</th>
        <th>Status</th>
        <th>Attempts</th>
        <th>Last Login</th>
        <th>Created By</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($usersList as $u): ?>
      <tr>
        <td style="color:var(--text-light);font-size:12px;font-family:var(--font-mono);"><?= $u['userID'] ?></td>

        <td>
          <div style="display:flex;align-items:center;gap:10px;">
            <div style="width:32px;height:32px;border-radius:50%;
                        background:<?= $roleColors[$u['role']] ?? 'var(--navy)' ?>;
                        color:white;display:flex;align-items:center;justify-content:center;
                        font-size:12px;font-weight:700;flex-shrink:0;">
              <?= Utils::initials($u['fullName']) ?>
            </div>
            <div>
              <div style="font-weight:500;font-size:13px;"><?= Utils::sanitize($u['fullName']) ?></div>
              <?php if ($u['userID'] == $currentUID): ?>
                <div style="font-size:10px;color:var(--amber-dk);font-weight:700;letter-spacing:.4px;">YOU</div>
              <?php endif; ?>
            </div>
          </div>
        </td>

        <td style="font-size:12.5px;color:var(--text-muted);"><?= Utils::sanitize($u['email']) ?></td>

        <td style="font-size:12.5px;color:var(--text-muted);">
          <?= $u['phoneNo'] ? Utils::sanitize($u['phoneNo']) : '<span style="color:var(--text-light)">—</span>' ?>
        </td>

        <td>
          <span class="tag" style="background:<?= $roleColors[$u['role']] ?? '#aaa' ?>22;
                                   color:<?= $roleColors[$u['role']] ?? 'var(--text-main)' ?>;font-size:11px;">
            <i class="fa-solid <?= $roleIcons[$u['role']] ?? 'fa-user' ?>"></i>
            <?= $u['role'] ?>
          </span>
        </td>

        <td>
          <?php if ($u['grade']): ?>
            <span class="tag" style="background:rgba(79,201,247,.12);color:#0a7a96;font-size:11px;">
              <i class="fa-solid fa-layer-group"></i> Grade <?= Utils::sanitize($u['grade']) ?>
            </span>
          <?php else: ?>
            <span style="color:var(--text-light);font-size:12px;">—</span>
          <?php endif; ?>
        </td>

        <td>
          <?php if ($u['active']): ?>
            <span class="tag" style="background:rgba(46,158,104,.1);color:#1c7a4e;font-size:11px;">Active</span>
          <?php else: ?>
            <span class="tag tag-expired" style="font-size:11px;">Inactive</span>
          <?php endif; ?>
        </td>

        <td>
          <span style="font-family:var(--font-mono);font-size:12px;
                       <?= $u['loginAttempts'] >= MAX_LOGIN_ATTEMPTS ? 'color:var(--red);font-weight:700;' : 'color:var(--text-muted)' ?>">
            <?= $u['loginAttempts'] ?>/<?= MAX_LOGIN_ATTEMPTS ?>
          </span>
          <?php if ($u['loginAttempts'] >= MAX_LOGIN_ATTEMPTS): ?>
            <span class="tag tag-urgent" style="font-size:9px;margin-left:4px;">Locked</span>
          <?php endif; ?>
        </td>

        <td style="font-size:12px;color:var(--text-muted);">
          <?= $u['lastLoginAt'] ? Utils::timeAgo($u['lastLoginAt']) : '<span style="color:var(--text-light)">Never</span>' ?>
        </td>

        <td style="font-size:12px;color:var(--text-muted);">
          <?= $u['createdByName'] ? Utils::sanitize($u['createdByName']) : '<span style="color:var(--text-light)">System</span>' ?>
        </td>

        <td>
          <div style="display:flex;gap:4px;flex-wrap:wrap;">
            <?php if ($u['userID'] !== $currentUID): ?>

              <!-- Toggle active/inactive -->
              <form method="POST" style="display:inline;">
                <input type="hidden" name="action"   value="toggle_active">
                <input type="hidden" name="targetID" value="<?= $u['userID'] ?>">
                <button type="submit" class="btn btn-outline btn-sm btn-icon"
                        data-tip="<?= $u['active'] ? 'Deactivate' : 'Activate' ?>">
                  <i class="fa-solid <?= $u['active'] ? 'fa-ban' : 'fa-circle-check' ?>"></i>
                </button>
              </form>

              <!-- Reset login lock -->
              <?php if ($u['loginAttempts'] > 0): ?>
              <form method="POST" style="display:inline;">
                <input type="hidden" name="action"   value="reset_attempts">
                <input type="hidden" name="targetID" value="<?= $u['userID'] ?>">
                <button type="submit" class="btn btn-outline btn-sm btn-icon"
                        data-tip="Unlock Account"
                        data-confirm="Reset login lock for <?= Utils::sanitize($u['fullName']) ?>?">
                  <i class="fa-solid fa-lock-open"></i>
                </button>
              </form>
              <?php endif; ?>

              <!-- Resend credentials -->
              <form method="POST" style="display:inline;">
                <input type="hidden" name="action"   value="resend_credentials">
                <input type="hidden" name="targetID" value="<?= $u['userID'] ?>">
                <button type="submit" class="btn btn-outline btn-sm btn-icon"
                        data-tip="Resend Login Credentials"
                        data-confirm="Generate a new password and email it to <?= Utils::sanitize($u['fullName']) ?>?">
                  <i class="fa-solid fa-envelope-circle-check"></i>
                </button>
              </form>

            <?php else: ?>
              <a href="<?= BASE_URL ?>public/auth/profile.php"
                 class="btn btn-outline btn-sm btn-icon" data-tip="My Profile">
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

<!-- ═══════════════════════════════════════
     CREATE USER MODAL
     ═══════════════════════════════════════ -->
<div class="modal-overlay" id="createModal"
     onclick="if(event.target===this)this.classList.remove('open')">
  <div class="modal" style="max-width:560px;">
    <div class="modal-header">
      <h3>
        <i class="fa-solid fa-user-plus" style="color:var(--amber);margin-right:8px;"></i>
        Add New User
      </h3>
      <p style="font-size:13px;color:var(--text-muted);margin-top:4px;">
        A secure password will be auto-generated and emailed to the new user.
      </p>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="create_user">
      <div class="modal-body">

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Full Name *</label>
            <div class="input-group">
              <i class="fa-solid fa-user input-icon"></i>
              <input type="text" name="fullName" class="form-control"
                     placeholder="e.g. Priya Sharma" required>
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">Phone Number</label>
            <div class="input-group">
              <i class="fa-solid fa-phone input-icon"></i>
              <input type="tel" name="phoneNo" class="form-control"
                     placeholder="e.g. 9876543210" maxlength="15">
            </div>
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

        <div class="form-group">
          <label class="form-label">Role *</label>
          <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-top:4px;">
            <?php foreach (['Student','Teacher','Admin'] as $r):
              $ic = $roleIcons[$r]; $cl = $roleColors[$r];
            ?>
            <label style="display:flex;flex-direction:column;align-items:center;gap:6px;padding:12px 8px;
                          border:2px solid var(--border);border-radius:var(--r-md);cursor:pointer;
                          transition:all .2s;" class="role-card" data-role="<?= $r ?>">
              <input type="radio" name="role" value="<?= $r ?>" required
                     style="position:absolute;opacity:0;" onchange="updateRoleUI()">
              <i class="fa-solid <?= $ic ?>" style="font-size:20px;color:<?= $cl ?>"></i>
              <span style="font-size:12px;font-weight:600;color:var(--navy);"><?= $r ?></span>
            </label>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Grade — only for Student -->
        <div class="form-group" id="gradeGroup" style="display:none;">
          <label class="form-label">Student Grade *</label>
          <select name="grade" id="gradeSelect" class="form-control">
            <option value="">Select grade</option>
            <?php foreach (NoticeHelper::GRADES as $g): ?>
              <option value="<?= $g ?>">Grade <?= $g ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Email preview notice -->
        <div class="alert alert-info" style="margin-top:4px;margin-bottom:0;">
          <i class="fa-solid fa-circle-info"></i>
          <span>A randomly generated password will be emailed to the new user immediately upon account creation.</span>
        </div>

      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline"
                onclick="document.getElementById('createModal').classList.remove('open')">
          Cancel
        </button>
        <button type="submit" class="btn btn-amber">
          <i class="fa-solid fa-user-plus"></i> Create &amp; Send Credentials
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

<style>
.role-card:has(input:checked) {
    border-color: var(--navy) !important;
    background: rgba(13,27,42,.05);
    box-shadow: 0 0 0 3px rgba(13,27,42,.08);
}
</style>
<script>
function updateRoleUI() {
    const selected = document.querySelector('input[name="role"]:checked')?.value;
    document.getElementById('gradeGroup').style.display  = selected === 'Student' ? '' : 'none';
    document.getElementById('gradeSelect').required      = selected === 'Student';
}
// Highlight card on click
document.querySelectorAll('.role-card').forEach(card => {
    card.addEventListener('click', () => {
        card.querySelector('input[type="radio"]').checked = true;
        updateRoleUI();
    });
});
</script>

<?php require_once ROOT_PATH . 'app/core/footer.php'; ?>
