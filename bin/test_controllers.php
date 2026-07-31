<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

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

echo "1. Testing AccountSalesController...\n";
try {
    require_once __DIR__ . '/../app/controllers/AccountSalesController.php';
    $ctrl = new AccountSalesController();
    ob_start();
    $ctrl->index();
    $html1 = ob_get_clean();
    echo "[SUCCESS] account_sales/index rendered. Length: " . strlen($html1) . " bytes\n";
} catch (Throwable $t) {
    if (ob_get_level()) ob_end_clean();
    echo "[ERROR] AccountSales EXCEPTION: " . $t->getMessage() . " in " . $t->getFile() . ":" . $t->getLine() . PHP_EOL;
    echo $t->getTraceAsString() . PHP_EOL;
}

echo "\n2. Testing WebsiteAnalyticsController...\n";
try {
    require_once __DIR__ . '/../app/controllers/WebsiteAnalyticsController.php';
    $ctrl2 = new WebsiteAnalyticsController();
    ob_start();
    $ctrl2->index();
    $html2 = ob_get_clean();
    echo "[SUCCESS] website_analytics/index rendered. Length: " . strlen($html2) . " bytes\n";
} catch (Throwable $t) {
    if (ob_get_level()) ob_end_clean();
    echo "[ERROR] WebsiteAnalytics EXCEPTION: " . $t->getMessage() . " in " . $t->getFile() . ":" . $t->getLine() . PHP_EOL;
    echo $t->getTraceAsString() . PHP_EOL;
}
