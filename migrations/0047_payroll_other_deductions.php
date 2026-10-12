<?php
/**
 * Migration 0047: Manual Other Deductions Table and payroll_details column
 */

$db->exec("
CREATE TABLE IF NOT EXISTS payroll_deductions (
    deduction_id INT AUTO_INCREMENT PRIMARY KEY,
    month_year VARCHAR(7) NOT NULL,
    employee_id INT NOT NULL,
    deduction_type VARCHAR(100) NOT NULL DEFAULT 'Other Deduction',
    amount DECIMAL(12,2) NOT NULL,
    reason TEXT NULL,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_month_emp (month_year, employee_id),
    FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");
echo "    + payroll_deductions table ensured\n";

try {
    $colCheck = $db->query("SHOW COLUMNS FROM payroll_details LIKE 'other_deductions'")->fetch();
    if (!$colCheck) {
        $db->exec("ALTER TABLE payroll_details ADD COLUMN other_deductions DECIMAL(12,2) DEFAULT 0.00 AFTER tds");
        echo "    + other_deductions column added to payroll_details\n";
    }
} catch (Exception $e) {}
