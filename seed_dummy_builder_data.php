<?php
require __DIR__ . '/app/config/config.php';
require __DIR__ . '/app/core/Database.php';

$db = Database::getInstance()->getConnection();

// Get valid client_id
$clientId = (int)$db->query("SELECT MIN(client_id) FROM clients")->fetchColumn();
if ($clientId <= 0) {
    $db->exec("INSERT INTO clients (company_name, created_at) VALUES ('Default Client', NOW())");
    $clientId = (int)$db->lastInsertId();
}

// Get valid user_id
$userId = (int)$db->query("SELECT MIN(user_id) FROM users")->fetchColumn();
if ($userId <= 0) $userId = 31;

echo "Seeding dummy data for Custom Dashboard Builder (client_id: $clientId, user_id: $userId)...\n";

// 1. Seed Leads with valid enum('new','contacted','qualified','lost')
$db->exec("DELETE FROM leads WHERE email LIKE '%@dummy.local' OR email LIKE '%@test.com'");
$leadsData = [
    ['LD-2026-001', 'Acme Corp Lead', 'John', 'Doe', 'john@dummy.local', '9876543210', 'new', 'hot', 85.00, 25000.00, 'Website'],
    ['LD-2026-002', 'TechSol Deal', 'Sarah', 'Smith', 'sarah@dummy.local', '9876543211', 'contacted', 'warm', 60.00, 18500.00, 'Referral'],
    ['LD-2026-003', 'Global Retail Upgrade', 'Michael', 'Brown', 'michael@dummy.local', '9876543212', 'qualified', 'hot', 90.00, 42000.00, 'LinkedIn'],
    ['LD-2026-004', 'Apex Systems CRM', 'Emily', 'Davis', 'emily@dummy.local', '9876543213', 'qualified', 'hot', 95.00, 35000.00, 'Direct'],
    ['LD-2026-005', 'Starlight Media Contract', 'David', 'Wilson', 'david@dummy.local', '9876543214', 'contacted', 'warm', 75.00, 29000.00, 'Google Ads'],
    ['LD-2026-006', 'Horizon Logistics Lead', 'Lisa', 'Taylor', 'lisa@dummy.local', '9876543215', 'new', 'cold', 30.00, 12000.00, 'Cold Call'],
    ['LD-2026-007', 'Vanguard Enterprise', 'Robert', 'Johnson', 'robert@dummy.local', '9876543216', 'contacted', 'warm', 50.00, 22000.00, 'Website'],
    ['LD-2026-008', 'Quantum Soft Analytics', 'Amanda', 'White', 'amanda@dummy.local', '9876543217', 'qualified', 'hot', 80.00, 50000.00, 'Event']
];

$stmtLead = $db->prepare("INSERT INTO leads (lead_code, client_id, assigned_to_user_id, first_name, last_name, email, phone, status, lead_quality, conversion_probability, lead_value, lead_source, created_at, updated_at)
    VALUES (:code, :cid, :uid, :fname, :lname, :email, :phone, :status, :quality, :prob, :val, :src, NOW(), NOW())");

foreach ($leadsData as $ld) {
    try {
        $stmtLead->execute([
            ':code' => $ld[0],
            ':cid' => $clientId,
            ':uid' => $userId,
            ':fname' => $ld[2],
            ':lname' => $ld[3],
            ':email' => $ld[4],
            ':phone' => $ld[5],
            ':status' => $ld[6],
            ':quality' => $ld[7],
            ':prob' => $ld[8],
            ':val' => $ld[9],
            ':src' => $ld[10]
        ]);
    } catch (Exception $e) {
        echo "Lead insert error: " . $e->getMessage() . "\n";
    }
}
echo "  + Seeded 8 dummy leads successfully!\n";
