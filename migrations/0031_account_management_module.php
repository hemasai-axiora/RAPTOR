<?php
// Migration 0031: Account Management (Inside Sales) Module
// Creates account_sales_activities and account_opportunities tables

echo "Starting Migration 0031: Account Management Module...\n";

$tableExists = function (PDO $db, string $tableName): bool {
    try {
        $stmt = $db->prepare("SHOW TABLES LIKE :table");
        $stmt->execute([':table' => $tableName]);
        return (bool) $stmt->fetch();
    } catch (Exception $e) {
        return false;
    }
};

// 1. Create account_sales_activities table
if (!$tableExists($db, 'account_sales_activities')) {
    $db->exec("CREATE TABLE account_sales_activities (
        activity_id INT AUTO_INCREMENT PRIMARY KEY,
        activity_code VARCHAR(20) UNIQUE NULL,
        customer_id INT NOT NULL,
        assigned_rep_employee_id INT NULL,
        activity_type ENUM('Call', 'Email', 'Upsell Pitch', 'Renewal Check-in', 'Cross-sell', 'QBR') NOT NULL DEFAULT 'Call',
        outcome ENUM('Successful', 'Follow-up Needed', 'No Answer', 'Closed Won', 'Closed Lost') NOT NULL DEFAULT 'Successful',
        next_follow_up_date DATETIME NULL,
        notes TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_asa_customer FOREIGN KEY (customer_id) REFERENCES customers(customer_id) ON DELETE CASCADE,
        CONSTRAINT fk_asa_employee FOREIGN KEY (assigned_rep_employee_id) REFERENCES employees(employee_id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    echo "    + Created account_sales_activities table\n";
} else {
    echo "    = account_sales_activities table already exists\n";
}

// 2. Create account_opportunities table
if (!$tableExists($db, 'account_opportunities')) {
    $db->exec("CREATE TABLE account_opportunities (
        opportunity_id INT AUTO_INCREMENT PRIMARY KEY,
        opportunity_code VARCHAR(20) UNIQUE NULL,
        customer_id INT NOT NULL,
        assigned_rep_employee_id INT NULL,
        title VARCHAR(150) NOT NULL,
        opportunity_type ENUM('Upsell', 'Renewal', 'Cross-sell') NOT NULL DEFAULT 'Upsell',
        stage ENUM('Identified', 'Proposed', 'Negotiating', 'Won', 'Lost') NOT NULL DEFAULT 'Identified',
        expected_value DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        probability INT NOT NULL DEFAULT 50,
        target_close_date DATE NULL,
        notes TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        CONSTRAINT fk_ao_customer FOREIGN KEY (customer_id) REFERENCES customers(customer_id) ON DELETE CASCADE,
        CONSTRAINT fk_ao_employee FOREIGN KEY (assigned_rep_employee_id) REFERENCES employees(employee_id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    echo "    + Created account_opportunities table\n";
} else {
    echo "    = account_opportunities table already exists\n";
}

echo "Migration 0031 complete.\n";
