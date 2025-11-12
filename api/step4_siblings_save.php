<?php
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok'=>false,'error'=>'method_not_allowed']);
    exit;
}

if (empty($_SESSION['user_id']) || empty($_SESSION['user_email'])) {
    http_response_code(401);
    echo json_encode(['ok'=>false,'error'=>'unauthorized']);
    exit;
}

// Expect a JSON body with an array of siblings
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data) || !isset($data['siblings']) || !is_array($data['siblings'])){
    echo json_encode(['ok'=>false,'error'=>'invalid_payload']);
    exit;
}

try {
    $pdo = Database::getInstance()->getConnection();
    // Resolve family and self person id
  $familyId = (int)($_SESSION['families_id'] ?? ($_SESSION['family_id'] ?? 0));
    if (!$familyId) {
        $st = $pdo->prepare('SELECT families_id FROM users WHERE id = ? LIMIT 1');
        $st->execute([ (int)$_SESSION['user_id'] ]);
        $row = $st->fetch();
        $familyId = (int)($row['families_id'] ?? 0);
    }
    if (!$familyId) { echo json_encode(['ok'=>false,'error'=>'no_family']); exit; }

    $selfSel = $pdo->prepare('SELECT id FROM persons WHERE family_id = ? AND email = ? LIMIT 1');
    $selfSel->execute([$familyId, $_SESSION['user_email']]);
    $self = $selfSel->fetch();
    if (!$self) { echo json_encode(['ok'=>false,'error'=>'self_missing']); exit; }
    $selfId = (int)$self['id'];

    $pdo->beginTransaction();

  $parents = is_array($data['parents'] ?? null) ? $data['parents'] : [];
  $fatherId = isset($parents['father_id']) ? (int)$parents['father_id'] : null;
  $motherId = isset($parents['mother_id']) ? (int)$parents['mother_id'] : null;

  // Fallback: if parent IDs were not sent from client, infer them from current self relationships
  if (!$fatherId || !$motherId) {
    $relSel = $pdo->prepare('SELECT r.parent_id, p.gender FROM relationships r INNER JOIN persons p ON p.id = r.parent_id WHERE r.family_id = ? AND r.child_id = ?');
    $relSel->execute([$familyId, $selfId]);
    $parentsFound = $relSel->fetchAll();
    if ($parentsFound) {
      foreach ($parentsFound as $r) {
        $pid = (int)$r['parent_id'];
        $pg = strtolower((string)$r['gender']);
        if (!$fatherId && $pg === 'male') { $fatherId = $pid; continue; }
        if (!$motherId && $pg === 'female') { $motherId = $pid; continue; }
      }
      // If still missing slots, fill by order without duplicating
      foreach ($parentsFound as $r) {
        $pid = (int)$r['parent_id'];
        if (!$fatherId) { $fatherId = $pid; continue; }
        if (!$motherId && $pid !== $fatherId) { $motherId = $pid; }
      }
    }
  }

  $created = []; $updated = []; $linked = [];
  $allowedRel = ['biological','adopted','step'];
    $allowedGender = ['male','female','other','prefer_not_to_say'];

    foreach ($data['siblings'] as $sib){
      $name = trim($sib['name'] ?? '');
      if ($name === '') continue;
      $gender = in_array(($sib['gender']??''), $allowedGender, true) ? $sib['gender'] : 'other';
      $status = trim($sib['status'] ?? '');
      $birth = trim($sib['birth_date'] ?? '');
      $death = trim($sib['death_date'] ?? '');
  // Enforce biological by default; relationship details can be edited later in profile
  $type = 'biological';
      $isAlive = ($status === 'deceased') ? 0 : 1;

      // Upsert person by (family, full_name, gender)
      $sel = $pdo->prepare('SELECT id FROM persons WHERE family_id=? AND full_name=? AND gender=? LIMIT 1');
      $sel->execute([$familyId, $name, $gender]);
      $row = $sel->fetch();
      if ($row) {
        $pid = (int)$row['id'];
        $upd = $pdo->prepare('UPDATE persons SET is_alive=?, birth_date=?, death_date=?, updated_at=NOW() WHERE id=?');
        $upd->execute([$isAlive, ($birth?:null), ($death?:null), $pid]);
        $updated[] = $pid;
      } else {
        $ins = $pdo->prepare('INSERT INTO persons (family_id, full_name, gender, is_alive, birth_date, death_date, created_by) VALUES (?,?,?,?,?,?,?)');
        $ins->execute([$familyId, $name, $gender, $isAlive, ($birth?:null), ($death?:null), (int)$_SESSION['user_id']]);
        $pid = (int)$pdo->lastInsertId();
        $created[] = $pid;
      }

      // Always link to both parents if available (no checkbox controls in UI)
      if ($fatherId){
        $rel = $pdo->prepare('INSERT INTO relationships (family_id, parent_id, child_id, relationship_type) VALUES (?,?,?,?)
                              ON DUPLICATE KEY UPDATE relationship_type=VALUES(relationship_type)');
        $rel->execute([$familyId, $fatherId, $pid, $type]);
        $linked[] = [ 'parent' => $fatherId, 'child' => $pid ];
      }
      if ($motherId){
        $rel = $pdo->prepare('INSERT INTO relationships (family_id, parent_id, child_id, relationship_type) VALUES (?,?,?,?)
                              ON DUPLICATE KEY UPDATE relationship_type=VALUES(relationship_type)');
        $rel->execute([$familyId, $motherId, $pid, $type]);
        $linked[] = [ 'parent' => $motherId, 'child' => $pid ];
      }
    }

    // Mark step 4 completed
    $fs = $pdo->prepare("UPDATE families SET step_4_completed='Y', updated_at=NOW() WHERE id=?");
    $fs->execute([$familyId]);

    $pdo->commit();
  echo json_encode(['ok'=>true,'created'=>$created,'updated'=>$updated,'linked'=>$linked]);
} catch (Throwable $e) {
    if (!empty($pdo) && $pdo->inTransaction()) { $pdo->rollBack(); }
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'server_error']);
}
