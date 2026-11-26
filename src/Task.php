<?php
class Task
{
    public const STATUSES = ['a_faire', 'en_cours', 'terminee'];

    public static function create(int $projectId, string $name, string $description, ?int $assignedTo = null): int
    {
        $pdo = Database::get();
        $st = $pdo->prepare(
            'INSERT INTO task (name, description, project_id, assigned_user_id) VALUES (?, ?, ?, ?)'
        );
        $st->execute([$name, $description, $projectId, $assignedTo]);
        return (int)$pdo->lastInsertId();
    }

    public static function find(int $id): ?array
    {
        $st = Database::get()->prepare('SELECT * FROM task WHERE id = ?');
        $st->execute([$id]);
        return $st->fetch() ?: null;
    }

    public static function listByProject(int $projectId): array
    {
                $st = Database::get()->prepare(
            'SELECT t.*, u.name AS assigned_name, u.avatar_color AS assigned_color
             FROM task t
             LEFT JOIN user u ON u.id = t.assigned_user_id
             WHERE t.project_id = ?
             ORDER BY t.created_at ASC'
        );
        $st->execute([$projectId]);
        return $st->fetchAll();
    }

    public static function listForUser(int $userId): array
    {
        $st = Database::get()->prepare(
            'SELECT t.*, p.name AS project_name
             FROM task t
             JOIN project p ON p.id = t.project_id
             JOIN project_member pm ON pm.project_id = p.id AND pm.user_id = t.assigned_user_id
             WHERE t.assigned_user_id = ?
             ORDER BY t.created_at DESC'
        );
        $st->execute([$userId]);
        return $st->fetchAll();
    }

    // L'assigné doit être membre du projet de la tâche
    public static function assign(int $taskId, ?int $userId): bool
    {
        $t = self::find($taskId);
        if (!$t) return false;
        if ($userId !== null && !ProjectMember::find($userId, (int)$t['project_id'])) return false;
        $st = Database::get()->prepare('UPDATE task SET assigned_user_id = ? WHERE id = ?');
        return $st->execute([$userId, $taskId]);
    }

    public static function changeStatus(int $taskId, string $status): bool
    {
        if (!in_array($status, self::STATUSES, true)) return false;
        $st = Database::get()->prepare('UPDATE task SET status = ? WHERE id = ?');
        return $st->execute([$status, $taskId]);
    }

    public static function delete(int $taskId): bool
    {
        $st = Database::get()->prepare('DELETE FROM task WHERE id = ?');
        return $st->execute([$taskId]);
    }
        /** Prend une tâche libre. Renvoie false si elle est déjà assignée ou hors projet. */
    public static function claim(int $taskId, int $projectId, int $userId): bool
    {
        $st = Database::get()->prepare(
            'UPDATE task SET assigned_user_id = ?
             WHERE id = ? AND project_id = ? AND assigned_user_id IS NULL'
        );
        $st->execute([$userId, $taskId, $projectId]);
        return $st->rowCount() === 1;
    }

    /** Libère une tâche, seulement si elle est assignée à cet utilisateur. */
    public static function release(int $taskId, int $projectId, int $userId): bool
    {
        $st = Database::get()->prepare(
            'UPDATE task SET assigned_user_id = NULL
             WHERE id = ? AND project_id = ? AND assigned_user_id = ?'
        );
        $st->execute([$taskId, $projectId, $userId]);
        return $st->rowCount() === 1;
    }
}
