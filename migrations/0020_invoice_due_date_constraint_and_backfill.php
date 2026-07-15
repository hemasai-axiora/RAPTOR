<?php
/**
 * Migration 0020 - Fix invalid invoice due dates and add CHECK constraint.
 */

// 1. Backfill bad/historic due dates to created_at + 30 days
try {
    $db->exec("
        UPDATE invoices 
        SET due_date = DATE_ADD(created_at, INTERVAL 30 DAY) 
        WHERE due_date < '2000-01-01' 
           OR due_date < DATE(created_at)
    ");
    echo "    + bad/historic invoice due dates backfilled\n";
} catch (Exception $e) {
    echo "    = invoice due date backfill failed/skipped: " . $e->getMessage() . "\n";
}

// 2. Add CHECK constraint: due_date >= DATE(created_at)
try {
    $db->exec("
        ALTER TABLE invoices 
        ADD CONSTRAINT chk_due_date CHECK (due_date >= DATE(created_at))
    ");
    echo "    + chk_due_date CHECK constraint added\n";
} catch (Exception $e) {
    echo "    = chk_due_date CHECK constraint skipped/failed (possibly already exists): " . $e->getMessage() . "\n";
}
