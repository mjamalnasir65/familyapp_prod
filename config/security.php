<?php
// Security helpers copied from legacy config/security.php for new public/ structure.
// Keep synced with root config/security.php if both exist; prefer public copy when deployed.
if (!function_exists('fa_set_security_headers')) {
    /**
     * Send strict security headers suitable for production.
     * Options:
     *  - enforce (bool): true => send Content-Security-Policy, false => Report-Only. Defaults to true.
     *  - hsts (bool): send HSTS when HTTPS. Defaults to true.
     */
    function fa_set_security_headers(array $opts = []): void {
        static $sent = false; if ($sent) { return; } $sent = true;

        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: same-origin');
        header('Permissions-Policy: geolocation=(), camera=(), microphone=(), interest-cohort=()');
        header('Cross-Origin-Opener-Policy: same-origin');
        header('Cross-Origin-Resource-Policy: same-origin');

        $csp = "default-src 'self'; img-src 'self' data: blob:; media-src 'self' data:; font-src 'self' data: https://fonts.gstatic.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; script-src 'self' 'unsafe-inline'; connect-src 'self'; worker-src 'self'; object-src 'none'";
        $enforce = array_key_exists('enforce', $opts) ? (bool)$opts['enforce'] : (getenv('FA_CSP_ENFORCE') !== '0');
        if ($enforce) {
            header('Content-Security-Policy: ' . $csp);
        } else {
            header('Content-Security-Policy-Report-Only: ' . $csp);
        }

        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        $wantHsts = array_key_exists('hsts', $opts) ? (bool)$opts['hsts'] : true;
        if ($isHttps && $wantHsts) {
            header('Strict-Transport-Security: max-age=15552000; includeSubDomains; preload');
        }
    }
}
if (!function_exists('fa_h')) { function fa_h(?string $s): string { return htmlspecialchars($s ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); } }
if (!function_exists('fa_normalize_string')) { function fa_normalize_string($value, int $maxLen = 2000): string { $s = is_string($value) ? $value : (string)$value; $s = trim(preg_replace("/\r\n?|\n/", "\n", $s)); if ($maxLen > 0 && strlen($s) > $maxLen) { $s = substr($s, 0, $maxLen); } return $s; } }
if (!function_exists('fa_json_input')) { function fa_json_input(): array { $raw = file_get_contents('php://input'); if ($raw === false || $raw === '') { return []; } $data = json_decode($raw, true); return is_array($data) ? $data : []; } }
