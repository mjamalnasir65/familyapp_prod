<?php
// Maintenance utility to repair a mistaken co-parent union and parent edges.
// Usage (CLI or browser restricted env):
//   php tools/fix_union_repair.php family_id=1 primary_id=3 wrong_partner_id=8 correct_partner_id=2
//   or /tools/fix_union_repair.php?family_id=1&primary_id=3&wrong_partner_id=8&correct_partner_id=2
// It will:
//  - delete union between primary and wrong partner (if exists)
//  - for each child that currently has (primary, wrong) as parents:
//      * remove the (wrong -> child) relationship
//      * add (correct -> child) relationship if missing

require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../config/config.php';

// Accept both CLI args and query params
parse_str(implode('&', array_slice($argv ?? [], 1)), $cliParams);
$in = array_merge($_GET ?? [], $_POST ?? [], $cliParams);

$familyId = (int)($in['family_id'] ?? 0);
$primaryId = (int)($in['primary_id'] ?? 0);
$wrongId   = (int)($in['wrong_partner_id'] ?? 0);
$correctId = (int)($in['correct_partner_id'] ?? 0);

header('Content-Type: application/json');
if (!$familyId || !$primaryId || !$wrongId || !$correctId) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>'missing_params','hint'=>'family_id, primary_id, wrong_partner_id, correct_partner_id']);
  exit;
}

try {
  $pdo = Database::getInstance()->getConnection();
  $pdo->beginTransaction();

  // Delete mistaken union (both orderings)
  $du = $pdo->prepare('DELETE FROM unions WHERE family_id=? AND ((person1_id=? AND person2_id=?) OR (person1_id=? AND person2_id=?))');
  $du->execute([$familyId, $primaryId, $wrongId, $wrongId, $primaryId]);

  // Find children that have both primary and wrong as parents
  $q = $pdo->prepare('SELECT r1.child_id
                      FROM relationships r1
                      JOIN relationships r2 ON r2.family_id=r1.family_id AND r2.child_id=r1.child_id
                      WHERE r1.family_id=? AND r1.parent_id=? AND r2.parent_id=?');
  $q->execute([$familyId, $primaryId, $wrongId]);
  $children = $q->fetchAll(PDO::FETCH_COLUMN) ?: [];

  // Remove wrong parent edge and add correct parent edge if missing
  $dr = $pdo->prepare('DELETE FROM relationships WHERE family_id=? AND parent_id=? AND child_id=?');
  $ir = $pdo->prepare('INSERT INTO relationships (family_id,parent_id,child_id,relationship_type,created_at) VALUES (?,?,?,?,NOW())
                       ON DUPLICATE KEY UPDATE relationship_type=VALUES(relationship_type)');

  foreach ($children as $cid) {
    $dr->execute([$familyId, $wrongId, (int)$cid]);
    $ir->execute([$familyId, $correctId, (int)$cid, 'biological']);
  }

  $pdo->commit();
  echo json_encode(['ok'=>true,'fixed_children'=>array_map('intval',$children)]);
} catch (Throwable $e) {
  if (!empty($pdo) && $pdo->inTransaction()) $pdo->rollBack();
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'server_error']);
}
