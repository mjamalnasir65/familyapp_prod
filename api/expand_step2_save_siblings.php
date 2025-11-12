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
$siblings = isset($data['siblings']) && is_array($data['siblings']) ? $data['siblings'] : [];

if (!$personId) { echo json_encode(['ok'=>false,'error'=>'missing_person']); exit; }

try {
    $pdo = Database::getInstance()->getConnection();

    // Resolve family and target person
    $ps = $pdo->prepare('SELECT id, family_id FROM persons WHERE id = ? LIMIT 1');
    $ps->execute([$personId]);
    $pRow = $ps->fetch();
    if (!$pRow) { echo json_encode(['ok'=>false,'error'=>'person_not_found']); exit; }
    $familyId = (int)$pRow['family_id'];

    // Verify user belongs to same family
    $u = $pdo->prepare('SELECT families_id FROM users WHERE id = ? LIMIT 1');
    $u->execute([ (int)$_SESSION['user_id'] ]);
    $ur = $u->fetch();
    $familyIdUser = (int)($ur['families_id'] ?? 0);
    if ($familyIdUser && $familyIdUser !== $familyId) { echo json_encode(['ok'=>false,'error'=>'forbidden_family']); exit; }

    // Get target parents to link for each sibling
    $rel = $pdo->prepare('SELECT r.parent_id, p.gender FROM relationships r JOIN persons p ON p.id = r.parent_id WHERE r.family_id = ? AND r.child_id = ?');
    $rel->execute([$familyId, $personId]);
    $parents = $rel->fetchAll();
    $fatherId = null; $motherId = null; $parentIds = [];
    foreach ($parents as $row) {
        $pid = (int)$row['parent_id'];
        $parentIds[] = $pid;
        $g = strtolower((string)$row['gender']);
        if ($g === 'male' && !$fatherId) $fatherId = $pid;
        if ($g === 'female' && !$motherId) $motherId = $pid;
    }

    $allowedGender = ['male','female','other','prefer_not_to_say'];

    $created = []; $updated = []; $linked = [];

    $pdo->beginTransaction();

    foreach ($siblings as $sib) {
        $name = trim($sib['name'] ?? '');
        if ($name === '') continue;
        $gender = in_array(($sib['gender']??''), $allowedGender, true) ? $sib['gender'] : 'other';
        $status = trim($sib['status'] ?? '');
        $birth = trim($sib['birth_date'] ?? '');
        $death = trim($sib['death_date'] ?? '');
        $isAlive = ($status === 'deceased') ? 0 : 1;

        // Upsert by (family, full_name, gender)
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

        // Link to both parents if available
        if ($fatherId) {
            $insRel = $pdo->prepare('INSERT INTO relationships (family_id, parent_id, child_id, relationship_type) VALUES (?,?,?,?)
                                     ON DUPLICATE KEY UPDATE relationship_type=VALUES(relationship_type)');
            $insRel->execute([$familyId, $fatherId, $pid, 'biological']);
            $linked[] = ['parent'=>$fatherId,'child'=>$pid];
        }
        if ($motherId) {
            $insRel = $pdo->prepare('INSERT INTO relationships (family_id, parent_id, child_id, relationship_type) VALUES (?,?,?,?)
                                     ON DUPLICATE KEY UPDATE relationship_type=VALUES(relationship_type)');
            $insRel->execute([$familyId, $motherId, $pid, 'biological']);
            $linked[] = ['parent'=>$motherId,'child'=>$pid];
        }
    }

    $pdo->commit();

    echo json_encode(['ok'=>true,'created'=>$created,'updated'=>$updated,'linked'=>$linked]);
} catch (Throwable $e) {
    if (!empty($pdo) && $pdo->inTransaction()) { $pdo->rollBack(); }
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'server_error']);
}
