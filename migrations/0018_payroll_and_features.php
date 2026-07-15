<?php
/**
 * Sprint 2 — Database Schema Update for Payroll and Features.
 * Runs via bin/migrate.php with $db (PDO) in scope.
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

// 1. Alter employees table to add personal, payroll, and compliance fields
$addColumn($db, 'employees', 'phone_number',      "phone_number VARCHAR(20) NULL AFTER job_title");
$addColumn($db, 'employees', 'salary',            "salary DECIMAL(12,2) NULL AFTER phone_number");
$addColumn($db, 'employees', 'date_of_joining',   "date_of_joining DATE NULL AFTER salary");
$addColumn($db, 'employees', 'date_of_birth',     "date_of_birth DATE NULL AFTER date_of_joining");
$addColumn($db, 'employees', 'employment_type',   "employment_type ENUM('Full-time', 'Part-time', 'Contract', 'Intern') DEFAULT 'Full-time' AFTER date_of_birth");
$addColumn($db, 'employees', 'work_location',     "work_location ENUM('Remote', 'Office', 'Hybrid') DEFAULT 'Office' AFTER employment_type");
$addColumn($db, 'employees', 'profile_photo',     "profile_photo VARCHAR(255) NULL AFTER work_location");
$addColumn($db, 'employees', 'bio',               "bio TEXT NULL AFTER profile_photo");
$addColumn($db, 'employees', 'emergency_contact', "emergency_contact VARCHAR(255) NULL AFTER bio");
$addColumn($db, 'employees', 'pan_number',        "pan_number VARCHAR(50) NULL AFTER emergency_contact");
$addColumn($db, 'employees', 'aadhaar_number',    "aadhaar_number VARCHAR(50) NULL AFTER pan_number");
$addColumn($db, 'employees', 'uan',               "uan VARCHAR(50) NULL AFTER aadhaar_number");
$addColumn($db, 'employees', 'pf_applicable',     "pf_applicable TINYINT(1) DEFAULT 0 AFTER uan");
$addColumn($db, 'employees', 'esic_number',       "esic_number VARCHAR(50) NULL AFTER pf_applicable");
$addColumn($db, 'employees', 'pay_grade',         "pay_grade VARCHAR(50) NULL AFTER esic_number");

// 2. Alter users table for forced password reset
$addColumn($db, 'users', 'force_password_reset', "force_password_reset TINYINT(1) DEFAULT 0 AFTER status");

// 3. Alter invoices table for billing address, currency, and conversion rate
$addColumn($db, 'invoices', 'billing_address', "billing_address TEXT NULL AFTER due_date");
$addColumn($db, 'invoices', 'currency',        "currency ENUM('USD', 'INR') DEFAULT 'USD' AFTER billing_address");
$addColumn($db, 'invoices', 'conversion_rate', "conversion_rate DECIMAL(10,4) DEFAULT 1.0000 AFTER currency");

// 4. Create new bank accounts table
$db->exec("
CREATE TABLE IF NOT EXISTS bank_accounts (
    bank_account_id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    account_holder_name VARCHAR(150) NOT NULL,
    bank_name VARCHAR(100) NOT NULL,
    account_number VARCHAR(100) NOT NULL,
    ifsc_code VARCHAR(50) NOT NULL,
    branch_name VARCHAR(150) NULL,
    account_type ENUM('Savings', 'Current') DEFAULT 'Savings',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");
echo "    + bank_accounts table ensured\n";

// 5. Create client contacts / stakeholders table
$db->exec("
CREATE TABLE IF NOT EXISTS client_contacts (
    contact_id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    phone VARCHAR(20) NULL,
    role_or_title VARCHAR(100) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(client_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");
echo "    + client_contacts table ensured\n";

// 6. Create salary structures table
$db->exec("
CREATE TABLE IF NOT EXISTS salary_structures (
    salary_structure_id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL UNIQUE,
    salary_type ENUM('Monthly', 'Hourly') DEFAULT 'Monthly',
    basic_salary DECIMAL(12,2) DEFAULT 0.00,
    hra DECIMAL(12,2) DEFAULT 0.00,
    special_allowance DECIMAL(12,2) DEFAULT 0.00,
    medical_allowance DECIMAL(12,2) DEFAULT 0.00,
    travel_allowance DECIMAL(12,2) DEFAULT 0.00,
    bonus DECIMAL(12,2) DEFAULT 0.00,
    other_earnings DECIMAL(12,2) DEFAULT 0.00,
    pf DECIMAL(12,2) DEFAULT 0.00,
    esic DECIMAL(12,2) DEFAULT 0.00,
    professional_tax DECIMAL(12,2) DEFAULT 0.00,
    tds DECIMAL(12,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");
echo "    + salary_structures table ensured\n";

// 7. Create payroll runs table
$db->exec("
CREATE TABLE IF NOT EXISTS payroll_runs (
    payroll_run_id INT AUTO_INCREMENT PRIMARY KEY,
    month_year VARCHAR(7) NOT NULL UNIQUE COMMENT 'Format: YYYY-MM',
    status ENUM('generated', 'approved', 'locked', 'released') DEFAULT 'generated',
    created_by INT NULL,
    approved_by INT NULL,
    released_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL,
    FOREIGN KEY (approved_by) REFERENCES users(user_id) ON DELETE SET NULL,
    FOREIGN KEY (released_by) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");
echo "    + payroll_runs table ensured\n";

// 8. Create payroll details table
$db->exec("
CREATE TABLE IF NOT EXISTS payroll_details (
    payroll_detail_id INT AUTO_INCREMENT PRIMARY KEY,
    payroll_run_id INT NOT NULL,
    employee_id INT NOT NULL,
    working_days INT DEFAULT 0,
    present_days INT DEFAULT 0,
    absent_days INT DEFAULT 0,
    leave_days INT DEFAULT 0,
    overtime_hours DECIMAL(5,2) DEFAULT 0.00,
    late_marks INT DEFAULT 0,
    basic_salary DECIMAL(12,2) DEFAULT 0.00,
    hra DECIMAL(12,2) DEFAULT 0.00,
    special_allowance DECIMAL(12,2) DEFAULT 0.00,
    medical_allowance DECIMAL(12,2) DEFAULT 0.00,
    travel_allowance DECIMAL(12,2) DEFAULT 0.00,
    bonus DECIMAL(12,2) DEFAULT 0.00,
    other_earnings DECIMAL(12,2) DEFAULT 0.00,
    pf DECIMAL(12,2) DEFAULT 0.00,
    esic DECIMAL(12,2) DEFAULT 0.00,
    professional_tax DECIMAL(12,2) DEFAULT 0.00,
    tds DECIMAL(12,2) DEFAULT 0.00,
    gross_salary DECIMAL(12,2) DEFAULT 0.00,
    total_deductions DECIMAL(12,2) DEFAULT 0.00,
    net_salary DECIMAL(12,2) DEFAULT 0.00,
    payment_status ENUM('unpaid', 'paid') DEFAULT 'unpaid',
    paid_at TIMESTAMP NULL,
    FOREIGN KEY (payroll_run_id) REFERENCES payroll_runs(payroll_run_id) ON DELETE CASCADE,
    FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE,
    UNIQUE KEY uq_emp_run (payroll_run_id, employee_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");
echo "    + payroll_details table ensured\n";

// 9. Create bonuses table
$db->exec("
CREATE TABLE IF NOT EXISTS bonuses (
    bonus_id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    bonus_type ENUM('Performance', 'Festival', 'Sales Incentives', 'Referral') NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    description TEXT,
    status ENUM('pending', 'approved', 'paid') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");
echo "    + bonuses table ensured\n";

// 10. Create reimbursements table
$db->exec("
CREATE TABLE IF NOT EXISTS reimbursements (
    reimbursement_id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    claim_type ENUM('Travel', 'Fuel', 'Food', 'Internet', 'Medical', 'Other') NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    description TEXT,
    attachment_url VARCHAR(255) NULL,
    status ENUM('pending', 'manager_approved', 'finance_approved', 'rejected') DEFAULT 'pending',
    manager_id INT NULL,
    finance_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE,
    FOREIGN KEY (manager_id) REFERENCES users(user_id) ON DELETE SET NULL,
    FOREIGN KEY (finance_id) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");
echo "    + reimbursements table ensured\n";

// 11. Add finance role if it doesn't exist
$db->exec("INSERT IGNORE INTO roles (role_name, description) VALUES ('finance', 'Finance Officer — processes payroll and payments')");
$db->exec("INSERT IGNORE INTO roles (role_name, description) VALUES ('hr', 'HR Manager — manages employees and payroll')");
echo "    + payroll system roles ensured\n";

// 12. Ensure default conversion rate setting exists
$db->exec("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('billing.conversion_rate_usd_to_inr', '83.50')");
echo "    + global currency setting ensured\n";

echo "  [OK] payroll schema updates completed.\n";
