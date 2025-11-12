<?php
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['user_id'])) { echo json_encode(['ok'=>false,'error'=>'auth']); exit; }

try {
    $pdo = Database::getInstance()->getConnection();
    $uid = (int)$_SESSION['user_id'];

    // Resolve family for current user
    // Get current user's family and email
    $st = $pdo->prepare('SELECT families_id, email FROM users WHERE id = ? LIMIT 1');
    $st->execute([$uid]);
    $rowUser = $st->fetch(PDO::FETCH_ASSOC);
    $familiesId = (int)($rowUser['families_id'] ?? 0);
    $sessionEmail = strtolower(trim((string)($rowUser['email'] ?? '')));
    if (!$familiesId) { echo json_encode(['ok'=>false,'error'=>'no_family']); exit; }

    $in = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $email = trim(strtolower((string)($in['email'] ?? '')));
    if (!$email) { echo json_encode(['ok'=>false,'error'=>'email_required']); exit; }

    // Check user existence
    $u = $pdo->prepare('SELECT id, families_id FROM users WHERE LOWER(email) = ? LIMIT 1');
    $u->execute([$email]);
    $user = $u->fetch();
    $existsUser = $user ? true : false;
    $userId = $user ? (int)$user['id'] : null;
    $userOtherFamily = $user && (int)$user['families_id'] !== $familiesId;

    // Check membership in this family
    $membership = null; $isMember = false;
    if ($userId) {
        $m = $pdo->prepare('SELECT role, status FROM family_members WHERE family_id = ? AND user_id = ? LIMIT 1');
        $m->execute([$familiesId, $userId]);
        $mem = $m->fetch();
        if ($mem) { $membership = ['role'=>$mem['role'], 'status'=>$mem['status']]; $isMember = ($mem['status'] === 'active'); }
    }

    // Check existing invites for this email in this family
    $invites = [ 'pending'=>[], 'recent'=>[] ];
    $p = $pdo->prepare('SELECT id, status, token_expires_at, created_at FROM invites WHERE families_id = ? AND LOWER(email) = ? ORDER BY created_at DESC LIMIT 10');
    $p->execute([$familiesId, $email]);
    $rows = $p->fetchAll();
    foreach ($rows as $r) {
        $item = [ 'id'=>(int)$r['id'], 'status'=>$r['status'], 'token_expires_at'=>$r['token_expires_at'], 'created_at'=>$r['created_at'] ];
        if ($r['status'] === 'pending') $invites['pending'][] = $item; else $invites['recent'][] = $item;
    }

    // Cross-family existence checks (users and persons)
    $warnings = [];
    $crossFamily = [ 'users'=>false, 'persons'=>false ];
    if ($userOtherFamily) { $crossFamily['users'] = true; }
    // persons table match in other families
    $pp = $pdo->prepare('SELECT id, family_id FROM persons WHERE LOWER(email) = ? AND family_id <> ? LIMIT 1');
    $pp->execute([$email, $familiesId]);
    $pr = $pp->fetch(PDO::FETCH_ASSOC);
    if ($pr) { $crossFamily['persons'] = true; }
    if ($crossFamily['users'] || $crossFamily['persons']) { $warnings[] = 'cross_family_exists'; }

    $canInvite = true; $reason = null;
    // self-invite check
    if ($sessionEmail && $email === $sessionEmail) { $canInvite = false; $reason = 'self_invite'; }
    elseif ($isMember) { $canInvite = false; $reason = 'already_member'; }
    elseif (!empty($invites['pending'])) { $canInvite = false; $reason = 'pending_invite_exists'; }

    echo json_encode([
        'ok'=>true,
        'email'=>$email,
        'user'=>[ 'exists'=>$existsUser, 'id'=>$userId ],
        'membership'=>$membership,
        'invites'=>$invites,
        'can_invite'=>$canInvite,
        'reason'=>$reason,
        'warnings'=>$warnings,
        'cross_family'=>$crossFamily
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'server_error']);
}

?>
