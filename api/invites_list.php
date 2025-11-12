<?php
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['user_id'])) { echo json_encode(['ok'=>false,'error'=>'auth']); exit; }

try {
    $pdo = Database::getInstance()->getConnection();
    $uid = (int)$_SESSION['user_id'];
    $st = $pdo->prepare('SELECT families_id FROM users WHERE id = ? LIMIT 1');
    $st->execute([$uid]);
    $familyId = (int)($st->fetchColumn() ?: 0);
    if (!$familyId) { echo json_encode(['ok'=>false,'error'=>'no_family']); exit; }

    $status = $_GET['status'] ?? $_POST['status'] ?? null;
    $allowed = ['pending','accepted','revoked','expired','canceled'];
    $where = 'families_id = ?';
    $args = [$familyId];
    if ($status && in_array($status, $allowed, true)) { $where .= ' AND status = ?'; $args[] = $status; }

    $q = $pdo->prepare("SELECT id,email,inviter_id,invited_user_id,role,scope_type,parent1_id,parent2_id,person_id,
                    can_edit,can_add,can_delete,can_manage_files,message,status,token_expires_at,
                    last_sent_at,sent_count,created_at,updated_at
                FROM invites WHERE $where ORDER BY created_at DESC LIMIT 500");
    $q->execute($args);
    $items = $q->fetchAll();
    echo json_encode(['ok'=>true,'items'=>$items]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'server_error']);
}

?>
