<?php
/**
 * Migration 0045: Pre-Payroll Processing Checklist, Salary Holds & Arrears
 */

echo "Starting Migration 0045: Pre-Payroll Processing Checklist...\n";

// 1. Create payroll_checklists table
$db->exec("
CREATE TABLE IF NOT EXISTS payroll_checklists (
    checklist_id INT AUTO_INCREMENT PRIMARY KEY,
    month_year VARCHAR(7) NOT NULL UNIQUE COMMENT 'Format: YYYY-MM',
    status ENUM('in_progress', 'completed') DEFAULT 'in_progress',
    completed_by INT NULL,
    completed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (completed_by) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");
echo "    + payroll_checklists table ensured\n";

// 2. Create payroll_checklist_items table
$db->exec("
CREATE TABLE IF NOT EXISTS payroll_checklist_items (
    item_id INT AUTO_INCREMENT PRIMARY KEY,
    checklist_id INT NOT NULL,
    item_key VARCHAR(50) NOT NULL,
    item_title VARCHAR(150) NOT NULL,
    item_description TEXT NULL,
    status ENUM('pending', 'in_progress', 'completed') DEFAULT 'pending',
    remarks TEXT NULL,
    verified_by INT NULL,
    verified_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_check_item (checklist_id, item_key),
    FOREIGN KEY (checklist_id) REFERENCES payroll_checklists(checklist_id) ON DELETE CASCADE,
    FOREIGN KEY (verified_by) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");
echo "    + payroll_checklist_items table ensured\n";

// 3. Create payroll_salary_holds table
$db->exec("
CREATE TABLE IF NOT EXISTS payroll_salary_holds (
    hold_id INT AUTO_INCREMENT PRIMARY KEY,
    month_year VARCHAR(7) NOT NULL,
    employee_id INT NOT NULL,
    reason VARCHAR(255) NOT NULL,
    hold_by INT NOT NULL,
    status ENUM('on_hold', 'released') DEFAULT 'on_hold',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_emp_hold (month_year, employee_id),
    FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE,
    FOREIGN KEY (hold_by) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");
echo "    + payroll_salary_holds table ensured\n";

// 4. Create payroll_arrears table
$db->exec("
CREATE TABLE IF NOT EXISTS payroll_arrears (
    arrear_id INT AUTO_INCREMENT PRIMARY KEY,
    month_year VARCHAR(7) NOT NULL,
    employee_id INT NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    reason VARCHAR(255) NOT NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");
echo "    + payroll_arrears table ensured\n";

echo "Migration 0045 completed successfully.\n";
