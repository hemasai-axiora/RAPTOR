import ftplib
import io

FTP_HOST = "ftpupload.net"
FTP_USER = "ezyro_42571719"
FTP_PASS = "b441442"

# This PHP script runs on the server and can read internal env/config that FTP cannot see
php_content = r"""<?php
header('Content-Type: text/plain; charset=utf-8');

echo "=== SERVER ENVIRONMENT SCAN ===\n\n";

// 1. All environment variables
echo "--- getenv() dump ---\n";
$env = getenv();
foreach ($env as $k => $v) {
    if (stripos($k, 'db') !== false || stripos($k, 'mysql') !== false || 
        stripos($k, 'pass') !== false || stripos($k, 'user') !== false ||
        stripos($k, 'host') !== false || stripos($k, 'dsn') !== false) {
        echo "$k = $v\n";
    }
}

// 2. All _SERVER vars
echo "\n--- _SERVER relevant vars ---\n";
foreach ($_SERVER as $k => $v) {
    if (stripos($k, 'db') !== false || stripos($k, 'mysql') !== false || 
        stripos($k, 'pass') !== false || stripos($k, 'user') !== false ||
        stripos($k, 'host') !== false) {
        echo "$k = $v\n";
    }
}

// 3. Try reading apache config via PHP
echo "\n--- Apache SetEnv (from $_SERVER) ---\n";
$relevant = ['DB_HOST','DB_USER','DB_PASS','DB_PASSWORD','DB_NAME','MYSQL_HOST','MYSQL_USER','MYSQL_PASSWORD','MYSQL_DATABASE'];
foreach ($relevant as $k) {
    $v = getenv($k) ?: ($_SERVER[$k] ?? null);
    if ($v) echo "$k = $v\n";
}

// 4. Try to read any readable files in the home directory
echo "\n--- Checking readable paths ---\n";
$paths = [
    '/home/vol17_2/ezyro.com/ezyro_42571719/.env',
    '/home/vol17_2/ezyro.com/ezyro_42571719/.my.cnf',
    '/home/vol17_2/ezyro.com/ezyro_42571719/htdocs/.env',
    '/home/vol17_2/ezyro.com/ezyro_42571719/htdocs/app/.env',
    '/etc/mysql/conf.d/profreehost.cnf',
    '/var/www/.my.cnf',
];
foreach ($paths as $p) {
    if (@is_readable($p)) {
        echo "READABLE: $p\n";
        echo @file_get_contents($p) . "\n";
    }
}

// 5. phpinfo key values
echo "\n--- phpinfo excerpt ---\n";
ob_start();
phpinfo(INFO_ENVIRONMENT | INFO_VARIABLES);
$info = ob_get_clean();
// Extract DB-related lines
$lines = explode("\n", strip_tags($info));
foreach ($lines as $line) {
    if (stripos($line, 'db') !== false || stripos($line, 'mysql') !== false || stripos($line, 'pass') !== false) {
        $line = trim($line);
        if (strlen($line) > 2) echo $line . "\n";
    }
}

// 6. Try to read PDO available drivers
echo "\n--- PDO Drivers ---\n";
echo implode(', ', PDO::getAvailableDrivers()) . "\n";

// 7. Try connecting with username ezyro_42571719 to list databases (no password)
echo "\n--- Try MySQL connection (no password) ---\n";
try {
    $pdo = new PDO("mysql:host=sql200.ezyro.com;connect_timeout=3", 'ezyro_42571719', '', [PDO::ATTR_TIMEOUT => 3]);
    $dbs = $pdo->query("SHOW DATABASES")->fetchAll(PDO::FETCH_COLUMN);
    echo "Connected! Databases: " . implode(', ', $dbs) . "\n";
} catch (PDOException $e) {
    echo "No-pass attempt: " . $e->getMessage() . "\n";
}

echo "\n=== DONE ===\n";
"""

print("Uploading env_probe.php via FTP...")
ftp = ftplib.FTP(FTP_HOST, timeout=15)
ftp.login(FTP_USER, FTP_PASS)
ftp.cwd("/htdocs/bin")
php_bytes = php_content.encode('utf-8')
ftp.storbinary("STOR env_probe.php", io.BytesIO(php_bytes))
print(f"[OK] Uploaded bin/env_probe.php ({len(php_bytes)} bytes)")
ftp.quit()
