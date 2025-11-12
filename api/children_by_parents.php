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

$p1 = (int)($_GET['person_id'] ?? $_GET['parent1_id'] ?? 0);
$p2 = (int)($_GET['partner_id'] ?? $_GET['parent2_id'] ?? 0);
if (!$p1 || !$p2) { echo json_encode(['ok'=>false,'error'=>'missing_parents']); exit; }

try {
    $pdo = Database::getInstance()->getConnection();

    // Ensure both parents exist and belong to same family
    $ps = $pdo->prepare('SELECT id, family_id, full_name FROM persons WHERE id IN (?, ?) ORDER BY id');
    $ps->execute([$p1, $p2]);
    $rows = $ps->fetchAll();
    if (count($rows) !== 2) { echo json_encode(['ok'=>false,'error'=>'parent_not_found']); exit; }
    $familyId = (int)$rows[0]['family_id'];
    if ($familyId !== (int)$rows[1]['family_id']) { echo json_encode(['ok'=>false,'error'=>'parents_different_families']); exit; }

    // Verify user belongs to same family (if users.families_id is set)
    $u = $pdo->prepare('SELECT families_id FROM users WHERE id = ? LIMIT 1');
    $u->execute([ (int)$_SESSION['user_id'] ]);
    $ur = $u->fetch();
    $familyIdUser = (int)($ur['families_id'] ?? 0);
    if ($familyIdUser && $familyIdUser !== $familyId) { echo json_encode(['ok'=>false,'error'=>'forbidden_family']); exit; }

    // Children are persons who have BOTH r(parent1->child) and r(parent2->child)
    $sql = 'SELECT c.id, c.full_name, c.gender, c.is_alive, c.birth_date, c.death_date
            FROM relationships r1
            JOIN relationships r2 ON r2.family_id = r1.family_id AND r2.child_id = r1.child_id
            JOIN persons c ON c.id = r1.child_id
            WHERE r1.family_id = ? AND r1.parent_id = ? AND r2.parent_id = ?
            GROUP BY c.id
            ORDER BY COALESCE(c.birth_date, "9999-12-31"), c.full_name';
    $st = $pdo->prepare($sql);
    $st->execute([$familyId, $p1, $p2]);
    $children = [];
    while ($r = $st->fetch()) {
        $children[] = [
            'id' => (int)$r['id'],
            'name' => $r['full_name'],
            'gender' => $r['gender'],
            'status' => ((int)$r['is_alive']===1 ? 'living' : 'deceased'),
            'birth_date' => $r['birth_date'],
            'death_date' => $r['death_date']
        ];
    }

    echo json_encode([
        'ok'=>true,
        'family_id'=>$familyId,
        'parent1_id'=>$p1,
        'parent2_id'=>$p2,
        'parent1_name'=>$rows[0]['id']==$p1 ? $rows[0]['full_name'] : $rows[1]['full_name'],
        'parent2_name'=>$rows[1]['id']==$p2 ? $rows[1]['full_name'] : $rows[0]['full_name'],
        'children'=>$children
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'server_error']);
}

?>