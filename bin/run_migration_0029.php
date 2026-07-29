<?php
require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/core/Database.php';

$db = Database::getInstance()->getConnection();
require_once __DIR__ . '/../migrations/0029_post_audience_demographics.php';
