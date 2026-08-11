<?php
// Raptor CRM Dashboard Controller

class DashboardController extends Controller {
    private $monitoringModel;
    private $dashboardModuleModel;
    private $customDashboardModel;

    public function __construct() {
        // Enforce user authentication for all dashboard routes
        $this->requireAuth();
        $this->requirePermission('dashboard', 'view');
        $this->monitoringModel = $this->model('Monitoring');
        $this->dashboardModuleModel = $this->model('DashboardModule');
        $this->customDashboardModel = $this->model('CustomDashboard');
    }

    // Dedicated dashboard module landing page
    public function index() {
        $dashboards = $this->dashboardModuleModel->dashboardsForRole($_SESSION['user_role']);
        $data = [
            'title' => 'Dashboard Module | Raptor CRM',
            'active_tab' => 'dashboard_module',
            'dashboards' => $dashboards,
        ];

        $this->viewWithLayout('dashboard/index', 'main', $data);
    }



    public function show($key = 'sales_command') {
        $dashboard = $this->dashboardModuleModel->getDashboard($key, $_SESSION['user_role']);
        if (!$dashboard) {
            $fallback = array_key_first($this->dashboardModuleModel->dashboardsForRole($_SESSION['user_role']));
            $this->redirect('index.php?route=dashboard/show/' . ($fallback ?: 'executive'));
        }

        $prefs = $this->dashboardModuleModel->preferences((int) $_SESSION['user_id'], $key);
        $data = [
            'title' => $dashboard['label'] . ' | Dashboard Module',
            'active_tab' => 'dashboard_module',
            'dashboard' => $dashboard,
            'dashboards' => $this->dashboardModuleModel->dashboardsForRole($_SESSION['user_role']),
            'prefs' => $prefs,
            'widgets' => $this->dashboardModuleModel->visibleWidgets($dashboard, $prefs),
            'widget_meta' => $this->dashboardModuleModel->widgetMeta(),
            'dashboard_data' => $this->dashboardModuleModel->data($key, $prefs, $this->visibleUserIds()),
        ];

        $this->viewWithLayout('dashboard/module', 'main', $data);
    }

    public function configure($key = 'sales_command') {
        $dashboard = $this->dashboardModuleModel->getDashboard($key, $_SESSION['user_role']);
        if (!$dashboard) {
            $this->redirect('index.php?route=dashboard/index');
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $input = [
                'hidden_widgets'  => $_POST['hidden_widgets'] ?? [],
                'widget_order'    => $_POST['widget_order'] ?? [],
                'theme_accent'    => trim($_POST['theme_accent'] ?? ''),
                'date_range_days' => trim($_POST['date_range_days'] ?? '')
            ];
            $this->dashboardModuleModel->savePreferences(
                (int) $_SESSION['user_id'],
                $key,
                $input,
                $dashboard['widgets']
            );
            $this->audit('Updated dashboard preferences: ' . $key, 'dashboard_preferences');
        }
        $this->redirect('index.php?route=dashboard/show/' . $key);
    }

    public function templates() {
        $customDashboards = $this->customDashboardModel->getDashboardsForUser((int)$_SESSION['user_id'], $_SESSION['user_role']);
        $legacyTemplates = $this->dashboardModuleModel->templatesForUser((int)$_SESSION['user_id'], $_SESSION['user_role']);

        $this->viewWithLayout('dashboard/templates', 'main', [
            'title' => 'Dashboard Templates | Raptor CRM',
            'active_tab' => 'dashboard_module',
            'custom_dashboards' => $customDashboards,
            'legacy_templates' => $legacyTemplates,
            'dashboards' => $this->dashboardModuleModel->dashboardsForRole($_SESSION['user_role']),
            'widget_meta' => $this->dashboardModuleModel->widgetMeta(),
        ]);
    }

    public function builder($id = null) {
        $id = $id !== null ? (int)$id : 0;
        $dashboard = null;

        if ($id > 0) {
            $dashboard = $this->customDashboardModel->getDashboardById($id);
            if (!$dashboard) {
                $_SESSION['template_error'] = 'Dashboard not found.';
                $this->redirect('index.php?route=dashboard/templates');
                return;
            }
        }

        // Available Data Sources per Role
        $allDataSources = [
            'leads' => 'Leads & Pipeline',
            'campaigns' => 'Marketing Campaigns',
            'invoices' => 'Invoices & Billing',
            'attendance' => 'Employee Attendance',
            'targets' => 'Targets & Quotas',
            'tasks' => 'Task Board',
            'customers' => 'Customer Accounts',
            'website_analytics' => 'Website Analytics (GA4)',
            'text' => 'Text & Note Block'
        ];

        if (in_array($_SESSION['user_role'], ['employee', 'sales_person'])) {
            unset($allDataSources['invoices']);
        }

        $data = [
            'title' => ($dashboard ? 'Edit ' . htmlspecialchars($dashboard->name) : 'New Dashboard Builder') . ' | Raptor CRM',
            'active_tab' => 'dashboard_module',
            'dashboard' => $dashboard,
            'data_sources' => $allDataSources,
            'roles' => ['admin', 'manager', 'team_leader', 'employee', 'sales_person', 'hr', 'finance', 'analyst']
        ];

        $this->viewWithLayout('dashboard/builder', 'main', $data);
    }

    public function saveCustomDashboard() {
        while (ob_get_level()) {
            ob_end_clean();
        }
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $rawInput = file_get_contents('php://input');
            $data = json_decode($rawInput, true);

            if (!$data && !empty($_POST)) {
                $data = $_POST;
            }

            // CSRF Validation Check
            $csrfToken = $data['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_GET['csrf_token'] ?? '';
            if (!$this->validateCsrfToken($csrfToken)) {
                echo json_encode(['success' => false, 'message' => 'Security Error: CSRF token validation failed.']);
                exit;
            }

            if (empty($data['name'])) {
                echo json_encode(['success' => false, 'message' => 'Dashboard name is required.']);
                exit;
            }

            $userId = (int)($_SESSION['user_id'] ?? 0);
            if ($userId <= 0) {
                echo json_encode(['success' => false, 'message' => 'Session expired. Please log in again.']);
                exit;
            }

            try {
                $id = $this->customDashboardModel->saveDashboard($data, $userId);
                $this->audit('Saved custom dashboard ID: ' . $id, 'custom_dashboard');
                echo json_encode(['success' => true, 'id' => $id, 'message' => 'Dashboard saved successfully.']);
            } catch (Throwable $e) {
                echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
            }
            exit;
        }
    }

    public function duplicateDashboard($id = null) {
        $id = (int)$id;
        if ($id > 0) {
            $newId = $this->customDashboardModel->duplicateDashboard($id, (int)$_SESSION['user_id']);
            if ($newId) {
                $_SESSION['template_success'] = 'Dashboard duplicated successfully.';
            } else {
                $_SESSION['template_error'] = 'Failed to duplicate dashboard.';
            }
        }
        $this->redirect('index.php?route=dashboard/templates');
    }

    public function deleteDashboard($id = null) {
        $id = (int)$id;
        if ($id > 0) {
            $isAdmin = ($_SESSION['user_role'] === 'admin');
            $res = $this->customDashboardModel->deleteDashboard($id, (int)$_SESSION['user_id'], $isAdmin);
            if ($res) {
                $_SESSION['template_success'] = 'Dashboard deleted cleanly.';
            } else {
                $_SESSION['template_error'] = 'Could not delete dashboard.';
            }
        }
        $this->redirect('index.php?route=dashboard/templates');
    }

    public function setDefaultDashboard($id = null) {
        $id = (int)$id;
        if ($id > 0) {
            $this->customDashboardModel->setDefaultDashboard($id, (int)$_SESSION['user_id']);
            $_SESSION['template_success'] = 'Set as your default dashboard.';
        }
        $this->redirect('index.php?route=dashboard/templates');
    }

    public function widgetData() {
        header('Content-Type: application/json');
        $raw = file_get_contents('php://input');
        $widget = json_decode($raw, true) ?: $_POST;

        $data = $this->customDashboardModel->getWidgetData($widget, $_SESSION['user_role'], (int)$_SESSION['user_id'], $_GET);
        echo json_encode(['success' => true, 'data' => $data]);
        exit;
    }

    public function createTemplate() {
        $this->requirePermission('dashboard', 'manage');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $_POST = filter_input_array(INPUT_POST, FILTER_SANITIZE_SPECIAL_CHARS) ?: [];
            $name = strip_tags(trim($_POST['name'] ?? $_POST['template_name'] ?? ''));
            $desc = strip_tags(trim($_POST['description'] ?? ''));

            if (empty($name) || !Validation::validateHasAlphanumeric($name)) {
                $_SESSION['template_error'] = 'Template Name must contain alphanumeric characters.';
                $this->redirect('index.php?route=dashboard/templates');
                return;
            }

            if (!empty($desc) && !Validation::validateHasAlphanumeric($desc)) {
                $_SESSION['template_error'] = 'Description must contain alphanumeric characters.';
                $this->redirect('index.php?route=dashboard/templates');
                return;
            }

            $this->dashboardModuleModel->createTemplate($_POST, (int) $_SESSION['user_id'], $_SESSION['user_role']);
            $this->audit('Created dashboard template', 'dashboard_templates');
            $_SESSION['template_success'] = 'Dashboard template created successfully.';
        }

        $this->redirect('index.php?route=dashboard/templates');
    }

    // Executive Marketing Overview Dashboard
    public function executive() {
        // Employers, Managers, and Admins can access
        $allowedRoles = ['admin', 'manager', 'employer'];
        if (!in_array($_SESSION['user_role'], $allowedRoles)) {
            if ($_SESSION['user_role'] === 'analyst') {
                $this->redirect('index.php?route=dashboard/channels');
            } else {
                $this->redirect('index.php?route=dashboard/index');
            }
        }

        $data = [
            'title' => 'Executive Marketing Overview | Raptor CRM',
            'active_tab' => 'executive',
            'is_readonly' => ($_SESSION['user_role'] === 'employer')
        ];

        // Render dashboard layout with content view
        $this->viewWithLayout('dashboard/executive', 'main', $data);
    }

    // Channel & Campaign Performance Dashboard
    public function channels() {
        // Analysts, Managers, and Admins can access
        $allowedRoles = ['admin', 'manager', 'analyst'];
        if (!in_array($_SESSION['user_role'], $allowedRoles)) {
            if ($_SESSION['user_role'] === 'employer') {
                $this->redirect('index.php?route=dashboard/executive');
            } else {
                $this->redirect('index.php?route=dashboard/index');
            }
        }

        $data = [
            'title' => 'Channel & Campaign Performance | Raptor CRM',
            'active_tab' => 'channels'
        ];

        $this->viewWithLayout('dashboard/channels', 'main', $data);
    }

    // Customer Intelligence & AI Analytics Dashboard
    public function customer() {
        // Analysts, Managers, and Admins can access
        $allowedRoles = ['admin', 'manager', 'analyst'];
        if (!in_array($_SESSION['user_role'], $allowedRoles)) {
            $this->redirect('index.php?route=dashboard/index');
        }

        $data = [
            'title' => 'Customer Intelligence & Analytics | Raptor CRM',
            'active_tab' => 'customer'
        ];

        $this->viewWithLayout('dashboard/customer', 'main', $data);
    }

    public function monitoring() {
        if (!in_array($_SESSION['user_role'], ['admin', 'ceo', 'employer', 'hr', 'manager', 'team_leader'], true)) {
            $this->redirect('index.php?route=dashboard/index');
            return;
        }

        $scope = $this->visibleUserIds();
        $rollup = $this->monitoringModel->todayRollup($scope);
        $pipeline = $this->monitoringModel->pipelineForecast($scope);
        $live_board = $this->monitoringModel->liveBoard($scope);

        $data = [
            'title' => 'Sales Monitoring Command Center | Raptor CRM',
            'active_tab' => 'monitoring_dashboard',
            'rollup' => $rollup,
            'pipeline' => $pipeline,
            'live_board' => $live_board,
            'team' => $live_board,
            'followups' => $rollup['followups'] ?? ['pending' => 0, 'missed' => 0]
        ];

        $this->viewWithLayout('dashboard/monitoring', 'main', $data);
    }

    public function day($userId = 0) {
        if (!in_array($_SESSION['user_role'], ['admin', 'ceo', 'employer', 'hr', 'manager', 'team_leader'], true)) {
            $this->redirect('index.php?route=attendance/index');
        }
        $userId = (int) $userId;
        $scope = $this->visibleUserIds();
        if ($scope !== null && !in_array($userId, $scope, true)) {
            $this->viewWithLayout('errors/403', 'main', [
                'title' => 'Access Denied',
                'message' => 'This employee is outside your team scope.'
            ]);
            return;
        }

        $date = (isset($_GET['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date'])) ? $_GET['date'] : date('Y-m-d');
        $data = array_merge($this->monitoringModel->employeeDay($userId, $date), [
            'title' => 'Employee Day Drill-down | Raptor CRM',
            'active_tab' => 'monitoring_dashboard',
            'date' => $date,
            'user_id' => $userId,
        ]);

        $this->viewWithLayout('dashboard/day', 'main', $data);
    }
}
