<?php
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Mailer.php';
require_once __DIR__ . '/invite_url_helper.php';

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['user_id'])) { echo json_encode(['ok'=>false,'error'=>'auth']); exit; }

try {
    $pdo = Database::getInstance()->getConnection();
    $uid = (int)$_SESSION['user_id'];

    // Resolve family and email for current user
    $st = $pdo->prepare('SELECT families_id, email FROM users WHERE id = ? LIMIT 1');
    $st->execute([$uid]);
    $usrRow = $st->fetch(PDO::FETCH_ASSOC);
    $familiesId = (int)($usrRow['families_id'] ?? 0);
    $sessionEmail = strtolower(trim((string)($usrRow['email'] ?? '')));
    if (!$familiesId) { echo json_encode(['ok'=>false,'error'=>'no_family']); exit; }

    $in = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $email = trim((string)($in['email'] ?? ''));
    $scope = (string)($in['scope_type'] ?? 'family');
    $role  = (string)($in['role'] ?? 'viewer');
    $p1 = (int)($in['parent1_id'] ?? 0);
    $p2 = (int)($in['parent2_id'] ?? 0);
    $personId = (int)($in['person_id'] ?? 0);
    $can_edit = (int)!!($in['can_edit'] ?? 0);
    $can_add  = (int)!!($in['can_add'] ?? 0);
    $can_del  = (int)!!($in['can_delete'] ?? 0);
    $can_files= (int)!!($in['can_manage_files'] ?? 0);
    $message  = trim((string)($in['message'] ?? ''));
    $days = max(1, min(30, (int)($in['expires_days'] ?? 7)));

    if (!$email) { echo json_encode(['ok'=>false,'error'=>'email_required']); exit; }
    if (!in_array($scope, ['family','couple','person'], true)) $scope = 'family';
    // Block self-invite
    if ($sessionEmail && strtolower($email) === $sessionEmail) { echo json_encode(['ok'=>false,'error'=>'self_invite']); exit; }

    if (!in_array($role, ['owner','editor','viewer'], true)) $role = 'viewer';
    if ($scope === 'couple' && (!$p1 || !$p2)) { echo json_encode(['ok'=>false,'error'=>'couple_scope_requires_parents']); exit; }

    // Validate couple persons belong to family
    if ($scope === 'couple') {
        $chk = $pdo->prepare('SELECT COUNT(*) FROM persons WHERE id IN (?,?) AND family_id = ?');
        $chk->execute([$p1,$p2,$familiesId]);
        if ((int)$chk->fetchColumn() !== 2) { echo json_encode(['ok'=>false,'error'=>'parents_not_in_family']); exit; }
    }

    // If person scope, validate
    if ($scope === 'person' && $personId) {
        $chk = $pdo->prepare('SELECT COUNT(*) FROM persons WHERE id = ? AND family_id = ?');
        $chk->execute([$personId,$familiesId]);
        if ((int)$chk->fetchColumn() !== 1) { echo json_encode(['ok'=>false,'error'=>'person_not_in_family']); exit; }
    }

    // If user exists, link
    $u = $pdo->prepare('SELECT id FROM users WHERE LOWER(email) = ? LIMIT 1');
    $u->execute([strtolower($email)]);
    $invitedUserId = $u->fetchColumn() ?: null;

    // Guard: prevent inviting existing active members
    if ($invitedUserId) {
        $m = $pdo->prepare('SELECT status FROM family_members WHERE family_id = ? AND user_id = ? LIMIT 1');
        $m->execute([$familiesId, $invitedUserId]);
        $mem = $m->fetch();
        if ($mem && $mem['status'] === 'active') {
            echo json_encode(['ok'=>false,'error'=>'already_member']);
            exit;
        }
    }

    // Guard: prevent duplicate pending invites to same email within family
    $dup = $pdo->prepare('SELECT id FROM invites WHERE families_id = ? AND LOWER(email) = ? AND status = "pending" LIMIT 1');
    $dup->execute([$familiesId, strtolower($email)]);
    if ($dup->fetchColumn()) {
        echo json_encode(['ok'=>false,'error'=>'pending_invite_exists']);
        exit;
    }

    // NEW: Guard — block if this email was ever invited before in this family
    // UI can disable the Create button and show "You had invited the email."
    $prev = $pdo->prepare('SELECT id, status, created_at FROM invites
                           WHERE families_id = ? AND LOWER(email) = ?
                           ORDER BY id DESC LIMIT 1');
    $prev->execute([$familiesId, strtolower($email)]);
    if ($row = $prev->fetch(PDO::FETCH_ASSOC)) {
        echo json_encode([
            'ok' => false,
            'error' => 'already_invited',
            'status' => $row['status'],
            'invited_at' => $row['created_at']
        ]);
        exit;
    }

    // Short, URL-safe token with strong entropy (~128-bit) via helper
    try {
        [$token, $hash] = generateInviteToken();
    } catch (RuntimeException $rx) {
        echo json_encode(['ok'=>false,'error'=>'server_error']);
        exit;
    }
    $expAt = (new DateTime("+{$days} days"))->format('Y-m-d H:i:s');

    $pdo->beginTransaction();
    $ins = $pdo->prepare('INSERT INTO invites
        (families_id,email,inviter_id,invited_user_id,role,scope_type,parent1_id,parent2_id,person_id,
         can_edit,can_add,can_delete,can_manage_files,message,token_hash,token_expires_at,status,last_sent_at,sent_count)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,? , NOW(), 1)');
    $ins->execute([
        $familiesId,$email,$uid,$invitedUserId,$role,$scope,
        $p1?:null,$p2?:null,$personId?:null,
        $can_edit,$can_add,$can_del,$can_files,$message,
        $hash,$expAt,'pending'
    ]);
    $invId = (int)$pdo->lastInsertId();

    // --- PD creation with merge_type auto‑classification ---------------------
    try {
        // which local persons do we want to compare against?
        $localIds = [];
        if ($scope === 'person' && $personId) {
            $localIds = [$personId];
        } else {
            // fallback: anyone local with that email
            $lp = $pdo->prepare('SELECT id FROM persons WHERE family_id = ? AND LOWER(email) = ?');
            $lp->execute([$familiesId, strtolower($email)]);
            $localIds = array_map(fn($r) => (int)$r['id'], $lp->fetchAll(PDO::FETCH_ASSOC));
        }

        if ($localIds) {
            // remote persons with the same email (other families)
            $rp = $pdo->prepare('SELECT id, family_id FROM persons WHERE family_id <> ? AND LOWER(email) = ?');
            $rp->execute([$familiesId, strtolower($email)]);
            $remote = $rp->fetchAll(PDO::FETCH_ASSOC);

            if ($remote) {
                // helpers
                $qParents = $pdo->prepare('SELECT parent_id FROM relationships WHERE child_id = ?');
                $qIsParent = $pdo->prepare('SELECT 1 FROM relationships WHERE parent_id = ? AND child_id = ? LIMIT 1');
                $qUnion = $pdo->prepare('SELECT 1 FROM unions WHERE ((person1_id=? AND person2_id=?) OR (person1_id=? AND person2_id=?)) LIMIT 1');

                $insPd = $pdo->prepare('
                  INSERT INTO possible_duplicates
                    (person_a_id, person_b_id, invite_id, reason, notes, similar_email, status)
                  VALUES (?,?,?,?,?,1,"pending")
                  ON DUPLICATE KEY UPDATE
                    invite_id = VALUES(invite_id),
                    reason = VALUES(reason),
                    notes = VALUES(notes),
                    status = IF(status IN ("merged","dismissed"), status, "pending")
                ');

                foreach ($localIds as $la) {
                    // collect inviter-context once (who is this local person relative to current invite?)
                    $localParents = [];
                    $qParents->execute([$la]);
                    $localParents = $qParents->fetchAll(PDO::FETCH_COLUMN) ?: [];

                    foreach ($remote as $r) {
                        $rb = (int)$r['id'];
                        $a = min($la, $rb);
                        $b = max($la, $rb);

                        // classify merge_type for this pair
                        $mergeType = 'unknown';
                        $sharedParents = [];

                        // fetch remote parents
                        $qParents->execute([$rb]);
                        $remoteParents = $qParents->fetchAll(PDO::FETCH_COLUMN) ?: [];

                        // sibling_branch: any shared parent(s)
                        $inter = array_values(array_unique(array_intersect($localParents, $remoteParents)));
                        if (!empty($inter)) {
                            $mergeType = 'sibling_branch';
                            $sharedParents = array_map('intval', $inter);
                        } else {
                            // parent_child: one is parent of the other
                            $qIsParent->execute([$a, $b]); $aParentOfB = (bool)$qIsParent->fetchColumn();
                            $qIsParent->execute([$b, $a]); $bParentOfA = (bool)$qIsParent->fetchColumn();
                            if ($aParentOfB || $bParentOfA) {
                                $mergeType = 'parent_child';
                            } else {
                                // partner_alias: they are partners (union exists)
                                $qUnion->execute([$a,$b,$a,$b]);
                                if ($qUnion->fetchColumn()) {
                                    $mergeType = 'partner_alias';
                                }
                            }
                        }

                        // store context in notes (safe even if merge_type column doesn't exist)
                        $notes = [
                            'scope' => $scope,
                            'inviter_id' => $uid,
                            'families_id' => $familiesId,
                            'person_scope_id' => $personId ?: null,
                            'merge_type' => $mergeType,
                            'shared_parents' => $sharedParents,
                            'reason_detail' => 'invite_cross_family',
                        ];

                        $insPd->execute([
                            $a, $b, $invId, 'invite_cross_family', json_encode($notes, JSON_UNESCAPED_SLASHES)
                        ]);

                        // Best-effort: set merge_type column if it exists (ignore if absent)
                        try {
                            static $updPd = null;
                            if ($updPd === null) {
                                $updPd = $pdo->prepare('
                                    UPDATE possible_duplicates
                                       SET merge_type = :merge_type
                                     WHERE person_a_id = :a AND person_b_id = :b
                                       AND invite_id = :inv
                                       AND reason = "invite_cross_family"
                                     LIMIT 1
                                ');
                            }
                            $updPd->execute([
                                ':merge_type' => $mergeType,
                                ':a' => $a,
                                ':b' => $b,
                                ':inv' => $invId,
                            ]);
                        } catch (PDOException $ex) {
                            // MySQL 1054 = Unknown column; quietly ignore, rethrow others
                            $code = (int)($ex->errorInfo[1] ?? 0);
                            if ($code !== 1054) { throw $ex; }
                        }
                    }
                }
            }
        }
    } catch (Throwable $_pdE) {
        // best-effort only; PD failure must not block invite creation
    }
    // ------------------------------------------------------------------------

    $pdo->commit();

    // Determine locale for invite acceptance page using helper (explicit lang overrides session)
    $lang = normalizeInviteLang($in['lang'] ?? null, $_SESSION['lang'] ?? null);
    $inviteUrl = buildInviteUrl($token, $lang);

    // Try to fetch family name for email context (best-effort)
    $familyName = 'Your Family';
    try {
        $qf = $pdo->prepare('SELECT name FROM families WHERE id = ? LIMIT 1');
        $qf->execute([$familiesId]);
        $familyName = (string)($qf->fetchColumn() ?: $familyName);
    } catch (Throwable $__ignore) {
        // ignore if families table/name not present
    }

    // Send invitation email (best-effort; never block API success)
    $mailResult = Mailer::sendInvite($email, $inviteUrl, $message, [
        'family_name' => $familyName,
        'role' => $role,
        'scope' => $scope,
    ]);

    echo json_encode([
        'ok' => true,
        'id' => $invId,
        'invite_url' => $inviteUrl,
        'expires_at' => $expAt,
        'email' => $mailResult,
    ]);
} catch (Throwable $e) {
    if ($pdo && $pdo->inTransaction()) { $pdo->rollBack(); }
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'server_error']);
}

?>
