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
$_SESSION['user_id'] = 20; // Correct Admin ID
$_SESSION['user_role'] = 'admin';
$_SESSION['role_id'] = 1;
$_SESSION['user_status'] = 'active';
$_SESSION['last_activity'] = time();

PermissionService::loadForUser(20, 1);

echo "--- 1. COMMUNICATIONS TEST ---
";
try {
    require_once 'app/controllers/CommunicationsController.php';
    require_once 'app/models/Communication.php';
    require_once 'app/models/Lead.php';
    $c = new CommunicationsController();
    $c->index();
    echo "COMMUNICATIONS PASSED
";
} catch (Throwable $e) {
    echo "COMMUNICATIONS ERROR: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine() . "
";
    echo $e->getTraceAsString() . "
";
}

echo "--- 2. MEETINGS TEST ---
";
try {
    require_once 'app/controllers/MeetingsController.php';
    require_once 'app/models/Meeting.php';
    require_once 'app/models/Customer.php';
    $m = new MeetingsController();
    $m->index();
    echo "MEETINGS PASSED
";
} catch (Throwable $e) {
    echo "MEETINGS ERROR: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine() . "
";
    echo $e->getTraceAsString() . "
";
}

echo "--- 3. MONITORING TEST ---
";
try {
    require_once 'app/controllers/DashboardController.php';
    require_once 'app/models/Monitoring.php';
    require_once 'app/models/DashboardModule.php';
    $d = new DashboardController();
    $d->monitoring();
    echo "MONITORING PASSED
";
} catch (Throwable $e) {
    echo "MONITORING ERROR: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine() . "
";
    echo $e->getTraceAsString() . "
";
}
