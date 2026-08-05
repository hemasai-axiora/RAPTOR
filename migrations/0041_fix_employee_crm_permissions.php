<?php
/**
 * Migration 0041: Seed complete CRM permissions for Employee and Sales Person roles.
 * Covers: My Follow-ups, Leads Manager, Customer Directory, Communications, Meetings.
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

echo "Running Migration 0041 (CRM & Employee Permissions Fix)...\n";

$permsToEnsure = [
    ['crm_leads.view', 'crm_leads', 'view', 'View leads'],
    ['crm_leads.create', 'crm_leads', 'create', 'Create leads'],
    ['crm_leads.edit', 'crm_leads', 'edit', 'Edit leads'],
    ['leads.view', 'leads', 'view', 'View leads'],
    ['leads.create', 'leads', 'create', 'Create leads'],
    ['leads.edit', 'leads', 'edit', 'Edit leads'],
    ['customers.view', 'customers', 'view', 'View customer directory'],
    ['customers.create', 'customers', 'create', 'Create customer accounts'],
    ['customers.edit', 'customers', 'edit', 'Edit customer accounts'],
    ['communications.view', 'communications', 'view', 'View communications log'],
    ['communications.create', 'communications', 'create', 'Log communication touches'],
    ['meetings.view', 'meetings', 'view', 'View meetings and demos'],
    ['meetings.create', 'meetings', 'create', 'Schedule meetings and demos'],
    ['followups.view', 'followups', 'view', 'View follow-ups'],
    ['followups.create', 'followups', 'create', 'Schedule follow-ups'],
];

foreach ($permsToEnsure as [$pName, $mod, $act, $desc]) {
    $stmt = $db->prepare('INSERT INTO permissions (permission_name, module, action, description) VALUES (:name, :mod, :act, :desc) ON DUPLICATE KEY UPDATE module = VALUES(module), action = VALUES(action), description = VALUES(description)');
    $stmt->execute([':name' => $pName, ':mod' => $mod, ':act' => $act, ':desc' => $desc]);
}

$rolesToGrant = ['employee', 'sales_person'];
foreach ($rolesToGrant as $roleName) {
    $grantPermission($db, $roleName, 'crm_leads.view', 'own');
    $grantPermission($db, $roleName, 'crm_leads.create', 'own');
    $grantPermission($db, $roleName, 'crm_leads.edit', 'own');
    $grantPermission($db, $roleName, 'leads.view', 'own');
    $grantPermission($db, $roleName, 'leads.create', 'own');
    $grantPermission($db, $roleName, 'leads.edit', 'own');
    $grantPermission($db, $roleName, 'customers.view', 'all');
    $grantPermission($db, $roleName, 'customers.create', 'own');
    $grantPermission($db, $roleName, 'communications.view', 'own');
    $grantPermission($db, $roleName, 'communications.create', 'own');
    $grantPermission($db, $roleName, 'meetings.view', 'own');
    $grantPermission($db, $roleName, 'meetings.create', 'own');
    $grantPermission($db, $roleName, 'followups.view', 'own');
    $grantPermission($db, $roleName, 'followups.create', 'own');
}

echo "Migration 0041 complete.\n";
