<?php
require_once dirname(dirname(__FILE__)) . '/app/config/config.php';
require_once dirname(dirname(__FILE__)) . '/app/core/Database.php';

try {
    $db = Database::getInstance()->getConnection();
    echo "Creating notifications and calendar_events tables...\n";

    // Disable foreign keys
    $db->exec("SET FOREIGN_KEY_CHECKS = 0;");

    // Create notifications table
    $db->exec("CREATE TABLE IF NOT EXISTS notifications (
        notification_id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        title VARCHAR(150) NOT NULL,
        message TEXT NOT NULL,
        type VARCHAR(50) NOT NULL COMMENT 'task, system, report, campaign',
        is_read BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    echo "[OK] Created notifications table.\n";

    // Create calendar_events table
    $db->exec("CREATE TABLE IF NOT EXISTS calendar_events (
        event_id INT AUTO_INCREMENT PRIMARY KEY,
        client_id INT NULL,
        user_id INT NULL,
        title VARCHAR(150) NOT NULL,
        description TEXT,
        event_type VARCHAR(50) NOT NULL COMMENT 'meeting, holiday, launch, review, post',
        start_date DATETIME NOT NULL,
        end_date DATETIME NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (client_id) REFERENCES clients(client_id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    echo "[OK] Created calendar_events table.\n";

    // Enable foreign keys
    $db->exec("SET FOREIGN_KEY_CHECKS = 1;");

} catch (Exception $e) {
    die("[FATAL ERROR] " . $e->getMessage() . "\n");
}
