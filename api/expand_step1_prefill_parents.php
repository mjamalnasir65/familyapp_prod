<?php
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['ok'=>false,'error'=>'method_not_allowed']);
    exit;
}

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok'=>false,'error'=>'unauthorized']);
    exit;
}

$personId = (int)($_GET['person_id'] ?? ($_SESSION['expand']['person_id'] ?? 0));
if (!$personId) { echo json_encode(['ok'=>false,'error'=>'missing_person']); exit; }

try {
    $pdo = Database::getInstance()->getConnection();

    // Load person and family
    $p = $pdo->prepare('SELECT id, family_id FROM persons WHERE id = ? LIMIT 1');
    $p->execute([$personId]);
    $pr = $p->fetch();
    if (!$pr) { echo json_encode(['ok'=>false,'error'=>'person_not_found']); exit; }
    $familyId = (int)$pr['family_id'];

    // Verify user belongs to the same family (if users table links family)
    $u = $pdo->prepare('SELECT families_id FROM users WHERE id = ? LIMIT 1');
    $u->execute([ (int)$_SESSION['user_id'] ]);
    $ur = $u->fetch();
    $familyIdUser = (int)($ur['families_id'] ?? 0);
    if ($familyIdUser && $familyIdUser !== $familyId) {
        echo json_encode(['ok'=>false,'error'=>'forbidden_family']);
        exit;
    }

    // Fetch current father and mother (latest per gender)
    $father = null; $mother = null;
    $sql = 'SELECT r.relationship_type, p.id, p.full_name, p.gender, p.is_alive, p.birth_date, p.death_date
            FROM relationships r
            JOIN persons p ON p.id = r.parent_id
            WHERE r.child_id = ? AND r.family_id = ? AND p.gender = ?
            ORDER BY r.id DESC LIMIT 1';
    $st = $pdo->prepare($sql);
    $st->execute([$personId, $familyId, 'male']);
    if ($row = $st->fetch()) {
        $father = [
            'id' => (int)$row['id'],
            'name' => $row['full_name'],
            'status' => ((int)$row['is_alive']===1 ? 'living' : 'deceased'),
            'birth_date' => $row['birth_date'],
            'death_date' => $row['death_date'],
            'rel_type' => $row['relationship_type'] ?: 'biological'
        ];
    }
    $st->execute([$personId, $familyId, 'female']);
    if ($row = $st->fetch()) {
        $mother = [
            'id' => (int)$row['id'],
            'name' => $row['full_name'],
            'status' => ((int)$row['is_alive']===1 ? 'living' : 'deceased'),
            'birth_date' => $row['birth_date'],
            'death_date' => $row['death_date'],
            'rel_type' => $row['relationship_type'] ?: 'biological'
        ];
    }

    echo json_encode(['ok'=>true,'family_id'=>$familyId,'person_id'=>$personId,'father'=>$father,'mother'=>$mother]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'server_error']);
}
