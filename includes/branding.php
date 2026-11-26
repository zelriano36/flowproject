<?php
function site_name(): string
{
    return SiteSetting::get('site_name', 'FlowProject');
}

function branding_dir(): string
{
    return dirname(__DIR__) . '/storage/branding';
}

function branding_has(string $key): bool
{
    return is_file(branding_dir() . '/' . $key . '.png');
}

function branding_url(string $key): string
{
    return 'site_asset.php?f=' . $key . '&v=' . (int)@filemtime(branding_dir() . '/' . $key . '.png');
}

function branding_remove(string $key): void
{
    $f = branding_dir() . '/' . $key . '.png';
    if (is_file($f)) {
        @unlink($f);
    }
}

function branding_save(string $key, $img, int $max, bool $square): bool
{
    $w = imagesx($img);
    $h = imagesy($img);
    if ($square) {
        $s = min($w, $h);
        $sx = intdiv($w - $s, 2);
        $sy = intdiv($h - $s, 2);
        $sw = $sh = $s;
        $dw = $dh = $max;
    } else {
        $r = min(1, $max / max($w, $h));
        $sx = $sy = 0;
        $sw = $w;
        $sh = $h;
        $dw = max(1, (int)round($w * $r));
        $dh = max(1, (int)round($h * $r));
    }
    $out = imagecreatetruecolor($dw, $dh);
    imagealphablending($out, false);
    imagesavealpha($out, true);
    imagefill($out, 0, 0, imagecolorallocatealpha($out, 0, 0, 0, 127));
    imagecopyresampled($out, $img, 0, 0, $sx, $sy, $dw, $dh, $sw, $sh);

    $dir = branding_dir();
    if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
        imagedestroy($out);
        return false;
    }
    $tmp = $dir . '/' . $key . '.tmp';
    $ok = @imagepng($out, $tmp) && @rename($tmp, $dir . '/' . $key . '.png');
    imagedestroy($out);
    return $ok;
}

/** Renvoie un message d'erreur, ou null si tout s'est bien passé. */
function branding_handle_upload(string $key, array $file, int $max, bool $square): ?string
{
    [$img, $err] = image_from_upload($file, 2 * 1048576, 16000000);
    if ($err !== null) {
        return $err;
    }
    $ok = branding_save($key, $img, $max, $square);
    imagedestroy($img);
    return $ok ? null : "Enregistrement impossible (droits d'écriture sur storage/branding ?).";
}