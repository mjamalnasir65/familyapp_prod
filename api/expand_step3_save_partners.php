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

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data) || !isset($data['person_id'])) {
    echo json_encode(['ok'=>false,'error'=>'invalid_payload']);
    exit;
}
$personId = (int)$data['person_id'];
$partners = isset($data['partners']) && is_array($data['partners']) ? $data['partners'] : [];

if (!$personId) { echo json_encode(['ok'=>false,'error'=>'missing_person']); exit; }

try {
    $pdo = Database::getInstance()->getConnection();

    // Resolve family and target person
    $ps = $pdo->prepare('SELECT id, family_id, gender FROM persons WHERE id = ? LIMIT 1');
    $ps->execute([$personId]);
    $pRow = $ps->fetch();
    if (!$pRow) { echo json_encode(['ok'=>false,'error'=>'person_not_found']); exit; }
    $familyId = (int)$pRow['family_id'];
    $selfGender = strtolower((string)$pRow['gender']);

    // Verify user belongs to same family
    $u = $pdo->prepare('SELECT families_id FROM users WHERE id = ? LIMIT 1');
    $u->execute([ (int)$_SESSION['user_id'] ]);
    $ur = $u->fetch();
    $familyIdUser = (int)($ur['families_id'] ?? 0);
    if ($familyIdUser && $familyIdUser !== $familyId) { echo json_encode(['ok'=>false,'error'=>'forbidden_family']); exit; }

    $allowedGender = ['male','female','other','prefer_not_to_say'];
    $allowedRelTypes = ['marriage','divorced','separated','widowed'];

    $created = []; $updated = []; $unions = [];

    $pdo->beginTransaction();

    foreach ($partners as $p) {
        $name = trim($p['name'] ?? ''); if ($name==='') continue;
        $gender = in_array(($p['gender']??''), $allowedGender, true) ? $p['gender'] : '';
        if ($gender === '' && ($selfGender==='male' || $selfGender==='female')) {
            $gender = ($selfGender==='male') ? 'female' : 'male';
        }
        if ($gender === '') $gender = 'other';

        $status = trim($p['status'] ?? '');
        $birth = trim($p['birth_date'] ?? '');
        $death = trim($p['death_date'] ?? '');
        $isAlive = ($status === 'deceased') ? 0 : 1;

        $relType = in_array(($p['rel_type']??'marriage'), $allowedRelTypes, true) ? $p['rel_type'] : 'marriage';
        $isCurrent = ($relType === 'marriage') ? 1 : 0;

        // Upsert partner
        $sel = $pdo->prepare('SELECT id FROM persons WHERE family_id=? AND full_name=? AND gender=? LIMIT 1');
        $sel->execute([$familyId, $name, $gender]);
        $row = $sel->fetch();
        if ($row) {
            $pid = (int)$row['id'];
            $upd = $pdo->prepare('UPDATE persons SET is_alive=?, birth_date=?, death_date=?, updated_at=NOW() WHERE id=?');
            $upd->execute([$isAlive, ($birth?:null), ($death?:null), $pid]);
            $updated[] = $pid;
        } else {
            $ins = $pdo->prepare('INSERT INTO persons (family_id, full_name, gender, is_alive, birth_date, death_date, created_by) VALUES (?,?,?,?,?,?,?)');
            $ins->execute([$familyId, $name, $gender, $isAlive, ($birth?:null), ($death?:null), (int)$_SESSION['user_id']]);
            $pid = (int)$pdo->lastInsertId();
            $created[] = $pid;
        }

        // Upsert union
        $p1 = min($personId, $pid); $p2 = max($personId, $pid);
        $union = $pdo->prepare('INSERT INTO unions (family_id, person1_id, person2_id, union_type, is_current) VALUES (?,?,?,?,?)
                                 ON DUPLICATE KEY UPDATE union_type=VALUES(union_type), is_current=VALUES(is_current), updated_at=NOW()');
        $union->execute([$familyId, $p1, $p2, $relType, $isCurrent]);
        $unions[] = ['p1'=>$p1,'p2'=>$p2,'type'=>$relType,'current'=>$isCurrent];
    }

    $pdo->commit();

    echo json_encode(['ok'=>true,'created'=>$created,'updated'=>$updated,'unions'=>$unions]);
} catch (Throwable $e) {
    if (!empty($pdo) && $pdo->inTransaction()) { $pdo->rollBack(); }
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'server_error']);
}
