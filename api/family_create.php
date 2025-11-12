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

$name = trim($_POST['name'] ?? '');
$description = trim($_POST['description'] ?? '');

if ($name === '') {
    echo json_encode(['ok'=>false,'error'=>'name_required']);
    exit;
}

try {
    $pdo = Database::getInstance()->getConnection();
    $pdo->beginTransaction();

    // Enforce uniqueness by name
    $check = $pdo->prepare('SELECT id FROM families WHERE name = ? LIMIT 1');
    $check->execute([$name]);
    if ($check->fetch()) {
        $pdo->rollBack();
        echo json_encode(['ok'=>false,'error'=>'name_exists']);
        exit;
    }

    // Insert family
    $ins = $pdo->prepare('INSERT INTO families (name, description, creator_id, created_by_email, step_1_completed) VALUES (?, ?, ?, ?, "Y")');
    $ins->execute([$name, $description, (int)$_SESSION['user_id'], $_SESSION['user_email']]);
    $familyId = (int)$pdo->lastInsertId();

    // Link user to family
    $up = $pdo->prepare('UPDATE users SET families_id = ? WHERE id = ? AND email = ?');
    $up->execute([$familyId, (int)$_SESSION['user_id'], $_SESSION['user_email']]);

    // Also add family_members row (creator)
    $fm = $pdo->prepare('INSERT IGNORE INTO family_members (family_id, user_id, role, can_edit, can_add, can_delete, can_manage_files, status) VALUES (?, ?, "creator", 1, 1, 1, 1, "active")');
    $fm->execute([$familyId, (int)$_SESSION['user_id']]);

    $pdo->commit();

    echo json_encode(['ok'=>true,'family_id'=>$familyId]);
} catch (Throwable $e) {
    if ($pdo && $pdo->inTransaction()) { $pdo->rollBack(); }
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'server_error']);
}
