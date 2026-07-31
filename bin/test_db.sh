#!/bin/bash
cd /var/www/html
echo "=== Test 1: DB connection ==="
php -d display_errors=1 -d error_reporting=32767 -r "
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once 'app/config/config.php';
require_once 'app/core/Database.php';
\$db = Database::getInstance()->getConnection();
echo 'Connected OK' . PHP_EOL;
\$stmt = \$db->query('SELECT role_id, role_name FROM roles');
\$roles = \$stmt->fetchAll(PDO::FETCH_ASSOC);
foreach (\$roles as \$r) { echo 'Role: ' . \$r['role_name'] . ' (' . \$r['role_id'] . ')' . PHP_EOL; }
echo 'Roles total: ' . count(\$roles) . PHP_EOL;
" 2>&1
