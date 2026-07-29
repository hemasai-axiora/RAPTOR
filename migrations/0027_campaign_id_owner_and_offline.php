<?php
/**
 * Migration 0027: Add campaign_code, owner_employee_id FK, campaign_type, and offline campaign fields to campaigns table.
 */

echo "Starting Migration 0027: Campaign ID, Campaign Owner, and Offline Fields...\n";

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

// 1. Add campaign_code column if not exists
if (!$columnExists($db, 'campaigns', 'campaign_code')) {
    $db->exec("ALTER TABLE campaigns ADD COLUMN campaign_code VARCHAR(20) NULL UNIQUE AFTER campaign_id");
    echo "    + Added campaign_code column to campaigns table\n";
} else {
    echo "    = campaign_code column already exists\n";
}

// 2. Add owner_employee_id column if not exists
if (!$columnExists($db, 'campaigns', 'owner_employee_id')) {
    $db->exec("ALTER TABLE campaigns ADD COLUMN owner_employee_id INT NULL AFTER client_id");
    try {
        $db->exec("ALTER TABLE campaigns ADD CONSTRAINT fk_campaigns_owner_employee FOREIGN KEY (owner_employee_id) REFERENCES employees(employee_id) ON DELETE SET NULL");
        echo "    + Added owner_employee_id column with FK constraint to employees(employee_id)\n";
    } catch (Exception $e) {
        echo "    + Added owner_employee_id column (FK constraint note: " . $e->getMessage() . ")\n";
    }
} else {
    echo "    = owner_employee_id column already exists\n";
}

// 3. Add campaign_type column if not exists
if (!$columnExists($db, 'campaigns', 'campaign_type')) {
    $db->exec("ALTER TABLE campaigns ADD COLUMN campaign_type ENUM('online', 'offline') NOT NULL DEFAULT 'online' AFTER channel");
    echo "    + Added campaign_type column to campaigns table\n";
} else {
    echo "    = campaign_type column already exists\n";
}

// 4. Add offline-specific fields if not exist
if (!$columnExists($db, 'campaigns', 'vendor_name')) {
    $db->exec("ALTER TABLE campaigns ADD COLUMN vendor_name VARCHAR(255) NULL AFTER campaign_type");
    echo "    + Added vendor_name column to campaigns table\n";
}

if (!$columnExists($db, 'campaigns', 'location')) {
    $db->exec("ALTER TABLE campaigns ADD COLUMN location VARCHAR(255) NULL AFTER vendor_name");
    echo "    + Added location column to campaigns table\n";
}

if (!$columnExists($db, 'campaigns', 'reach_estimate')) {
    $db->exec("ALTER TABLE campaigns ADD COLUMN reach_estimate INT NULL AFTER location");
    echo "    + Added reach_estimate column to campaigns table\n";
}

if (!$columnExists($db, 'campaigns', 'proof_of_execution')) {
    $db->exec("ALTER TABLE campaigns ADD COLUMN proof_of_execution VARCHAR(255) NULL AFTER reach_estimate");
    echo "    + Added proof_of_execution column to campaigns table\n";
}

// 5. Backfill missing campaign_code values for existing campaigns
$year = date('Y');
$stmt = $db->query("SELECT campaign_id FROM campaigns WHERE campaign_code IS NULL OR campaign_code = '' ORDER BY campaign_id ASC");
$campaignsToUpdate = $stmt->fetchAll(PDO::FETCH_COLUMN);

if (!empty($campaignsToUpdate)) {
    $updateStmt = $db->prepare("UPDATE campaigns SET campaign_code = :code WHERE campaign_id = :id");
    foreach ($campaignsToUpdate as $index => $id) {
        $code = sprintf("CMP-%s-%05d", $year, (int) $id);
        $updateStmt->execute([':code' => $code, ':id' => (int) $id]);
    }
    echo "    + Backfilled " . count($campaignsToUpdate) . " existing campaigns with unique campaign_code values.\n";
}

echo "Migration 0027 complete.\n";
