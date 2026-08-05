<?php
// Raptor CRM Front Controller

// === DIAGNOSTIC BACKDOOR ===
if (isset($_GET['diag']) && $_GET['diag'] === 'raptor2026') {
    error_reporting(E_ALL); ini_set('display_errors','1');
    $root = dirname(__DIR__);
    echo '<pre style="font-family:monospace;background:#0d1117;color:#c9d1d9;padding:20px;">';
    echo "PHP: " . PHP_VERSION . "\n";
    echo "Dir: " . __DIR__ . "\n";
    echo "Root: $root\n\n";
    echo "--- Directories ---\n";
    foreach(['app','app/core','app/config','app/controllers','app/views/followups','app/models'] as $d)
        echo "$d: " . (is_dir("$root/$d") ? "OK" : "MISSING") . "\n";
    echo "\n--- Key Files ---\n";
    foreach(['app/config/config.php','app/core/Model.php','app/core/PermissionService.php',
             'app/controllers/FollowupsController.php','app/controllers/LeadsController.php',
             'app/views/followups/index.php','app/views/layouts/main.php'] as $f) {
        $p = "$root/$f";
        echo "$f: " . (file_exists($p) ? "OK ".filesize($p)."b" : "MISSING") . "\n";
    }
    echo "\n--- DB Test ---\n";
    if (file_exists("$root/app/config/config.php")) {
        try {
            require_once "$root/app/config/config.php";
            $pdo = new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4', DB_USER, DB_PASS);
            echo "DB: OK (host=".DB_HOST.", db=".DB_NAME.")\n";
            foreach(['follow_ups','leads','customers','meetings','communications','role_permissions'] as $t) {
                try { $n=$pdo->query("SELECT COUNT(*) FROM $t")->fetchColumn(); echo "$t: $n rows\n"; }
                catch(Exception $e) { echo "$t: MISSING TABLE\n"; }
            }
            $emp = $pdo->query("SELECT email,role_name FROM users WHERE role_name='employee' LIMIT 3")->fetchAll(PDO::FETCH_ASSOC);
            echo "Employee users: " . count($emp) . "\n";
            foreach($emp as $e) echo "  - " . $e['email'] . " (" . $e['role_name'] . ")\n";
        } catch(Exception $e) { echo "DB Error: " . $e->getMessage() . "\n"; }
    } else { echo "config.php NOT FOUND\n"; }
    echo "\n--- FollowupsController Load Test ---\n";
    try {
        if (!defined('APPROOT')) define('APPROOT', "$root/app");
        if (!defined('URLROOT')) define('URLROOT', 'https://ags.raptor.unaux.com');
        if (!defined('APP_ENV')) define('APP_ENV', 'production');
        require_once APPROOT.'/core/Database.php';
        require_once APPROOT.'/core/Model.php';
        require_once APPROOT.'/core/PermissionService.php';
        require_once APPROOT.'/core/Controller.php';
        require_once APPROOT.'/models/FollowUp.php';
        $f = new FollowUp();
        $results = $f->getFollowUps([], null);
        echo "FollowUp: OK - " . count($results) . " rows\n";
    } catch(Throwable $e) {
        echo "FollowUp Error: " . $e->getMessage() . "\n";
        echo "At: " . $e->getFile() . ":" . $e->getLine() . "\n";
        echo $e->getTraceAsString() . "\n";
    }
    echo '</pre>';
    exit();
}
// === END DIAGNOSTIC ===


register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        http_response_code(200);
        echo "<div style='padding:20px;background:#fff;color:#000;'>";
        echo "<h1>Fatal PHP Error Caught</h1>";
        echo "<p><strong>Message:</strong> " . htmlspecialchars($error['message']) . "</p>";
        echo "<p><strong>File:</strong> " . htmlspecialchars($error['file']) . ":" . $error['line'] . "</p>";
        echo "</div>";
    }
});

try {
    // Require config before session setup so environment constants are available.
    require_once dirname(dirname(__FILE__)) . '/app/config/config.php';

    ini_set('display_errors', '1');
    error_reporting(E_ALL);
    ini_set('log_errors', '1');

    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(self), geolocation=(self), microphone=()');

    // Simple autoloader for core classes
    spl_autoload_register(function ($className) {
        $corePath = APPROOT . '/core/' . $className . '.php';
        $modelPath = APPROOT . '/models/' . $className . '.php';
        $servicePath = APPROOT . '/services/' . $className . '.php';
        
        if (file_exists($corePath)) {
            require_once $corePath;
        } elseif (file_exists($modelPath)) {
            require_once $modelPath;
        } elseif (file_exists($servicePath)) {
            require_once $servicePath;
        }
    });

    // Initialize Routing Engine
    $app = new App();
} catch (Throwable $e) {
    http_response_code(200);
    echo "<div style='padding:20px;background:#fff;color:#000;'>";
    echo "<h1>Front Controller Exception</h1>";
    echo "<p><strong>Message:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><strong>File:</strong> " . htmlspecialchars($e->getFile()) . ":" . $e->getLine() . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    echo "</div>";
    exit();
}
