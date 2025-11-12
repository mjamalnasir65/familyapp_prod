<?php
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['user_id'])) { echo json_encode(['ok'=>false,'error'=>'auth']); exit; }

try {
    $pdo = Database::getInstance()->getConnection();
    $uid = (int)$_SESSION['user_id'];
    $in = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $inviteId = (int)($in['id'] ?? 0);
    if (!$inviteId) { echo json_encode(['ok'=>false,'error'=>'missing_id']); exit; }

    // Resolve family and ensure ownership/editor rights (simplified: member in family)
    $st = $pdo->prepare('SELECT families_id FROM users WHERE id=?'); $st->execute([$uid]);
    $familiesId = (int)($st->fetchColumn() ?: 0);
    if (!$familiesId) { echo json_encode(['ok'=>false,'error'=>'no_family']); exit; }

    $q = $pdo->prepare('UPDATE invites SET status="revoked", updated_at = NOW() WHERE id = ? AND families_id = ? AND status = "pending"');
    $q->execute([$inviteId,$familiesId]);
    echo json_encode(['ok'=>true,'affected'=>$q->rowCount()]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'server_error']);
}

?>
