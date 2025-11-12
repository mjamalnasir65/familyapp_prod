<?php
require_once __DIR__ . '/_init.php';

header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok'=>false,'error'=>'unauthorized']);
    exit;
}

try {
    $pdo = Database::getInstance()->getConnection();

    // Resolve family id from session or user record
    $familyId = (int)($_SESSION['families_id'] ?? ($_SESSION['family_id'] ?? 0));
    if (!$familyId) {
        $st = $pdo->prepare('SELECT families_id FROM users WHERE id = ? LIMIT 1');
        $st->execute([ (int)$_SESSION['user_id'] ]);
        $row = $st->fetch();
        $familyId = (int)($row['families_id'] ?? 0);
    }
    if (!$familyId) { echo json_encode(['ok'=>false,'error'=>'no_family']); exit; }

    // Fetch persons
    $people = [];
    $ps = $pdo->prepare('SELECT id, full_name, gender, is_alive, birth_date, death_date, email FROM persons WHERE family_id = ? ORDER BY full_name');
    $ps->execute([$familyId]);
    while ($r = $ps->fetch()) {
        $people[(int)$r['id']] = [
            'id' => (int)$r['id'],
            'name' => $r['full_name'],
            'gender' => $r['gender'],
            'is_alive' => (int)$r['is_alive'] === 1,
            'birth_date' => $r['birth_date'] ?? null,
            'death_date' => $r['death_date'] ?? null,
            'email' => $r['email'] ?? null,
            'children' => [],
            'parents' => []
        ];
    }

    // Fetch relationships (assuming relationships table has parent_id/child_id columns)
    $rels = $pdo->prepare('SELECT parent_id, child_id, relationship_type FROM relationships WHERE family_id = ?');
    $rels->execute([$familyId]);
    $edges = [];
    // childId => [parentIds]
    $childParents = [];
    while ($r = $rels->fetch()) {
        $p = (int)$r['parent_id']; $c = (int)$r['child_id'];
        if (isset($people[$p]) && isset($people[$c])) {
            $people[$p]['children'][] = $c;
            $people[$c]['parents'][] = $p;
            $edges[] = ['parent'=>$p,'child'=>$c,'type'=>$r['relationship_type']];
            if (!isset($childParents[$c])) $childParents[$c] = [];
            if (!in_array($p, $childParents[$c], true)) $childParents[$c][] = $p;
        }
    }

    // Fetch unions and build current spouse map
    $currentSpouse = [];
    $us = $pdo->prepare('SELECT person1_id, person2_id, is_current FROM unions WHERE family_id = ?');
    $us->execute([$familyId]);
    while ($u = $us->fetch()) {
        $a = (int)$u['person1_id']; $b = (int)$u['person2_id'];
        if (!isset($people[$a]) || !isset($people[$b])) continue;
        if ((int)$u['is_current'] === 1) {
            $currentSpouse[$a] = $b;
            $currentSpouse[$b] = $a;
        }
    }
    foreach ($currentSpouse as $pid=>$sid) {
        if (isset($people[$pid])) $people[$pid]['current_spouse_id'] = $sid;
    }

    // Build parent pairs mapping from child -> parents
    // key = "minId-maxId" (or "pid-0" for single parent)
    $parentPairs = [];
    $coParents = [];// pid => [coparentIds]
    foreach ($childParents as $childId => $parents) {
        $parents = array_values(array_unique(array_filter($parents, fn($x)=>$x>0)));
        if (count($parents) >= 2) {
            // Consider only first two distinct parents for primary pairing
            $a = (int)$parents[0]; $b = (int)$parents[1];
            if ($a === $b) { continue; }
            $min = min($a,$b); $max = max($a,$b);
            $key = $min.'-'.$max;
            if (!isset($parentPairs[$key])) $parentPairs[$key] = ['a'=>$min,'b'=>$max,'children'=>[]];
            $parentPairs[$key]['children'][] = (int)$childId;
            if (!isset($coParents[$a])) $coParents[$a] = [];
            if (!in_array($b, $coParents[$a], true)) $coParents[$a][] = $b;
            if (!isset($coParents[$b])) $coParents[$b] = [];
            if (!in_array($a, $coParents[$b], true)) $coParents[$b][] = $a;
        } elseif (count($parents) === 1) {
            $a = (int)$parents[0];
            $key = $a.'-0';
            if (!isset($parentPairs[$key])) $parentPairs[$key] = ['a'=>$a,'b'=>0,'children'=>[]];
            $parentPairs[$key]['children'][] = (int)$childId;
        }
    }

    // Derive couples (pairs with b>0) mostly for selection lists
    $couples = [];
    foreach ($parentPairs as $k=>$v) {
        if (($v['b'] ?? 0) > 0) $couples[] = ['key'=>$k,'a'=>$v['a'],'b'=>$v['b'],'children'=>$v['children']];
    }

    // Roots at individual level still useful for fallback
    $roots = [];
    foreach ($people as $pid => $p) {
        if (count($p['parents']) === 0) $roots[] = $pid;
    }

    echo json_encode([
        'ok'=>true,
        'family_id'=>$familyId,
        'roots'=>$roots,
        'people'=>$people,
        'edges'=>$edges,
        'parent_pairs'=>$parentPairs,
        'co_parents'=>$coParents,
        'couples'=>$couples
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'server_error']);
}
?>
