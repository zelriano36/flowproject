<?php
function current_user(): ?User
{
    static $user = false;
    if ($user === false) {
        $user = isset($_SESSION['user_id']) ? User::findById((int)$_SESSION['user_id']) : null;
    }
    return $user;
}

function require_login(): User
{
    $u = current_user();
    if (!$u) {
        header('Location: login.php');
        exit;
    }
    return $u;
}

function login_user(User $u): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = $u->getId();
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 3600, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

function flash(string $msg = null): ?string
{
    if ($msg !== null) {
        $_SESSION['flash'] = $msg;
        return null;
    }
    $m = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $m;
}