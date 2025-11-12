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

$personId = (int)($_POST['person_id'] ?? ($_SESSION['expand']['person_id'] ?? 0));
$fatherName = trim($_POST['father_name'] ?? '');
$fatherStatus = trim($_POST['father_status'] ?? ''); // ''|living|deceased
$fatherBirth = trim($_POST['father_birth_date'] ?? '');
$fatherDeath = trim($_POST['father_death_date'] ?? '');
$fatherRelType = trim($_POST['father_rel_type'] ?? 'biological');

$motherName = trim($_POST['mother_name'] ?? '');
$motherStatus = trim($_POST['mother_status'] ?? '');
$motherBirth = trim($_POST['mother_birth_date'] ?? '');
$motherDeath = trim($_POST['mother_death_date'] ?? '');
$motherRelType = trim($_POST['mother_rel_type'] ?? 'biological');

if (!$personId) { echo json_encode(['ok'=>false,'error'=>'missing_person']); exit; }

// SEA rule: require both parents
if ($fatherName === '' || $motherName === '') {
    echo json_encode(['ok'=>false,'error'=>'both_parents_required']);
    exit;
}

$allowedRel = ['biological','step'];
if (!in_array($fatherRelType, $allowedRel, true)) $fatherRelType = 'biological';
if (!in_array($motherRelType, $allowedRel, true)) $motherRelType = 'biological';

try {
    $pdo = Database::getInstance()->getConnection();

    // Resolve family id for the target person
    $p = $pdo->prepare('SELECT id, family_id FROM persons WHERE id = ? LIMIT 1');
    $p->execute([$personId]);
    $pr = $p->fetch();
    if (!$pr) { echo json_encode(['ok'=>false,'error'=>'person_not_found']); exit; }
    $familyId = (int)$pr['family_id'];

    // Verify user belongs to same family
    $u = $pdo->prepare('SELECT families_id FROM users WHERE id = ? LIMIT 1');
    $u->execute([ (int)$_SESSION['user_id'] ]);
    $ur = $u->fetch();
    $familyIdUser = (int)($ur['families_id'] ?? 0);
    if ($familyIdUser && $familyIdUser !== $familyId) {
        echo json_encode(['ok'=>false,'error'=>'forbidden_family']);
        exit;
    }

    $pdo->beginTransaction();

    $fatherId = null; $motherId = null;

    if ($fatherName !== '') {
        $isAlive = ($fatherStatus === 'deceased') ? 0 : 1;
        $selF = $pdo->prepare('SELECT id FROM persons WHERE family_id=? AND full_name=? AND gender="male" LIMIT 1');
        $selF->execute([$familyId, $fatherName]);
        $rowF = $selF->fetch();
        if ($rowF) {
            $fatherId = (int)$rowF['id'];
            $upd = $pdo->prepare('UPDATE persons SET is_alive=?, birth_date=?, death_date=?, updated_at=NOW() WHERE id=?');
            $upd->execute([$isAlive, ($fatherBirth ?: null), ($fatherDeath ?: null), $fatherId]);
        } else {
            $ins = $pdo->prepare('INSERT INTO persons (family_id, full_name, gender, is_alive, birth_date, death_date, created_by) VALUES (?,?,?,?,?,?,?)');
            $ins->execute([$familyId, $fatherName, 'male', $isAlive, ($fatherBirth ?: null), ($fatherDeath ?: null), (int)$_SESSION['user_id']]);
            $fatherId = (int)$pdo->lastInsertId();
        }
        $rel = $pdo->prepare('INSERT INTO relationships (family_id, parent_id, child_id, relationship_type) VALUES (?,?,?,?)
                              ON DUPLICATE KEY UPDATE relationship_type=VALUES(relationship_type)');
        $rel->execute([$familyId, $fatherId, $personId, $fatherRelType]);
    }

    if ($motherName !== '') {
        $isAlive = ($motherStatus === 'deceased') ? 0 : 1;
        $selM = $pdo->prepare('SELECT id FROM persons WHERE family_id=? AND full_name=? AND gender="female" LIMIT 1');
        $selM->execute([$familyId, $motherName]);
        $rowM = $selM->fetch();
        if ($rowM) {
            $motherId = (int)$rowM['id'];
            $upd = $pdo->prepare('UPDATE persons SET is_alive=?, birth_date=?, death_date=?, updated_at=NOW() WHERE id=?');
            $upd->execute([$isAlive, ($motherBirth ?: null), ($motherDeath ?: null), $motherId]);
        } else {
            $ins = $pdo->prepare('INSERT INTO persons (family_id, full_name, gender, is_alive, birth_date, death_date, created_by) VALUES (?,?,?,?,?,?,?)');
            $ins->execute([$familyId, $motherName, 'female', $isAlive, ($motherBirth ?: null), ($motherDeath ?: null), (int)$_SESSION['user_id']]);
            $motherId = (int)$pdo->lastInsertId();
        }
        $rel = $pdo->prepare('INSERT INTO relationships (family_id, parent_id, child_id, relationship_type) VALUES (?,?,?,?)
                              ON DUPLICATE KEY UPDATE relationship_type=VALUES(relationship_type)');
        $rel->execute([$familyId, $motherId, $personId, $motherRelType]);
    }

    if ($fatherId && $motherId) {
        $p1 = min($fatherId, $motherId);
        $p2 = max($fatherId, $motherId);
        $sqlU = 'INSERT INTO unions (family_id, person1_id, person2_id, union_type, is_current)
                 VALUES (?,?,?,?, NULL)
                 ON DUPLICATE KEY UPDATE union_type = VALUES(union_type)';
        $stU = $pdo->prepare($sqlU);
        $stU->execute([$familyId, $p1, $p2, 'marriage']);
    }

    $pdo->commit();
    echo json_encode(['ok'=>true,'family_id'=>$familyId,'person_id'=>$personId,'father_id'=>$fatherId,'mother_id'=>$motherId]);
} catch (Throwable $e) {
    if (!empty($pdo) && $pdo->inTransaction()) { $pdo->rollBack(); }
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'server_error']);
}
