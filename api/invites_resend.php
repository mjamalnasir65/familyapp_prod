<?php
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Mailer.php';
require_once __DIR__ . '/invite_url_helper.php';

header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['user_id'])) { echo json_encode(['ok'=>false,'error'=>'auth']); exit; }

try {
    $pdo = Database::getInstance()->getConnection();
    $uid = (int)$_SESSION['user_id'];
    $in = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $inviteId = (int)($in['id'] ?? 0);
    if (!$inviteId) { echo json_encode(['ok'=>false,'error'=>'missing_id']); exit; }

    // Resolve family for current user
    $st = $pdo->prepare('SELECT families_id FROM users WHERE id = ? LIMIT 1');
    $st->execute([$uid]);
    $familiesId = (int)($st->fetchColumn() ?: 0);
    if (!$familiesId) { echo json_encode(['ok'=>false,'error'=>'no_family']); exit; }

    // Load pending invite details
    $sel = $pdo->prepare('SELECT email, role, scope_type, message FROM invites WHERE id = ? AND families_id = ? AND status = "pending" LIMIT 1');
    $sel->execute([$inviteId,$familiesId]);
    $row = $sel->fetch(PDO::FETCH_ASSOC);
    if (!$row) { echo json_encode(['ok'=>false,'error'=>'not_found_or_not_pending']); exit; }

    // Issue a fresh short, URL-safe token (~128-bit) using helper (with error handling)
    try {
        [$token, $hash] = generateInviteToken();
    } catch (RuntimeException $ex) {
        echo json_encode(['ok'=>false,'error'=>'server_error']);
        exit;
    }

    // Update invite with new token and send count
    $up = $pdo->prepare('UPDATE invites SET token_hash = ?, last_sent_at = NOW(), sent_count = sent_count + 1 WHERE id = ? AND families_id = ?');
    $up->execute([$hash,$inviteId,$familiesId]);

    // Build URL and determine language
    $lang = normalizeInviteLang($in['lang'] ?? null, $_SESSION['lang'] ?? null);
    $inviteUrl = buildInviteUrl($token, $lang);

    // Try to fetch family name for email context (best-effort)
    $familyName = 'Your Family';
    try {
        $qf = $pdo->prepare('SELECT name FROM families WHERE id = ? LIMIT 1');
        $qf->execute([$familiesId]);
        $familyName = (string)($qf->fetchColumn() ?: $familyName);
    } catch (Throwable $__ignore) {}

    // Send invite email (best-effort; API still returns ok even if email fails)
    $mailResult = Mailer::sendInvite(
        (string)$row['email'],
        $inviteUrl,
        (string)($row['message'] ?? ''),
        [
            'family_name' => $familyName,
            'role' => (string)($row['role'] ?? 'viewer'),
            'scope' => (string)($row['scope_type'] ?? 'family'),
        ]
    );

    echo json_encode(['ok'=>true,'invite_url'=>$inviteUrl,'email'=>$mailResult]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'server_error']);
}

?>
