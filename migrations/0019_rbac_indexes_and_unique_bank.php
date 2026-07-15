<?php
/**
 * Migration 0019 - Add missing indexes and unique constraint on bank accounts.
 * Safe to execute on existing database.
 */

$indexExists = function (PDO $db, string $table, string $index): bool {
    $stmt = $db->prepare(
        "SELECT COUNT(*) FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND INDEX_NAME = :i"
    );
    $stmt->execute([':t' => $table, ':i' => $index]);
    return (int) $stmt->fetchColumn() > 0;
};

$addIndex = function (PDO $db, string $table, string $index, string $ddl) use ($indexExists) {
    if (!$indexExists($db, $table, $index)) {
        $db->exec("ALTER TABLE `$table` ADD $ddl");
        echo "    + $table.$index added\n";
    } else {
        echo "    = $table.$index already exists\n";
    }
};

// 1. Deduplicate bank_accounts safely keeping only the latest entry per employee_id
try {
    $db->exec("
        DELETE b1 FROM bank_accounts b1
        INNER JOIN bank_accounts b2 
        WHERE b1.bank_account_id < b2.bank_account_id 
          AND b1.employee_id = b2.employee_id
    ");
    echo "    + bank_accounts deduplicated safely\n";
} catch (Exception $e) {
    echo "    = bank_accounts deduplication skipped/failed: " . $e->getMessage() . "\n";
}

// 2. Add UNIQUE constraint to bank_accounts(employee_id)
$addIndex($db, 'bank_accounts', 'uq_bank_employee', 'UNIQUE KEY `uq_bank_employee` (`employee_id`)');

// 3. Add other query optimization indexes
$addIndex($db, 'reimbursements', 'idx_reimb_emp_status_date', 'INDEX `idx_reimb_emp_status_date` (`employee_id`, `status`, `created_at`)');
$addIndex($db, 'payroll_details', 'idx_pd_employee', 'INDEX `idx_pd_employee` (`employee_id`)');
$addIndex($db, 'bonuses', 'idx_bonus_emp_status', 'INDEX `idx_bonus_emp_status` (`employee_id`, `status`)');
$addIndex($db, 'leads', 'idx_leads_assigned_status', 'INDEX `idx_leads_assigned_status` (`assigned_to_user_id`, `status`)');
