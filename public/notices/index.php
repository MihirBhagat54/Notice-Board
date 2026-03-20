<?php
// public/notices/index.php
require_once __DIR__ . '/../../app/config/config.php';
Auth::requireLogin();

$uid  = Auth::id(); $role = Auth::role();
$user  = Auth::currentUser();
$grade = $user['grade'] ?? null;
$filters = ['categoryID' => Utils::getInt('categoryID') ?: null, 'scopeID' => Utils::getInt('scopeID') ?: null, 'search' => Utils::get('search')];
$notices    = ($role === 'Admin') ? NoticeHelper::getAllNotices($filters) : NoticeHelper::getVisibleNotices($uid, $role, $grade, $filters);
$categories = Database::fetchAll('SELECT DISTINCT categoryName FROM notice_categories WHERE isActive=1 ORDER BY categoryName');
$scopes     = NoticeHelper::getScopes();

$perPage = 12; $total = count($notices); $page = max(1, Utils::getInt('page', 1));
$paged = array_slice($notices, ($page-1)*$perPage, $perPage); $pages = (int)ceil($total/$perPage);

$pageTitle = 'All Notices'; $pageSubtitle = $total.' notice'.($total!==1?'s':'').' found';
require_once ROOT_PATH . 'app/core/header.php';
?>

<div class="page-header fade-up">
  <div class="page-header-left">
    <h1>Notice Board</h1>
    <div class="breadcrumb"><a href="<?= BASE_URL ?>public/dashboard.php">Dashboard</a><i class="fa-solid fa-chevron-right" style="font-size:9px"></i>All Notices</div>
  </div>
  <?php if (in_array($role,['Admin','Teacher'])): ?><a href="<?= BASE_URL ?>public/notices/create.php" class="btn btn-amber"><i class="fa-solid fa-circle-plus"></i> New Notice</a><?php endif; ?>
</div>

<form method="GET" class="filter-bar fade-up">
  <label><i class="fa-solid fa-filter" style="margin-right:5px"></i>Filter</label>
  <select name="categoryID" class="filter-select">
    <option value="">All Categories</option>
    <?php foreach ($categories as $c): ?><option value="<?= $c['categoryName'] ?>" <?= (Utils::get('categoryID')===$c['categoryName'])?'selected':'' ?>><?= Utils::sanitize($c['categoryName']) ?></option><?php endforeach; ?>
  </select>
  <?php if ($role !== 'Student'): ?>
  <select name="scopeID" class="filter-select">
    <option value="">All Scopes</option>
    <?php foreach ($scopes as $s): ?><option value="<?= $s['scopeID'] ?>" <?= (Utils::getInt('scopeID')==(int)$s['scopeID'])?'selected':'' ?>><?= Utils::sanitize($s['scopeName']) ?></option><?php endforeach; ?>
  </select>
  <?php endif; ?>
  <div class="filter-search-wrap"><i class="fa-solid fa-magnifying-glass fs-icon"></i><input type="text" name="search" class="filter-search form-control" placeholder="Search notices…" value="<?= Utils::sanitize($filters['search']) ?>" style="padding-left:36px;"></div>
  <button type="submit" class="btn btn-primary btn-sm">Search</button>
  <?php if (array_filter($filters)): ?><a href="index.php" class="btn btn-outline btn-sm">Clear</a><?php endif; ?>
</form>

<?php if (empty($paged)): ?>
  <div class="empty-state fade-up"><i class="fa-regular fa-file-lines"></i><h3>No Notices Found</h3><p>No notices match your current filters.</p></div>
<?php else: ?>
  <div class="notices-grid">
    <?php foreach ($paged as $n):
      $catColor=$n['isUrgent']=str_contains($n['categoryName'],'Urgent'); $catColor=NoticeHelper::categoryColor($n['categoryName']);
      $isExp=NoticeHelper::isExpired($n['expiryDate']); $isUrgent=str_contains($n['categoryName'],'Urgent');
    ?>
    <div class="notice-card fade-up <?= $isUrgent?'urgent':'' ?> <?= $isExp?'expired':'' ?>" style="--cat-color:<?= $catColor ?>">
      <div class="notice-card-top">
        <div class="notice-card-meta">
          <span class="tag tag-category"><i class="fa-solid <?= NoticeHelper::categoryIcon($n['categoryName']) ?>"></i> <?= Utils::sanitize($n['subCategory']) ?></span>
          <?php if ($role!=='Student'): ?><span class="tag tag-scope-<?= preg_match('/^Student Grade/', $n['scopeName']) ? 'grade' : strtolower(explode(' ', $n['scopeName'])[0]) ?>"><?= Utils::sanitize($n['scopeName']) ?></span><?php endif; ?>
          <?php if ($isUrgent): ?><span class="tag tag-urgent"><i class="fa-solid fa-bolt"></i>Urgent</span><?php endif; ?>
          <?php if ($isExp):   ?><span class="tag tag-expired">Expired</span><?php endif; ?>
        </div>
        <h3 class="notice-card-title"><a href="<?= BASE_URL ?>public/notices/detail.php?id=<?= $n['noticeID'] ?>"><?= Utils::sanitize($n['title']) ?></a></h3>
        <p class="notice-card-excerpt"><?= Utils::sanitize(substr($n['description'],0,140)) ?></p>
      </div>
      <div class="notice-card-bottom">
        <div class="notice-card-author"><div class="mini-avatar"><?= strtoupper(substr($n['authorName'],0,1)) ?></div><span><?= Utils::sanitize($n['authorName']) ?></span></div>
        <div style="display:flex;align-items:center;gap:12px;">
          <span class="view-count"><i class="fa-solid fa-eye"></i><?= $n['viewCount'] ?></span>
          <span class="notice-card-time"><?= Utils::timeAgo($n['publishDate']) ?></span>
          <?php if ($role==='Admin'||($role==='Teacher'&&$n['createdBy']==$uid)): ?>
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

  <?php if ($pages > 1): ?>
  <div class="pagination">
    <a href="?<?= http_build_query(array_merge($_GET,['page'=>$page-1])) ?>" class="page-link <?= $page<=1?'disabled':'' ?>"><i class="fa-solid fa-chevron-left"></i></a>
    <?php for ($p=1;$p<=$pages;$p++): ?><a href="?<?= http_build_query(array_merge($_GET,['page'=>$p])) ?>" class="page-link <?= $p===$page?'active':'' ?>"><?= $p ?></a><?php endfor; ?>
    <a href="?<?= http_build_query(array_merge($_GET,['page'=>$page+1])) ?>" class="page-link <?= $page>=$pages?'disabled':'' ?>"><i class="fa-solid fa-chevron-right"></i></a>
  </div>
  <?php endif; ?>
<?php endif; ?>

<?php require_once ROOT_PATH . 'app/core/footer.php'; ?>
