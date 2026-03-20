<?php
// public/teacher/users.php — Teacher: Add Student accounts
// Teachers can only create Student accounts for their assigned grades.
require_once __DIR__ . '/../../app/config/config.php';
Auth::requireLogin();
Auth::requireRole(['Teacher']);

$currentUID  = Auth::id();
$currentUser = Auth::currentUser();
$errors      = [];
$action      = Utils::post('action');

// ── Handle POST ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'create_student') {
    $fullName = Utils::post('fullName');
    $email    = Utils::post('email');
    $phoneNo  = Utils::post('phoneNo') ?: null;
    $grade    = Utils::post('grade');

    // Validation
    if (!$fullName) $errors[] = 'Full name is required.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email address is required.';
    if (!$grade || !in_array($grade, NoticeHelper::GRADES, true)) {
        $errors[] = 'Please select a valid grade (1–12).';
    }

    if (empty($errors)) {
        $dup = Database::fetchOne('SELECT userID FROM users WHERE email = ?', 's', $email);
        if ($dup) {
            $errors[] = 'That email address is already registered.';
        } else {
            $plainPwd = Auth::generateRandomPassword();
            $salt     = Auth::generateSalt();
            $hash     = Auth::hashPassword($plainPwd, $salt);

            Database::query(
                'INSERT INTO users (fullName, email, password, salt, role, grade, phoneNo, createdBy)
                 VALUES (?,?,?,?,?,?,?,?)',
                'sssssssi',
                $fullName, $email, $hash, $salt, 'Student', $grade, $phoneNo, $currentUID
            );

            Auth::sendWelcomeEmail($email, $fullName, 'Student', $plainPwd, $grade);
            Utils::flash('success',
                "Student account for \"{$fullName}\" (Grade {$grade}) created. Credentials emailed to {$email}."
            );
            Utils::redirect('public/teacher/users.php');
        }
    }
}


    // ── Resend credentials ────────────────────────────────────
    elseif ($action === 'resend_credentials') {
        $targetID = Utils::postInt('targetID');
        $target   = Database::fetchOne(
            "SELECT * FROM users WHERE userID = ? AND role = 'Student' AND createdBy = ?",
            'ii', $targetID, $currentUID
        );
        if ($target) {
            $plainPwd = Auth::generateRandomPassword();
            $salt     = Auth::generateSalt();
            $hash     = Auth::hashPassword($plainPwd, $salt);
            Database::query(
                'UPDATE users SET password=?, salt=?, lastPasswordChangedAt=NOW() WHERE userID=?',
                'ssi', $hash, $salt, $targetID
            );
            Auth::sendWelcomeEmail(
                $target['email'], $target['fullName'], 'Student', $plainPwd, $target['grade']
            );
            Utils::flash('success', "New credentials generated and emailed to {$target['email']}.");
        }
        Utils::redirect('public/teacher/users.php');
    }

// ── Fetch students created by this teacher ────────────────────
$search = Utils::get('search');
$gradeF = Utils::get('grade');

$where  = ["u.role = 'Student'", "u.createdBy = ?"];
$types  = 'i';
$params = [$currentUID];

if ($search) {
    $where[]  = '(u.fullName LIKE ? OR u.email LIKE ?)';
    $types   .= 'ss';
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}
if ($gradeF && in_array($gradeF, NoticeHelper::GRADES, true)) {
    $where[]  = 'u.grade = ?';
    $types   .= 's';
    $params[] = $gradeF;
}

$students = Database::fetchAll(
    "SELECT u.* FROM users u
     WHERE " . implode(' AND ', $where) . "
     ORDER BY u.grade + 0, u.fullName",
    $types, ...$params
);

// Grade breakdown for this teacher's students
$gradeBreakdown = [];
foreach (Database::fetchAll(
    "SELECT grade, COUNT(*) AS c FROM users WHERE role='Student' AND createdBy=? GROUP BY grade ORDER BY grade+0",
    'i', $currentUID
) as $row) {
    $gradeBreakdown[$row['grade']] = $row['c'];
}

$pageTitle    = 'My Students';
$pageSubtitle = 'Create & manage student accounts';
require_once ROOT_PATH . 'app/core/header.php';
?>

<div class="page-header fade-up">
  <div class="page-header-left">
    <h1>My Students</h1>
    <div class="breadcrumb">
      <a href="<?= BASE_URL ?>public/dashboard.php">Dashboard</a>
      <i class="fa-solid fa-chevron-right" style="font-size:9px"></i>
      Students
    </div>
  </div>
  <button class="btn btn-amber"
          onclick="document.getElementById('createModal').classList.add('open')">
    <i class="fa-solid fa-user-plus"></i> Add Student
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

<!-- Grade breakdown mini-stats -->
<?php if ($gradeBreakdown): ?>
<div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:22px;" class="fade-up">
  <?php foreach ($gradeBreakdown as $g => $cnt): ?>
  <div style="background:var(--white);border:1px solid var(--border-lt);border-radius:var(--r-md);
              padding:12px 18px;display:flex;align-items:center;gap:10px;">
    <span style="font-size:13px;font-weight:600;color:var(--navy);">Grade <?= $g ?></span>
    <span style="font-family:var(--font-mono);font-size:18px;color:var(--navy);font-weight:700;"><?= $cnt ?></span>
    <span style="font-size:11px;color:var(--text-muted);">student<?= $cnt!==1?'s':'' ?></span>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Filters -->
<form method="GET" class="filter-bar fade-up">
  <div class="filter-search-wrap" style="flex:1;">
    <i class="fa-solid fa-magnifying-glass fs-icon"></i>
    <input type="text" name="search" class="filter-search form-control"
           placeholder="Search by name or email…"
           value="<?= Utils::sanitize($search) ?>" style="padding-left:36px;">
  </div>
  <select name="grade" class="filter-select">
    <option value="">All Grades</option>
    <?php foreach (NoticeHelper::GRADES as $g): ?>
      <option value="<?= $g ?>" <?= $gradeF===$g?'selected':''?>>Grade <?= $g ?></option>
    <?php endforeach; ?>
  </select>
  <button type="submit" class="btn btn-primary btn-sm">Search</button>
  <?php if ($search || $gradeF): ?><a href="users.php" class="btn btn-outline btn-sm">Clear</a><?php endif; ?>
</form>

<!-- Students Table -->
<?php if (empty($students)): ?>
  <div class="empty-state fade-up">
    <i class="fa-solid fa-user-graduate"></i>
    <h3>No Students Yet</h3>
    <p>You haven't added any students. Click "Add Student" to create the first account.</p>
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
        <th>Grade</th>
        <th>Status</th>
        <th>Last Login</th>
        <th>Joined</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($students as $u): ?>
      <tr>
        <td style="color:var(--text-light);font-size:12px;font-family:var(--font-mono);"><?= $u['userID'] ?></td>

        <td>
          <div style="display:flex;align-items:center;gap:10px;">
            <div style="width:30px;height:30px;border-radius:50%;background:#2e9e68;
                        color:white;display:flex;align-items:center;justify-content:center;
                        font-size:11px;font-weight:700;flex-shrink:0;">
              <?= Utils::initials($u['fullName']) ?>
            </div>
            <span style="font-weight:500;font-size:13px;"><?= Utils::sanitize($u['fullName']) ?></span>
          </div>
        </td>

        <td style="font-size:12.5px;color:var(--text-muted);"><?= Utils::sanitize($u['email']) ?></td>

        <td style="font-size:12.5px;color:var(--text-muted);">
          <?= $u['phoneNo'] ? Utils::sanitize($u['phoneNo']) : '<span style="color:var(--text-light)">—</span>' ?>
        </td>

        <td>
          <span class="tag" style="background:rgba(79,201,247,.12);color:#0a7a96;font-size:11px;">
            <i class="fa-solid fa-layer-group"></i> Grade <?= Utils::sanitize($u['grade']) ?>
          </span>
        </td>

        <td>
          <?php if ($u['active']): ?>
            <span class="tag" style="background:rgba(46,158,104,.1);color:#1c7a4e;font-size:11px;">Active</span>
          <?php else: ?>
            <span class="tag tag-expired" style="font-size:11px;">Inactive</span>
          <?php endif; ?>
        </td>

        <td style="font-size:12px;color:var(--text-muted);">
          <?= $u['lastLoginAt'] ? Utils::timeAgo($u['lastLoginAt']) : '<span style="color:var(--text-light)">Never</span>' ?>
        </td>

        <td style="font-size:12px;color:var(--text-muted);white-space:nowrap;">
          <?= date('d M Y', strtotime($u['createdAt'])) ?>
        </td>

        <td>
          <!-- Teachers can resend credentials for students they created -->
          <form method="POST" style="display:inline;">
            <input type="hidden" name="action"   value="resend_credentials">
            <input type="hidden" name="targetID" value="<?= $u['userID'] ?>">
            <button type="submit" class="btn btn-outline btn-sm btn-icon"
                    data-tip="Resend Credentials"
                    data-confirm="Generate a new password and email it to <?= Utils::sanitize($u['fullName']) ?>?">
              <i class="fa-solid fa-envelope-circle-check"></i>
            </button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<!-- ═══════════════════════════════════════
     ADD STUDENT MODAL
     ═══════════════════════════════════════ -->
<div class="modal-overlay" id="createModal"
     onclick="if(event.target===this)this.classList.remove('open')">
  <div class="modal" style="max-width:480px;">
    <div class="modal-header">
      <h3>
        <i class="fa-solid fa-user-graduate" style="color:var(--amber);margin-right:8px;"></i>
        Add New Student
      </h3>
      <p style="font-size:13px;color:var(--text-muted);margin-top:4px;">
        Login credentials will be auto-generated and emailed to the student.
      </p>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="create_student">
      <div class="modal-body">

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Full Name *</label>
            <div class="input-group">
              <i class="fa-solid fa-user input-icon"></i>
              <input type="text" name="fullName" class="form-control"
                     placeholder="Student's full name" required>
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">Student Grade *</label>
            <select name="grade" class="form-control" required>
              <option value="">Select grade</option>
              <?php foreach (NoticeHelper::GRADES as $g): ?>
                <option value="<?= $g ?>">Grade <?= $g ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Email Address *</label>
          <div class="input-group">
            <i class="fa-solid fa-envelope input-icon"></i>
            <input type="email" name="email" class="form-control"
                   placeholder="student@school.edu" required>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Phone Number <span style="font-weight:400;color:var(--text-light)">(optional)</span></label>
          <div class="input-group">
            <i class="fa-solid fa-phone input-icon"></i>
            <input type="tel" name="phoneNo" class="form-control"
                   placeholder="e.g. 9876543210" maxlength="15">
          </div>
        </div>

        <div class="alert alert-info" style="margin-bottom:0;">
          <i class="fa-solid fa-circle-info"></i>
          A secure password will be auto-generated and sent to the student's email address.
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


<?php if ($errors && $action === 'create_student'): ?>
<script>
document.addEventListener('DOMContentLoaded', () =>
    document.getElementById('createModal').classList.add('open')
);
</script>
<?php endif; ?>

<?php require_once ROOT_PATH . 'app/core/footer.php'; ?>
