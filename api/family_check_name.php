<?php
// Top-level error handler to ensure JSON response even on fatal errors
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        if (!headers_sent()) {
            header('Content-Type: application/json');
            http_response_code(500);
        }
        echo json_encode(['ok'=>false,'error'=>'fatal_error','message'=>'Server configuration error']);
        exit;
    }
});

require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) session_start();
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

$name = trim($_POST['name'] ?? '');
if ($name === '') {
    echo json_encode(['ok'=>false,'error'=>'name_required']);
    exit;
}

try {
    $pdo = Database::getInstance()->getConnection();
    $stmt = $pdo->prepare('SELECT id FROM families WHERE name = ? LIMIT 1');
    $stmt->execute([$name]);
    $exists = (bool)$stmt->fetch();
    echo json_encode(['ok'=>true, 'unique'=>!$exists]);
} catch (Throwable $e) {
    http_response_code(500);
    // Optional debug toggle; suppress if constant not defined
    $debug = (defined('APP_DEBUG') && constant('APP_DEBUG')) ? $e->getMessage() : null;
    echo json_encode([
        'ok'=>false,
        'error'=>'server_error',
        'message'=>'Family check failed',
        'detail'=> $debug
    ]);
}
