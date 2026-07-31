<?php
/**
 * Migration 0038: Fix RC-04 missing permissions for Employee, Finance, HR, and Manager.
 */

if (!isset($db)) {
    require_once __DIR__ . '/../app/config/config.php';
    require_once __DIR__ . '/../app/core/Database.php';
    $db = Database::getInstance()->getConnection();
}

$getRoleId = function (PDO $db, string $roleName): ?int {
    $stmt = $db->prepare('SELECT role_id FROM roles WHERE role_name = :name LIMIT 1');
    $stmt->execute([':name' => $roleName]);
    $id = $stmt->fetchColumn();
    return $id !== false ? (int) $id : null;
};

$getPermissionId = function (PDO $db, string $permissionName): ?int {
    $stmt = $db->prepare('SELECT permission_id FROM permissions WHERE permission_name = :name LIMIT 1');
    $stmt->execute([':name' => $permissionName]);
    $id = $stmt->fetchColumn();
    return $id !== false ? (int) $id : null;
};

$grantPermission = function (PDO $db, string $roleName, string $permissionName, ?string $scope) use ($getRoleId, $getPermissionId) {
    $rid = $getRoleId($db, $roleName);
    $pid = $getPermissionId($db, $permissionName);
    if ($rid && $pid) {
        $stmt = $db->prepare('INSERT INTO role_permissions (role_id, permission_id, scope) VALUES (:r, :p, :s) ON DUPLICATE KEY UPDATE scope = VALUES(scope)');
        $stmt->execute([':r' => $rid, ':p' => $pid, ':s' => $scope]);
        echo "    + Granted {$permissionName} to {$roleName} with scope '{$scope}'\n";
    }
};

echo "Running Migration 0038 (RC-04 Permissions Fix)...";

// Ensure permissions exist in permissions table
$permsToEnsure = [
    ['leads.create', 'leads', 'create'],
    ['leads.view', 'leads', 'view'],
    ['customers.view', 'customers', 'view'],
    ['tasks.view', 'tasks', 'view'],
];

foreach ($permsToEnsure as [$pName, $mod, $act]) {
    $stmt = $db->prepare('INSERT IGNORE INTO permissions (permission_name, module, action) VALUES (:name, :mod, :act)');
    $stmt->execute([':name' => $pName, ':mod' => $mod, ':act' => $act]);
}

// 1. Employee needs leads.create (Capture Lead) & leads.view
$grantPermission($db, 'employee', 'leads.create', 'own');
$grantPermission($db, 'employee', 'leads.view', 'own');

// 2. Finance needs leads.view, customers.view, tasks.view
$grantPermission($db, 'finance', 'leads.view', 'all');
$grantPermission($db, 'finance', 'customers.view', 'all');
$grantPermission($db, 'finance', 'tasks.view', 'all');

// 3. HR needs leads.view, customers.view, tasks.view
$grantPermission($db, 'hr', 'leads.view', 'all');
$grantPermission($db, 'hr', 'customers.view', 'all');
$grantPermission($db, 'hr', 'tasks.view', 'all');

// 4. Manager needs leads.view
$grantPermission($db, 'manager', 'leads.view', 'all');

echo " Migration 0038 complete.\n";
