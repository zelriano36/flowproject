<?php
require __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/admin.php';
$admin = require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $msgs = [];

    $name = trim($_POST['site_name'] ?? '');
    if ($name === '' || mb_strlen($name) > 40) {
        $msgs[] = 'Nom invalide (1 à 40 caractères).';
    } else {
        SiteSetting::set('site_name', $name);
        $msgs[] = 'Nom enregistré.';
    }

    $specs = ['logo' => [512, false, 'Logo'], 'favicon' => [128, true, 'Icône']];
    foreach ($specs as $key => [$max, $square, $label]) {
        if (($_POST['remove_' . $key] ?? '') === '1') {
            branding_remove($key);
            $msgs[] = $label . ' réinitialisé.';
            continue;
        }
        $f = $_FILES[$key] ?? null;
        if ($f && ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $err = branding_handle_upload($key, $f, $max, $square);
            $msgs[] = $err ? $label . ' : ' . $err : $label . ' mis à jour.';
        }
    }
    flash(implode(' ', $msgs));
    header('Location: admin_site.php');
    exit;
}

$pageTitle = 'Apparence du site';
include __DIR__ . '/../includes/header.php';
?>
<h2>Administration</h2>
<?php admin_tabs('admin_site.php'); ?>

<div class="card">
    <h3>Apparence du site</h3>
    <form method="post" enctype="multipart/form-data">
        <?= csrf_field() ?>

        <label for="site_name">Nom du site</label>
        <input type="text" id="site_name" name="site_name" maxlength="40" required value="<?= e(site_name()) ?>">

        <label for="logo">Logo (PNG, JPG ou WebP, 2 Mo maximum)</label>
        <?php if (branding_has('logo')): ?>
            <p><img src="<?= e(branding_url('logo')) ?>" alt="Logo actuel" style="max-height:56px;max-width:200px"></p>
            <label><input type="checkbox" name="remove_logo" value="1"> Revenir au logo par défaut</label>
        <?php endif; ?>
        <input type="file" id="logo" name="logo" accept="image/png,image/jpeg,image/webp">

        <label for="favicon">Icône de l'onglet (image carrée de préférence)</label>
        <?php if (branding_has('favicon')): ?>
            <p><img src="<?= e(branding_url('favicon')) ?>" alt="Icône actuelle" style="width:32px;height:32px"></p>
            <label><input type="checkbox" name="remove_favicon" value="1"> Revenir à l'icône par défaut</label>
        <?php endif; ?>
        <input type="file" id="favicon" name="favicon" accept="image/png,image/jpeg,image/webp">

        <p class="hint">Le logo est réduit à 512 px maximum. L'icône est recadrée en carré (128 px). La transparence des PNG est conservée.</p>
        <p><button type="submit">Enregistrer</button></p>
    </form>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>