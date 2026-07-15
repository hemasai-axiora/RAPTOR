<?php
/**
 * Sprint 13 cron: evaluate enabled alert rules and create notifications.
 *
 * Usage:
 *   php bin/cron_alerts.php
 */

require_once dirname(__DIR__) . '/app/config/config.php';
require_once APPROOT . '/core/Database.php';
require_once APPROOT . '/core/Model.php';
require_once APPROOT . '/models/AlertRule.php';

$db = Database::getInstance()->getConnection();
$enabled = $db->query("SELECT setting_value FROM settings WHERE setting_key = 'alerts.cron_enabled'")->fetchColumn();
if ($enabled !== false && (string) $enabled !== '1') {
    echo "[SKIP] alerts.cron_enabled is off\n";
    exit(0);
}

$alerts = new AlertRule();
$results = $alerts->runEnabledRules();

foreach ($results as $rule => $created) {
    echo "[OK] {$rule}: {$created} notification(s)\n";
}
