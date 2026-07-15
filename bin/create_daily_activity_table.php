<?php
require_once dirname(dirname(__FILE__)) . '/app/config/config.php';
require_once dirname(dirname(__FILE__)) . '/app/core/Database.php';

try {
    $db = Database::getInstance()->getConnection();
    echo "Creating daily_activity_logs table...\n";

    // Disable foreign keys
    $db->exec("SET FOREIGN_KEY_CHECKS = 0;");

    // Create daily_activity_logs table
    $db->exec("CREATE TABLE IF NOT EXISTS daily_activity_logs (
        activity_id BIGINT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        account_id INT NOT NULL,
        recorded_date DATE NOT NULL,
        posts_published INT DEFAULT 0,
        posts_scheduled INT DEFAULT 0,
        comments_made INT DEFAULT 0,
        replies_sent INT DEFAULT 0,
        likes_given INT DEFAULT 0,
        shares_completed INT DEFAULT 0,
        new_followers INT DEFAULT 0,
        lost_followers INT DEFAULT 0,
        messages_replied INT DEFAULT 0,
        videos_uploaded INT DEFAULT 0,
        stories_posted INT DEFAULT 0,
        reels_published INT DEFAULT 0,
        FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
        FOREIGN KEY (account_id) REFERENCES social_accounts(account_id) ON DELETE CASCADE,
        UNIQUE KEY uq_user_account_date (user_id, account_id, recorded_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    
    echo "[OK] Created daily_activity_logs table.\n";

    // Enable foreign keys
    $db->exec("SET FOREIGN_KEY_CHECKS = 1;");

} catch (Exception $e) {
    die("[FATAL ERROR] " . $e->getMessage() . "\n");
}
