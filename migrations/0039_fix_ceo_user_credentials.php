<?php
/**
 * Migration 0039: Guarantee CEO role, permissions & ceo@raptor.local credentials across all environments.
 */

if (!isset($db)) {
    require_once __DIR__ . '/../app/config/config.php';
    require_once __DIR__ . '/../app/core/Database.php';
    $db = Database::getInstance()->getConnection();
}

echo "Running Migration 0039 (CEO User Credentials & Role Fix)...";

// 1. Ensure 'ceo' role exists
$db->exec("INSERT IGNORE INTO roles (role_name, description) VALUES ('ceo', 'Chief Executive Officer')");

$stmt = $db->prepare("SELECT role_id FROM roles WHERE role_name = 'ceo' LIMIT 1");
$stmt->execute();
$ceoRoleId = (int) $stmt->fetchColumn();

// Password hash for 'Raptor@12345'
$ceoHash = '$2y$10$md383xacx3BKgMuezEM08umH9b7oHty1wk5g3FXd/E1e1npN2I0/e';

// 2. Insert or Update ceo@raptor.local
$stmt = $db->prepare("SELECT user_id FROM users WHERE email = 'ceo@raptor.local'");
$stmt->execute();
$existingUser = $stmt->fetchColumn();

if ($existingUser) {
    $stmt = $db->prepare("UPDATE users SET password = :hash, role_id = :rid, status = 'active', force_password_reset = 0 WHERE email = 'ceo@raptor.local'");
    $stmt->execute([':hash' => $ceoHash, ':rid' => $ceoRoleId]);
} else {
    $stmt = $db->prepare("INSERT INTO users (role_id, name, email, password, status, force_password_reset) VALUES (:rid, 'CEO Executive', 'ceo@raptor.local', :hash, 'active', 0)");
    $stmt->execute([':rid' => $ceoRoleId, ':hash' => $ceoHash]);
}

// 3. Seed all permissions for CEO role in role_permissions table
$stmt = $db->query("SELECT permission_id FROM permissions");
$allPermIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

$grantStmt = $db->prepare("INSERT INTO role_permissions (role_id, permission_id, scope) VALUES (:r, :p, 'all') ON DUPLICATE KEY UPDATE scope = 'all'");
foreach ($allPermIds as $pid) {
    $grantStmt->execute([':r' => $ceoRoleId, ':p' => $pid]);
}

echo " Migration 0039 complete.\n";
