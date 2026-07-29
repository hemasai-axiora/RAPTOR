<?php
require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../migrations/0031_account_management_module.php';

try {
    $db = Database::getInstance()->getConnection();
    up_0031($db);
} catch (Exception $e) {
    echo "Migration 0031 failed: " . $e->getMessage() . "\n";
    exit(1);
}
