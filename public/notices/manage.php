<?php
// public/notices/manage.php — Notice management table (Admin & Teacher)
require_once __DIR__ . '/../../app/config/config.php';
Auth::requireLogin();
Auth::requireRole(['Admin', 'Teacher']);

$uid  = Auth::id();
$role = Auth::role();

// Build query
$search  = Utils::get('search');
$catID   = Utils::getInt('categoryID') ?: null;
$scopeID = Utils::getInt('scopeID') ?: null;

$where  = ['n.deletedAt IS NULL'];
$types  = '';
$params = [];

// Teachers only see their own notices; Admins see all
if ($role === 'Teacher') {
    $where[]  = 'n.createdBy = ?';
    $types   .= 'i';
    $params[] = $uid;
}
if ($catID) {
    $where[]  = 'n.categoryID = ?';
    $types   .= 'i';
    $params[] = $catID;
}
if ($scopeID) {
    $where[]  = 'n.scopeID = ?';
    $types   .= 'i';
    $params[] = $scopeID;
}
if ($search) {
    $where[]  = '(n.title LIKE ? OR n.description LIKE ?)';
    $types   .= 'ss';
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

$whereSQL = implode(' AND ', $where);
$notices  = Database::fetchAll(
    "SELECT n.*,
            nc.categoryName, nc.subCategory,
            ns.scopeName,
            u.fullName AS authorName
     FROM   notices n
     JOIN   notice_categories nc ON n.categoryID = nc.categoryID
     JOIN   notice_scopes     ns ON n.scopeID    = ns.scopeID
     JOIN   users             u  ON n.createdBy  = u.userID
     WHERE  {$whereSQL}
     ORDER  BY n.createdAt DESC",
    $types, ...$params
);

$categories = Database::fetchAll('SELECT categoryID, categoryName FROM notice_categories WHERE isActive=1 ORDER BY categoryName');
$scopes     = NoticeHelper::getScopes();

// Stats for this view
$totalViews  = array_sum(array_column($notices, 'viewCount'));
$activeCount = count(array_filter($notices, fn($n) => $n['active'] && !NoticeHelper::isExpired($n['expiryDate'])));
$expiredCount = count(array_filter($notices, fn($n) => NoticeHelper::isExpired($n['expiryDate'])));

$pageTitle    = $role === 'Admin' ? 'All Notices' : 'My Notices';
$pageSubtitle = count($notices) . ' notice' . (count($notices) !== 1 ? 's' : '');
require_once ROOT_PATH . 'app/core/header.php';
?>

<div class="page-header fade-up">
  <div class="page-header-left">
    <h1><?= $role === 'Admin' ? 'Manage All Notices' : 'My Notices' ?></h1>
    <div class="breadcrumb">
      <a href="<?= BASE_URL ?>public/dashboard.php">Dashboard</a>
      <i class="fa-solid fa-chevron-right" style="font-size:9px"></i>
      <?= $role === 'Admin' ? 'All Notices' : 'My Notices' ?>
    </div>
  </div>
  <a href="<?= BASE_URL ?>public/notices/create.php" class="btn btn-amber">
    <i class="fa-solid fa-circle-plus"></i> New Notice
  </a>
</div>

<?php $f = Utils::flash('success'); if ($f): ?>
  <div class="alert alert-success fade-up"><i class="fa-solid fa-circle-check"></i><?= Utils::sanitize($f) ?></div>
<?php endif; ?>

<!-- Stats row -->
<div class="stats-row fade-up">
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(13,27,42,.07);">
      <i class="fa-solid fa-newspaper" style="color:var(--navy)"></i>
    </div>
    <div class="stat-body">
      <div class="stat-num"><?= count($notices) ?></div>
      <div class="stat-label">Total Notices</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(46,158,104,.1);">
      <i class="fa-solid fa-circle-check" style="color:var(--green)"></i>
    </div>
    <div class="stat-body">
      <div class="stat-num"><?= $activeCount ?></div>
      <div class="stat-label">Active</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(217,79,61,.1);">
      <i class="fa-solid fa-clock" style="color:var(--red)"></i>
    </div>
    <div class="stat-body">
      <div class="stat-num"><?= $expiredCount ?></div>
      <div class="stat-label">Expired</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(79,142,247,.1);">
      <i class="fa-solid fa-eye" style="color:#4f8ef7"></i>
    </div>
    <div class="stat-body">
      <div class="stat-num"><?= $totalViews ?></div>
      <div class="stat-label">Total Views</div>
    </div>
  </div>
</div>

<!-- Filter bar -->
<form method="GET" class="filter-bar fade-up">
  <label><i class="fa-solid fa-filter" style="margin-right:5px"></i>Filter</label>
  <select name="categoryID" class="filter-select">
    <option value="">All Categories</option>
    <?php foreach ($categories as $c): ?>
      <option value="<?= (int)$c['categoryID'] ?>" <?= (Utils::getInt('categoryID') === (int)$c['categoryID']) ? 'selected' : '' ?>>
        <?= Utils::sanitize($c['categoryName']) ?>
      </option>
    <?php endforeach; ?>
  </select>
  <select name="scopeID" class="filter-select">
    <option value="">All Scopes</option>
    <?php foreach ($scopes as $s): ?>
      <option value="<?= $s['scopeID'] ?>" <?= ($scopeID == $s['scopeID']) ? 'selected' : '' ?>>
        <?= Utils::sanitize($s['scopeName']) ?>
      </option>
    <?php endforeach; ?>
  </select>
  <div class="filter-search-wrap">
    <i class="fa-solid fa-magnifying-glass fs-icon"></i>
    <input type="text" name="search" class="filter-search form-control"
           placeholder="Search notices…" value="<?= Utils::sanitize($search) ?>"
           style="padding-left:36px;">
  </div>
  <button type="submit" class="btn btn-primary btn-sm">Search</button>
  <?php if ($search || $catID || $scopeID): ?>
    <a href="manage.php" class="btn btn-outline btn-sm">Clear</a>
  <?php endif; ?>
</form>

<!-- Table -->
<?php if (empty($notices)): ?>
  <div class="empty-state fade-up">
    <i class="fa-solid fa-file-circle-plus"></i>
    <h3>No Notices Found</h3>
    <p>
      <?= $search || $catID || $scopeID
          ? 'No notices match your current filters.'
          : 'You haven\'t published any notices yet.' ?>
    </p>
    <a href="<?= BASE_URL ?>public/notices/create.php" class="btn btn-amber" style="margin-top:16px;">
      <i class="fa-solid fa-circle-plus"></i> Publish First Notice
    </a>
  </div>
<?php else: ?>
  <div class="table-wrap fade-up">
    <table class="data-table">
      <thead>
        <tr>
          <th style="width:48px;">#</th>
          <th>Title</th>
          <th>Category</th>
          <th>Scope</th>
          <?php if ($role === 'Admin'): ?><th>Author</th><?php endif; ?>
          <th>Published</th>
          <th>Expires</th>
          <th>Views</th>
          <th>Status</th>
          <th style="width:120px;">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($notices as $n):
          $isExp = NoticeHelper::isExpired($n['expiryDate']);
        ?>
        <tr>
          <td style="color:var(--text-light);font-family:var(--font-mono);font-size:12px;">
            <?= $n['noticeID'] ?>
          </td>

          <td>
            <a href="<?= BASE_URL ?>public/notices/detail.php?id=<?= $n['noticeID'] ?>"
               style="font-weight:500;color:var(--navy);">
              <?= Utils::sanitize(mb_strimwidth($n['title'], 0, 52, '…')) ?>
            </a>
            <?php if (!empty($n['attachmentName'])): ?>
              <i class="fa-solid fa-paperclip"
                 style="font-size:11px;color:var(--text-light);margin-left:5px;"
                 title="Has attachment"></i>
            <?php endif; ?>
          </td>

          <td>
            <span class="tag tag-category" style="font-size:11px;">
              <i class="fa-solid <?= NoticeHelper::categoryIcon($n['categoryName']) ?>"></i>
              <?= Utils::sanitize($n['subCategory']) ?>
            </span>
          </td>

          <td>
            <span class="tag tag-scope-<?= preg_match('/^Student Grade/', $n['scopeName']) ? 'grade' : strtolower(explode(' ', $n['scopeName'])[0]) ?>"
                  style="font-size:11px;">
              <?= Utils::sanitize($n['scopeName']) ?>
            </span>
          </td>

          <?php if ($role === 'Admin'): ?>
          <td>
            <div style="display:flex;align-items:center;gap:8px;">
              <div style="width:26px;height:26px;border-radius:50%;background:var(--navy);color:var(--amber);display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;flex-shrink:0;">
                <?= strtoupper(substr($n['authorName'], 0, 1)) ?>
              </div>
              <span style="font-size:13px;"><?= Utils::sanitize($n['authorName']) ?></span>
            </div>
          </td>
          <?php endif; ?>

          <td style="font-size:12.5px;color:var(--text-muted);white-space:nowrap;">
            <?= date('d M Y', strtotime($n['publishDate'])) ?>
          </td>

          <td style="font-size:12.5px;color:var(--text-muted);white-space:nowrap;">
            <?= $n['expiryDate']
                ? date('d M Y', strtotime($n['expiryDate']))
                : '<span style="color:var(--text-light)">—</span>' ?>
          </td>

          <td>
            <span class="view-count">
              <i class="fa-solid fa-eye"></i><?= $n['viewCount'] ?>
            </span>
          </td>

          <td>
            <?php if ($isExp): ?>
              <span class="tag tag-expired">Expired</span>
            <?php elseif ($n['active']): ?>
              <span class="tag" style="background:rgba(46,158,104,.1);color:#1c7a4e;font-size:11px;">Active</span>
            <?php else: ?>
              <span class="tag tag-expired">Inactive</span>
            <?php endif; ?>
          </td>

          <td>
            <div style="display:flex;gap:5px;">
              <a href="<?= BASE_URL ?>public/notices/detail.php?id=<?= $n['noticeID'] ?>"
                 class="btn btn-outline btn-sm btn-icon" data-tip="View">
                <i class="fa-regular fa-eye"></i>
              </a>
              <?php if ($role === 'Admin' || $n['createdBy'] == $uid): ?>
                <a href="<?= BASE_URL ?>public/notices/create.php?id=<?= $n['noticeID'] ?>"
                   class="btn btn-outline btn-sm btn-icon" data-tip="Edit">
                  <i class="fa-solid fa-pen"></i>
                </a>
                <a href="<?= BASE_URL ?>public/notices/delete.php?id=<?= $n['noticeID'] ?>"
                   class="btn btn-danger btn-sm btn-icon" data-tip="Delete"
                   data-confirm="Delete this notice permanently?">
                  <i class="fa-solid fa-trash"></i>
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

<?php require_once ROOT_PATH . 'app/core/footer.php'; ?>
