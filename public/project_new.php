<?php
require __DIR__ . '/../includes/bootstrap.php';
$user = require_login();

$errors = [];
$name = $description = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if ($name === '' || mb_strlen($name) > 150) $errors[] = 'Nom invalide (1 à 150 caractères).';
    if (mb_strlen($description) > 5000) $errors[] = 'Description trop longue.';

    if (!$errors) {
        $project = Project::create($name, $description, $user->getId());
        flash('Projet créé.');
        header('Location: project.php?id=' . $project->getId());
        exit;
    }
}

$pageTitle = 'Nouveau projet';
include __DIR__ . '/../includes/header.php';
?>
<div class="card form-card">
    <h2>Nouveau projet</h2>
    <?php foreach ($errors as $err): ?><div class="error"><?= e($err) ?></div><?php endforeach; ?>
    <form method="post">
        <?= csrf_field() ?>
        <label for="name">Nom</label>
        <input type="text" id="name" name="name" value="<?= e($name) ?>" required>
        <label for="description">Description</label>
        <textarea id="description" name="description" rows="4"><?= e($description) ?></textarea>
        <p><button type="submit">Créer le projet</button></p>
    </form>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>