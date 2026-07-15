<?php
// Raptor CRM Social Media Analytics Controller

class SocialController extends Controller {

    public function __construct() {
        // Enforce authentication
        $this->requireAuth();
    }

    // Default route: directs to appropriate view based on role
    public function index() {
        $role = $_SESSION['user_role'];
        if ($role === 'admin') {
            $this->redirect('index.php?route=social/admin');
        } elseif ($role === 'manager' || $role === 'team_leader') {
            $this->redirect('index.php?route=social/manager');
        } else {
            $this->redirect('index.php?route=social/update');
        }
    }

    // 1. Employee Update Page & Submission
    public function update() {
        $socialModel = $this->model('SocialAccount');
        $analyticsModel = $this->model('AnalyticsEntry');
        $postModel = $this->model('Post');
        $platformModel = $this->model('Platform');

        $userId = $_SESSION['user_id'];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $_POST = filter_input_array(INPUT_POST, FILTER_SANITIZE_SPECIAL_CHARS) ?: [];

            $data = [
                'platform_id' => (int)($_POST['platform_id'] ?? 0),
                'account_id' => (int)($_POST['account_id'] ?? 0),
                'post_id' => !empty($_POST['post_id']) ? (int)$_POST['post_id'] : null,
                'likes' => (int)($_POST['likes'] ?? 0),
                'comments' => (int)($_POST['comments'] ?? 0),
                'shares' => (int)($_POST['shares'] ?? 0),
                'views' => (int)($_POST['views'] ?? 1),
                'reach' => (int)($_POST['reach'] ?? 0),
                'impressions' => (int)($_POST['impressions'] ?? 0),
                'clicks' => (int)($_POST['clicks'] ?? 0),
                'followers_gained' => (int)($_POST['followers_gained'] ?? 0),
                'custom_notes' => trim($_POST['custom_notes'] ?? ''),
                'updated_by' => $userId
            ];

            if (empty($data['platform_id']) || empty($data['account_id'])) {
                $_SESSION['flash_error'] = "Platform and Social Account are required.";
                $this->redirect('index.php?route=social/update');
                return;
            }

            // Verify account and platform pair
            $account = $socialModel->getAccountById($data['account_id']);
            if (!$account || (int)$account->platform_id !== $data['platform_id']) {
                $_SESSION['flash_error'] = "Invalid Platform and Social Account selection.";
                $this->redirect('index.php?route=social/update');
                return;
            }

            // Verify post matches account if post_id is provided
            if ($data['post_id'] !== null) {
                $post = $postModel->getPostById($data['post_id']);
                if (!$post || (int)$post->account_id !== $data['account_id']) {
                    $_SESSION['flash_error'] = "Target Post does not belong to the selected Social Account.";
                    $this->redirect('index.php?route=social/update');
                    return;
                }
            }

            try {
                if ($analyticsModel->logEntry($data)) {
                    // Create Notification for Manager
                    $notifyModel = $this->model('Notification');
                    $userModel = $this->model('User');
                    $account = $socialModel->getAccountById($data['account_id']);
                    
                    // Find reporting manager for this employee
                    $this->db->query("SELECT reporting_manager_id FROM employees WHERE user_id = :uid LIMIT 1");
                    $this->db->bind(':uid', $userId);
                    $mgrId = $this->db->single();

                    $recipients = [];
                    if ($mgrId && !empty($mgrId->reporting_manager_id)) {
                        $recipients[] = $mgrId->reporting_manager_id;
                    } else {
                        // Notify all managers/admins
                        $this->db->query("SELECT user_id FROM users u JOIN roles r ON u.role_id = r.role_id WHERE r.role_name IN ('manager', 'admin') AND u.status = 'active'");
                        foreach ($this->db->resultSet() as $mgr) {
                            $recipients[] = $mgr->user_id;
                        }
                    }

                    foreach (array_unique($recipients) as $rid) {
                        $notifyModel->query("INSERT INTO notifications (user_id, title, message, type, severity) 
                                             VALUES (:uid, 'Analytics Updated', :msg, 'social', 'info')");
                        $notifyModel->bind(':uid', $rid);
                        $notifyModel->bind(':msg', $_SESSION['user_name'] . " updated analytics for " . $account->platform_name . " (" . $account->profile_name . ").");
                        $notifyModel->execute();
                    }

                    $_SESSION['flash_success'] = "Social media analytics logged successfully.";
                } else {
                    $_SESSION['flash_error'] = "Failed to log social media analytics.";
                }
            } catch (Exception $e) {
                $_SESSION['flash_error'] = "Error: " . $e->getMessage();
            }

            $this->redirect('index.php?route=social/update');
        }

        // Get assigned accounts for this employee
        $assignedAccounts = $socialModel->getAssignedAccountsForUser($userId);
        
        // Group accounts by platform name for easy selection
        $groupedAccounts = [];
        foreach ($assignedAccounts as $acc) {
            $groupedAccounts[$acc->platform_id][] = $acc;
        }

        // Get active platforms
        $platforms = $platformModel->getPlatforms();

        $this->viewWithLayout('social/update', 'main', [
            'title' => 'Log Social Analytics',
            'active_tab' => 'social_update',
            'platforms' => $platforms,
            'assignedAccounts' => $assignedAccounts,
            'groupedAccounts' => $groupedAccounts
        ]);
    }

    // Get posts by Account ID as JSON (AJAX Helper)
    public function getPostsByAccount($accountId) {
        $postModel = $this->model('Post');
        $posts = $postModel->getPostsByAccount((int)$accountId);
        $this->jsonOk($posts);
    }

    // 2. Complete Timeline History
    public function history() {
        $analyticsModel = $this->model('AnalyticsEntry');
        
        // Scope timeline based on hierarchy permissions
        $scopeIds = $this->visibleUserIds();
        $history = $analyticsModel->getCompleteHistory($scopeIds);

        $this->viewWithLayout('social/history', 'main', [
            'title' => 'Analytics History Timeline',
            'active_tab' => 'social_history',
            'history' => $history
        ]);
    }

    // 3. Manager Performance Dashboard
    public function manager() {
        // Enforce Manager role boundary
        if (!Policy::isManager() && !Policy::isAdmin()) {
            $this->redirect('index.php?route=social/update');
        }

        $analyticsModel = $this->model('AnalyticsEntry');
        
        // Scoped stats for visible employees
        $scopeIds = $this->visibleUserIds();
        $metrics = $analyticsModel->getDashboardMetrics($scopeIds);
        $productivity = $analyticsModel->getEmployeeProductivity();
        $history = $analyticsModel->getCompleteHistory($scopeIds);

        $this->viewWithLayout('social/manager', 'main', [
            'title' => 'Manager Analytics Panel',
            'active_tab' => 'social_manager',
            'metrics' => $metrics,
            'productivity' => $productivity,
            'history' => $history
        ]);
    }

    // 4. Admin Settings Panel
    public function admin() {
        // Enforce Admin role boundary
        if (!Policy::isAdmin()) {
            $this->redirect('index.php?route=social/update');
        }

        $platformModel = $this->model('Platform');
        $socialModel = $this->model('SocialAccount');
        $userModel = $this->model('User');

        $platforms = $platformModel->getPlatforms();
        $accounts = $socialModel->getAccounts();
        
        // Get employees/managers for assignments dropdown
        $users = $userModel->getUsers();
        $employees = [];
        foreach ($users as $u) {
            if ($u->role_name === 'employee' || $u->role_name === 'sales_person') {
                $employees[] = $u;
            }
        }

        // Get clients list
        $clientModel = $this->model('Client');
        $clients = $clientModel->getClients();

        $this->viewWithLayout('social/admin', 'main', [
            'title' => 'Admin Social Configuration',
            'active_tab' => 'social_admin',
            'platforms' => $platforms,
            'accounts' => $accounts,
            'employees' => $employees,
            'clients' => $clients
        ]);
    }

    // Admin POST action: Add Platform
    public function addPlatform() {
        if (!Policy::isAdmin()) $this->jsonError('Unauthorized.', 403);
        $platformModel = $this->model('Platform');
        
        $name = trim($_POST['name'] ?? '');
        $icon = trim($_POST['icon'] ?? 'fa-solid fa-share-nodes');

        if (empty($name)) {
            $_SESSION['flash_error'] = "Platform name is required.";
            $this->redirect('index.php?route=social/admin');
        }

        $platformModel->addPlatform(['name' => $name, 'icon' => $icon]);
        $_SESSION['flash_success'] = "Platform added successfully.";
        $this->redirect('index.php?route=social/admin');
    }

    // Admin POST action: Remove Platform
    public function removePlatform() {
        if (!Policy::isAdmin()) $this->jsonError('Unauthorized.', 403);
        $platformModel = $this->model('Platform');
        $id = (int)($_POST['platform_id'] ?? 0);
        $platformModel->removePlatform($id);
        $_SESSION['flash_success'] = "Platform removed successfully.";
        $this->redirect('index.php?route=social/admin');
    }

    // Admin POST action: Add Account
    public function addAccount() {
        if (!Policy::isAdmin()) $this->jsonError('Unauthorized.', 403);
        $socialModel = $this->model('SocialAccount');
        $platformModel = $this->model('Platform');

        $client_id = (int)$_POST['client_id'];
        $platform_id = (int)$_POST['platform_id'];
        $profile_name = trim($_POST['profile_name'] ?? '');
        $profile_url = trim($_POST['profile_url'] ?? '');

        if (empty($profile_name)) {
            $_SESSION['flash_error'] = "Account name is required.";
            $this->redirect('index.php?route=social/admin');
        }

        $plat = $platformModel->getPlatformById($platform_id);
        
        $data = [
            'client_id' => $client_id,
            'platform_id' => $platform_id,
            'platform' => $plat ? strtolower($plat->name) : 'unknown',
            'profile_name' => $profile_name,
            'profile_url' => $profile_url
        ];

        $socialModel->addAccount($data);
        $_SESSION['flash_success'] = "Social account created successfully.";
        $this->redirect('index.php?route=social/admin');
    }

    // Admin POST action: Assign Account
    public function assignAccount() {
        if (!Policy::isAdmin()) $this->jsonError('Unauthorized.', 403);
        $socialModel = $this->model('SocialAccount');

        $account_id = (int)$_POST['account_id'];
        $user_id = (int)$_POST['user_id'];
        $is_shared = isset($_POST['is_shared']) ? 1 : 0;

        if ($socialModel->assignEmployee($account_id, $user_id, $_SESSION['user_id'], $is_shared)) {
            
            // Notify Employee
            $notifyModel = $this->model('Notification');
            $account = $socialModel->getAccountById($account_id);
            $notifyModel->query("INSERT INTO notifications (user_id, title, message, type, severity) 
                                 VALUES (:uid, 'New Social Account Assigned', :msg, 'social', 'info')");
            $notifyModel->bind(':uid', $user_id);
            $notifyModel->bind(':msg', "You have been assigned to manage " . $account->platform_name . " account: " . $account->profile_name);
            $notifyModel->execute();

            $_SESSION['flash_success'] = "Account assigned successfully.";
        } else {
            $_SESSION['flash_error'] = "Failed to assign. Account is already assigned to another employee and shared access is disabled.";
        }
        $this->redirect('index.php?route=social/admin');
    }

    // Admin POST action: Unassign Account
    public function unassignAccount() {
        if (!Policy::isAdmin()) $this->jsonError('Unauthorized.', 403);
        $socialModel = $this->model('SocialAccount');

        $account_id = (int)$_POST['account_id'];
        $user_id = (int)$_POST['user_id'];

        $socialModel->unassignEmployee($account_id, $user_id);
        $_SESSION['flash_success'] = "Account unassigned successfully.";
        $this->redirect('index.php?route=social/admin');
    }

    // Admin POST action: Archive / Move Account
    public function archiveAccount() {
        if (!Policy::isAdmin()) $this->jsonError('Unauthorized.', 403);
        $socialModel = $this->model('SocialAccount');
        $id = (int)($_POST['account_id'] ?? 0);
        $socialModel->archiveAccount($id);
        $_SESSION['flash_success'] = "Account archived (disconnected) successfully.";
        $this->redirect('index.php?route=social/admin');
    }
}
