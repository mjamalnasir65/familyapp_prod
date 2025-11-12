<?php
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../config/config.php';
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['ok'=>false,'error'=>'method_not_allowed']); exit; }
if (empty($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['ok'=>false,'error'=>'unauthorized']); exit; }

try {
  $pdo = Database::getInstance()->getConnection();
  $uid = (int)$_SESSION['user_id'];

  // Resolve session family
  $st = $pdo->prepare('SELECT families_id FROM users WHERE id = ? LIMIT 1');
  $st->execute([$uid]);
  $familyId = (int)($st->fetchColumn() ?: 0);
  if (!$familyId) { echo json_encode(['ok'=>false,'error'=>'no_family']); exit; }

  $in = json_decode(file_get_contents('php://input'), true) ?: $_POST;
  // Fixed default: 72 hours, user doesn't choose TTL
  $ttlHours = 72;
  $expectedChildren = isset($in['expected_children']) ? max(0, (int)$in['expected_children']) : 0;
  $unionId = (int)($in['union_id'] ?? 0);

  if (!$unionId) { echo json_encode(['ok'=>false,'error'=>'union_required']); exit; }

  // Verify union belongs to this family
  $u = $pdo->prepare('SELECT id, person1_id, person2_id FROM unions WHERE id=? AND family_id=? LIMIT 1');
  $u->execute([$unionId, $familyId]);
  $union = $u->fetch(PDO::FETCH_ASSOC);
  if (!$union) { echo json_encode(['ok'=>false,'error'=>'forbidden_family']); exit; }

  // Generate a short, user-friendly token code (16 chars, ~96 bits)
  $rawBytes = random_bytes(12);
  $code = rtrim(strtr(base64_encode($rawBytes), '+/', '-_'), '=');
  $hash = hash('sha256', $code);
  $exp = (new DateTime("+{$ttlHours} hour"))->format('Y-m-d H:i:s');

  $upd = $pdo->prepare('UPDATE families SET family_token_hash=?, family_token_expires_at=?, family_token_issued_by=?, family_token_union_id=?, expected_children=?, children_added=0 WHERE id=?');
  $upd->execute([$hash, $exp, $uid, $unionId, $expectedChildren, $familyId]);

  $url = BASE_URL . '/chat_token_wizard.html?tok=' . urlencode($code);
  echo json_encode(['ok'=>true,'code'=>$code,'url'=>$url,'family_id'=>$familyId,'union_id'=>$unionId,'expires_at'=>$exp,'ttl_hours'=>$ttlHours]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'server_error','detail'=>$e->getMessage()]);
}
