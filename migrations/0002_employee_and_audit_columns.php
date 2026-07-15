<?php
/**
 * Sprint 1 — Idempotent column additions.
 * Runs via bin/migrate.php with $db (PDO) in scope. Guarded with column
 * existence checks because MySQL 8 has no ADD COLUMN IF NOT EXISTS and some
 * of these columns may already exist from legacy bin/alter_*.php scripts.
 */

/** Add a column only if the table doesn't already have it. */
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

/** Add a foreign key only if it's not already defined. */
$addFk = function (PDO $db, string $table, string $fkName, string $ddl) {
    $stmt = $db->prepare(
        "SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
         WHERE CONSTRAINT_SCHEMA = DATABASE()
           AND TABLE_NAME = :t AND CONSTRAINT_NAME = :n"
    );
    $stmt->execute([':t' => $table, ':n' => $fkName]);
    if ((int) $stmt->fetchColumn() === 0) {
        $db->exec("ALTER TABLE `$table` ADD CONSTRAINT `$fkName` $ddl");
        echo "    + $table FK $fkName added\n";
    } else {
        echo "    = $table FK $fkName already present\n";
    }
};

// --- employees: org linkage columns ---
$addColumn($db, 'employees', 'employee_code',        "employee_code VARCHAR(50) NULL UNIQUE AFTER user_id");
$addColumn($db, 'employees', 'reporting_manager_id', "reporting_manager_id INT NULL AFTER job_title");
$addColumn($db, 'employees', 'team_id',              "team_id INT NULL AFTER reporting_manager_id");
$addColumn($db, 'employees', 'branch_id',            "branch_id INT NULL AFTER team_id");

$addFk($db, 'employees', 'fk_emp_manager', "FOREIGN KEY (reporting_manager_id) REFERENCES users(user_id) ON DELETE SET NULL");
$addFk($db, 'employees', 'fk_emp_team',    "FOREIGN KEY (team_id) REFERENCES teams(team_id) ON DELETE SET NULL");
$addFk($db, 'employees', 'fk_emp_branch',  "FOREIGN KEY (branch_id) REFERENCES branches(branch_id) ON DELETE SET NULL");

// --- activity_logs: richer audit trail ---
$addColumn($db, 'activity_logs', 'entity_type', "entity_type VARCHAR(50) NULL AFTER action");
$addColumn($db, 'activity_logs', 'entity_id',   "entity_id INT NULL AFTER entity_type");
$addColumn($db, 'activity_logs', 'before_json', "before_json JSON NULL AFTER entity_id");
$addColumn($db, 'activity_logs', 'after_json',  "after_json JSON NULL AFTER before_json");

echo "  [OK] employee & audit columns ensured.\n";
