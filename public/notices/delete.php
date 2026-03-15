<?php
// public/notices/delete.php — Soft-delete handler
require_once __DIR__ . '/../../app/config/config.php';
Auth::requireLogin();
Auth::requireRole(['Admin', 'Teacher']);

$uid = Auth::id();
$id  = Utils::getInt('id');

if ($id) {
    $notice = Database::fetchOne(
        'SELECT createdBy FROM notices WHERE noticeID = ? AND deletedAt IS NULL', 'i', $id
    );
    if ($notice && (Auth::role() === 'Admin' || $notice['createdBy'] == $uid)) {
        NoticeHelper::softDelete($id, $uid);
        Utils::flash('success', 'Notice deleted successfully.');
    }
}

Utils::redirect('public/notices/manage.php');
