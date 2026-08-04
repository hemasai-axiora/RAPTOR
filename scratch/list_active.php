<?php
require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/core/Database.php';

$db = Database::getInstance()->getConnection();
$stmt = $db->query("SELECT user_id, email, role_id, status FROM users LIMIT 10");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
