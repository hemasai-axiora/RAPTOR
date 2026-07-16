<?php
/**
 * Migration 0022 — HRMS Module Database Setup
 *
 * Adds:
 *  - Extends employees table with blood_group, address, experience_years, skills, documents_json.
 *  - Creates leave_requests, leave_approvals, attendance_approvals, holidays, leave_balances tables.
 *  - Seeds default leave balances for existing users.
 *  - Seeds holiday calendar.
 */

$columnExists = function (PDO $db, string $table, string $column): bool {
    $stmt = $db->prepare(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c"
    );
    $stmt->execute([':t' => $table, ':c' => $column]);
    return (int) $stmt->fetchColumn() > 0;
};

$tableExists = function (PDO $db, string $table): bool {
    $stmt = $db->prepare(
        "SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t"
    );
    $stmt->execute([':t' => $table]);
    return (int) $stmt->fetchColumn() > 0;
};

// ─── 1. Extend employees table ──────────────────────────────────────────────
if (!$columnExists($db, 'employees', 'blood_group')) {
    $db->exec("ALTER TABLE employees ADD COLUMN blood_group VARCHAR(10) NULL AFTER pay_grade");
    echo "    + employees.blood_group added\n";
}
if (!$columnExists($db, 'employees', 'address')) {
    $db->exec("ALTER TABLE employees ADD COLUMN address TEXT NULL AFTER blood_group");
    echo "    + employees.address added\n";
}
if (!$columnExists($db, 'employees', 'experience_years')) {
    $db->exec("ALTER TABLE employees ADD COLUMN experience_years DECIMAL(4,2) NULL AFTER address");
    echo "    + employees.experience_years added\n";
}
if (!$columnExists($db, 'employees', 'skills')) {
    $db->exec("ALTER TABLE employees ADD COLUMN skills TEXT NULL AFTER experience_years");
    echo "    + employees.skills added\n";
}
if (!$columnExists($db, 'employees', 'documents_json')) {
    $db->exec("ALTER TABLE employees ADD COLUMN documents_json TEXT NULL AFTER skills");
    echo "    + employees.documents_json added\n";
}

// ─── 2. Create leave_requests table ────────────────────────────────────────
if (!$tableExists($db, 'leave_requests')) {
    $db->exec("CREATE TABLE leave_requests (
        leave_request_id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        leave_type VARCHAR(50) NOT NULL,
        from_date DATE NOT NULL,
        to_date DATE NOT NULL,
        half_day TINYINT(1) NOT NULL DEFAULT 0,
        reason TEXT NOT NULL,
        supporting_document VARCHAR(255) NULL,
        status ENUM('pending_manager', 'pending_hr', 'approved', 'rejected', 'cancelled') NOT NULL DEFAULT 'pending_manager',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_lr_user (user_id),
        INDEX idx_lr_status (status),
        CONSTRAINT fk_lr_user FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    echo "    + leave_requests table created\n";
}

// ─── 3. Create leave_approvals table ───────────────────────────────────────
if (!$tableExists($db, 'leave_approvals')) {
    $db->exec("CREATE TABLE leave_approvals (
        leave_approval_id INT AUTO_INCREMENT PRIMARY KEY,
        leave_request_id INT NOT NULL,
        approver_id INT NOT NULL,
        stage ENUM('manager', 'hr') NOT NULL,
        status ENUM('approved', 'rejected') NOT NULL,
        comments TEXT NULL,
        ip_address VARCHAR(45) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_la_request (leave_request_id),
        CONSTRAINT fk_la_request FOREIGN KEY (leave_request_id) REFERENCES leave_requests (leave_request_id) ON DELETE CASCADE,
        CONSTRAINT fk_la_approver FOREIGN KEY (approver_id) REFERENCES users (user_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    echo "    + leave_approvals table created\n";
}

// ─── 4. Create attendance_approvals table ──────────────────────────────────
if (!$tableExists($db, 'attendance_approvals')) {
    $db->exec("CREATE TABLE attendance_approvals (
        attendance_approval_id INT AUTO_INCREMENT PRIMARY KEY,
        attendance_id BIGINT NOT NULL,
        approver_id INT NOT NULL,
        status ENUM('approved', 'rejected') NOT NULL,
        comments TEXT NULL,
        ip_address VARCHAR(45) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_aa_attendance (attendance_id),
        CONSTRAINT fk_aa_attendance FOREIGN KEY (attendance_id) REFERENCES attendance (attendance_id) ON DELETE CASCADE,
        CONSTRAINT fk_aa_approver FOREIGN KEY (approver_id) REFERENCES users (user_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    echo "    + attendance_approvals table created\n";
}

// ─── 5. Create holidays table ──────────────────────────────────────────────
if (!$tableExists($db, 'holidays')) {
    $db->exec("CREATE TABLE holidays (
        holiday_id INT AUTO_INCREMENT PRIMARY KEY,
        holiday_name VARCHAR(100) NOT NULL,
        holiday_date DATE NOT NULL UNIQUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    echo "    + holidays table created\n";

    // Seed default holidays for 2026
    $holidays = [
        ['New Year\'s Day', '2026-01-01'],
        ['Republic Day', '2026-01-26'],
        ['Good Friday', '2026-04-03'],
        ['Independence Day', '2026-08-15'],
        ['Gandhi Jayanti', '2026-10-02'],
        ['Diwali', '2026-11-08'],
        ['Christmas Day', '2026-12-25']
    ];
    $stmt = $db->prepare("INSERT IGNORE INTO holidays (holiday_name, holiday_date) VALUES (:name, :date)");
    foreach ($holidays as $h) {
        $stmt->execute([':name' => $h[0], ':date' => $h[1]]);
    }
    echo "    + 2026 holiday calendar seeded\n";
}

// ─── 6. Create leave_balances table ────────────────────────────────────────
if (!$tableExists($db, 'leave_balances')) {
    $db->exec("CREATE TABLE leave_balances (
        leave_balance_id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL UNIQUE,
        sick_leave DECIMAL(5,2) NOT NULL DEFAULT 12.00,
        casual_leave DECIMAL(5,2) NOT NULL DEFAULT 12.00,
        earned_leave DECIMAL(5,2) NOT NULL DEFAULT 15.00,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        CONSTRAINT fk_lb_user FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    echo "    + leave_balances table created\n";

    // Seed default balances for existing users
    $db->exec("INSERT IGNORE INTO leave_balances (user_id, sick_leave, casual_leave, earned_leave)
               SELECT user_id, 12.00, 12.00, 15.00 FROM users");
    echo "    + default leave balances seeded for all users\n";
}

echo "  [OK] HRMS database migration complete.\n";
