#!/bin/bash
cd /var/www/html
php -d display_errors=1 -d error_reporting=32767 -r "
require_once 'app/config/config.php';
require_once 'app/core/Database.php';
\$db = Database::getInstance()->getConnection();

echo '=== Running migration 0002_employee_and_audit_columns.php ===' . PHP_EOL;
require_once 'migrations/0002_employee_and_audit_columns.php';

echo '=== Verification employees columns ===' . PHP_EOL;
\$stmt = \$db->query('DESCRIBE employees');
foreach (\$stmt->fetchAll(PDO::FETCH_ASSOC) as \$c) {
    echo \$c['Field'] . ' | ' . \$c['Type'] . PHP_EOL;
}
" 2>&1
