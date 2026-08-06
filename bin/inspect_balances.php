<?php
require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/core/Model.php';
require_once __DIR__ . '/../app/models/Leave.php';

$leaveModel = new Leave();
$leaveModel->ensureLeaveBalanceExists(35);

$db = Database::getInstance()->getConnection();

// Check user 35 (Mundlamuri Mrudula)
$stmt = $db->query("SELECT u.user_id, u.name, e.department FROM users u LEFT JOIN employees e ON u.user_id = e.user_id WHERE u.user_id = 35");
$u35 = $stmt->fetch(PDO::FETCH_ASSOC);
echo "User 35: " . json_encode($u35) . "\n";

// Check employee_leave_balances count for user 35
$stmt2 = $db->query("SELECT * FROM employee_leave_balances WHERE user_id = 35");
$b35 = $stmt2->fetchAll(PDO::FETCH_ASSOC);
echo "Leave Balances for 35: " . json_encode($b35) . "\n";

// Run getAllDetailedLeaveBalances for Analytics department
$res = $leaveModel->getAllDetailedLeaveBalances(['department' => 'Analytics', 'leave_year' => 2026]);
echo "Detailed Balances for Analytics count: " . count($res) . "\n";
echo "Detailed Balances Data: " . json_encode($res) . "\n";
