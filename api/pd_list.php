<?php
require_once __DIR__ . '/_init.php';

header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['user_id'])) { echo json_encode(['ok'=>false,'error'=>'auth']); exit; }

try {
    $pdo = Database::getInstance()->getConnection();
    $uid = (int)$_SESSION['user_id'];

    // Resolve current family
    $st = $pdo->prepare('SELECT families_id FROM users WHERE id = ? LIMIT 1');
    $st->execute([$uid]);
    $familyId = (int)($st->fetchColumn() ?: 0);
    if (!$familyId) { echo json_encode(['ok'=>true,'items'=>[]]); exit; }

    // PDs where either person belongs to this family
    $sql = 'SELECT pd.id, pd.person_a_id, pd.person_b_id, pd.similar_email, pd.similar_full_name, pd.similar_father_name, pd.similar_mother_name, pd.similar_shared_parent, pd.status, pd.created_at,
                   pd.u1_merged_click, pd.u2_merged_click, pd.invite_id, pd.reason,
                   pa.full_name AS a_name, pa.family_id AS a_family_id,
                   pb.full_name AS b_name, pb.family_id AS b_family_id
            FROM possible_duplicates pd
            JOIN persons pa ON pa.id = pd.person_a_id
            JOIN persons pb ON pb.id = pd.person_b_id
            WHERE pa.family_id = ? OR pb.family_id = ?
            ORDER BY pd.created_at DESC
            LIMIT 500';
    $q = $pdo->prepare($sql);
    $q->execute([$familyId, $familyId]);
    $rows = $q->fetchAll(PDO::FETCH_ASSOC);

    $items = [];
    foreach ($rows as $r) {
        $yourSide = ($r['a_family_id'] == $familyId) ? 'u1' : (($r['b_family_id'] == $familyId) ? 'u2' : null);
        $yourConsented = $yourSide === 'u1' ? ($r['u1_merged_click'] === 'Y') : ($yourSide === 'u2' ? ($r['u2_merged_click'] === 'Y') : false);
        $items[] = [
            'id' => (int)$r['id'],
            'a'  => [ 'id'=>(int)$r['person_a_id'], 'name'=>$r['a_name'], 'family_id'=>(int)$r['a_family_id'] ],
            'b'  => [ 'id'=>(int)$r['person_b_id'], 'name'=>$r['b_name'], 'family_id'=>(int)$r['b_family_id'] ],
            'flags' => [
                'email' => (bool)$r['similar_email'],
                'name'  => (bool)$r['similar_full_name'],
                'father'=> (bool)$r['similar_father_name'],
                'mother'=> (bool)$r['similar_mother_name'],
                'parents'=> (bool)$r['similar_shared_parent']
            ],
            'status' => $r['status'],
            'can_merge' => ($r['status'] === 'reviewed'),
            'u1' => ($r['u1_merged_click'] === 'Y'),
            'u2' => ($r['u2_merged_click'] === 'Y'),
            'invite_id' => isset($r['invite_id']) ? (int)$r['invite_id'] : null,
            'reason' => $r['reason'] ?? null,
            'your_side' => $yourSide,
            'your_consented' => $yourConsented
        ];
    }

    echo json_encode(['ok'=>true,'items'=>$items]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'server_error']);
}

?>
