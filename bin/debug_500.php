<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

$_SERVER['REQUEST_URI'] = '/public/index.php';
$_SERVER['HTTP_HOST']   = 'localhost';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_GET['route'] = 'account_sales/index';

// Initialize session as admin user
session_start();
$_SESSION['user_id'] = 2;
$_SESSION['user_name'] = 'Naveen';
$_SESSION['user_email'] = 'admin@raptor.local';
$_SESSION['role_name'] = 'admin';
$_SESSION['role_id'] = 1;

try {
    require_once __DIR__ . '/../public/index.php';
} catch (Throwable $t) {
    echo "THROWABLE EXCEPTION: " . $t->getMessage() . " in " . $t->getFile() . " on line " . $t->getLine() . "\n";
    echo $t->getTraceAsString() . "\n";
}
