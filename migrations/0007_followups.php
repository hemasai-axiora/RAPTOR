<?php
/**
 * Sprint 6 - Follow-ups, reminder notifications, and contact-SLA escalations.
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
}
$addIndex($db, 'notifications', 'uq_notifications_dedupe', 'UNIQUE KEY `uq_notifications_dedupe` (`dedupe_key`)');

if (!$tableExists($db, 'follow_ups')) {
    $db->exec(
        "CREATE TABLE follow_ups (
            follow_up_id BIGINT AUTO_INCREMENT PRIMARY KEY,
            lead_id INT NOT NULL,
            assigned_to_user_id INT NOT NULL,
            created_by_user_id INT NULL,
            channel ENUM('call','whatsapp','sms','email','meeting','demo','other') NOT NULL DEFAULT 'call',
            due_at DATETIME NOT NULL,
            note TEXT NULL,
            status ENUM('scheduled','completed','missed','cancelled') NOT NULL DEFAULT 'scheduled',
            completed_at DATETIME NULL,
            outcome VARCHAR(255) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_followups_owner_due (assigned_to_user_id, due_at),
            INDEX idx_followups_status_due (status, due_at),
            INDEX idx_followups_lead (lead_id),
            FOREIGN KEY (lead_id) REFERENCES leads(lead_id) ON DELETE CASCADE,
            FOREIGN KEY (assigned_to_user_id) REFERENCES users(user_id) ON DELETE CASCADE,
            FOREIGN KEY (created_by_user_id) REFERENCES users(user_id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    echo "    + follow_ups table added\n";
}

if (!$tableExists($db, 'lead_sla_escalations')) {
    $db->exec(
        "CREATE TABLE lead_sla_escalations (
            escalation_id BIGINT AUTO_INCREMENT PRIMARY KEY,
            lead_id INT NOT NULL,
            assigned_to_user_id INT NULL,
            escalated_to_user_id INT NOT NULL,
            reason VARCHAR(150) NOT NULL,
            status ENUM('open','resolved') NOT NULL DEFAULT 'open',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            resolved_at DATETIME NULL,
            UNIQUE KEY uq_lead_sla_open (lead_id, reason, status),
            FOREIGN KEY (lead_id) REFERENCES leads(lead_id) ON DELETE CASCADE,
            FOREIGN KEY (assigned_to_user_id) REFERENCES users(user_id) ON DELETE SET NULL,
            FOREIGN KEY (escalated_to_user_id) REFERENCES users(user_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    echo "    + lead_sla_escalations table added\n";
}

$db->exec(
    "INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
        ('lead.contact_sla_hours', '24'),
        ('followup.due_today_hour', '8')"
);

echo "  [OK] follow-up and reminder schema ensured.\n";
