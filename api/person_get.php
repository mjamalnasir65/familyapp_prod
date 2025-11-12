<?php
require_once __DIR__ . '/_init.php';
require_once __DIR__ . '/../classes/ActionLogger.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['ok'=>false,'error'=>'method_not_allowed']);
    exit;
}

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok'=>false,'error'=>'unauthorized']);
    exit;
}

$mode = $_GET['mode'] ?? null;
$personId = (int)($_GET['id'] ?? $_GET['person_id'] ?? 0);

try {
    $pdo = Database::getInstance()->getConnection();
    // Resolve user's family
    $u = $pdo->prepare('SELECT families_id, email FROM users WHERE id = ? LIMIT 1');
    $u->execute([ (int)$_SESSION['user_id'] ]);
    $userRow = $u->fetch();
    $familyIdUser = (int)($userRow['families_id'] ?? 0);
    $userEmail = $userRow['email'] ?? ($_SESSION['user_email'] ?? null);
    if (!$familyIdUser) { echo json_encode(['ok'=>false,'error'=>'no_family']); exit; }

    // Self mode: discover the logged-in user's person id and short-circuit
    if (!$personId && $mode === 'self') {
        // First look for cached session value
        $cached = $_SESSION['self_person_id'] ?? null;
        if ($cached) { $personId = (int)$cached; }
        if (!$personId && $userEmail) {
            $psSelf = $pdo->prepare('SELECT id FROM persons WHERE family_id = ? AND email = ? LIMIT 1');
            $psSelf->execute([$familyIdUser, $userEmail]);
            if ($rSelf = $psSelf->fetch()) {
                $personId = (int)$rSelf['id'];
                $_SESSION['self_person_id'] = $personId; // cache for later
            }
        }
        echo json_encode(['ok'=> (bool)$personId, 'person_id'=>$personId ?: null]);
        exit;
    }

    if (!$personId) { echo json_encode(['ok'=>false,'error'=>'missing_id']); exit; }

    // Load person and ensure same family
    $ps = $pdo->prepare('SELECT * FROM persons WHERE id = ? LIMIT 1');
    $ps->execute([$personId]);
    $row = $ps->fetch();
    if (!$row) { echo json_encode(['ok'=>false,'error'=>'not_found']); exit; }
    if ((int)$row['family_id'] !== $familyIdUser) { http_response_code(403); echo json_encode(['ok'=>false,'error'=>'forbidden_family']); exit; }

    // Map fields from DB
    $person = [
        'id' => (int)$row['id'],
        'full_name' => $row['full_name'],
        'gender' => $row['gender'],
        'is_alive' => (int)$row['is_alive'] === 1,
        'birth_date' => $row['birth_date'],
        'birth_place' => $row['birth_place'],
        'death_date' => $row['death_date'],
        'burial_place' => $row['burial_place'],
        'profession' => $row['profession'],
        'email' => $row['email'],
        'mobile' => $row['mobile'],
        'fb_link' => $row['fb_link'],
        'address' => $row['address'],
        'photo_data' => $row['photo_data'],
        'photo_site' => $row['photo_site'] ?? null,
        'updated_at' => $row['updated_at'],
    ];

    echo json_encode(['ok'=>true,'person'=>$person]);
    // Best-effort log
    ActionLogger::log('person_view', ['person_id'=>$personId]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'server_error']);
}

?>