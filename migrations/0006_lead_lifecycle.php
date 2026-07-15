<?php
/**
 * Sprint 5 - Lead lifecycle, pipeline, products, sources, and history.
 */

$columnExists = function (PDO $db, string $table, string $column): bool {
    $stmt = $db->prepare(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c"
    );
    $stmt->execute([':t' => $table, ':c' => $column]);
    return (int) $stmt->fetchColumn() > 0;
};

$tableExists = function (PDO $db, string $table): bool {
    $stmt = $db->prepare(
        "SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t"
    );
    $stmt->execute([':t' => $table]);
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

$addColumn = function (PDO $db, string $table, string $column, string $ddl) use ($columnExists) {
    if (!$columnExists($db, $table, $column)) {
        $db->exec("ALTER TABLE `$table` ADD COLUMN $ddl");
        echo "    + $table.$column added\n";
    } else {
        echo "    = $table.$column already present\n";
    }
};

$addIndex = function (PDO $db, string $table, string $index, string $ddl) use ($indexExists) {
    if (!$indexExists($db, $table, $index)) {
        $db->exec("ALTER TABLE `$table` ADD INDEX `$index` $ddl");
        echo "    + $table.$index added\n";
    }
};

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
    }
};

$db->exec("ALTER TABLE leads MODIFY status ENUM('new','contacted','qualified','proposal','converted','lost') DEFAULT 'new'");

$addColumn($db, 'leads', 'company_name',       "company_name VARCHAR(150) NULL AFTER last_name");
$addColumn($db, 'leads', 'campaign_source',    "campaign_source VARCHAR(100) NULL AFTER lead_source");
$addColumn($db, 'leads', 'product_id',         "product_id INT NULL AFTER campaign_source");
$addColumn($db, 'leads', 'location',           "location VARCHAR(255) NULL AFTER product_id");
$addColumn($db, 'leads', 'priority',           "priority ENUM('low','medium','high','urgent') NOT NULL DEFAULT 'medium' AFTER location");
$addColumn($db, 'leads', 'next_follow_up_at',  "next_follow_up_at DATETIME NULL AFTER priority");
$addColumn($db, 'leads', 'lost_reason',        "lost_reason VARCHAR(255) NULL AFTER next_follow_up_at");
$addColumn($db, 'leads', 'converted_at',       "converted_at DATETIME NULL AFTER lost_reason");
$addColumn($db, 'leads', 'probability',        "probability DECIMAL(5,2) NOT NULL DEFAULT 0.00 AFTER converted_at");
$addColumn($db, 'leads', 'team_id',            "team_id INT NULL AFTER assigned_to_user_id");

$db->exec("UPDATE leads SET probability = conversion_probability WHERE probability = 0.00 AND conversion_probability IS NOT NULL");

if (!$tableExists($db, 'products')) {
    $db->exec(
        "CREATE TABLE products (
            product_id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(150) NOT NULL,
            sku VARCHAR(50) NULL,
            description TEXT NULL,
            base_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            status ENUM('active','inactive') NOT NULL DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_products_name (name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    echo "    + products table added\n";
}

if (!$tableExists($db, 'lead_sources')) {
    $db->exec(
        "CREATE TABLE lead_sources (
            source_id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            category VARCHAR(50) NULL,
            status ENUM('active','inactive') NOT NULL DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_lead_sources_name (name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    echo "    + lead_sources table added\n";
}

$db->exec(
    "INSERT IGNORE INTO lead_sources (name, category) VALUES
    ('LinkedIn', 'social'), ('Instagram', 'social'), ('Facebook', 'social'),
    ('YouTube', 'social'), ('Google Search', 'search'), ('Email Marketing', 'email'),
    ('Direct Outreach', 'outbound'), ('Referral', 'referral'), ('Website', 'web')"
);

$db->exec(
    "INSERT IGNORE INTO products (name, sku, description) VALUES
    ('Digital Marketing Retainer', 'DMR', 'Recurring digital marketing service'),
    ('Sales Monitoring Suite', 'SMS', 'Raptor sales monitoring package'),
    ('Performance Campaign', 'PC', 'Campaign execution and optimization')"
);

if (!$tableExists($db, 'lead_status_history')) {
    $db->exec(
        "CREATE TABLE lead_status_history (
            history_id BIGINT AUTO_INCREMENT PRIMARY KEY,
            lead_id INT NOT NULL,
            from_status VARCHAR(30) NULL,
            to_status VARCHAR(30) NOT NULL,
            changed_by_user_id INT NULL,
            note VARCHAR(255) NULL,
            changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_lsh_lead_time (lead_id, changed_at),
            FOREIGN KEY (lead_id) REFERENCES leads(lead_id) ON DELETE CASCADE,
            FOREIGN KEY (changed_by_user_id) REFERENCES users(user_id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    echo "    + lead_status_history table added\n";
}

if (!$tableExists($db, 'lead_assignments')) {
    $db->exec(
        "CREATE TABLE lead_assignments (
            assignment_id BIGINT AUTO_INCREMENT PRIMARY KEY,
            lead_id INT NOT NULL,
            from_user_id INT NULL,
            to_user_id INT NULL,
            assigned_by_user_id INT NULL,
            note VARCHAR(255) NULL,
            assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_la_lead_time (lead_id, assigned_at),
            FOREIGN KEY (lead_id) REFERENCES leads(lead_id) ON DELETE CASCADE,
            FOREIGN KEY (from_user_id) REFERENCES users(user_id) ON DELETE SET NULL,
            FOREIGN KEY (to_user_id) REFERENCES users(user_id) ON DELETE SET NULL,
            FOREIGN KEY (assigned_by_user_id) REFERENCES users(user_id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    echo "    + lead_assignments table added\n";
}

$addIndex($db, 'leads', 'idx_leads_status_assignee', '(status, assigned_to_user_id)');
$addIndex($db, 'leads', 'idx_leads_followup', '(next_follow_up_at)');
$addIndex($db, 'leads', 'idx_leads_team', '(team_id)');
$addFk($db, 'leads', 'fk_leads_product', 'FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE SET NULL');
$addFk($db, 'leads', 'fk_leads_team', 'FOREIGN KEY (team_id) REFERENCES teams(team_id) ON DELETE SET NULL');

echo "  [OK] lead lifecycle schema ensured.\n";
