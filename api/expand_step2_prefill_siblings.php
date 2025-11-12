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

    // Load person & family
    $ps = $pdo->prepare('SELECT id, family_id, full_name FROM persons WHERE id = ? LIMIT 1');
    $ps->execute([$personId]);
    $pRow = $ps->fetch();
    if (!$pRow) { echo json_encode(['ok'=>false,'error'=>'person_not_found']); exit; }
    $familyId = (int)$pRow['family_id'];
    $personName = (string)$pRow['full_name'];

    // Verify user belongs to family
    $u = $pdo->prepare('SELECT families_id FROM users WHERE id = ? LIMIT 1');
    $u->execute([ (int)$_SESSION['user_id'] ]);
    $ur = $u->fetch();
    $familyIdUser = (int)($ur['families_id'] ?? 0);
    if ($familyIdUser && $familyIdUser !== $familyId) {
        echo json_encode(['ok'=>false,'error'=>'forbidden_family']);
        exit;
    }

    // Get parents for the person
    $rel = $pdo->prepare('SELECT r.parent_id, p.gender FROM relationships r JOIN persons p ON p.id = r.parent_id WHERE r.family_id = ? AND r.child_id = ?');
    $rel->execute([$familyId, $personId]);
    $parents = $rel->fetchAll();
    $fatherId = null; $motherId = null; $parentIds = [];
    foreach ($parents as $row) {
        $pid = (int)$row['parent_id'];
        $parentIds[] = $pid;
        $g = strtolower((string)$row['gender']);
        if ($g === 'male') $fatherId = $pid;
        if ($g === 'female') $motherId = $pid;
    }

    // Find siblings: children of any of the parentIds (excluding the personId)
    $siblings = [];
    if (!empty($parentIds)) {
        $in = implode(',', array_fill(0, count($parentIds), '?'));
        $sql = "SELECT DISTINCT c.id, c.full_name, c.gender, c.is_alive, c.birth_date, c.death_date
                FROM relationships r
                JOIN persons c ON c.id = r.child_id
                WHERE r.family_id = ? AND r.parent_id IN ($in) AND r.child_id <> ?
                ORDER BY c.full_name";
        $params = array_merge([$familyId], $parentIds, [$personId]);
        $st = $pdo->prepare($sql);
        $st->execute($params);
        while ($r = $st->fetch()) {
            $siblings[] = [
                'id' => (int)$r['id'],
                'name' => $r['full_name'],
                'gender' => $r['gender'],
                'status' => ((int)$r['is_alive']===1 ? 'living' : 'deceased'),
                'birth_date' => $r['birth_date'],
                'death_date' => $r['death_date']
            ];
        }
    }

    echo json_encode([
        'ok'=>true,
        'family_id'=>$familyId,
        'person_id'=>$personId,
        'person_name'=>$personName,
        'parents'=>[ 'father_id'=>$fatherId, 'mother_id'=>$motherId, 'count'=>count($parentIds) ],
        'siblings'=>$siblings
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'server_error']);
}
