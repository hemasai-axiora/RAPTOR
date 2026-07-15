<?php
require_once dirname(dirname(__FILE__)) . '/app/config/config.php';
require_once dirname(dirname(__FILE__)) . '/app/core/Database.php';

try {
    $db = Database::getInstance()->getConnection();
    echo "Altering employees table...\n";

    // Check existing columns
    $stmt = $db->query("DESCRIBE employees");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (!in_array('employee_code', $columns)) {
        $db->exec("ALTER TABLE employees ADD COLUMN employee_code VARCHAR(50) UNIQUE AFTER user_id;");
        echo "[INFO] Added employee_code column.\n";
    }

    if (!in_array('reporting_manager_id', $columns)) {
        $db->exec("ALTER TABLE employees ADD COLUMN reporting_manager_id INT NULL AFTER job_title;");
        echo "[INFO] Added reporting_manager_id column.\n";
    }

    // Enable foreign keys
    $db->exec("SET FOREIGN_KEY_CHECKS = 1;");

    echo "[OK] employees table altered successfully.\n";
} catch (Exception $e) {
    die("[FATAL ERROR] " . $e->getMessage() . "\n");
}
