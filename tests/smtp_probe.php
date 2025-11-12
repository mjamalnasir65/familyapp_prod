<?php
// Lightweight SMTP connectivity probe WITHOUT password/auth by default.
// Does not depend on PHPMailer internals; uses raw socket to test banner + EHLO + optional STARTTLS.
// Usage examples:
//   /familyapp/tests/smtp_probe.php
//   /familyapp/tests/smtp_probe.php?host=mail.hdsite.com.my&port=465&secure=ssl
//   /familyapp/tests/smtp_probe.php?host=mail.hdsite.com.my&port=587&secure=tls
//   /familyapp/tests/smtp_probe.php?auth=1  (attempt AUTH LOGIN without password – will fail, for diagnostics)

header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';

$host   = isset($_GET['host']) ? $_GET['host'] : (defined('SMTP_HOST') ? SMTP_HOST : 'localhost');
$port   = (int)($_GET['port'] ?? (defined('SMTP_PORT') ? SMTP_PORT : 25));
$secure = isset($_GET['secure']) ? strtolower($_GET['secure']) : (defined('SMTP_SECURE') ? strtolower(SMTP_SECURE) : ''); // '', ssl, tls
$tryAuth = !empty($_GET['auth']); // will send AUTH LOGIN sequence without password

$result = [
  'host' => $host,
  'port' => $port,
  'secure' => $secure,
  'auth_attempted' => $tryAuth,
  'ok' => false,
];

function readLine($fp, $timeout = 5) {
  stream_set_timeout($fp, $timeout);
  $line = fgets($fp, 512);
  return $line === false ? null : rtrim($line, "\r\n");
}

$start = microtime(true);
$context = stream_context_create(['ssl' => [
  'verify_peer' => false,
  'verify_peer_name' => false,
]]);
$prefix = ($secure === 'ssl') ? 'ssl://' : '';
$fp = @stream_socket_client($prefix.$host.':'.$port, $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $context);
if (!$fp) {
  $result['error'] = 'connect_failed';
  $result['errno'] = $errno;
  $result['errstr'] = $errstr;
  $result['ms'] = (int)((microtime(true)-$start)*1000);
  echo json_encode($result); exit;
}
$banner = readLine($fp);
$result['banner'] = $banner;

// Send EHLO
fwrite($fp, "EHLO localhost\r\n");
$ehloLines = [];
while (($line = readLine($fp)) !== null) {
  $ehloLines[] = $line;
  if (!preg_match('/^\d{3}-/', $line)) { // last line when no hyphen after code
    if (preg_match('/^\d{3} /', $line)) break;
  }
}
$result['ehlo'] = $ehloLines;

// STARTTLS if requested and not implicit SSL
if ($secure === 'tls') {
  fwrite($fp, "STARTTLS\r\n");
  $starttlsResp = readLine($fp);
  $result['starttls_response'] = $starttlsResp;
  if ($starttlsResp && strpos($starttlsResp, '220') === 0) {
    $cryptoOk = @stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
    $result['tls_negotiated'] = (bool)$cryptoOk;
    if ($cryptoOk) {
      // Re-issue EHLO after TLS
      fwrite($fp, "EHLO localhost\r\n");
      $ehloTls = [];
      while (($line = readLine($fp)) !== null) {
        $ehloTls[] = $line;
        if (!preg_match('/^\d{3}-/', $line)) {
          if (preg_match('/^\d{3} /', $line)) break;
        }
      }
      $result['ehlo_after_tls'] = $ehloTls;
    }
  }
}

// Optional AUTH LOGIN attempt (without password — diagnostic only)
if ($tryAuth) {
  fwrite($fp, "AUTH LOGIN\r\n");
  $auth1 = readLine($fp);
  $result['auth_initial'] = $auth1;
  if ($auth1 && preg_match('/^334/', $auth1)) {
    // Server asks for username (base64). Provide base64 of SMTP_USER or placeholder.
    $user = defined('SMTP_USER') ? SMTP_USER : 'user@example.com';
    fwrite($fp, base64_encode($user)."\r\n");
    $auth2 = readLine($fp);
    $result['auth_user_respond'] = $auth2;
    if ($auth2 && preg_match('/^334/', $auth2)) {
      // Provide empty password intentionally
      fwrite($fp, "\r\n");
      $auth3 = readLine($fp);
      $result['auth_final'] = $auth3;
    }
  }
}

// QUIT politely
fwrite($fp, "QUIT\r\n");
$result['quit'] = readLine($fp);
fclose($fp);

$result['ok'] = true;
$result['ms'] = (int)((microtime(true)-$start)*1000);
echo json_encode($result);
