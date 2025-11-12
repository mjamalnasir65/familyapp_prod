<?php
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../config/config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['ok'=>false,'error'=>'method']); exit; }
if (empty($_SESSION['user_id']) || empty($_SESSION['user_email'])) { echo json_encode(['ok'=>false,'error'=>'auth']); exit; }

$pdo = Database::getInstance()->getConnection();
$uid = (int)$_SESSION['user_id'];

// family
$st = $pdo->prepare("SELECT families_id FROM users WHERE id=?");
$st->execute([$uid]);
$familyId = (int)($st->fetchColumn() ?: 0);
if (!$familyId) { echo json_encode(['ok'=>false,'error'=>'no_family']); exit; }

$raw = file_get_contents('php://input');
$body = json_decode($raw, true) ?: [];
$children = $body['children'] ?? [];
$parentA = isset($body['parent_a_id']) ? (int)$body['parent_a_id'] : 0;
$parentB = isset($body['parent_b_id']) ? (int)$body['parent_b_id'] : 0;

// Resolve primary/focus person (must be one parent)
$primaryId = null;
if (!empty($_SESSION['expand']['person_id']) && (int)$_SESSION['expand']['family_id'] === $familyId) {
  $primaryId = (int)$_SESSION['expand']['person_id'];
} else {
  $q = $pdo->prepare("SELECT id FROM persons WHERE family_id=? AND email=? LIMIT 1");
  $q->execute([$familyId, $_SESSION['user_email']]);
  $primaryId = (int)$q->fetchColumn();
}
if (!$primaryId) { echo json_encode(['ok'=>false,'error'=>'self_missing']); exit; }

// If client didn’t pass parents, infer: A=primary, B=session last partner or current union
if (!$parentA) $parentA = $primaryId;
if ($parentA !== $primaryId) { echo json_encode(['ok'=>false,'error'=>'parentA_must_be_primary']); exit; }
if (!$parentB) {
  if (!empty($_SESSION['last_partner_id'])) {
    $chk = $pdo->prepare("SELECT 1 FROM persons WHERE id=? AND family_id=?");
    $chk->execute([ (int)$_SESSION['last_partner_id'], $familyId ]);
    if ($chk->fetch()) $parentB = (int)$_SESSION['last_partner_id'];
  }
  if (!$parentB) {
    $u = $pdo->prepare("
      SELECT CASE WHEN person1_id=? THEN person2_id ELSE person1_id END AS pid
      FROM unions
      WHERE family_id=? AND (person1_id=? OR person2_id=?) AND (is_current=1 OR is_current IS NULL)
      ORDER BY id DESC LIMIT 1
    ");
    $u->execute([$primaryId, $familyId, $primaryId, $primaryId]);
    $parentB = (int)($u->fetchColumn() ?: 0);
  }
}

// Validate parents exist in family (A must; B optional)
$valA = $pdo->prepare("SELECT full_name FROM persons WHERE id=? AND family_id=?");
$valA->execute([$parentA,$familyId]);
$parentAName = $valA->fetchColumn();
if (!$parentAName) { echo json_encode(['ok'=>false,'error'=>'parentA_not_found']); exit; }

$parentBName = null;
if ($parentB) {
  $valB = $pdo->prepare("SELECT full_name FROM persons WHERE id=? AND family_id=?");
  $valB->execute([$parentB,$familyId]);
  $parentBName = $valB->fetchColumn();
  if (!$parentBName) $parentB = 0; // ignore invalid B
}

$created=[]; $updated=[]; $linked=[];

try {
  $pdo->beginTransaction();

  foreach ($children as $c) {
    $name = trim($c['name'] ?? '');
    if ($name === '') continue;
    $gender = trim($c['gender'] ?? '');
    $status = strtolower(trim($c['status'] ?? 'living'));
    $isAlive = $status !== 'deceased' ? 1 : 0;
    $birth = trim($c['birth_date'] ?? '');
    $death = trim($c['death_date'] ?? '');

    // upsert child by (family_id, full_name, gender)
    $sel = $pdo->prepare("SELECT id FROM persons WHERE family_id=? AND full_name=? AND (gender=? OR ?='')");
    $sel->execute([$familyId, $name, $gender, $gender]);
    $childId = (int)($sel->fetchColumn() ?: 0);

    if ($childId) {
      $upd = $pdo->prepare("UPDATE persons SET is_alive=?, birth_date=?, death_date=?, gender=IF(?<>'',?,gender), updated_at=NOW() WHERE id=?");
      $upd->execute([$isAlive, $birth ?: null, $death ?: null, $gender, $gender, $childId]);
      $updated[]=$childId;
    } else {
      $ins = $pdo->prepare("INSERT INTO persons (family_id,full_name,gender,is_alive,birth_date,death_date,created_by) VALUES (?,?,?,?,?,?,?)");
      $ins->execute([$familyId, $name, $gender ?: null, $isAlive, $birth ?: null, $death ?: null, $uid]);
      $childId = (int)$pdo->lastInsertId();
      $created[]=$childId;
    }

    // parent-child edges (A is mandatory)
    $edge = $pdo->prepare("INSERT INTO relationships (family_id,parent_id,child_id,relationship_type,created_at)
                           VALUES (?,?,?,?,NOW())
                           ON DUPLICATE KEY UPDATE relationship_type=VALUES(relationship_type)");
    $edge->execute([$familyId, $parentA, $childId, 'biological']);
    $linked[] = [$parentA,$childId,'biological'];

    if ($parentB && $parentB !== $parentA) {
      $edge->execute([$familyId, $parentB, $childId, 'biological']);
      $linked[] = [$parentB,$childId,'biological'];
    }
  }

  // If this is the original wizard (not expand), mark completed
  if (empty($_SESSION['expand'])) {
    $done = $pdo->prepare("UPDATE families SET step_6_completed='Y', wizard_completed_at=IFNULL(wizard_completed_at,NOW()), updated_at=NOW() WHERE id=?");
    $done->execute([$familyId]);
  }

  $pdo->commit();
  echo json_encode([
    'ok'=>true,
    'parents'=>[
      'a'=>['id'=>$parentA,'name'=>$parentAName],
      'b'=>$parentB ? ['id'=>$parentB,'name'=>$parentBName] : null
    ],
    'created'=>$created,'updated'=>$updated,'relationships'=>$linked
  ]);
} catch (Throwable $e) {
  $pdo->rollBack();
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
?>