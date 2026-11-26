<?php

class ProjectLink
{
    public static function forProject(int $projectId): array
    {
        $st = Database::get()->prepare(
            'SELECT id, project_id, title, url, created_at
             FROM project_link WHERE project_id = ? ORDER BY id'
        );
        $st->execute([$projectId]);
        return $st->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $st = Database::get()->prepare('SELECT * FROM project_link WHERE id = ?');
        $st->execute([$id]);
        return $st->fetch() ?: null;
    }

    public static function countForProject(int $projectId): int
    {
        $st = Database::get()->prepare('SELECT COUNT(*) FROM project_link WHERE project_id = ?');
        $st->execute([$projectId]);
        return (int)$st->fetchColumn();
    }

    public static function create(int $projectId, string $title, string $url, int $userId): void
    {
        $st = Database::get()->prepare(
            'INSERT INTO project_link (project_id, title, url, created_by) VALUES (?, ?, ?, ?)'
        );
        $st->execute([$projectId, $title, $url, $userId]);
    }

    public static function delete(int $id): void
    {
        Database::get()->prepare('DELETE FROM project_link WHERE id = ?')->execute([$id]);
    }

    /** Normalise une adresse saisie. Renvoie null si elle n'est pas un lien http(s) valide. */
    public static function normalizeUrl(string $raw): ?string
    {
        $url = trim($raw);
        if ($url === '' || mb_strlen($url) > 500) {
            return null;
        }
        if (!preg_match('#^[a-z][a-z0-9+.-]*:#i', $url)) {
            $url = 'https://' . $url;
        }
        if (!preg_match('#^https?://#i', $url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }
        return parse_url($url, PHP_URL_HOST) ? $url : null;
    }
}