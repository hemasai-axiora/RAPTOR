<?php
// Migration 0032: Website Analytics Module
// Creates website_analytics_snapshots, website_traffic_sources, website_top_pages, and website_credentials tables

echo "Starting Migration 0032: Website Analytics Module...\n";

$tableExists = function (PDO $db, string $tableName): bool {
    try {
        $stmt = $db->prepare("SHOW TABLES LIKE :table");
        $stmt->execute([':table' => $tableName]);
        return (bool) $stmt->fetch();
    } catch (Exception $e) {
        return false;
    }
};

// 1. Create website_analytics_snapshots table
if (!$tableExists($db, 'website_analytics_snapshots')) {
    $db->exec("CREATE TABLE website_analytics_snapshots (
        snapshot_id INT AUTO_INCREMENT PRIMARY KEY,
        client_id INT NOT NULL,
        snapshot_date DATE NOT NULL,
        sessions INT NOT NULL DEFAULT 0,
        users INT NOT NULL DEFAULT 0,
        new_users INT NOT NULL DEFAULT 0,
        pageviews INT NOT NULL DEFAULT 0,
        bounce_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00,
        avg_session_duration INT NOT NULL DEFAULT 0,
        synced_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uk_client_date (client_id, snapshot_date),
        CONSTRAINT fk_was_client FOREIGN KEY (client_id) REFERENCES clients(client_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    echo "    + Created website_analytics_snapshots table\n";
} else {
    echo "    = website_analytics_snapshots table already exists\n";
}

// 2. Create website_traffic_sources table
if (!$tableExists($db, 'website_traffic_sources')) {
    $db->exec("CREATE TABLE website_traffic_sources (
        id INT AUTO_INCREMENT PRIMARY KEY,
        snapshot_id INT NOT NULL,
        channel_group VARCHAR(50) NOT NULL,
        sessions INT NOT NULL DEFAULT 0,
        conversions INT NOT NULL DEFAULT 0,
        CONSTRAINT fk_wts_snapshot FOREIGN KEY (snapshot_id) REFERENCES website_analytics_snapshots(snapshot_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    echo "    + Created website_traffic_sources table\n";
} else {
    echo "    = website_traffic_sources table already exists\n";
}

// 3. Create website_top_pages table
if (!$tableExists($db, 'website_top_pages')) {
    $db->exec("CREATE TABLE website_top_pages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        snapshot_id INT NOT NULL,
        page_path VARCHAR(255) NOT NULL,
        pageviews INT NOT NULL DEFAULT 0,
        avg_time_on_page INT NOT NULL DEFAULT 0,
        conversions INT NOT NULL DEFAULT 0,
        CONSTRAINT fk_wtp_snapshot FOREIGN KEY (snapshot_id) REFERENCES website_analytics_snapshots(snapshot_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    echo "    + Created website_top_pages table\n";
} else {
    echo "    = website_top_pages table already exists\n";
}

// 4. Create website_credentials table
if (!$tableExists($db, 'website_credentials')) {
    $db->exec("CREATE TABLE website_credentials (
        id INT AUTO_INCREMENT PRIMARY KEY,
        client_id INT NOT NULL UNIQUE,
        ga4_property_id VARCHAR(50) NOT NULL,
        status ENUM('Active', 'Disconnected') NOT NULL DEFAULT 'Active',
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        CONSTRAINT fk_wc_client FOREIGN KEY (client_id) REFERENCES clients(client_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    echo "    + Created website_credentials table\n";
} else {
    echo "    = website_credentials table already exists\n";
}

echo "Migration 0032 complete.\n";
