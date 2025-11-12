<?php
// Adjusted include paths for new public/ docroot structure
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';
header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    echo json_encode(['ok'=>false]);
    exit;
}

$uid = (int)$_SESSION['user_id'];
$email = $_SESSION['user_email'] ?? null;
$sessionName = $_SESSION['user_name'] ?? null; // often already the full_name
$fullName = null;

try {
    $pdo = Database::getInstance()->getConnection();
    $st = $pdo->prepare('SELECT full_name, email, pwa_admin FROM users WHERE id = ? LIMIT 1');
    $st->execute([$uid]);
    if ($row = $st->fetch()) {
    $fullName = $row['full_name'] ?? null;
        // Prefer DB email if present
        if (!empty($row['email'])) { $email = $row['email']; }
    $isAdmin = !empty($row['pwa_admin']);
    }
} catch (Throwable $e) {
    // Fail silently and fall back to session values
}

// Back-compat: keep "name" while also exposing "full_name"
$nameOut = $fullName ?: $sessionName;

echo json_encode([
    'ok' => true,
    'user' => [
        'id' => $uid,
        'email' => $email,
        'name' => $nameOut,
        'full_name' => $fullName ?: $sessionName,
        'is_admin' => isset($isAdmin) ? (bool)$isAdmin : false,
    ]
]);
