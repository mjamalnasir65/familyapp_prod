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

$in = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$name = trim($in['name'] ?? '');
$description = trim($in['description'] ?? '');
$expected = isset($in['expected_children']) ? max(0, (int)$in['expected_children']) : 0;
$p1name = trim($in['parent1_name'] ?? '');
$p2name = trim($in['parent2_name'] ?? '');
$p1gender = strtolower(trim($in['parent1_gender'] ?? 'male'));
$p2gender = strtolower(trim($in['parent2_gender'] ?? 'female'));
$allowedGender = ['male','female','other','prefer_not_to_say'];
if (!in_array($p1gender, $allowedGender, true)) $p1gender = 'male';
if (!in_array($p2gender, $allowedGender, true)) $p2gender = 'female';

if ($name === '' || $p1name === '' || $p2name === '') {
    echo json_encode(['ok'=>false,'error'=>'required_fields']);
    exit;
}

try {
    $pdo = Database::getInstance()->getConnection();
    $pdo->beginTransaction();

    // Uniqueness by family name
    $check = $pdo->prepare('SELECT id FROM families WHERE name = ? LIMIT 1');
    $check->execute([$name]);
    if ($check->fetch()) {
        $pdo->rollBack();
        echo json_encode(['ok'=>false,'error'=>'name_exists']);
        exit;
    }

    // Insert family
    $insF = $pdo->prepare('INSERT INTO families (name, description, creator_id, created_by_email, step_1_completed) VALUES (?, ?, ?, ?, "Y")');
    $insF->execute([$name, $description, (int)$_SESSION['user_id'], $_SESSION['user_email']]);
    $familyId = (int)$pdo->lastInsertId();

    // Link user to family
    $up = $pdo->prepare('UPDATE users SET families_id = ? WHERE id = ? AND email = ?');
    $up->execute([$familyId, (int)$_SESSION['user_id'], $_SESSION['user_email']]);

    // Add creator as family_member
    $fm = $pdo->prepare('INSERT IGNORE INTO family_members (family_id, user_id, role, can_edit, can_add, can_delete, can_manage_files, status) VALUES (?, ?, "creator", 1, 1, 1, 1, "active")');
    $fm->execute([$familyId, (int)$_SESSION['user_id']]);

    // Create parents
    $pi = $pdo->prepare('INSERT INTO persons (family_id, full_name, gender, is_alive, created_by) VALUES (?,?,?,?,?)');
    $pi->execute([$familyId, $p1name, $p1gender, 1, (int)$_SESSION['user_id']]);
    $p1Id = (int)$pdo->lastInsertId();
    $pi->execute([$familyId, $p2name, $p2gender, 1, (int)$_SESSION['user_id']]);
    $p2Id = (int)$pdo->lastInsertId();

    // Create union (marriage/current)
    $ui = $pdo->prepare('INSERT INTO unions (family_id, person1_id, person2_id, union_type, is_current) VALUES (?,?,?,?,1)');
    $ui->execute([$familyId, $p1Id, $p2Id, 'marriage']);
    $unionId = (int)$pdo->lastInsertId();

    // Set expected children and couple for token scope
    $uf = $pdo->prepare('UPDATE families SET expected_children=?, children_added=0, family_token_union_id=? WHERE id=?');
    $uf->execute([$expected, $unionId, $familyId]);

    $pdo->commit();

    echo json_encode(['ok'=>true,'family_id'=>$familyId,'union_id'=>$unionId]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) { $pdo->rollBack(); }
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'server_error']);
}
