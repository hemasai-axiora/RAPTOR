<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/core/Database.php';

try {
    $db = Database::getInstance()->getConnection();
    echo "DB CONNECTED OK\n";
} catch (Exception $e) {
    echo "DB ERROR: " . $e->getMessage() . "\n";
}
