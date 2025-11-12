<?php
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['ok'=>false,'error'=>'unauthorized']); exit; }

try {
    $pdo = Database::getInstance()->getConnection();

    // Resolve family id from session or user record
    $familyId = (int)($_SESSION['families_id'] ?? ($_SESSION['family_id'] ?? 0));
    if (!$familyId) {
        $st = $pdo->prepare('SELECT families_id FROM users WHERE id = ? LIMIT 1');
        $st->execute([ (int)$_SESSION['user_id'] ]);
        $familyId = (int)($st->fetchColumn() ?: 0);
    }
    if (!$familyId) { echo json_encode(['ok'=>false,'error'=>'no_family']); exit; }

    // Load persons for this family
    $people = [];
    $ps = $pdo->prepare('SELECT id, full_name, gender, is_alive, birth_date, death_date, email FROM persons WHERE family_id = ? ORDER BY full_name');
    $ps->execute([$familyId]);
    $familyPersonIds = [];
    while ($r = $ps->fetch(PDO::FETCH_ASSOC)) {
        $pid = (int)$r['id'];
        $familyPersonIds[$pid] = true;
        $people[$pid] = [
            'id' => $pid,
            'name' => $r['full_name'],
            'gender' => $r['gender'],
            'is_alive' => (int)$r['is_alive'] === 1,
            'birth_date' => $r['birth_date'] ?? null,
            'death_date' => $r['death_date'] ?? null,
            'email' => $r['email'] ?? null,
            'children' => [],
            'parents' => [],
        ];
    }

    // Load alias links touching this family
    // Include cross-family links; we will still collapse ids but only render nodes belonging to this family
    $links = $pdo->prepare('SELECT pl.person_id_a a, pl.person_id_b b,
                                   pa.family_id fa, pb.family_id fb,
                                   pa.full_name an, pb.full_name bn
                            FROM person_links pl
                            LEFT JOIN persons pa ON pa.id = pl.person_id_a
                            LEFT JOIN persons pb ON pb.id = pl.person_id_b
                            WHERE (pa.family_id = ? OR pb.family_id = ?)');
    $links->execute([$familyId, $familyId]);
    $adj = [];
    $allAliasIds = [];
    while ($L = $links->fetch(PDO::FETCH_ASSOC)) {
        $a = (int)$L['a']; $b = (int)$L['b'];
        if ($a <= 0 || $b <= 0) continue;
        if (!isset($adj[$a])) $adj[$a] = [];
        if (!isset($adj[$b])) $adj[$b] = [];
        $adj[$a][] = $b; $adj[$b][] = $a;
        $allAliasIds[$a] = true; $allAliasIds[$b] = true;
    }

    // Find connected components (alias groups)
    $aliasOf = [];
    $groups = [];
    $visited = [];
    foreach (array_keys($adj) as $start) {
        if (isset($visited[$start])) continue;
        // BFS
        $q = [$start]; $visited[$start] = true; $comp = [$start];
        for ($i=0; $i<count($q); $i++) {
            $u = $q[$i];
            foreach ($adj[$u] as $v) {
                if (!isset($visited[$v])) { $visited[$v] = true; $q[] = $v; $comp[] = $v; }
            }
        }
        // Choose canonical id: prefer smallest id that belongs to this family; otherwise smallest overall
        $inFam = array_values(array_filter($comp, fn($id)=>isset($familyPersonIds[$id])));
        $canon = count($inFam) ? min($inFam) : min($comp);
        foreach ($comp as $id) { $aliasOf[$id] = $canon; }
        $groups[] = [
            'canonical_id' => $canon,
            'ids' => array_values($comp)
        ];
    }

    // Build merged people map (collapse aliases pointing into this family)
    $merged = [];
    foreach ($people as $pid => $p) {
        $cid = $aliasOf[$pid] ?? $pid;
        if (!isset($merged[$cid])) {
            // Base from canonical if available else current
            $base = $people[$cid] ?? $p;
            $merged[$cid] = $base + ['alias_ids' => []];
        }
        if ($pid !== $cid) $merged[$cid]['alias_ids'][] = $pid;
    }

    // Fetch relationships and collapse through alias map
    $rels = $pdo->prepare('SELECT parent_id, child_id, relationship_type FROM relationships WHERE family_id = ?');
    $rels->execute([$familyId]);
    $edges = [];
    $edgeSet = [];
    $childParents = [];
    while ($r = $rels->fetch(PDO::FETCH_ASSOC)) {
        $p = (int)$r['parent_id']; $c = (int)$r['child_id'];
        $p = $aliasOf[$p] ?? $p; $c = $aliasOf[$c] ?? $c;
        if (!isset($merged[$p]) || !isset($merged[$c])) continue; // Only render family nodes
        if ($p === $c) continue;
        $key = $p.'>'.$c;
        if (isset($edgeSet[$key])) continue;
        $edgeSet[$key] = true;
        $edges[] = ['parent'=>$p,'child'=>$c,'type'=>$r['relationship_type']];
        $merged[$p]['children'][] = $c;
        $merged[$c]['parents'][] = $p;
        if (!isset($childParents[$c])) $childParents[$c] = [];
        if (!in_array($p, $childParents[$c], true)) $childParents[$c][] = $p;
    }

    // Unions (current spouse) and union-only couples (no children)
    $currentSpouse = [];
    $unionPairs = [];
    $us = $pdo->prepare('SELECT person1_id, person2_id, is_current FROM unions WHERE family_id = ?');
    $us->execute([$familyId]);
    while ($u = $us->fetch(PDO::FETCH_ASSOC)) {
        $a = (int)$u['person1_id']; $b = (int)$u['person2_id'];
        $a = $aliasOf[$a] ?? $a; $b = $aliasOf[$b] ?? $b;
        if (!isset($merged[$a]) || !isset($merged[$b])) continue;
        if ((int)$u['is_current'] === 1) { $currentSpouse[$a] = $b; $currentSpouse[$b] = $a; }
        // Track union pair for couples list even if they have no recorded children
        $min = min($a,$b); $max = max($a,$b);
        if ($min > 0 && $max > 0 && $min !== $max) { $unionPairs[$min.'-'.$max] = ['a'=>$min,'b'=>$max]; }
    }
    foreach ($currentSpouse as $pid=>$sid) { if (isset($merged[$pid])) $merged[$pid]['current_spouse_id'] = $sid; }

    // Parent pairs and couples
    $parentPairs = [];
    $coParents = [];
    foreach ($childParents as $childId => $parents) {
        $parents = array_values(array_unique(array_filter($parents, fn($x)=>$x>0)));
        if (count($parents) >= 2) {
            $a = (int)$parents[0]; $b = (int)$parents[1];
            if ($a === $b) continue;
            $min = min($a,$b); $max = max($a,$b); $key = $min.'-'.$max;
            if (!isset($parentPairs[$key])) $parentPairs[$key] = ['a'=>$min,'b'=>$max,'children'=>[]];
            $parentPairs[$key]['children'][] = (int)$childId;
            if (!isset($coParents[$a])) $coParents[$a] = [];
            if (!in_array($b, $coParents[$a], true)) $coParents[$a][] = $b;
            if (!isset($coParents[$b])) $coParents[$b] = [];
            if (!in_array($a, $coParents[$b], true)) $coParents[$b][] = $a;
        } elseif (count($parents) === 1) {
            $a = (int)$parents[0]; $key = $a.'-0';
            if (!isset($parentPairs[$key])) $parentPairs[$key] = ['a'=>$a,'b'=>0,'children'=>[]];
            $parentPairs[$key]['children'][] = (int)$childId;
        }
    }
    // Seed union-only couples (ensure they exist in parentPairs even without children)
    foreach ($unionPairs as $k=>$pair) {
        if (!isset($parentPairs[$k])) { $parentPairs[$k] = ['a'=>$pair['a'],'b'=>$pair['b'],'children'=>[]]; }
    }

    $couples = [];
    foreach ($parentPairs as $k=>$v) { if (($v['b'] ?? 0) > 0) $couples[] = ['key'=>$k,'a'=>$v['a'],'b'=>$v['b'],'children'=>$v['children']]; }

    // Roots on merged graph
    $roots = [];
    foreach ($merged as $pid=>$p) { if (empty($p['parents'])) $roots[] = $pid; }

    // Prepare alias groups summary for UI (only show groups touching this family and with size>1 including a family node)
    $aliasGroups = [];
    foreach ($groups as $g) {
        $ids = $g['ids'];
        $hasFam = false; foreach ($ids as $id) { if (isset($familyPersonIds[$id])) { $hasFam = true; break; } }
        if (!$hasFam) continue;
        $visIds = array_values(array_filter($ids, fn($id)=>isset($familyPersonIds[$id])));
        if (count($visIds) <= 1) continue; // only show if at least 2 from this family are linked
        $aliasGroups[] = [
            'canonical_id' => $g['canonical_id'],
            'ids' => $visIds
        ];
    }

    echo json_encode([
        'ok' => true,
        'family_id' => $familyId,
        'alias_groups' => $aliasGroups,
        'people' => $merged,
        'edges' => $edges,
        'parent_pairs' => $parentPairs,
        'co_parents' => $coParents,
        'couples' => $couples,
        'roots' => $roots,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'server_error']);
}
?>
