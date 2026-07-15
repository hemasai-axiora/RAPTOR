<?php
/**
 * Sprint 7 - Task proof uploads, carry-forward, progress, and review workflow.
 */

$columnExists = function (PDO $db, string $table, string $column): bool {
    $stmt = $db->prepare(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c"
    );
    $stmt->execute([':t' => $table, ':c' => $column]);
    return (int) $stmt->fetchColumn() > 0;
};

$indexExists = function (PDO $db, string $table, string $index): bool {
    $stmt = $db->prepare(
        "SELECT COUNT(*) FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND INDEX_NAME = :i"
    );
    $stmt->execute([':t' => $table, ':i' => $index]);
    return (int) $stmt->fetchColumn() > 0;
};

$fkExists = function (PDO $db, string $table, string $fk): bool {
    $stmt = $db->prepare(
        "SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
         WHERE CONSTRAINT_SCHEMA = DATABASE()
           AND TABLE_NAME = :t AND CONSTRAINT_NAME = :fk"
    );
    $stmt->execute([':t' => $table, ':fk' => $fk]);
    return (int) $stmt->fetchColumn() > 0;
};

$addColumn = function (PDO $db, string $table, string $column, string $ddl) use ($columnExists) {
    if (!$columnExists($db, $table, $column)) {
        $db->exec("ALTER TABLE `$table` ADD COLUMN $ddl");
        echo "    + $table.$column added\n";
    }
};

$addIndex = function (PDO $db, string $table, string $index, string $ddl) use ($indexExists) {
    if (!$indexExists($db, $table, $index)) {
        $db->exec("ALTER TABLE `$table` ADD INDEX `$index` $ddl");
        echo "    + $table.$index added\n";
    }
};

$addFk = function (PDO $db, string $table, string $fk, string $ddl) use ($fkExists) {
    if (!$fkExists($db, $table, $fk)) {
        $db->exec("ALTER TABLE `$table` ADD CONSTRAINT `$fk` $ddl");
        echo "    + $table FK $fk added\n";
    }
};

$addColumn($db, 'tasks', 'start_date', "start_date DATETIME NULL AFTER description");
$addColumn($db, 'tasks', 'completed_at', "completed_at DATETIME NULL AFTER status");
$addColumn($db, 'tasks', 'progress_percent', "progress_percent INT NOT NULL DEFAULT 0 AFTER completed_at");
$addColumn($db, 'tasks', 'estimated_hours', "estimated_hours DECIMAL(5,2) NOT NULL DEFAULT 0.00 AFTER progress_percent");
$addColumn($db, 'tasks', 'actual_hours', "actual_hours DECIMAL(5,2) NOT NULL DEFAULT 0.00 AFTER estimated_hours");
$addColumn($db, 'tasks', 'remarks', "remarks TEXT NULL AFTER actual_hours");
$addColumn($db, 'tasks', 'proof_url', "proof_url VARCHAR(255) NULL AFTER remarks");
$addColumn($db, 'tasks', 'is_carry_forward', "is_carry_forward BOOLEAN NOT NULL DEFAULT FALSE AFTER proof_url");
$addColumn($db, 'tasks', 'source_task_id', "source_task_id INT NULL AFTER is_carry_forward");
$addColumn($db, 'tasks', 'review_status', "review_status ENUM('not_submitted','pending_review','approved','rejected') NOT NULL DEFAULT 'not_submitted' AFTER source_task_id");
$addColumn($db, 'tasks', 'reviewed_by', "reviewed_by INT NULL AFTER review_status");
$addColumn($db, 'tasks', 'reviewed_at', "reviewed_at DATETIME NULL AFTER reviewed_by");
$addColumn($db, 'tasks', 'review_remark', "review_remark VARCHAR(255) NULL AFTER reviewed_at");

$addIndex($db, 'tasks', 'idx_tasks_assignee_status', '(assigned_to_user_id, status)');
$addIndex($db, 'tasks', 'idx_tasks_review', '(review_status, completed_at)');
$addIndex($db, 'tasks', 'idx_tasks_source', '(source_task_id)');

$addFk($db, 'tasks', 'fk_tasks_source', 'FOREIGN KEY (source_task_id) REFERENCES tasks(task_id) ON DELETE SET NULL');
$addFk($db, 'tasks', 'fk_tasks_reviewer', 'FOREIGN KEY (reviewed_by) REFERENCES users(user_id) ON DELETE SET NULL');

echo "  [OK] task proof/review schema ensured.\n";
