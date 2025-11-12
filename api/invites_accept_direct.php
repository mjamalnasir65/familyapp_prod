<?php
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['user_id'])) { echo json_encode(['ok'=>false,'error'=>'auth']); exit; }

$in = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$inviteId = (int)($in['id'] ?? 0);
if (!$inviteId) { echo json_encode(['ok'=>false,'error'=>'missing_id']); exit; }

try {
    $pdo = Database::getInstance()->getConnection();
    $uid = (int)$_SESSION['user_id'];

    // Load user email for validation
    $u = $pdo->prepare('SELECT email FROM users WHERE id = ? LIMIT 1');
    $u->execute([$uid]);
    $email = strtolower(trim((string)($u->fetchColumn() ?: '')));
    if (!$email) { echo json_encode(['ok'=>false,'error'=>'no_email']); exit; }

    // Load invite and ensure it targets this user email and is pending
    $sel = $pdo->prepare('SELECT * FROM invites WHERE id = ? AND status = "pending" LIMIT 1');
    $sel->execute([$inviteId]);
    $inv = $sel->fetch(PDO::FETCH_ASSOC);
    if (!$inv) { echo json_encode(['ok'=>false,'error'=>'not_found_or_not_pending']); exit; }
    if (strtolower((string)$inv['email']) !== $email) { http_response_code(403); echo json_encode(['ok'=>false,'error'=>'forbidden_email']); exit; }
    if (!empty($inv['token_expires_at']) && (new DateTime() > new DateTime($inv['token_expires_at']))) { echo json_encode(['ok'=>false,'error'=>'expired']); exit; }

    $pdo->beginTransaction();
    // Activate membership
    $up = $pdo->prepare('INSERT INTO family_members (family_id,user_id,role,can_edit,can_add,can_delete,can_manage_files,status,joined_at)
                         VALUES (?,?,?,?,?,?,?,"active",NOW())
                         ON DUPLICATE KEY UPDATE role=VALUES(role), can_edit=VALUES(can_edit), can_add=VALUES(can_add), can_delete=VALUES(can_delete), can_manage_files=VALUES(can_manage_files), status="active"');
    $up->execute([$inv['families_id'],$uid,$inv['role'],$inv['can_edit'],$inv['can_add'],$inv['can_delete'],$inv['can_manage_files']]);

    // Optional couple/person scoped grants (best-effort)
    if ($inv['scope_type'] === 'couple' && $inv['parent1_id'] && $inv['parent2_id']) {
        try {
            $pdo->query('SELECT 1 FROM tree_admin_couples LIMIT 0');
            $ta = $pdo->prepare('INSERT INTO tree_admin_couples (family_id,parent1_id,parent2_id,user_id,role,can_edit,can_add,can_delete)
                                 VALUES (?,?,?,? ,"branch_editor",?,?,?)
                                 ON DUPLICATE KEY UPDATE role="branch_editor", can_edit=VALUES(can_edit), can_add=VALUES(can_add), can_delete=VALUES(can_delete)');
            $ta->execute([$inv['families_id'],$inv['parent1_id'],$inv['parent2_id'],$uid,$inv['can_edit'],$inv['can_add'],$inv['can_delete']]);
        } catch (Throwable $_) { /* ignore if table is missing */ }
    }

    $mark = $pdo->prepare('UPDATE invites SET status = "accepted", accepted_at = NOW(), accepted_by = ? WHERE id = ?');
    $mark->execute([$uid,$inviteId]);

    $pdo->commit();
    // Promote related PDs from 'pending' -> 'reviewed' for this invite's email
    try {
        $familiesId = (int)$inv['families_id'];
        $emailLower = strtolower((string)$inv['email']);
        $lp = $pdo->prepare('SELECT id FROM persons WHERE family_id = ? AND LOWER(email) = ?');
        $lp->execute([$familiesId, $emailLower]);
        $localIds = array_map(fn($r) => (int)$r['id'], $lp->fetchAll(PDO::FETCH_ASSOC));
        if (!empty($localIds)) {
            $rp = $pdo->prepare('SELECT id FROM persons WHERE family_id <> ? AND LOWER(email) = ?');
            $rp->execute([$familiesId, $emailLower]);
            $remoteIds = array_map(fn($r) => (int)$r['id'], $rp->fetchAll(PDO::FETCH_ASSOC));
            if (!empty($remoteIds)) {
                $upd = $pdo->prepare('UPDATE possible_duplicates SET status = "reviewed"
                    WHERE status = "pending" AND ((person_a_id = ? AND person_b_id = ?) OR (person_a_id = ? AND person_b_id = ?))');
                foreach ($localIds as $la) {
                    foreach ($remoteIds as $rb) {
                        $a = min($la, $rb); $b = max($la, $rb);
                        $upd->execute([$a,$b,$b,$a]);
                    }
                }
            }
        }
    } catch (Throwable $_pdE) { /* ignore */ }

    echo json_encode(['ok'=>true,'family_id'=>(int)$inv['families_id']]);
} catch (Throwable $e) {
    if ($pdo && $pdo->inTransaction()) { $pdo->rollBack(); }
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'server_error']);
}

?>
