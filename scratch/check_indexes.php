<?php
require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/core/Database.php';

$db = Database::getInstance()->getConnection();
echo "USERS INDEXES:\n";
print_r($db->query("SHOW INDEX FROM users")->fetchAll(PDO::FETCH_ASSOC));
echo "\nEMPLOYEES INDEXES:\n";
print_r($db->query("SHOW INDEX FROM employees")->fetchAll(PDO::FETCH_ASSOC));
