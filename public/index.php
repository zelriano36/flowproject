<?php
require __DIR__ . '/../includes/bootstrap.php';

$user = current_user();
$projects = $tasks = [];
if ($user) {
    $projects = Project::findForUser($user->getId());
    $tasks    = Task::listForUser($user->getId());
}
$labels = ['a_faire' => 'À faire', 'en_cours' => 'En cours', 'terminee' => 'Terminée'];
$projLabels = ['en_cours' => 'En cours', 'termine' => 'Terminé', 'archive' => 'Archivé'];

$pageTitle = 'Accueil';
include __DIR__ . '/../includes/header.php';
?>
<?php if (!$user): ?>
    <section class="hero">
        <h1>Organisez vos projets en équipe</h1>
        <p>Créez des projets, invitez des membres et déplacez vos tâches d'un geste.</p>
        <a class="btn" href="register.php">Créer un compte</a>
        <a class="btn secondary" href="login.php">Se connecter</a>
    </section>
<?php else: ?>
    <div class="page-head">
        <h2>Mes projets</h2>
        <a class="btn" href="project_new.php">+ Nouveau projet</a>
    </div>

    <?php if (!$projects): ?>
        <div class="card">Vous n'êtes membre d'aucun projet. Créez le premier !</div>
    <?php else: ?>
    <div class="grid">
        <?php foreach ($projects as $p): ?>
        <a class="card project-card" href="project.php?id=<?= (int)$p['id'] ?>">
            <h3><?= e($p['name']) ?></h3>
            <p><?= e(mb_strimwidth((string)$p['description'], 0, 90, '…')) ?></p>
            <progress max="<?= max(1, (int)$p['total']) ?>" value="<?= (int)$p['done'] ?>"></progress>
            <p><?= (int)$p['done'] ?>/<?= (int)$p['total'] ?> tâches terminées</p>
            <span class="badge"><?= e($projLabels[$p['status']] ?? $p['status']) ?></span>
            <span class="badge <?= $p['my_role'] === 'manager' ? 'manager' : '' ?>"><?= e($p['my_role']) ?></span>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <h2>Mes tâches</h2>
    <div class="card">
        <?php if (!$tasks): ?>
            Aucune tâche ne vous est assignée.
        <?php else: ?>
        <ul class="task-list">
            <?php foreach ($tasks as $t): ?>
            <li>
                <span><strong><?= e($t['name']) ?></strong><br>
                    <a href="project.php?id=<?= (int)$t['project_id'] ?>"><?= e($t['project_name']) ?></a></span>
                <span class="badge"><?= e($labels[$t['status']] ?? $t['status']) ?></span>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </div>
<?php endif; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>