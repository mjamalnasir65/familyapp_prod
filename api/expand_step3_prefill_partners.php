<?php
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/ActionLogger.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['ok'=>false,'error'=>'method_not_allowed']);
    exit;
}

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok'=>false,'error'=>'unauthorized']);
    exit;
}

$personId = (int)($_GET['person_id'] ?? ($_SESSION['expand']['person_id'] ?? 0));
if (!$personId) { echo json_encode(['ok'=>false,'error'=>'missing_person']); exit; }

try {
    $pdo = Database::getInstance()->getConnection();
    ActionLogger::log('expand_step3_prefill_partners:start', ['person_id'=>$personId]);

    // Load person and family
    $ps = $pdo->prepare('SELECT id, family_id, full_name, gender FROM persons WHERE id = ? LIMIT 1');
    $ps->execute([$personId]);
    $pRow = $ps->fetch();
    if (!$pRow) { echo json_encode(['ok'=>false,'error'=>'person_not_found']); exit; }
    $familyId = (int)$pRow['family_id'];
    $personName = (string)$pRow['full_name'];
    $personGender = (string)$pRow['gender'];

    // Verify membership
    $u = $pdo->prepare('SELECT families_id FROM users WHERE id = ? LIMIT 1');
    $u->execute([ (int)$_SESSION['user_id'] ]);
    $ur = $u->fetch();
    $familyIdUser = (int)($ur['families_id'] ?? 0);
    if ($familyIdUser && $familyIdUser !== $familyId) {
        echo json_encode(['ok'=>false,'error'=>'forbidden_family']);
        exit;
    }

    // Existing partners via unions table
    $sql = 'SELECT u.id as union_id,
           CASE WHEN u.person1_id = :pidA THEN u.person2_id ELSE u.person1_id END AS partner_id,
           u.union_type, u.is_current,
           p.full_name, p.gender, p.is_alive, p.birth_date, p.death_date
        FROM unions u
        JOIN persons p ON p.id = CASE WHEN u.person1_id = :pidB THEN u.person2_id ELSE u.person1_id END
        WHERE u.family_id = :fid AND (u.person1_id = :pidC OR u.person2_id = :pidD)
        ORDER BY u.updated_at DESC, u.id DESC';
    $st = $pdo->prepare($sql);
    $st->execute([':pidA'=>$personId, ':pidB'=>$personId, ':pidC'=>$personId, ':pidD'=>$personId, ':fid'=>$familyId]);
    $partners = [];
    while ($r = $st->fetch()) {
        $partners[] = [
            'partner_id' => (int)$r['partner_id'],
            'name' => $r['full_name'],
            'gender' => $r['gender'],
            'status' => ((int)$r['is_alive']===1 ? 'living' : 'deceased'),
            'birth_date' => $r['birth_date'],
            'death_date' => $r['death_date'],
            'rel_type' => $r['union_type'],
            'is_current' => (int)$r['is_current'] === 1
        ];
    }

    $out = [
        'ok'=>true,
        'family_id'=>$familyId,
        'person_id'=>$personId,
        'person_name'=>$personName,
        'person_gender'=>$personGender,
        'partners'=>$partners
    ];
    ActionLogger::log('expand_step3_prefill_partners:success', ['person_id'=>$personId,'partners'=>count($partners)]);
    echo json_encode($out);
} catch (Throwable $e) {
    ActionLogger::log('expand_step3_prefill_partners:error', ['person_id'=>$personId,'error'=>'server_error']);
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'server_error']);
}
