<?php
require_once __DIR__ . '/_init.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok'=>false,'error'=>'method_not_allowed']);
    exit;
}

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok'=>false,'error'=>'unauthorized']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) { echo json_encode(['ok'=>false,'error'=>'invalid_payload']); exit; }

$personId = (int)($data['id'] ?? $data['person_id'] ?? 0);
if (!$personId) { echo json_encode(['ok'=>false,'error'=>'missing_id']); exit; }

try {
    $pdo = Database::getInstance()->getConnection();

    // Resolve user's family
    $u = $pdo->prepare('SELECT families_id FROM users WHERE id = ? LIMIT 1');
    $u->execute([ (int)$_SESSION['user_id'] ]);
    $familyIdUser = (int)($u->fetchColumn() ?: 0);
    if (!$familyIdUser) { echo json_encode(['ok'=>false,'error'=>'no_family']); exit; }

    // Load person and ensure same family
    $ps = $pdo->prepare('SELECT id, family_id FROM persons WHERE id = ? LIMIT 1');
    $ps->execute([$personId]);
    $row = $ps->fetch();
    if (!$row) { echo json_encode(['ok'=>false,'error'=>'not_found']); exit; }
    if ((int)$row['family_id'] !== $familyIdUser) { http_response_code(403); echo json_encode(['ok'=>false,'error'=>'forbidden_family']); exit; }

    // Collect allowed fields
    $allowedGenders = ['male','female','other','prefer_not_to_say'];
    $fields = [];
    $values = [];

    if (isset($data['full_name'])) { $fields[] = 'full_name = ?'; $values[] = trim((string)$data['full_name']); }
    if (isset($data['gender'])) {
        $g = in_array($data['gender'], $allowedGenders, true) ? $data['gender'] : 'other';
        $fields[] = 'gender = ?'; $values[] = $g;
    }
    if (array_key_exists('is_alive', $data)) {
        $alive = (int)!!$data['is_alive'];
        $fields[] = 'is_alive = ?'; $values[] = $alive;
        if ($alive === 1) {
            // If living, clear death-related fields
            $fields[] = 'death_date = NULL';
            $fields[] = 'burial_place = NULL';
        }
    }
    // Date validation helper: accept year-only (YYYY) or full (YYYY-MM-DD)
    $isYearOrISO = function($s){ return is_string($s) && preg_match('/^\d{4}(-\d{2}(-\d{2})?)?$/', $s) === 1; };
    if (array_key_exists('birth_date', $data)) {
        $bd = $data['birth_date'];
        if ($bd !== null && $bd !== '' && !$isYearOrISO($bd)) { echo json_encode(['ok'=>false,'error'=>'invalid_date_format_birth','expected'=>'YYYY or YYYY-MM-DD']); exit; }
        $fields[] = 'birth_date = ?'; $values[] = ($bd === '' ? null : $bd);
    }
    if (isset($data['birth_place'])) { $fields[] = 'birth_place = ?'; $values[] = (string)$data['birth_place'] ?: null; }
    if (array_key_exists('death_date', $data)) {
        $dd = $data['death_date'];
        if ($dd !== null && $dd !== '' && !$isYearOrISO($dd)) { echo json_encode(['ok'=>false,'error'=>'invalid_date_format_death','expected'=>'YYYY or YYYY-MM-DD']); exit; }
        $fields[] = 'death_date = ?'; $values[] = ($dd === '' ? null : $dd);
    }
    if (isset($data['burial_place'])) { $fields[] = 'burial_place = ?'; $values[] = (string)$data['burial_place'] ?: null; }
    if (isset($data['profession'])) { $fields[] = 'profession = ?'; $values[] = (string)$data['profession'] ?: null; }
    if (isset($data['email'])) { $fields[] = 'email = ?'; $values[] = (string)$data['email'] ?: null; }
    if (isset($data['mobile'])) { $fields[] = 'mobile = ?'; $values[] = (string)$data['mobile'] ?: null; }
    if (isset($data['fb_link'])) { $fields[] = 'fb_link = ?'; $values[] = (string)$data['fb_link'] ?: null; }
    if (isset($data['address'])) { $fields[] = 'address = ?'; $values[] = (string)$data['address'] ?: null; }
    if (isset($data['photo_data'])) { $fields[] = 'photo_data = ?'; $values[] = (string)$data['photo_data'] ?: null; }
    if (isset($data['photo_site'])) { $fields[] = 'photo_site = ?'; $values[] = (string)$data['photo_site'] ?: null; }

    if (!$fields) { echo json_encode(['ok'=>true,'id'=>$personId,'no_changes'=>true]); exit; }

    $sql = 'UPDATE persons SET '.implode(', ', $fields).', updated_at = NOW() WHERE id = ? AND family_id = ?';
    $values[] = $personId; $values[] = $familyIdUser;
    $st = $pdo->prepare($sql);
    $st->execute($values);

    echo json_encode(['ok'=>true,'id'=>$personId]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'server_error']);
}

?>