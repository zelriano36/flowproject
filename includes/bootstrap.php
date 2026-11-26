<?php
spl_autoload_register(function (string $class) {
    $file = __DIR__ . '/../src/' . $class . '.php';
    if (is_file($file)) require $file;
});

ini_set('display_errors', '1');
error_reporting(E_ALL);
ini_set('log_errors', '1');

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']),
    ]);
    session_start();
}

function e(?string $s): string
{
    return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
}
require __DIR__ . '/csrf.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/avatar_files.php';
require __DIR__ . '/image_utils.php';
require __DIR__ . '/branding.php';
header("Content-Security-Policy: default-src 'self'; frame-ancestors 'none'; form-action 'self'");
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

function initials(?string $name): string
{
    $parts = preg_split('/\s+/', trim($name ?? ''));
    $i = '';
    foreach (array_slice($parts, 0, 2) as $p) {
        $i .= mb_strtoupper(mb_substr($p, 0, 1));
    }
    return $i !== '' ? $i : '?';
}

/**
 * Avatar : photo si l'utilisateur en a une (il faut alors passer son id),
 * sinon initiales sur fond coloré.
 */
function avatar(?string $name, int $color = 0, string $extra = '', ?int $userId = null): string
{
    $cls = $extra ? ' ' . e($extra) : '';

    if ($userId !== null && $userId > 0) {
        $file = avatar_file($userId);
        if (is_file($file)) {
            return '<img class="avatar avatar-img' . $cls . '" src="avatar.php?id=' . $userId
                 . '&amp;v=' . (int)filemtime($file) . '" alt="' . e($name) . '" title="' . e($name) . '" loading="lazy">';
        }
    }
    return '<span class="avatar c' . ($color % 8) . $cls . '" title="' . e($name) . '">'
         . e(initials($name)) . '</span>';
}