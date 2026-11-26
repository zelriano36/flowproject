<?php
require __DIR__ . '/../includes/bootstrap.php';

if (current_user()) { header('Location: index.php'); exit; }

$error = '';
$email = '';
$maxTries = 5;
$lockSeconds = 300;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';

    $tries = $_SESSION['login_tries'] ?? 0;
    $last  = $_SESSION['login_last'] ?? 0;
    if ($tries >= $maxTries && time() - $last < $lockSeconds) {
        $error = 'Trop de tentatives. Réessayez dans quelques minutes.';
    } else {
        if (time() - $last >= $lockSeconds) $tries = 0;
        $user = User::login($email, $pass);
        if ($user) {
            unset($_SESSION['login_tries'], $_SESSION['login_last']);
            login_user($user);
            header('Location: index.php');
            exit;
        }
        $_SESSION['login_tries'] = $tries + 1;
        $_SESSION['login_last'] = time();
        $error = 'Email ou mot de passe incorrect.';
    }
}

$pageTitle = 'Connexion';
include __DIR__ . '/../includes/header.php';
?>
<div class="card form-card">
    <h2>Connexion</h2>
    <?php if ($error): ?><div class="error"><?= e($error) ?></div><?php endif; ?>
    <form method="post">
        <?= csrf_field() ?>
        <label for="email">Email</label>
        <input type="email" id="email" name="email" value="<?= e($email) ?>" required>
        <label for="password">Mot de passe</label>
        <input type="password" id="password" name="password" required>
        <p><button type="submit">Se connecter</button></p>
    </form>
    <p>Pas de compte ? <a href="register.php">Inscription</a></p>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>