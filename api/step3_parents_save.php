<?php
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok'=>false,'error'=>'method_not_allowed']);
    exit;
}

if (empty($_SESSION['user_id']) || empty($_SESSION['user_email'])) {
    http_response_code(401);
    echo json_encode(['ok'=>false,'error'=>'unauthorized']);
    exit;
}

// Inputs
$fatherName = trim($_POST['father_name'] ?? '');
$fatherStatus = trim($_POST['father_status'] ?? ''); // ''|living|deceased (UI: living optional, deceased explicit)
$fatherBirth = trim($_POST['father_birth_date'] ?? '');
$fatherDeath = trim($_POST['father_death_date'] ?? '');
$fatherRelType = trim($_POST['father_rel_type'] ?? 'biological'); // restrict to biological|step

$motherName = trim($_POST['mother_name'] ?? '');
$motherStatus = trim($_POST['mother_status'] ?? '');
$motherBirth = trim($_POST['mother_birth_date'] ?? '');
$motherDeath = trim($_POST['mother_death_date'] ?? '');
$motherRelType = trim($_POST['mother_rel_type'] ?? 'biological');

// Require BOTH parents now
if ($fatherName === '' || $motherName === '') {
    echo json_encode(['ok'=>false,'error'=>'both_parents_required']);
    exit;
}

// Normalize relationship types
$allowedRel = ['biological','step'];
if (!in_array($fatherRelType, $allowedRel, true)) $fatherRelType = 'biological';
if (!in_array($motherRelType, $allowedRel, true)) $motherRelType = 'biological';

try {
    $pdo = Database::getInstance()->getConnection();
    // Resolve family and self person id
    $familyId = (int)($_SESSION['families_id'] ?? ($_SESSION['family_id'] ?? 0));
    if (!$familyId) {
        $st = $pdo->prepare('SELECT families_id FROM users WHERE id = ? LIMIT 1');
        $st->execute([ (int)$_SESSION['user_id'] ]);
        $row = $st->fetch();
        $familyId = (int)($row['families_id'] ?? 0);
    }
    if (!$familyId) { echo json_encode(['ok'=>false,'error'=>'no_family']); exit; }

    // Find the self person row by family + email
    $selfSel = $pdo->prepare('SELECT id FROM persons WHERE family_id = ? AND email = ? LIMIT 1');
    $selfSel->execute([$familyId, $_SESSION['user_email']]);
    $self = $selfSel->fetch();
    if (!$self) { echo json_encode(['ok'=>false,'error'=>'self_missing']); exit; }
    $selfId = (int)$self['id'];

    $pdo->beginTransaction();

    $fatherId = null; $motherId = null;

    if ($fatherName !== '') {
        $isAlive = ($fatherStatus === 'deceased') ? 0 : 1;
        // Upsert by (family_id, full_name, gender='male')
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
        // Relationship: parent->child (idempotent)
        $rel = $pdo->prepare('INSERT INTO relationships (family_id, parent_id, child_id, relationship_type) VALUES (?,?,?,?)
                              ON DUPLICATE KEY UPDATE relationship_type=VALUES(relationship_type)');
        $rel->execute([$familyId, $fatherId, $selfId, $fatherRelType]);
    }

    if ($motherName !== '') {
        $isAlive = ($motherStatus === 'deceased') ? 0 : 1;
        // Upsert by (family_id, full_name, gender='female')
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
        // Relationship: parent->child (idempotent)
        $rel = $pdo->prepare('INSERT INTO relationships (family_id, parent_id, child_id, relationship_type) VALUES (?,?,?,?)
                              ON DUPLICATE KEY UPDATE relationship_type=VALUES(relationship_type)');
        $rel->execute([$familyId, $motherId, $selfId, $motherRelType]);
    }

    // After both parent relationships
    // Upsert a union for the parents when both exist (idempotent)
    if ($fatherId && $motherId) {
        $p1 = min($fatherId, $motherId);
        $p2 = max($fatherId, $motherId);
        $sqlU = 'INSERT INTO unions (family_id, person1_id, person2_id, union_type, is_current)
                 VALUES (?,?,?,?, NULL)
                 ON DUPLICATE KEY UPDATE union_type = VALUES(union_type)';
        $stU = $pdo->prepare($sqlU);
        $stU->execute([$familyId, $p1, $p2, 'marriage']);
    }

    // Mark step 3 completed
    $fs = $pdo->prepare("UPDATE families SET step_3_completed='Y', updated_at=NOW() WHERE id=?");
    $fs->execute([$familyId]);

    $pdo->commit();
    echo json_encode(['ok'=>true,'family_id'=>$familyId,'father_id'=>$fatherId,'mother_id'=>$motherId]);
} catch (Throwable $e) {
    if (!empty($pdo) && $pdo->inTransaction()) { $pdo->rollBack(); }
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'server_error']);
}
