<?php
require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/core/Database.php';

try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('billing.conversion_rate_usd_to_inr', '95.31') ON DUPLICATE KEY UPDATE setting_value = '95.31'");
    $stmt->execute();
    echo "Updated billing.conversion_rate_usd_to_inr = 95.31\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
