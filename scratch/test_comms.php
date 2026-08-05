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

require_once 'app/controllers/CommunicationsController.php';
require_once 'app/models/Communication.php';
require_once 'app/models/Lead.php';
require_once 'app/models/Customer.php';

$c = new CommunicationsController();
$c->index();
