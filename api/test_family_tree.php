<?php
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

// Guard: session required
if (empty($_SESSION['user_id'])) {
	http_response_code(401);
	echo json_encode(['ok'=>false,'error'=>'unauthorized']);
	exit;
}

try {
	$pdo = Database::getInstance()->getConnection();

	// Resolve family id using repo convention (users.families_id)
	$familyId = (int)($_SESSION['families_id'] ?? ($_SESSION['family_id'] ?? 0));
	if (!$familyId) {
		$st = $pdo->prepare('SELECT families_id FROM users WHERE id = ? LIMIT 1');
		$st->execute([ (int)$_SESSION['user_id'] ]);
		$row = $st->fetch();
		$familyId = (int)($row['families_id'] ?? 0);
	}
	if (!$familyId) {
		echo json_encode(['ok'=>false,'error'=>'no_family']);
		exit;
	}

	// Optional: allow focusing by person or couple key via query
	$rootPersonId = isset($_GET['root_person']) ? (int)$_GET['root_person'] : 0;
	$coupleKey = isset($_GET['couple']) ? trim((string)$_GET['couple']) : '';

	// Fetch persons in scope
	$people = [];
	$ps = $pdo->prepare('SELECT id, full_name, gender, is_alive, birth_date, death_date, email FROM persons WHERE family_id = ?');
	$ps->execute([$familyId]);
	while ($r = $ps->fetch()) {
		$pid = (int)$r['id'];
		$people[$pid] = [
			'id' => $pid,
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

	if (empty($people)) {
		echo json_encode(['ok'=>true,'family_id'=>$familyId,'people'=>new stdClass(),'roots'=>[],'edges'=>[],'parent_pairs'=>new stdClass(),'co_parents'=>new stdClass(),'couples'=>[]]);
		exit;
	}

	// Prepare containers used by both unions and relationships
	$parentPairs = [];
	$coParents = [];

	// Relationships → edges, children/parents maps
	$rels = $pdo->prepare('SELECT parent_id, child_id, relationship_type FROM relationships WHERE family_id = ?');
	$rels->execute([$familyId]);
	$edges = [];
	$childParents = [];
	while ($r = $rels->fetch()) {
		$p = (int)$r['parent_id'];
		$c = (int)$r['child_id'];
		if (!isset($people[$p]) || !isset($people[$c])) continue;
		$people[$p]['children'][] = $c;
		$people[$c]['parents'][] = $p;
		$edges[] = ['parent'=>$p,'child'=>$c,'type'=>$r['relationship_type']];
		if (!isset($childParents[$c])) $childParents[$c] = [];
		if (!in_array($p, $childParents[$c], true)) $childParents[$c][] = $p;
	}

	// Unions → current spouse map (for convenience) and ensure childless couples included
	$currentSpouse = [];
	$unionMeta = [];// key => ['type'=>..., 'current'=>bool]
	$us = $pdo->prepare('SELECT person1_id, person2_id, is_current, union_type FROM unions WHERE family_id = ?');
	$us->execute([$familyId]);
	while ($u = $us->fetch()) {
		$a = (int)$u['person1_id'];
		$b = (int)$u['person2_id'];
		if (!isset($people[$a]) || !isset($people[$b])) continue;
		if ((int)$u['is_current'] === 1) {
			$currentSpouse[$a] = $b;
			$currentSpouse[$b] = $a;
		}
		// Include union pair into parent_pairs even if no shared child
		$min = min($a,$b); $max = max($a,$b);
		$pairKey = $min.'-'.$max;
		if (!isset($parentPairs[$pairKey])) {
			$parentPairs[$pairKey] = ['a'=>$min,'b'=>$max,'children'=>[]];
		}
		$unionMeta[$pairKey] = [
			'type' => $u['union_type'] ?? 'marriage',
			'current' => ((int)$u['is_current'] === 1)
		];
	}
	foreach ($currentSpouse as $pid=>$sid) {
		if (isset($people[$pid])) $people[$pid]['current_spouse_id'] = $sid;
	}

	// Build parent_pairs and co_parents (child → two parents; person → co-parents)
	foreach ($childParents as $childId => $parents) {
		$parents = array_values(array_unique(array_filter($parents, fn($x)=>$x>0)));
		if (count($parents) >= 2) {
			$a = (int)$parents[0];
			$b = (int)$parents[1];
			if ($a === $b) continue;
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

	// Couples list derived from parent pairs (b>0)
	$couples = [];
	foreach ($parentPairs as $k=>$v) {
		if (($v['b'] ?? 0) > 0) {
			$meta = $unionMeta[$k] ?? null;
			$couples[] = [
				'key'=>$k,
				'a'=>$v['a'],
				'b'=>$v['b'],
				'children'=>$v['children'],
				'type'=>$meta['type'] ?? null,
				'current'=>$meta['current'] ?? null
			];
		}
	}

	// Roots at person level (no parents)
	$roots = [];
	foreach ($people as $pid => $p) {
		if (empty($p['parents'])) $roots[] = $pid;
	}

	echo json_encode([
		'ok'=>true,
		'family_id'=>$familyId,
		'people'=>$people,
		'roots'=>$roots,
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
