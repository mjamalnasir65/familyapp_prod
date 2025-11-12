<?php
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../config/config.php';

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

$personId = (int)($_POST['person_id'] ?? 0);
if (!$personId) { echo json_encode(['ok'=>false,'error'=>'missing_person_id']); exit; }

if (!isset($_FILES['photo']) || !is_uploaded_file($_FILES['photo']['tmp_name'])) {
    echo json_encode(['ok'=>false,'error'=>'no_file']);
    exit;
}

$file = $_FILES['photo'];
$maxSize = 5 * 1024 * 1024; // 5MB
$allowedExt = ['jpg','jpeg','png','gif','webp'];
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($ext, $allowedExt, true)) { echo json_encode(['ok'=>false,'error'=>'unsupported_type']); exit; }
if ((int)$file['size'] > $maxSize) { echo json_encode(['ok'=>false,'error'=>'too_large']); exit; }

try {
    $pdo = Database::getInstance()->getConnection();

    // Resolve user's family
    $u = $pdo->prepare('SELECT families_id FROM users WHERE id = ? LIMIT 1');
    $u->execute([ (int)$_SESSION['user_id'] ]);
    $familyIdUser = (int)($u->fetchColumn() ?: 0);
    if (!$familyIdUser) { echo json_encode(['ok'=>false,'error'=>'no_family']); exit; }

    // Ensure person belongs to same family
    $ps = $pdo->prepare('SELECT id, family_id FROM persons WHERE id = ? LIMIT 1');
    $ps->execute([$personId]);
    $row = $ps->fetch();
    if (!$row) { echo json_encode(['ok'=>false,'error'=>'not_found']); exit; }
    if ((int)$row['family_id'] !== $familyIdUser) { http_response_code(403); echo json_encode(['ok'=>false,'error'=>'forbidden_family']); exit; }

    // Prepare target directory under public folder
    // Base directory is project root (no public/ prefix in deployment)
    $baseDir = realpath(__DIR__ . '/..');
    $relDir = '/uploads/person_photos/' . $familyIdUser;
    $targetDir = $baseDir . $relDir;
    if (!is_dir($targetDir)) {
            if (!mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
                throw new \RuntimeException('mkdir_failed');
        }
    }

    // Generate unique filename
    $name = bin2hex(random_bytes(8)) . '.' . $ext;
    $dest = $targetDir . DIRECTORY_SEPARATOR . $name;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
            throw new \RuntimeException('move_failed');
    }

    // Build URL
    // Public URL (root-relative)
    $url = $relDir . '/' . $name;

    // Update person record (photo_site)
    $up = $pdo->prepare('UPDATE persons SET photo_site = ?, updated_at = NOW() WHERE id = ? AND family_id = ?');
    $up->execute([$url, $personId, $familyIdUser]);

    echo json_encode(['ok'=>true,'url'=>$url]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'server_error']);
}

?>
