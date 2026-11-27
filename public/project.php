<?php
require __DIR__ . '/../includes/bootstrap.php';
$user = require_login();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$project = $id ? Project::findById($id) : null;

if (!$project || !$project->isMember($user->getId())) {
    http_response_code(404);
    exit('Projet introuvable.');
}

$isManager = $project->isManager($user->getId());
$isOwner   = $project->isOwner($user->getId());
$pid  = $project->getId();
$self = 'project.php?id=' . $pid;

function task_of_project(int $taskId, int $pid): ?array
{
    $t = Task::find($taskId);
    return ($t && (int)$t['project_id'] === $pid) ? $t : null;
}
function deny(): void { http_response_code(403); exit('Accès refusé.'); }

/* ---------- Actions ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    $taskId = (int)($_POST['task_id'] ?? 0);
    $ajax   = ($_POST['ajax'] ?? '') === '1';
    $redirect = $self;

    switch ($action) {
        case 'add_task':
            if (!$isManager) deny();
            $tname = trim($_POST['name'] ?? '');
            $assigned = (int)($_POST['assigned'] ?? 0) ?: null;
            if ($tname === '' || mb_strlen($tname) > 150) { flash('Nom de tâche invalide.'); break; }
            if ($assigned && !$project->isMember($assigned)) { flash('Assigné invalide.'); break; }
            Task::create($pid, $tname, trim($_POST['description'] ?? ''), $assigned);
            flash('Tâche ajoutée.');
            break;

        case 'delete_task':
            if (!$isManager) deny();
            if (task_of_project($taskId, $pid)) {
                TaskImage::purgeFilesForTask($taskId);
                Task::delete($taskId);
                flash('Tâche supprimée.');
            }
            break;

        case 'assign_task':
            if (!$isManager) deny();
            $assigned = (int)($_POST['assigned'] ?? 0) ?: null;
            if (task_of_project($taskId, $pid) && Task::assign($taskId, $assigned)) flash('Tâche assignée.');
            else flash('Assignation impossible.');
            break;

        case 'claim_task':
            if (task_of_project($taskId, $pid) && Task::claim($taskId, $pid, $user->getId())) {
                flash('Tâche prise.');
            } else {
                flash('Cette tâche est déjà assignée.');
            }
            break;

        case 'release_task':
            if (task_of_project($taskId, $pid) && Task::release($taskId, $pid, $user->getId())) {
                flash('Tâche libérée.');
            } else {
                flash('Impossible de libérer cette tâche.');
            }
            break;

        case 'change_status':
            $t = task_of_project($taskId, $pid);
            $mine = $t && (int)$t['assigned_user_id'] === $user->getId();
            if (!$t || !($isManager || $mine)) deny();
            $ok = Task::changeStatus($taskId, $_POST['status'] ?? '');
            if ($ajax) {
                header('Content-Type: application/json');
                echo json_encode(['ok' => $ok]);
                exit;
            }
            break;

        case 'add_subtask':
            $t = task_of_project($taskId, $pid);
            if (!$t || !($isManager || (int)$t['assigned_user_id'] === $user->getId())) deny();
            $title = trim($_POST['title'] ?? '');
            if ($title === '' || mb_strlen($title) > 150) {
                flash('Titre de sous-tâche invalide.');
            } elseif (Subtask::countForTask($taskId) >= 30) {
                flash('Maximum 30 sous-tâches par tâche.');
            } else {
                Subtask::create($taskId, $title);
            }
            $redirect = $self . '&open=' . $taskId . '#task-' . $taskId;
            break;

        case 'toggle_subtask':
            $s = Subtask::find((int)($_POST['subtask_id'] ?? 0));
            if (!$s || (int)$s['project_id'] !== $pid
                || !($isManager || (int)$s['assigned_user_id'] === $user->getId())) deny();
            $ok = Subtask::setDone((int)$s['id'], ($_POST['done'] ?? '') === '1');
            if ($ajax) {
                header('Content-Type: application/json');
                echo json_encode(['ok' => $ok]);
                exit;
            }
            $redirect = $self . '&open=' . (int)$s['task_id'] . '#task-' . (int)$s['task_id'];
            break;

        case 'delete_subtask':
            $s = Subtask::find((int)($_POST['subtask_id'] ?? 0));
            if (!$s || (int)$s['project_id'] !== $pid
                || !($isManager || (int)$s['assigned_user_id'] === $user->getId())) deny();
            Subtask::delete((int)$s['id']);
            $redirect = $self . '&open=' . (int)$s['task_id'] . '#task-' . (int)$s['task_id'];
            break;

        case 'upload_images':
            $t = task_of_project($taskId, $pid);
            if (!$t || !($isManager || (int)$t['assigned_user_id'] === $user->getId())) deny();
            $files = normalize_files($_FILES['images'] ?? []);
            $room = TaskImage::MAX_PER_TASK - TaskImage::countForTask($taskId);
            if (!$files) {
                flash('Aucun fichier reçu.');
            } elseif ($room <= 0) {
                flash('Maximum ' . TaskImage::MAX_PER_TASK . ' images par tâche.');
            } else {
                $added = 0;
                $errs = [];
                foreach (array_slice($files, 0, $room) as $f) {
                    $err = TaskImage::addFromUpload($taskId, $user->getId(), $f);
                    if ($err === null) $added++;
                    else $errs[] = (string)$f['name'] . ' : ' . $err;
                }
                if (count($files) > $room) $errs[] = 'Limite de ' . TaskImage::MAX_PER_TASK . ' images atteinte, les autres sont ignorées.';
                flash($added . ' image(s) ajoutée(s).' . ($errs ? ' ' . implode(' · ', $errs) : ''));
            }
            $redirect = $self . '#task-' . $taskId;
            break;

        case 'delete_image':
            $img = TaskImage::find((int)($_POST['image_id'] ?? 0));
            if (!$img || (int)$img['project_id'] !== $pid
                || !($isManager || (int)$img['assigned_user_id'] === $user->getId())) deny();
            TaskImage::delete((int)$img['id']);
            flash('Image supprimée.');
            $redirect = $self . '#task-' . (int)$img['task_id'];
            break;

        case 'add_link':
            if (!$isManager) deny();
            $url = ProjectLink::normalizeUrl($_POST['url'] ?? '');
            $ltitle = trim($_POST['title'] ?? '');
            if ($url === null) {
                flash('Adresse invalide (http:// ou https:// uniquement).');
            } elseif (mb_strlen($ltitle) > 100) {
                flash('Titre trop long (100 caractères maximum).');
            } elseif (ProjectLink::countForProject($pid) >= 30) {
                flash('Maximum 30 liens par projet.');
            } else {
                if ($ltitle === '') {
                    $ltitle = (string)parse_url($url, PHP_URL_HOST);
                }
                ProjectLink::create($pid, $ltitle, $url, $user->getId());
                flash('Lien ajouté.');
            }
            break;

        case 'delete_link':
            if (!$isManager) deny();
            $link = ProjectLink::find((int)($_POST['link_id'] ?? 0));
            if ($link && (int)$link['project_id'] === $pid) {
                ProjectLink::delete((int)$link['id']);
                flash('Lien supprimé.');
            }
            break;

        case 'add_member':
            if (!$isManager) deny();
            $target = User::findByEmail(trim($_POST['email'] ?? ''));
            $role = ($_POST['role'] ?? '') === 'manager' ? 'manager' : 'membre';
            if (!$target) flash('Aucun utilisateur avec cet email.');
            elseif ($project->addMember($target->getId(), $role)) flash('Membre ajouté.');
            else flash('Cet utilisateur est déjà membre.');
            break;

        case 'remove_member':
            if (!$isManager) deny();
            $targetId = (int)($_POST['user_id'] ?? 0);
            if ($project->isOwner($targetId)) flash('Le créateur du projet ne peut pas être retiré.');
            elseif ($project->removeMember($targetId)) flash('Membre retiré.');
            else flash('Impossible (dernier gestionnaire ou membre inconnu).');
            break;

        case 'leave_project':
            if ($isOwner) { flash('Le créateur ne peut pas quitter son projet : supprimez-le ou transférez-le.'); break; }
            if ($project->removeMember($user->getId())) {
                flash('Vous avez quitté le projet.');
                header('Location: index.php');
                exit;
            }
            flash('Impossible : vous êtes le dernier gestionnaire.');
            break;

        case 'update_project':
            if (!$isManager) deny();
            $status = in_array($_POST['status'] ?? '', ['en_cours', 'termine', 'archive'], true) ? $_POST['status'] : 'en_cours';
            $pname = trim($_POST['name'] ?? '');
            if ($pname === '' || mb_strlen($pname) > 150) { flash('Nom invalide.'); break; }
            $project->update($pname, trim($_POST['description'] ?? ''), $status);
            flash('Projet mis à jour.');
            break;

        case 'delete_project':
            if (!$isOwner) deny();
            TaskImage::purgeFilesForProject($pid);
            $project->delete();
            flash('Projet supprimé.');
            header('Location: index.php');
            exit;
    }
    header('Location: ' . $redirect);
    exit;
}

/* ---------- Données ---------- */
$members = $project->getMembers();
$tasks   = $project->getTasks();
$subtasksByTask = Subtask::forProject($pid);
$imagesByTask = TaskImage::forProject($pid);
$links = ProjectLink::forProject($pid);
$openTask = (int)($_GET['open'] ?? 0);

$columns    = ['a_faire' => 'À faire', 'en_cours' => 'En cours', 'terminee' => 'Terminée'];
$projLabels = ['en_cours' => 'En cours', 'termine' => 'Terminé', 'archive' => 'Archivé'];
$byStatus   = array_fill_keys(array_keys($columns), []);
foreach ($tasks as $t) {
    if (isset($byStatus[$t['status']])) $byStatus[$t['status']][] = $t;
}

$pageTitle = $project->getName();
include __DIR__ . '/../includes/header.php';
?>
<div class="project-head">
    <div>
        <h2><?= e($project->getName()) ?> <span class="badge"><?= e($projLabels[$project->getStatus()] ?? $project->getStatus()) ?></span></h2>
        <p><?= e($project->getDescription()) ?></p>
    </div>
    <button type="button" class="secondary only-mobile" data-toggle-side>👥 Membres et liens</button>
</div>

<div class="layout">
    <!-- Tableau -->
    <div class="board" data-url="<?= e($self) ?>" data-csrf="<?= e(csrf_token()) ?>">
    <?php foreach ($columns as $key => $label): ?>
        <section class="column" data-status="<?= e($key) ?>">
            <h4><?= e($label) ?> <span class="count"><?= count($byStatus[$key]) ?></span></h4>
            <div class="cards"><?php foreach ($byStatus[$key] as $t):
                $mine = (int)$t['assigned_user_id'] === $user->getId();
                $canMove = $isManager || $mine;
                $subs = $subtasksByTask[(int)$t['id']] ?? [];
                $subDone = count(array_filter($subs, fn($s) => (int)$s['is_done'] === 1));
                $imgs = $imagesByTask[(int)$t['id']] ?? [];
            ?><article class="task" id="task-<?= (int)$t['id'] ?>" data-id="<?= (int)$t['id'] ?>" data-canmove="<?= $canMove ? '1' : '0' ?>">
                    <div class="task-row">
                        <div class="task-body">
                            <p class="task-title"><?= e($t['name']) ?></p>
                            <?php if ($t['description']): ?><p class="task-desc"><?= e($t['description']) ?></p><?php endif; ?>

                            <?php if ($imgs || $canMove): ?>
                            <div class="task-images">
                                <?php foreach ($imgs as $im): ?>
                                <div class="img-item">
                                    <a class="img-thumb" href="task_image.php?id=<?= (int)$im['id'] ?>" target="_blank" rel="noopener" title="<?= e($im['original_name']) ?>">
                                        <img src="task_image.php?id=<?= (int)$im['id'] ?>&amp;t=1" alt="<?= e($im['original_name']) ?>" loading="lazy">
                                    </a>
                                    <?php if ($canMove): ?>
                                    <form method="post" class="img-del" data-confirm="Supprimer cette image ?">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete_image">
                                        <input type="hidden" name="image_id" value="<?= (int)$im['id'] ?>">
                                        <button type="submit" title="Supprimer l'image">✕</button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                                <?php if ($canMove && count($imgs) < TaskImage::MAX_PER_TASK): ?>
                                <form method="post" enctype="multipart/form-data" class="img-add-form">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="upload_images">
                                    <input type="hidden" name="task_id" value="<?= (int)$t['id'] ?>">
                                    <label class="img-add" title="Ajouter des images">＋ 🖼
                                        <input type="file" class="img-input" name="images[]" accept="image/jpeg,image/png,image/webp" multiple>
                                    </label>
                                </form>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>

                            <?php if ($subs || $canMove): ?>
                            <details class="subtasks" <?= $openTask === (int)$t['id'] ? 'open' : '' ?>>
                                <summary>
                                    ☑ <span class="sub-count"><?= $subDone ?>/<?= count($subs) ?></span>
                                    <progress class="sub-progress" max="<?= max(1, count($subs)) ?>" value="<?= $subDone ?>"></progress>
                                </summary>
                                <ul class="sub-list">
                                    <?php foreach ($subs as $s): ?>
                                    <li class="<?= $s['is_done'] ? 'done' : '' ?>">
                                        <label>
                                            <input type="checkbox" class="subtask-check" data-id="<?= (int)$s['id'] ?>"
                                                   <?= $s['is_done'] ? 'checked' : '' ?> <?= $canMove ? '' : 'disabled' ?>>
                                            <span><?= e($s['title']) ?></span>
                                        </label>
                                        <?php if ($canMove): ?>
                                        <form method="post" class="inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="delete_subtask">
                                            <input type="hidden" name="subtask_id" value="<?= (int)$s['id'] ?>">
                                            <button type="submit" class="ghost-btn small sub-del" title="Supprimer">✕</button>
                                        </form>
                                        <?php endif; ?>
                                    </li>
                                    <?php endforeach; ?>
                                </ul>
                                <?php if ($canMove): ?>
                                <form method="post" class="sub-add">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="add_subtask">
                                    <input type="hidden" name="task_id" value="<?= (int)$t['id'] ?>">
                                    <input type="text" name="title" placeholder="Nouvelle sous-tâche" maxlength="150" required>
                                    <button type="submit" class="small">+</button>
                                </form>
                                <?php endif; ?>
                            </details>
                            <?php endif; ?>

                            <div class="task-foot">
                                <?php if ($t['assigned_name']): ?>
                                    <?= avatar($t['assigned_name'], (int)$t['assigned_color'], 'sm', (int)$t['assigned_user_id']) ?> <?= e($t['assigned_name']) ?>
                                <?php else: ?>Non assignée<?php endif; ?>
                            </div>
                            <?php if ($canMove): ?>
<div class="move-btns">
    <button type="button" class="secondary small" data-move="prev" aria-label="Colonne précédente">◀</button>
    <button type="button" class="secondary small" data-move="next" aria-label="Colonne suivante">▶</button>
</div>
<?php endif; ?>
                            <?php if ($t['assigned_user_id'] === null): ?>
                            <form method="post" class="inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="claim_task">
                                <input type="hidden" name="task_id" value="<?= (int)$t['id'] ?>">
                                <button type="submit" class="small secondary">Je prends</button>
                            </form>
                            <?php elseif ($mine && !$isManager): ?>
                            <form method="post" class="inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="release_task">
                                <input type="hidden" name="task_id" value="<?= (int)$t['id'] ?>">
                                <button type="submit" class="small ghost-btn">Je libère</button>
                            </form>
                            <?php endif; ?>

                            <?php if ($isManager): ?>
                            <details class="more">
                                <summary>Options</summary>
                                <form method="post">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="assign_task">
                                    <input type="hidden" name="task_id" value="<?= (int)$t['id'] ?>">
                                    <select name="assigned">
                                        <option value="0">— Personne —</option>
                                        <?php foreach ($members as $m): ?>
                                        <option value="<?= (int)$m['id'] ?>" <?= (int)$t['assigned_user_id'] === (int)$m['id'] ? 'selected' : '' ?>><?= e($m['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" class="secondary small">OK</button>
                                </form>
                                <form method="post" data-confirm="Supprimer cette tâche ?">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete_task">
                                    <input type="hidden" name="task_id" value="<?= (int)$t['id'] ?>">
                                    <button type="submit" class="danger small">Supprimer la tâche</button>
                                </form>
                            </details>
                            <?php endif; ?>
                        </div>
                    </div>
                </article><?php endforeach; ?></div>

            <?php if ($isManager && $key === 'a_faire'): ?>
            <details class="addcard">
                <summary>+ Ajouter une tâche</summary>
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="add_task">
                    <input type="text" name="name" placeholder="Titre de la tâche" required maxlength="150">
                    <textarea name="description" rows="2" placeholder="Description (facultatif)"></textarea>
                    <select name="assigned">
                        <option value="0">— Non assignée —</option>
                        <?php foreach ($members as $m): ?>
                        <option value="<?= (int)$m['id'] ?>"><?= e($m['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p><button type="submit" class="small">Ajouter</button></p>
                </form>
            </details>
            <?php endif; ?>
        </section>
    <?php endforeach; ?>
    </div>

    <!-- Panneau latéral -->
    <aside class="side" id="side">
        <details class="panel" open>
            <summary>Membres (<?= count($members) ?>)</summary>
            <div class="panel-body">
                <?php foreach ($members as $m): ?>
                <div class="member">
                    <?= avatar($m['name'], (int)$m['avatar_color'], '', (int)$m['id']) ?>
                    <div class="member-info">
                        <div class="member-name"><?= e($m['name']) ?></div>
                        <div class="member-mail"><?= e($m['email']) ?></div>
                    </div>
                    <span class="badge <?= $m['role'] === 'manager' ? 'manager' : '' ?>">
                        <?= $project->isOwner((int)$m['id']) ? 'Créateur' : e($m['role']) ?>
                    </span>
                    <?php if ($isManager && !$project->isOwner((int)$m['id'])): ?>
                    <form method="post" class="inline" data-confirm="Retirer ce membre du projet ?">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="remove_member">
                        <input type="hidden" name="user_id" value="<?= (int)$m['id'] ?>">
                        <button type="submit" class="ghost-btn small" title="Retirer">✕</button>
                    </form>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </details>

        <?php if ($links || $isManager): ?>
        <details class="panel" open>
            <summary>Liens importants (<?= count($links) ?>)</summary>
            <div class="panel-body">
                <?php if (!$links): ?>
                    <p class="hint">Aucun lien pour le moment.</p>
                <?php endif; ?>
                <ul class="link-list">
                    <?php foreach ($links as $l):
                        if (!preg_match('#^https?://#i', $l['url'])) continue; ?>
                    <li class="link-item">
                        <div class="link-main">
                            <a href="<?= e($l['url']) ?>" target="_blank" rel="noopener noreferrer" title="<?= e($l['url']) ?>">🔗 <?= e($l['title']) ?></a>
                            <span class="link-host"><?= e((string)parse_url($l['url'], PHP_URL_HOST)) ?></span>
                        </div>
                        <?php if ($isManager): ?>
                        <form method="post" class="inline" data-confirm="Supprimer ce lien ?">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete_link">
                            <input type="hidden" name="link_id" value="<?= (int)$l['id'] ?>">
                            <button type="submit" class="ghost-btn small" title="Supprimer">✕</button>
                        </form>
                        <?php endif; ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php if ($isManager): ?>
                <form method="post" class="link-add">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="add_link">
                    <label for="ltitle">Titre (facultatif)</label>
                    <input type="text" id="ltitle" name="title" maxlength="100" placeholder="Maquettes Figma">
                    <label for="lurl">Adresse</label>
                    <input type="text" id="lurl" name="url" maxlength="500" placeholder="https://..." required>
                    <p><button type="submit" class="small">Ajouter le lien</button></p>
                </form>
                <?php endif; ?>
            </div>
        </details>
        <?php endif; ?>

        <?php if ($isManager): ?>
        <details class="panel">
            <summary>Ajouter un membre</summary>
            <div class="panel-body">
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="add_member">
                    <label for="memail">Email</label>
                    <input type="email" id="memail" name="email" required>
                    <label for="mrole">Rôle</label>
                    <select id="mrole" name="role">
                        <option value="membre">Membre</option>
                        <option value="manager">Gestionnaire</option>
                    </select>
                    <p><button type="submit" class="small">Ajouter</button></p>
                </form>
            </div>
        </details>

        <details class="panel">
            <summary>Paramètres du projet</summary>
            <div class="panel-body">
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="update_project">
                    <label for="pname">Nom</label>
                    <input type="text" id="pname" name="name" value="<?= e($project->getName()) ?>" required>
                    <label for="pdesc">Description</label>
                    <textarea id="pdesc" name="description" rows="3"><?= e($project->getDescription()) ?></textarea>
                    <label for="pstatus">Statut</label>
                    <select id="pstatus" name="status">
                        <?php foreach ($projLabels as $k => $l): ?>
                        <option value="<?= e($k) ?>" <?= $project->getStatus() === $k ? 'selected' : '' ?>><?= e($l) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p><button type="submit" class="small">Enregistrer</button></p>
                </form>
                <?php if ($isOwner): ?>
                <form method="post" data-confirm="Supprimer définitivement ce projet et toutes ses tâches ?">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete_project">
                    <button type="submit" class="danger small">Supprimer le projet</button>
                </form>
                <?php endif; ?>
            </div>
        </details>
        <?php endif; ?>

        <?php if (!$isOwner): ?>
        <form method="post" data-confirm="Quitter ce projet ?">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="leave_project">
            <button type="submit" class="secondary">Quitter le projet</button>
        </form>
        <?php endif; ?>
    </aside>
</div>
<div class="overlay" id="overlay"></div>
<section class="chat" id="chat" data-project-id="<?= $pid ?>">
    <h3>💬 Discussion</h3>
    <div class="chat-list" id="chat-list" aria-live="polite"></div>
    <form class="chat-form" id="chat-form" method="post" action="chat_api.php">
        <?= csrf_field() ?>
        <input type="hidden" name="project_id" value="<?= $pid ?>">
        <textarea id="chat-input" name="body" rows="1" maxlength="2000" placeholder="Écrire un message… (Entrée pour envoyer)" required></textarea>
        <button type="submit">Envoyer</button>
    </form>
</section>
<script src="assets/js/chat.js?v=<?= filemtime(__DIR__ . '/assets/js/chat.js') ?>" defer></script>
<script src="assets/js/live.js?v=<?= filemtime(__DIR__ . '/assets/js/live.js') ?>" defer></script>
<?php include __DIR__ . '/../includes/footer.php'; ?>