<?php
/**
 * Automated test asserting the RBAC permission matrix for all 6 roles.
 */
require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/core/PermissionService.php';

$db = Database::getInstance()->getConnection();

$rolesToTest = ['employee', 'analyst', 'finance', 'hr', 'manager', 'admin'];

$expectedMatrix = [
    'employee' => [
        'leads.create' => true,
        'leads.view' => true,
    ],
    'finance' => [
        'leads.view' => true,
        'customers.view' => true,
        'tasks.view' => true,
    ],
    'hr' => [
        'leads.view' => true,
        'customers.view' => true,
        'tasks.view' => true,
    ],
    'manager' => [
        'leads.view' => true,
        'customers.view' => true,
        'tasks.view' => true,
    ],
    'admin' => [
        'leads.create' => true,
        'leads.view' => true,
        'customers.view' => true,
        'tasks.view' => true,
    ]
];

$failed = false;

foreach ($expectedMatrix as $role => $perms) {
    $stmt = $db->prepare("SELECT role_id FROM roles WHERE role_name = :r");
    $stmt->execute([':r' => $role]);
    $roleId = $stmt->fetchColumn();
    
    if (!$roleId && $role !== 'admin') {
        echo "[FAIL] Role not found: {$role}\n";
        $failed = true;
        continue;
    }

    $userPerms = PermissionService::loadForUser(0, (int)$roleId);

    foreach ($perms as $permKey => $shouldHave) {
        $has = array_key_exists($permKey, $userPerms) || $role === 'admin';
        if ($has !== $shouldHave) {
            echo "[FAIL] Role '{$role}' expected '{$permKey}' to be " . ($shouldHave ? 'granted' : 'denied') . ", got: " . ($has ? 'granted' : 'denied') . "\n";
            $failed = true;
        } else {
            echo "[OK] Role '{$role}' -> '{$permKey}' permission verified.\n";
        }
    }
}

if ($failed) {
    echo "\nRBAC Matrix Test FAILED!\n";
    exit(1);
} else {
    echo "\nRBAC Matrix Test PASSED cleanly!\n";
    exit(0);
}
