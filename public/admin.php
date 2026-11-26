<?php
require __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/admin.php';
$admin = require_admin();
$pdo = Database::get();

$stats = [
    'Utilisateurs' => (int)$pdo->query('SELECT COUNT(*) FROM `user`')->fetchColumn(),
    'Projets'      => (int)$pdo->query('SELECT COUNT(*) FROM project')->fetchColumn(),
    'Tâches'       => (int)$pdo->query('SELECT COUNT(*) FROM task')->fetchColumn(),
    'Tâches terminées' => (int)$pdo->query("SELECT COUNT(*) FROM task WHERE status = 'terminee'")->fetchColumn(),
];
$lastUsers = $pdo->query('SELECT id, name, email, created_at FROM `user` ORDER BY id DESC LIMIT 5')->fetchAll();
$lastProjects = $pdo->query(
    'SELECT p.id, p.name, p.created_at, u.name AS owner_name
     FROM project p JOIN `user` u ON u.id = p.owner_id
     ORDER BY p.id DESC LIMIT 5'
)->fetchAll();

$pageTitle = 'Administration';
include __DIR__ . '/../includes/header.php';
?>
<h2>Administration</h2>
<?php admin_tabs('admin.php'); ?>

<div class="grid">
    <?php foreach ($stats as $label => $n): ?>
    <div class="card stat"><div class="stat-n"><?= $n ?></div><div class="hint"><?= e($label) ?></div></div>
    <?php endforeach; ?>
</div>

<div class="grid">
    <div class="card">
        <h3>Derniers inscrits</h3>
        <ul class="task-list">
            <?php foreach ($lastUsers as $u): ?>
            <li><span><?= e($u['name']) ?> <span class="hint"><?= e($u['email']) ?></span></span>
                <span class="hint"><?= e(substr($u['created_at'], 0, 10)) ?></span></li>
            <?php endforeach; ?>
        </ul>
        <a href="admin_users.php">Gérer les utilisateurs →</a>
    </div>
    <div class="card">
        <h3>Derniers projets</h3>
        <ul class="task-list">
            <?php foreach ($lastProjects as $p): ?>
            <li><span><?= e($p['name']) ?> <span class="hint">par <?= e($p['owner_name']) ?></span></span>
                <span class="hint"><?= e(substr($p['created_at'], 0, 10)) ?></span></li>
            <?php endforeach; ?>
        </ul>
        <a href="admin_projects.php">Gérer les projets →</a>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>