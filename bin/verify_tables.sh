#!/bin/bash
cd /var/www/html
php -r "
require_once 'app/config/config.php';
require_once 'app/core/Database.php';
\$db = Database::getInstance()->getConnection();
\$tables = ['account_sales_activities','account_opportunities','website_analytics_snapshots','website_traffic_sources','website_top_pages','website_credentials','customers'];
foreach (\$tables as \$t) {
    \$stmt = \$db->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:t');
    \$stmt->execute([':t'=>\$t]);
    \$exists = (int)\$stmt->fetchColumn();
    echo (\$exists ? '[OK]' : '[MISSING]') . ' ' . \$t . PHP_EOL;
}
"
