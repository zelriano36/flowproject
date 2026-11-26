<?php
require_once dirname(__DIR__) . '/includes/image_utils.php';

class TaskImage
{
    public const MAX_BYTES = 15 * 1024 * 1024;
    public const MAX_PIXELS = 25000000;
    public const MAX_PER_TASK = 10;
    private const FULL_SIDE = 1600;
    private const THUMB_SIDE = 240;

    public static function dir(): string
    {
        return dirname(__DIR__) . '/storage/task_images';
    }

    /** Chemin d'un fichier. Le nom est validé pour interdire toute traversée de dossier. */
    public static function filePath(string $name, bool $thumb = false): ?string
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $name)) {
            return null;
        }
        return self::dir() . '/' . $name . ($thumb ? '_t' : '') . '.jpg';
    }

    public static function forProject(int $projectId): array
    {
        $st = Database::get()->prepare(
            'SELECT i.id, i.task_id, i.file_name, i.original_name
             FROM task_image i JOIN task t ON t.id = i.task_id
             WHERE t.project_id = ? ORDER BY i.id'
        );
        $st->execute([$projectId]);
        $out = [];
        foreach ($st->fetchAll() as $row) {
            $out[(int)$row['task_id']][] = $row;
        }
        return $out;
    }

    public static function find(int $id): ?array
    {
        $st = Database::get()->prepare(
            'SELECT i.*, t.project_id, t.assigned_user_id
             FROM task_image i JOIN task t ON t.id = i.task_id
             WHERE i.id = ?'
        );
        $st->execute([$id]);
        return $st->fetch() ?: null;
    }

    public static function countForTask(int $taskId): int
    {
        $st = Database::get()->prepare('SELECT COUNT(*) FROM task_image WHERE task_id = ?');
        $st->execute([$taskId]);
        return (int)$st->fetchColumn();
    }

    /** Ajoute une image envoyée. Renvoie null en cas de succès, sinon un message d'erreur. */
    public static function addFromUpload(int $taskId, int $userId, array $f): ?string
    {
        [$src, $err] = image_from_upload($f, self::MAX_BYTES, self::MAX_PIXELS);
        if ($err !== null) {
            return $err;
        }

        $w = imagesx($src);
        $h = imagesy($src);

        $scale = min(1, self::FULL_SIDE / max($w, $h));
        $fw = max(1, (int)round($w * $scale));
        $fh = max(1, (int)round($h * $scale));
        $full = imagecreatetruecolor($fw, $fh);
        imagefill($full, 0, 0, imagecolorallocate($full, 255, 255, 255));
        imagecopyresampled($full, $src, 0, 0, 0, 0, $fw, $fh, $w, $h);

        $side = min($w, $h);
        $thumb = imagecreatetruecolor(self::THUMB_SIDE, self::THUMB_SIDE);
        imagefill($thumb, 0, 0, imagecolorallocate($thumb, 255, 255, 255));
        imagecopyresampled($thumb, $src, 0, 0, intdiv($w - $side, 2), intdiv($h - $side, 2),
            self::THUMB_SIDE, self::THUMB_SIDE, $side, $side);
        imagedestroy($src);

        $dir = self::dir();
        if (!is_dir($dir) && !@mkdir($dir, 0750, true)) {
            imagedestroy($full);
            imagedestroy($thumb);
            return 'Impossible de créer le dossier de stockage.';
        }

        $name = bin2hex(random_bytes(16));
        $pathFull = self::filePath($name);
        $pathThumb = self::filePath($name, true);
        $ok = @imagejpeg($full, $pathFull, 88) && @imagejpeg($thumb, $pathThumb, 85);
        imagedestroy($full);
        imagedestroy($thumb);
        if (!$ok) {
            @unlink($pathFull);
            @unlink($pathThumb);
            return 'Impossible d\'enregistrer l\'image.';
        }

        $original = basename(str_replace('\\', '/', (string)($f['name'] ?? 'image')));
        $original = mb_substr(preg_replace('/[\x00-\x1F\x7F]/u', '', $original), 0, 255);

        $st = Database::get()->prepare(
            'INSERT INTO task_image (task_id, file_name, original_name, uploaded_by) VALUES (?, ?, ?, ?)'
        );
        $st->execute([$taskId, $name, $original !== '' ? $original : 'image', $userId]);
        return null;
    }

    public static function delete(int $id): void
    {
        $img = self::find($id);
        if (!$img) {
            return;
        }
        self::unlinkFiles($img['file_name']);
        Database::get()->prepare('DELETE FROM task_image WHERE id = ?')->execute([$id]);
    }

    /** À appeler AVANT de supprimer une tâche : efface ses fichiers (les lignes partent en cascade). */
    public static function purgeFilesForTask(int $taskId): void
    {
        $st = Database::get()->prepare('SELECT file_name FROM task_image WHERE task_id = ?');
        $st->execute([$taskId]);
        foreach ($st->fetchAll() as $row) {
            self::unlinkFiles($row['file_name']);
        }
    }

    /** À appeler AVANT de supprimer un projet. */
    public static function purgeFilesForProject(int $projectId): void
    {
        $st = Database::get()->prepare(
            'SELECT i.file_name FROM task_image i JOIN task t ON t.id = i.task_id WHERE t.project_id = ?'
        );
        $st->execute([$projectId]);
        foreach ($st->fetchAll() as $row) {
            self::unlinkFiles($row['file_name']);
        }
    }

    private static function unlinkFiles(string $name): void
    {
        foreach ([false, true] as $thumb) {
            $path = self::filePath($name, $thumb);
            if ($path !== null && is_file($path)) {
                @unlink($path);
            }
        }
    }
}