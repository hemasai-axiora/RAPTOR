<?php
/**
 * Migration 0029: Audience Demographics Capture (post_audience_demographics, post_audience_age, post_audience_gender, post_audience_country)
 */

echo "Starting Migration 0029: Audience Demographics Capture & Insights Engine...\n";

// Helper function to check if column exists
$columnExists = function (PDO $db, string $table, string $column): bool {
    try {
        $stmt = $db->prepare("SHOW COLUMNS FROM {$table} LIKE :col");
        $stmt->execute([':col' => $column]);
        return (bool) $stmt->fetch();
    } catch (Exception $e) {
        return false;
    }
};

// 1. Add current_followers_pct and current_non_followers_pct to posts table
if (!$columnExists($db, 'posts', 'current_followers_pct')) {
    $db->exec("ALTER TABLE posts ADD COLUMN current_followers_pct DECIMAL(5,2) DEFAULT 0.00 AFTER current_engagement_rate");
    echo "    + Added current_followers_pct column to posts table\n";
}

if (!$columnExists($db, 'posts', 'current_non_followers_pct')) {
    $db->exec("ALTER TABLE posts ADD COLUMN current_non_followers_pct DECIMAL(5,2) DEFAULT 0.00 AFTER current_followers_pct");
    echo "    + Added current_non_followers_pct column to posts table\n";
}

// 2. Create parent snapshot table: post_audience_demographics
$db->exec("CREATE TABLE IF NOT EXISTS post_audience_demographics (
    demographics_id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    followers_pct DECIMAL(5,2) DEFAULT 0.00,
    non_followers_pct DECIMAL(5,2) DEFAULT 0.00,
    captured_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    created_by_user_id INT NULL,
    FOREIGN KEY (post_id) REFERENCES posts(post_id) ON DELETE CASCADE,
    FOREIGN KEY (created_by_user_id) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
echo "    + Verified/created post_audience_demographics parent table\n";

// 3. Create normalized age breakdown table: post_audience_age
$db->exec("CREATE TABLE IF NOT EXISTS post_audience_age (
    age_id INT AUTO_INCREMENT PRIMARY KEY,
    demographics_id INT NOT NULL,
    age_bracket VARCHAR(20) NOT NULL,
    percentage DECIMAL(5,2) DEFAULT 0.00,
    FOREIGN KEY (demographics_id) REFERENCES post_audience_demographics(demographics_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
echo "    + Verified/created post_audience_age child table\n";

// 4. Create normalized gender breakdown table: post_audience_gender
$db->exec("CREATE TABLE IF NOT EXISTS post_audience_gender (
    gender_id INT AUTO_INCREMENT PRIMARY KEY,
    demographics_id INT NOT NULL,
    gender VARCHAR(20) NOT NULL,
    percentage DECIMAL(5,2) DEFAULT 0.00,
    FOREIGN KEY (demographics_id) REFERENCES post_audience_demographics(demographics_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
echo "    + Verified/created post_audience_gender child table\n";

// 5. Create normalized country breakdown table: post_audience_country
$db->exec("CREATE TABLE IF NOT EXISTS post_audience_country (
    country_id INT AUTO_INCREMENT PRIMARY KEY,
    demographics_id INT NOT NULL,
    country VARCHAR(100) NOT NULL,
    percentage DECIMAL(5,2) DEFAULT 0.00,
    FOREIGN KEY (demographics_id) REFERENCES post_audience_demographics(demographics_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
echo "    + Verified/created post_audience_country child table\n";

echo "Migration 0029 complete.\n";
