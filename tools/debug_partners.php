<?php
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../config/config.php';
header('Content-Type: application/json');

$pid = (int)($_GET['person_id'] ?? 0);
$fid = isset($_GET['family_id']) ? (int)$_GET['family_id'] : 0;
if (!$pid) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'missing_person_id']); exit; }

try {
  $pdo = Database::getInstance()->getConnection();

  if (!$fid) {
    $qf = $pdo->prepare('SELECT family_id FROM persons WHERE id=? LIMIT 1');
    $qf->execute([$pid]);
    $fid = (int)($qf->fetchColumn() ?: 0);
  }
  if (!$fid) { echo json_encode(['ok'=>false,'error'=>'family_not_found']); exit; }

  $q = $pdo->prepare('SELECT id, person1_id, person2_id, union_type, is_current FROM unions WHERE family_id=? AND (person1_id=? OR person2_id=?) ORDER BY id');
  $q->execute([$fid, $pid, $pid]);
  $rows = $q->fetchAll(PDO::FETCH_ASSOC) ?: [];

  echo json_encode(['ok'=>true,'family_id'=>$fid,'person_id'=>$pid,'unions'=>$rows]);
} catch (Throwable $e) {
  http_response_code(500); echo json_encode(['ok'=>false,'error'=>'server_error']);
}
