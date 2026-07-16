<?php
// Raptor CRM Core Base Controller

class Controller {
    // Load model
    public function model($model) {
        require_once APPROOT . '/models/' . $model . '.php';
        return new $model();
    }

    // Render view
    public function view($view, $data = []) {
        // Extract data to make variables available in view
        extract($data);

        // Check if view file exists
        if (file_exists(APPROOT . '/views/' . $view . '.php')) {
            // Require view file
            require APPROOT . '/views/' . $view . '.php';
        } else {
            die("View does not exist: " . $view);
        }
    }

    // Render view inside a layout
    public function viewWithLayout($view, $layout, $data = []) {
        // Ensure CSRF token is generated and active in session
        $this->generateCsrfToken();

        // Extract data
        extract($data);

        // Start output buffering for child view
        ob_start();
        if (file_exists(APPROOT . '/views/' . $view . '.php')) {
            require APPROOT . '/views/' . $view . '.php';
        } else {
            die("View does not exist: " . $view);
        }
        $content = ob_get_clean();

        // Load layout and pass content and data
        if (file_exists(APPROOT . '/views/layouts/' . $layout . '.php')) {
            require_once APPROOT . '/views/layouts/' . $layout . '.php';
        } else {
            die("Layout does not exist: " . $layout);
        }
    }

    // Redirect helper
    public function redirect($url) {
        header('Location: ' . URLROOT . '/' . ltrim($url, '/'));
        exit();
    }

    // Generate CSRF token
    protected function generateCsrfToken() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    // Validate CSRF token
    protected function validateCsrfToken($token) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (!isset($_SESSION['csrf_token']) || $token !== $_SESSION['csrf_token']) {
            return false;
        }
        return true;
    }

    // Check if user is logged in
    protected function isLoggedIn() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['user_id'])) {
            return false;
        }

        // Check session inactivity timeout
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
            // Session expired
            session_unset();
            session_destroy();
            return false;
        }

        $_SESSION['last_activity'] = time(); // Refresh active timer
        return true;
    }

    // Require authentication
    protected function requireAuth() {
        if (!$this->isLoggedIn()) {
            $this->redirect('index.php?route=auth/login');
        }

        // Force password reset gate
        if (isset($_SESSION['force_password_reset']) && $_SESSION['force_password_reset'] == 1) {
            $route = $_GET['route'] ?? '';
            if ($route !== 'auth/reset_forced_password' && $route !== 'auth/logout') {
                $this->redirect('index.php?route=auth/reset_forced_password');
            }
        }

        // Automatic CSRF token validation on all authenticated POST requests
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $csrf = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
            if (!$this->validateCsrfToken($csrf)) {
                http_response_code(403);
                die('Security Error: CSRF token validation failed.');
            }

            $route = $_GET['route'] ?? '';
            if (Policy::canRequestDataEdit()
                && strpos($route, 'editrequests/') !== 0
                && preg_match('#(^|/)edit(/|$)#', $route)
            ) {
                http_response_code(403);
                die('Governance policy: managers must submit a data edit request with a comment for admin approval.');
            }
        }
    }

    // Check RBAC permissions for current user
    protected function hasPermission($permissionName) {
        if (!$this->isLoggedIn()) {
            return false;
        }

        // If user is Admin, they have all permissions
        if ($_SESSION['user_role'] === 'admin') {
            return true;
        }

        // Check permission list stored in session
        if (isset($_SESSION['permissions']) && in_array($permissionName, $_SESSION['permissions'])) {
            return true;
        }

        return false;
    }

    // Enforce role permission boundary
    protected function requirePermission($module, $action = null, $record = null) {
        $this->requireAuth();
        
        $permitted = false;
        if ($action === null) {
            if (strpos($module, '.') !== false) {
                list($mod, $act) = explode('.', $module, 2);
                $permitted = PermissionService::can($mod, $act, $record);
            } else {
                $permitted = $this->hasPermission($module);
            }
        } else {
            $permitted = PermissionService::can($module, $action, $record);
        }

        if (!$permitted) {
            // Render 403 Access Denied
            $this->viewWithLayout('errors/403', 'main', [
                'title' => 'Access Denied',
                'message' => 'You do not have permission to access this resource.'
            ]);
            exit();
        }
    }

    // ------------------------------------------------------------------
    // JSON / AJAX layer (Sprint 0)
    // The dynamic UI calls internal endpoints via fetch/AJAX and expects
    // JSON. These helpers standardize responses and auth for those routes.
    // ------------------------------------------------------------------

    /** Emit a JSON response and stop. */
    protected function json($payload, int $status = 200) {
        if (ob_get_length()) {
            ob_clean();
        }
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload);
        exit();
    }

    /** Standard success envelope. */
    protected function jsonOk($data = null, string $message = 'OK') {
        $this->json(['success' => true, 'message' => $message, 'data' => $data]);
    }

    /** Standard error envelope. */
    protected function jsonError(string $message, int $status = 400, $errors = null) {
        $this->json(['success' => false, 'message' => $message, 'errors' => $errors], $status);
    }

    /**
     * Auth guard for JSON/AJAX endpoints. Unlike requireAuth() it never
     * redirects to an HTML login page (which would break a fetch call) —
     * it returns a 401/403 JSON body instead. CSRF is still enforced on
     * writes, accepting the token via header (X-CSRF-Token) or body.
     */
    protected function requireAuthApi() {
        if (!$this->isLoggedIn()) {
            $this->jsonError('Authentication required.', 401);
        }

        $limit = (int) Security::setting('rate.api_limit', 120);
        $window = (int) Security::setting('rate.api_window_seconds', 60);
        $bucket = 'api:' . Security::clientIp() . ':' . ($_SESSION['user_id'] ?? 'guest') . ':' . ($_GET['route'] ?? '');
        if (!Security::rateLimit($bucket, $limit, $window)) {
            $this->jsonError('Too many requests. Please retry shortly.', 429);
        }

        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            $csrf = $_POST['csrf_token']
                ?? $_SERVER['HTTP_X_CSRF_TOKEN']
                ?? $_GET['csrf_token']
                ?? '';
            if (!$this->validateCsrfToken($csrf)) {
                $this->jsonError('CSRF token validation failed.', 403);
            }
        }
    }

    /** Read a JSON request body into an associative array (AJAX POSTs). */
    protected function jsonInput(): array {
        $raw = file_get_contents('php://input');
        if ($raw === '' || $raw === false) {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    // ------------------------------------------------------------------
    // Team-subtree data scoping (Sprint 1)
    // Managers/team leaders may only see users within their org subtree;
    // sales persons see only themselves; admins see everyone.
    // Returns null to mean "no restriction" (admin) so callers can skip
    // the WHERE clause entirely.
    // ------------------------------------------------------------------

    /**
     * Write an audit trail entry to activity_logs (extended in migration 0002).
     * Safe to call even before the audit columns exist (falls back to action only).
     */
    protected function audit(string $action, ?string $entityType = null, ?int $entityId = null, $before = null, $after = null) {
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare(
                'INSERT INTO activity_logs (user_id, action, entity_type, entity_id, before_json, after_json, ip_address, user_agent)
                 VALUES (:uid, :act, :etype, :eid, :before, :after, :ip, :ua)'
            );
            $stmt->execute([
                ':uid'    => $_SESSION['user_id'] ?? null,
                ':act'    => $action,
                ':etype'  => $entityType,
                ':eid'    => $entityId,
                ':before' => $before !== null ? json_encode($before) : null,
                ':after'  => $after !== null ? json_encode($after) : null,
                ':ip'     => $_SERVER['REMOTE_ADDR'] ?? null,
                ':ua'     => $_SERVER['HTTP_USER_AGENT'] ?? null,
            ]);
        } catch (Exception $e) {
            // Fall back to a minimal insert if the extended columns aren't present yet.
            try {
                $db = Database::getInstance()->getConnection();
                $stmt = $db->prepare('INSERT INTO activity_logs (user_id, action, ip_address, user_agent) VALUES (:uid, :act, :ip, :ua)');
                $stmt->execute([
                    ':uid' => $_SESSION['user_id'] ?? null,
                    ':act' => $action,
                    ':ip'  => $_SERVER['REMOTE_ADDR'] ?? null,
                    ':ua'  => $_SERVER['HTTP_USER_AGENT'] ?? null,
                ]);
            } catch (Exception $e2) { /* audit is best-effort */ }
        }
    }

    protected function visibleUserIds() {
        if (!$this->isLoggedIn()) {
            return [];
        }

        $role = $_SESSION['user_role'];
        $uid  = (int) $_SESSION['user_id'];

        if ($role === 'admin') {
            return null; // unrestricted
        }

        if ($role === 'hr') {
            // HR can see Managers, Team Leaders, Finance, and Analysts
            $db = Database::getInstance()->getConnection();
            $stmt = $db->query("SELECT u.user_id FROM users u 
                                JOIN roles r ON u.role_id = r.role_id 
                                WHERE r.role_name IN ('manager', 'team_leader', 'finance', 'analyst')");
            $ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN)) ?: [];
            $ids[] = $uid; // Include self
            return array_values(array_unique($ids));
        }

        if (Policy::isEmployee() || in_array($role, ['analyst', 'employer'], true)) {
            return [$uid]; // self only
        }

        // manager / team_leader: self + everyone in teams they own/lead,
        // plus direct reports via employees.reporting_manager_id.
        $db = Database::getInstance()->getConnection();
        $sql = "SELECT DISTINCT e.user_id
                FROM employees e
                LEFT JOIN teams t ON e.team_id = t.team_id
                WHERE e.reporting_manager_id = :uid
                   OR t.team_leader_user_id = :uid2
                   OR t.manager_user_id = :uid3";
        $stmt = $db->prepare($sql);
        $stmt->execute([':uid' => $uid, ':uid2' => $uid, ':uid3' => $uid]);

        $ids = [$uid];
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $id) {
            $ids[] = (int) $id;
        }
        return array_values(array_unique($ids));
    }
}
