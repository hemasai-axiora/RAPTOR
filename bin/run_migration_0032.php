<?php
require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../migrations/0032_website_analytics_module.php';

try {
    $db = Database::getInstance()->getConnection();
    up_0032($db);
} catch (Exception $e) {
    echo "Migration 0032 failed: " . $e->getMessage() . "\n";
    exit(1);
}
