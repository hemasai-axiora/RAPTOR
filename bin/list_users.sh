#!/bin/bash
cd /var/www/html
php -r "
require_once 'app/config/config.php';
require_once 'app/core/Database.php';
\$db = Database::getInstance()->getConnection();
\$stmt = \$db->query('SELECT user_id, email, status FROM users');
foreach (\$stmt->fetchAll(PDO::FETCH_ASSOC) as \$u) {
    echo \$u['user_id'] . ' | ' . \$u['email'] . ' | ' . \$u['status'] . PHP_EOL;
}
"
