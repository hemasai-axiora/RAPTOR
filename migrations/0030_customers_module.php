<?php
/**
 * Migration 0030: Customer Management Module (customers table)
 */

echo "Starting Migration 0030: Customer Management Module...\n";

// Helper function to check if table exists
$tableExists = function (PDO $db, string $tableName): bool {
    try {
        $stmt = $db->prepare("SHOW TABLES LIKE :table");
        $stmt->execute([':table' => $tableName]);
        return (bool) $stmt->fetch();
    } catch (Exception $e) {
        return false;
    }
};

if (!$tableExists($db, 'customers')) {
    $db->exec("CREATE TABLE customers (
        customer_id INT AUTO_INCREMENT PRIMARY KEY,
        customer_code VARCHAR(20) UNIQUE NULL,
        converted_from_lead_id INT NULL,
        first_name VARCHAR(100) NULL,
        company_name VARCHAR(150) NULL,
        customer_type ENUM('Individual', 'Business') DEFAULT 'Business',
        email VARCHAR(150) NOT NULL,
        phone VARCHAR(50) NULL,
        billing_address TEXT NULL,
        shipping_address TEXT NULL,
        owner_employee_id INT NULL,
        associated_client_id INT NULL,
        status ENUM('Active', 'On Hold', 'Churned', 'Renewal Due') DEFAULT 'Active',
        onboarding_date DATE NULL,
        contract_value DECIMAL(12,2) DEFAULT 0.00,
        payment_terms VARCHAR(50) DEFAULT 'Net 30',
        products_subscribed TEXT NULL,
        renewal_date DATE NULL,
        tags VARCHAR(255) NULL,
        notes TEXT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (converted_from_lead_id) REFERENCES leads(lead_id) ON DELETE SET NULL,
        FOREIGN KEY (owner_employee_id) REFERENCES employees(employee_id) ON DELETE SET NULL,
        FOREIGN KEY (associated_client_id) REFERENCES clients(client_id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    echo "    + Created customers table\n";
} else {
    echo "    = customers table already exists\n";
}

echo "Migration 0030 complete.\n";
