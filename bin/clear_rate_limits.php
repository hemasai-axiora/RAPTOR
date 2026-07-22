<?php
require_once dirname(__DIR__) . '/app/config/config.php';
require_once dirname(__DIR__) . '/app/core/Database.php';

$db = Database::getInstance()->getConnection();
$db->exec('TRUNCATE TABLE rate_limits');
$db->exec('TRUNCATE TABLE login_attempts');
echo "Rate limits and login attempts cleared successfully.\n";
