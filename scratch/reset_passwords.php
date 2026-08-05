<?php
require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/core/Database.php';

try {
    $db = Database::getInstance()->getConnection();
    $hash = password_hash('Raptor@12345', PASSWORD_BCRYPT);

    $roles = [
        'admin' => ['Admin User', 'admin@raptor.local', 'admin@raptor.com'],
        'manager' => ['Manager User', 'manager@raptor.local', 'manager@raptor.com'],
        'hr' => ['HR User', 'hr@raptor.local', 'hr@raptor.com'],
        'finance' => ['Finance User', 'finance@raptor.local', 'finance@raptor.com'],
        'analyst' => ['Analyst User', 'analyst@raptor.local', 'analyst@raptor.com'],
        'employee' => ['Employee User', 'employee@raptor.local', 'employee@raptor.com'],
        'ceo' => ['CEO Executive', 'ceo@raptor.local', 'ceo@raptor.com']
    ];

    foreach ($roles as $roleName => $info) {
        $stmt = $db->prepare("SELECT role_id FROM roles WHERE role_name = :r LIMIT 1");
        $stmt->execute([':r' => $roleName]);
        $roleId = $stmt->fetchColumn();

        if (!$roleId) {
            $db->exec("INSERT INTO roles (role_name, description) VALUES ('$roleName', '$roleName role')");
            $roleId = $db->lastInsertId();
        }

        $name = $info[0];
        for ($i = 1; $i < count($info); $i++) {
            $email = $info[$i];
            $stmt = $db->prepare("SELECT user_id FROM users WHERE email = :e LIMIT 1");
            $stmt->execute([':e' => $email]);
            $uid = $stmt->fetchColumn();

            if ($uid) {
                $up = $db->prepare("UPDATE users SET password = :h, role_id = :r, status = 'active', force_password_reset = 0 WHERE user_id = :u");
                $up->execute([':h' => $hash, ':r' => $roleId, ':u' => $uid]);
            } else {
                $ins = $db->prepare("INSERT INTO users (role_id, name, email, password, status, force_password_reset) VALUES (:r, :n, :e, :h, 'active', 0)");
                $ins->execute([':r' => $roleId, ':n' => $name, ':e' => $email, ':h' => $hash]);
            }
            echo "Upserted $email -> Raptor@12345\n";
        }
    }

    echo "=== Password & Account Sync Complete ===\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
