<?php
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/ActionLogger.php';

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

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data) || !isset($data['partners']) || !is_array($data['partners'])){
    echo json_encode(['ok'=>false,'error'=>'invalid_payload']);
    exit;
}

try {
    $pdo = Database::getInstance()->getConnection();
    $familyId = (int)($_SESSION['families_id'] ?? ($_SESSION['family_id'] ?? 0));
    if (!$familyId) {
        $st = $pdo->prepare('SELECT families_id FROM users WHERE id = ? LIMIT 1');
        $st->execute([ (int)$_SESSION['user_id'] ]);
        $row = $st->fetch();
        $familyId = (int)($row['families_id'] ?? 0);
    }
    if (!$familyId) { echo json_encode(['ok'=>false,'error'=>'no_family']); exit; }

    // Resolve "self" person
    $selfSel = $pdo->prepare('SELECT id, gender FROM persons WHERE family_id = ? AND email = ? LIMIT 1');
    $selfSel->execute([$familyId, $_SESSION['user_email']]);
    $self = $selfSel->fetch();
    if (!$self) { echo json_encode(['ok'=>false,'error'=>'self_missing']); exit; }
    $selfId = (int)$self['id'];
    $selfGender = strtolower((string)$self['gender']);

    $pdo->beginTransaction();
    ActionLogger::log('step5_partners_save:start', ['self_email'=>$_SESSION['user_email'] ?? null,'partners_count'=>count($data['partners'])]);

    $allowedGender = ['male','female','other','prefer_not_to_say'];
    $allowedRelTypes = ['marriage','divorced','separated','widowed'];

    $created = []; $updated = []; $unions = [];

    foreach ($data['partners'] as $p){
        $name = trim($p['name'] ?? '');
        if ($name === '') continue;
        $gender = in_array(($p['gender']??''), $allowedGender, true) ? $p['gender'] : '';
        // Infer opposite gender if empty
        if ($gender === '' && ($selfGender==='male' || $selfGender==='female')){
            $gender = ($selfGender==='male') ? 'female' : 'male';
        }
        if ($gender === '') $gender = 'other';

        $status = trim($p['status'] ?? '');
        $birth = trim($p['birth_date'] ?? '');
        $death = trim($p['death_date'] ?? '');
        $isAlive = ($status === 'deceased') ? 0 : 1;

        $relType = in_array(($p['rel_type']??'marriage'), $allowedRelTypes, true) ? $p['rel_type'] : 'marriage';
        $isCurrent = !empty($p['is_current']) ? 1 : 0;
        // Enforce business rule: married => current, others => not current
        $isCurrent = ($relType === 'marriage') ? 1 : 0;

        // Upsert partner person by (family, full_name, gender)
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

        // Create/update union between self and partner
        $p1 = min($selfId, $pid); // normalize ordering for uniqueness
        $p2 = max($selfId, $pid);
        $union = $pdo->prepare('INSERT INTO unions (family_id, person1_id, person2_id, union_type, is_current) VALUES (?,?,?,?,?)
                                ON DUPLICATE KEY UPDATE union_type=VALUES(union_type), is_current=VALUES(is_current), updated_at=NOW()');
        $union->execute([$familyId, $p1, $p2, $relType, $isCurrent]);
        $unions[] = ['p1'=>$p1, 'p2'=>$p2, 'type'=>$relType, 'current'=>$isCurrent];

        // after successfully upserting a partner + union, remember last partner scoped by the focus person
        if (!headers_sent()) {
            // Back-compat global key (kept for older flows)
            $_SESSION['last_partner_id'] = $pid;
            // New: scope by the current person to avoid leaking partners across people
            if (empty($_SESSION['last_partner_by_person']) || !is_array($_SESSION['last_partner_by_person'])) {
                $_SESSION['last_partner_by_person'] = [];
            }
            $_SESSION['last_partner_by_person'][(int)$selfId] = (int)$pid;
        }
    }

    // Mark step 5 completed if at least one partner processed
    if (!empty($created) || !empty($updated)){
        $fs = $pdo->prepare("UPDATE families SET step_5_completed='Y', updated_at=NOW() WHERE id=?");
        $fs->execute([$familyId]);
    }

    $pdo->commit();
    ActionLogger::log('step5_partners_save:success', ['self_id'=>$selfId,'created'=>count($created),'updated'=>count($updated),'unions'=>count($unions)]);
    echo json_encode(['ok'=>true,'created'=>$created,'updated'=>$updated,'unions'=>$unions]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) { $pdo->rollBack(); }
    http_response_code(500);
    ActionLogger::log('step5_partners_save:error', ['error'=>'server_error']);
    echo json_encode(['ok'=>false,'error'=>'server_error']);
}
?>