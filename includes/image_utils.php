<?php
/** Normalise $_FILES['x'] (simple ou multiple) en liste de fichiers. */
function normalize_files(array $f): array
{
    if (!isset($f['name'])) {
        return [];
    }
    if (!is_array($f['name'])) {
        return $f['name'] !== '' ? [$f] : [];
    }
    $out = [];
    foreach ($f['name'] as $i => $name) {
        if (($f['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        $out[] = [
            'name'     => $name,
            'type'     => $f['type'][$i] ?? '',
            'tmp_name' => $f['tmp_name'][$i] ?? '',
            'error'    => $f['error'][$i],
            'size'     => $f['size'][$i] ?? 0,
        ];
    }
    return $out;
}

/**
 * Charge une image envoyée avec contrôles (type réel, taille, dimensions, rotation EXIF).
 * Renvoie [image GD ou null, message d'erreur ou null].
 */
function image_from_upload(array $f, int $maxBytes, int $maxPixels = 16000000): array
{
    $mo = (int)round($maxBytes / 1048576);
    $mp = (int)round($maxPixels / 1000000);

    if (!extension_loaded('gd')) {
        return [null, 'L\'extension GD est absente du serveur.'];
    }
    if (!isset($f['error']) || is_array($f['error'])) {
        return [null, 'Envoi invalide.'];
    }
    switch ($f['error']) {
        case UPLOAD_ERR_OK:
            break;
        case UPLOAD_ERR_NO_FILE:
            return [null, 'Aucun fichier choisi.'];
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return [null, 'Fichier trop volumineux (' . $mo . ' Mo maximum).'];
        default:
            return [null, 'Erreur pendant l\'envoi du fichier.'];
    }
    if ($f['size'] > $maxBytes) {
        return [null, 'Fichier trop volumineux (' . $mo . ' Mo maximum).'];
    }
    if (!is_uploaded_file($f['tmp_name'])) {
        return [null, 'Envoi invalide.'];
    }

    $info = @getimagesize($f['tmp_name']);
    if (!$info) {
        return [null, 'Ce fichier n\'est pas une image valide.'];
    }
    [$w, $h] = $info;
    if ($w < 32 || $h < 32 || $w * $h > $maxPixels) {
        return [null, 'Dimensions invalides (32 px minimum, ' . $mp . ' mégapixels maximum).'];
    }

    switch ($info['mime']) {
        case 'image/jpeg':
            $img = @imagecreatefromjpeg($f['tmp_name']);
            break;
        case 'image/png':
            $img = @imagecreatefrompng($f['tmp_name']);
            break;
        case 'image/webp':
            if (!function_exists('imagecreatefromwebp')) {
                return [null, 'Le format WebP n\'est pas supporté par ce serveur.'];
            }
            $img = @imagecreatefromwebp($f['tmp_name']);
            break;
        default:
            return [null, 'Format non accepté (JPG, PNG ou WebP).'];
    }
    if (!$img) {
        return [null, 'Image illisible (mémoire insuffisante ou fichier corrompu).'];
    }

    if ($info['mime'] === 'image/jpeg' && function_exists('exif_read_data')) {
        $exif = @exif_read_data($f['tmp_name']);
        $angle = [3 => 180, 6 => -90, 8 => 90][$exif['Orientation'] ?? 1] ?? 0;
        if ($angle !== 0) {
            $rotated = imagerotate($img, $angle, 0);
            if ($rotated) {
                imagedestroy($img);
                $img = $rotated;
            }
        }
    }
    return [$img, null];
}