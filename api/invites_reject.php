<?php
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();

$token = $_GET['token'] ?? ($_POST['token'] ?? '');
if (!$token) { echo json_encode(['ok'=>false,'error'=>'token_required']); exit; }
$hash = hash('sha256', $token);

try {
    $pdo = Database::getInstance()->getConnection();
    $sel = $pdo->prepare('SELECT * FROM invites WHERE token_hash = ? AND status = "pending" LIMIT 1');
    $sel->execute([$hash]);
    $inv = $sel->fetch();
    if (!$inv) { echo json_encode(['ok'=>false,'error'=>'invalid_or_used']); exit; }
    if (new DateTime() > new DateTime($inv['token_expires_at'])) { echo json_encode(['ok'=>false,'error'=>'expired']); exit; }

    if (empty($_SESSION['user_id'])) { echo json_encode(['ok'=>false,'require_login'=>true]); exit; }
    $uid = (int)$_SESSION['user_id'];

    $pdo->beginTransaction();

    // Mark invite as rejected
    $rej = $pdo->prepare('UPDATE invites SET status = "rejected", rejected_at = NOW(), rejected_by = ? WHERE id = ?');
    $rej->execute([$uid, (int)$inv['id']]);

    // Dismiss associated PDs created for this invite
    try {
        $dismiss = $pdo->prepare('UPDATE possible_duplicates SET status = "dismissed" WHERE invite_id = ? AND status IN ("pending","reviewed")');
        $dismiss->execute([(int)$inv['id']]);
    } catch (Throwable $_pdE) { /* PD table or columns may not exist; ignore */ }

    $pdo->commit();
    echo json_encode(['ok'=>true]);
} catch (Throwable $e) {
    if ($pdo && $pdo->inTransaction()) { $pdo->rollBack(); }
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'server_error']);
}

?>
