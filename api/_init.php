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

// ---------------------------------------------------------
// Comprehensive request instrumentation (logAction wrapper)
// ---------------------------------------------------------
$__logStart = microtime(true);
$__script   = basename($_SERVER['SCRIPT_NAME'] ?? 'unknown');
$__path     = $_SERVER['REQUEST_URI'] ?? '';
$__method   = $_SERVER['REQUEST_METHOD'] ?? 'CLI';
$__ip       = $_SERVER['REMOTE_ADDR'] ?? null;
$__ua       = $_SERVER['HTTP_USER_AGENT'] ?? null;

// Load ActionLogger
$__al = __DIR__ . '/../classes/ActionLogger.php';
if (is_file($__al)) { require_once $__al; }

// Defaults if not set in config
if (!defined('LOG_ACTION_ENABLED')) {
    define('LOG_ACTION_ENABLED', true); // master switch
}
if (!defined('LOG_ACTION_VERBOSE')) {
    define('LOG_ACTION_VERBOSE', false); // enable tick/function tracing (expensive)
}

if (!function_exists('logAction')) {
    function logAction(string $event, array $data = []): void {
        if (!defined('LOG_ACTION_ENABLED') || !LOG_ACTION_ENABLED) { return; }
        try {
            ActionLogger::log($event, $data);
        } catch (\Throwable $e) {
            // swallow
        }
    }
}

// Log request start
logAction('request.start', [
    'script' => $__script,
    'path'   => $__path,
    'method' => $__method,
    'ip'     => $__ip,
    'ua_hash'=> $__ua ? substr(sha1($__ua),0,12) : null,
    'query'  => $_GET ? array_keys($_GET) : [],
    'post_keys' => $_POST ? array_keys($_POST) : [],
]);

// Error handler (non‑fatal warnings / notices)
set_error_handler(function($errno, $errstr, $errfile, $errline){
    // Map level
    $levels = [
        E_ERROR=>'E_ERROR', E_WARNING=>'E_WARNING', E_PARSE=>'E_PARSE', E_NOTICE=>'E_NOTICE',
        E_CORE_ERROR=>'E_CORE_ERROR', E_CORE_WARNING=>'E_CORE_WARNING', E_COMPILE_ERROR=>'E_COMPILE_ERROR',
        E_COMPILE_WARNING=>'E_COMPILE_WARNING', E_USER_ERROR=>'E_USER_ERROR', E_USER_WARNING=>'E_USER_WARNING',
        E_USER_NOTICE=>'E_USER_NOTICE', E_STRICT=>'E_STRICT', E_RECOVERABLE_ERROR=>'E_RECOVERABLE_ERROR',
        E_DEPRECATED=>'E_DEPRECATED', E_USER_DEPRECATED=>'E_USER_DEPRECATED'
    ];
    logAction('php.error', [
        'level' => $levels[$errno] ?? $errno,
        'msg'   => substr($errstr,0,300),
        'file'  => $errfile,
        'line'  => $errline,
    ]);
    // Do not prevent default error handling for fatal levels
    return false;
});

// Shutdown hook (captures fatal + duration)
register_shutdown_function(function() use ($__logStart, $__script, $__path, $__method) {
    $durationMs = round((microtime(true)-$__logStart)*1000,2);
    $status     = http_response_code();
    $fatal      = error_get_last();
    $mem        = round(memory_get_peak_usage(true)/1024/1024,2);
    logAction('request.end', [
        'script'   => $__script,
        'path'     => $__path,
        'method'   => $__method,
        'status'   => $status,
        'duration_ms' => $durationMs,
        'memory_mb'   => $mem,
    ]);
    if ($fatal && in_array($fatal['type'], [E_ERROR,E_PARSE,E_CORE_ERROR,E_COMPILE_ERROR], true)) {
        logAction('php.fatal', [
            'type' => $fatal['type'],
            'msg'  => substr($fatal['message'],0,300),
            'file' => $fatal['file'],
            'line' => $fatal['line'],
        ]);
    }
});

// Verbose per-function tracing intentionally disabled by default (performance). Set LOG_ACTION_VERBOSE true and
// implement a profiler script if granular function call logging is required.

// Small helper to emit JSON errors consistently
if (!function_exists('api_error')) {
    function api_error(int $status, string $code, array $extra = []): void {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode(array_merge(['ok'=>false, 'error'=>$code], $extra));
        exit;
    }
}
