<?php
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../config/config.php';
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'GET') { http_response_code(405); echo json_encode(['ok'=>false,'error'=>'method_not_allowed']); exit; }

try {
  $pdo = Database::getInstance()->getConnection();
  $tok = $_GET['tok'] ?? $_POST['tok'] ?? '';
  if (!$tok || strlen($tok) < 16) { echo json_encode(['ok'=>false,'error'=>'bad_token']); exit; }
  $hash = hash('sha256', $tok);

  $st = $pdo->prepare('SELECT f.id AS family_id, f.name AS family_name, f.family_token_union_id, f.expected_children, f.children_added
                       FROM families f
                       WHERE f.family_token_hash=? AND (f.family_token_expires_at IS NULL OR f.family_token_expires_at > NOW())
                       LIMIT 1');
  $st->execute([$hash]);
  $fam = $st->fetch(PDO::FETCH_ASSOC);
  if (!$fam) { echo json_encode(['ok'=>false,'error'=>'invalid_or_expired']); exit; }

  $unionId = (int)($fam['family_token_union_id'] ?? 0);
  $parents = null;
  if ($unionId) {
    $p = $pdo->prepare('SELECT u.id, u.person1_id, u.person2_id, p1.full_name AS a_name, p2.full_name AS b_name
                        FROM unions u
                        LEFT JOIN persons p1 ON p1.id=u.person1_id
                        LEFT JOIN persons p2 ON p2.id=u.person2_id
                        WHERE u.id=? LIMIT 1');
    $p->execute([$unionId]);
    $parents = $p->fetch(PDO::FETCH_ASSOC) ?: null;
  }

  echo json_encode([
    'ok'=>true,
    'family_id'=>(int)$fam['family_id'],
    'family_name'=>$fam['family_name'],
    'union_id'=>$unionId ?: null,
    'expected_children'=> isset($fam['expected_children']) ? (int)$fam['expected_children'] : 0,
    'children_added'=> (int)$fam['children_added'],
    'parents'=>$parents ? [
      'a_id'=>(int)$parents['person1_id'], 'a_name'=>$parents['a_name'] ?? null,
      'b_id'=>(int)$parents['person2_id'], 'b_name'=>$parents['b_name'] ?? null
    ] : null
  ]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'server_error']);
}
