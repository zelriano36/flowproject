<?php
require __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/admin.php';
$user = require_login();
$uid = $user->getId();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$img = $id ? TaskImage::find($id) : null;
if (!$img) {
    http_response_code(404);
    exit;
}

$project = Project::findById((int)$img['project_id']);
if (!$project || !($project->isMember($uid) || is_admin($uid))) {
    http_response_code(404);
    exit;
}
session_write_close();

$file = TaskImage::filePath($img['file_name'], isset($_GET['t']));
if ($file === null || !is_file($file)) {
    http_response_code(404);
    exit;
}

$size = filesize($file);
$etag = '"' . $img['file_name'] . '-' . $size . '"';

header('Content-Type: image/jpeg');
header('Cache-Control: private, max-age=604800, immutable');
header('ETag: ' . $etag);
if (($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) {
    http_response_code(304);
    exit;
}
header('Content-Length: ' . $size);
readfile($file);