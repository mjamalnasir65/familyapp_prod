<?php
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['ok'=>false,'error'=>'method_not_allowed']);
    exit;
}

if (empty($_SESSION['user_id']) || empty($_SESSION['user_email'])) {
    http_response_code(401);
    echo json_encode(['ok'=>false,'error'=>'unauthorized']);
    exit;
}

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

    $selfSel = $pdo->prepare('SELECT id FROM persons WHERE family_id = ? AND email = ? LIMIT 1');
    $selfSel->execute([$familyId, $_SESSION['user_email']]);
    $self = $selfSel->fetch();
    if (!$self) { echo json_encode(['ok'=>false,'error'=>'self_missing']); exit; }
    $selfId = (int)$self['id'];

    // Get latest male parent
    $father = null; $mother = null;
    $sql = 'SELECT r.relationship_type, p.id, p.full_name, p.gender, p.is_alive, p.birth_date, p.death_date
            FROM relationships r
            JOIN persons p ON p.id = r.parent_id
            WHERE r.child_id = ? AND r.family_id = ? AND p.gender = ?
            ORDER BY r.id DESC LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$selfId, $familyId, 'male']);
    if ($row = $stmt->fetch()) {
        $father = [
            'id' => (int)$row['id'],
            'name' => $row['full_name'],
            'status' => ((int)$row['is_alive']===1 ? 'living' : 'deceased'),
            'birth_date' => $row['birth_date'],
            'death_date' => $row['death_date'],
            'rel_type' => $row['relationship_type'] ?: 'biological'
        ];
    }
    $stmt->execute([$selfId, $familyId, 'female']);
    if ($row = $stmt->fetch()) {
        $mother = [
            'id' => (int)$row['id'],
            'name' => $row['full_name'],
            'status' => ((int)$row['is_alive']===1 ? 'living' : 'deceased'),
            'birth_date' => $row['birth_date'],
            'death_date' => $row['death_date'],
            'rel_type' => $row['relationship_type'] ?: 'biological'
        ];
    }

    echo json_encode(['ok'=>true, 'father'=>$father, 'mother'=>$mother]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'server_error']);
}
