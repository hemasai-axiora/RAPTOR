<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once 'app/config/config.php';
require_once 'app/core/Database.php';
require_once 'app/core/Model.php';
require_once 'app/core/Controller.php';
require_once 'app/core/Policy.php';
require_once 'app/core/PermissionService.php';
require_once 'app/models/User.php';
require_once 'app/models/DashboardModule.php';
require_once 'app/controllers/DashboardController.php';

session_start();
$_SESSION['user_id'] = 481;
$_SESSION['user_role'] = 'ceo';
$_SESSION['role_id'] = 12;
$_SESSION['user_name'] = 'Prem';
$_SESSION['user_status'] = 'active';

PermissionService::loadForUser(481, 12);

$controller = new DashboardController();
$controller->index();
