<?php
// Reset claim #8 to pending for workflow demo
require __DIR__ . '/../app/config/config.php';
require __DIR__ . '/../app/core/Database.php';

$db = Database::getInstance()->getConnection();
$db->exec("UPDATE reimbursements SET status = 'pending', manager_id = NULL, finance_id = NULL WHERE reimbursement_id = 8");
echo "Claim #8 reset to 'pending' status.\n";

// Also seed 2 more fresh pending claims for employee test accounts
$emp22_stmt = $db->query("SELECT e.employee_id FROM employees e JOIN users u ON e.user_id=u.user_id WHERE u.email='employee22@raptor.test' LIMIT 1");
$emp22 = $emp22_stmt->fetch(PDO::FETCH_ASSOC);

if ($emp22) {
    $empId = $emp22['employee_id'];
    $db->prepare("INSERT INTO reimbursements (employee_id, claim_type, amount, description, status, created_at) VALUES (?, 'Fuel', 1200.00, 'Field visit fuel reimbursement', 'pending', NOW())")->execute([$empId]);
    $db->prepare("INSERT INTO reimbursements (employee_id, claim_type, amount, description, status, created_at) VALUES (?, 'Internet', 750.00, 'Monthly WFH internet bill', 'pending', NOW())")->execute([$empId]);
    echo "Added 2 fresh pending claims for employee22@raptor.test\n";
}

// Summary
$rows = $db->query("SELECT status, COUNT(*) as cnt FROM reimbursements GROUP BY status")->fetchAll(PDO::FETCH_ASSOC);
echo "\nCurrent status breakdown:\n";
foreach ($rows as $r) {
    echo "  {$r['status']}: {$r['cnt']}\n";
}
