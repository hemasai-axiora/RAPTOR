<?php
// Migration 0057: Expand pay_grade column to VARCHAR(150)
if ($tableExists($db, 'employees')) {
    try {
        $db->exec("ALTER TABLE employees MODIFY COLUMN pay_grade VARCHAR(150) NULL");
        echo "    + employees.pay_grade expanded to VARCHAR(150)\n";
    } catch (Exception $e) {
        echo "    ! employees.pay_grade modification notice: " . $e->getMessage() . "\n";
    }
}
