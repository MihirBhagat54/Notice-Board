<?php
// public/notices/create.php — Create & Edit Notice
require_once __DIR__ . '/../../app/config/config.php';
Auth::requireLogin();
Auth::requireRole(['Admin', 'Teacher']);

$uid  = Auth::id();
$role = Auth::role();

// Are we editing?
$editID = Utils::getInt('id');
$notice = null;
if ($editID) {
    $notice = Database::fetchOne(
        'SELECT * FROM notices WHERE noticeID = ? AND deletedAt IS NULL', 'i', $editID
    );
    if (!$notice) Utils::redirect('public/notices/manage.php');
    if ($role !== 'Admin' && $notice['createdBy'] != $uid) Utils::redirect('public/notices/manage.php');
}

$isEdit  = (bool)$notice;
$errors  = [];
$grouped = NoticeHelper::getCategoriesGrouped();
$scopes  = NoticeHelper::getScopes();
$users   = Database::fetchAll('SELECT userID, fullName, role FROM users WHERE active=1 ORDER BY role, fullName');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title       = Utils::post('title');
    $description = Utils::post('description');
    $categoryID  = Utils::postInt('categoryID');
    $scopeID     = Utils::postInt('scopeID');
    $targetRole  = Utils::post('targetRole') ?: null;
    $targetUID   = Utils::postInt('targetUserID') ?: null;
    $publishDate = Utils::post('publishDate');
    $expiryDate  = Utils::post('expiryDate') ?: null;

    // Validation
    if (!$title)       $errors[] = 'Notice title is required.';
    if (!$description) $errors[] = 'Notice content is required.';
    if (!$categoryID)  $errors[] = 'Please select a category.';
    if (!$scopeID)     $errors[] = 'Please select a scope.';

    $scopeRow = Database::fetchOne('SELECT scopeName FROM notice_scopes WHERE scopeID = ?', 'i', $scopeID);
    if ($scopeRow) {
        if ($scopeRow['scopeName'] === 'Role Based' && !$targetRole) $errors[] = 'Please select a target role for Role Based scope.';
        if ($scopeRow['scopeName'] === 'Individual'  && !$targetUID)  $errors[] = 'Please select a target user for Individual scope.';
    }

    // Attachment handling
    $attachData = $notice['attachment']     ?? null;
    $attachName = $notice['attachmentName'] ?? null;
    $attachType = $notice['attachmentType'] ?? null;

    if (!empty($_FILES['attachment']['size'])) {
        $file    = $_FILES['attachment'];
        $allowed = ['application/pdf', 'image/jpeg', 'image/png', 'image/gif'];
        if (!in_array($file['type'], $allowed)) {
            $errors[] = 'Only PDF, JPG, PNG, or GIF files are allowed.';
        } elseif ($file['size'] > 5 * 1024 * 1024) {
            $errors[] = 'Attachment must not exceed 5 MB.';
        } else {
            $attachData = file_get_contents($file['tmp_name']);
            $attachName = basename($file['name']);
            $attachType = $file['type'];
        }
    }

    if (empty($errors)) {
        $pubDate = date('Y-m-d H:i:s', strtotime($publishDate));
        $expDate = $expiryDate ? date('Y-m-d H:i:s', strtotime($expiryDate)) : null;

        $db = Database::raw();

        if ($isEdit) {
            $stmt = $db->prepare(
                'UPDATE notices
                 SET title=?, description=?, categoryID=?, scopeID=?,
                     targetRole=?, targetUserID=?, publishDate=?, expiryDate=?,
                     attachment=?, attachmentName=?, attachmentType=?,
                     modifiedBy=?, modifiedAt=NOW()
                 WHERE noticeID=?'
            );
            $null = null;
            $stmt->bind_param(
                'ssiissssbssii',
                $title, $description, $categoryID, $scopeID,
                $targetRole, $targetUID, $pubDate, $expDate,
                $null, $attachName, $attachType,
                $uid, $editID
            );
            $stmt->send_long_data(8, $attachData ?? '');
            $stmt->execute();
            Utils::flash('success', 'Notice updated successfully.');
        } else {
            $stmt = $db->prepare(
                'INSERT INTO notices
                    (title, description, categoryID, scopeID,
                     targetRole, targetUserID, publishDate, expiryDate,
                     attachment, attachmentName, attachmentType,
                     createdBy, modifiedBy)
                 VALUES (?,?,?,?, ?,?,?,?, ?,?,?, ?,?)'
            );
            $null = null;
            $stmt->bind_param(
                'ssiissssbssii',
                $title, $description, $categoryID, $scopeID,
                $targetRole, $targetUID, $pubDate, $expDate,
                $null, $attachName, $attachType,
                $uid, $uid
            );
            $stmt->send_long_data(8, $attachData ?? '');
            $stmt->execute();
            Utils::flash('success', 'Notice published successfully.');
        }
        Utils::redirect('public/notices/manage.php');
    }
}

$pageTitle    = $isEdit ? 'Edit Notice' : 'New Notice';
$pageSubtitle = $isEdit ? 'Update notice details' : 'Publish to the board';
require_once ROOT_PATH . 'app/core/header.php';
?>

<div class="page-header fade-up">
  <div class="page-header-left">
    <h1><?= $isEdit ? 'Edit Notice' : 'Publish New Notice' ?></h1>
    <div class="breadcrumb">
      <a href="<?= BASE_URL ?>public/dashboard.php">Dashboard</a>
      <i class="fa-solid fa-chevron-right" style="font-size:9px"></i>
      <a href="<?= BASE_URL ?>public/notices/manage.php">My Notices</a>
      <i class="fa-solid fa-chevron-right" style="font-size:9px"></i>
      <?= $isEdit ? 'Edit' : 'New Notice' ?>
    </div>
  </div>
  <a href="<?= BASE_URL ?>public/notices/manage.php" class="btn btn-outline">
    <i class="fa-solid fa-arrow-left"></i> Back
  </a>
</div>

<?php if ($errors): ?>
<div class="alert alert-error fade-up">
  <i class="fa-solid fa-circle-exclamation"></i>
  <div><?php foreach ($errors as $e) echo '<div>' . Utils::sanitize($e) . '</div>'; ?></div>
</div>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data">
  <div style="display:grid; grid-template-columns:1fr 320px; gap:24px; align-items:start;">

    <!-- ── Left: Notice Content ── -->
    <div style="display:flex; flex-direction:column; gap:20px;">

      <div class="card fade-up">
        <div class="card-header">
          <h2><i class="fa-solid fa-file-pen" style="color:var(--amber);margin-right:8px;"></i>Notice Content</h2>
        </div>
        <div class="card-body">
          <div class="form-group">
            <label class="form-label">Title *</label>
            <input type="text" name="title" class="form-control"
                   placeholder="Enter a clear, descriptive title"
                   value="<?= Utils::sanitize($notice['title'] ?? $_POST['title'] ?? '') ?>"
                   required autofocus>
          </div>
          <div class="form-group">
            <label class="form-label">Content *</label>
            <textarea name="description" class="form-control" rows="9"
                      placeholder="Write the full notice content here…"
                      required><?= Utils::sanitize($notice['description'] ?? $_POST['description'] ?? '') ?></textarea>
          </div>
        </div>
      </div>

      <!-- Attachment -->
      <div class="card fade-up">
        <div class="card-header">
          <h2><i class="fa-solid fa-paperclip" style="color:var(--amber);margin-right:8px;"></i>Attachment</h2>
          <span style="font-size:12px;color:var(--text-muted);">PDF or Image · max 5 MB</span>
        </div>
        <div class="card-body">
          <?php if ($isEdit && !empty($notice['attachmentName'])): ?>
            <div class="attachment-box" style="margin-bottom:16px;">
              <div class="attachment-icon">
                <i class="fa-solid <?= str_contains($notice['attachmentType'] ?? '', 'pdf') ? 'fa-file-pdf' : 'fa-file-image' ?>"></i>
              </div>
              <div>
                <div class="attachment-name"><?= Utils::sanitize($notice['attachmentName']) ?></div>
                <div class="attachment-size">Current attachment</div>
              </div>
              <a href="<?= BASE_URL ?>public/notices/attachment.php?id=<?= $editID ?>"
                 class="btn btn-outline btn-sm" style="margin-left:auto;" target="_blank">
                <i class="fa-solid fa-eye"></i> View
              </a>
            </div>
          <?php endif; ?>
          <div class="upload-area" id="dropZone"
               onclick="document.getElementById('attachFile').click()">
            <i class="fa-solid fa-cloud-arrow-up"></i>
            <p><?= ($isEdit && !empty($notice['attachmentName'])) ? 'Click to replace attachment' : 'Click or drag & drop file here' ?></p>
            <small>PDF, JPG, PNG, GIF — max 5 MB</small>
            <div id="fileNameDisplay" style="margin-top:10px;font-size:13px;color:var(--navy);font-weight:500;"></div>
          </div>
          <input type="file" name="attachment" id="attachFile"
                 accept=".pdf,.jpg,.jpeg,.png,.gif" style="display:none;">
        </div>
      </div>
    </div>

    <!-- ── Right: Settings Panel ── -->
    <div class="card fade-up">
      <div class="card-header">
        <h2><i class="fa-solid fa-sliders" style="color:var(--amber);margin-right:8px;"></i>Settings</h2>
      </div>
      <div class="card-body">

        <!-- Category -->
        <div class="form-group">
          <label class="form-label">Category *</label>
          <select name="categoryID" class="form-control" required>
            <option value="">Select a category</option>
            <?php foreach ($grouped as $catName => $items): ?>
              <optgroup label="<?= Utils::sanitize($catName) ?>">
                <?php foreach ($items as $item): ?>
                  <option value="<?= $item['categoryID'] ?>"
                    <?= (($notice['categoryID'] ?? $_POST['categoryID'] ?? '') == $item['categoryID']) ? 'selected' : '' ?>>
                    <?= Utils::sanitize($item['subCategory']) ?>
                  </option>
                <?php endforeach; ?>
              </optgroup>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Scope -->
        <div class="form-group">
          <label class="form-label">Scope *</label>
          <select name="scopeID" id="scopeSelect" class="form-control" required>
            <option value="">Select a scope</option>
            <?php foreach ($scopes as $s): ?>
              <option value="<?= $s['scopeID'] ?>"
                      data-name="<?= Utils::sanitize($s['scopeName']) ?>"
                      data-desc="<?= Utils::sanitize($s['description']) ?>"
                <?= (($notice['scopeID'] ?? $_POST['scopeID'] ?? '') == $s['scopeID']) ? 'selected' : '' ?>>
                <?= Utils::sanitize($s['scopeName']) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <div id="scopeDesc" style="font-size:12px;color:var(--text-muted);margin-top:5px;"></div>
        </div>

        <!-- Role Based target -->
        <div class="form-group" id="roleTargetGroup" style="display:none;">
          <label class="form-label">Target Role *</label>
          <select name="targetRole" class="form-control">
            <option value="">Select role</option>
            <?php foreach (['Admin','Teacher','Student'] as $r): ?>
              <option value="<?= $r ?>"
                <?= (($notice['targetRole'] ?? '') === $r) ? 'selected' : '' ?>>
                <?= $r ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Individual target -->
        <div class="form-group" id="userTargetGroup" style="display:none;">
          <label class="form-label">Target User *</label>
          <select name="targetUserID" class="form-control">
            <option value="">Select user</option>
            <?php foreach ($users as $u): ?>
              <option value="<?= $u['userID'] ?>"
                <?= (($notice['targetUserID'] ?? '') == $u['userID']) ? 'selected' : '' ?>>
                <?= Utils::sanitize($u['fullName']) ?> (<?= $u['role'] ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <hr style="border:none;border-top:1px solid var(--border-lt);margin:16px 0;">

        <!-- Dates -->
        <div class="form-group">
          <label class="form-label">Publish Date *</label>
          <input type="datetime-local" name="publishDate" class="form-control" required
                 value="<?= $notice ? date('Y-m-d\TH:i', strtotime($notice['publishDate'])) : date('Y-m-d\TH:i') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">
            Expiry Date
            <span style="font-weight:400;color:var(--text-light);"> (optional)</span>
          </label>
          <input type="datetime-local" name="expiryDate" class="form-control"
                 value="<?= ($notice && $notice['expiryDate']) ? date('Y-m-d\TH:i', strtotime($notice['expiryDate'])) : '' ?>">
        </div>
      </div>

      <div class="card-footer">
        <a href="<?= BASE_URL ?>public/notices/manage.php" class="btn btn-outline">Cancel</a>
        <button type="submit" class="btn btn-amber">
          <i class="fa-solid fa-<?= $isEdit ? 'floppy-disk' : 'paper-plane' ?>"></i>
          <?= $isEdit ? 'Save Changes' : 'Publish Notice' ?>
        </button>
      </div>
    </div>

  </div>
</form>

<script>
// ── Scope conditional fields ───────────────────────────────────
const scopeSelect = document.getElementById('scopeSelect');
function updateScopeUI() {
  const opt  = scopeSelect.options[scopeSelect.selectedIndex];
  const name = opt?.dataset.name || '';
  const desc = opt?.dataset.desc || '';
  document.getElementById('scopeDesc').textContent      = desc;
  document.getElementById('roleTargetGroup').style.display = name === 'Role Based' ? '' : 'none';
  document.getElementById('userTargetGroup').style.display = name === 'Individual'  ? '' : 'none';
}
scopeSelect?.addEventListener('change', updateScopeUI);
updateScopeUI();

// ── File drop zone ─────────────────────────────────────────────
const dropZone    = document.getElementById('dropZone');
const fileInput   = document.getElementById('attachFile');
const fileDisplay = document.getElementById('fileNameDisplay');

fileInput?.addEventListener('change', () => {
  if (fileInput.files[0]) fileDisplay.textContent = '📎 ' + fileInput.files[0].name;
});
dropZone?.addEventListener('dragover',  e => { e.preventDefault(); dropZone.classList.add('drag-over'); });
dropZone?.addEventListener('dragleave', ()  => dropZone.classList.remove('drag-over'));
dropZone?.addEventListener('drop', e => {
  e.preventDefault(); dropZone.classList.remove('drag-over');
  const dt = new DataTransfer();
  dt.items.add(e.dataTransfer.files[0]);
  fileInput.files = dt.files;
  fileDisplay.textContent = '📎 ' + e.dataTransfer.files[0].name;
});
</script>

<?php require_once ROOT_PATH . 'app/core/footer.php'; ?>
