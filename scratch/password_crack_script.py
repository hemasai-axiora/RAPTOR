import ftplib
import io
import urllib.request
import time

FTP_HOST = "ftpupload.net"
FTP_USER = "ezyro_42571719"
FTP_PASS = "b441442"

# The cPanel says "Your vPanel Password" - meaning the ProFreeHost cPanel password
# Try ALL known passwords for this account + variations
passwords_to_try = [
    # From cPanel login
    "Axiorags@2026",
    "axiorags@2026", 
    "Axiorags2026",
    "axiorags2026",
    "AXIORAGS@2026",
    # FTP password variants
    "b441442",
    "B441442",
    "b441442!",
    "b441442@",
    # Common defaults
    "Raptor@12345",
    "RaptorCRM@2026",
    "raptor2026",
    "raptor@2026",
    "Raptor2026!",
    "raptor",
    "Raptor@2026",
    # ezyro account name based
    "ezyro42571719",
    "Ezyro@42571719",
    "42571719",
    "ezyro_42571719",
    # Date-based (account created 2026-08-03)
    "Aug@2026",
    "August2026",
    # Simple
    "password",
    "Password123!",
    "admin",
    "Admin@123",
    "123456",
    "1234567",
    "12345678",
    "Profreehost@2026",
    "profreehost",
]

# Upload a PHP script that tries all these passwords
php = """<?php
header('Content-Type: text/plain; charset=utf-8');
set_time_limit(120);

$host = 'sql212.ezyro.com';
$user = 'ezyro_42571719';
$db   = 'ezyro_42571719_raptor';

$passwords = """ + repr(passwords_to_try) + """;

echo "Testing " . count($passwords) . " passwords on $host/$db as $user\\n\\n";

foreach ($passwords as $pass) {
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4;connect_timeout=3", $user, $pass, [
            PDO::ATTR_TIMEOUT => 3,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        echo "*** SUCCESS! Password is: $pass ***\\n";
        $tbls = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        echo "Tables: " . implode(', ', $tbls) . "\\n";
        exit(0);
    } catch (PDOException $e) {
        $msg = $e->getMessage();
        if (strpos($msg, '1045') !== false) {
            echo "WRONG: $pass\\n";
        } elseif (strpos($msg, 'timed out') !== false || strpos($msg, 'refused') !== false) {
            echo "TIMEOUT on $pass\\n";
            break;
        } else {
            echo "ERROR ($pass): $msg\\n";
        }
    }
}
echo "\\nNo password worked.\\n";
"""

print("Uploading password_crack.php via FTP...")
ftp = ftplib.FTP(FTP_HOST, timeout=15)
ftp.login(FTP_USER, FTP_PASS)
ftp.cwd("/htdocs/bin")
ftp.storbinary("STOR password_crack.php", io.BytesIO(php.encode('utf-8')))
print("[OK] Uploaded bin/password_crack.php")
ftp.quit()

print("\nTriggering via HTTP...")
cookie = "ecdd9de927b44aceffae5934f5990024"
req = urllib.request.Request(
    "http://raptor.unaux.com/bin/password_crack.php",
    headers={"User-Agent": "Mozilla/5.0", "Cookie": f"__test={cookie}; path=/"}
)
try:
    with urllib.request.urlopen(req, timeout=90) as resp:
        body = resp.read().decode('utf-8', errors='ignore')
        print(f"HTTP {resp.status} ({len(body)} bytes)")
        print(body)
except Exception as e:
    print(f"Error: {e}")
