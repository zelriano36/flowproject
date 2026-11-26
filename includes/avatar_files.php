<?php
const AVATAR_MAX_BYTES = 2 * 1024 * 1024;
const AVATAR_SIZE = 256;

function avatar_dir(): string
{
    return dirname(__DIR__) . '/storage/avatars';
}

function avatar_file(int $userId): string
{
    return avatar_dir() . '/' . $userId . '.jpg';
}

function avatar_delete(int $userId): void
{
    $file = avatar_file($userId);
    if (is_file($file)) {
        @unlink($file);
    }
}

/** Traite un envoi de photo. Renvoie null en cas de succès, sinon un message d'erreur. */
function avatar_save_upload(int $userId, array $f): ?string
{
    if (!extension_loaded('gd')) {
        return 'L\'extension GD est absente du serveur.';
    }
    if (!isset($f['error']) || is_array($f['error'])) {
        return 'Envoi invalide.';
    }
    switch ($f['error']) {
        case UPLOAD_ERR_OK:
            break;
        case UPLOAD_ERR_NO_FILE:
            return 'Aucun fichier choisi.';
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return 'Fichier trop volumineux (2 Mo maximum).';
        default:
            return 'Erreur pendant l\'envoi du fichier.';
    }
    if ($f['size'] > AVATAR_MAX_BYTES) {
        return 'Fichier trop volumineux (2 Mo maximum).';
    }
    if (!is_uploaded_file($f['tmp_name'])) {
        return 'Envoi invalide.';
    }

    $info = @getimagesize($f['tmp_name']);
    if (!$info) {
        return 'Ce fichier n\'est pas une image valide.';
    }
    [$w, $h] = $info;
    if ($w < 32 || $h < 32 || $w * $h > 16000000) {
        return 'Dimensions invalides (32 px minimum, 16 mégapixels maximum).';
    }

    switch ($info['mime']) {
        case 'image/jpeg':
            $src = @imagecreatefromjpeg($f['tmp_name']);
            break;
        case 'image/png':
            $src = @imagecreatefrompng($f['tmp_name']);
            break;
        case 'image/webp':
            if (!function_exists('imagecreatefromwebp')) {
                return 'Le format WebP n\'est pas supporté par ce serveur.';
            }
            $src = @imagecreatefromwebp($f['tmp_name']);
            break;
        default:
            return 'Format non accepté (JPG, PNG ou WebP).';
    }
    if (!$src) {
        return 'Image illisible.';
    }

    // Orientation EXIF des photos de téléphone
    if ($info['mime'] === 'image/jpeg' && function_exists('exif_read_data')) {
        $exif = @exif_read_data($f['tmp_name']);
        $angle = [3 => 180, 6 => -90, 8 => 90][$exif['Orientation'] ?? 1] ?? 0;
        if ($angle !== 0) {
            $rotated = imagerotate($src, $angle, 0);
            if ($rotated) {
                imagedestroy($src);
                $src = $rotated;
            }
        }
    }

    $w = imagesx($src);
    $h = imagesy($src);
    $side = min($w, $h);
    $x = intdiv($w - $side, 2);
    $y = intdiv($h - $side, 2);

    $dst = imagecreatetruecolor(AVATAR_SIZE, AVATAR_SIZE);
    imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
    imagecopyresampled($dst, $src, 0, 0, $x, $y, AVATAR_SIZE, AVATAR_SIZE, $side, $side);
    imagedestroy($src);

    $dir = avatar_dir();
    if (!is_dir($dir) && !@mkdir($dir, 0750, true)) {
        imagedestroy($dst);
        return 'Impossible de créer le dossier de stockage.';
    }

    $target = avatar_file($userId);
    $tmp = $target . '.tmp';
    $ok = @imagejpeg($dst, $tmp, 88);
    imagedestroy($dst);
    if (!$ok || !@rename($tmp, $target)) {
        @unlink($tmp);
        return 'Impossible d\'enregistrer la photo.';
    }
    return null;
}