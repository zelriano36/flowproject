<?php
function is_admin(int $userId): bool
{
    $st = Database::get()->prepare(
        'SELECT 1 FROM `user` u JOIN `role` r ON r.id = u.role_id
         WHERE u.id = ? AND r.name = ?'
    );
    $st->execute([$userId, 'admin']);
    return (bool)$st->fetchColumn();
}

function require_admin()
{
    $user = require_login();
    if (!is_admin($user->getId())) {
        http_response_code(403);
        exit('Accès réservé aux administrateurs.');
    }
    return $user;
}

function admin_tabs(string $active): void
{
    $tabs = [
        'admin.php'          => 'Tableau de bord',
        'admin_users.php'    => 'Utilisateurs',
        'admin_projects.php' => 'Projets',
        'admin_site.php'     => 'Apparence',
    ];
    echo '<div class="tabs">';
    foreach ($tabs as $file => $label) {
        echo '<a href="' . $file . '"' . ($file === $active ? ' class="active"' : '') . '>' . e($label) . '</a>';
    }
    echo '</div>';
}