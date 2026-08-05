<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once 'app/config/config.php';
require_once 'app/core/Database.php';
require_once 'app/core/Model.php';
require_once 'app/core/Controller.php';
require_once 'app/core/Policy.php';
require_once 'app/core/PermissionService.php';

session_start();
$_SESSION['user_id'] = 1;
$_SESSION['user_role'] = 'admin';
$_SESSION['role_id'] = 1;
$_SESSION['user_status'] = 'active';

PermissionService::loadForUser(1, 1);

require_once 'app/controllers/DashboardController.php';
require_once 'app/models/Monitoring.php';
require_once 'app/models/DashboardModule.php';

$d = new DashboardController();

ob_start();
try {
    $d->monitoring();
    $out = ob_get_clean();
    echo "SUCCESS, Output length: " . strlen($out) . " bytes\n";
} catch (Throwable $e) {
    ob_end_clean();
    echo "CATCH ERROR: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo $e->getTraceAsString() . "\n";
}
