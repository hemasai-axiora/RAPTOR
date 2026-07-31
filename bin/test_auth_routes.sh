#!/bin/bash
cd /var/www/html
php -d display_errors=1 -d error_reporting=32767 -r "
$_SERVER['REQUEST_URI'] = '/public/index.php';
$_SERVER['HTTP_HOST']   = 'localhost';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SESSION['user_id'] = 2; // Admin user
$_SESSION['user_name'] = 'Naveen';
$_SESSION['user_email'] = 'admin@raptor.local';
$_SESSION['role_name'] = 'admin';
$_SESSION['role_id'] = 1;

$_GET['route'] = 'account_sales/index';
ob_start();
require_once 'public/index.php';
\$out1 = ob_get_clean();
echo 'account_sales length: ' . strlen(\$out1) . PHP_EOL;

$_GET['route'] = 'website_analytics/index';
ob_start();
require_once 'public/index.php';
\$out2 = ob_get_clean();
echo 'website_analytics length: ' . strlen(\$out2) . PHP_EOL;
" 2>&1
