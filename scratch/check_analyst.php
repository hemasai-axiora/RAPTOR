<?php
require_once 'app/config/config.php';
require_once 'app/core/Database.php';

$db = Database::getInstance()->getConnection();
$stmt = $db->query("SELECT p.module, p.action FROM role_permissions rp JOIN permissions p ON rp.permission_id = p.permission_id JOIN roles r ON rp.role_id = r.role_id WHERE r.role_name = 'analyst' AND p.module = 'payroll'");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
