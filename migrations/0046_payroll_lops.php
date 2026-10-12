<?php
/**
 * Migration 0046: Manual LOP (Loss of Pay) Adjustments Table
 */

$db->exec("
CREATE TABLE IF NOT EXISTS payroll_lops (
    lop_id INT AUTO_INCREMENT PRIMARY KEY,
    month_year VARCHAR(7) NOT NULL,
    employee_id INT NOT NULL,
    lop_days DECIMAL(4,1) NOT NULL,
    reason TEXT NULL,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_emp_month_lop (month_year, employee_id),
    FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");
echo "    + payroll_lops table ensured\n";
