<?php
// Raptor CRM Routing Core App Class

class App {
    protected $currentController = 'HomeController';
    protected $currentMethod = 'index';
    protected $params = [];

    public function __construct() {
        $route = $this->getRoute();

        // Parse route: controller/method
        if ($route) {
            $parts = explode('/', trim($route, '/'));
            
            // Format controller name (e.g. auth -> AuthController)
            if (isset($parts[0])) {
                $controllerName = ucfirst($parts[0]) . 'Controller';
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
                if ($methodName !== '' && $methodName[0] !== '_' && is_callable([$this->currentController, $methodName])) {
                    $this->currentMethod = $methodName;
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
        try {
            call_user_func_array([$this->currentController, $this->currentMethod], $this->params);
        } catch (Exception $e) {
            // Handle error (render 404 or 500)
            if (defined('APP_ENV') && APP_ENV === 'production') {
                http_response_code(500);
                if (file_exists(APPROOT . '/views/errors/500.php')) {
                    // Start buffer or render layout
                    $data = [
                        'title' => 'Internal Server Error | Raptor CRM',
                        'message' => 'An unexpected server error occurred. Please try again later.'
                    ];
                    if (file_exists(APPROOT . '/views/layouts/main.php') && isset($_SESSION['user_id'])) {
                        // Render inside layout
                        extract($data);
                        ob_start();
                        require APPROOT . '/views/errors/500.php';
                        $content = ob_get_clean();
                        require APPROOT . '/views/layouts/main.php';
                    } else {
                        // Render standalone
                        require APPROOT . '/views/errors/500.php';
                    }
                } else {
                    echo "Internal Server Error";
                }
            } else {
                echo "Routing Error: " . htmlspecialchars($e->getMessage());
            }
        }
    }

    private function getRoute() {
        if (isset($_GET['route'])) {
            return filter_var($_GET['route'], FILTER_SANITIZE_URL);
        }
        return null;
    }
}
