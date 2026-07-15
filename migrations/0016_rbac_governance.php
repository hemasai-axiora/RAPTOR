<?php
/**
 * RBAC and governance hardening.
 *
 * - employee replaces legacy sales_person as the canonical field role.
 * - HR can create and manage employees.
 * - Managers request edits; admins approve them.
 * - Operational records gain archive metadata so data is not physically deleted.
 * - Analysts/admins can create reusable dashboard templates.
 */

$tableExists = function (PDO $db, string $table): bool {
    $stmt = $db->prepare(
        "SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t"
    );
    $stmt->execute([':t' => $table]);
    return (int) $stmt->fetchColumn() > 0;
};

$columnExists = function (PDO $db, string $table, string $column): bool {
    $stmt = $db->prepare(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c"
    );
    $stmt->execute([':t' => $table, ':c' => $column]);
    return (int) $stmt->fetchColumn() > 0;
};

$roleId = function (PDO $db, string $role): ?int {
    $stmt = $db->prepare('SELECT role_id FROM roles WHERE role_name = :role LIMIT 1');
    $stmt->execute([':role' => $role]);
    $id = $stmt->fetchColumn();
    return $id === false ? null : (int) $id;
};

$permissionId = function (PDO $db, string $permission): ?int {
    $stmt = $db->prepare('SELECT permission_id FROM permissions WHERE permission_name = :permission LIMIT 1');
    $stmt->execute([':permission' => $permission]);
    $id = $stmt->fetchColumn();
    return $id === false ? null : (int) $id;
};

$legacySalesRoleId = $roleId($db, 'sales_person');
$employeeRoleId = $roleId($db, 'employee');
if ($legacySalesRoleId && !$employeeRoleId) {
    $stmt = $db->prepare('UPDATE roles SET role_name = :employee, description = :description WHERE role_id = :id');
    $stmt->execute([
        ':employee' => 'employee',
        ':description' => 'Employee self-service role for field activity, tasks, and assigned work.',
        ':id' => $legacySalesRoleId,
    ]);
    echo "    + sales_person role renamed to employee\n";
} elseif (!$employeeRoleId) {
    $stmt = $db->prepare('INSERT INTO roles (role_name, description) VALUES (:role, :description)');
    $stmt->execute([
        ':role' => 'employee',
        ':description' => 'Employee self-service role for field activity, tasks, and assigned work.',
    ]);
    echo "    + employee role added\n";
}

if (!$roleId($db, 'hr')) {
    $stmt = $db->prepare('INSERT INTO roles (role_name, description) VALUES (:role, :description)');
    $stmt->execute([
        ':role' => 'hr',
        ':description' => 'HR role for employee creation, employee updates, and employee directory management.',
    ]);
    echo "    + hr role added\n";
}

$permissions = [
    'manage_employees' => 'Create and update employee records.',
    'assign_employee_roles' => 'Assign roles to employee accounts.',
    'view_employee_directory' => 'View employee directory and HR profile fields.',
    'request_data_edit' => 'Request a governed edit with a manager comment.',
    'approve_data_edit' => 'Approve or reject governed edit requests.',
    'create_dashboard_templates' => 'Create reusable dashboard templates.',
    'manage_dashboard_templates' => 'Manage shared dashboard templates.',
    'archive_records' => 'Archive records without physical deletion.',
];

foreach ($permissions as $name => $description) {
    $stmt = $db->prepare(
        'INSERT IGNORE INTO permissions (permission_name, description) VALUES (:name, :description)'
    );
    $stmt->execute([':name' => $name, ':description' => $description]);
}

$grant = function (PDO $db, string $role, array $permissionNames) use ($roleId, $permissionId): void {
    $rid = $roleId($db, $role);
    if (!$rid) {
        return;
    }
    foreach ($permissionNames as $permissionName) {
        $pid = $permissionId($db, $permissionName);
        if (!$pid) {
            continue;
        }
        $stmt = $db->prepare(
            'INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (:rid, :pid)'
        );
        $stmt->execute([':rid' => $rid, ':pid' => $pid]);
    }
};

$allPermissionNames = array_keys($permissions);
$grant($db, 'admin', $allPermissionNames);
$grant($db, 'hr', ['manage_employees', 'assign_employee_roles', 'view_employee_directory']);
$grant($db, 'manager', ['request_data_edit', 'view_employee_directory']);
$grant($db, 'analyst', ['create_dashboard_templates']);

if (!$tableExists($db, 'data_edit_requests')) {
    $db->exec(
        "CREATE TABLE data_edit_requests (
            request_id BIGINT AUTO_INCREMENT PRIMARY KEY,
            entity_type VARCHAR(60) NOT NULL,
            entity_id BIGINT NOT NULL,
            requested_action ENUM('update','archive') NOT NULL DEFAULT 'update',
            proposed_changes JSON NULL,
            manager_comment TEXT NOT NULL,
            status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
            requested_by_user_id INT NOT NULL,
            reviewed_by_user_id INT NULL,
            reviewed_comment TEXT NULL,
            requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            reviewed_at TIMESTAMP NULL,
            INDEX idx_der_status (status, requested_at),
            INDEX idx_der_entity (entity_type, entity_id),
            FOREIGN KEY (requested_by_user_id) REFERENCES users(user_id) ON DELETE RESTRICT,
            FOREIGN KEY (reviewed_by_user_id) REFERENCES users(user_id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    echo "    + data_edit_requests table added\n";
}

$archivableTables = [
    'users', 'employees', 'clients', 'campaigns', 'leads', 'tasks', 'communications',
    'meetings', 'follow_ups', 'targets', 'invoices', 'teams', 'branches',
    'territories', 'geofences',
];

foreach ($archivableTables as $table) {
    if (!$tableExists($db, $table)) {
        continue;
    }
    if (!$columnExists($db, $table, 'is_archived')) {
        $db->exec("ALTER TABLE `$table` ADD COLUMN is_archived TINYINT(1) NOT NULL DEFAULT 0");
    }
    if (!$columnExists($db, $table, 'archived_at')) {
        $db->exec("ALTER TABLE `$table` ADD COLUMN archived_at TIMESTAMP NULL");
    }
    if (!$columnExists($db, $table, 'archived_by_user_id')) {
        $db->exec("ALTER TABLE `$table` ADD COLUMN archived_by_user_id INT NULL");
    }
    if (!$columnExists($db, $table, 'archive_reason')) {
        $db->exec("ALTER TABLE `$table` ADD COLUMN archive_reason TEXT NULL");
    }
}

if (!$tableExists($db, 'dashboard_templates')) {
    $db->exec(
        "CREATE TABLE dashboard_templates (
            template_id BIGINT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(120) NOT NULL,
            description TEXT NULL,
            base_dashboard_key VARCHAR(60) NOT NULL,
            widgets JSON NOT NULL,
            visibility ENUM('private','role','global') NOT NULL DEFAULT 'role',
            allowed_roles JSON NULL,
            created_by_user_id INT NOT NULL,
            status ENUM('active','inactive') NOT NULL DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_dashboard_templates_status (status, visibility),
            FOREIGN KEY (created_by_user_id) REFERENCES users(user_id) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    echo "    + dashboard_templates table added\n";
}

$db->exec(
    "INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
        ('governance.no_physical_delete', '1'),
        ('governance.manager_edits_require_admin_approval', '1')"
);

echo "  [OK] RBAC governance schema ensured.\n";
