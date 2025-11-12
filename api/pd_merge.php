<?php
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['user_id'])) { echo json_encode(['ok'=>false,'error'=>'auth']); exit; }

$in = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$pdId = (int)($in['id'] ?? 0);
if (!$pdId) { echo json_encode(['ok'=>false,'error'=>'missing_id']); exit; }

try {
    $pdo = Database::getInstance()->getConnection();
    $uid = (int)$_SESSION['user_id'];

    // Resolve family
    $st = $pdo->prepare('SELECT families_id FROM users WHERE id = ? LIMIT 1');
    $st->execute([$uid]);
    $familyId = (int)($st->fetchColumn() ?: 0);
    if (!$familyId) { echo json_encode(['ok'=>false,'error'=>'no_family']); exit; }

    // Load PD row with joined persons
    $sel = $pdo->prepare('SELECT pd.id, pd.person_a_id, pd.person_b_id, pd.status,
                                 pa.family_id AS a_family, pb.family_id AS b_family
                          FROM possible_duplicates pd
                          JOIN persons pa ON pa.id = pd.person_a_id
                          JOIN persons pb ON pb.id = pd.person_b_id
                          WHERE pd.id = ? LIMIT 1');
    $sel->execute([$pdId]);
    $pd = $sel->fetch(PDO::FETCH_ASSOC);
    if (!$pd) { echo json_encode(['ok'=>false,'error'=>'not_found']); exit; }

    if ($pd['status'] !== 'reviewed') { echo json_encode(['ok'=>false,'error'=>'not_actionable']); exit; }
    $aFam = (int)$pd['a_family']; $bFam = (int)$pd['b_family'];
    if ($aFam !== $familyId && $bFam !== $familyId) { http_response_code(403); echo json_encode(['ok'=>false,'error'=>'forbidden']); exit; }

    $a = (int)$pd['person_a_id'];
    $b = (int)$pd['person_b_id'];
    $lo = min($a,$b); $hi = max($a,$b);

    $pdo->beginTransaction();

    // Require double-consent: both u1 and u2 clicks must be 'Y'
    $clicks = $pdo->prepare('SELECT u1_merged_click, u2_merged_click FROM possible_duplicates WHERE id = ? LIMIT 1');
    $clicks->execute([$pdId]);
    $cf = $clicks->fetch(PDO::FETCH_ASSOC) ?: [];
    if (($cf['u1_merged_click'] ?? 'N') !== 'Y' || ($cf['u2_merged_click'] ?? 'N') !== 'Y') {
        $pdo->rollBack();
        echo json_encode(['ok'=>false,'error'=>'consent_required']);
        return;
    }

    // Upsert person_links
    // There's no unique constraint, so ensure idempotency via existence check
    $exists = $pdo->prepare('SELECT id FROM person_links WHERE person_id_a = ? AND person_id_b = ? LIMIT 1');
    $exists->execute([$lo,$hi]);
    if (!$exists->fetchColumn()) {
        $ins = $pdo->prepare('INSERT INTO person_links (person_id_a, person_id_b, verified_by_account_id, verified_at, verified_status)
                               VALUES (?,?,?,?,"confirmed")');
        $ins->execute([$lo,$hi,$uid, date('Y-m-d H:i:s')]);
    }

    // Mark PD as merged
    $upd = $pdo->prepare('UPDATE possible_duplicates SET status = "merged" WHERE id = ?');
    $upd->execute([$pdId]);

    $pdo->commit();
    echo json_encode(['ok'=>true,'link'=>['a'=>$lo,'b'=>$hi]]);
} catch (Throwable $e) {
    if ($pdo && $pdo->inTransaction()) { $pdo->rollBack(); }
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'server_error']);
}

?>
