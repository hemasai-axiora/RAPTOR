<?php
// Raptor CRM Front Controller

// Require config before session setup so environment constants are available.
require_once dirname(dirname(__FILE__)) . '/app/config/config.php';

$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => $https,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(self), geolocation=(self), microphone=()');

// Simple autoloader for core classes
spl_autoload_register(function ($className) {
    $corePath = APPROOT . '/core/' . $className . '.php';
    $modelPath = APPROOT . '/models/' . $className . '.php';
    $servicePath = APPROOT . '/services/' . $className . '.php';
    
    if (file_exists($corePath)) {
        require_once $corePath;
    } elseif (file_exists($modelPath)) {
        require_once $modelPath;
    } elseif (file_exists($servicePath)) {
        require_once $servicePath;
    }
});

// Initialize Routing Engine
$app = new App();
