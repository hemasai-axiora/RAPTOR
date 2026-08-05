<?php
require_once 'app/config/config.php';
require_once 'app/core/Database.php';

$db = Database::getInstance()->getConnection();
$stmt = $db->query("SELECT user_id, email, status FROM users WHERE email = 'admin@raptor.local'");
$row = $stmt->fetch(PDO::FETCH_ASSOC);
print_r($row);
