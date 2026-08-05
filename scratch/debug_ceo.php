<?php
require_once 'app/config/config.php';
require_once 'app/core/Database.php';
require_once 'app/core/Model.php';
require_once 'app/core/Controller.php';
require_once 'app/core/Policy.php';
require_once 'app/core/PermissionService.php';
require_once 'app/models/User.php';
require_once 'app/models/DashboardModule.php';

session_start();

$userModel = new User();
$user = $userModel->login('ceo@raptor.local', 'Raptor@12345');

echo "User found: ";
var_dump($user);

if ($user) {
    $_SESSION['user_id'] = $user->user_id;
    $_SESSION['user_role'] = $user->role_name;
    $_SESSION['role_id'] = $user->role_id;
    $_SESSION['user_name'] = $user->name;

    echo "Loaded permissions for role: " . $user->role_name . "\n";
    $perms = PermissionService::loadForUser((int)$user->user_id, (int)$user->role_id);
    print_r($perms);

    echo "Checking dashboard view permission: ";
    var_dump(Policy::can('dashboard', 'view'));

    echo "Checking dashboards for role: ";
    $dm = new DashboardModule();
    $d = $dm->dashboardsForRole($user->role_name);
    print_r($d);
}
