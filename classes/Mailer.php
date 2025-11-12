<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailException;

class Mailer
{
    private static function loadVendor(): bool
    {
        $autoloads = [
            __DIR__ . '/../vendor/autoload.php',
            __DIR__ . '/../../vendor/autoload.php',
        ];
        foreach ($autoloads as $a) {
            if (is_file($a)) { require_once $a; return true; }
        }
        return false;
    }

    public static function sendInvite(string $toEmail, string $inviteUrl, string $message = '', array $context = []): array
    {
        // Attempt to load PHPMailer
        if (!self::loadVendor()) {
            return ['ok' => false, 'error' => 'vendor_missing'];
        }
        // Pull config
        $fromEmail = defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : 'no-reply@localhost';
        $fromName  = defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : 'Family App';

        $familyName = $context['family_name'] ?? 'Your Family';
        $role = $context['role'] ?? 'viewer';
        $scope = $context['scope'] ?? 'family';

        $html = '<p>You\'ve been invited to join <strong>' . htmlspecialchars($familyName) . "</strong> on Family App.</p>"
              . '<p>Role: <strong>' . htmlspecialchars($role) . '</strong> · Scope: <strong>' . htmlspecialchars($scope) . "</strong></p>";
        if ($message) {
            $html .= '<p style="margin-top:10px">Message from inviter:</p><blockquote style="margin:6px 0 14px;padding-left:10px;border-left:3px solid #ddd">' . nl2br(htmlspecialchars($message)) . '</blockquote>';
        }
    // Also include a login link with token for first-time users
    $loginUrl = preg_replace('~(/pages/[^/]+)/accept_invite\.html\?token=~i', '$1/auth/login.html?invite_token=', $inviteUrl);
    $html .= '<p><a href="' . htmlspecialchars($inviteUrl) . '" style="display:inline-block;background:#0ea5e9;color:#fff;padding:10px 14px;border-radius:8px;text-decoration:none">Accept invitation</a></p>';
    $html .= '<p style="margin-top:8px"><a href="' . htmlspecialchars($loginUrl) . '" style="color:#0284c7;text-decoration:underline">Having trouble? Sign in with invite code</a></p>';
        $text = "You have been invited to join {$familyName} on Family App.\nRole: {$role} · Scope: {$scope}\n\nAccept: {$inviteUrl}\n\n" . ($message ? ("Message:\n{$message}\n") : '');

        $mail = new PHPMailer(true);
        try {
            if (defined('SMTP_HOST') && SMTP_HOST) {
                $mail->isSMTP();
                $mail->Host = SMTP_HOST;
                $mail->Port = (int)(defined('SMTP_PORT') ? SMTP_PORT : 587);
                // Always authenticate when credentials provided
                $mail->SMTPAuth = (bool)(defined('SMTP_USER') ? SMTP_USER : '');
                if (!empty(SMTP_USER) || !empty(SMTP_PASS)) {
                    $mail->Username = (string)SMTP_USER;
                    $mail->Password = (string)SMTP_PASS;
                }
                // Map secure string to PHPMailer constants
                $secureCfg = (string)(defined('SMTP_SECURE') ? SMTP_SECURE : 'tls');
                if ($secureCfg === 'ssl' || $secureCfg === 'smtps') {
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                    $mail->SMTPAutoTLS = false; // using implicit TLS on 465
                } elseif ($secureCfg === 'tls' || $secureCfg === 'starttls') {
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->SMTPAutoTLS = true;
                } else {
                    // no encryption (not recommended)
                    $mail->SMTPSecure = '';
                    $mail->SMTPAutoTLS = false;
                }
                $debugVal = getenv('SMTP_DEBUG');
                if (defined('SMTP_DEBUG')) { $debugVal = constant('SMTP_DEBUG'); }
                if ($debugVal) {
                    $mail->SMTPDebug = (int)$debugVal; // 2 = client/server messages
                    $mail->Debugoutput = 'error_log';
                }
            }

            $mail->CharSet = 'UTF-8';
            $mail->setFrom($fromEmail, $fromName);
            $mail->addAddress($toEmail);
            $mail->Subject = 'Family App invitation';
            $mail->AltBody = $text;
            $mail->isHTML(true);
            $mail->Body    = '<div style="font-family:Segoe UI,Arial,sans-serif;color:#0f172a">' . $html . '</div>';

            try {
                $mail->send();
                return ['ok' => true];
            } catch (MailException $e) {
                $msg = $e->getMessage();
                // Retry once with STARTTLS:587 if first attempt was SMTPS:465 and auth failed
                $firstPort = (int)(defined('SMTP_PORT') ? SMTP_PORT : 587);
                $firstSecure = (string)(defined('SMTP_SECURE') ? SMTP_SECURE : 'tls');
                $isAuthErr = stripos($msg, 'authenticate') !== false;
                $usedImplicitTLS = ($firstSecure === 'ssl' || $firstSecure === 'smtps' || $firstPort === 465);
                if ($isAuthErr && $usedImplicitTLS) {
                    $m2 = new PHPMailer(true);
                    try {
                        $m2->isSMTP();
                        $m2->Host = SMTP_HOST;
                        $m2->Port = 587;
                        $m2->SMTPAuth = true;
                        $m2->Username = (string)SMTP_USER;
                        $m2->Password = (string)SMTP_PASS;
                        $m2->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                        $m2->SMTPAutoTLS = true;
                        $debugVal = getenv('SMTP_DEBUG');
                        if (defined('SMTP_DEBUG')) { $debugVal = constant('SMTP_DEBUG'); }
                        if ($debugVal) { $m2->SMTPDebug = (int)$debugVal; $m2->Debugoutput = 'error_log'; }

                        $m2->CharSet = 'UTF-8';
                        $m2->setFrom($fromEmail, $fromName);
                        $m2->addAddress($toEmail);
                        $m2->Subject = 'Family App invitation';
                        $m2->AltBody = $text;
                        $m2->isHTML(true);
                        $m2->Body = '<div style="font-family:Segoe UI,Arial,sans-serif;color:#0f172a">' . $html . '</div>';
                        $m2->send();
                        return ['ok' => true, 'fallback' => 'tls587'];
                    } catch (MailException $e2) {
                        return ['ok' => false, 'error' => $e2->getMessage()];
                    } catch (\Throwable $t2) {
                        return ['ok' => false, 'error' => $t2->getMessage()];
                    }
                }
                return ['ok' => false, 'error' => $msg];
            }
        } catch (MailException $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        } catch (\Throwable $t) {
            return ['ok' => false, 'error' => $t->getMessage()];
        }
    }
}
