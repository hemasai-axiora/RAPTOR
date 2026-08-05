<?php
require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/core/Database.php';

try {
    $db = Database::getInstance()->getConnection();
    
    // Find user_ids to remove with @raptor.com or SYS-ADMIN
    $stmt = $db->query("SELECT user_id, email FROM users WHERE email LIKE '%@raptor.com'");
    $toDelete = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "=== FOUND SYS-ADMIN / DUPLICATE ACCOUNTS TO REMOVE ===\n";
    foreach ($toDelete as $row) {
        echo "User ID: {$row['user_id']} | Email: {$row['email']}\n";
    }

    if (!empty($toDelete)) {
        $userIds = array_column($toDelete, 'user_id');

        $db->exec("SET FOREIGN_KEY_CHECKS = 0;");

        $placeholders = implode(',', array_fill(0, count($userIds), '?'));
        $delEmp = $db->prepare("DELETE FROM employees WHERE user_id IN ($placeholders)");
        $delEmp->execute($userIds);
        echo "[OK] Deleted matching rows from employees table.\n";

        $delUsers = $db->prepare("DELETE FROM users WHERE user_id IN ($placeholders)");
        $delUsers->execute($userIds);
        echo "[OK] Deleted " . count($userIds) . " matching rows from users table.\n";

        $db->exec("SET FOREIGN_KEY_CHECKS = 1;");
    } else {
        echo "No SYS-ADMIN / @raptor.com duplicate rows found.\n";
    }

    echo "=== CLEANUP COMPLETE ===\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
