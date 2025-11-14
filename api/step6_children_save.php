<?php
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/ActionLogger.php';
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

ActionLogger::log('step6_children_save:start', [
  'children_count' => is_array($children) ? count($children) : 0,
  'parent_a_id' => $parentA,
  'parent_b_id' => $parentB
]);

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
  // Prefer a partner remembered specifically for this primary person
  $scopedPartner = 0;
  if (!empty($_SESSION['last_partner_by_person']) && is_array($_SESSION['last_partner_by_person'])) {
    $scopedPartner = (int)($_SESSION['last_partner_by_person'][$primaryId] ?? 0);
  }
  if ($scopedPartner) {
    // Validate that a union exists between primary and scoped partner within the same family
    $vu = $pdo->prepare("SELECT 1 FROM unions WHERE family_id=? AND ((person1_id=? AND person2_id=?) OR (person1_id=? AND person2_id=?)) LIMIT 1");
    $vu->execute([$familyId, $primaryId, $scopedPartner, $scopedPartner, $primaryId]);
    if ($vu->fetch()) { $parentB = $scopedPartner; }
  }

  // Back-compat: older sessions stored a global last_partner_id.
  if (!$parentB && !empty($_SESSION["last_partner_id"])) {
    $candidate = (int)$_SESSION['last_partner_id'];
    // Only accept if that candidate is actually in a union with the primary
    $vu = $pdo->prepare("SELECT 1 FROM unions WHERE family_id=? AND ((person1_id=? AND person2_id=?) OR (person1_id=? AND person2_id=?)) LIMIT 1");
    $vu->execute([$familyId, $primaryId, $candidate, $candidate, $primaryId]);
    if ($vu->fetch()) { $parentB = $candidate; }
  }

  // Final fallback: choose the current/most recent union partner of the primary
  if (!$parentB) {
    $u = $pdo->prepare(
      "SELECT CASE WHEN person1_id=? THEN person2_id ELSE person1_id END AS pid
       FROM unions
       WHERE family_id=? AND (person1_id=? OR person2_id=?) AND (is_current=1 OR is_current IS NULL)
       ORDER BY id DESC LIMIT 1"
    );
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
  ActionLogger::log('step6_children_save:success', [
    'created' => count($created),
    'updated' => count($updated),
    'linked'  => count($linked),
    'parent_a_id' => $parentA,
    'parent_b_id' => $parentB
  ]);
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
  ActionLogger::log('step6_children_save:error', ['error'=>$e->getMessage()]);
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
?>