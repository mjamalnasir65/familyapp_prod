<?php
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();

$token = $_GET['token'] ?? ($_POST['token'] ?? '');
if (!$token) { echo json_encode(['ok'=>false,'error'=>'token_required']); exit; }
$hash = hash('sha256', $token);

try {
    $pdo = Database::getInstance()->getConnection();
    $sel = $pdo->prepare('SELECT * FROM invites WHERE token_hash = ? AND status = "pending" LIMIT 1');
    $sel->execute([$hash]);
    $inv = $sel->fetch();
    if (!$inv) { echo json_encode(['ok'=>false,'error'=>'invalid_or_used']); exit; }
    if (new DateTime() > new DateTime($inv['token_expires_at'])) { echo json_encode(['ok'=>false,'error'=>'expired']); exit; }

    if (empty($_SESSION['user_id'])) { echo json_encode(['ok'=>false,'require_login'=>true]); exit; }
    $uid = (int)$_SESSION['user_id'];

    $pdo->beginTransaction();
    // Upsert family membership
    $up = $pdo->prepare('INSERT INTO family_members (family_id,user_id,role,can_edit,can_add,can_delete,can_manage_files,status,joined_at)
                         VALUES (?,?,?,?,?,?,?,"active",NOW())
                         ON DUPLICATE KEY UPDATE role=VALUES(role), can_edit=VALUES(can_edit), can_add=VALUES(can_add), can_delete=VALUES(can_delete), can_manage_files=VALUES(can_manage_files), status="active"');
    $up->execute([$inv['families_id'],$uid,$inv['role'],$inv['can_edit'],$inv['can_add'],$inv['can_delete'],$inv['can_manage_files']]);

    // Couple/person scoped grants (optional table; guard for existence later if needed)
    if ($inv['scope_type'] === 'couple' && $inv['parent1_id'] && $inv['parent2_id']) {
        // If tree_admin_couples exists, grant there; otherwise skip silently
        try {
            $pdo->query('SELECT 1 FROM tree_admin_couples LIMIT 0');
            $ta = $pdo->prepare('INSERT INTO tree_admin_couples (family_id,parent1_id,parent2_id,user_id,role,can_edit,can_add,can_delete)
                                 VALUES (?,?,?,?,"branch_editor",?,?,?)
                                 ON DUPLICATE KEY UPDATE role="branch_editor", can_edit=VALUES(can_edit), can_add=VALUES(can_add), can_delete=VALUES(can_delete)');
            $ta->execute([$inv['families_id'],$inv['parent1_id'],$inv['parent2_id'],$uid,$inv['can_edit'],$inv['can_add'],$inv['can_delete']]);
        } catch (Throwable $_) { /* table not present, ignore */ }
    }

    $mark = $pdo->prepare('UPDATE invites SET status = "accepted", accepted_at = NOW(), accepted_by = ? WHERE id = ?');
    $mark->execute([$uid,$inv['id']]);

    $pdo->commit();
    // Promote related PDs from 'pending' -> 'reviewed' for this email
    try {
        $familiesId = (int)$inv['families_id'];
        $emailLower = strtolower((string)$inv['email']);
        // Local persons (this family) with this email
        $lp = $pdo->prepare('SELECT id FROM persons WHERE family_id = ? AND LOWER(email) = ?');
        $lp->execute([$familiesId, $emailLower]);
        $localIds = array_map(fn($r) => (int)$r['id'], $lp->fetchAll(PDO::FETCH_ASSOC));
        if (!empty($localIds)) {
            // Remote persons (other families) with same email
            $rp = $pdo->prepare('SELECT id FROM persons WHERE family_id <> ? AND LOWER(email) = ?');
            $rp->execute([$familiesId, $emailLower]);
            $remoteIds = array_map(fn($r) => (int)$r['id'], $rp->fetchAll(PDO::FETCH_ASSOC));
            if (!empty($remoteIds)) {
                // Build OR predicates in chunks to avoid overly long IN lists
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
    } catch (Throwable $_pdE) { /* ignore if PD table absent */ }

    echo json_encode(['ok'=>true,'family_id'=>(int)$inv['families_id']]);
} catch (Throwable $e) {
    if ($pdo && $pdo->inTransaction()) { $pdo->rollBack(); }
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'server_error']);
}

?>
