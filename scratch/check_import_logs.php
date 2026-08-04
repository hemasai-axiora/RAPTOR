<?php
require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/core/Database.php';

$db = Database::getInstance()->getConnection();
echo "IMPORT LOGS:\n";
print_r($db->query("SELECT * FROM import_logs ORDER BY created_at DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC));
