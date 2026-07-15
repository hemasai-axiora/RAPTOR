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
define('DB_USER', env('DB_USER', 'root'));
define('DB_PASS', env('DB_PASS', env('DB_PASSWORD', 'rootpassword')));
define('DB_NAME', env('DB_NAME', 'raptor_crm_db'));

// App Paths & URLs
define('APPROOT', dirname(dirname(__FILE__)));

if (isset($_SERVER['HTTP_HOST'])) {
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    $script_name = $_SERVER['SCRIPT_NAME'] ?? '';
    $dir = dirname($script_name);
    $dir = ($dir === '\\' || $dir === '/') ? '' : $dir;
    $detected_urlroot = $protocol . '://' . $_SERVER['HTTP_HOST'] . $dir;
    define('URLROOT', env('URLROOT', $detected_urlroot));
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
if (APP_ENV === 'production') {
    error_reporting(0);
    ini_set('display_errors', '0');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
}

/**
 * Timezone Helpers
 */
if (!function_exists('getUserTimezone')) {
    function getUserTimezone(): string {
        return $_COOKIE['user_timezone'] ?? 'UTC';
    }
}

if (!function_exists('formatToLocalTime')) {
    function formatToLocalTime($utcDatetime, $format = 'Y-m-d H:i:s'): string {
        if (empty($utcDatetime)) return '';
        try {
            $dt = new DateTime($utcDatetime, new DateTimeZone('UTC'));
            $localTz = getUserTimezone();
            // Fallback if browser timezone name is not recognized by PHP
            try {
                $dt->setTimezone(new DateTimeZone($localTz));
            } catch (Exception $ex) {
                $dt->setTimezone(new DateTimeZone('UTC'));
            }
            return $dt->format($format);
        } catch (Exception $e) {
            return $utcDatetime;
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

