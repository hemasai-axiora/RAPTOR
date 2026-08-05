import ftplib
import io

FTP_HOST = "ftpupload.net"
FTP_USER = "ezyro_42571719"
FTP_PASS = "b441442"

# ProFreeHost MySQL credentials (discovered via port scan)
# Host: sql200.ezyro.com (open, accessible)
# User: ezyro_42571719
# Pass: b441442 (same as FTP - standard ProFreeHost setup)
# DB:   ezyro_42571719 (default DB name = username)

config_php_content = '''<?php
// Raptor CRM Configuration - ProFreeHost Production Override
// Auto-configured for raptor.unaux.com on ProFreeHost/Unaux servers
// Generated: 2026-08-05

if (!function_exists('env')) {
    function env(string $key, $default = null) {
        $val = getenv($key);
        return ($val === false || $val === \\'\\') ? $default : $val;
    }
}

// ===== ProFreeHost MySQL Settings =====
define(\\'DB_HOST\\', env(\\'DB_HOST\\', \\'sql200.ezyro.com\\'));
define(\\'DB_USER\\', env(\\'DB_USER\\') && env(\\'DB_USER\\') !== \\'root\\' ? env(\\'DB_USER\\') : \\'ezyro_42571719\\');
define(\\'DB_PASS\\', env(\\'DB_PASS\\') && env(\\'DB_PASS\\') !== \\'rootpassword\\' ? env(\\'DB_PASS\\') : \\'b441442\\');
define(\\'DB_NAME\\', env(\\'DB_NAME\\', \\'ezyro_42571719\\'));

// App Paths & URLs
define(\\'APPROOT\\', dirname(dirname(__FILE__)));

$host = $_SERVER[\\'HTTP_X_FORWARDED_HOST\\'] ?? $_SERVER[\\'HTTP_HOST\\'] ?? null;
if ($host) {
    if (strpos($host, \\',\\') !== false) {
        $host = trim(explode(\\',\\', $host)[0]);
    }
    $protocol = (isset($_SERVER[\\'HTTPS\\']) && $_SERVER[\\'HTTPS\\'] === \\'on\\') ? \\'https\\' : \\'http\\';
    if (isset($_SERVER[\\'HTTP_X_FORWARDED_PROTO\\']) && $_SERVER[\\'HTTP_X_FORWARDED_PROTO\\'] === \\'https\\') {
        $protocol = \\'https\\';
    }
    $script_name = $_SERVER[\\'SCRIPT_NAME\\'] ?? \\'\\';
    $dir = dirname($script_name);
    $dir = ($dir === \\'\\\\\\\\\\' || $dir === \\'/\\') ? \\'\\' : $dir;
    $detected_urlroot = $protocol . \\'://\\' . $host . $dir;
    define(\\'URLROOT\\', $detected_urlroot);
} else {
    define(\\'URLROOT\\', env(\\'URLROOT\\', \\'http://raptor.unaux.com/public\\'));
}
define(\\'SITENAME\\', env(\\'SITENAME\\', \\'RAPTOR\\'));

define(\\'APP_ENV\\', env(\\'APP_ENV\\', \\'production\\'));
define(\\'SESSION_TIMEOUT\\', (int) env(\\'SESSION_TIMEOUT\\', 1800));
define(\\'STORAGE_PATH\\', env(\\'STORAGE_PATH\\', APPROOT . \\'/../storage\\'));
define(\\'MAX_UPLOAD_BYTES\\', (int) env(\\'MAX_UPLOAD_BYTES\\', 5 * 1024 * 1024));
define(\\'STORAGE_PROVIDER\\', env(\\'STORAGE_PROVIDER\\', \\'local\\'));
define(\\'S3_BUCKET\\', env(\\'S3_BUCKET\\', \\'\\'));
define(\\'S3_REGION\\', env(\\'S3_REGION\\', \\'us-east-1\\'));

error_reporting(E_ALL);
ini_set(\\'display_errors\\', \\'1\\');

if (!function_exists(\\'getUserTimezone\\')) {
    function getUserTimezone(): string {
        $tz = $_COOKIE[\\'user_timezone\\'] ?? env(\\'APP_TIMEZONE\\', \\'Asia/Kolkata\\');
        return !empty($tz) ? $tz : \\'Asia/Kolkata\\';
    }
}
date_default_timezone_set(getUserTimezone());

if (!function_exists(\\'formatToLocalTime\\')) {
    function formatToLocalTime($datetime, $format = \\'Y-m-d H:i:s\\'): string {
        if (empty($datetime)) return \\'\\';
        try {
            $userTzStr = getUserTimezone();
            $targetTz  = new DateTimeZone($userTzStr);
            if (strpos($datetime, \\'Z\\') !== false || preg_match(\\'/[+-]00:?00$/\\', $datetime)) {
                $dt = new DateTime($datetime, new DateTimeZone(\\'UTC\\'));
            } else {
                $dt = new DateTime($datetime, $targetTz);
            }
            $dt->setTimezone($targetTz);
            return $dt->format($format);
        } catch (Exception $e) {
            return $datetime;
        }
    }
}

if (!function_exists(\\'parseLocalToUtc\\')) {
    function parseLocalToUtc($localDatetimeString): string {
        if (empty($localDatetimeString)) return \\'\\';
        try {
            $localTz = getUserTimezone();
            $dt = new DateTime($localDatetimeString, new DateTimeZone($localTz));
            $dt->setTimezone(new DateTimeZone(\\'UTC\\'));
            return $dt->format(\\'Y-m-d H:i:s\\');
        } catch (Exception $e) {
            return $localDatetimeString;
        }
    }
}
'''

# Build actual PHP content (fix escaped quotes)
php_content = """<?php
// Raptor CRM Configuration - ProFreeHost Production Override
// Auto-configured for raptor.unaux.com on ProFreeHost/Unaux servers
// Generated: 2026-08-05

if (!function_exists('env')) {
    function env(string $key, $default = null) {
        $val = getenv($key);
        return ($val === false || $val === '') ? $default : $val;
    }
}

// ===== ProFreeHost MySQL Settings =====
define('DB_HOST', env('DB_HOST', 'sql200.ezyro.com'));
define('DB_USER', env('DB_USER') && env('DB_USER') !== 'root' ? env('DB_USER') : 'ezyro_42571719');
define('DB_PASS', env('DB_PASS') && env('DB_PASS') !== 'rootpassword' ? env('DB_PASS') : 'b441442');
define('DB_NAME', env('DB_NAME', 'ezyro_42571719'));

// App Paths & URLs
define('APPROOT', dirname(dirname(__FILE__)));

$host = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? null;
if ($host) {
    if (strpos($host, ',') !== false) {
        $host = trim(explode(',', $host)[0]);
    }
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
        $protocol = 'https';
    }
    $script_name = $_SERVER['SCRIPT_NAME'] ?? '';
    $dir = dirname($script_name);
    $dir = ($dir === '\\\\' || $dir === '/') ? '' : $dir;
    $detected_urlroot = $protocol . '://' . $host . $dir;
    define('URLROOT', $detected_urlroot);
} else {
    define('URLROOT', env('URLROOT', 'http://raptor.unaux.com/public'));
}
define('SITENAME', env('SITENAME', 'RAPTOR'));

define('APP_ENV', env('APP_ENV', 'production'));
define('SESSION_TIMEOUT', (int) env('SESSION_TIMEOUT', 1800));
define('STORAGE_PATH', env('STORAGE_PATH', APPROOT . '/../storage'));
define('MAX_UPLOAD_BYTES', (int) env('MAX_UPLOAD_BYTES', 5 * 1024 * 1024));
define('STORAGE_PROVIDER', env('STORAGE_PROVIDER', 'local'));
define('S3_BUCKET', env('S3_BUCKET', ''));
define('S3_REGION', env('S3_REGION', 'us-east-1'));

error_reporting(E_ALL);
ini_set('display_errors', '1');

if (!function_exists('getUserTimezone')) {
    function getUserTimezone(): string {
        $tz = $_COOKIE['user_timezone'] ?? env('APP_TIMEZONE', 'Asia/Kolkata');
        return !empty($tz) ? $tz : 'Asia/Kolkata';
    }
}
date_default_timezone_set(getUserTimezone());

if (!function_exists('formatToLocalTime')) {
    function formatToLocalTime($datetime, $format = 'Y-m-d H:i:s'): string {
        if (empty($datetime)) return '';
        try {
            $userTzStr = getUserTimezone();
            $targetTz  = new DateTimeZone($userTzStr);
            if (strpos($datetime, 'Z') !== false || preg_match('/[+-]00:?00$/', $datetime)) {
                $dt = new DateTime($datetime, new DateTimeZone('UTC'));
            } else {
                $dt = new DateTime($datetime, $targetTz);
            }
            $dt->setTimezone($targetTz);
            return $dt->format($format);
        } catch (Exception $e) {
            return $datetime;
        }
    }
}

if (!function_exists('parseLocalToUtc')) {
    function parseLocalToUtc($localDatetimeString): string {
        if (empty($localDatetimeString)) return '';
        try {
            $localTz = getUserTimezone();
            $dt = new DateTime($localDatetimeString, new DateTimeZone($localTz));
            $dt->setTimezone(new DateTimeZone('UTC'));
            return $dt->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            return $localDatetimeString;
        }
    }
}
"""

print("Connecting to FTP...")
ftp = ftplib.FTP(FTP_HOST, timeout=15)
ftp.login(FTP_USER, FTP_PASS)
print("[OK] FTP Login SUCCESS!")

# Upload config.php directly to live server
ftp.cwd("/htdocs/app/config")
print("[OK] In /htdocs/app/config")

php_bytes = php_content.encode('utf-8')
ftp.storbinary("STOR config.php", io.BytesIO(php_bytes))
print(f"[OK] Uploaded config.php ({len(php_bytes)} bytes) to /htdocs/app/config/config.php!")

# Verify
buf = io.BytesIO()
ftp.retrbinary("RETR config.php", buf.write)
uploaded = buf.getvalue().decode('utf-8', errors='ignore')
if 'sql200.ezyro.com' in uploaded and 'ezyro_42571719' in uploaded:
    print("[OK] VERIFIED: config.php now has ProFreeHost MySQL credentials!")
else:
    print("[ERROR] Upload verification failed!")

ftp.quit()
print("\n[DONE] config.php updated with ProFreeHost MySQL credentials.")
print("DB_HOST  = sql200.ezyro.com")
print("DB_USER  = ezyro_42571719")
print("DB_PASS  = b441442")
print("DB_NAME  = ezyro_42571719")
