<?php
require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/core/Database.php';

$db = Database::getInstance()->getConnection();
require_once __DIR__ . '/../migrations/0030_customers_module.php';
