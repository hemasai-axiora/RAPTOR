<?php
require_once dirname(dirname(__FILE__)) . '/app/config/config.php';
require_once dirname(dirname(__FILE__)) . '/app/core/Database.php';

try {
    $db = Database::getInstance()->getConnection();
    echo "Altering social_accounts table...\n";

    // Disable foreign keys
    $db->exec("SET FOREIGN_KEY_CHECKS = 0;");

    // Check existing columns
    $stmt = $db->query("DESCRIBE social_accounts");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (!in_array('assigned_user_id', $columns)) {
        $db->exec("ALTER TABLE social_accounts ADD COLUMN assigned_user_id INT NULL AFTER client_id;");
        $db->exec("ALTER TABLE social_accounts ADD FOREIGN KEY (assigned_user_id) REFERENCES users(user_id) ON DELETE SET NULL;");
        echo "[INFO] Added assigned_user_id column.\n";
    }

    if (!in_array('username', $columns)) {
        $db->exec("ALTER TABLE social_accounts ADD COLUMN username VARCHAR(100) NULL AFTER profile_name;");
        echo "[INFO] Added username column.\n";
    }

    if (!in_array('followers', $columns)) {
        $db->exec("ALTER TABLE social_accounts ADD COLUMN followers INT DEFAULT 0 AFTER username;");
        echo "[INFO] Added followers column.\n";
    }

    if (!in_array('following', $columns)) {
        $db->exec("ALTER TABLE social_accounts ADD COLUMN following INT DEFAULT 0 AFTER followers;");
        echo "[INFO] Added following column.\n";
    }

    if (!in_array('page_likes', $columns)) {
        $db->exec("ALTER TABLE social_accounts ADD COLUMN page_likes INT DEFAULT 0 AFTER following;");
        echo "[INFO] Added page_likes column.\n";
    }

    // Enable foreign keys
    $db->exec("SET FOREIGN_KEY_CHECKS = 1;");

    echo "[OK] social_accounts table altered successfully.\n";
} catch (Exception $e) {
    die("[FATAL ERROR] " . $e->getMessage() . "\n");
}
