<?php
// Common API bootstrap for new public/ structure
// Loads config and Database class with simple fallbacks.

$__config = __DIR__ . '/../config/config.php';
if (is_file($__config)) {
    require_once $__config;
} else {
    // Legacy fallback if deployed differently
    $alt = dirname(__DIR__, 2) . '/config/config.php';
    if (is_file($alt)) { require_once $alt; }
}

$__db = __DIR__ . '/../classes/Database.php';
if (is_file($__db)) {
    require_once $__db;
} else {
    $altDb = dirname(__DIR__, 2) . '/classes/Database.php';
    if (is_file($altDb)) { require_once $altDb; }
}

// Small helper to emit JSON errors consistently
if (!function_exists('api_error')) {
    function api_error(int $status, string $code, array $extra = []): void {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode(array_merge(['ok'=>false, 'error'=>$code], $extra));
        exit;
    }
}
