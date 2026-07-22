<?php
require_once dirname(__DIR__) . '/app/config/config.php';
require_once dirname(__DIR__) . '/app/core/Database.php';

$db = Database::getInstance()->getConnection();

// 1. Ensure finance role exists
$stmt = $db->prepare("SELECT role_id FROM roles WHERE role_name = 'finance'");
$stmt->execute();
$roleId = $stmt->fetchColumn();

if (!$roleId) {
    $stmt = $db->prepare("INSERT INTO roles (role_name, description) VALUES ('finance', 'Finance and Payroll Administrator')");
    $stmt->execute();
    $roleId = $db->lastInsertId();
    echo "Created 'finance' role (ID: $roleId)\n";
} else {
    echo "Found 'finance' role (ID: $roleId)\n";
}

// 2. Ensure finance user exists
$stmt = $db->prepare("SELECT user_id FROM users WHERE email = 'finance1@raptor.test'");
$stmt->execute();
$userId = $stmt->fetchColumn();

$hash = password_hash('Raptor@12345', PASSWORD_BCRYPT, ['cost' => 10]);

if (!$userId) {
    $stmt = $db->prepare("INSERT INTO users (role_id, name, email, password, status, force_password_reset) VALUES (:rid, 'Finance Officer', 'finance1@raptor.test', :pass, 'active', 0)");
    $stmt->execute([':rid' => $roleId, ':pass' => $hash]);
    $userId = $db->lastInsertId();
    echo "Created user finance1@raptor.test (ID: $userId)\n";
} else {
    $stmt = $db->prepare("UPDATE users SET role_id = :rid, password = :pass, status = 'active' WHERE user_id = :uid");
    $stmt->execute([':rid' => $roleId, ':pass' => $hash, ':uid' => $userId]);
    echo "Updated user finance1@raptor.test (ID: $userId) to finance role\n";
}

// 3. Grant key permissions to finance role
$perms = [
    'invoices.view', 'invoices.create', 'invoices.edit', 'invoices.export',
    'payroll.view', 'payroll.edit', 'payroll.create', 'payroll.approve',
    'attendance.view', 'attendance.mark', 'social_media.view', 'reports.view'
];

foreach ($perms as $pName) {
    [$mod, $act] = explode('.', $pName);
    $stmt = $db->prepare("SELECT permission_id FROM permissions WHERE permission_name = :pname OR (module = :mod AND action = :act)");
    $stmt->execute([':pname' => $pName, ':mod' => $mod, ':act' => $act]);
    $pId = $stmt->fetchColumn();
    if ($pId) {
        $stmt = $db->prepare("INSERT IGNORE INTO role_permissions (role_id, permission_id, scope) VALUES (:rid, :pid, 'all')");
        $stmt->execute([':rid' => $roleId, ':pid' => $pId]);
    }
}

echo "Finance permissions granted successfully.\n";
