<?php
require_once __DIR__ . '/_init.php';

header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['user_id'])) { echo json_encode(['ok'=>false,'error'=>'auth']); exit; }

try {
    $pdo = Database::getInstance()->getConnection();
    $uid = (int)$_SESSION['user_id'];

    // Get current user's email
    $u = $pdo->prepare('SELECT email FROM users WHERE id = ? LIMIT 1');
    $u->execute([$uid]);
    $email = strtolower(trim((string)($u->fetchColumn() ?: '')));
    if (!$email) { echo json_encode(['ok'=>false,'error'=>'no_email']); exit; }

    // Pending, not expired invites addressed to this email
    $q = $pdo->prepare('SELECT i.id, i.families_id, i.email, i.role, i.scope_type, i.parent1_id, i.parent2_id, i.person_id,
                               i.message, i.status, i.token_expires_at, i.created_at, i.last_sent_at, i.sent_count,
                               f.name AS family_name
                        FROM invites i
                        JOIN families f ON f.id = i.families_id
                        WHERE LOWER(i.email) = ? AND i.status = "pending" AND (i.token_expires_at IS NULL OR i.token_expires_at > NOW())
                        ORDER BY i.created_at DESC LIMIT 200');
    $q->execute([$email]);
    $items = $q->fetchAll();

    echo json_encode(['ok'=>true,'items'=>$items]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'server_error']);
}

?>
