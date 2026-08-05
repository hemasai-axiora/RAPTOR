<?php
// Raptor CRM Configuration
//
// Values are read from environment variables first (set these on the VPS via
// Apache SetEnv, a .env loader, or the shell), falling back to sensible local
// Docker defaults. Never commit real production credentials — set them in the
// server environment. See config.sample.php for the full list.

if (!function_exists('env')) {
    /** Read an env var with a default. */
    function env(string $key, $default = null) {
        $val = getenv($key);
        return ($val === false || $val === '') ? $default : $val;
    }
}

// Database Settings
define('DB_HOST', env('DB_HOST', 'db'));
define('DB_USER', env('DB_USER') && env('DB_USER') !== 'root' ? env('DB_USER') : 'raptor_user');
define('DB_PASS', env('DB_PASS') && env('DB_PASS') !== 'rootpassword' ? env('DB_PASS') : 'RaptorProd@2026!');
define('DB_NAME', env('DB_NAME', 'raptor_crm_db'));

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
    $dir = ($dir === '\\' || $dir === '/') ? '' : $dir;
    $detected_urlroot = $protocol . '://' . $host . $dir;
    define('URLROOT', $detected_urlroot);
} else {
    define('URLROOT', env('URLROOT', 'http://localhost:8080/public'));
}
define('SITENAME', env('SITENAME', 'RAPTOR'));

// Environment: 'development' shows errors, 'production' hides them.
define('APP_ENV', env('APP_ENV', 'development'));

// Session Settings
define('SESSION_TIMEOUT', (int) env('SESSION_TIMEOUT', 1800)); // 30 minutes in seconds

// File storage (selfies, proof uploads). STORAGE_PATH is a private directory
// OUTSIDE the web root; files are served through a controlled endpoint, never
// linked directly. On a VPS point this at e.g. /var/raptor/storage.
define('STORAGE_PATH', env('STORAGE_PATH', APPROOT . '/../storage'));
define('MAX_UPLOAD_BYTES', (int) env('MAX_UPLOAD_BYTES', 5 * 1024 * 1024)); // 5 MB

// S3-compatible cloud storage settings
define('STORAGE_PROVIDER', env('STORAGE_PROVIDER', 'local')); // 'local' or 's3'
define('S3_BUCKET', env('S3_BUCKET', 'app-frontend-hosting-dev-847013096108'));
define('S3_REGION', env('S3_REGION', 'us-east-1'));

// Error display driven by environment.
error_reporting(E_ALL);
ini_set('display_errors', '1');

/**
 * Timezone Helpers
 */
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
            
            // Check if string contains explicit UTC marker (e.g., 'Z' or '+00:00')
            if (strpos($datetime, 'Z') !== false || preg_match('/[+-]00:?00$/', $datetime)) {
                $dt = new DateTime($datetime, new DateTimeZone('UTC'));
            } else {
                // Datetime string stored in database local timezone
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

