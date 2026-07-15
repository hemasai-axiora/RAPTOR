<?php
/**
 * Sprint 14 - Security, retention, indexes, and launch hardening.
 */

$tableExists = function (PDO $db, string $table): bool {
    $stmt = $db->prepare(
        "SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t"
    );
    $stmt->execute([':t' => $table]);
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

$addIndex = function (PDO $db, string $table, string $index, string $ddl) use ($indexExists) {
    if (!$indexExists($db, $table, $index)) {
        $db->exec("ALTER TABLE `$table` ADD $ddl");
        echo "    + $table.$index added\n";
    }
};

if (!$tableExists($db, 'login_attempts')) {
    $db->exec(
        "CREATE TABLE login_attempts (
            attempt_id BIGINT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(150) NOT NULL,
            ip_address VARCHAR(45) NOT NULL,
            user_agent VARCHAR(255) NULL,
            success BOOLEAN NOT NULL DEFAULT FALSE,
            attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_login_attempts_email_time (email, attempted_at),
            INDEX idx_login_attempts_ip_time (ip_address, attempted_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    echo "    + login_attempts table added\n";
}

if (!$tableExists($db, 'rate_limits')) {
    $db->exec(
        "CREATE TABLE rate_limits (
            bucket_key VARCHAR(180) NOT NULL PRIMARY KEY,
            hit_count INT NOT NULL DEFAULT 0,
            window_start DATETIME NOT NULL,
            expires_at DATETIME NOT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_rate_limits_expires (expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    echo "    + rate_limits table added\n";
}

if (!$tableExists($db, 'security_events')) {
    $db->exec(
        "CREATE TABLE security_events (
            event_id BIGINT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NULL,
            event_type VARCHAR(80) NOT NULL,
            severity ENUM('info','warning','critical') NOT NULL DEFAULT 'info',
            ip_address VARCHAR(45) NULL,
            user_agent VARCHAR(255) NULL,
            context_json JSON NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_security_events_type_time (event_type, created_at),
            INDEX idx_security_events_user_time (user_id, created_at),
            FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    echo "    + security_events table added\n";
}

$addIndex($db, 'activity_logs', 'idx_activity_created', 'INDEX `idx_activity_created` (`created_at`)');
$addIndex($db, 'notifications', 'idx_notifications_created', 'INDEX `idx_notifications_created` (`created_at`)');
$addIndex($db, 'leads', 'idx_leads_created_status', 'INDEX `idx_leads_created_status` (`created_at`, `status`)');
$addIndex($db, 'follow_ups', 'idx_followups_due_status', 'INDEX `idx_followups_due_status` (`due_at`, `status`)');
$addIndex($db, 'communications', 'idx_comms_happened_channel', 'INDEX `idx_comms_happened_channel` (`happened_at`, `channel`)');
$addIndex($db, 'meetings', 'idx_meetings_schedule_type', 'INDEX `idx_meetings_schedule_type` (`scheduled_start`, `type`, `status`)');
$addIndex($db, 'tasks', 'idx_tasks_deadline_status', 'INDEX `idx_tasks_deadline_status` (`deadline`, `status`)');
$addIndex($db, 'attendance', 'idx_attendance_login', 'INDEX `idx_attendance_login` (`login_at`)');

$db->exec(
    "INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
        ('auth.max_failed_attempts', '5'),
        ('auth.lockout_minutes', '15'),
        ('rate.api_limit', '120'),
        ('rate.api_window_seconds', '60'),
        ('rate.login_limit', '20'),
        ('rate.login_window_seconds', '300'),
        ('retention.location_days', '90'),
        ('retention.notifications_days', '180'),
        ('retention.audit_days', '365'),
        ('retention.security_events_days', '365'),
        ('retention.login_attempts_days', '90')"
);

echo "  [OK] security hardening schema ensured.\n";
