<?php
require __DIR__ . '/../includes/bootstrap.php';
require_login();
session_write_close();   // libère le verrou de session : les images se chargent en parallèle

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$file = $id ? avatar_file($id) : null;
if (!$file || !is_file($file)) {
    http_response_code(404);
    exit;
}

$size  = filesize($file);
$mtime = filemtime($file);
$etag  = '"' . $mtime . '-' . $size . '"';

header('Content-Type: image/jpeg');
header('Cache-Control: private, max-age=86400');
header('ETag: ' . $etag);
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');

if (($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) {
    http_response_code(304);
    exit;
}
header('Content-Length: ' . $size);
readfile($file);