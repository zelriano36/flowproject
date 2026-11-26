<?php
require __DIR__ . '/../includes/bootstrap.php';
$user = require_login();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $name  = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        if ($name === '' || mb_strlen($name) > 100) $errors[] = 'Pseudo invalide (1 à 100 caractères).';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email invalide.';
        elseif (User::emailTaken($email, $user->getId())) $errors[] = 'Cet email est déjà utilisé.';
        if (!$errors) {
            $user->updateProfile($name, $email);
            flash('Profil mis à jour.');
            header('Location: profile.php');
            exit;
        }
    }

    if ($action === 'update_color') {
        $user->setAvatarColor((int)($_POST['color'] ?? 0));
        flash('Couleur mise à jour.');
        header('Location: profile.php');
        exit;
    }

    if ($action === 'upload_avatar') {
        $err = avatar_save_upload($user->getId(), $_FILES['photo'] ?? []);
        if ($err !== null) {
            $errors[] = $err;
        } else {
            flash('Photo de profil mise à jour.');
            header('Location: profile.php');
            exit;
        }
    }

    if ($action === 'delete_avatar') {
        avatar_delete($user->getId());
        flash('Photo de profil supprimée.');
        header('Location: profile.php');
        exit;
    }

    if ($action === 'update_password') {
        $old = $_POST['old'] ?? '';
        $new = $_POST['new'] ?? '';
        if (!$user->checkPassword($old)) $errors[] = 'Mot de passe actuel incorrect.';
        elseif (strlen($new) < 8) $errors[] = 'Nouveau mot de passe : 8 caractères minimum.';
        elseif ($new !== ($_POST['new2'] ?? '')) $errors[] = 'Les deux mots de passe ne correspondent pas.';
        else {
            $user->changePassword($new);
            session_regenerate_id(true);
            flash('Mot de passe modifié.');
            header('Location: profile.php');
            exit;
        }
    }

    if ($action === 'delete_account') {
        if (!$user->checkPassword($_POST['password'] ?? '')) {
            $errors[] = 'Mot de passe incorrect : le compte n\'a pas été supprimé.';
        } else {
            $uid = $user->getId();
            $user->delete();
            avatar_delete($uid);
            logout_user();
            session_start();
            flash('Votre compte a été supprimé.');
            header('Location: login.php');
            exit;
        }
    }
}

$hasPhoto = is_file(avatar_file($user->getId()));

$pageTitle = 'Mon profil';
include __DIR__ . '/../includes/header.php';
?>
<div class="form-card">
    <?php foreach ($errors as $err): ?><div class="error"><?= e($err) ?></div><?php endforeach; ?>

    <div class="card">
        <div class="profile-head">
            <?= avatar($user->getName(), $user->getAvatarColor(), 'lg', $user->getId()) ?>
            <div><h2><?= e($user->getName()) ?></h2><span class="hint"><?= e($user->getEmail()) ?></span></div>
        </div>

        <form method="post" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="upload_avatar">
            <label for="photo">Photo de profil</label>
            <input type="file" id="photo" name="photo" accept="image/jpeg,image/png,image/webp" required>
            <p class="hint">JPG, PNG ou WebP, 2 Mo maximum. L'image est recadrée en carré.</p>
            <p><button type="submit">Envoyer la photo</button></p>
        </form>

        <?php if ($hasPhoto): ?>
        <form method="post" data-confirm="Supprimer votre photo de profil ?">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete_avatar">
            <button type="submit" class="secondary small">Supprimer ma photo</button>
        </form>
        <?php endif; ?>

        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update_color">
            <label>Couleur de l'avatar <span class="hint">(affichée quand vous n'avez pas de photo)</span></label>
            <div class="swatches">
                <?php for ($i = 0; $i < 8; $i++): ?>
                <label class="swatch">
                    <input type="radio" name="color" value="<?= $i ?>" <?= $user->getAvatarColor() === $i ? 'checked' : '' ?>>
                    <?= avatar($user->getName(), $i) ?>
                </label>
                <?php endfor; ?>
            </div>
            <button type="submit">Enregistrer la couleur</button>
        </form>
    </div>

    <div class="card">
        <h3>Informations</h3>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update_profile">
            <label for="name">Pseudo</label>
            <input type="text" id="name" name="name" value="<?= e($user->getName()) ?>" required>
            <label for="email">Email</label>
            <input type="email" id="email" name="email" value="<?= e($user->getEmail()) ?>" required>
            <p><button type="submit">Enregistrer</button></p>
        </form>
    </div>

    <div class="card">
        <h3>Mot de passe</h3>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update_password">
            <label for="old">Mot de passe actuel</label>
            <input type="password" id="old" name="old" required autocomplete="current-password">
            <label for="new">Nouveau mot de passe</label>
            <input type="password" id="new" name="new" required autocomplete="new-password">
            <label for="new2">Confirmer</label>
            <input type="password" id="new2" name="new2" required autocomplete="new-password">
            <p><button type="submit">Changer le mot de passe</button></p>
        </form>
    </div>

    <div class="card danger-zone">
        <h3>Supprimer mon compte</h3>
        <p class="hint">Action définitive. Vos tâches deviennent « non assignées ». Les projets que vous avez créés sont transférés à un autre membre, ou supprimés si vous êtes seul.</p>
        <form method="post" data-confirm="Supprimer définitivement votre compte ?">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete_account">
            <label for="password">Confirmez avec votre mot de passe</label>
            <input type="password" id="password" name="password" required autocomplete="current-password">
            <p><button type="submit" class="danger">Supprimer mon compte</button></p>
        </form>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>