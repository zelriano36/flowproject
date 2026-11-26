<?php
require __DIR__ . '/../includes/bootstrap.php';
$user = require_login();
set_exception_handler(function (Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Erreur serveur : ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
});
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function out(array $data, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

$pid = (int)($_GET['project_id'] ?? $_POST['project_id'] ?? 0);
$project = $pid ? Project::findById($pid) : null;
if (!$project || !$project->isMember($user->getId())) {
    out(['error' => 'Accès refusé.'], 403);
}
$isManager = $project->isManager($user->getId());

function chat_row(array $r, int $uid, bool $isManager): array
{
    return [
        'id'         => (int)$r['id'],
        'name'       => $r['name'],
        'avatar'     => avatar($r['name'], (int)$r['avatar_color'], 'sm', (int)$r['user_id']),
        'body'       => $r['body'],
        'time'       => date('d/m H:i', strtotime($r['created_at'])),
        'mine'       => (int)$r['user_id'] === $uid,
        'can_delete' => $isManager || (int)$r['user_id'] === $uid,
    ];
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $after = (int)($_GET['after'] ?? 0);
    $rows = $after > 0 ? ProjectMessage::after($pid, $after) : ProjectMessage::latest($pid, 50);
    out([
        'messages' => array_map(fn($r) => chat_row($r, $user->getId(), $isManager), $rows),
        'ids'      => ProjectMessage::recentIds($pid),
    ]);
}

csrf_check();
$action = $_POST['action'] ?? '';

if ($action === 'send') {
    $body = trim((string)($_POST['body'] ?? ''));
    $len = mb_strlen($body);
    if ($len < 1 || $len > 2000) {
        out(['error' => 'Message vide ou trop long (2000 caractères maximum).'], 422);
    }
    $id = ProjectMessage::create($pid, $user->getId(), $body);
    $row = ProjectMessage::find($id);
    out(['message' => chat_row($row, $user->getId(), $isManager)]);
}

if ($action === 'delete') {
    $m = ProjectMessage::find((int)($_POST['message_id'] ?? 0));
    if (!$m || (int)$m['project_id'] !== $pid) {
        out(['error' => 'Message introuvable.'], 404);
    }
    if (!$isManager && (int)$m['user_id'] !== $user->getId()) {
        out(['error' => 'Suppression non autorisée.'], 403);
    }
    ProjectMessage::delete((int)$m['id']);
    out(['ok' => true]);
}

    out([
        'messages' => array_map(fn($r) => chat_row($r, $user->getId(), $isManager), $rows),
        'ids'      => ProjectMessage::recentIds($pid),
    ]);