<?php
require_once 'app/config/config.php';
require_once 'app/core/Database.php';

$db = Database::getInstance()->getConnection();
$stmt = $db->query("SELECT u.user_id, u.email, u.status, r.role_name FROM users u JOIN roles r ON u.role_id = r.role_id WHERE u.email = 'employee@raptor.local'");
$row = $stmt->fetch(PDO::FETCH_ASSOC);
print_r($row);
