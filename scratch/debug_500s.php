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
$_SESSION['user_id'] = 481;
$_SESSION['user_role'] = 'hr';
$_SESSION['role_id'] = 5;
$_SESSION['user_status'] = 'active';

echo "--- TESTING COMMUNICATIONS CONTROLLER ---\n";
try {
    require_once 'app/controllers/CommunicationsController.php';
    require_once 'app/models/Lead.php';
    require_once 'app/models/Communication.php';
    require_once 'app/models/Customer.php';
    $c = new CommunicationsController();
    $c->index();
    echo "COMMUNICATIONS PASSED\n";
} catch (Throwable $e) {
    echo "COMMUNICATIONS ERROR: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo $e->getTraceAsString() . "\n";
}

echo "--- TESTING MEETINGS CONTROLLER ---\n";
try {
    require_once 'app/controllers/MeetingsController.php';
    require_once 'app/models/Meeting.php';
    $m = new MeetingsController();
    $m->index();
    echo "MEETINGS PASSED\n";
} catch (Throwable $e) {
    echo "MEETINGS ERROR: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo $e->getTraceAsString() . "\n";
}

echo "--- TESTING MONITORING ACTION ---\n";
try {
    require_once 'app/controllers/DashboardController.php';
    require_once 'app/models/Monitoring.php';
    $d = new DashboardController();
    $d->monitoring();
    echo "MONITORING PASSED\n";
} catch (Throwable $e) {
    echo "MONITORING ERROR: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo $e->getTraceAsString() . "\n";
}
