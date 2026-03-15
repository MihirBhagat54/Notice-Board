<?php
// public/notices/attachment.php — Serve BLOB attachment securely
require_once __DIR__ . '/../../app/config/config.php';
Auth::requireLogin();

$id     = Utils::getInt('id');
$inline = (bool)Utils::getInt('inline');

if (!$id) { http_response_code(400); exit('Bad request.'); }

$row = Database::fetchOne(
    'SELECT attachment, attachmentName, attachmentType
     FROM   notices
     WHERE  noticeID = ? AND deletedAt IS NULL AND attachment IS NOT NULL',
    'i', $id
);

if (!$row || empty($row['attachment'])) {
    http_response_code(404);
    exit('Attachment not found.');
}

$disposition = $inline ? 'inline' : 'attachment';
$mime        = $row['attachmentType'] ?: 'application/octet-stream';
$filename    = addslashes($row['attachmentName'] ?: 'download');

header('Content-Type: '        . $mime);
header('Content-Disposition: ' . $disposition . '; filename="' . $filename . '"');
header('Content-Length: '      . strlen($row['attachment']));
header('Cache-Control: private, max-age=3600');
header('X-Content-Type-Options: nosniff');

echo $row['attachment'];
exit;
