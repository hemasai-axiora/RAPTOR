<?php
require_once dirname(__DIR__) . '/app/config/config.php';
require_once dirname(__DIR__) . '/app/core/Database.php';

$db = Database::getInstance()->getConnection();
$hash = password_hash('Raptor@12345', PASSWORD_BCRYPT, ['cost' => 10]);

// Update all user passwords to Raptor@12345
$stmt = $db->prepare("UPDATE users SET password = :pass, force_password_reset = 0");
$stmt->execute([':pass' => $hash]);
echo "Updated " . $stmt->rowCount() . " users to password Raptor@12345\n";

// Print all users
$stmt = $db->query("SELECT u.email, r.role_name FROM users u JOIN roles r ON u.role_id = r.role_id ORDER BY r.role_name");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    echo $row['role_name'] . ' => ' . $row['email'] . "\n";
}
