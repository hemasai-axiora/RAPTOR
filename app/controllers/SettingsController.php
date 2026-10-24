<?php
// Raptor CRM Settings Controller (Admin Only)

class SettingsController extends Controller {
    private $db;
    private $alertRuleModel;

    public function __construct() {
        $this->requireAuth();
        $this->requirePermission('settings', 'manage');

        $this->db = Database::getInstance()->getConnection();
        $this->alertRuleModel = $this->model('AlertRule');
    }

    // Load Settings view
    public function index() {
        // Fetch current settings
        $stmt = $this->db->query('SELECT * FROM settings');
        $rows = $stmt->fetchAll(PDO::FETCH_OBJ);
        
        $settings = [];
        foreach ($rows as $row) {
            $settings[$row->setting_key] = $row->setting_value;
        }

        $data = [
            'title' => 'Global Settings | Raptor CRM',
            'active_tab' => 'settings',
            'settings' => $settings,
            'alert_rules' => $this->alertRuleModel->getRules(),
            'success_msg' => $_SESSION['settings_success'] ?? ''
        ];
        
        unset($_SESSION['settings_success']);

        $this->viewWithLayout('settings/index', 'main', $data);
    }

    // Process settings update
    public function save() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Verify CSRF token
            if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
                die('CSRF validation failed.');
            }

            // Keys to support
            $settingKeys = [
                'gemini_api_key',
                'ga4_measurement_id',
                'google_ads_client_id',
                'meta_access_token',
                'linkedin_urn',
                'attendance.shift_start',
                'attendance.shift_end',
                'attendance.grace_minutes',
                'attendance.geofence_enabled',
                'location.tracking_enabled',
                'location.ping_interval_seconds',
                'location.retention_days',
                'retention.location_days',
                'retention.notifications_days',
                'retention.audit_days',
                'retention.security_events_days',
                'retention.login_attempts_days',
                'lead.contact_sla_hours',
                'auth.max_failed_attempts',
                'auth.lockout_minutes',
                'rate.api_limit',
                'rate.api_window_seconds',
                'rate.login_limit',
                'rate.login_window_seconds',
                'alerts.cron_enabled',
                'alerts.web_push_enabled',
                'alerts.vapid_public_key',
                'alerts.vapid_private_key',
                'alerts.email_enabled',
                'alerts.email_from',
                'reports.email_enabled',
                'reports.digest_recipients',

                // Week Off Configurations
                'attendance.week_off_days',
                'attendance.alt_saturday_off',
                'attendance.allow_week_off_override',

                // Leave Policy Configurations
                'leave.approval_workflow',
                'leave.min_notice_days',
                'leave.year_start_month',
                'leave.types_json',

                // Shift Roster Configurations
                'attendance.roster_mode',
                'attendance.roster_publish_lead_days',
                'attendance.shift_templates_json',

                // Email & SMTP Configurations (Payroll Payslips & Alerts)
                'mail.driver',
                'mail.host',
                'mail.port',
                'mail.username',
                'mail.password',
                'mail.encryption',
                'mail.from_address',
                'mail.from_name',
                'mail.auto_send_on_generation'
            ];

            try {
                $this->db->beginTransaction();

                $stmt = $this->db->prepare('INSERT INTO settings (setting_key, setting_value) 
                                            VALUES (:key, :val) 
                                            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');

                foreach ($settingKeys as $key) {
                    if ($key === 'attendance.week_off_days') {
                        $weekOffArr = $_POST['attendance_week_off_days'] ?? [];
                        $value = is_array($weekOffArr) ? implode(',', array_map('trim', $weekOffArr)) : '';
                    } else {
                        $value = isset($_POST[$key]) ? trim($_POST[$key]) : '';
                    }

                    if (in_array($key, ['attendance.geofence_enabled', 'location.tracking_enabled', 'alerts.cron_enabled', 'alerts.web_push_enabled', 'alerts.email_enabled', 'reports.email_enabled', 'attendance.allow_week_off_override', 'mail.auto_send_on_generation'], true)) {
                        $value = isset($_POST[$key]) ? '1' : '0';
                    }

                    $stmt->execute([
                        ':key' => $key,
                        ':val' => $value
                    ]);
                }

                $this->db->commit();
                $_SESSION['settings_success'] = 'Settings saved successfully!';
                
            } catch (Exception $e) {
                if ($this->db->inTransaction()) {
                    $this->db->rollBack();
                }
                die('[ERROR] Failed to save settings: ' . $e->getMessage());
            }

            if (isset($_POST['alert_rules']) && is_array($_POST['alert_rules'])) {
                $this->alertRuleModel->updateRules($_POST['alert_rules']);
            }
        }
        $this->redirect('index.php?route=settings/index');
    }

    // Send diagnostic test email to verify SMTP configuration
    public function test_email() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
                if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'error' => 'CSRF validation failed']);
                    exit;
                }
                die('CSRF validation failed.');
            }

            require_once APPROOT . '/services/EmailService.php';

            $recipient = trim($_POST['test_email_recipient'] ?? '');
            if (empty($recipient)) {
                $recipient = $_SESSION['user_email'] ?? 'admin@raptor.local';
            }

            $testConfig = [
                'driver'       => trim($_POST['mail_driver'] ?? 'smtp'),
                'host'         => trim($_POST['mail_host'] ?? ''),
                'port'         => (int)($_POST['mail_port'] ?? 587),
                'username'     => trim($_POST['mail_username'] ?? ''),
                'password'     => $_POST['mail_password'] ?? '',
                'encryption'   => trim($_POST['mail_encryption'] ?? 'tls'),
                'from_address' => trim($_POST['mail_from_address'] ?? 'accounts@axioraglobalsolutions.com'),
                'from_name'    => trim($_POST['mail_from_name'] ?? 'Axiora Global Solutions'),
                'timeout'      => 10
            ];

            $result = EmailService::testConnection($recipient, $testConfig);

            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                header('Content-Type: application/json');
                echo json_encode($result);
                exit;
            }

            if ($result['success']) {
                $_SESSION['settings_success'] = "Test email sent successfully to {$recipient}!";
            } else {
                $_SESSION['settings_error'] = "Failed to send test email: " . ($result['error'] ?? 'Unknown error');
            }
        }
        $this->redirect('index.php?route=settings/index');
    }

    // List users for Access Control management
    public function accessControl() {
        $userModel = $this->model('User');
        $users = $userModel->getUsers();
        $roles = $userModel->getRoles();

        $data = [
            'title' => 'User Access Control | Raptor CRM',
            'active_tab' => 'settings',
            'users' => $users,
            'roles' => $roles
        ];
        $this->viewWithLayout('settings/access_control', 'main', $data);
    }

    // Configure specific user permission overrides
    public function userAccess($userId = null) {
        $userId = (int)$userId;
        $userModel = $this->model('User');
        $user = $userModel->getUserById($userId);
        if (!$user) {
            $this->redirect('index.php?route=settings/accessControl');
            return;
        }

        $roleModel = $this->model('Role');
        $permissions = $roleModel->getPermissions();
        
        // Fetch current user-level overrides
        $stmt = $this->db->prepare(
            'SELECT permission_id, scope, type FROM user_permission_overrides WHERE user_id = :uid'
        );
        $stmt->execute([':uid' => $userId]);
        $rows = $stmt->fetchAll(PDO::FETCH_OBJ) ?: [];
        
        $overrides = [];
        foreach ($rows as $r) {
            $overrides[(int)$r->permission_id] = [
                'scope' => $r->scope,
                'type' => $r->type
            ];
        }

        // Base role permissions map
        $rolePerms = $roleModel->getRolePermissionsMap($user->role_id);

        // Group permissions by module
        $groupedPerms = [];
        foreach ($permissions as $p) {
            $groupedPerms[$p->module][] = $p;
        }

        $data = [
            'title' => 'Configure Access: ' . htmlspecialchars($user->name) . ' | Raptor CRM',
            'active_tab' => 'settings',
            'user' => $user,
            'grouped_permissions' => $groupedPerms,
            'role_permissions' => $rolePerms,
            'overrides' => $overrides
        ];

        $this->viewWithLayout('settings/user_access', 'main', $data);
    }

    // Save user permission overrides
    public function saveUserAccess($userId = null) {
        $userId = (int)$userId;
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $_POST = filter_input_array(INPUT_POST, FILTER_SANITIZE_SPECIAL_CHARS) ?: [];
            
            // 1. Update user role directly
            $roleId = (int)($_POST['role_id'] ?? 0);
            if ($roleId > 0) {
                $stmt = $this->db->prepare('UPDATE users SET role_id = :rid WHERE user_id = :uid');
                $stmt->execute([':rid' => $roleId, ':uid' => $userId]);
            }

            // 2. Process overrides
            $submitted = $_POST['overrides'] ?? [];

            try {
                $this->db->beginTransaction();

                // Delete current overrides for this user
                $stmtDel = $this->db->prepare('DELETE FROM user_permission_overrides WHERE user_id = :uid');
                $stmtDel->execute([':uid' => $userId]);

                // Insert new ones
                $stmtIns = $this->db->prepare(
                    'INSERT INTO user_permission_overrides (user_id, permission_id, scope, type, created_by) 
                     VALUES (:uid, :pid, :scope, :type, :created_by)'
                );

                foreach ($submitted as $pid => $val) {
                    $pid = (int)$pid;
                    if ($val === 'inherit') continue;

                    $type = ($val === 'revoke') ? 'revoke' : 'grant';
                    $scope = null;
                    if ($type === 'grant') {
                        $scope = str_replace('grant_', '', $val); // 'own', 'team', 'all'
                    }

                    $stmtIns->execute([
                        ':uid' => $userId,
                        ':pid' => $pid,
                        ':scope' => $scope,
                        ':type' => $type,
                        ':created_by' => $_SESSION['user_id']
                    ]);
                }

                $this->db->commit();
                $this->audit("Updated permission overrides for user ID: {$userId}", 'users', $userId);
                $_SESSION['settings_success'] = 'User access permissions updated successfully.';
            } catch (Exception $e) {
                if ($this->db->inTransaction()) {
                    $this->db->rollBack();
                }
                $_SESSION['settings_error'] = 'Failed to update user overrides: ' . $e->getMessage();
            }
        }
        $this->redirect('index.php?route=settings/userAccess/' . $userId);
    }

    // View Audit Logs
    public function auditLogs() {
        // Fetch last 500 audit/activity logs
        $stmt = $this->db->query(
            'SELECT al.*, u.name as user_name, r.role_name
             FROM activity_logs al
             LEFT JOIN users u ON al.user_id = u.user_id
             LEFT JOIN roles r ON u.role_id = r.role_id
             ORDER BY al.created_at DESC
             LIMIT 500'
        );
        $logs = $stmt->fetchAll(PDO::FETCH_OBJ) ?: [];

        $data = [
            'title' => 'System Audit Trail | Raptor CRM',
            'active_tab' => 'settings',
            'logs' => $logs
        ];
        $this->viewWithLayout('settings/audit_logs', 'main', $data);
    }
}
