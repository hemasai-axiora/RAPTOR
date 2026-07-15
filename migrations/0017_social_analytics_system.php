<?php
/**
 * Migration 0017: Social Account Management & Analytics Tracking
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

// 1. Create platforms table
if (!$tableExists($db, 'platforms')) {
    $db->exec("
        CREATE TABLE platforms (
            platform_id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(50) NOT NULL UNIQUE,
            icon VARCHAR(50) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "    + platforms table created\n";

    // Seed default platforms
    $stmt = $db->prepare("INSERT INTO platforms (name, icon) VALUES (:name, :icon)");
    $defaults = [
        ['Instagram', 'fa-brands fa-instagram'],
        ['Facebook', 'fa-brands fa-facebook'],
        ['YouTube', 'fa-brands fa-youtube'],
        ['WhatsApp Business', 'fa-brands fa-whatsapp'],
        ['LinkedIn', 'fa-brands fa-linkedin'],
        ['Twitter/X', 'fa-brands fa-x-twitter'],
        ['Snapchat', 'fa-brands fa-snapchat']
    ];
    foreach ($defaults as [$name, $icon]) {
        $stmt->execute([':name' => $name, ':icon' => $icon]);
    }
    echo "    + default platforms seeded\n";
}

// 2. Modify social_accounts to add platform_id
if ($tableExists($db, 'social_accounts') && !$columnExists($db, 'social_accounts', 'platform_id')) {
    $db->exec("ALTER TABLE social_accounts ADD COLUMN platform_id INT NULL");
    $db->exec("ALTER TABLE social_accounts ADD CONSTRAINT fk_social_accounts_platform FOREIGN KEY (platform_id) REFERENCES platforms(platform_id) ON DELETE SET NULL");
    echo "    + social_accounts platform_id column added\n";

    // Migrate existing data based on platform text
    $platforms = $db->query("SELECT platform_id, name FROM platforms")->fetchAll(PDO::FETCH_ASSOC);
    $updateStmt = $db->prepare("UPDATE social_accounts SET platform_id = :pid WHERE LOWER(platform) = :pname");
    foreach ($platforms as $p) {
        $searchName = strtolower($p['name']);
        if ($searchName === 'twitter/x') {
            $updateStmt->execute([':pid' => $p['platform_id'], ':pname' => 'x']);
        } elseif ($searchName === 'whatsapp business') {
            $updateStmt->execute([':pid' => $p['platform_id'], ':pname' => 'whatsapp']);
        } else {
            $updateStmt->execute([':pid' => $p['platform_id'], ':pname' => $searchName]);
        }
    }
    echo "    + existing social_accounts mapped to platforms\n";
}

// 3. Modify posts to add platform_id
if ($tableExists($db, 'posts') && !$columnExists($db, 'posts', 'platform_id')) {
    $db->exec("ALTER TABLE posts ADD COLUMN platform_id INT NULL");
    $db->exec("ALTER TABLE posts ADD CONSTRAINT fk_posts_platform FOREIGN KEY (platform_id) REFERENCES platforms(platform_id) ON DELETE SET NULL");
    echo "    + posts platform_id column added\n";

    // Populate platform_id from associated social_account
    $db->exec("UPDATE posts p JOIN social_accounts s ON p.account_id = s.account_id SET p.platform_id = s.platform_id");
    echo "    + existing posts mapped to platforms\n";
}

// 4. Create assignments table
if (!$tableExists($db, 'assignments')) {
    $db->exec("
        CREATE TABLE assignments (
            assignment_id INT AUTO_INCREMENT PRIMARY KEY,
            account_id INT NOT NULL,
            user_id INT NOT NULL,
            assigned_by INT NULL,
            is_shared BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (account_id) REFERENCES social_accounts(account_id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
            FOREIGN KEY (assigned_by) REFERENCES users(user_id) ON DELETE SET NULL,
            UNIQUE KEY uq_account_user (account_id, user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "    + assignments table created\n";
}

// 5. Create analytics_entries table
if (!$tableExists($db, 'analytics_entries')) {
    $db->exec("
        CREATE TABLE analytics_entries (
            entry_id INT AUTO_INCREMENT PRIMARY KEY,
            platform_id INT NOT NULL,
            account_id INT NOT NULL,
            post_id INT NULL,
            likes INT DEFAULT 0,
            comments INT DEFAULT 0,
            shares INT DEFAULT 0,
            views INT DEFAULT 0,
            reach INT DEFAULT 0,
            impressions INT DEFAULT 0,
            clicks INT DEFAULT 0,
            followers_gained INT DEFAULT 0,
            engagement_rate DECIMAL(5,2) DEFAULT 0.00,
            custom_notes TEXT,
            status VARCHAR(50) DEFAULT 'active',
            updated_by INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (platform_id) REFERENCES platforms(platform_id) ON DELETE CASCADE,
            FOREIGN KEY (account_id) REFERENCES social_accounts(account_id) ON DELETE CASCADE,
            FOREIGN KEY (post_id) REFERENCES posts(post_id) ON DELETE SET NULL,
            FOREIGN KEY (updated_by) REFERENCES users(user_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "    + analytics_entries table created\n";
}

// 6. Create analytics_history table
if (!$tableExists($db, 'analytics_history')) {
    $db->exec("
        CREATE TABLE analytics_history (
            history_id INT AUTO_INCREMENT PRIMARY KEY,
            entry_id INT NOT NULL,
            platform_id INT NOT NULL,
            account_id INT NOT NULL,
            post_id INT NULL,
            likes INT DEFAULT 0,
            comments INT DEFAULT 0,
            shares INT DEFAULT 0,
            views INT DEFAULT 0,
            reach INT DEFAULT 0,
            impressions INT DEFAULT 0,
            clicks INT DEFAULT 0,
            followers_gained INT DEFAULT 0,
            engagement_rate DECIMAL(5,2) DEFAULT 0.00,
            custom_notes TEXT,
            status VARCHAR(50) DEFAULT 'active',
            updated_by INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (platform_id) REFERENCES platforms(platform_id) ON DELETE CASCADE,
            FOREIGN KEY (account_id) REFERENCES social_accounts(account_id) ON DELETE CASCADE,
            FOREIGN KEY (post_id) REFERENCES posts(post_id) ON DELETE SET NULL,
            FOREIGN KEY (updated_by) REFERENCES users(user_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "    + analytics_history table created\n";
}
