#!/bin/bash
cd /var/www/html
echo "=== Deleting stale 0031/0032 migration records from web DB ==="
php -r "
require_once 'app/config/config.php';
require_once 'app/core/Database.php';
\$db = Database::getInstance()->getConnection();
\$n = \$db->exec(\"DELETE FROM schema_migrations WHERE version IN ('0031_account_management_module', '0032_website_analytics_module')\");
echo 'Deleted: ' . \$n . ' rows' . PHP_EOL;
"

echo ""
echo "=== Running migrations ==="
php bin/migrate.php

echo ""
echo "=== Verifying tables ==="
php -r "
require_once 'app/config/config.php';
require_once 'app/core/Database.php';
\$db = Database::getInstance()->getConnection();
\$tables = ['account_sales_activities','account_opportunities','website_analytics_snapshots','website_traffic_sources','website_top_pages','website_credentials'];
foreach (\$tables as \$t) {
    \$stmt = \$db->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:t');
    \$stmt->execute([':t'=>\$t]);
    \$exists = (int)\$stmt->fetchColumn();
    echo (\$exists ? '[OK]' : '[MISSING]') . ' ' . \$t . PHP_EOL;
}
"
