<?php
// Migration 0042: Custom Dashboard Builder Tables
// Creates custom_dashboards, custom_dashboard_widgets, and custom_dashboard_roles tables

echo "Starting Migration 0042: Custom Dashboard Builder...\n";

$tableExists = function (PDO $db, string $tableName): bool {
    try {
        $stmt = $db->prepare("SHOW TABLES LIKE :table");
        $stmt->execute([':table' => $tableName]);
        return (bool) $stmt->fetch();
    } catch (Exception $e) {
        return false;
    }
};

// 1. Create custom_dashboards table
if (!$tableExists($db, 'custom_dashboards')) {
    $db->exec("CREATE TABLE custom_dashboards (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(150) NOT NULL,
        description TEXT NULL,
        owner_id INT NOT NULL,
        is_template TINYINT(1) NOT NULL DEFAULT 0,
        is_default TINYINT(1) NOT NULL DEFAULT 0,
        visibility_type ENUM('private', 'role', 'everyone') NOT NULL DEFAULT 'private',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        CONSTRAINT fk_cd_owner FOREIGN KEY (owner_id) REFERENCES users(user_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    echo "    + Created custom_dashboards table\n";
} else {
    echo "    = custom_dashboards table already exists\n";
}

// 2. Create custom_dashboard_widgets table
if (!$tableExists($db, 'custom_dashboard_widgets')) {
    $db->exec("CREATE TABLE custom_dashboard_widgets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        dashboard_id INT NOT NULL,
        title VARCHAR(150) NOT NULL,
        widget_type ENUM('kpi', 'line', 'bar', 'pie', 'table', 'funnel', 'gauge', 'map', 'text') NOT NULL DEFAULT 'kpi',
        data_source VARCHAR(50) NOT NULL DEFAULT 'leads',
        config_json TEXT NOT NULL,
        pos_x INT NOT NULL DEFAULT 0,
        pos_y INT NOT NULL DEFAULT 0,
        width INT NOT NULL DEFAULT 4,
        height INT NOT NULL DEFAULT 4,
        sort_order INT NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_cdw_dashboard FOREIGN KEY (dashboard_id) REFERENCES custom_dashboards(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    echo "    + Created custom_dashboard_widgets table\n";
} else {
    echo "    = custom_dashboard_widgets table already exists\n";
}

// 3. Create custom_dashboard_roles table
if (!$tableExists($db, 'custom_dashboard_roles')) {
    $db->exec("CREATE TABLE custom_dashboard_roles (
        id INT AUTO_INCREMENT PRIMARY KEY,
        dashboard_id INT NOT NULL,
        role VARCHAR(50) NOT NULL,
        UNIQUE KEY uk_dashboard_role (dashboard_id, role),
        CONSTRAINT fk_cdr_dashboard FOREIGN KEY (dashboard_id) REFERENCES custom_dashboards(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    echo "    + Created custom_dashboard_roles table\n";
} else {
    echo "    = custom_dashboard_roles table already exists\n";
}

echo "Migration 0042 complete.\n";
