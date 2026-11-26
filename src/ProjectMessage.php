<?php
class ProjectMessage
{
    private static function db(): PDO
    {
        return Database::get();
    }

    private const SELECT = 'SELECT m.id, m.project_id, m.user_id, m.body, m.created_at,
                                   u.name, u.avatar_color
                            FROM project_message m
                            JOIN `user` u ON u.id = m.user_id';

    public static function latest(int $pid, int $limit = 50): array
    {
        $st = self::db()->prepare(self::SELECT . ' WHERE m.project_id = ? ORDER BY m.id DESC LIMIT ' . (int)$limit);
        $st->execute([$pid]);
        return array_reverse($st->fetchAll(PDO::FETCH_ASSOC));
    }

    public static function after(int $pid, int $afterId): array
    {
        $st = self::db()->prepare(self::SELECT . ' WHERE m.project_id = ? AND m.id > ? ORDER BY m.id ASC LIMIT 200');
        $st->execute([$pid, $afterId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function find(int $id): ?array
    {
        $st = self::db()->prepare(self::SELECT . ' WHERE m.id = ?');
        $st->execute([$id]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        return $r ?: null;
    }

    public static function create(int $pid, int $uid, string $body): int
    {
        $db = self::db();
        $db->prepare('INSERT INTO project_message (project_id, user_id, body) VALUES (?, ?, ?)')
           ->execute([$pid, $uid, $body]);
        return (int)$db->lastInsertId();
    }

    public static function delete(int $id): bool
    {
        return self::db()->prepare('DELETE FROM project_message WHERE id = ?')->execute([$id]);
    }
        public static function recentIds(int $pid, int $limit = 100): array
    {
        $st = self::db()->prepare('SELECT id FROM project_message WHERE project_id = ? ORDER BY id DESC LIMIT ' . (int)$limit);
        $st->execute([$pid]);
        return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    }
}