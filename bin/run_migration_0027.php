<?php
require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/core/Database.php';

$db = Database::getInstance()->getConnection();
require_once __DIR__ . '/../migrations/0027_campaign_id_owner_and_offline.php';
