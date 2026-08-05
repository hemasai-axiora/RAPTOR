<?php
require_once 'app/config/config.php';
require_once 'app/core/Database.php';

$db = Database::getInstance()->getConnection();
$stmt = $db->query("DESCRIBE leads");
print_r($stmt->fetchAll(PDO::FETCH_COLUMN));
