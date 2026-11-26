<?php
$user = current_user();
require_once __DIR__ . '/admin.php';
$assets = dirname(__DIR__) . '/public/assets';
$siteName = site_name();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(isset($pageTitle) ? $pageTitle . ' · ' . $siteName : $siteName) ?></title>
    <?php if (branding_has('favicon')): ?>
    <link rel="icon" type="image/png" href="<?= e(branding_url('favicon')) ?>">
    <?php else: ?>
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ctext y='.9em' font-size='90'%3E%E2%97%86%3C/text%3E%3C/svg%3E">
    <?php endif; ?>
    <link rel="stylesheet" href="assets/css/style.css?v=<?= (int)@filemtime($assets . '/css/style.css') ?>">
    <script src="assets/js/theme.js?v=<?= (int)@filemtime($assets . '/js/theme.js') ?>"></script>
    <script src="assets/js/app.js?v=<?= (int)@filemtime($assets . '/js/app.js') ?>" defer></script>
</head>
<body>
<header class="topbar">
    <a class="brand" href="index.php">
        <?php if (branding_has('logo')): ?>
            <img class="brand-logo" src="<?= e(branding_url('logo')) ?>" alt="">
        <?php else: ?>◆<?php endif; ?>
        <span><?= e($siteName) ?></span>
    </a>
    <nav>
        <?php if ($user && is_admin($user->getId())): ?>
            <a href="admin.php">Admin</a>
        <?php endif; ?>
        <select id="theme-select" class="theme-select" aria-label="Thème">
            <option value="auto">🖥️ Système</option>
            <option value="light">☀️ Clair</option>
            <option value="dark">🌙 Sombre</option>
        </select>
        <?php if ($user): ?>
            <a class="btn small" href="project_new.php">+ Projet</a>
            <a class="me" href="profile.php" title="Voir le profil">
                <?= avatar($user->getName(), $user->getAvatarColor(), '', $user->getId()) ?>
                <span class="me-label">Voir le profil</span>
            </a>
            <form method="post" action="logout.php" class="inline">
                <?= csrf_field() ?>
                <button type="submit" class="ghost-btn small">Déconnexion</button>
            </form>
        <?php else: ?>
            <a href="login.php">Connexion</a>
            <a class="btn small" href="register.php">Inscription</a>
        <?php endif; ?>
    </nav>
</header>
<main class="container">
<?php if ($m = flash()): ?>
    <div class="alert"><?= e($m) ?></div>
<?php endif; ?>