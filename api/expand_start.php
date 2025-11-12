<?php
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok'=>false,'error'=>'method_not_allowed']);
    exit;
}

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok'=>false,'error'=>'unauthorized']);
    exit;
}

$personId = (int)($_POST['person_id'] ?? 0);
if (!$personId) { echo json_encode(['ok'=>false,'error'=>'missing_person']); exit; }

try {
    $pdo = Database::getInstance()->getConnection();

    // Resolve user's family
    $u = $pdo->prepare('SELECT families_id, email FROM users WHERE id = ? LIMIT 1');
    $u->execute([ (int)$_SESSION['user_id'] ]);
    $ur = $u->fetch();
    $familyIdUser = (int)($ur['families_id'] ?? 0);
    $userEmail = $ur['email'] ?? null;

    // Load person and ensure access
    $p = $pdo->prepare('SELECT id, family_id, full_name FROM persons WHERE id = ? LIMIT 1');
    $p->execute([$personId]);
    $pr = $p->fetch();
    if (!$pr) { echo json_encode(['ok'=>false,'error'=>'person_not_found']); exit; }

    $familyId = (int)$pr['family_id'];
    if ($familyIdUser && $familyIdUser !== $familyId) {
        // Simple policy: user must belong to same family as person
        echo json_encode(['ok'=>false,'error'=>'forbidden_family']);
        exit;
    }

    // Count parents
    $rp = $pdo->prepare('SELECT p.id, p.full_name, p.gender FROM relationships r JOIN persons p ON p.id = r.parent_id WHERE r.family_id = ? AND r.child_id = ?');
    $rp->execute([$familyId, $personId]);
    $parents = $rp->fetchAll();

    $hasFather = false; $hasMother = false;
    foreach ($parents as $row) {
        if ($row['gender'] === 'male') $hasFather = true;
        if ($row['gender'] === 'female') $hasMother = true;
    }

    $parentCount = count($parents);
    $next = ($parentCount >= 2) ? 'siblings' : 'parents';

    // Start expand session context
    $expandId = bin2hex(random_bytes(8));
    $_SESSION['expand'] = [
        'expand_id' => $expandId,
        'family_id' => $familyId,
        'person_id' => (int)$personId,
        'started_at' => time(),
        'by_user' => (int)$_SESSION['user_id']
    ];

    echo json_encode([
        'ok'=>true,
        'expand_id'=>$expandId,
        'family_id'=>$familyId,
        'person_id'=>(int)$personId,
        'next_step'=>$next,
        'prefill'=>[
            'parent_count'=>$parentCount,
            'has_father'=>$hasFather,
            'has_mother'=>$hasMother
        ]
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'server_error']);
}
