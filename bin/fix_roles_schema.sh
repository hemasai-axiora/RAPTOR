#!/bin/bash
cd /var/www/html
echo "=== Fixing roles table schema ==="
php -d display_errors=1 -d error_reporting=32767 -r "
require_once 'app/config/config.php';
require_once 'app/core/Database.php';
\$db = Database::getInstance()->getConnection();

// Check if is_system column exists
\$stmt = \$db->prepare(\"SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='roles' AND COLUMN_NAME='is_system'\");
\$stmt->execute();
if (!\$stmt->fetchColumn()) {
    \$db->exec('ALTER TABLE roles ADD COLUMN is_system TINYINT(1) NOT NULL DEFAULT 0');
    echo 'Added is_system column to roles' . PHP_EOL;
} else {
    echo 'is_system column already exists' . PHP_EOL;
}

// Check description column
\$stmt2 = \$db->prepare(\"SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='roles' AND COLUMN_NAME='description'\");
\$stmt2->execute();
if (!\$stmt2->fetchColumn()) {
    \$db->exec('ALTER TABLE roles ADD COLUMN description VARCHAR(255) NULL');
    echo 'Added description column to roles' . PHP_EOL;
} else {
    echo 'description column already exists' . PHP_EOL;
}

// Insert missing roles
\$missingRoles = [
    'hr'          => 'HR Manager',
    'finance'     => 'Finance Manager',
    'employee'    => 'Field Sales Employee',
    'ceo'         => 'Chief Executive Officer (CEO)',
    'team_leader' => 'Team Leader (Legacy)',
];
foreach (\$missingRoles as \$rname => \$desc) {
    \$stmt = \$db->prepare('SELECT role_id FROM roles WHERE role_name = :n');
    \$stmt->execute([':n' => \$rname]);
    \$existing = \$stmt->fetchColumn();
    if (!\$existing) {
        \$db->prepare('INSERT INTO roles (role_name, description, is_system) VALUES (:n, :d, 1)')
           ->execute([':n' => \$rname, ':d' => \$desc]);
        echo 'Created role: ' . \$rname . PHP_EOL;
    } else {
        echo 'Role exists: ' . \$rname . PHP_EOL;
    }
}

\$stmt = \$db->query('SELECT role_id, role_name FROM roles');
echo 'Final roles: ' . PHP_EOL;
foreach (\$stmt->fetchAll(PDO::FETCH_ASSOC) as \$r) {
    echo '  ' . \$r['role_name'] . ' (' . \$r['role_id'] . ')' . PHP_EOL;
}
" 2>&1

echo ""
echo "=== Running clean_and_seed.php ==="
php -d display_errors=1 bin/clean_and_seed.php 2>&1
echo "Exit: $?"
