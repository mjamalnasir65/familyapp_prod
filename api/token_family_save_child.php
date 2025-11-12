<?php
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../config/config.php';
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['ok'=>false,'error'=>'method_not_allowed']); exit; }
if (empty($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['ok'=>false,'error'=>'unauthorized']); exit; }

try {
  $pdo = Database::getInstance()->getConnection();
  $uid = (int)$_SESSION['user_id'];
  $in = json_decode(file_get_contents('php://input'), true) ?: $_POST;
  $tok = trim((string)($in['tok'] ?? ''));
  $childNameRaw = trim((string)($in['child_name'] ?? ''));
  $childGender = isset($in['child_gender']) ? (string)$in['child_gender'] : null;
  $withPartner = !empty($in['with_partner']);
  $partnerNameRaw = trim((string)($in['partner_name'] ?? ''));

  if (!$tok || $childNameRaw === '') { echo json_encode(['ok'=>false,'error'=>'bad_request']); exit; }
  $hash = hash('sha256', $tok);

  // Resolve family by token
  $st = $pdo->prepare('SELECT id, family_token_union_id, expected_children, children_added FROM families WHERE family_token_hash=? AND (family_token_expires_at IS NULL OR family_token_expires_at>NOW()) LIMIT 1');
  $st->execute([$hash]);
  $fam = $st->fetch(PDO::FETCH_ASSOC);
  if (!$fam) { echo json_encode(['ok'=>false,'error'=>'invalid_or_expired']); exit; }
  $familyId = (int)$fam['id'];
  $unionId = (int)($fam['family_token_union_id'] ?? 0);
  if (!$unionId) { echo json_encode(['ok'=>false,'error'=>'union_missing']); exit; }

  // Fetch parents from union (scope check)
  $pu = $pdo->prepare('SELECT person1_id, person2_id FROM unions WHERE id=? AND family_id=? LIMIT 1');
  $pu->execute([$unionId, $familyId]);
  $parents = $pu->fetch(PDO::FETCH_ASSOC);
  if (!$parents) { echo json_encode(['ok'=>false,'error'=>'forbidden_family']); exit; }

  // Normalize names for dedupe
  $norm = function($s){ $s = preg_replace('/\s+/', ' ', trim(mb_strtolower($s))); return $s; };
  $childName = $childNameRaw; $childKey = $norm($childNameRaw);

  $pdo->beginTransaction();

  // Dedupe: existing person with same name and both parent edges
  $find = $pdo->prepare('SELECT p.id FROM persons p
                          WHERE p.family_id=? AND LOWER(TRIM(REPLACE(p.full_name, "  ", " "))) = ? LIMIT 1');
  $find->execute([$familyId, $childKey]);
  $existingChildId = (int)($find->fetchColumn() ?: 0);
  if ($existingChildId) {
    // Verify both parent edges exist
    $chk = $pdo->prepare('SELECT COUNT(*) FROM relationships WHERE family_id=? AND child_id=? AND parent_id IN (?, ?)');
    $chk->execute([$familyId, $existingChildId, (int)$parents['person1_id'], (int)$parents['person2_id']]);
    $cnt = (int)$chk->fetchColumn();
    if ($cnt >= 2) {
      // Already exists; do not create new
      $pdo->commit();
      echo json_encode(['ok'=>true,'child_id'=>$existingChildId,'children_added'=>(int)$fam['children_added'],'remaining'=> max(0, (int)($fam['expected_children'] ?? 0) - (int)$fam['children_added'])]);
      exit;
    }
  }

  // Create child person
  $pi = $pdo->prepare('INSERT INTO persons (family_id, full_name, gender, is_alive, created_by) VALUES (?,?,?,?,?)');
  $pi->execute([$familyId, $childName, $childGender ?: 'other', 1, $uid]);
  $childId = (int)$pdo->lastInsertId();

  // Insert two parent edges
  $ri = $pdo->prepare('INSERT INTO relationships (family_id, child_id, parent_id, relationship_type) VALUES (?,?,?,?)');
  $ri->execute([$familyId, $childId, (int)$parents['person1_id'], 'biological']);
  $ri->execute([$familyId, $childId, (int)$parents['person2_id'], 'biological']);

  // Optional partner + union
  $newUnionId = null;
  if ($withPartner && $partnerNameRaw !== '') {
    $pi->execute([$familyId, $partnerNameRaw, 'other', 1, $uid]);
    $partnerId = (int)$pdo->lastInsertId();
    $ui = $pdo->prepare('INSERT INTO unions (family_id, person1_id, person2_id, union_type, is_current) VALUES (?,?,?,?,1)');
    $ui->execute([$familyId, $childId, $partnerId, 'marriage']);
    $newUnionId = (int)$pdo->lastInsertId();
  }

  // Bump children_added bounded by expected_children
  $expected = (int)($fam['expected_children'] ?? 0);
  $added = (int)$fam['children_added'] + 1;
  if ($expected > 0 && $added > $expected) { $added = $expected; }
  $pdo->prepare('UPDATE families SET children_added=? WHERE id=?')->execute([$added, $familyId]);

  $pdo->commit();

  echo json_encode([
    'ok'=>true,
    'child_id'=>$childId,
    'new_union_id'=>$newUnionId,
    'children_added'=>$added,
    'remaining'=> max(0, $expected - $added)
  ]);
} catch (Throwable $e) {
  if (!empty($pdo) && $pdo->inTransaction()) $pdo->rollBack();
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'server_error','detail'=>$e->getMessage()]);
}
