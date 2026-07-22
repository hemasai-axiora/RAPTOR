<?php
/**
 * Migration 0025: Grant missing permissions for Manager and Finance roles.
 */

// Helper to query and insert permissions
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
    } else {
        echo "    ! Failed to find role/permission: {$roleName} / {$permissionName}\n";
    }
};

echo "Starting Granular RBAC Fixes Migration...\n";

// 1. Managers need full invoices access (create, edit, export)
$grantPermission($db, 'manager', 'invoices.create', 'all');
$grantPermission($db, 'manager', 'invoices.edit', 'all');
$grantPermission($db, 'manager', 'invoices.export', 'all');

// 2. Finance needs social_media.view (Analytics History 404 fix)
$grantPermission($db, 'finance', 'social_media.view', 'all');

// 3. Finance needs self-service attendance permissions (view and mark own check-ins)
$grantPermission($db, 'finance', 'attendance.view', 'own');
$grantPermission($db, 'finance', 'attendance.mark', 'own');

echo "Granular RBAC Fixes Migration complete.\n";
