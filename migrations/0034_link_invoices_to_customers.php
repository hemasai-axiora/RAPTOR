<?php
/**
 * Migration 0034: Link Invoices to Customer Management & Lead Traceability
 */

require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/core/Database.php';

try {
    $db = Database::getInstance()->getConnection();
    echo "Applying Migration 0034: Link Invoices to Customers...\n";

    // 1. Modify client_id to be nullable
    $db->exec("ALTER TABLE invoices MODIFY client_id INT NULL");

    // 2. Add customer_id, customer_code, lead_id, lead_code columns if missing
    $columns = [
        'customer_id'   => "ALTER TABLE invoices ADD COLUMN customer_id INT NULL AFTER client_id, ADD INDEX idx_inv_customer (customer_id)",
        'customer_code' => "ALTER TABLE invoices ADD COLUMN customer_code VARCHAR(20) NULL AFTER customer_id",
        'lead_id'       => "ALTER TABLE invoices ADD COLUMN lead_id INT NULL AFTER customer_code, ADD INDEX idx_inv_lead (lead_id)",
        'lead_code'     => "ALTER TABLE invoices ADD COLUMN lead_code VARCHAR(20) NULL AFTER lead_id"
    ];

    $existingCols = $db->query("SHOW COLUMNS FROM invoices")->fetchAll(PDO::FETCH_COLUMN);

    foreach ($columns as $col => $sql) {
        if (!in_array($col, $existingCols, true)) {
            $db->exec($sql);
            echo "Added column '{$col}' to invoices table.\n";
        }
    }

    // 3. Best-effort backfill historical invoices by matching client company_name with customers table
    $stmt = $db->query("
        UPDATE invoices i
        JOIN clients cl ON i.client_id = cl.client_id
        JOIN customers c ON LOWER(TRIM(c.company_name)) = LOWER(TRIM(cl.company_name)) OR LOWER(TRIM(c.email)) = LOWER(TRIM(cl.email))
        LEFT JOIN leads l ON c.converted_from_lead_id = l.lead_id
        SET i.customer_id = c.customer_id,
            i.customer_code = c.customer_code,
            i.lead_id = c.converted_from_lead_id,
            i.lead_code = l.lead_code
        WHERE i.customer_id IS NULL
    ");
    $affected = $stmt->rowCount();
    echo "Backfilled {$affected} historical invoice(s) with matching customer and lead records.\n";

    echo "Migration 0034 completed successfully.\n";
} catch (Exception $e) {
    echo "Migration 0034 error: " . $e->getMessage() . "\n";
    exit(1);
}
