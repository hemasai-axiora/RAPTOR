<?php
// Raptor CRM Front Controller

// ── Load config & Composer autoloader early for Sentry bootstrap ──
require_once dirname(dirname(__FILE__)) . '/app/config/config.php';

$composerAutoload = dirname(dirname(__FILE__)) . '/vendor/autoload.php';
if (file_exists($composerAutoload)) {
    require_once $composerAutoload;
}

// ── Initialize Sentry SDK (errors + tracing + profiling) ──
if (defined('SENTRY_DSN') && SENTRY_DSN !== '' && SENTRY_DSN !== 'your-sentry-dsn-here' && class_exists('\Sentry\SentrySdk')) {
    \Sentry\init([
        'dsn'                  => SENTRY_DSN,
        'environment'          => defined('SENTRY_ENVIRONMENT') ? SENTRY_ENVIRONMENT : 'production',
        'release'              => 'raptor@' . (defined('APP_VERSION') ? APP_VERSION : '0.0.0'),
        'traces_sample_rate'   => defined('SENTRY_TRACES_SAMPLE_RATE') ? SENTRY_TRACES_SAMPLE_RATE : 1.0,
        'profiles_sample_rate' => defined('SENTRY_PROFILES_SAMPLE_RATE') ? SENTRY_PROFILES_SAMPLE_RATE : 1.0,
        'http_ssl_verify_peer' => defined('SENTRY_SSL_VERIFY') ? SENTRY_SSL_VERIFY : true,
        'send_default_pii'     => true,

        // Attach X-Request-ID as a tag on every event
        'before_send' => function (\Sentry\Event $event): ?\Sentry\Event {
            $requestId = $_SERVER['HTTP_X_REQUEST_ID'] ?? null;
            if ($requestId) {
                $event->setTag('request_id', $requestId);
            }
            // Tag the route for easy filtering in Sentry
            $route = $_GET['route'] ?? 'home/index';
            $event->setTag('route', $route);
            return $event;
        },

        // Attach route to every transaction for performance monitoring
        'before_send_transaction' => function (\Sentry\Event $event): ?\Sentry\Event {
            $route = $_GET['route'] ?? 'home/index';
            $event->setTag('route', $route);
            return $event;
        },
    ]);

    // Add breadcrumb with the current route
    \Sentry\addBreadcrumb(new \Sentry\Breadcrumb(
        \Sentry\Breadcrumb::LEVEL_INFO,
        \Sentry\Breadcrumb::TYPE_NAVIGATION,
        'route',
        'Raptor route: ' . ($_GET['route'] ?? 'home/index')
    ));
}

// ── Fatal error handler (also reports to Sentry) ──
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        // Sentry captures fatal errors automatically via its own shutdown handler,
        // but we still render a user-friendly page.
        http_response_code(200);
        echo "<div style='padding:20px;background:#fff;color:#000;'>";
        echo "<h1>Fatal PHP Error Caught</h1>";
        echo "<p><strong>Message:</strong> " . htmlspecialchars($error['message']) . "</p>";
        echo "<p><strong>File:</strong> " . htmlspecialchars($error['file']) . ":" . $error['line'] . "</p>";
        echo "</div>";
    }
});

try {
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

    // Set Sentry user context after session is available
    if (class_exists('\Sentry\SentrySdk') && \Sentry\SentrySdk::getCurrentHub()->getClient()) {
        \Sentry\configureScope(function (\Sentry\State\Scope $scope): void {
            if (isset($_SESSION['user_id'])) {
                $scope->setUser([
                    'id'       => $_SESSION['user_id'],
                    'username' => $_SESSION['user_name'] ?? null,
                    'email'    => $_SESSION['user_email'] ?? null,
                    'segment'  => $_SESSION['user_role'] ?? null,
                ]);
            }
            // Attach X-Request-ID as context
            $requestId = $_SERVER['HTTP_X_REQUEST_ID'] ?? $_SERVER['HTTP_X_CORRELATION_ID'] ?? bin2hex(random_bytes(16));
            $scope->setTag('request_id', $requestId);
            $scope->setContext('raptor', [
                'version'    => defined('APP_VERSION') ? APP_VERSION : 'unknown',
                'request_id' => $requestId,
                'route'      => $_GET['route'] ?? 'home/index',
            ]);
        });
    }

    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(self), geolocation=(self), microphone=()');

    // Simple autoloader for core classes
    spl_autoload_register(function ($className) {
        if ($className === 'PreflightContext' || $className === 'Finding' || $className === 'MarketingRule') {
            require_once APPROOT . '/services/PreflightEngine.php';
            return;
        }

        $corePath    = APPROOT . '/core/'             . $className . '.php';
        $modelPath   = APPROOT . '/models/'           . $className . '.php';
        $servicePath = APPROOT . '/services/'         . $className . '.php';
        $mailerPath  = APPROOT . '/services/mailers/' . $className . '.php';
        
        if (file_exists($corePath)) {
            require_once $corePath;
        } elseif (file_exists($modelPath)) {
            require_once $modelPath;
        } elseif (file_exists($servicePath)) {
            require_once $servicePath;
        } elseif (file_exists($mailerPath)) {
            require_once $mailerPath;
        }
    });

    // Initialize Routing Engine
    $app = new App();
} catch (Throwable $e) {
    // Report to Sentry before rendering the error page
    if (class_exists('\Sentry\SentrySdk') && \Sentry\SentrySdk::getCurrentHub()->getClient()) {
        \Sentry\captureException($e);
    }
    http_response_code(200);
    echo "<div style='padding:20px;background:#fff;color:#000;'>";
    echo "<h1>Front Controller Exception</h1>";
    echo "<p><strong>Message:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><strong>File:</strong> " . htmlspecialchars($e->getFile()) . ":" . $e->getLine() . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    echo "</div>";
    exit();
}
