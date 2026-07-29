<?php
require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/core/Database.php';

try {
    $db = Database::getInstance()->getConnection();

    // Clean customers company_name
    $stmt = $db->query("SELECT customer_id, company_name FROM customers WHERE company_name REGEXP '[0-9]{10,}$'");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $updated = 0;
    foreach ($rows as $row) {
        $cleanName = preg_replace('/\s+\d{10,}$/', '', $row['company_name']);
        if ($cleanName && $cleanName !== $row['company_name']) {
            $uStmt = $db->prepare("UPDATE customers SET company_name = :name WHERE customer_id = :id");
            $uStmt->execute([':name' => $cleanName, ':id' => $row['customer_id']]);
            $updated++;
        }
    }
    echo "Cleaned {$updated} customer company name(s).\n";

    // Clean leads company_name
    $lStmt = $db->query("SELECT lead_id, company_name FROM leads WHERE company_name REGEXP '[0-9]{10,}$'");
    $lRows = $lStmt->fetchAll(PDO::FETCH_ASSOC);
    $lUpdated = 0;
    foreach ($lRows as $lRow) {
        $cleanLName = preg_replace('/\s+\d{10,}$/', '', $lRow['company_name']);
        if ($cleanLName && $cleanLName !== $lRow['company_name']) {
            $uLStmt = $db->prepare("UPDATE leads SET company_name = :name WHERE lead_id = :id");
            $uLStmt->execute([':name' => $cleanLName, ':id' => $lRow['lead_id']]);
            $lUpdated++;
        }
    }
    echo "Cleaned {$lUpdated} lead company name(s).\n";

} catch (Exception $e) {
    echo "Error cleaning names: " . $e->getMessage() . "\n";
}
