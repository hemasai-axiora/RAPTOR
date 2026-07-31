<?php
require_once dirname(__DIR__) . '/app/config/config.php';
require_once dirname(__DIR__) . '/app/core/Database.php';

$db = Database::getInstance()->getConnection();

// Ensure roles exist
$rolesToEnsure = [
    'admin' => 'Administrator',
    'manager' => 'Sales Manager',
    'hr' => 'HR Manager',
    'finance' => 'Finance Manager',
    'analyst' => 'Data Analyst',
    'employee' => 'Sales Associate',
    'ceo' => 'Chief Executive Officer',
    'team_leader' => 'Team Leader'
];

foreach ($rolesToEnsure as $rName => $desc) {
    $stmt = $db->prepare("SELECT role_id FROM roles WHERE role_name = :n");
    $stmt->execute([':n' => $rName]);
    if (!$stmt->fetchColumn()) {
        $db->prepare("INSERT INTO roles (role_name, description, is_system) VALUES (:n, :d, 1)")
           ->execute([':n' => $rName, ':d' => $desc]);
        echo "Ensured role: $rName\n";
    }
}

// Ensure columns on permissions and role_permissions
try {
    $db->exec("ALTER TABLE role_permissions ADD COLUMN scope VARCHAR(20) NOT NULL DEFAULT 'all'");
} catch (Exception $e) {}

try {
    $db->exec("ALTER TABLE roles ADD COLUMN is_system TINYINT(1) NOT NULL DEFAULT 0");
} catch (Exception $e) {}

try {
    $db->exec("ALTER TABLE roles ADD COLUMN description VARCHAR(255) NULL");
} catch (Exception $e) {}

echo "Roles and schema verified.\n";
