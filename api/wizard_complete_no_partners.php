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

try {
    $pdo = Database::getInstance()->getConnection();
    $familyId = (int)($_SESSION['families_id'] ?? ($_SESSION['family_id'] ?? 0));
    if (!$familyId) {
        $st = $pdo->prepare('SELECT families_id FROM users WHERE id = ? LIMIT 1');
        $st->execute([ (int)$_SESSION['user_id'] ]);
        $row = $st->fetch();
        $familyId = (int)($row['families_id'] ?? 0);
    }
    if (!$familyId) { echo json_encode(['ok'=>false,'error'=>'no_family']); exit; }

    $upd = $pdo->prepare("UPDATE families SET step_5_completed='Y', step_6_completed='Y', wizard_completed_at=NOW(), updated_at=NOW() WHERE id=?");
    $upd->execute([$familyId]);

    echo json_encode(['ok'=>true]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'server_error']);
}
?>