#!/bin/bash
cd /var/www/html
php -d display_errors=1 -d error_reporting=32767 -r "
require_once 'app/config/config.php';
require_once 'app/core/Database.php';
\$db = Database::getInstance()->getConnection();

// Clear schema_migrations so all files run self-healing column checks
\$db->exec('TRUNCATE TABLE schema_migrations');
" 2>&1

echo "=== Running php bin/migrate.php ==="
php -d display_errors=1 bin/migrate.php 2>&1
