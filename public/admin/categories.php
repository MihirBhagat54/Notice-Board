<?php
// public/admin/categories.php — Admin: Notice Category Management
require_once __DIR__ . '/../../app/config/config.php';
Auth::requireLogin();
Auth::requireRole(['Admin']);

$errors = [];
$action = Utils::post('action');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Create sub-category
    if ($action === 'create') {
        $catName = Utils::post('categoryName');
        $subCat  = Utils::post('subCategory');
        $desc    = Utils::post('description');

        if (!$catName) $errors[] = 'Parent category name is required.';
        if (!$subCat)  $errors[] = 'Sub-category name is required.';

        if (empty($errors)) {
            Database::query(
                'INSERT INTO notice_categories (categoryName, subCategory, description) VALUES (?,?,?)',
                'sss', $catName, $subCat, $desc
            );
            Utils::flash('success', "Sub-category \"{$subCat}\" added successfully.");
            Utils::redirect('public/admin/categories.php');
        }
    }

    // Toggle active/inactive
    elseif ($action === 'toggle') {
        $cid = Utils::postInt('catID');
        if ($cid) {
            Database::query(
                'UPDATE notice_categories SET isActive = 1 - isActive WHERE categoryID = ?', 'i', $cid
            );
            Utils::flash('success', 'Category status updated.');
        }
        Utils::redirect('public/admin/categories.php');
    }

    // Delete (only if unused)
    elseif ($action === 'delete') {
        $cid  = Utils::postInt('catID');
        $used = Database::fetchOne(
            'SELECT COUNT(*) AS c FROM notices WHERE categoryID = ? AND deletedAt IS NULL', 'i', $cid
        )['c'] ?? 0;

        if ($used > 0) {
            Utils::flash('error', "Cannot delete: {$used} notice(s) are using this category.");
        } else {
            Database::query('DELETE FROM notice_categories WHERE categoryID = ?', 'i', $cid);
            Utils::flash('success', 'Sub-category deleted.');
        }
        Utils::redirect('public/admin/categories.php');
    }
}

// Load categories with notice counts
$categories = Database::fetchAll(
    'SELECT nc.*,
            (SELECT COUNT(*) FROM notices n
             WHERE n.categoryID = nc.categoryID AND n.deletedAt IS NULL) AS noticeCount
     FROM notice_categories nc
     ORDER BY nc.categoryName, nc.subCategory'
);

// Group by parent
$grouped = [];
foreach ($categories as $c) {
    $grouped[$c['categoryName']][] = $c;
}

$pageTitle    = 'Notice Categories';
$pageSubtitle = 'Manage categories & sub-categories';
require_once ROOT_PATH . 'app/core/header.php';
?>

<div class="page-header fade-up">
  <div class="page-header-left">
    <h1>Notice Categories</h1>
    <div class="breadcrumb">
      <a href="<?= BASE_URL ?>public/dashboard.php">Dashboard</a>
      <i class="fa-solid fa-chevron-right" style="font-size:9px"></i>
      Admin
      <i class="fa-solid fa-chevron-right" style="font-size:9px"></i>
      Categories
    </div>
  </div>
  <button class="btn btn-amber"
          onclick="document.getElementById('catModal').classList.add('open')">
    <i class="fa-solid fa-circle-plus"></i> Add Sub-Category
  </button>
</div>

<?php
$sf = Utils::flash('success'); $ef = Utils::flash('error');
if ($sf): ?><div class="alert alert-success fade-up"><i class="fa-solid fa-circle-check"></i><?= Utils::sanitize($sf) ?></div><?php endif;
if ($ef): ?><div class="alert alert-error fade-up"><i class="fa-solid fa-circle-exclamation"></i><?= Utils::sanitize($ef) ?></div><?php endif;
if ($errors): ?><div class="alert alert-error fade-up"><i class="fa-solid fa-circle-exclamation"></i><div><?php foreach ($errors as $e) echo '<div>'.Utils::sanitize($e).'</div>'; ?></div></div><?php endif;
?>

<!-- Summary stats -->
<div class="stats-row fade-up">
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(13,27,42,.07);"><i class="fa-solid fa-layer-group" style="color:var(--navy)"></i></div>
    <div class="stat-body"><div class="stat-num"><?= count($grouped) ?></div><div class="stat-label">Parent Categories</div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(79,142,247,.1);"><i class="fa-solid fa-tags" style="color:#4f8ef7"></i></div>
    <div class="stat-body"><div class="stat-num"><?= count($categories) ?></div><div class="stat-label">Sub-Categories</div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(46,158,104,.1);"><i class="fa-solid fa-circle-check" style="color:var(--green)"></i></div>
    <div class="stat-body"><div class="stat-num"><?= count(array_filter($categories, fn($c) => $c['isActive'])) ?></div><div class="stat-label">Active</div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(232,168,49,.1);"><i class="fa-solid fa-newspaper" style="color:var(--amber-dk)"></i></div>
    <div class="stat-body"><div class="stat-num"><?= array_sum(array_column($categories, 'noticeCount')) ?></div><div class="stat-label">Total Notices</div></div>
  </div>
</div>

<!-- Category cards grid -->
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(340px,1fr));gap:20px;" class="fade-up">
  <?php foreach ($grouped as $catName => $items):
    $color      = NoticeHelper::categoryColor($catName);
    $icon       = NoticeHelper::categoryIcon($catName);
    $totalInCat = array_sum(array_column($items, 'noticeCount'));
  ?>
  <div class="card">
    <!-- Parent header -->
    <div style="background:<?= $color ?>18;border-left:4px solid <?= $color ?>;
                padding:16px 20px;display:flex;align-items:center;gap:12px;">
      <div style="width:38px;height:38px;border-radius:10px;background:<?= $color ?>;
                  color:white;display:flex;align-items:center;justify-content:center;
                  font-size:16px;flex-shrink:0;">
        <i class="fa-solid <?= $icon ?>"></i>
      </div>
      <div style="flex:1;">
        <div style="font-weight:700;font-size:15px;color:var(--navy);"><?= Utils::sanitize($catName) ?></div>
        <div style="font-size:12px;color:var(--text-muted);">
          <?= count($items) ?> sub-categor<?= count($items) !== 1 ? 'ies' : 'y' ?>
          &nbsp;·&nbsp; <?= $totalInCat ?> notice<?= $totalInCat !== 1 ? 's' : '' ?>
        </div>
      </div>
    </div>

    <!-- Sub-category rows -->
    <div>
      <?php foreach ($items as $c): ?>
      <div style="display:flex;align-items:center;justify-content:space-between;
                  padding:10px 20px;border-bottom:1px solid var(--border-lt);">
        <div style="flex:1;min-width:0;">
          <div style="font-size:13.5px;font-weight:500;
                      color:<?= $c['isActive'] ? 'var(--text-main)' : 'var(--text-light)' ?>;">
            <?= Utils::sanitize($c['subCategory']) ?>
          </div>
          <?php if ($c['description']): ?>
            <div style="font-size:11.5px;color:var(--text-muted);margin-top:2px;
                        white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
              <?= Utils::sanitize($c['description']) ?>
            </div>
          <?php endif; ?>
        </div>

        <div style="display:flex;align-items:center;gap:8px;flex-shrink:0;margin-left:12px;">
          <span style="font-size:11px;font-family:var(--font-mono);color:var(--text-light);">
            <?= $c['noticeCount'] ?>
          </span>

          <?php if (!$c['isActive']): ?>
            <span class="tag tag-expired" style="font-size:10px;">Inactive</span>
          <?php endif; ?>

          <!-- Toggle active -->
          <form method="POST" style="display:inline;">
            <input type="hidden" name="action" value="toggle">
            <input type="hidden" name="catID"  value="<?= $c['categoryID'] ?>">
            <button type="submit" class="btn btn-outline btn-sm btn-icon"
                    data-tip="<?= $c['isActive'] ? 'Deactivate' : 'Activate' ?>">
              <i class="fa-solid <?= $c['isActive'] ? 'fa-toggle-on' : 'fa-toggle-off' ?>"
                 style="color:<?= $c['isActive'] ? 'var(--green)' : 'var(--text-light)' ?>"></i>
            </button>
          </form>

          <!-- Delete (only if unused) -->
          <?php if ($c['noticeCount'] == 0): ?>
          <form method="POST" style="display:inline;">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="catID"  value="<?= $c['categoryID'] ?>">
            <button type="submit" class="btn btn-danger btn-sm btn-icon"
                    data-tip="Delete"
                    data-confirm="Delete &quot;<?= Utils::sanitize($c['subCategory']) ?>&quot;?">
              <i class="fa-solid fa-trash"></i>
            </button>
          </form>
          <?php else: ?>
            <button class="btn btn-outline btn-sm btn-icon" disabled
                    data-tip="Cannot delete — has <?= $c['noticeCount'] ?> notice(s)" style="opacity:.35;">
              <i class="fa-solid fa-trash"></i>
            </button>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- ── Add Sub-Category Modal ───────────────────────────────── -->
<div class="modal-overlay" id="catModal"
     onclick="if(event.target===this)this.classList.remove('open')">
  <div class="modal">
    <div class="modal-header">
      <h3>
        <i class="fa-solid fa-tag" style="color:var(--amber);margin-right:8px;"></i>
        Add Sub-Category
      </h3>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="create">
      <div class="modal-body">

        <div class="form-group">
          <label class="form-label">Parent Category *</label>
          <select name="categoryName" id="parentSelect" class="form-control" required>
            <option value="">Select parent category</option>
            <?php foreach (array_keys($grouped) as $g): ?>
              <option value="<?= Utils::sanitize($g) ?>"><?= Utils::sanitize($g) ?></option>
            <?php endforeach; ?>
            <option value="__new__">＋ Create new parent category</option>
          </select>
        </div>

        <!-- New parent input (shown when '+ Create new' is selected) -->
        <div class="form-group" id="newParentGroup" style="display:none;">
          <label class="form-label">New Category Name *</label>
          <input type="text" id="newParentInput" class="form-control"
                 placeholder="e.g. Library, Health & Wellness">
        </div>

        <div class="form-group">
          <label class="form-label">Sub-Category Name *</label>
          <input type="text" name="subCategory" class="form-control"
                 placeholder="e.g. Book Returns, Vaccination Drive" required>
        </div>

        <div class="form-group">
          <label class="form-label">Description <span style="font-weight:400;color:var(--text-light)">(optional)</span></label>
          <input type="text" name="description" class="form-control"
                 placeholder="Brief description of this sub-category">
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-outline"
                onclick="document.getElementById('catModal').classList.remove('open')">
          Cancel
        </button>
        <button type="submit" class="btn btn-amber">
          <i class="fa-solid fa-circle-plus"></i> Add Sub-Category
        </button>
      </div>
    </form>
  </div>
</div>

<script>
// New parent category toggle
const parentSelect   = document.getElementById('parentSelect');
const newParentGroup = document.getElementById('newParentGroup');
const newParentInput = document.getElementById('newParentInput');

parentSelect?.addEventListener('change', function () {
  const isNew = this.value === '__new__';
  newParentGroup.style.display = isNew ? '' : 'none';
  newParentInput.required = isNew;
});

// On submit, replace __new__ with the typed name
parentSelect?.closest('form').addEventListener('submit', function () {
  if (parentSelect.value === '__new__') {
    const v = newParentInput.value.trim();
    if (v) {
      // Inject a hidden input with the real name
      const hidden = document.createElement('input');
      hidden.type  = 'hidden';
      hidden.name  = 'categoryName';
      hidden.value = v;
      this.appendChild(hidden);
      parentSelect.name = ''; // disable original select
    }
  }
});

<?php if ($errors && $action === 'create'): ?>
document.addEventListener('DOMContentLoaded', () =>
  document.getElementById('catModal').classList.add('open')
);
<?php endif; ?>
</script>

<?php require_once ROOT_PATH . 'app/core/footer.php'; ?>
