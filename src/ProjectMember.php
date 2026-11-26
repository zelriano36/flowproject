<?php
class ProjectMember
{
    public static function find(int $userId, int $projectId): ?array
    {
        $st = Database::get()->prepare(
            'SELECT pm.id, pm.user_id, pm.project_id, pr.name AS role
             FROM project_member pm
             JOIN project_role pr ON pr.id = pm.project_role_id
             WHERE pm.user_id = ? AND pm.project_id = ?'
        );
        $st->execute([$userId, $projectId]);
        return $st->fetch() ?: null;
    }

    public static function add(int $userId, int $projectId, string $roleName): bool
    {
        $role = ProjectRole::findByName($roleName);
        if (!$role || self::find($userId, $projectId)) return false;
        $st = Database::get()->prepare(
            'INSERT INTO project_member (user_id, project_id, project_role_id) VALUES (?, ?, ?)'
        );
        return $st->execute([$userId, $projectId, $role->getId()]);
    }

    // Refuse de retirer le dernier manager
    public static function remove(int $userId, int $projectId): bool
    {
        $m = self::find($userId, $projectId);
        if (!$m) return false;
        if ($m['role'] === 'manager' && self::countManagers($projectId) <= 1) return false;

                $pdo = Database::get();
        $pdo->prepare('UPDATE task SET assigned_user_id = NULL WHERE project_id = ? AND assigned_user_id = ?')
            ->execute([$projectId, $userId]);

        $st = $pdo->prepare('DELETE FROM project_member WHERE user_id = ? AND project_id = ?');
        return $st->execute([$userId, $projectId]);
    }

    public static function countManagers(int $projectId): int
    {
        $st = Database::get()->prepare(
            "SELECT COUNT(*) FROM project_member pm
             JOIN project_role pr ON pr.id = pm.project_role_id
             WHERE pm.project_id = ? AND pr.name = 'manager'"
        );
        $st->execute([$projectId]);
        return (int)$st->fetchColumn();
    }

    public static function listByProject(int $projectId): array
    {
                $st = Database::get()->prepare(
            'SELECT u.id, u.name, u.email, u.avatar_color, pr.name AS role
             FROM project_member pm
             JOIN user u ON u.id = pm.user_id
             JOIN project_role pr ON pr.id = pm.project_role_id
             WHERE pm.project_id = ?
             ORDER BY u.name'
        );
        $st->execute([$projectId]);
        return $st->fetchAll();
    }
}