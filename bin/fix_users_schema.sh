#!/bin/bash
cd /var/www/html
php -d display_errors=1 -d error_reporting=32767 -r "
require_once 'app/config/config.php';
require_once 'app/core/Database.php';
\$db = Database::getInstance()->getConnection();

echo '=== Checking users table columns ===' . PHP_EOL;
\$stmt = \$db->prepare(\"SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='force_password_reset'\");
\$stmt->execute();
if (!\$stmt->fetchColumn()) {
    \$db->exec(\"ALTER TABLE users ADD COLUMN force_password_reset TINYINT(1) NOT NULL DEFAULT 0\");
    echo 'Added force_password_reset column to users' . PHP_EOL;
} else {
    echo 'force_password_reset column already exists' . PHP_EOL;
}
" 2>&1
