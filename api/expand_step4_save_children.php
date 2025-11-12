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
if (!is_array($data)) { echo json_encode(['ok'=>false,'error'=>'invalid_payload']); exit; }

$p1 = (int)($data['parent1_id'] ?? $data['person_id'] ?? 0);
$p2 = (int)($data['parent2_id'] ?? $data['partner_id'] ?? 0);
$children = isset($data['children']) && is_array($data['children']) ? $data['children'] : [];
if (!$p1 || !$p2) { echo json_encode(['ok'=>false,'error'=>'missing_parents']); exit; }

try {
    $pdo = Database::getInstance()->getConnection();

    // Resolve family and parents
    $ps = $pdo->prepare('SELECT id, family_id FROM persons WHERE id IN (?, ?) ORDER BY id');
    $ps->execute([$p1, $p2]);
    $rows = $ps->fetchAll();
    if (count($rows) !== 2) { echo json_encode(['ok'=>false,'error'=>'parent_not_found']); exit; }
    $familyId = (int)$rows[0]['family_id'];
    if ($familyId !== (int)$rows[1]['family_id']) { echo json_encode(['ok'=>false,'error'=>'parents_different_families']); exit; }

    // Verify user belongs to same family
    $u = $pdo->prepare('SELECT families_id FROM users WHERE id = ? LIMIT 1');
    $u->execute([ (int)$_SESSION['user_id'] ]);
    $ur = $u->fetch();
    $familyIdUser = (int)($ur['families_id'] ?? 0);
    if ($familyIdUser && $familyIdUser !== $familyId) { echo json_encode(['ok'=>false,'error'=>'forbidden_family']); exit; }

    $allowedGender = ['male','female','other','prefer_not_to_say'];
    $created = []; $updated = []; $linked = [];

    $pdo->beginTransaction();

    foreach ($children as $ch) {
        $name = trim($ch['name'] ?? '');
        if ($name === '') continue;
        $gender = in_array(($ch['gender']??''), $allowedGender, true) ? $ch['gender'] : 'other';
        $status = trim($ch['status'] ?? '');
        $birth = trim($ch['birth_date'] ?? '');
        $death = trim($ch['death_date'] ?? '');
        $isAlive = ($status === 'deceased') ? 0 : 1;

        // Upsert a child by (family, full_name, gender)
        $sel = $pdo->prepare('SELECT id FROM persons WHERE family_id=? AND full_name=? AND gender=? LIMIT 1');
        $sel->execute([$familyId, $name, $gender]);
        $row = $sel->fetch();
        if ($row) {
            $cid = (int)$row['id'];
            $upd = $pdo->prepare('UPDATE persons SET is_alive=?, birth_date=?, death_date=?, updated_at=NOW() WHERE id=?');
            $upd->execute([$isAlive, ($birth?:null), ($death?:null), $cid]);
            $updated[] = $cid;
        } else {
            $ins = $pdo->prepare('INSERT INTO persons (family_id, full_name, gender, is_alive, birth_date, death_date, created_by) VALUES (?,?,?,?,?,?,?)');
            $ins->execute([$familyId, $name, $gender, $isAlive, ($birth?:null), ($death?:null), (int)$_SESSION['user_id']]);
            $cid = (int)$pdo->lastInsertId();
            $created[] = $cid;
        }

        // Link to both parents (idempotent via ON DUPLICATE KEY)
        $insRel = $pdo->prepare('INSERT INTO relationships (family_id, parent_id, child_id, relationship_type) VALUES (?,?,?,?)
                                 ON DUPLICATE KEY UPDATE relationship_type=VALUES(relationship_type)');
        $insRel->execute([$familyId, $p1, $cid, 'biological']);
        $linked[] = ['parent'=>$p1,'child'=>$cid];
        $insRel->execute([$familyId, $p2, $cid, 'biological']);
        $linked[] = ['parent'=>$p2,'child'=>$cid];
    }

    $pdo->commit();

    echo json_encode(['ok'=>true,'created'=>$created,'updated'=>$updated,'linked'=>$linked]);
} catch (Throwable $e) {
    if (!empty($pdo) && $pdo->inTransaction()) { $pdo->rollBack(); }
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'server_error']);
}

?>