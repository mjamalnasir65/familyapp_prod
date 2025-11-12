<?php
// Simple mail send test using the project's Mailer helper.
// Usage (via browser): http://localhost/familyapp/tests/php-mail-test.php?to=someone@example.com
// Or leave ?to= to send to SMTP_USER by default. Requires vendor/ with PHPMailer.

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Mailer.php';

header('Content-Type: application/json');

$to = isset($_GET['to']) && $_GET['to'] !== '' ? $_GET['to'] : (defined('SMTP_USER') ? SMTP_USER : '');
if (!$to) {
    echo json_encode(['ok' => false, 'error' => 'no_recipient']);
    exit;
}

// Use language-aware path (default EN). For manual testing you can append &lang=MY.
$lang = strtoupper(trim((string)($_GET['lang'] ?? 'EN')));
if (!in_array($lang, ['EN','MY'], true)) { $lang = 'EN'; }
$acceptUrl = rtrim(BASE_URL,'/') . '/pages/' . $lang . '/accept_invite.html?token=TEST_TOKEN';
$result = Mailer::sendInvite($to, $acceptUrl, 'Test email from Family App mail test.', [
    'family_name' => 'Mail Test',
    'role' => 'viewer',
    'scope' => 'family',
]);

echo json_encode([
    'ok' => $result['ok'] ?? false,
    'result' => $result,
    'config' => [
        'host' => SMTP_HOST,
        'port' => SMTP_PORT,
        'secure' => SMTP_SECURE,
        'user' => SMTP_USER,
        'from' => SMTP_FROM_EMAIL,
    ],
]);
