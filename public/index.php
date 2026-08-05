<?php
// Raptor CRM Front Controller

register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        http_response_code(200);
        echo "<div style='padding:20px;background:#fff;color:#000;'>";
        echo "<h1>Fatal PHP Error Caught</h1>";
        echo "<p><strong>Message:</strong> " . htmlspecialchars($error['message']) . "</p>";
        echo "<p><strong>File:</strong> " . htmlspecialchars($error['file']) . ":" . $error['line'] . "</p>";
        echo "</div>";
    }
});

try {
    // Require config before session setup so environment constants are available.
    require_once dirname(dirname(__FILE__)) . '/app/config/config.php';

    ini_set('display_errors', '1');
    error_reporting(E_ALL);
    ini_set('log_errors', '1');

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
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

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
} catch (Throwable $e) {
    http_response_code(200);
    echo "<div style='padding:20px;background:#fff;color:#000;'>";
    echo "<h1>Front Controller Exception</h1>";
    echo "<p><strong>Message:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><strong>File:</strong> " . htmlspecialchars($e->getFile()) . ":" . $e->getLine() . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    echo "</div>";
    exit();
}
