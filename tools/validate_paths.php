<?php
// Path validation script for NASAB Family Tree
// Usage: php tools/validate_paths.php (CLI) or hit via browser (admin only)

header('Content-Type: text/plain; charset=utf-8');

$host = isset($_SERVER['HTTP_HOST']) ? ('https://' . $_SERVER['HTTP_HOST']) : 'https://nasab.hdsite.com.my';
// Allow CLI override: php tools/validate_paths.php https://example.com
if (PHP_SAPI === 'cli' && isset($argv[1])) {
    $host = rtrim($argv[1], '/');
}

$paths = [
    '/',
    '/pages/en/index.html',
    '/pages/my/index.html',
    '/pages/en/dashboard.html',
    '/pages/en/chatexpand_siblings.html',
    '/pages/en/chatexpand_partners.html',
    '/pages/en/chatexpand_children.html',
    '/pages/my/chatexpand_siblings.html',
    '/api/session_info.php',
    '/api/family_tree.php',
    '/manifest.webmanifest',
];

function check($url){
    // Prefer curl to follow redirects
    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_NOBODY => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 8,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $final = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        curl_close($ch);
        return [$url, $code, $final];
    }
    // Fallback: get_headers (first status only)
    $headers = @get_headers($url);
    $statusLine = $headers ? $headers[0] : 'NO RESPONSE';
    preg_match('/\s(\d{3})\s/', $statusLine, $m);
    $code = isset($m[1]) ? (int)$m[1] : 0;
    return [$url, $code, $url];
}

printf("NASAB Path Validation (%s) Host: %s\n\n", date('Y-m-d H:i:s'), $host);
$allowed = [200, 301, 302];
$results = [];
foreach ($paths as $p) {
    [$orig, $code, $final] = check($host . $p);
    $statusLabel = in_array($code, $allowed) ? 'OK' : 'FAIL';
    $redir = ($final !== $orig) ? ' → ' . $final : '';
    printf("%-45s %3d %-4s%s\n", $p, $code, $statusLabel, $redir);
    $results[] = [$p, $code, $final];
}

$failures = array_filter($results, function($r) use ($allowed){ return !in_array($r[1], $allowed); });

echo "\nSummary:\n";
if ($failures) {
    foreach ($failures as $f) {
        printf("FAIL %-40s code=%d final=%s\n", $f[0], $f[1], $f[2]);
    }
    if (!headers_sent()) http_response_code(500);
} else {
    echo "All paths acceptable (codes: " . implode(',', $allowed) . ").\n";
}
