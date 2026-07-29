<?php
require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/core/Database.php';

try {
    $db = Database::getInstance()->getConnection();
    $db->exec("ALTER TABLE targets MODIFY COLUMN status ENUM('draft', 'pending_approval', 'approved', 'completed', 'rejected') NOT NULL DEFAULT 'pending_approval'");
    echo "Successfully updated targets status ENUM to include 'completed'.\n";
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
