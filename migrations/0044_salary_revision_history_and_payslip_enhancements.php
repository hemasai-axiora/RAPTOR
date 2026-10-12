<?php
/**
 * Migration 0044: Salary Revision History & Enhanced Payslip Fields
 * Tracks employee salary revisions, stores complete revision audit trail,
 * and enhances payroll details with exact PF/Gratuity/CTC and email delivery tracking.
 */

$addColumn = function (PDO $db, string $table, string $column, string $ddl) {
    $stmt = $db->query("DESCRIBE `$table`");
    $cols = $stmt->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array($column, $cols, true)) {
        $db->exec("ALTER TABLE `$table` ADD COLUMN $ddl");
        echo "    + $table.$column added\n";
    } else {
        echo "    = $table.$column already present\n";
    }
};

echo "Starting Migration 0044: Salary Revision History & Payslip Enhancements...\n";

// 1. Create salary_revision_history table
$db->exec("
CREATE TABLE IF NOT EXISTS salary_revision_history (
    revision_id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    revision_date DATE NOT NULL,
    effective_date DATE NOT NULL,
    revised_by INT NULL,
    reason VARCHAR(255) NULL,
    previous_basic DECIMAL(12,2) DEFAULT 0.00,
    previous_hra DECIMAL(12,2) DEFAULT 0.00,
    previous_special_allowance DECIMAL(12,2) DEFAULT 0.00,
    previous_other_allowance DECIMAL(12,2) DEFAULT 0.00,
    previous_gross DECIMAL(12,2) DEFAULT 0.00,
    previous_ctc DECIMAL(12,2) DEFAULT 0.00,
    new_basic DECIMAL(12,2) DEFAULT 0.00,
    new_hra DECIMAL(12,2) DEFAULT 0.00,
    new_special_allowance DECIMAL(12,2) DEFAULT 0.00,
    new_other_allowance DECIMAL(12,2) DEFAULT 0.00,
    new_gross DECIMAL(12,2) DEFAULT 0.00,
    new_ctc DECIMAL(12,2) DEFAULT 0.00,
    details_json TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_rev_emp (employee_id),
    FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE,
    FOREIGN KEY (revised_by) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");
echo "    + salary_revision_history table ensured\n";

// 2. Add columns to salary_structures
$addColumn($db, 'salary_structures', 'other_allowance', "other_allowance DECIMAL(12,2) DEFAULT 0.00 AFTER special_allowance");
$addColumn($db, 'salary_structures', 'employer_pf',    "employer_pf DECIMAL(12,2) DEFAULT 0.00 AFTER pf");
$addColumn($db, 'salary_structures', 'gratuity',       "gratuity DECIMAL(12,2) DEFAULT 0.00 AFTER employer_pf");

// 3. Add columns to payroll_details
$addColumn($db, 'payroll_details', 'other_allowance',          "other_allowance DECIMAL(12,2) DEFAULT 0.00 AFTER special_allowance");
$addColumn($db, 'payroll_details', 'actual_basic',             "actual_basic DECIMAL(12,2) DEFAULT 0.00 AFTER other_earnings");
$addColumn($db, 'payroll_details', 'actual_hra',               "actual_hra DECIMAL(12,2) DEFAULT 0.00 AFTER actual_basic");
$addColumn($db, 'payroll_details', 'actual_special_allowance', "actual_special_allowance DECIMAL(12,2) DEFAULT 0.00 AFTER actual_hra");
$addColumn($db, 'payroll_details', 'actual_other_allowance',   "actual_other_allowance DECIMAL(12,2) DEFAULT 0.00 AFTER actual_special_allowance");
$addColumn($db, 'payroll_details', 'employer_pf',              "employer_pf DECIMAL(12,2) DEFAULT 0.00 AFTER tds");
$addColumn($db, 'payroll_details', 'gratuity',                 "gratuity DECIMAL(12,2) DEFAULT 0.00 AFTER employer_pf");
$addColumn($db, 'payroll_details', 'ctc',                      "ctc DECIMAL(12,2) DEFAULT 0.00 AFTER net_salary");
$addColumn($db, 'payroll_details', 'email_status',             "email_status ENUM('pending', 'sent', 'failed') DEFAULT 'pending' AFTER paid_at");
$addColumn($db, 'payroll_details', 'email_sent_at',            "email_sent_at TIMESTAMP NULL AFTER email_status");
$addColumn($db, 'payroll_details', 'email_error',              "email_error VARCHAR(255) NULL AFTER email_sent_at");
$addColumn($db, 'payroll_details', 'email_attempts',           "email_attempts INT DEFAULT 0 AFTER email_error");

echo "Migration 0044 completed successfully.\n";
