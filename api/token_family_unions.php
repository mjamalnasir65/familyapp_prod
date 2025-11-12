<?php
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../config/config.php';
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'GET') { http_response_code(405); echo json_encode(['ok'=>false,'error'=>'method_not_allowed']); exit; }
if (empty($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['ok'=>false,'error'=>'unauthorized']); exit; }

try {
  $pdo = Database::getInstance()->getConnection();
  $uid = (int)$_SESSION['user_id'];
  $st = $pdo->prepare('SELECT families_id FROM users WHERE id=? LIMIT 1');
  $st->execute([$uid]); $familyId = (int)($st->fetchColumn() ?: 0);
  if (!$familyId) { echo json_encode(['ok'=>false,'error'=>'no_family']); exit; }

  $q = $pdo->prepare('SELECT u.id, u.person1_id, u.person2_id, p1.full_name AS a_name, p2.full_name AS b_name
                      FROM unions u
                      LEFT JOIN persons p1 ON p1.id=u.person1_id
                      LEFT JOIN persons p2 ON p2.id=u.person2_id
                      WHERE u.family_id=? ORDER BY u.id DESC');
  $q->execute([$familyId]);
  $rows = $q->fetchAll(PDO::FETCH_ASSOC) ?: [];
  $list = array_map(function($r){
    return [
      'union_id'=>(int)$r['id'],
      'a_id'=>(int)$r['person1_id'], 'a_name'=>$r['a_name'],
      'b_id'=>(int)$r['person2_id'], 'b_name'=>$r['b_name'],
      'label'=> trim(($r['a_name'] ?? 'Unknown'). ' + ' . ($r['b_name'] ?? 'Unknown'))
    ];
  }, $rows);

  echo json_encode(['ok'=>true,'unions'=>$list]);
} catch (Throwable $e) {
  http_response_code(500); echo json_encode(['ok'=>false,'error'=>'server_error']);
}
