<?php
// public/dashboard.php
require_once __DIR__ . '/../app/config/config.php';
Auth::requireLogin();

$user = Auth::currentUser();
$uid  = Auth::id();
$role = Auth::role();

$filters = [
    'categoryID' => Utils::getInt('categoryID') ?: null,
    'scopeID'    => Utils::getInt('scopeID')    ?: null,
    'search'     => Utils::get('search'),
];

$notices    = ($role === 'Admin') ? NoticeHelper::getAllNotices($filters)                   : NoticeHelper::getVisibleNotices($uid, $role, $filters);
$allNotices = ($role === 'Admin') ? NoticeHelper::getAllNotices()                           : NoticeHelper::getVisibleNotices($uid, $role);
$totalUrgent = count(array_filter($allNotices, fn($n) => str_contains($n['categoryName'], 'Urgent')));

$catBreakdown = [];
foreach ($allNotices as $n) {
    $catBreakdown[$n['categoryName']] = ($catBreakdown[$n['categoryName']] ?? 0) + 1;
}
arsort($catBreakdown);

$categories = Database::fetchAll('SELECT DISTINCT categoryName FROM notice_categories WHERE isActive=1 ORDER BY categoryName');
$scopes     = NoticeHelper::getScopes();
$myCount    = Database::fetchOne('SELECT COUNT(*) AS c FROM notices WHERE createdBy=? AND deletedAt IS NULL','i',$uid)['c'] ?? 0;

$pageTitle = 'Dashboard'; $pageSubtitle = 'Academic Year 2024–25';
require_once ROOT_PATH . 'app/core/header.php';
?>

<?php $f = Utils::flash('success'); if ($f): ?><div class="alert alert-success fade-up"><i class="fa-solid fa-circle-check"></i><?= Utils::sanitize($f) ?></div><?php endif; ?>

<!-- Stats -->
<div class="stats-row fade-up">
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(13,27,42,.07);"><i class="fa-solid fa-newspaper" style="color:var(--navy)"></i></div>
    <div class="stat-body"><div class="stat-num"><?= count($allNotices) ?></div><div class="stat-label">Active Notices</div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(217,79,61,.1);"><i class="fa-solid fa-triangle-exclamation" style="color:var(--red)"></i></div>
    <div class="stat-body"><div class="stat-num"><?= $totalUrgent ?></div><div class="stat-label">Urgent Notices</div></div>
  </div>
  <?php if (in_array($role, ['Admin','Teacher'])): ?>
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(79,142,247,.1);"><i class="fa-solid fa-file-lines" style="color:#4f8ef7"></i></div>
    <div class="stat-body"><div class="stat-num"><?= $myCount ?></div><div class="stat-label">My Notices</div></div>
  </div>
  <?php endif; ?>
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(46,158,104,.1);"><i class="fa-solid fa-tags" style="color:var(--green)"></i></div>
    <div class="stat-body"><div class="stat-num"><?= count($catBreakdown) ?></div><div class="stat-label">Categories Active</div></div>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 300px;gap:24px;align-items:start;">
  <div>
    <!-- Filters -->
    <form method="GET" class="filter-bar fade-up">
      <label><i class="fa-solid fa-filter" style="margin-right:5px"></i>Filter</label>
      <select name="categoryID" class="filter-select">
        <option value="">All Categories</option>
        <?php foreach ($categories as $c): ?>
          <option value="<?= $c['categoryName'] ?>" <?= (Utils::get('categoryID') === $c['categoryName']) ? 'selected' : '' ?>><?= Utils::sanitize($c['categoryName']) ?></option>
        <?php endforeach; ?>
      </select>
      <?php if ($role !== 'Student'): ?>
      <select name="scopeID" class="filter-select">
        <option value="">All Scopes</option>
        <?php foreach ($scopes as $s): ?>
          <option value="<?= $s['scopeID'] ?>" <?= (Utils::getInt('scopeID') === (int)$s['scopeID']) ? 'selected' : '' ?>><?= Utils::sanitize($s['scopeName']) ?></option>
        <?php endforeach; ?>
      </select>
      <?php endif; ?>
      <div class="filter-search-wrap">
        <i class="fa-solid fa-magnifying-glass fs-icon"></i>
        <input type="text" name="search" class="filter-search form-control" placeholder="Search notices…" value="<?= Utils::sanitize($filters['search']) ?>" style="padding-left:36px;">
      </div>
      <button type="submit" class="btn btn-primary btn-sm">Search</button>
      <?php if (array_filter($filters)): ?><a href="dashboard.php" class="btn btn-outline btn-sm">Clear</a><?php endif; ?>
    </form>

    <!-- Notice cards -->
    <?php if (empty($notices)): ?>
      <div class="empty-state fade-up"><i class="fa-regular fa-file-lines"></i><h3>No Notices Found</h3><p>Nothing matches your current filters.</p></div>
    <?php else: ?>
      <div class="notices-grid">
        <?php foreach ($notices as $n):
          $catColor = NoticeHelper::categoryColor($n['categoryName']);
          $isUrgent = str_contains($n['categoryName'], 'Urgent');
          $isExp    = NoticeHelper::isExpired($n['expiryDate']);
        ?>
        <div class="notice-card fade-up <?= $isUrgent?'urgent':'' ?> <?= $isExp?'expired':'' ?>" style="--cat-color:<?= $catColor ?>">
          <div class="notice-card-top">
            <div class="notice-card-meta">
              <span class="tag tag-category"><i class="fa-solid <?= NoticeHelper::categoryIcon($n['categoryName']) ?>"></i> <?= Utils::sanitize($n['subCategory']) ?></span>
              <?php if ($role !== 'Student'): ?><span class="tag tag-scope-<?= strtolower(explode(' ',$n['scopeName'])[0]) ?>"><?= Utils::sanitize($n['scopeName']) ?></span><?php endif; ?>
              <?php if ($isUrgent): ?><span class="tag tag-urgent"><i class="fa-solid fa-bolt"></i>Urgent</span><?php endif; ?>
              <?php if ($isExp):   ?><span class="tag tag-expired">Expired</span><?php endif; ?>
            </div>
            <h3 class="notice-card-title"><a href="<?= BASE_URL ?>public/notices/detail.php?id=<?= $n['noticeID'] ?>"><?= Utils::sanitize($n['title']) ?></a></h3>
            <p class="notice-card-excerpt"><?= Utils::sanitize(substr($n['description'], 0, 140)) ?></p>
          </div>
          <div class="notice-card-bottom">
            <div class="notice-card-author">
              <div class="mini-avatar"><?= strtoupper(substr($n['authorName'],0,1)) ?></div>
              <span><?= Utils::sanitize($n['authorName']) ?></span>
            </div>
            <div style="display:flex;align-items:center;gap:12px;">
              <span class="view-count"><i class="fa-solid fa-eye"></i><?= $n['viewCount'] ?></span>
              <span class="notice-card-time"><?= Utils::timeAgo($n['publishDate']) ?></span>
              <?php if ($role === 'Admin' || ($role === 'Teacher' && $n['createdBy'] == $uid)): ?>
              <div class="notice-actions">
                <a href="<?= BASE_URL ?>public/notices/create.php?id=<?= $n['noticeID'] ?>" class="btn btn-outline btn-sm btn-icon" data-tip="Edit"><i class="fa-solid fa-pen"></i></a>
                <a href="<?= BASE_URL ?>public/notices/delete.php?id=<?= $n['noticeID'] ?>" class="btn btn-danger btn-sm btn-icon" data-tip="Delete" data-confirm="Delete this notice?"><i class="fa-solid fa-trash"></i></a>
              </div>
              <?php endif; ?>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <!-- Right sidebar -->
  <aside>
    <div class="card fade-up">
      <div class="card-header"><h2>By Category</h2></div>
      <div style="padding:16px 20px;">
        <?php foreach (array_slice($catBreakdown, 0, 8, true) as $cat => $cnt): ?>
        <div style="margin-bottom:12px;">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:5px;">
            <span style="font-size:12.5px;font-weight:500;color:var(--text-main);display:flex;align-items:center;gap:7px;">
              <i class="fa-solid <?= NoticeHelper::categoryIcon($cat) ?>" style="color:<?= NoticeHelper::categoryColor($cat) ?>;width:14px;text-align:center;"></i>
              <?= Utils::sanitize($cat) ?>
            </span>
            <span style="font-size:11px;font-family:var(--font-mono);color:var(--text-muted);"><?= $cnt ?></span>
          </div>
          <div style="height:5px;background:var(--ivory-2);border-radius:3px;overflow:hidden;">
            <div style="height:100%;width:<?= min(100, round($cnt / max(1, max($catBreakdown)) * 100)) ?>%;background:<?= NoticeHelper::categoryColor($cat) ?>;border-radius:3px;"></div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <?php if (in_array($role, ['Admin','Teacher'])): ?>
    <div class="card fade-up" style="margin-top:20px;">
      <div class="card-body" style="padding:20px;">
        <p style="font-size:13px;color:var(--text-muted);margin-bottom:16px;">Post a new notice to keep everyone informed.</p>
        <a href="<?= BASE_URL ?>public/notices/create.php" class="btn btn-primary btn-block"><i class="fa-solid fa-circle-plus"></i> New Notice</a>
      </div>
    </div>
    <?php endif; ?>
  </aside>
</div>

<?php require_once ROOT_PATH . 'app/core/footer.php'; ?>
