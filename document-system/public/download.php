<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

require_login();

$id = (int) query_string('id', 20);
$stmt = db()->prepare(
    'SELECT id, stored_name, original_name, mime_type FROM documents WHERE id = ? LIMIT 1'
);
$stmt->execute([$id]);
$doc = $stmt->fetch();
if (!$doc) {
    flash('error', 'ไม่พบเอกสาร');
    redirect('documents.php');
}

$stored = basename((string) $doc['stored_name']);
if (preg_match('/^[a-f0-9]{32}\.(pdf|doc|docx|xls|xlsx|png|jpg|jpeg)$/', $stored) !== 1) {
    flash('error', 'ไม่พบไฟล์ของเอกสารนี้');
    redirect('documents.php');
}
$root = realpath(UPLOAD_DIR);
$real = $root !== false ? realpath($root . DIRECTORY_SEPARATOR . $stored) : false;
$prefix = $root !== false ? rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR : '';
if ($root === false || $real === false || $prefix === '' || !is_file($real) || !str_starts_with($real, $prefix)) {
    flash('error', 'ไม่พบไฟล์ของเอกสารนี้');
    redirect('documents.php');
}

$allowedMimes = [];
foreach (ALLOWED_MIMES as $mimeList) {
    foreach ($mimeList as $mime) {
        $allowedMimes[$mime] = true;
    }
}
$mime = isset($allowedMimes[(string) $doc['mime_type']]) ? (string) $doc['mime_type'] : 'application/octet-stream';
$original = sanitize_original_name((string) $doc['original_name']);
$fallback = preg_replace('/[^A-Za-z0-9._-]/', '_', $original) ?? 'document';
$fallback = trim($fallback, '._');
if ($fallback === '') {
    $fallback = 'document';
}

header('Content-Type: ' . $mime);
header('Content-Length: ' . (string) filesize($real));
header('Content-Disposition: attachment; filename="' . $fallback . '"; filename*=UTF-8\'\'' . rawurlencode($original));
header('X-Content-Type-Options: nosniff');
header('Content-Transfer-Encoding: binary');
readfile($real);
exit;
