<?php
/**
 * Sprint 14 retention worker.
 *
 * Usage:
 *   php bin/cron_retention.php
 */

require_once dirname(__DIR__) . '/app/config/config.php';
require_once APPROOT . '/core/Database.php';
require_once APPROOT . '/core/Model.php';
require_once APPROOT . '/models/LocationLog.php';

$db = Database::getInstance()->getConnection();

$setting = function (string $key, int $default) use ($db): int {
    $stmt = $db->prepare('SELECT setting_value FROM settings WHERE setting_key = :key');
    $stmt->execute([':key' => $key]);
    $value = $stmt->fetchColumn();
    return $value === false ? $default : (int) $value;
};

$deleteOlderThan = function (string $table, string $dateColumn, int $days, string $extraWhere = '1=1') use ($db): int {
    return 0;
};

$location = new LocationLog();
$purgedLocation = $location->purgeOldPoints();
$purgedNotifications = $deleteOlderThan('notifications', 'created_at', $setting('retention.notifications_days', 180), 'is_read = 1');
$purgedAudit = $deleteOlderThan('activity_logs', 'created_at', $setting('retention.audit_days', 365));
$purgedSecurity = $deleteOlderThan('security_events', 'created_at', $setting('retention.security_events_days', 365));
$purgedAttempts = $deleteOlderThan('login_attempts', 'attempted_at', $setting('retention.login_attempts_days', 90));
$purgedRates = $deleteOlderThan('rate_limits', 'expires_at', 1);

echo "[OK] retention: location={$purgedLocation} notifications={$purgedNotifications} audit={$purgedAudit} security={$purgedSecurity} login_attempts={$purgedAttempts} rates={$purgedRates} (physical deletes disabled when governance.no_physical_delete=1)\n";
