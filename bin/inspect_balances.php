<?php
require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/core/Model.php';
require_once __DIR__ . '/../app/models/Leave.php';

$leaveModel = new Leave();

$db = Database::getInstance()->getConnection();

// Create table employee_leave_balances if not exists
$db->exec("CREATE TABLE IF NOT EXISTS employee_leave_balances (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    employee_id INT NULL,
    leave_type_name VARCHAR(60) NOT NULL,
    leave_year INT NOT NULL DEFAULT 2026,
    allocated_days DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    carried_forward_days DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    consumed_days DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    pending_days DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_type_year (user_id, leave_type_name, leave_year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$db->exec("CREATE TABLE IF NOT EXISTS leave_balance_ledger (
    ledger_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    leave_type_name VARCHAR(60) NOT NULL,
    transaction_type VARCHAR(50) NOT NULL,
    days DECIMAL(5,2) NOT NULL,
    performed_by INT NULL,
    remarks TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// Populate for all active users
$stmtUsers = $db->query("SELECT user_id FROM users WHERE status = 'active'");
$users = $stmtUsers->fetchAll(PDO::FETCH_COLUMN);

foreach ($users as $uid) {
    $leaveModel->ensureDetailedLeaveBalances((int)$uid, 2026);
}

echo "Successfully created table employee_leave_balances and populated leave balances for " . count($users) . " users.\n";
