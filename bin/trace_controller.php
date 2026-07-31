<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

set_error_handler(function($errno, $errstr, $errfile, $errline) {
    echo "ERROR [$errno] $errstr in $errfile:$errline\n";
});

set_exception_handler(function($e) {
    echo "EXCEPTION: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo $e->getTraceAsString() . "\n";
});

register_shutdown_function(function() {
    $err = error_get_last();
    if ($err) {
        echo "SHUTDOWN ERROR: {$err['message']} in {$err['file']}:{$err['line']}\n";
    }
});

require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/core/Controller.php';
require_once __DIR__ . '/../app/core/Model.php';

$db = Database::getInstance()->getConnection();
$stmt = $db->query("SELECT user_id, name, email, r.role_name, u.role_id FROM users u JOIN roles r ON u.role_id = r.role_id WHERE u.status = 'active' LIMIT 1");
$user = $stmt->fetch(PDO::FETCH_ASSOC);

session_start();
$_SESSION['user_id'] = (int)$user['user_id'];
$_SESSION['user_name'] = $user['name'];
$_SESSION['user_email'] = $user['email'];
$_SESSION['role_name'] = $user['role_name'];
$_SESSION['role_id'] = (int)$user['role_id'];
$_SESSION['last_activity'] = time();

echo "Step 1: Loading WebsiteAnalyticsController...\n";
require_once __DIR__ . '/../app/controllers/WebsiteAnalyticsController.php';

echo "Step 2: Instantiating WebsiteAnalyticsController...\n";
$ctrl = new WebsiteAnalyticsController();

echo "Step 3: Calling index()...\n";
$ctrl->index();

echo "Step 4: Done.\n";
