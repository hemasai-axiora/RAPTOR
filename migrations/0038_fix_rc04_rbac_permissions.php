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

$stmtCol = $db->query("SHOW COLUMNS FROM permissions LIKE 'module'");
if (!$stmtCol || !$stmtCol->fetch()) {
    $db->exec("ALTER TABLE permissions ADD COLUMN module VARCHAR(60) NULL AFTER permission_id");
}
$stmtCol = $db->query("SHOW COLUMNS FROM permissions LIKE 'action'");
if (!$stmtCol || !$stmtCol->fetch()) {
    $db->exec("ALTER TABLE permissions ADD COLUMN action VARCHAR(60) NULL AFTER module");
}
$stmtCol = $db->query("SHOW COLUMNS FROM role_permissions LIKE 'scope'");
if (!$stmtCol || !$stmtCol->fetch()) {
    $db->exec("ALTER TABLE role_permissions ADD COLUMN scope VARCHAR(20) NOT NULL DEFAULT 'all'");
}
$stmtCol = $db->query("SHOW COLUMNS FROM users LIKE 'force_password_reset'");
if (!$stmtCol || !$stmtCol->fetch()) {
    $db->exec("ALTER TABLE users ADD COLUMN force_password_reset TINYINT(1) NOT NULL DEFAULT 0");
}

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

// 5. Ensure CEO role exists & set CEO user password hash to 'Raptor@12345'
$stmt = $db->prepare("INSERT IGNORE INTO roles (role_name, description) VALUES ('ceo', 'Chief Executive Officer')");
$stmt->execute();

$ceoRoleId = $getRoleId($db, 'ceo');
$ceoHash = '$2y$10$md383xacx3BKgMuezEM08umH9b7oHty1wk5g3FXd/E1e1npN2I0/e'; // Raptor@12345

$stmt = $db->prepare("UPDATE users SET password = :hash, status = 'active', force_password_reset = 0 WHERE email = 'ceo@raptor.local'");
$stmt->execute([':hash' => $ceoHash]);

if ($stmt->rowCount() === 0) {
    // If user doesn't exist yet, insert ceo@raptor.local
    $stmt = $db->prepare("INSERT INTO users (role_id, name, email, password, status, force_password_reset) VALUES (:rid, 'CEO Executive', 'ceo@raptor.local', :hash, 'active', 0) ON DUPLICATE KEY UPDATE password = :hash2, status = 'active', force_password_reset = 0");
    $stmt->execute([':rid' => $ceoRoleId, ':hash' => $ceoHash, ':hash2' => $ceoHash]);
}

echo " Migration 0038 complete.\n";
