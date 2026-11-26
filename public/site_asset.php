<?php
$key = $_GET['f'] ?? '';
if (!in_array($key, ['logo', 'favicon'], true)) {
    http_response_code(404);
    exit;
}
$file = dirname(__DIR__) . '/storage/branding/' . $key . '.png';
if (!is_file($file)) {
    http_response_code(404);
    exit;
}
header('Content-Type: image/png');
header('Content-Length: ' . filesize($file));
header('Cache-Control: public, max-age=31536000, immutable');
header('X-Content-Type-Options: nosniff');
readfile($file);