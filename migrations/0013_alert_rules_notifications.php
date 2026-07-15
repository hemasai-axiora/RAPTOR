<?php
/**
 * Sprint 13 - Alert rules, notification center metadata, and web-push subscriptions.
 */

$tableExists = function (PDO $db, string $table): bool {
    $stmt = $db->prepare(
        "SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t"
    );
    $stmt->execute([':t' => $table]);
    return (int) $stmt->fetchColumn() > 0;
};

$columnExists = function (PDO $db, string $table, string $column): bool {
    $stmt = $db->prepare(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c"
    );
    $stmt->execute([':t' => $table, ':c' => $column]);
    return (int) $stmt->fetchColumn() > 0;
};

$indexExists = function (PDO $db, string $table, string $index): bool {
    $stmt = $db->prepare(
        "SELECT COUNT(*) FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND INDEX_NAME = :i"
    );
    $stmt->execute([':t' => $table, ':i' => $index]);
    return (int) $stmt->fetchColumn() > 0;
};

$addColumn = function (PDO $db, string $table, string $column, string $ddl) use ($columnExists) {
    if (!$columnExists($db, $table, $column)) {
        $db->exec("ALTER TABLE `$table` ADD COLUMN $ddl");
        echo "    + $table.$column added\n";
    }
};

$addIndex = function (PDO $db, string $table, string $index, string $ddl) use ($indexExists) {
    if (!$indexExists($db, $table, $index)) {
        $db->exec("ALTER TABLE `$table` ADD $ddl");
        echo "    + $table.$index added\n";
    }
};

if (!$tableExists($db, 'notifications')) {
    $db->exec(
        "CREATE TABLE notifications (
            notification_id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            title VARCHAR(150) NOT NULL,
            message TEXT NOT NULL,
            type VARCHAR(50) NOT NULL DEFAULT 'system',
            action_url VARCHAR(255) NULL,
            severity ENUM('info','warning','critical') NOT NULL DEFAULT 'info',
            category VARCHAR(50) NULL,
            dedupe_key VARCHAR(150) NULL,
            is_read BOOLEAN DEFAULT FALSE,
            read_at DATETIME NULL,
            push_status ENUM('pending','sent','failed','skipped') NOT NULL DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    echo "    + notifications table added\n";
} else {
    $addColumn($db, 'notifications', 'action_url', "action_url VARCHAR(255) NULL AFTER type");
    $addColumn($db, 'notifications', 'severity', "severity ENUM('info','warning','critical') NOT NULL DEFAULT 'info' AFTER action_url");
    $addColumn($db, 'notifications', 'category', "category VARCHAR(50) NULL AFTER severity");
    $addColumn($db, 'notifications', 'dedupe_key', "dedupe_key VARCHAR(150) NULL AFTER category");
    $addColumn($db, 'notifications', 'read_at', "read_at DATETIME NULL AFTER is_read");
    $addColumn($db, 'notifications', 'push_status', "push_status ENUM('pending','sent','failed','skipped') NOT NULL DEFAULT 'pending' AFTER read_at");
}
$addIndex($db, 'notifications', 'uq_notifications_dedupe', 'UNIQUE KEY `uq_notifications_dedupe` (`dedupe_key`)');
$addIndex($db, 'notifications', 'idx_notifications_user_read', 'INDEX `idx_notifications_user_read` (`user_id`, `is_read`, `created_at`)');

if (!$tableExists($db, 'alert_rules')) {
    $db->exec(
        "CREATE TABLE alert_rules (
            rule_id INT AUTO_INCREMENT PRIMARY KEY,
            rule_key VARCHAR(80) NOT NULL UNIQUE,
            name VARCHAR(150) NOT NULL,
            category VARCHAR(60) NOT NULL,
            severity ENUM('info','warning','critical') NOT NULL DEFAULT 'warning',
            enabled BOOLEAN NOT NULL DEFAULT TRUE,
            threshold_value DECIMAL(10,2) NULL,
            threshold_unit VARCHAR(30) NULL,
            params_json JSON NULL,
            recipient_scope ENUM('owner','manager','both','admin') NOT NULL DEFAULT 'owner',
            last_run_at DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    echo "    + alert_rules table added\n";
}

if (!$tableExists($db, 'push_subscriptions')) {
    $db->exec(
        "CREATE TABLE push_subscriptions (
            subscription_id BIGINT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            endpoint TEXT NOT NULL,
            p256dh_key VARCHAR(255) NOT NULL,
            auth_key VARCHAR(255) NOT NULL,
            user_agent VARCHAR(255) NULL,
            active BOOLEAN NOT NULL DEFAULT TRUE,
            last_seen_at DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_push_user_active (user_id, active),
            FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    echo "    + push_subscriptions table added\n";
}

$db->exec(
    "INSERT INTO alert_rules
        (rule_key, name, category, severity, enabled, threshold_value, threshold_unit, recipient_scope)
     VALUES
        ('late_login', 'Late login', 'attendance', 'warning', 1, 0, 'minutes', 'owner'),
        ('no_login', 'No login after shift start', 'attendance', 'critical', 1, 60, 'minutes', 'manager'),
        ('missing_logout', 'Missing logout', 'attendance', 'warning', 1, 12, 'hours', 'both'),
        ('location_disabled', 'Location disabled while on duty', 'location', 'warning', 1, 0, 'minutes', 'manager'),
        ('target_not_updated', 'Target progress not updated', 'target', 'warning', 1, 24, 'hours', 'manager'),
        ('task_overdue', 'Task overdue', 'task', 'warning', 1, 0, 'minutes', 'both'),
        ('followup_due', 'Follow-up due soon', 'followup', 'info', 1, 120, 'minutes', 'owner'),
        ('missed_followup', 'Missed follow-up', 'followup', 'warning', 1, 0, 'minutes', 'both'),
        ('lead_unattended', 'Lead unattended', 'lead', 'warning', 1, 24, 'hours', 'manager'),
        ('meeting_reminder', 'Meeting/demo reminder', 'meeting', 'info', 1, 60, 'minutes', 'owner'),
        ('low_performance', 'Low performance', 'performance', 'warning', 1, 50, 'score', 'manager'),
        ('approval_pending', 'Approval pending', 'approval', 'info', 1, 24, 'hours', 'manager'),
        ('high_value_lead', 'High-value lead pending', 'lead', 'critical', 1, 100000, 'currency', 'manager'),
        ('contact_sla_breach', 'Contact SLA breach', 'lead_sla', 'critical', 1, 24, 'hours', 'manager')
     ON DUPLICATE KEY UPDATE name = VALUES(name), category = VALUES(category)"
);

$db->exec(
    "INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
        ('alerts.cron_enabled', '1'),
        ('alerts.web_push_enabled', '0'),
        ('alerts.vapid_public_key', ''),
        ('alerts.vapid_private_key', ''),
        ('alerts.email_enabled', '0'),
        ('alerts.email_from', '')"
);

echo "  [OK] alert rules and notification schema ensured.\n";
