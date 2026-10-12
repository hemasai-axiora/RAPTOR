<?php
/**
 * Migration 0048: Add email subject and body tracking to payroll_details
 */

try {
    $colCheck = $db->query("SHOW COLUMNS FROM payroll_details LIKE 'email_subject'")->fetch();
    if (!$colCheck) {
        $db->exec("ALTER TABLE payroll_details ADD COLUMN email_subject VARCHAR(255) NULL AFTER email_error");
        echo "    + email_subject column added to payroll_details\n";
    }
} catch (Exception $e) {
    echo "    - error checking email_subject: " . $e->getMessage() . "\n";
}

try {
    $colCheck = $db->query("SHOW COLUMNS FROM payroll_details LIKE 'email_body'")->fetch();
    if (!$colCheck) {
        $db->exec("ALTER TABLE payroll_details ADD COLUMN email_body TEXT NULL AFTER email_subject");
        echo "    + email_body column added to payroll_details\n";
    }
} catch (Exception $e) {
    echo "    - error checking email_body: " . $e->getMessage() . "\n";
}
