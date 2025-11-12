<?php
/**
 * ActionLogger
 * Lightweight dual-channel logger: writes to DB table `action_logs` and to daily JSONL file under public/logs.
 * Usage:
 *   ActionLogger::log('person_update', [ 'person_id' => 123, 'changes' => ['full_name'=>'New'] ]);
 */
class ActionLogger {
    public static function log(string $action, array $details = [], ?int $userId = null): void {
        try {
            if (session_status() === PHP_SESSION_NONE) { session_start(); }
            $userId = $userId ?? (int)($_SESSION['user_id'] ?? 0);
            $sessionId = session_id();
            $ip = $_SERVER['REMOTE_ADDR'] ?? null;
            $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;

            // Normalize details to not exceed typical row limits; shallow copy only
            $normalized = self::sanitizeDetails($details);

            // DB write (best-effort)
            try {
                $pdo = Database::getInstance()->getConnection();
                $stmt = $pdo->prepare('INSERT INTO action_logs (user_id, session_id, action, details, ip_address, user_agent) VALUES (?,?,?,?,?,?)');
                $stmt->execute([
                    $userId ?: null,
                    $sessionId,
                    $action,
                    json_encode($normalized, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
                    $ip,
                    $ua
                ]);
            } catch (\Throwable $e) {
                // Fail silently; still attempt file log
            }

            // File log (append JSON line)
            self::writeFileLine([
                'ts' => gmdate('c'),
                'user_id' => $userId ?: null,
                'session_id' => $sessionId,
                'action' => $action,
                'details' => $normalized,
                'ip' => $ip,
                'ua' => $ua
            ]);
        } catch (\Throwable $e) {
            // Absolute silence (do not break main flow)
        }
    }

    private static function sanitizeDetails(array $details): array {
        // Ensure scalar or shallow arrays only (avoid huge nested dumps)
        $out = [];
        foreach ($details as $k => $v) {
            if (is_scalar($v) || $v === null) {
                $out[$k] = $v;
            } elseif (is_array($v)) {
                // Limit depth: keep only first level
                $sub = [];
                $i = 0;
                foreach ($v as $sk => $sv) {
                    if ($i++ > 25) { break; }
                    $sub[$sk] = is_scalar($sv) || $sv === null ? $sv : (is_array($sv) ? '[array]' : '[object]');
                }
                $out[$k] = $sub;
            } elseif (is_object($v)) {
                $out[$k] = '[object:' . get_class($v) . ']';
            } else {
                $out[$k] = '[type]';
            }
            if (strlen($k) > 64) { /* skip overly long keys */ }
        }
        return $out;
    }

    private static function writeFileLine(array $row): void {
    // Use project root logs directory (public/ not present in deployment)
    $dir = dirname(__DIR__) . '/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $file = $dir . '/actions-' . gmdate('Y-m-d') . '.jsonl';
        $line = json_encode($row, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) . "\n";
        // Use file locking to reduce race issues
        $fp = @fopen($file, 'ab');
        if ($fp) {
            @flock($fp, LOCK_EX);
            @fwrite($fp, $line);
            @flock($fp, LOCK_UN);
            @fclose($fp);
        }
    }
}
?>