<?php
/**
 * Dedicated customizable dashboard module.
 */

$tableExists = function (PDO $db, string $table): bool {
    $stmt = $db->prepare(
        "SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t"
    );
    $stmt->execute([':t' => $table]);
    return (int) $stmt->fetchColumn() > 0;
};

if (!$tableExists($db, 'dashboard_preferences')) {
    $db->exec(
        "CREATE TABLE dashboard_preferences (
            preference_id BIGINT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            dashboard_key VARCHAR(60) NOT NULL,
            widget_order JSON NULL,
            hidden_widgets JSON NULL,
            theme_accent VARCHAR(30) NOT NULL DEFAULT 'indigo',
            date_range_days INT NOT NULL DEFAULT 30,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_dashboard_pref_user_key (user_id, dashboard_key),
            FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    echo "    + dashboard_preferences table added\n";
}

$db->exec(
    "INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
        ('dashboards.default_range_days', '30'),
        ('dashboards.enable_customization', '1')"
);

echo "  [OK] dashboard module schema ensured.\n";
