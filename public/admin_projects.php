<?php
require __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/admin.php';
$admin = require_admin();
$pdo = Database::get();
$me = $admin->getId();

$statusLabels = ['en_cours' => 'En cours', 'termine' => 'Terminé', 'archive' => 'Archivé', 'actif' => 'Actif'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action  = $_POST['action'] ?? '';
    $pid     = (int)($_POST['project_id'] ?? 0);
    $project = $pid ? Project::findById($pid) : null;

    if (!$project) {
        flash('Projet introuvable.');
    } else {
        switch ($action) {
            case 'set_status':
                $status = in_array($_POST['status'] ?? '', ['en_cours', 'termine', 'archive'], true) ? $_POST['status'] : 'en_cours';
                $project->update($project->getName(), $project->getDescription(), $status);
                flash('Statut mis à jour.');
                break;

            case 'join_project':
                if ($project->isMember($me)) { flash('Vous êtes déjà membre de ce projet.'); break; }
                $project->addMember($me, 'manager');
                flash('Vous avez rejoint le projet comme gestionnaire.');
                break;

            case 'delete_project':
                TaskImage::purgeFilesForProject($pid);
                $project->delete();
                flash('Projet supprimé.');
                break;
        }
    }
    header('Location: admin_projects.php');
    exit;
}

$q = trim($_GET['q'] ?? '');
$st = $pdo->prepare(
    "SELECT p.id, p.name, p.status, p.created_at, u.name AS owner_name,
            (SELECT COUNT(*) FROM project_member pm WHERE pm.project_id = p.id) AS members,
            (SELECT COUNT(*) FROM task t WHERE t.project_id = p.id) AS total,
            (SELECT COUNT(*) FROM task t WHERE t.project_id = p.id AND t.status = 'terminee') AS done,
            EXISTS(SELECT 1 FROM project_member pm WHERE pm.project_id = p.id AND pm.user_id = ?) AS is_member
     FROM project p JOIN `user` u ON u.id = p.owner_id
     WHERE p.name LIKE ?
     ORDER BY p.created_at DESC"
);
$st->execute([$me, '%' . $q . '%']);
$projects = $st->fetchAll();

$pageTitle = 'Projets';
include __DIR__ . '/../includes/header.php';
?>
<h2>Administration</h2>
<?php admin_tabs('admin_projects.php'); ?>

<div class="page-head">
    <form method="get" class="inline">
        <input type="text" name="q" value="<?= e($q) ?>" placeholder="Rechercher un projet">
    </form>
</div>

<div class="card table-wrap">
<table class="table">
    <thead>
        <tr><th>Projet</th><th>Créateur</th><th>Statut</th><th>Membres</th><th>Tâches</th><th>Créé le</th><th>Actions</th></tr>
    </thead>
    <tbody>
    <?php foreach ($projects as $p): ?>
        <tr>
            <td><strong><?= e($p['name']) ?></strong></td>
            <td><?= e($p['owner_name']) ?></td>
            <td>
                <form method="post" class="inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="set_status">
                    <input type="hidden" name="project_id" value="<?= (int)$p['id'] ?>">
                    <select name="status" class="theme-select">
                        <?php foreach (['en_cours', 'termine', 'archive'] as $s): ?>
                        <option value="<?= $s ?>" <?= $p['status'] === $s ? 'selected' : '' ?>><?= e($statusLabels[$s]) ?></option>
                        <?php endforeach; ?>
                        <?php if ($p['status'] === 'actif'): ?><option value="actif" selected>Actif (ancien)</option><?php endif; ?>
                    </select>
                    <button type="submit" class="secondary small">OK</button>
                </form>
            </td>
            <td><?= (int)$p['members'] ?></td>
            <td><?= (int)$p['done'] ?>/<?= (int)$p['total'] ?></td>
            <td><?= e(substr($p['created_at'], 0, 10)) ?></td>
            <td class="actions">
                <?php if ($p['is_member']): ?>
                    <a class="btn secondary small" href="project.php?id=<?= (int)$p['id'] ?>">Ouvrir</a>
                <?php else: ?>
                    <form method="post" class="inline" data-confirm="Vous ajouter comme gestionnaire de ce projet ?">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="join_project">
                        <input type="hidden" name="project_id" value="<?= (int)$p['id'] ?>">
                        <button type="submit" class="secondary small">Rejoindre</button>
                    </form>
                <?php endif; ?>
                <form method="post" class="inline" data-confirm="Supprimer définitivement ce projet et toutes ses tâches ?">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete_project">
                    <input type="hidden" name="project_id" value="<?= (int)$p['id'] ?>">
                    <button type="submit" class="danger small">Supprimer</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php if (!$projects): ?><p class="hint">Aucun projet trouvé.</p><?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>