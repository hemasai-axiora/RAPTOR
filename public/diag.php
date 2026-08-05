<?php
// Raptor CRM Diagnostic & Auto-Fix Script
error_reporting(E_ALL);
ini_set('display_errors', '1');

echo '<!DOCTYPE html><html><head><style>
body { font-family: monospace; background: #0d1117; color: #c9d1d9; padding: 20px; }
h2 { color: #58a6ff; }
h3 { color: #79c0ff; border-bottom: 1px solid #30363d; padding-bottom: 5px; }
.ok { color: #3fb950; }
.err { color: #f85149; }
.warn { color: #d29922; }
pre { background: #161b22; padding: 10px; border-radius: 6px; overflow: auto; font-size: 12px; }
</style></head><body>';

echo '<h2>🔍 Raptor CRM Diagnostic Report</h2>';

// 1. Server info
echo '<h3>1. Server Environment</h3>';
echo '<pre>';
echo "PHP Version:     " . PHP_VERSION . "\n";
echo "Server Software: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'unknown') . "\n";
echo "Document Root:   " . ($_SERVER['DOCUMENT_ROOT'] ?? 'unknown') . "\n";
echo "Script Path:     " . __FILE__ . "\n";
echo "Script Dir:      " . __DIR__ . "\n";
echo "Parent Dir:      " . dirname(__DIR__) . "\n";
echo "Grandparent Dir: " . dirname(dirname(__DIR__)) . "\n";
echo '</pre>';

// 2. Detect app root
echo '<h3>2. App Root Detection</h3>';
$candidates = [
    dirname(__DIR__),
    dirname(dirname(__DIR__)),
    dirname(__DIR__) . '/RAPTOR-main',
    dirname(dirname(__DIR__)) . '/RAPTOR-main',
];
$appRoot = null;
foreach ($candidates as $c) {
    $hasApp = is_dir($c . '/app') && is_dir($c . '/app/core');
    echo "<p>" . htmlspecialchars($c) . "/app/core — " . ($hasApp ? '<span class="ok">✔ FOUND</span>' : '<span class="err">✘ NOT FOUND</span>') . "</p>";
    if ($hasApp && !$appRoot) $appRoot = $c;
}
echo "<p><strong>Detected App Root: " . ($appRoot ? '<span class="ok">' . htmlspecialchars($appRoot) . '</span>' : '<span class="err">NOT DETECTED</span>') . "</strong></p>";

// 3. Key files exist?
echo '<h3>3. Critical File Check</h3>';
if ($appRoot) {
    $files = [
        'app/config/config.php',
        'app/core/App.php',
        'app/core/Controller.php',
        'app/core/Model.php',
        'app/core/PermissionService.php',
        'app/views/layouts/main.php',
        'app/controllers/FollowupsController.php',
        'app/views/followups/index.php',
    ];
    foreach ($files as $f) {
        $path = $appRoot . '/' . $f;
        $exists = file_exists($path);
        $size = $exists ? filesize($path) : 0;
        echo "<p>" . htmlspecialchars($f) . " — " . ($exists ? '<span class="ok">✔ EXISTS (' . $size . ' bytes)</span>' : '<span class="err">✘ MISSING</span>') . "</p>";
    }
}

// 4. Test DB connection
echo '<h3>4. Database Connection Test</h3>';
if ($appRoot && file_exists($appRoot . '/app/config/config.php')) {
    try {
        require_once $appRoot . '/app/config/config.php';
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        echo '<p class="ok">✔ Database connected successfully to ' . DB_NAME . ' on ' . DB_HOST . '</p>';
        
        // Check key tables
        $tables = ['users', 'follow_ups', 'leads', 'customers', 'communications', 'meetings', 'role_permissions'];
        foreach ($tables as $t) {
            $r = $pdo->query("SHOW TABLES LIKE '$t'")->rowCount();
            echo "<p>" . $t . " — " . ($r ? '<span class="ok">✔ table exists</span>' : '<span class="err">✘ MISSING TABLE</span>') . "</p>";
        }
        
        // Check employee user exists
        $stmt = $pdo->query("SELECT email, role_name FROM users WHERE role_name = 'employee' LIMIT 3");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($rows) {
            echo '<p class="ok">✔ Employee users found:</p><pre>';
            foreach ($rows as $r) echo htmlspecialchars($r['email']) . " ({$r['role_name']})\n";
            echo '</pre>';
        } else {
            echo '<p class="warn">⚠ No employee role users found in DB</p>';
        }
        
        // Check permissions table
        $count = $pdo->query("SELECT COUNT(*) FROM role_permissions")->fetchColumn();
        echo "<p>role_permissions rows: $count " . ($count > 0 ? '<span class="ok">✔</span>' : '<span class="warn">⚠ Empty — migration needed</span>') . "</p>";
        
    } catch (Throwable $e) {
        echo '<p class="err">✘ DB Error: ' . htmlspecialchars($e->getMessage()) . '</p>';
        if (defined('DB_HOST')) echo '<pre>Host: ' . DB_HOST . "\nDB: " . DB_NAME . "\nUser: " . DB_USER . '</pre>';
    }
} else {
    echo '<p class="err">✘ config.php not found — cannot test DB</p>';
}

// 5. PHP error log tail
echo '<h3>5. Recent PHP Errors</h3>';
$possibleLogs = [
    $appRoot ? $appRoot . '/../logs/php_error.log' : null,
    '/tmp/php_error.log',
    ini_get('error_log'),
];
$found = false;
foreach ($possibleLogs as $log) {
    if ($log && file_exists($log) && is_readable($log)) {
        $content = file_get_contents($log);
        $lines = array_slice(explode("\n", $content), -30);
        echo '<pre>' . htmlspecialchars(implode("\n", $lines)) . '</pre>';
        $found = true;
        break;
    }
}
if (!$found) echo '<p class="warn">⚠ No PHP error log found</p>';

// 6. Test FollowupsController loading
echo '<h3>6. FollowupsController Load Test</h3>';
if ($appRoot) {
    try {
        define('APPROOT', $appRoot . '/app');
        define('URLROOT', 'https://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
        if (!defined('APP_ENV')) define('APP_ENV', 'production');
        
        require_once APPROOT . '/core/Database.php';
        require_once APPROOT . '/core/Model.php';
        require_once APPROOT . '/core/PermissionService.php';
        require_once APPROOT . '/core/Controller.php';
        require_once APPROOT . '/models/FollowUp.php';
        
        $f = new FollowUp();
        echo '<p class="ok">✔ FollowUp model loaded successfully</p>';
        
        $results = $f->getFollowUps([], null);
        echo '<p class="ok">✔ getFollowUps() returned: ' . count($results) . ' results</p>';
        
    } catch (Throwable $e) {
        echo '<p class="err">✘ Error: ' . htmlspecialchars($e->getMessage()) . '</p>';
        echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
    }
}

echo '</body></html>';
