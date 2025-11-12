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

$fullName  = trim($_POST['name'] ?? '');
$gender    = trim($_POST['gender'] ?? '');
$status    = trim($_POST['status'] ?? ''); // '' | 'deceased' (UI hides 'living')
$birthDate = trim($_POST['birth_date'] ?? '');

if ($fullName === '' || $gender === '') {
    echo json_encode(['ok'=>false,'error'=>'missing_fields']);
    exit;
}

// Normalize/validate
$allowedGender = ['male','female','other','prefer_not_to_say'];
if (!in_array($gender, $allowedGender, true)) {
    echo json_encode(['ok'=>false,'error'=>'invalid_gender']);
    exit;
}
$isAlive = ($status === 'deceased') ? 0 : 1; // default living when not specified

try {
    $pdo = Database::getInstance()->getConnection();
    // Resolve family id (from session first, fallback to users.families_id)
    $familyId = (int)($_SESSION['families_id'] ?? ($_SESSION['family_id'] ?? 0));
    if (!$familyId) {
        $st = $pdo->prepare('SELECT families_id FROM users WHERE id = ? LIMIT 1');
        $st->execute([ (int)$_SESSION['user_id'] ]);
        $row = $st->fetch();
        $familyId = (int)($row['families_id'] ?? 0);
    }
    if (!$familyId) {
        echo json_encode(['ok'=>false,'error'=>'no_family']);
        exit;
    }

    $pdo->beginTransaction();

    // Check if "self" already exists: we key by (family_id, email)
    $sel = $pdo->prepare('SELECT id FROM persons WHERE family_id = ? AND email = ? LIMIT 1');
    $sel->execute([$familyId, $_SESSION['user_email']]);
    $existing = $sel->fetch();

    if ($existing) {
        $pid = (int)$existing['id'];
        $upd = $pdo->prepare('UPDATE persons SET full_name=?, gender=?, is_alive=?, birth_date=?, updated_at=NOW() WHERE id=?');
        $upd->execute([$fullName, $gender, $isAlive, ($birthDate ?: null), $pid]);
        $personId = $pid;
    } else {
        $ins = $pdo->prepare('INSERT INTO persons (family_id, full_name, gender, is_alive, birth_date, email, created_by) VALUES (?,?,?,?,?,?,?)');
        $ins->execute([$familyId, $fullName, $gender, $isAlive, ($birthDate ?: null), $_SESSION['user_email'], (int)$_SESSION['user_id']]);
        $personId = (int)$pdo->lastInsertId();
    }

    // Mark families.step_2_completed = 'Y'
    $fs = $pdo->prepare("UPDATE families SET step_2_completed='Y', updated_at=NOW() WHERE id = ?");
    $fs->execute([$familyId]);

    $pdo->commit();
    echo json_encode(['ok'=>true,'person_id'=>$personId,'family_id'=>$familyId]);
} catch (Throwable $e) {
    if ($pdo && $pdo->inTransaction()) { $pdo->rollBack(); }
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'server_error']);
}
