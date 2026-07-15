<?php
require_once dirname(dirname(__FILE__)) . '/app/config/config.php';
require_once dirname(dirname(__FILE__)) . '/app/core/Database.php';

try {
    $db = Database::getInstance()->getConnection();
    echo "Altering tasks table...\n";

    // Check existing columns
    $stmt = $db->query("DESCRIBE tasks");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (!in_array('start_date', $columns)) {
        $db->exec("ALTER TABLE tasks ADD COLUMN start_date DATETIME NULL AFTER description;");
        echo "[INFO] Added start_date column.\n";
    }

    if (!in_array('completed_at', $columns)) {
        $db->exec("ALTER TABLE tasks ADD COLUMN completed_at DATETIME NULL AFTER status;");
        echo "[INFO] Added completed_at column.\n";
    }

    if (!in_array('progress_percent', $columns)) {
        $db->exec("ALTER TABLE tasks ADD COLUMN progress_percent INT DEFAULT 0 AFTER completed_at;");
        echo "[INFO] Added progress_percent column.\n";
    }

    if (!in_array('estimated_hours', $columns)) {
        $db->exec("ALTER TABLE tasks ADD COLUMN estimated_hours DECIMAL(5,2) DEFAULT 0.00 AFTER progress_percent;");
        echo "[INFO] Added estimated_hours column.\n";
    }

    if (!in_array('actual_hours', $columns)) {
        $db->exec("ALTER TABLE tasks ADD COLUMN actual_hours DECIMAL(5,2) DEFAULT 0.00 AFTER estimated_hours;");
        echo "[INFO] Added actual_hours column.\n";
    }

    if (!in_array('remarks', $columns)) {
        $db->exec("ALTER TABLE tasks ADD COLUMN remarks TEXT NULL AFTER actual_hours;");
        echo "[INFO] Added remarks column.\n";
    }

    echo "[OK] tasks table altered successfully.\n";
} catch (Exception $e) {
    die("[FATAL ERROR] " . $e->getMessage() . "\n");
}
