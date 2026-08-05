<?php
// ProFreeHost MySQL Discovery & Connection Test
// Account: ezyro_42571719
// ProFreeHost always uses format: ezyro_XXXXXXX for both DB names and usernames
// DB name from their panel = ezyro_42571719 (same as FTP user by default)

header('Content-Type: text/plain; charset=utf-8');

$username = 'ezyro_42571719';

// ProFreeHost DB credentials follow this pattern:
// - Username: same as FTP username (ezyro_42571719)
// - Password: same as hosting account password OR custom set
// - DB Name: same as username (ezyro_42571719) OR custom set

// Try password guesses - these are common ProFreeHost defaults
$passwords = [
    'Axiorags@2026',     // User's stated hosting password
    'Axiorags2026',
    'axiorags@2026',
    'Raptor@12345',
    'RaptorCRM@2026',
    'raptor2026',
    '',
    'password',
];

$dbNames = [
    'ezyro_42571719',
    'ezyro_42571719_raptor',
    'ezyro_42571719_crm',
    'raptor_crm_db',
    'raptor',
];

$hosts = [
    'sql200.ezyro.com',
    'sql201.ezyro.com',
    'sql202.ezyro.com',
    'sql203.ezyro.com',
    'sql204.ezyro.com',
    'sql205.ezyro.com',
];

echo "=== ProFreeHost MySQL Credential Discovery ===\n\n";

foreach ($hosts as $host) {
    foreach ($dbNames as $dbName) {
        foreach ($passwords as $pass) {
            $dsn = "mysql:host={$host};dbname={$dbName};charset=utf8";
            try {
                $pdo = new PDO($dsn, $username, $pass, [
                    PDO::ATTR_TIMEOUT => 3,
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                ]);
                echo "SUCCESS! Connected to:\n";
                echo "  HOST:  {$host}\n";
                echo "  USER:  {$username}\n";
                echo "  PASS:  {$pass}\n";
                echo "  DB:    {$dbName}\n";
                // Show tables
                $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
                echo "  TABLES: " . implode(', ', $tables) . "\n";
                exit(0);
            } catch (PDOException $e) {
                // Only show non-timeout errors  
                if (strpos($e->getMessage(), 'Connection timed out') === false &&
                    strpos($e->getMessage(), 'Connection refused') === false &&
                    strpos($e->getMessage(), 'No such host') === false) {
                    echo "Partial match on {$host}/{$dbName}: " . $e->getMessage() . "\n";
                }
            }
        }
    }
}
echo "\n\nNo successful connection found.\n";
echo "Attempted user: {$username}\n";
echo "Attempted hosts: " . implode(', ', array_slice($hosts, 0, 3)) . "...\n";
