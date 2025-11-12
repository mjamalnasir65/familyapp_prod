<?php
// Production app config and DB credentials

// Session and error settings (production hardened)
// Secure session cookies
ini_set('session.cookie_httponly', '1');
ini_set('session.use_strict_mode', '1');
// Only set secure cookies when under HTTPS (many prod envs are HTTPS-only)
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    ini_set('session.cookie_secure', '1');
}
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
date_default_timezone_set('UTC');
// Do not display errors in production; log them via server/php.ini.
ini_set('display_errors', '0');
error_reporting(E_ALL);

// Security headers and helpers
require_once __DIR__ . '/security.php';
// TEMP: Relax security headers while testing SMTP (CSP report-only, no HSTS)
fa_set_security_headers(['enforce' => false, 'hsts' => false]);

// Database (production)
if (!defined('DB_HOST')) define('DB_HOST', getenv('DB_HOST') ?: '218.208.91.162');
if (!defined('DB_NAME')) define('DB_NAME', getenv('DB_NAME') ?: 'hdsiteco_familyapp');
if (!defined('DB_USER')) define('DB_USER', getenv('DB_USER') ?: 'hdsiteco_mjamalnasir');
// Prefer environment for DB_PASS, fall back to existing value if provided here.
if (!defined('DB_PASS')) define('DB_PASS', getenv('DB_PASS') ?: 'Aa@j4m4ln4s1r');
if (!defined('DB_CHARSET')) define('DB_CHARSET', 'utf8mb4');

// Optional: set DB_PORT if not default (e.g., 3307). Leave null for default driver port.
define('DB_PORT', null);

// Base URL (adjust if your virtual host differs)
if (!defined('BASE_URL')) {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $basePath = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php'), '/\\');
    define('BASE_URL', $scheme . '://' . $host . ($basePath ?: ''));
}

// Outbound email (optional SMTP). Prefer environment variables; fall back to sensible defaults.
// Provided secure settings (SSL/TLS Recommended):
//  - Incoming: IMAP 993 / POP3 995 (not used here)
//  - Outgoing SMTP: mail.hdsite.com.my port 465, SSL
//  - Username: mjamalnasir@hdsite.com.my
//  - Password: email account password (DO NOT commit it)
// Explicit SMTP settings for testing (bypass environment). Revert to env after validation.
if (!defined('SMTP_HOST')) define('SMTP_HOST', 'mail.hdsite.com.my');
if (!defined('SMTP_PORT')) define('SMTP_PORT', 465);
if (!defined('SMTP_USER')) define('SMTP_USER', 'nasabpwa@hdsite.com.my');
if (!defined('SMTP_PASS')) define('SMTP_PASS', 'Aa@j4m4ln4s1r');
if (!defined('SMTP_SECURE')) define('SMTP_SECURE', 'ssl'); // tls|ssl|''
if (!defined('SMTP_FROM_EMAIL')) define('SMTP_FROM_EMAIL', SMTP_USER);
if (!defined('SMTP_FROM_NAME')) define('SMTP_FROM_NAME', 'Family App');
// Enable SMTP debug during testing; set to 0 after it works
if (!defined('SMTP_DEBUG')) define('SMTP_DEBUG', 2);

// Optional local overrides (never commit secrets). Create config/config.local.php to override constants.
$__local_override = __DIR__ . '/config.local.php';
if (is_file($__local_override)) {
    require_once $__local_override;
}
