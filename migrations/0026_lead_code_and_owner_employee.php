<?php
/**
 * Migration 0026: Add lead_code business identifier and owner_employee_id FK to leads table.
 */

echo "Starting Migration 0026: Lead ID and Owner Employee FK...\n";

// Helper function to check if column exists
$columnExists = function (PDO $db, string $table, string $column): bool {
    try {
        $stmt = $db->prepare("SHOW COLUMNS FROM {$table} LIKE :col");
        $stmt->execute([':col' => $column]);
        return (bool) $stmt->fetch();
    } catch (Exception $e) {
        return false;
    }
};

// 1. Add lead_code column if not exists
if (!$columnExists($db, 'leads', 'lead_code')) {
    $db->exec("ALTER TABLE leads ADD COLUMN lead_code VARCHAR(20) NULL UNIQUE AFTER lead_id");
    echo "    + Added lead_code column to leads table\n";
} else {
    echo "    = lead_code column already exists\n";
}

// 2. Add owner_employee_id column if not exists
if (!$columnExists($db, 'leads', 'owner_employee_id')) {
    $db->exec("ALTER TABLE leads ADD COLUMN owner_employee_id INT NULL AFTER client_id");
    try {
        $db->exec("ALTER TABLE leads ADD CONSTRAINT fk_leads_owner_employee FOREIGN KEY (owner_employee_id) REFERENCES employees(employee_id) ON DELETE SET NULL");
        echo "    + Added owner_employee_id column with FK constraint to employees(employee_id)\n";
    } catch (Exception $e) {
        echo "    + Added owner_employee_id column (FK constraint warning: " . $e->getMessage() . ")\n";
    }
} else {
    echo "    = owner_employee_id column already exists\n";
}

// 3. Backfill missing lead_code values for existing leads
$year = date('Y');
$stmt = $db->query("SELECT lead_id FROM leads WHERE lead_code IS NULL OR lead_code = '' ORDER BY lead_id ASC");
$leadsToUpdate = $stmt->fetchAll(PDO::FETCH_COLUMN);

if (!empty($leadsToUpdate)) {
    $updateStmt = $db->prepare("UPDATE leads SET lead_code = :code WHERE lead_id = :id");
    foreach ($leadsToUpdate as $index => $id) {
        $code = sprintf("LD-%s-%05d", $year, (int) $id);
        $updateStmt->execute([':code' => $code, ':id' => (int) $id]);
    }
    echo "    + Backfilled " . count($leadsToUpdate) . " existing leads with unique lead_code values.\n";
}

// 4. Backfill owner_employee_id from assigned_to_user_id
$db->exec("
    UPDATE leads l
    JOIN employees e ON l.assigned_to_user_id = e.user_id
    SET l.owner_employee_id = e.employee_id
    WHERE l.owner_employee_id IS NULL AND l.assigned_to_user_id IS NOT NULL
");
echo "    + Backfilled owner_employee_id from assigned_to_user_id mapping.\n";

echo "Migration 0026 complete.\n";
