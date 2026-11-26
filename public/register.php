<?php
require __DIR__ . '/../includes/bootstrap.php';

$errors = [];
$name = $email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $name  = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';
    $pass2 = $_POST['password2'] ?? '';

    if ($name === '' || mb_strlen($name) > 100) $errors[] = 'Nom invalide.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email invalide.';
    if (strlen($pass) < 8) $errors[] = 'Mot de passe : 8 caractères minimum.';
    if ($pass !== $pass2) $errors[] = 'Les mots de passe ne correspondent pas.';

    if (!$errors) {
        $user = User::register($name, $email, $pass);
        if ($user) {
            login_user($user);
            flash('Compte créé, bienvenue !');
            header('Location: index.php');
            exit;
        }
        $errors[] = 'Cet email est déjà utilisé.';
    }
}

$pageTitle = 'Inscription';
include __DIR__ . '/../includes/header.php';
?>
<div class="card form-card">
    <h2>Inscription</h2>
    <?php foreach ($errors as $err): ?>
        <div class="error"><?= e($err) ?></div>
    <?php endforeach; ?>
    <form method="post">
        <?= csrf_field() ?>
        <label for="name">Nom</label>
        <input type="text" id="name" name="name" value="<?= e($name) ?>" required>
        <label for="email">Email</label>
        <input type="email" id="email" name="email" value="<?= e($email) ?>" required>
        <label for="password">Mot de passe</label>
        <input type="password" id="password" name="password" required>
        <label for="password2">Confirmer le mot de passe</label>
        <input type="password" id="password2" name="password2" required>
        <p><button type="submit">Créer mon compte</button></p>
    </form>
    <p>Déjà inscrit ? <a href="login.php">Connexion</a></p>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>