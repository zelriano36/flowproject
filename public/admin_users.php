<?php
require __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/admin.php';
$admin = require_admin();
$pdo = Database::get();
$me = $admin->getId();

$roles = $pdo->query('SELECT id, name FROM `role` ORDER BY id')->fetchAll();
$roleNames = array_column($roles, 'name', 'id');
$roleLabels = ['admin' => 'Administrateur', 'user' => 'Utilisateur'];
$adminRoleId = (int)array_search('admin', $roleNames, true);

function count_admins(PDO $pdo, int $adminRoleId): int
{
    $st = $pdo->prepare('SELECT COUNT(*) FROM `user` WHERE role_id = ?');
    $st->execute([$adminRoleId]);
    return (int)$st->fetchColumn();
}
function email_taken(PDO $pdo, string $email, int $exceptId = 0): bool
{
    $st = $pdo->prepare('SELECT 1 FROM `user` WHERE email = ? AND id <> ?');
    $st->execute([$email, $exceptId]);
    return (bool)$st->fetchColumn();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    $uid    = (int)($_POST['user_id'] ?? 0);
    $name   = trim($_POST['name'] ?? '');
    $email  = trim($_POST['email'] ?? '');
    $roleId = (int)($_POST['role_id'] ?? 0);

    $target = null;
    if ($uid) {
        $st = $pdo->prepare('SELECT id, role_id FROM `user` WHERE id = ?');
        $st->execute([$uid]);
        $target = $st->fetch() ?: null;
    }

    try {
        switch ($action) {
            case 'create_user':
                $pass = $_POST['password'] ?? '';
                if ($name === '' || mb_strlen($name) > 100) { flash('Nom invalide.'); break; }
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { flash('Email invalide.'); break; }
                if (!isset($roleNames[$roleId])) { flash('Rôle invalide.'); break; }
                if (strlen($pass) < 8) { flash('Mot de passe trop court (8 caractères minimum).'); break; }
                if (email_taken($pdo, $email)) { flash('Cet email est déjà utilisé.'); break; }
                $st = $pdo->prepare('INSERT INTO `user` (name, email, password, role_id, avatar_color) VALUES (?, ?, ?, ?, ?)');
                $st->execute([$name, $email, password_hash($pass, PASSWORD_DEFAULT), $roleId, random_int(0, 7)]);
                flash('Utilisateur créé.');
                break;

            case 'update_user':
                if (!$target) { flash('Utilisateur introuvable.'); break; }
                if ($name === '' || mb_strlen($name) > 100) { flash('Nom invalide.'); break; }
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { flash('Email invalide.'); break; }
                if (!isset($roleNames[$roleId])) { flash('Rôle invalide.'); break; }
                if (email_taken($pdo, $email, $uid)) { flash('Cet email est déjà utilisé.'); break; }
                $wasAdmin = (int)$target['role_id'] === $adminRoleId;
                if ($wasAdmin && $roleId !== $adminRoleId) {
                    if ($uid === $me) { flash('Vous ne pouvez pas retirer votre propre rôle administrateur.'); break; }
                    if (count_admins($pdo, $adminRoleId) <= 1) { flash('Impossible : dernier administrateur.'); break; }
                }
                $st = $pdo->prepare('UPDATE `user` SET name = ?, email = ?, role_id = ? WHERE id = ?');
                $st->execute([$name, $email, $roleId, $uid]);
                flash('Utilisateur mis à jour.');
                break;

            case 'reset_password':
                $pass = $_POST['password'] ?? '';
                if (!$target) { flash('Utilisateur introuvable.'); break; }
                if (strlen($pass) < 8) { flash('Mot de passe trop court (8 caractères minimum).'); break; }
                $st = $pdo->prepare('UPDATE `user` SET password = ? WHERE id = ?');
                $st->execute([password_hash($pass, PASSWORD_DEFAULT), $uid]);
                flash('Mot de passe réinitialisé.');
                break;

            case 'delete_user':
                if (!$target) { flash('Utilisateur introuvable.'); break; }
                if ($uid === $me) { flash('Vous ne pouvez pas supprimer votre propre compte.'); break; }
                if ((int)$target['role_id'] === $adminRoleId && count_admins($pdo, $adminRoleId) <= 1) {
                    flash('Impossible : dernier administrateur.'); break;
                }
                $st = $pdo->prepare('SELECT COUNT(*) FROM project WHERE owner_id = ?');
                $st->execute([$uid]);
                if ((int)$st->fetchColumn() > 0) {
                    flash('Cet utilisateur possède des projets : supprimez-les d\'abord depuis la page Projets.');
                    break;
                }
                $pdo->prepare('DELETE FROM `user` WHERE id = ?')->execute([$uid]);
                avatar_delete($uid);
                flash('Utilisateur supprimé.');
                break;
        }
    } catch (PDOException $ex) {
        flash('Opération impossible : l\'utilisateur est lié à d\'autres données.');
    }
    header('Location: admin_users.php' . (isset($_GET['q']) ? '?q=' . urlencode($_GET['q']) : ''));
    exit;
}

$q = trim($_GET['q'] ?? '');
$st = $pdo->prepare(
    'SELECT u.id, u.name, u.email, u.role_id, u.created_at, u.avatar_color,
            (SELECT COUNT(*) FROM project p WHERE p.owner_id = u.id) AS owned,
            (SELECT COUNT(*) FROM project_member pm WHERE pm.user_id = u.id) AS memberships
     FROM `user` u
     WHERE u.name LIKE ? OR u.email LIKE ?
     ORDER BY u.id'
);
$st->execute(['%' . $q . '%', '%' . $q . '%']);
$users = $st->fetchAll();

$pageTitle = 'Utilisateurs';
include __DIR__ . '/../includes/header.php';
?>
<h2>Administration</h2>
<?php admin_tabs('admin_users.php'); ?>

<div class="page-head">
    <form method="get" class="inline">
        <input type="text" name="q" value="<?= e($q) ?>" placeholder="Rechercher un nom ou un email">
    </form>
</div>

<details class="card">
    <summary><strong>+ Créer un utilisateur</strong></summary>
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="create_user">
        <label>Nom</label><input type="text" name="name" required maxlength="100">
        <label>Email</label><input type="email" name="email" required>
        <label>Mot de passe</label><input type="password" name="password" required minlength="8">
        <label>Rôle</label>
        <select name="role_id">
            <?php foreach ($roles as $r): ?>
            <option value="<?= (int)$r['id'] ?>" <?= $r['name'] === 'user' ? 'selected' : '' ?>><?= e($roleLabels[$r['name']] ?? $r['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <p><button type="submit" class="small">Créer</button></p>
    </form>
</details>

<?php foreach ($users as $u): $isMe = (int)$u['id'] === $me; ?>
<details class="card">
    <summary class="user-line">
        <?= avatar($u['name'], (int)$u['avatar_color'], '', (int)$u['id']) ?>
        <span class="member-info">
            <span class="member-name"><?= e($u['name']) ?><?= $isMe ? ' (vous)' : '' ?></span>
            <span class="member-mail"><?= e($u['email']) ?></span>
        </span>
        <span class="badge <?= (int)$u['role_id'] === $adminRoleId ? 'manager' : '' ?>"><?= e($roleLabels[$roleNames[$u['role_id']] ?? ''] ?? '?') ?></span>
        <span class="hint"><?= (int)$u['owned'] ?> projet(s) créé(s) · <?= (int)$u['memberships'] ?> appartenance(s)</span>
    </summary>

    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="update_user">
        <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
        <label>Nom</label><input type="text" name="name" value="<?= e($u['name']) ?>" required maxlength="100">
        <label>Email</label><input type="email" name="email" value="<?= e($u['email']) ?>" required>
        <label>Rôle</label>
        <select name="role_id">
            <?php foreach ($roles as $r): ?>
            <option value="<?= (int)$r['id'] ?>" <?= (int)$u['role_id'] === (int)$r['id'] ? 'selected' : '' ?>><?= e($roleLabels[$r['name']] ?? $r['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <p><button type="submit" class="small">Enregistrer</button></p>
    </form>

    <form method="post" data-confirm="Réinitialiser le mot de passe de cet utilisateur ?">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="reset_password">
        <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
        <label>Nouveau mot de passe</label>
        <input type="password" name="password" minlength="8" required>
        <p><button type="submit" class="secondary small">Réinitialiser</button></p>
    </form>

    <?php if (!$isMe): ?>
    <form method="post" data-confirm="Supprimer définitivement cet utilisateur ?">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="delete_user">
        <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
        <button type="submit" class="danger small">Supprimer l'utilisateur</button>
    </form>
    <?php endif; ?>
</details>
<?php endforeach; ?>
<?php if (!$users): ?><p class="hint">Aucun utilisateur trouvé.</p><?php endif; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>