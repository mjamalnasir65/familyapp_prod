<?php
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../config/config.php';
header('Content-Type: application/json');

if (empty($_SESSION['user_id']) || empty($_SESSION['user_email'])) {
  echo json_encode(['ok'=>false,'error'=>'auth']); exit;
}
$pdo = Database::getInstance()->getConnection();
$uid = (int)$_SESSION['user_id'];

// Resolve family_id
$stmt = $pdo->prepare("SELECT families_id FROM users WHERE id=?");
$stmt->execute([$uid]);
$familyId = (int)($stmt->fetchColumn() ?: 0);
if (!$familyId) { echo json_encode(['ok'=>false,'error'=>'no_family']); exit; }

// Resolve primary person (focus)
$primaryId = null;
if (!empty($_SESSION['expand']['person_id']) && (int)$_SESSION['expand']['family_id'] === $familyId) {
  $primaryId = (int)$_SESSION['expand']['person_id'];
} else {
  // normal wizard “self” by email
  $q = $pdo->prepare("SELECT id FROM persons WHERE family_id=? AND email=? LIMIT 1");
  $q->execute([$familyId, $_SESSION['user_email']]);
  $primaryId = (int)$q->fetchColumn();
}
if (!$primaryId) { echo json_encode(['ok'=>false,'error'=>'self_missing']); exit; }

// Fetch primary name
$q = $pdo->prepare("SELECT full_name FROM persons WHERE id=? AND family_id=?");
$q->execute([$primaryId, $familyId]);
$primaryName = (string)($q->fetchColumn() ?: '');

// Resolve partner (priority: session last_partner_id → current union)
$partnerId = null; $partnerName = null;

if (!empty($_SESSION['last_partner_id'])) {
  $pid = (int)$_SESSION['last_partner_id'];
  $chk = $pdo->prepare("SELECT full_name FROM persons WHERE id=? AND family_id=?");
  $chk->execute([$pid, $familyId]);
  if ($n = $chk->fetchColumn()) { $partnerId = $pid; $partnerName = $n; }
}
if (!$partnerId) {
  $u = $pdo->prepare("
    SELECT CASE WHEN person1_id=? THEN person2_id ELSE person1_id END AS pid
    FROM unions
    WHERE family_id=? AND (person1_id=? OR person2_id=?) AND (is_current=1 OR is_current IS NULL)
    ORDER BY id DESC LIMIT 1
  ");
  $u->execute([$primaryId, $familyId, $primaryId, $primaryId]);
  if ($pid = $u->fetchColumn()) {
    $nq = $pdo->prepare("SELECT full_name FROM persons WHERE id=?");
    $nq->execute([$pid]);
    $partnerId = (int)$pid;
    $partnerName = (string)($nq->fetchColumn() ?: null);
  }
}

echo json_encode([
  'ok'=>true,
  'family_id'=>$familyId,
  'primary'=>['id'=>$primaryId,'name'=>$primaryName],
  'partner'=>$partnerId ? ['id'=>$partnerId,'name'=>$partnerName] : null
]);