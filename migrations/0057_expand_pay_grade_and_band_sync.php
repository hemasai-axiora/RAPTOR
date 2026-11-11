<?php
// Migration 0057: Expand pay_grade column to VARCHAR(150)

$driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);

if ($driver === 'sqlite') {
    // In SQLite, columns are dynamically typed and ALTER TABLE MODIFY is not supported.
    echo "    ~ SQLite detected; skipping MODIFY COLUMN for employees.pay_grade\n";
} else {
    try {
        $db->exec("ALTER TABLE employees MODIFY COLUMN pay_grade VARCHAR(150) NULL");
        echo "    + employees.pay_grade expanded to VARCHAR(150)\n";
    } catch (Throwable $e) {
        echo "    ! employees.pay_grade modification notice: " . $e->getMessage() . "\n";
    }
}

