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

  $unionId = (int)($in['union_id'] ?? 0);
  $parentNestedId = !empty($in['parent_nested_id']) ? (int)$in['parent_nested_id'] : null;
  $kind = (($in['kind'] ?? 'D') === 'A') ? 'A' : 'D';
  $expectedChildren = isset($in['expected_children']) ? max(0, (int)$in['expected_children']) : null;

  if (!$unionId) { echo json_encode(['ok'=>false,'error'=>'union_required']); exit; }

  // Scope via union
  $fu = $pdo->prepare('SELECT family_id FROM unions WHERE id=? LIMIT 1');
  $fu->execute([$unionId]);
  $familyId = (int)($fu->fetchColumn() ?: 0);
  if (!$familyId) { echo json_encode(['ok'=>false,'error'=>'not_found']); exit; }

  // Compute level/label
  $level = 1; $label = 'L1';
  if ($parentNestedId) {
    $ps = $pdo->prepare('SELECT level, family_id FROM nested_families WHERE id=? LIMIT 1');
    $ps->execute([$parentNestedId]);
    $p = $ps->fetch(PDO::FETCH_ASSOC);
    if (!$p || (int)$p['family_id'] !== $familyId) { echo json_encode(['ok'=>false,'error'=>'forbidden_family']); exit; }
    $level = ((int)$p['level']) + 1; $label = 'L' . $level;
  }

  $ins = $pdo->prepare('INSERT INTO nested_families (family_id, kind, level, label, parent_nested_id, root_union_id, expected_children, created_by) VALUES (?,?,?,?,?,?,?,?)');
  $ins->execute([$familyId, $kind, $level, $label, $parentNestedId, $unionId, $expectedChildren, $uid]);
  $nestedId = (int)$pdo->lastInsertId();

  // Add creator as owner member (best effort)
  $ms = $pdo->prepare('INSERT INTO nested_members (nested_id, user_id, role) VALUES (?,?,?)');
  try { $ms->execute([$nestedId, $uid, 'owner']); } catch (Throwable $ignored) {}

  echo json_encode(['ok'=>true,'nested_id'=>$nestedId,'level'=>$level,'label'=>$label]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'server_error']);
}
