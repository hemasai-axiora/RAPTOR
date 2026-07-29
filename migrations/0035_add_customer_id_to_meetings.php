<?php
require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/core/Database.php';

try {
    $db = Database::getInstance()->getConnection();

    // Check if customer_id column exists on meetings table
    $stmt = $db->query("SHOW COLUMNS FROM meetings LIKE 'customer_id'");
    $col = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$col) {
        $db->exec("ALTER TABLE meetings ADD COLUMN customer_id INT NULL AFTER lead_id, ADD CONSTRAINT fk_meetings_customer FOREIGN KEY (customer_id) REFERENCES customers(customer_id) ON DELETE SET NULL");
        echo "Successfully added customer_id column to meetings table.\n";
    } else {
        echo "customer_id column already exists on meetings table.\n";
    }
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
