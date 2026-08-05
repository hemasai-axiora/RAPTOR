<?php
// Raptor CRM Routing Core App Class

class App {
    protected $currentController = 'HomeController';
    protected $currentMethod = 'index';
    protected $params = [];

    public function __construct() {
        try {
            $route = $this->getRoute();

            // Parse route: controller/method
            if ($route) {
                $parts = explode('/', trim($route, '/'));
                
                // Format controller name (e.g. auth -> AuthController)
                if (isset($parts[0])) {
                    $rawName = $parts[0];
                    $studlyName = str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ', $rawName)));
                    $controllerName = $studlyName . 'Controller';
                    if (!file_exists(APPROOT . '/controllers/' . $controllerName . '.php')) {
                        $controllerName = ucfirst($rawName) . 'Controller';
                    }
                    if (file_exists(APPROOT . '/controllers/' . $controllerName . '.php')) {
                        $this->currentController = $controllerName;
                        unset($parts[0]);
                    }
                }

                // Require the controller
                require_once APPROOT . '/controllers/' . $this->currentController . '.php';
                $this->currentController = new $this->currentController;

                // Check for method name
                if (isset($parts[1])) {
                    $methodName = $parts[1];
                    $camelMethod = lcfirst(str_replace(' ', '', ucwords(str_replace('-', ' ', $methodName))));
                    if ($methodName !== '' && $methodName[0] !== '_' && is_callable([$this->currentController, $methodName])) {
                        $this->currentMethod = $methodName;
                        unset($parts[1]);
                    } elseif ($camelMethod !== '' && is_callable([$this->currentController, $camelMethod])) {
                        $this->currentMethod = $camelMethod;
                        unset($parts[1]);
                    }
                }

                // Get parameters
                $this->params = $parts ? array_values($parts) : [];
            } else {
                // Default controller
                require_once APPROOT . '/controllers/' . $this->currentController . '.php';
                $this->currentController = new $this->currentController;
            }

            // Call a callback with array of params
            call_user_func_array([$this->currentController, $this->currentMethod], $this->params);
        } catch (Throwable $e) {
            http_response_code(200);
            echo "<div style='padding:20px;background:#fff;color:#000;'>";
            echo "<h1>Application Exception</h1>";
            echo "<p><strong>Message:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
            echo "<p><strong>File:</strong> " . htmlspecialchars($e->getFile()) . ":" . $e->getLine() . "</p>";
            echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
            echo "</div>";
            exit();
        }
    }

    private function getRoute() {
        if (isset($_GET['route'])) {
            return filter_var($_GET['route'], FILTER_SANITIZE_URL);
        }
        return null;
    }
}
