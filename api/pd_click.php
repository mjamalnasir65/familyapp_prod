<?php
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['ok'=>false,'error'=>'method_not_allowed']); exit; }
if (empty($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['ok'=>false,'error'=>'unauthorized']); exit; }

$in = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$pdId = (int)($in['id'] ?? 0);
if (!$pdId) { echo json_encode(['ok'=>false,'error'=>'missing_id']); exit; }

try {
    $pdo = Database::getInstance()->getConnection();
    $uid = (int)$_SESSION['user_id'];

    // Resolve current family
    $st = $pdo->prepare('SELECT families_id FROM users WHERE id = ? LIMIT 1');
    $st->execute([$uid]);
    $familyId = (int)($st->fetchColumn() ?: 0);
    if (!$familyId) { echo json_encode(['ok'=>false,'error'=>'no_family']); exit; }

    // Load PD with person family ids
    $sel = $pdo->prepare('SELECT pd.id, pd.u1_merged_click, pd.u2_merged_click,
                                 pa.family_id AS a_family, pb.family_id AS b_family
                          FROM possible_duplicates pd
                          JOIN persons pa ON pa.id = pd.person_a_id
                          JOIN persons pb ON pb.id = pd.person_b_id
                          WHERE pd.id = ? LIMIT 1');
    $sel->execute([$pdId]);
    $pd = $sel->fetch(PDO::FETCH_ASSOC);
    if (!$pd) { echo json_encode(['ok'=>false,'error'=>'not_found']); exit; }

    $aFam = (int)$pd['a_family'];
    $bFam = (int)$pd['b_family'];
    if ($aFam !== $familyId && $bFam !== $familyId) { http_response_code(403); echo json_encode(['ok'=>false,'error'=>'forbidden']); exit; }

    // Determine which flag to set
    $col = ($aFam === $familyId) ? 'u1_merged_click' : 'u2_merged_click';

    // Idempotent set to 'Y'
    $upd = $pdo->prepare("UPDATE possible_duplicates SET $col = 'Y' WHERE id = ?");
    $upd->execute([$pdId]);

    // Return updated flags
    $ref = $pdo->prepare('SELECT u1_merged_click, u2_merged_click FROM possible_duplicates WHERE id = ?');
    $ref->execute([$pdId]);
    $flags = $ref->fetch(PDO::FETCH_ASSOC) ?: [];

    echo json_encode([
        'ok' => true,
        'u1' => ($flags['u1_merged_click'] ?? 'N') === 'Y',
        'u2' => ($flags['u2_merged_click'] ?? 'N') === 'Y'
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'server_error']);
}

?>
