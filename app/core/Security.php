<?php
// Sprint 14 - Security helpers for rate limiting, lockout, and event logging.

class Security {
    private static function db(): PDO {
        return Database::getInstance()->getConnection();
    }

    public static function clientIp(): string {
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return substr(trim($ips[0]), 0, 45);
        }
        return substr($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0', 0, 45);
    }

    public static function userAgent(): string {
        return substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
    }

    public static function setting(string $key, $default) {
        try {
            $stmt = self::db()->prepare('SELECT setting_value FROM settings WHERE setting_key = :key');
            $stmt->execute([':key' => $key]);
            $value = $stmt->fetchColumn();
            return $value === false ? $default : $value;
        } catch (Exception $e) {
            return $default;
        }
    }

    public static function logEvent(string $type, string $severity = 'info', ?int $userId = null, array $context = []): void {
        try {
            $stmt = self::db()->prepare('INSERT INTO security_events
                    (user_id, event_type, severity, ip_address, user_agent, context_json)
                 VALUES (:uid, :type, :severity, :ip, :ua, :context)');
            $stmt->execute([
                ':uid' => $userId,
                ':type' => substr($type, 0, 80),
                ':severity' => in_array($severity, ['info', 'warning', 'critical'], true) ? $severity : 'info',
                ':ip' => self::clientIp(),
                ':ua' => self::userAgent(),
                ':context' => $context ? json_encode($context) : null,
            ]);
        } catch (Exception $e) {
            // Security event logging is best-effort during migrations.
        }
    }

    public static function rateLimit(string $bucket, int $limit, int $windowSeconds): bool {
        $limit = max(1, $limit);
        $windowSeconds = max(1, $windowSeconds);
        $key = substr(hash('sha256', $bucket), 0, 64);

        try {
            $db = self::db();
            $db->beginTransaction();
            $stmt = $db->prepare('SELECT hit_count, expires_at FROM rate_limits WHERE bucket_key = :key FOR UPDATE');
            $stmt->execute([':key' => $key]);
            $row = $stmt->fetch(PDO::FETCH_OBJ);
            $now = time();

            if (!$row || strtotime($row->expires_at) <= $now) {
                $expiresAt = date('Y-m-d H:i:s', $now + $windowSeconds);
                $insert = $db->prepare('INSERT INTO rate_limits (bucket_key, hit_count, window_start, expires_at)
                    VALUES (:key, 1, NOW(), :expires_at)
                    ON DUPLICATE KEY UPDATE hit_count = 1, window_start = NOW(), expires_at = :expires_at2');
                $insert->bindValue(':key', $key);
                $insert->bindValue(':expires_at', $expiresAt);
                $insert->bindValue(':expires_at2', $expiresAt);
                $insert->execute();
                $db->commit();
                return true;
            }

            $count = (int) $row->hit_count + 1;
            $update = $db->prepare('UPDATE rate_limits SET hit_count = :count WHERE bucket_key = :key');
            $update->execute([':count' => $count, ':key' => $key]);
            $db->commit();

            if ($count > $limit) {
                self::logEvent('rate_limit_exceeded', 'warning', $_SESSION['user_id'] ?? null, ['bucket' => $bucket]);
                return false;
            }
            return true;
        } catch (Exception $e) {
            if (isset($db) && $db->inTransaction()) {
                $db->rollBack();
            }
            return true;
        }
    }

    public static function recordLoginAttempt(string $email, bool $success): void {
        try {
            $cleanEmail = strtolower(trim($email));
            if ($success) {
                $delStmt = self::db()->prepare('DELETE FROM login_attempts WHERE email = :email');
                $delStmt->execute([':email' => $cleanEmail]);
            }
            $stmt = self::db()->prepare('INSERT INTO login_attempts (email, ip_address, user_agent, success)
                VALUES (:email, :ip, :ua, :success)');
            $stmt->execute([
                ':email' => $cleanEmail,
                ':ip' => self::clientIp(),
                ':ua' => self::userAgent(),
                ':success' => $success ? 1 : 0,
            ]);
        } catch (Exception $e) {
            // Best-effort before migrations are applied.
        }
    }

    public static function loginLocked(string $email): bool {
        $max = (int) self::setting('auth.max_failed_attempts', 15);
        $minutes = (int) self::setting('auth.lockout_minutes', 15);
        try {
            $cutoff = date('Y-m-d H:i:s', time() - (max(1, $minutes) * 60));
            $stmt = self::db()->prepare('SELECT COUNT(*) FROM login_attempts
                WHERE success = 0
                  AND email = :email
                  AND attempted_at >= :cutoff');
            $stmt->bindValue(':email', strtolower(trim($email)));
            $stmt->bindValue(':cutoff', $cutoff);
            $stmt->execute();
            return (int) $stmt->fetchColumn() >= max(1, $max);
        } catch (Exception $e) {
            return false;
        }
    }
}
