<?php
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['user_id'])) { echo json_encode(['ok'=>false,'error'=>'auth']); exit; }

try {
    $pdo = Database::getInstance()->getConnection();
    $uid = (int)$_SESSION['user_id'];

    // Resolve current family
    $st = $pdo->prepare('SELECT families_id FROM users WHERE id = ? LIMIT 1');
    $st->execute([$uid]);
    $familiesId = (int)($st->fetchColumn() ?: 0);
    if (!$familiesId) { echo json_encode(['ok'=>true,'clusters'=>[]]); exit; }

    // Load persons with email in this family
    $ps = $pdo->prepare('SELECT id, full_name, email FROM persons WHERE family_id = ? AND email IS NOT NULL AND email <> ""');
    $ps->execute([$familiesId]);
    $people = $ps->fetchAll(PDO::FETCH_ASSOC);

    if (!$people) { echo json_encode(['ok'=>true,'clusters'=>[]]); exit; }

    // Group by lowercase email
    $byEmail = [];
    foreach ($people as $p) {
        $e = strtolower(trim($p['email']));
        if ($e === '') continue;
        $byEmail[$e][] = ['id'=>(int)$p['id'], 'full_name'=>$p['full_name']];
    }

    // Helper: get normalized parent pair key for a child (min-max)
    $getPairKey = function(PDO $db, int $fam, int $childId) {
        // Try edge-join (parent_id,child_id)
        try {
            $sql = 'SELECT CONCAT(LEAST(r1.parent_id, r2.parent_id),"-",GREATEST(r1.parent_id, r2.parent_id)) k
                    FROM relationships r1
                    JOIN relationships r2 ON r1.child_id = r2.child_id AND r1.parent_id < r2.parent_id
                    WHERE r1.family_id = ? AND r2.family_id = ? AND r1.child_id = ?
                    LIMIT 1';
            $s = $db->prepare($sql); $s->execute([$fam,$fam,$childId]);
            $k = $s->fetchColumn();
            if ($k) return $k;
        } catch (Throwable $e) {}
        // Fallback: wide parents schema
        try {
            $sql = 'SELECT parent1_id, parent2_id FROM relationships WHERE family_id = ? AND child_id = ? LIMIT 1';
            $s = $db->prepare($sql); $s->execute([$fam,$childId]); $row = $s->fetch(PDO::FETCH_ASSOC);
            if ($row && $row['parent1_id'] && $row['parent2_id']) {
                $a = (int)$row['parent1_id']; $b = (int)$row['parent2_id'];
                return ($a < $b ? $a.'-'.$b : $b.'-'.$a);
            }
        } catch (Throwable $e) {}
        return null;
    };

    // Build clusters: for each email with 2+ persons, split by pair key and keep groups with >=2
    $clusters = [];
    foreach ($byEmail as $email => $arr) {
        if (count($arr) < 2) continue;
        $byPair = [];
        foreach ($arr as $p) {
            $k = $getPairKey($pdo, $familiesId, $p['id']) ?: 'none';
            $byPair[$k][] = $p;
        }
        foreach ($byPair as $k => $members) {
            if ($k === 'none' || count($members) < 2) continue; // require shared parent
            [$np1,$np2] = array_map('intval', explode('-', $k));
            // Resolve parent names
            $pn = $pdo->prepare('SELECT id, full_name FROM persons WHERE id IN (?,?)');
            $pn->execute([$np1,$np2]);
            $names = [];
            foreach ($pn->fetchAll(PDO::FETCH_ASSOC) as $r) { $names[(int)$r['id']] = $r['full_name']; }
            $clusters[] = [
                'email' => $email,
                'pair_key' => $k,
                'np1' => $np1,
                'np2' => $np2,
                'parent_a' => $names[$np1] ?? ('#'.$np1),
                'parent_b' => $names[$np2] ?? ('#'.$np2),
                'persons' => $members
            ];
        }
    }

    echo json_encode(['ok'=>true,'clusters'=>$clusters]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'server_error']);
}
