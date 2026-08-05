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
$_SESSION['user_id'] = 25; // Employee ID
$_SESSION['user_role'] = 'employee';
$_SESSION['role_id'] = 6;
$_SESSION['user_status'] = 'active';
$_SESSION['last_activity'] = time();

PermissionService::loadForUser(25, 6);

echo "--- 1. EMPLOYEE COMMUNICATIONS TEST ---
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

echo "--- 2. EMPLOYEE MEETINGS TEST ---
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
