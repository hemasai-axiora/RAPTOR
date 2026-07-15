<?php
/**
 * Sprint 9 - Target planning and planned-vs-achieved progress.
 */

$tableExists = function (PDO $db, string $table): bool {
    $stmt = $db->prepare(
        "SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t"
    );
    $stmt->execute([':t' => $table]);
    return (int) $stmt->fetchColumn() > 0;
};

if (!$tableExists($db, 'target_categories')) {
    $db->exec(
        "CREATE TABLE target_categories (
            category_id INT AUTO_INCREMENT PRIMARY KEY,
            category_key VARCHAR(50) NOT NULL UNIQUE,
            name VARCHAR(100) NOT NULL,
            unit VARCHAR(30) NOT NULL DEFAULT 'count',
            description VARCHAR(255) NULL,
            active BOOLEAN NOT NULL DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    echo "    + target_categories table added\n";
}

if (!$tableExists($db, 'targets')) {
    $db->exec(
        "CREATE TABLE targets (
            target_id BIGINT AUTO_INCREMENT PRIMARY KEY,
            owner_type ENUM('employee','team') NOT NULL DEFAULT 'employee',
            owner_user_id INT NULL,
            team_id INT NULL,
            period ENUM('daily','weekly','monthly') NOT NULL DEFAULT 'monthly',
            start_date DATE NOT NULL,
            end_date DATE NOT NULL,
            status ENUM('draft','pending_approval','approved','rejected') NOT NULL DEFAULT 'pending_approval',
            created_by_user_id INT NULL,
            approved_by_user_id INT NULL,
            approved_at DATETIME NULL,
            approval_remark VARCHAR(255) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_targets_owner_period (owner_type, owner_user_id, team_id, start_date, end_date),
            INDEX idx_targets_status (status),
            FOREIGN KEY (owner_user_id) REFERENCES users(user_id) ON DELETE CASCADE,
            FOREIGN KEY (team_id) REFERENCES teams(team_id) ON DELETE CASCADE,
            FOREIGN KEY (created_by_user_id) REFERENCES users(user_id) ON DELETE SET NULL,
            FOREIGN KEY (approved_by_user_id) REFERENCES users(user_id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    echo "    + targets table added\n";
}

if (!$tableExists($db, 'target_items')) {
    $db->exec(
        "CREATE TABLE target_items (
            target_item_id BIGINT AUTO_INCREMENT PRIMARY KEY,
            target_id BIGINT NOT NULL,
            category_id INT NOT NULL,
            product_id INT NULL,
            territory_id INT NULL,
            planned_value DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_target_items_target (target_id),
            FOREIGN KEY (target_id) REFERENCES targets(target_id) ON DELETE CASCADE,
            FOREIGN KEY (category_id) REFERENCES target_categories(category_id) ON DELETE CASCADE,
            FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE SET NULL,
            FOREIGN KEY (territory_id) REFERENCES territories(territory_id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    echo "    + target_items table added\n";
}

if (!$tableExists($db, 'target_progress')) {
    $db->exec(
        "CREATE TABLE target_progress (
            progress_id BIGINT AUTO_INCREMENT PRIMARY KEY,
            target_item_id BIGINT NOT NULL,
            achieved_value DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            completion_percent DECIMAL(6,2) NOT NULL DEFAULT 0.00,
            computed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_target_progress_item (target_item_id),
            FOREIGN KEY (target_item_id) REFERENCES target_items(target_item_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    echo "    + target_progress table added\n";
}

$db->exec(
    "INSERT IGNORE INTO target_categories (category_key, name, unit, description) VALUES
        ('calls', 'Calls', 'count', 'Call communications logged'),
        ('emails', 'Emails', 'count', 'Email communications logged'),
        ('messages', 'Messages', 'count', 'WhatsApp/SMS/social communications logged'),
        ('meetings', 'Meetings', 'count', 'Completed meetings'),
        ('demos', 'Demos', 'count', 'Completed demos'),
        ('leads', 'Leads Generated', 'count', 'Leads created'),
        ('conversions', 'Conversions', 'count', 'Leads converted'),
        ('revenue', 'Revenue', 'currency', 'Converted lead value'),
        ('tasks', 'Approved Tasks', 'count', 'Tasks approved by leader')"
);

echo "  [OK] target planning schema ensured.\n";
