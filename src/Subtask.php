<?php

class Subtask
{
    /** Toutes les sous-tâches d'un projet, groupées par task_id. */
    public static function forProject(int $projectId): array
    {
        $st = Database::get()->prepare(
            'SELECT s.* FROM subtask s
             JOIN task t ON t.id = s.task_id
             WHERE t.project_id = ?
             ORDER BY s.position, s.id'
        );
        $st->execute([$projectId]);
        $out = [];
        foreach ($st->fetchAll() as $row) {
            $out[(int)$row['task_id']][] = $row;
        }
        return $out;
    }

    /** Sous-tâche avec le projet et l'assigné de sa tâche (pour les contrôles de droits). */
    public static function find(int $id): ?array
    {
        $st = Database::get()->prepare(
            'SELECT s.*, t.project_id, t.assigned_user_id
             FROM subtask s JOIN task t ON t.id = s.task_id
             WHERE s.id = ?'
        );
        $st->execute([$id]);
        return $st->fetch() ?: null;
    }

    public static function countForTask(int $taskId): int
    {
        $st = Database::get()->prepare('SELECT COUNT(*) FROM subtask WHERE task_id = ?');
        $st->execute([$taskId]);
        return (int)$st->fetchColumn();
    }

    public static function create(int $taskId, string $title): void
    {
        $st = Database::get()->prepare(
            'INSERT INTO subtask (task_id, title, position)
             SELECT ?, ?, COALESCE(MAX(position), 0) + 1 FROM subtask WHERE task_id = ?'
        );
        $st->execute([$taskId, $title, $taskId]);
    }

    public static function setDone(int $id, bool $done): bool
    {
        $st = Database::get()->prepare('UPDATE subtask SET is_done = ? WHERE id = ?');
        return $st->execute([$done ? 1 : 0, $id]);
    }

    public static function delete(int $id): void
    {
        Database::get()->prepare('DELETE FROM subtask WHERE id = ?')->execute([$id]);
    }
}