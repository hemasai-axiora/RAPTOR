<?php
/**
 * Migration 0024 — Attendance Approval Workflow
 *
 * Adds new tracking columns to the attendance table:
 *  - `attendance_status` ENUM('Pending', 'Approved', 'Rejected') NOT NULL DEFAULT 'Pending'
 *  - `approved_at` DATETIME NULL
 *  - `rejection_reason` TEXT NULL
 *  - `requested_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP NULL
 *
 * Migrates old `approval_status` data to the new structure, then drops `approval_status`.
 */

$columnExists = function (PDO $db, string $table, string $column): bool {
    $stmt = $db->prepare(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c"
    );
    $stmt->execute([':t' => $table, ':c' => $column]);
    return (int) $stmt->fetchColumn() > 0;
};

// 1. Add requested_at
if (!$columnExists($db, 'attendance', 'requested_at')) {
    $db->exec("ALTER TABLE attendance ADD COLUMN requested_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP AFTER remarks");
    echo "    + attendance.requested_at added\n";
}

// 2. Add approved_at
if (!$columnExists($db, 'attendance', 'approved_at')) {
    $db->exec("ALTER TABLE attendance ADD COLUMN approved_at DATETIME NULL AFTER requested_at");
    echo "    + attendance.approved_at added\n";
}

// 3. Add rejection_reason
if (!$columnExists($db, 'attendance', 'rejection_reason')) {
    $db->exec("ALTER TABLE attendance ADD COLUMN rejection_reason TEXT NULL AFTER approved_at");
    echo "    + attendance.rejection_reason added\n";
}

// 4. Add attendance_status
if (!$columnExists($db, 'attendance', 'attendance_status')) {
    $db->exec("ALTER TABLE attendance ADD COLUMN attendance_status ENUM('Pending', 'Approved', 'Rejected') NOT NULL DEFAULT 'Pending' AFTER rejection_reason");
    echo "    + attendance.attendance_status added\n";

    // 5. Backfill values
    if ($columnExists($db, 'attendance', 'approval_status')) {
        $db->exec("UPDATE attendance SET attendance_status = 'Pending' WHERE approval_status = 'pending'");
        $db->exec("UPDATE attendance SET attendance_status = 'Approved' WHERE approval_status IN ('approved', 'auto')");
        $db->exec("UPDATE attendance SET attendance_status = 'Rejected' WHERE approval_status = 'rejected'");
        $db->exec("UPDATE attendance SET requested_at = login_at WHERE login_at IS NOT NULL");
        $db->exec("UPDATE attendance SET approved_at = updated_at WHERE approval_status IN ('approved', 'rejected')");
        echo "    + migrated legacy approval_status data to attendance_status\n";
    }
}

// 6. Safely drop legacy approval_status column
if ($columnExists($db, 'attendance', 'approval_status')) {
    $db->exec("ALTER TABLE attendance DROP COLUMN approval_status");
    echo "    + legacy attendance.approval_status column dropped\n";
}
