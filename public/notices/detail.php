<?php
// public/notices/detail.php — Notice Detail View
require_once __DIR__ . '/../../app/config/config.php';
Auth::requireLogin();

$uid  = Auth::id();
$role = Auth::role();
$id   = Utils::getInt('id');
if (!$id) Utils::redirect('public/notices/index.php');

$notice = Database::fetchOne(
    'SELECT n.*,
            nc.categoryName, nc.subCategory,
            ns.scopeName,
            u.fullName  AS authorName,
            u.role      AS authorRole,
            t.fullName  AS targetUserName,
            m.fullName  AS modifierName
     FROM   notices n
     JOIN   notice_categories nc ON n.categoryID   = nc.categoryID
     JOIN   notice_scopes     ns ON n.scopeID      = ns.scopeID
     JOIN   users             u  ON n.createdBy    = u.userID
     LEFT JOIN users          t  ON n.targetUserID = t.userID
     LEFT JOIN users          m  ON n.modifiedBy   = m.userID
     WHERE  n.noticeID = ? AND n.deletedAt IS NULL',
    'i', $id
);

if (!$notice) Utils::redirect('public/notices/index.php');

// Visibility gate for students
if ($role === 'Student') {
    $sn          = $notice['scopeName'];
    $currentUser = Auth::currentUser();
    $myGrade     = $currentUser['grade'] ?? null;
    $ok = ($sn === 'General')
       || ($sn === 'Role Based' && $notice['targetRole']   === 'Student')
       || ($sn === 'Individual'  && $notice['targetUserID'] == $uid)
       || (NoticeHelper::isGradeScope($sn) && $myGrade !== null && $notice['targetGrade'] === $myGrade);
    if (!$ok) Utils::redirect('public/dashboard.php');
}

// Increment view count
Database::query('UPDATE notices SET viewCount = viewCount + 1 WHERE noticeID = ?', 'i', $id);

$catColor = NoticeHelper::categoryColor($notice['categoryName']);
$isUrgent = str_contains($notice['categoryName'], 'Urgent');
$isExp    = NoticeHelper::isExpired($notice['expiryDate']);
$canEdit  = ($role === 'Admin') || ($role === 'Teacher' && $notice['createdBy'] == $uid);

$pageTitle    = 'Notice Details';
$pageSubtitle = Utils::sanitize($notice['title']);
require_once ROOT_PATH . 'app/core/header.php';
?>

<div class="page-header fade-up">
  <div class="page-header-left">
    <h1>Notice Details</h1>
    <div class="breadcrumb">
      <a href="<?= BASE_URL ?>public/dashboard.php">Dashboard</a>
      <i class="fa-solid fa-chevron-right" style="font-size:9px"></i>
      <a href="<?= BASE_URL ?>public/notices/index.php">All Notices</a>
      <i class="fa-solid fa-chevron-right" style="font-size:9px"></i>
      #<?= $id ?>
    </div>
  </div>
  <div style="display:flex;gap:10px;">
    <?php if ($canEdit): ?>
      <a href="<?= BASE_URL ?>public/notices/create.php?id=<?= $id ?>" class="btn btn-outline">
        <i class="fa-solid fa-pen"></i> Edit
      </a>
      <a href="<?= BASE_URL ?>public/notices/delete.php?id=<?= $id ?>" class="btn btn-danger"
         data-confirm="Permanently delete this notice?">
        <i class="fa-solid fa-trash"></i> Delete
      </a>
    <?php endif; ?>
    <a href="<?= BASE_URL ?>public/notices/index.php" class="btn btn-primary">
      <i class="fa-solid fa-arrow-left"></i> Back
    </a>
  </div>
</div>

<div style="max-width:820px;" class="fade-up">
  <div class="notice-detail-card">

    <!-- ── Header band ───────────────────────────────────────── -->
    <div class="notice-detail-header" style="border-left:5px solid <?= $catColor ?>;">
      <div class="meta">
        <span class="tag" style="background:rgba(255,255,255,.1);color:rgba(255,255,255,.85);">
          <i class="fa-solid <?= NoticeHelper::categoryIcon($notice['categoryName']) ?>"></i>
          <?= Utils::sanitize($notice['categoryName']) ?> · <?= Utils::sanitize($notice['subCategory']) ?>
        </span>
        <span class="tag" style="background:rgba(255,255,255,.08);color:rgba(255,255,255,.7);">
          <i class="fa-solid fa-globe"></i> <?= Utils::sanitize($notice['scopeName']) ?>
        </span>
        <?php if ($notice['scopeName'] === 'Role Based' && $notice['targetRole']): ?>
          <span class="tag" style="background:rgba(255,255,255,.08);color:rgba(255,255,255,.7);">
            For: <?= Utils::sanitize($notice['targetRole']) ?>s
          </span>
        <?php elseif ($notice['scopeName'] === 'Individual' && $notice['targetUserName']): ?>
          <span class="tag" style="background:rgba(255,255,255,.08);color:rgba(255,255,255,.7);">
            To: <?= Utils::sanitize($notice['targetUserName']) ?>
          </span>
        <?php elseif (NoticeHelper::isGradeScope($notice['scopeName']) && $notice['targetGrade']): ?>
          <span class="tag" style="background:rgba(255,255,255,.08);color:rgba(255,255,255,.7);">
            <i class="fa-solid fa-layer-group"></i> Grade <?= Utils::sanitize($notice['targetGrade']) ?>
          </span>
        <?php endif; ?>
        <?php if ($isUrgent): ?>
          <span class="tag tag-urgent"><i class="fa-solid fa-bolt"></i> Urgent</span>
        <?php endif; ?>
        <?php if ($isExp): ?>
          <span class="tag tag-expired">Expired</span>
        <?php endif; ?>
      </div>

      <h1><?= Utils::sanitize($notice['title']) ?></h1>

      <div class="date-row">
        <div class="date-item">
          <i class="fa-solid fa-circle-user"></i>
          Posted by
          <strong><?= Utils::sanitize($notice['authorName']) ?></strong>
          (<?= $notice['authorRole'] ?>)
        </div>
        <div class="date-item">
          <i class="fa-regular fa-calendar"></i>
          <strong><?= date('d M Y, H:i', strtotime($notice['publishDate'])) ?></strong>
        </div>
        <?php if ($notice['expiryDate']): ?>
          <div class="date-item">
            <i class="fa-solid fa-calendar-xmark"></i>
            Expires: <strong><?= date('d M Y', strtotime($notice['expiryDate'])) ?></strong>
          </div>
        <?php endif; ?>
        <div class="date-item" style="margin-left:auto;">
          <i class="fa-solid fa-eye"></i>
          <strong><?= $notice['viewCount'] ?></strong> views
        </div>
      </div>
    </div>

    <!-- ── Body ──────────────────────────────────────────────── -->
    <div class="notice-detail-body">
      <?= nl2br(Utils::sanitize($notice['description'])) ?>
    </div>

    <!-- ── Attachment ─────────────────────────────────────────── -->
    <?php if (!empty($notice['attachmentName'])): ?>
    <div class="notice-attachment-section">
      <h4 style="font-size:12px;font-weight:600;letter-spacing:.6px;text-transform:uppercase;color:var(--text-muted);margin-bottom:10px;">
        <i class="fa-solid fa-paperclip" style="margin-right:5px;"></i>Attachment
      </h4>
      <div class="attachment-box">
        <div class="attachment-icon">
          <i class="fa-solid <?= str_contains($notice['attachmentType'] ?? '', 'pdf') ? 'fa-file-pdf' : 'fa-file-image' ?>"></i>
        </div>
        <div>
          <div class="attachment-name"><?= Utils::sanitize($notice['attachmentName']) ?></div>
          <div class="attachment-size"><?= Utils::sanitize($notice['attachmentType'] ?? '') ?></div>
        </div>
        <div style="margin-left:auto;display:flex;gap:8px;">
          <a href="<?= BASE_URL ?>public/notices/attachment.php?id=<?= $id ?>"
             class="btn btn-primary btn-sm">
            <i class="fa-solid fa-download"></i> Download
          </a>
          <?php if (str_contains($notice['attachmentType'] ?? '', 'image')): ?>
            <a href="<?= BASE_URL ?>public/notices/attachment.php?id=<?= $id ?>&inline=1"
               class="btn btn-outline btn-sm" target="_blank">
              <i class="fa-regular fa-eye"></i> Preview
            </a>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- ── Meta footer ────────────────────────────────────────── -->
    <div style="padding:14px 36px;border-top:1px solid var(--border-lt);background:var(--ivory);
                display:flex;align-items:center;justify-content:space-between;
                font-size:12px;color:var(--text-muted);gap:20px;flex-wrap:wrap;">
      <span>Notice #<?= $id ?> &nbsp;·&nbsp; Created <?= date('d M Y', strtotime($notice['createdAt'])) ?></span>
      <?php if ($notice['modifierName']): ?>
        <span>
          Last edited by <strong><?= Utils::sanitize($notice['modifierName']) ?></strong>
          on <?= date('d M Y, H:i', strtotime($notice['modifiedAt'])) ?>
        </span>
      <?php endif; ?>
    </div>

  </div>
</div>

<?php require_once ROOT_PATH . 'app/core/footer.php'; ?>
