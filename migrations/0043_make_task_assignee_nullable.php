<?php
// Migration 0043: Make assigned_to_user_id nullable in tasks table to support team-assigned tasks

require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/core/Database.php';

try {
    $db = Database::getInstance()->getConnection();
    $db->exec("ALTER TABLE tasks MODIFY COLUMN assigned_to_user_id INT NULL");
    echo "Migration 0043 executed successfully: Modified tasks.assigned_to_user_id to NULLable.\n";
} catch (Exception $e) {
    echo "Migration 0043 Note: " . $e->getMessage() . "\n";
}
