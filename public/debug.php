<?php
require __DIR__ . '/../includes/bootstrap.php';
ini_set('display_errors', '1');
$user = require_login();
$pdo = Database::get();
$uid = $user->getId();

function show(string $label, PDO $pdo, string $sql, array $p): void {
    $st = $pdo->prepare($sql);
    $st->execute($p);
    echo '<h3>' . htmlspecialchars($label) . '</h3><pre>';
    print_r($st->fetchAll());
    echo '</pre>';
}

echo '<h2>Utilisateur connecté : id ' . $uid . ' (' . htmlspecialchars($user->getEmail()) . ')</h2>';
echo '<p>Base : ' . $pdo->query('SELECT DATABASE()')->fetchColumn() . '</p>';

show('1. project_member pour moi', $pdo, 'SELECT * FROM project_member WHERE user_id = ?', [$uid]);
show('2. avec projet', $pdo, 'SELECT pm.*, p.name FROM project_member pm JOIN project p ON p.id = pm.project_id WHERE pm.user_id = ?', [$uid]);
show('3. avec rôle de projet', $pdo, 'SELECT pm.*, pr.name AS role FROM project_member pm JOIN project_role pr ON pr.id = pm.project_role_id WHERE pm.user_id = ?', [$uid]);
show('4. Project::findForUser()', $pdo, 'SELECT 1', []);
echo '<pre>'; print_r(Project::findForUser($uid)); echo '</pre>';
show('5. mes tâches assignées', $pdo, 'SELECT id, name, project_id, assigned_user_id FROM task WHERE assigned_user_id = ?', [$uid]);