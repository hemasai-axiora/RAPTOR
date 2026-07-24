<?php
// Raptor CRM Social Media Analytics Controller

class SocialController extends Controller {

    public function __construct() {
        // Enforce authentication
        $this->requireAuth();
        $this->requirePermission('social_media', 'view');
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
        $this->requirePermission('social_media', 'create');
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
                'leads_generated' => (int)($_POST['leads_generated'] ?? 0),
                'lead_details' => trim($_POST['lead_details'] ?? ''),
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
                    $userModel->query("SELECT reporting_manager_id FROM employees WHERE user_id = :uid LIMIT 1");
                    $userModel->bind(':uid', $userId);
                    $mgrId = $userModel->single();

                    $recipients = [];
                    if ($mgrId && !empty($mgrId->reporting_manager_id)) {
                        $recipients[] = $mgrId->reporting_manager_id;
                    } else {
                        // Notify all managers/admins
                        $userModel->query("SELECT user_id FROM users u JOIN roles r ON u.role_id = r.role_id WHERE r.role_name IN ('manager', 'admin') AND u.status = 'active'");
                        foreach ($userModel->resultSet() as $mgr) {
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

        // Get scoped history for updates table
        $scopeIds = $this->visibleUserIds();
        $history = $analyticsModel->getCompleteHistory($scopeIds);

        $this->viewWithLayout('social/update', 'main', [
            'title' => 'Log Social Analytics',
            'active_tab' => 'social_update',
            'platforms' => $platforms,
            'assignedAccounts' => $assignedAccounts,
            'groupedAccounts' => $groupedAccounts,
            'history' => $history
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
        $this->requirePermission('social_media', 'manage');

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

    // 4. Social Accounts Directory & Credentials Panel
    public function admin() {
        $this->requireAuth();
        $isEmployee = in_array($_SESSION['user_role'] ?? '', ['employee', 'sales_person'], true);

        $platformModel = $this->model('Platform');
        $socialModel = $this->model('SocialAccount');
        $userModel = $this->model('User');

        $platforms = $platformModel->getPlatforms();
        
        if ($isEmployee) {
            $accounts = $socialModel->getAssignedAccountsForUser($_SESSION['user_id']);
        } else {
            $accounts = $socialModel->getAccounts();
        }
        
        // Get all active employees for assignments dropdown
        $employees = $userModel->getUsers();

        // Get clients list
        $clientModel = $this->model('Client');
        $clients = $clientModel->getClients();

        $this->viewWithLayout('social/admin', 'main', [
            'title' => 'Accounts Directory | Raptor CRM',
            'active_tab' => 'social_admin',
            'platforms' => $platforms,
            'accounts' => $accounts,
            'employees' => $employees,
            'clients' => $clients,
            'is_employee' => $isEmployee
        ]);
    }

    // POST action: Add Platform
    public function addPlatform() {
        if (!in_array($_SESSION['user_role'], ['admin', 'ceo', 'manager', 'analyst'], true)) $this->jsonError('Unauthorized.', 403);
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

    // POST action: Remove Platform
    public function removePlatform() {
        if (!in_array($_SESSION['user_role'], ['admin', 'ceo', 'manager', 'analyst'], true)) $this->jsonError('Unauthorized.', 403);
        $platformModel = $this->model('Platform');
        $id = (int)($_POST['platform_id'] ?? 0);
        $platformModel->removePlatform($id);
        $_SESSION['flash_success'] = "Platform removed successfully.";
        $this->redirect('index.php?route=social/admin');
    }

    // POST action: Add Account with Credentials
    public function addAccount() {
        if (!in_array($_SESSION['user_role'], ['admin', 'ceo', 'manager', 'analyst'], true)) $this->jsonError('Unauthorized.', 403);
        $socialModel = $this->model('SocialAccount');
        $platformModel = $this->model('Platform');

        $client_id = (int)$_POST['client_id'];
        $platform_id = (int)$_POST['platform_id'];
        $profile_name = trim($_POST['profile_name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $account_password = trim($_POST['account_password'] ?? '');
        $account_notes = trim($_POST['account_notes'] ?? '');
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
            'username' => $username,
            'account_password' => $account_password,
            'account_notes' => $account_notes,
            'profile_url' => $profile_url
        ];

        $socialModel->addAccount($data);
        $_SESSION['flash_success'] = "Social account & credentials stored successfully.";
        $this->redirect('index.php?route=social/admin');
    }

    // POST action: Save Manager Remarks / Review Comment
    public function saveRemarks() {
        if (!in_array($_SESSION['user_role'], ['admin', 'ceo', 'manager', 'analyst'], true)) {
            $_SESSION['flash_error'] = "Unauthorized access.";
            $this->redirect('index.php?route=social/admin');
            return;
        }

        $account_id = (int)($_POST['account_id'] ?? 0);
        $manager_remarks = trim($_POST['manager_remarks'] ?? '');

        if (!$account_id) {
            $_SESSION['flash_error'] = "Invalid social account ID.";
            $this->redirect('index.php?route=social/admin');
            return;
        }

        $socialModel = $this->model('SocialAccount');
        if ($socialModel->saveManagerRemarks($account_id, $manager_remarks)) {
            $_SESSION['flash_success'] = "Manager review comment updated successfully.";
        } else {
            $_SESSION['flash_error'] = "Failed to update manager review comment.";
        }
        $this->redirect('index.php?route=social/admin');
    }

    // POST action: Assign Account
    public function assignAccount() {
        if (!in_array($_SESSION['user_role'], ['admin', 'ceo', 'manager', 'analyst'], true)) $this->jsonError('Unauthorized.', 403);
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

    // POST action: Unassign Account
    public function unassignAccount() {
        if (!in_array($_SESSION['user_role'], ['admin', 'ceo', 'manager', 'analyst'], true)) $this->jsonError('Unauthorized.', 403);
        $socialModel = $this->model('SocialAccount');

        $account_id = (int)$_POST['account_id'];
        $user_id = (int)$_POST['user_id'];

        $socialModel->unassignEmployee($account_id, $user_id);
        $_SESSION['flash_success'] = "Account unassigned successfully.";
        $this->redirect('index.php?route=social/admin');
    }

    // POST action: Archive / Move Account
    public function archiveAccount() {
        if (!in_array($_SESSION['user_role'], ['admin', 'ceo', 'manager', 'analyst'], true)) $this->jsonError('Unauthorized.', 403);
        $socialModel = $this->model('SocialAccount');
        $id = (int)($_POST['account_id'] ?? 0);
        $socialModel->archiveAccount($id);
        $_SESSION['flash_success'] = "Account archived (disconnected) successfully.";
        $this->redirect('index.php?route=social/admin');
    }

    // 5. Dedicated Lead Generation Hub Action
    public function leads() {
        $this->requireAuth();
        $userId = $_SESSION['user_id'];

        $socialModel = $this->model('SocialAccount');
        $platformModel = $this->model('Platform');
        $leadModel = $this->model('Lead');
        $analyticsModel = $this->model('AnalyticsEntry');

        $platforms = $platformModel->getPlatforms();
        $assignedAccounts = $socialModel->getAssignedAccountsForUser($userId);

        // Fetch leads
        $scopeIds = $this->visibleUserIds();
        $leads = $leadModel->getLeads([], $scopeIds);

        // Fetch database link click logs
        $clickLogs = $analyticsModel->getLinkClicks(null, 100);

        $this->viewWithLayout('social/leads', 'main', [
            'title' => 'Lead Generation Hub | Raptor CRM',
            'active_tab' => 'social_leads',
            'platforms' => $platforms,
            'assignedAccounts' => $assignedAccounts,
            'leads' => $leads,
            'clickLogs' => $clickLogs
        ]);
    }

    // POST: Manual Lead Entry Submission from Marketing
    public function addLeadFromMarketing() {
        $this->requireAuth();
        $userId = $_SESSION['user_id'];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $_POST = filter_input_array(INPUT_POST, FILTER_SANITIZE_SPECIAL_CHARS) ?: [];

            $fullName = trim($_POST['name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $company = trim($_POST['company_name'] ?? '');
            $platformId = (int)($_POST['platform_id'] ?? 0);
            $leadValue = (float)($_POST['lead_value'] ?? 0.00);
            $notes = trim($_POST['notes'] ?? '');

            if (empty($fullName) || empty($email) || empty($phone)) {
                $_SESSION['flash_error'] = "Full Name, Email Address, and Phone Number are required.";
                $this->redirect('index.php?route=social/leads');
                return;
            }

            // Split name into first and last
            $parts = explode(' ', $fullName, 2);
            $firstName = $parts[0];
            $lastName = $parts[1] ?? '';

            // Determine lead source platform name
            $platformModel = $this->model('Platform');
            $plat = $platformModel->getPlatformById($platformId);
            $sourceName = $plat ? 'Social Media (' . $plat->name . ')' : 'Social Media Organic';

            $leadData = [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $email,
                'phone' => $phone,
                'company_name' => $company ?: 'Independent Lead',
                'status' => 'new',
                'lead_quality' => 'warm',
                'lead_value' => $leadValue,
                'lead_source' => $sourceName,
                'assigned_to_user_id' => $userId,
                'changed_by_user_id' => $userId
            ];

            $leadModel = $this->model('Lead');
            $leadId = $leadModel->addLead($leadData);

            if ($leadId) {
                // Also record in analytics history
                $analyticsModel = $this->model('AnalyticsEntry');
                $analyticsModel->logEntry([
                    'platform_id' => $platformId ?: 1,
                    'account_id' => 1,
                    'post_id' => null,
                    'likes' => 0,
                    'comments' => 0,
                    'shares' => 0,
                    'views' => 1,
                    'leads_generated' => 1,
                    'lead_details' => "$fullName ($email, $phone)",
                    'custom_notes' => "Marketing Lead Generated: $notes",
                    'updated_by' => $userId
                ]);

                $_SESSION['flash_success'] = "Lead '$fullName' created and registered successfully!";
            } else {
                $_SESSION['flash_error'] = "Failed to create lead entry.";
            }

            $this->redirect('index.php?route=social/leads');
        }
    }

    // POST: Bulk CSV Upload for Generated Leads
    public function uploadLeadsCsv() {
        $this->requireAuth();
        $userId = $_SESSION['user_id'];

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
            $file = $_FILES['csv_file'];

            if ($file['error'] !== UPLOAD_ERR_OK || empty($file['tmp_name'])) {
                $_SESSION['flash_error'] = "Please select a valid CSV file for upload.";
                $this->redirect('index.php?route=social/leads');
                return;
            }

            $handle = fopen($file['tmp_name'], 'r');
            if (!$handle) {
                $_SESSION['flash_error'] = "Could not open uploaded CSV file.";
                $this->redirect('index.php?route=social/leads');
                return;
            }

            $leadModel = $this->model('Lead');
            $importedCount = 0;
            $rowNum = 0;

            // Read header row
            $header = fgetcsv($handle);

            while (($row = fgetcsv($handle)) !== false) {
                $rowNum++;
                if (empty(array_filter($row))) continue;

                $name = trim($row[0] ?? '');
                $email = trim($row[1] ?? '');
                $phone = trim($row[2] ?? '');
                $company = trim($row[3] ?? '');
                $platform = trim($row[4] ?? 'Social Media');
                $notes = trim($row[5] ?? '');

                if (empty($name) && empty($email) && empty($phone)) continue;

                $parts = explode(' ', $name, 2);
                $firstName = $parts[0] ?? 'Lead';
                $lastName = $parts[1] ?? ("#" . $rowNum);

                $leadData = [
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'email' => $email ?: null,
                    'phone' => $phone ?: null,
                    'company_name' => $company ?: 'CSV Import',
                    'status' => 'new',
                    'lead_quality' => 'warm',
                    'lead_value' => 0.00,
                    'lead_source' => 'CSV Import (' . ($platform ?: 'Social Media') . ')',
                    'assigned_to_user_id' => $userId,
                    'changed_by_user_id' => $userId
                ];

                if ($leadModel->addLead($leadData)) {
                    $importedCount++;
                }
            }

            fclose($handle);

            if ($importedCount > 0) {
                $_SESSION['flash_success'] = "Successfully imported $importedCount leads from CSV file!";
            } else {
                $_SESSION['flash_error'] = "No valid lead records found in CSV file.";
            }

            $this->redirect('index.php?route=social/leads');
        }
    }

    // GET: Sample CSV Template Download
    public function downloadSampleLeadsCsv() {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=sample_social_leads_import.csv');

        $output = fopen('php://output', 'w');
        fputcsv($output, ['Full Name', 'Email Address', 'Phone Number', 'Company Name', 'Platform', 'Notes / Inquiry']);
        fputcsv($output, ['John Doe', 'john.doe@example.com', '+1 555-0192', 'Acme Marketing', 'Facebook', 'Interested in social media growth package']);
        fputcsv($output, ['Sarah Smith', 'sarah.s@techcorp.io', '+1 555-0198', 'TechCorp Inc', 'LinkedIn', 'Requested demo for digital leads automation']);
        fputcsv($output, ['Alex Rivera', 'alex@riveramedia.com', '+1 555-0244', 'Rivera Media', 'Instagram', 'Inquired about monthly ad budget management']);
        fclose($output);
        exit;
    }

    // Track link click and redirect visitor
    public function click() {
        $accId = (int)($_GET['acc'] ?? 0);
        $targetUrl = trim($_GET['url'] ?? '');
        $shortCode = trim($_GET['code'] ?? '');

        $analyticsModel = $this->model('AnalyticsEntry');
        
        if (empty($targetUrl)) {
            $targetUrl = "index.php?route=social/capture&acc=" . $accId;
        }

        // Log click event into database (IP, user agent, referrer, timestamp)
        $analyticsModel->logLinkClick($accId, $shortCode ?: "ACC-$accId", $targetUrl);

        // Also increment view/click count for account
        if ($accId) {
            $socialModel = $this->model('SocialAccount');
            $acc = $socialModel->getAccountById($accId);
            if ($acc) {
                $analyticsModel->logEntry([
                    'platform_id' => $acc->platform_id,
                    'account_id' => $accId,
                    'post_id' => null,
                    'likes' => 0,
                    'comments' => 0,
                    'shares' => 0,
                    'views' => 1,
                    'clicks' => 1,
                    'custom_notes' => "Link clicked by visitor",
                    'updated_by' => !empty($_SESSION['user_id']) ? $_SESSION['user_id'] : 1
                ]);
            }
        }

        // Redirect visitor to target URL
        header("Location: " . $targetUrl);
        exit;
    }

    // Public Customer Lead Capture Form (No Auth Required)
    public function capture() {
        $accId = (int)($_GET['acc'] ?? 0);

        $socialModel = $this->model('SocialAccount');
        $account = $accId ? $socialModel->getAccountById($accId) : null;

        // Log link visit event in database
        $analyticsModel = $this->model('AnalyticsEntry');
        $analyticsModel->logLinkClick($accId, "CAP-$accId", "index.php?route=social/capture&acc=" . $accId);

        $this->view('social/capture', [
            'title' => 'Get in Touch | Raptor CRM',
            'account' => $account
        ]);
    }

    // Process Public Lead Form Submission
    public function submitPublicLead() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $_POST = filter_input_array(INPUT_POST, FILTER_SANITIZE_SPECIAL_CHARS) ?: [];

            $fullName = trim($_POST['name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $company = trim($_POST['company_name'] ?? '');
            $notes = trim($_POST['notes'] ?? '');
            $accId = (int)($_POST['account_id'] ?? 0);

            if (empty($fullName) || empty($email) || empty($phone)) {
                $_SESSION['flash_error'] = "Full Name, Email, and Phone Number are required.";
                $this->redirect("index.php?route=social/capture&acc=" . $accId);
                return;
            }

            $parts = explode(' ', $fullName, 2);
            $firstName = $parts[0];
            $lastName = $parts[1] ?? '';

            // Find account handler/assignee
            $socialModel = $this->model('SocialAccount');
            $account = $accId ? $socialModel->getAccountById($accId) : null;
            $assigneeId = !empty($account->assigned_users) ? $account->assigned_users[0]->user_id : 1;
            $platformName = $account ? $account->platform_name : 'Social Media Link';

            $leadData = [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $email,
                'phone' => $phone,
                'company_name' => $company ?: 'Public Link Visitor',
                'status' => 'new',
                'lead_quality' => 'hot',
                'lead_value' => 0.00,
                'lead_source' => 'Public Link (' . $platformName . ')',
                'assigned_to_user_id' => $assigneeId,
                'changed_by_user_id' => $assigneeId
            ];

            $leadModel = $this->model('Lead');
            $leadId = $leadModel->addLead($leadData);

            if ($leadId) {
                // Log entry in analytics history
                $analyticsModel = $this->model('AnalyticsEntry');
                $analyticsModel->logEntry([
                    'platform_id' => $account ? $account->platform_id : 1,
                    'account_id' => $accId ?: 1,
                    'post_id' => null,
                    'likes' => 0,
                    'comments' => 0,
                    'shares' => 0,
                    'views' => 1,
                    'leads_generated' => 1,
                    'lead_details' => "$fullName ($email, $phone)",
                    'custom_notes' => "Public Link Click Lead: $notes",
                    'updated_by' => $assigneeId
                ]);

                // Also log link click event in database
                $analyticsModel->logLinkClick($accId, "LEAD-SUBMIT", "Public Lead Form: $fullName");

                $this->view('social/capture_success', [
                    'title' => 'Inquiry Submitted | Thank You',
                    'name' => $fullName
                ]);
                return;
            }
        }
        $this->redirect('index.php?route=auth/login');
    }
}
