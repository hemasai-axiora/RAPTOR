<?php
require __DIR__ . '/../app/config/config.php';
require __DIR__ . '/../app/core/Database.php';

$db = Database::getInstance()->getConnection();

// Total reimbursements
$total = $db->query('SELECT COUNT(*) FROM reimbursements')->fetchColumn();
echo "Total reimbursement claims: $total\n\n";

// Latest 10 claims
$stmt = $db->query('
    SELECT r.reimbursement_id, u.name as employee_name, e.employee_code,
           r.claim_type, r.amount, r.description, r.status, r.created_at
    FROM reimbursements r
    JOIN employees e ON r.employee_id = e.employee_id
    JOIN users u ON e.user_id = u.user_id
    ORDER BY r.created_at DESC
    LIMIT 10
');
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($rows)) {
    echo "No claims found in the database.\n";
} else {
    echo "Latest claims:\n";
    foreach ($rows as $row) {
        printf(
            "  [#%d] %s (%s) | %-12s | Rs.%-8.2f | %-16s | %s\n",
            $row['reimbursement_id'],
            $row['employee_name'],
            $row['employee_code'],
            $row['claim_type'],
            $row['amount'],
            $row['status'],
            $row['created_at']
        );
    }
}

// Check approval workflow statuses
echo "\nStatus breakdown:\n";
$statuses = $db->query("SELECT status, COUNT(*) as cnt FROM reimbursements GROUP BY status")->fetchAll(PDO::FETCH_ASSOC);
foreach ($statuses as $s) {
    echo "  {$s['status']}: {$s['cnt']}\n";
}
