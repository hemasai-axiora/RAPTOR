<?php
// Migration 0040: Revoke all payroll module permissions from Analyst role

require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/core/Database.php';

try {
    $db = Database::getInstance()->getConnection();
    
    // Revoke payroll permissions for analyst role
    $stmt = $db->prepare("
        DELETE rp FROM role_permissions rp
        JOIN roles r ON rp.role_id = r.role_id
        JOIN permissions p ON rp.permission_id = p.permission_id
        WHERE r.role_name = 'analyst' AND p.module = 'payroll'
    ");
    $stmt->execute();

    echo "Migration 0040 executed successfully: Revoked payroll permissions from Analyst role.\n";
} catch (Exception $e) {
    echo "Migration 0040 Error: " . $e->getMessage() . "\n";
}
