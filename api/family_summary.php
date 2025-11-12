<?php
require_once __DIR__ . '/_init.php';

header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
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

    $q = $pdo->prepare('SELECT total_members, living_members, deceased_members, total_relationships, total_unions, current_unions FROM family_statistics WHERE family_id = ? LIMIT 1');
    $q->execute([$familyId]);
    $sum = $q->fetch();
    echo json_encode(['ok'=>true,'summary'=>$sum ?: (object)[]]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'server_error']);
}
?>