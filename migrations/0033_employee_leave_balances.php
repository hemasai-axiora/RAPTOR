<?php
// Migration 0033: Employee Leave Balances and Transactions tables

function up_0033(PDO $db) {
    // 1. employee_leave_balances table
    $db->exec("CREATE TABLE IF NOT EXISTS employee_leave_balances (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        employee_id INT NULL,
        leave_type_name VARCHAR(50) NOT NULL,
        leave_year INT NOT NULL DEFAULT 2026,
        allocated_days DECIMAL(5,2) NOT NULL DEFAULT 0.00,
        carried_forward_days DECIMAL(5,2) NOT NULL DEFAULT 0.00,
        consumed_days DECIMAL(5,2) NOT NULL DEFAULT 0.00,
        pending_days DECIMAL(5,2) NOT NULL DEFAULT 0.00,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uk_user_type_year (user_id, leave_type_name, leave_year),
        FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 2. employee_leave_transactions table
    $db->exec("CREATE TABLE IF NOT EXISTS employee_leave_transactions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        leave_type_name VARCHAR(50) NOT NULL,
        transaction_type ENUM('Accrual', 'Consumption', 'Carry-Forward', 'Manual Adjustment', 'Encashment', 'Pending Hold', 'Pending Release') NOT NULL,
        days DECIMAL(5,2) NOT NULL,
        reference_leave_request_id INT NULL,
        created_by_user_id INT NULL,
        remarks TEXT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Seed default leave balances for all active users for year 2026
    $stmtUsers = $db->query("SELECT user_id FROM users WHERE status = 'active'");
    $users = $stmtUsers->fetchAll(PDO::FETCH_COLUMN);

    $defaultQuotas = [
        'Casual Leave' => 12.00,
        'Sick Leave'   => 10.00,
        'Earned Leave' => 15.00,
        'Comp-Off'     => 6.00
    ];

    $stmtInsert = $db->prepare("INSERT IGNORE INTO employee_leave_balances (user_id, leave_type_name, leave_year, allocated_days) VALUES (:uid, :type, 2026, :quota)");
    $stmtTx = $db->prepare("INSERT INTO employee_leave_transactions (user_id, leave_type_name, transaction_type, days, remarks) VALUES (:uid, :type, 'Accrual', :quota, 'Initial annual leave policy quota allocation')");

    foreach ($users as $uid) {
        foreach ($defaultQuotas as $type => $quota) {
            $stmtInsert->execute([':uid' => $uid, ':type' => $type, ':quota' => $quota]);
            if ($stmtInsert->rowCount() > 0) {
                $stmtTx->execute([':uid' => $uid, ':type' => $type, ':quota' => $quota]);
            }
        }
    }
}
