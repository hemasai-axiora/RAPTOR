<?php
require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/core/Database.php';

try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->query("SELECT u.user_id, u.email, u.status, r.role_name FROM users u JOIN roles r ON u.role_id = r.role_id");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "=== ALL USERS IN DATABASE ===\n";
    foreach ($users as $u) {
        echo "ID: {$u['user_id']} | Email: {$u['email']} | Role: {$u['role_name']} | Status: {$u['status']}\n";
    }
    echo "=============================\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
