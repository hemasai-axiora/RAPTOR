#!/bin/bash
cd /var/www/html
php -d display_errors=1 -d error_reporting=32767 -r "
require_once 'app/config/config.php';
require_once 'app/core/Database.php';
\$db = Database::getInstance()->getConnection();

echo '=== DESCRIBE role_permissions ===' . PHP_EOL;
\$stmt = \$db->query('DESCRIBE role_permissions');
foreach (\$stmt->fetchAll(PDO::FETCH_ASSOC) as \$c) {
    echo \$c['Field'] . ' | ' . \$c['Type'] . PHP_EOL;
}

// Add scope column to role_permissions if missing
\$stmt = \$db->prepare(\"SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='role_permissions' AND COLUMN_NAME='scope'\");
\$stmt->execute();
if (!\$stmt->fetchColumn()) {
    \$db->exec(\"ALTER TABLE role_permissions ADD COLUMN scope VARCHAR(20) NOT NULL DEFAULT 'all'\");
    echo 'Added scope column to role_permissions' . PHP_EOL;
} else {
    echo 'scope column already exists in role_permissions' . PHP_EOL;
}
" 2>&1
