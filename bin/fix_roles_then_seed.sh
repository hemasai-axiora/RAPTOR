#!/bin/bash
cd /var/www/html
echo "=== Inserting missing roles ==="
php -d display_errors=1 -d error_reporting=32767 -r "
require_once 'app/config/config.php';
require_once 'app/core/Database.php';
\$db = Database::getInstance()->getConnection();

\$missingRoles = ['hr', 'finance', 'employee', 'ceo', 'team_leader'];
foreach (\$missingRoles as \$rname) {
    try {
        \$stmt = \$db->prepare('SELECT role_id FROM roles WHERE role_name = :n');
        \$stmt->execute([':n' => \$rname]);
        \$existing = \$stmt->fetchColumn();
        if (!\$existing) {
            \$db->prepare('INSERT INTO roles (role_name, description, is_system) VALUES (:n, :d, 1)')
               ->execute([':n' => \$rname, ':d' => ucfirst(\$rname) . ' Role']);
            echo 'Created role: ' . \$rname . PHP_EOL;
        } else {
            echo 'Role exists: ' . \$rname . ' (id=' . \$existing . ')' . PHP_EOL;
        }
    } catch (Exception \$e) {
        echo 'Error for ' . \$rname . ': ' . \$e->getMessage() . PHP_EOL;
    }
}
\$stmt = \$db->query('SELECT role_id, role_name FROM roles');
echo 'All roles: ' . PHP_EOL;
foreach (\$stmt->fetchAll(PDO::FETCH_ASSOC) as \$r) {
    echo '  ' . \$r['role_name'] . ' (' . \$r['role_id'] . ')' . PHP_EOL;
}
" 2>&1

echo ""
echo "=== Now running clean_and_seed.php ==="
php -d display_errors=1 -d error_reporting=32767 bin/clean_and_seed.php 2>&1
echo "Exit: $?"
