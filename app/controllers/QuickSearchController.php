<?php
/**
 * QuickSearchController — Real-time search provider for Raptor Global Command Palette (Ctrl+K).
 * Performs lightning-fast fuzzy querying across navigation links, employees, leads, and campaigns.
 *
 * Security & Defense-in-Depth (Raptor v2.2 - Sprint 1 / AUD-003):
 * - Enforces session authentication on all requests.
 * - Restricts navigation and quick actions to user's permitted modules and roles.
 * - Restricts employee directory search to team-subtree visible user IDs.
 * - Restricts lead search to assigned/created leads within team-subtree visible user IDs.
 * - Restricts marketing campaigns to users with campaign permissions.
 */

declare(strict_types=1);

class QuickSearchController extends Controller {

    public function __construct() {
        // Enforce session authentication for all search queries
        $this->requireAuth();
    }

    /**
     * Handle search query endpoint: index.php?route=quicksearch/query&q=term
     */
    public function query() {
        $q = trim((string)($_GET['q'] ?? ''));
        
        if (mb_strlen($q) < 2) {
            $this->json([
                'success' => true,
                'query'   => $q,
                'results' => $this->getDefaultNavigationSuggestions()
            ]);
            return;
        }

        $results = [];
        $lowerQ = mb_strtolower($q);

        // 1. Static Navigation & Quick Actions Matching (Permission-filtered)
        $navItems = $this->getAllNavigationItems();
        foreach ($navItems as $item) {
            if (
                strpos(mb_strtolower($item['title']), $lowerQ) !== false ||
                strpos(mb_strtolower($item['subtitle'] ?? ''), $lowerQ) !== false ||
                strpos(mb_strtolower($item['keywords'] ?? ''), $lowerQ) !== false
            ) {
                $results[] = [
                    'category' => 'Navigation',
                    'title'    => $item['title'],
                    'subtitle' => $item['subtitle'] ?? '',
                    'url'      => $item['url'],
                    'icon'     => $item['icon'] ?? 'fa-compass',
                    'badge'    => $item['badge'] ?? null
                ];
            }
        }

        // 2. Query Database for Entities (Employees, Leads, Campaigns) with Team-Subtree Scoping
        try {
            $db = Database::getInstance()->getConnection();
            $param = '%' . $q . '%';
            $visibleIds = $this->visibleUserIds();

            // A. Employees / Team Members (AUD-003 Subtree Filter)
            $canViewStaff = Policy::isAdmin() || Policy::isHr() || Policy::isSeniorStaff()
                || PermissionService::can('users', 'view') || PermissionService::can('employees', 'view') || PermissionService::can('hrms', 'view');

            if ($canViewStaff && ($visibleIds === null || !empty($visibleIds))) {
                try {
                    $scopeClause = '';
                    $scopeParams = [];

                    if ($visibleIds !== null) {
                        $placeholders = [];
                        foreach ($visibleIds as $idx => $vId) {
                            $ph = ':vis_emp_' . $idx;
                            $placeholders[] = $ph;
                            $scopeParams[$ph] = (int)$vId;
                        }
                        $scopeClause = ' AND u.user_id IN (' . implode(',', $placeholders) . ')';
                    }

                    $sqlEmp = "
                        SELECT u.user_id, u.name, u.email, 
                               COALESCE(e.employee_id, '') AS emp_id,
                               COALESCE(e.designation, r.role_name) AS subtitle
                        FROM users u
                        LEFT JOIN roles r ON u.role_id = r.role_id
                        LEFT JOIN employees e ON u.user_id = e.user_id
                        WHERE (u.name LIKE :p1 OR u.email LIKE :p2 OR e.employee_id LIKE :p3 OR e.designation LIKE :p4)
                          AND u.status = 'active'
                          {$scopeClause}
                        ORDER BY u.name ASC
                        LIMIT 5
                    ";
                    $stmtEmp = $db->prepare($sqlEmp);
                    $stmtEmp->execute(array_merge([
                        ':p1' => $param,
                        ':p2' => $param,
                        ':p3' => $param,
                        ':p4' => $param
                    ], $scopeParams));
                    $employees = $stmtEmp->fetchAll(PDO::FETCH_ASSOC) ?: [];

                    foreach ($employees as $emp) {
                        $sub = trim(($emp['emp_id'] ? $emp['emp_id'] . ' • ' : '') . ($emp['subtitle'] ?: $emp['email']));
                        $results[] = [
                            'category' => 'Employees & Staff',
                            'title'    => (string)$emp['name'],
                            'subtitle' => $sub,
                            'url'      => 'index.php?route=users/index',
                            'icon'     => 'fa-id-badge',
                            'badge'    => 'HR'
                        ];
                    }
                } catch (Throwable $eEmp) {
                    // Ignore transient db errors
                }
            }

            // B. Leads & Opportunities (AUD-003 Scoping + Defense-in-depth)
            $canViewLeads = Policy::isAdmin() || PermissionService::can('leads', 'view') || PermissionService::can('crm_leads', 'view');

            if ($canViewLeads && ($visibleIds === null || !empty($visibleIds))) {
                try {
                    $scopeClause = '';
                    $scopeParams = [];

                    if ($visibleIds !== null) {
                        $placeholders = [];
                        foreach ($visibleIds as $idx => $vId) {
                            $ph = ':vis_lead_' . $idx;
                            $placeholders[] = $ph;
                            $scopeParams[$ph] = (int)$vId;
                        }
                        $scopeClause = ' AND (assigned_to_user_id IN (' . implode(',', $placeholders) . ') OR created_by_user_id IN (' . implode(',', $placeholders) . '))';
                    }

                    $sqlLead = "
                        SELECT lead_id, 
                               TRIM(CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, ''))) AS full_name,
                               COALESCE(company_name, '') AS company,
                               COALESCE(email, '') AS email,
                               COALESCE(phone, '') AS phone,
                               COALESCE(status, 'New') AS lead_status,
                               assigned_to_user_id, created_by_user_id
                        FROM leads
                        WHERE (first_name LIKE :p1 OR last_name LIKE :p2 OR email LIKE :p3 OR phone LIKE :p4 OR company_name LIKE :p5)
                          {$scopeClause}
                        ORDER BY lead_id DESC
                        LIMIT 5
                    ";
                    $stmtLead = $db->prepare($sqlLead);
                    $stmtLead->execute(array_merge([
                        ':p1' => $param,
                        ':p2' => $param,
                        ':p3' => $param,
                        ':p4' => $param,
                        ':p5' => $param
                    ], $scopeParams));
                    $leads = $stmtLead->fetchAll(PDO::FETCH_ASSOC) ?: [];

                    foreach ($leads as $lead) {
                        // Secondary validation for defense-in-depth
                        if (!Policy::isAdmin() && !PermissionService::can('leads', 'view', (object)$lead)) {
                            continue;
                        }
                        $displayName = trim($lead['full_name']) ?: ($lead['company'] ?: $lead['email']);
                        $sub = trim(($lead['company'] ? $lead['company'] . ' • ' : '') . ($lead['email'] ?: $lead['phone']));
                        $results[] = [
                            'category' => 'Leads & Accounts',
                            'title'    => $displayName,
                            'subtitle' => $sub,
                            'url'      => 'index.php?route=leads/index',
                            'icon'     => 'fa-crosshairs',
                            'badge'    => ucfirst($lead['lead_status'] ?: 'Lead')
                        ];
                    }
                } catch (Throwable $eLead) {
                    // Table might not exist in standalone mocks
                }
            }

            // C. Email Campaigns & Broadcasts (Permission-scoped)
            $canViewCampaigns = Policy::isAdmin() || PermissionService::can('campaigns', 'view');

            if ($canViewCampaigns) {
                try {
                    $stmtCamp = $db->prepare("
                        SELECT campaign_id, name, COALESCE(subject, '') AS subject, COALESCE(status, 'draft') AS camp_status
                        FROM marketing_campaigns
                        WHERE (name LIKE :p1 OR subject LIKE :p2)
                        ORDER BY campaign_id DESC
                        LIMIT 5
                    ");
                    $stmtCamp->execute([':p1' => $param, ':p2' => $param]);
                    $campaigns = $stmtCamp->fetchAll(PDO::FETCH_ASSOC) ?: [];

                    foreach ($campaigns as $camp) {
                        $results[] = [
                            'category' => 'Campaigns & Marketing',
                            'title'    => (string)$camp['name'],
                            'subtitle' => (string)($camp['subject'] ?: 'Subject not set'),
                            'url'      => 'index.php?route=emailcampaigns/index',
                            'icon'     => 'fa-paper-plane',
                            'badge'    => strtoupper($camp['camp_status'])
                        ];
                    }
                } catch (Throwable $eCamp) {
                    // Ignore transient db errors
                }
            }

        } catch (Throwable $e) {
            // Safe fallback
        }

        $this->json([
            'success' => true,
            'query'   => $q,
            'total'   => count($results),
            'results' => array_slice($results, 0, 15)
        ]);
    }

    /**
     * Quick suggested items when palette is first opened without typing.
     */
    private function getDefaultNavigationSuggestions(): array {
        $suggestions = [
            [
                'category'   => 'Quick Actions',
                'title'      => 'New Email Campaign',
                'subtitle'   => 'Compose & launch deliverability-guarded broadcast',
                'url'        => 'index.php?route=emailcampaigns/create',
                'icon'       => 'fa-plus-circle',
                'badge'      => 'Action',
                'permission' => 'campaigns.view'
            ],
            [
                'category'   => 'Quick Actions',
                'title'      => 'Add New Lead',
                'subtitle'   => 'Quickly capture prospective customer record',
                'url'        => 'index.php?route=leads/index',
                'icon'       => 'fa-user-plus',
                'badge'      => 'Action',
                'permission' => 'leads.view'
            ],
            [
                'category'   => 'Quick Actions',
                'title'      => 'Run Pre-Payroll Checklist',
                'subtitle'   => 'Verify attendance, deductions, and active CTC',
                'url'        => 'index.php?route=payroll/checklist',
                'icon'       => 'fa-calculator',
                'badge'      => 'Payroll',
                'role'       => 'hr_finance_admin'
            ],
            [
                'category'   => 'Frequent Places',
                'title'      => 'Employee Directory',
                'subtitle'   => 'HR & Payroll staff directory and compensation',
                'url'        => 'index.php?route=users/index',
                'icon'       => 'fa-users',
                'badge'      => 'HR',
                'permission' => 'users.view'
            ],
            [
                'category'   => 'Frequent Places',
                'title'      => 'Shared Omnichannel Inbox',
                'subtitle'   => 'Unified WhatsApp, Email & Customer messages',
                'url'        => 'index.php?route=inbox/index',
                'icon'       => 'fa-inbox',
                'badge'      => 'Live'
            ],
            [
                'category'   => 'Frequent Places',
                'title'      => 'Deliverability Preflight & Alerts',
                'subtitle'   => 'Spam score rules D-001 through D-020 verification',
                'url'        => 'index.php?route=emailcampaigns/preflight',
                'icon'       => 'fa-shield-halved',
                'badge'      => 'Deliverability',
                'permission' => 'campaigns.view'
            ]
        ];

        return $this->filterPermittedItems($suggestions);
    }

    /**
     * Comprehensive registry of Raptor pages and actions.
     */
    private function getAllNavigationItems(): array {
        $items = [
            // HR & Payroll
            ['title' => 'Employee Directory', 'subtitle' => 'HR & Payroll Staff Directory', 'url' => 'index.php?route=users/index', 'icon' => 'fa-users', 'keywords' => 'employees staff team directory people hr', 'badge' => 'HR', 'permission' => 'users.view'],
            ['title' => 'Salary Structures & Revision', 'subtitle' => 'CTC, Basic, HRA, PF, Gratuity configuration', 'url' => 'index.php?route=payroll/salary_structures', 'icon' => 'fa-file-invoice-dollar', 'keywords' => 'salary ctc basic hra pf gratuity revision compensation', 'badge' => 'Payroll', 'role' => 'hr_finance_admin'],
            ['title' => 'Pay Bands & Grades', 'subtitle' => 'Band 1 to Band 8 salary bands', 'url' => 'index.php?route=payroll/pay_bands', 'icon' => 'fa-layer-group', 'keywords' => 'pay bands grades compensation tiers', 'badge' => 'Payroll', 'role' => 'hr_finance_admin'],
            ['title' => 'Pre-Payroll Checklist', 'subtitle' => 'Attendance, LOP, and salary readiness audit', 'url' => 'index.php?route=payroll/checklist', 'icon' => 'fa-list-check', 'keywords' => 'checklist pre payroll lop attendance audit', 'badge' => 'Payroll', 'role' => 'hr_finance_admin'],
            ['title' => 'Payroll Runs', 'subtitle' => 'Monthly payroll execution, approval & lock', 'url' => 'index.php?route=payroll/runs', 'icon' => 'fa-money-check-dollar', 'keywords' => 'payroll run execute approve lock payout', 'badge' => 'Payroll', 'role' => 'hr_finance_admin'],
            ['title' => 'Reconciliation Report', 'subtitle' => 'Month-over-month variance analysis', 'url' => 'index.php?route=payroll/reconciliation', 'icon' => 'fa-scale-balanced', 'keywords' => 'reconciliation report variance diff month', 'badge' => 'Payroll', 'role' => 'hr_finance_admin'],
            ['title' => 'My Payslips', 'subtitle' => 'Download monthly payslip PDF', 'url' => 'index.php?route=payroll/my_payslips', 'icon' => 'fa-receipt', 'keywords' => 'payslip download salary slip pdf', 'badge' => 'Employee'],
            ['title' => 'Leave Balances', 'subtitle' => 'Leave approvals, casual, sick and earned leaves', 'url' => 'index.php?route=leaves/index', 'icon' => 'fa-calendar-check', 'keywords' => 'leave vacation sick casual holidays', 'badge' => 'HR', 'permission' => 'leaves.view'],
            ['title' => 'Attendance & Check-In', 'subtitle' => 'Punch in/out and daily work logs', 'url' => 'index.php?route=attendance/index', 'icon' => 'fa-fingerprint', 'keywords' => 'attendance clock in check in punch work date', 'badge' => 'Portal', 'permission' => 'attendance.view'],

            // CRM & Omnichannel
            ['title' => 'Customer 360', 'subtitle' => 'Holistic customer profiles & activity streams', 'url' => 'index.php?route=customers/index', 'icon' => 'fa-address-card', 'keywords' => 'customer 360 accounts clients profile history', 'badge' => 'CRM', 'permission' => 'customers.view'],
            ['title' => 'Leads Management', 'subtitle' => 'Lead capture, scoring & pipeline progression', 'url' => 'index.php?route=leads/index', 'icon' => 'fa-crosshairs', 'keywords' => 'leads prospect sales scoring pipeline ingestion', 'badge' => 'CRM', 'permission' => 'leads.view'],
            ['title' => 'Contacts Directory', 'subtitle' => 'Individual contact records and preferences', 'url' => 'index.php?route=contacts/index', 'icon' => 'fa-address-book', 'keywords' => 'contacts list phone email person', 'badge' => 'CRM', 'permission' => 'leads.view'],
            ['title' => 'Shared Omnichannel Inbox', 'subtitle' => 'WhatsApp, email and customer messages', 'url' => 'index.php?route=inbox/index', 'icon' => 'fa-inbox', 'keywords' => 'inbox shared conversations messages chat whatsapp', 'badge' => 'Omnichannel'],
            ['title' => 'WhatsApp Cloud API', 'subtitle' => 'Meta WhatsApp broadcast and templates', 'url' => 'index.php?route=whatsapp/index', 'icon' => 'fa-brands fa-whatsapp', 'keywords' => 'whatsapp meta cloud api templates broadcast', 'badge' => 'Messaging', 'permission' => 'whatsapp.view'],
            ['title' => 'Telephony & Call Logs', 'subtitle' => 'Inbound/outbound calls and call tracking', 'url' => 'index.php?route=telephony/index', 'icon' => 'fa-phone-volume', 'keywords' => 'phone telephony call call logs recording ivr', 'badge' => 'Voice', 'permission' => 'telephony.view'],
            ['title' => 'Sales Pipeline & Opportunities', 'subtitle' => 'Deal stages, forecasts and growth opportunities', 'url' => 'index.php?route=accountsales/opportunities', 'icon' => 'fa-chart-line', 'keywords' => 'deals opportunities pipeline stages forecast', 'badge' => 'Sales', 'permission' => 'leads.view'],
            ['title' => 'Automation Journeys', 'subtitle' => 'Visual trigger and drip automation workflows', 'url' => 'index.php?route=journeys/index', 'icon' => 'fa-diagram-project', 'keywords' => 'automation journeys drip trigger sequence workflows', 'badge' => 'Marketing', 'permission' => 'campaigns.view'],

            // Email Marketing & Deliverability
            ['title' => 'Email Campaigns', 'subtitle' => 'Mass broadcast campaigns and dispatch status', 'url' => 'index.php?route=emailcampaigns/index', 'icon' => 'fa-envelope-open-text', 'keywords' => 'campaigns email mass broadcast mailer newsletters', 'badge' => 'Marketing', 'permission' => 'campaigns.view'],
            ['title' => 'New Email Campaign', 'subtitle' => 'Compose campaign with preflight spam check', 'url' => 'index.php?route=emailcampaigns/create', 'icon' => 'fa-paper-plane', 'keywords' => 'new campaign create compose broadcast send', 'badge' => 'Action', 'permission' => 'campaigns.view'],
            ['title' => 'Campaign Templates', 'subtitle' => 'Responsive email templates and merge tags', 'url' => 'index.php?route=emailcampaigns/templates', 'icon' => 'fa-palette', 'keywords' => 'templates html email design layouts', 'badge' => 'Marketing', 'permission' => 'campaigns.view'],
            ['title' => 'Marketing Contacts & Lists', 'subtitle' => 'Audience lists, CSV import and dynamic segments', 'url' => 'index.php?route=emailcampaigns/contacts', 'icon' => 'fa-list-ul', 'keywords' => 'lists contacts segments audience subscribers', 'badge' => 'Marketing', 'permission' => 'campaigns.view'],
            ['title' => 'Global Suppression List', 'subtitle' => 'Hard bounces, complaints and unsubscribed emails', 'url' => 'index.php?route=emailcampaigns/suppression', 'icon' => 'fa-ban', 'keywords' => 'suppression unsubscribe bounce complaint optout blacklist', 'badge' => 'Deliverability', 'permission' => 'campaigns.view'],
            ['title' => 'Sender Domains & DKIM', 'subtitle' => 'SPF, DKIM 2048-bit, DMARC authentication', 'url' => 'index.php?route=emailcampaigns/domains', 'icon' => 'fa-shield-halved', 'keywords' => 'domains dkim spf dmarc dns authentication mx', 'badge' => 'Deliverability', 'permission' => 'campaigns.view'],
            ['title' => 'Campaign Analytics', 'subtitle' => 'Real-time opens, clicks, CTR and bounce tracking', 'url' => 'index.php?route=emailcampaigns/analytics', 'icon' => 'fa-chart-pie', 'keywords' => 'analytics opens clicks ctr bounce rates stats metrics', 'badge' => 'Marketing', 'permission' => 'campaigns.view'],
            ['title' => 'Deliverability Preflight & Rules', 'subtitle' => 'Inspect rules D-001 through D-020 engine', 'url' => 'index.php?route=emailcampaigns/preflight', 'icon' => 'fa-traffic-light', 'keywords' => 'preflight deliverability spam rules d001 d020 score', 'badge' => 'Deliverability', 'permission' => 'campaigns.view'],
            ['title' => 'Marketing Alert Center', 'subtitle' => 'Real-time alert thresholds and automated pauses', 'url' => 'index.php?route=emailcampaigns/alerts', 'icon' => 'fa-bell', 'keywords' => 'alerts center notifications threshold warnings', 'badge' => 'Deliverability', 'permission' => 'campaigns.view'],

            // Operations & Settings
            ['title' => 'Task Management', 'subtitle' => 'Assign, track and complete team tasks', 'url' => 'index.php?route=tasks/index', 'icon' => 'fa-list-check', 'keywords' => 'tasks todo assignments kanban', 'badge' => 'Operations', 'permission' => 'tasks.view'],
            ['title' => 'Targets & Milestones', 'subtitle' => 'Monthly revenue and sales targets', 'url' => 'index.php?route=targets/index', 'icon' => 'fa-bullseye', 'keywords' => 'targets quotas goals kpi', 'badge' => 'Sales', 'permission' => 'targets.view'],
            ['title' => 'Invoices & Billing', 'subtitle' => 'Generate and send client invoices', 'url' => 'index.php?route=invoices/index', 'icon' => 'fa-file-invoice', 'keywords' => 'invoices billing payments finance', 'badge' => 'Finance', 'permission' => 'invoices.view'],
            ['title' => 'System Settings', 'subtitle' => 'Application configurations and integrations', 'url' => 'index.php?route=settings/index', 'icon' => 'fa-gear', 'keywords' => 'settings configuration security api keys preferences', 'badge' => 'Admin', 'role' => 'admin'],
            ['title' => 'Roles & RBAC Permissions', 'subtitle' => 'Access control policies and role boundaries', 'url' => 'index.php?route=roles/index', 'icon' => 'fa-user-shield', 'keywords' => 'roles permissions rbac access security policy', 'badge' => 'Admin', 'role' => 'admin']
        ];

        return $this->filterPermittedItems($items);
    }

    /**
     * Filter items according to active user's permissions and role.
     */
    private function filterPermittedItems(array $items): array {
        // In CLI test harnesses (without active session) or for system administrators, return full catalog
        if (!isset($_SESSION['user_id']) || Policy::isAdmin()) {
            return $items;
        }

        $filtered = [];
        foreach ($items as $item) {
            // Check role boundary
            if (isset($item['role'])) {
                if ($item['role'] === 'admin' && !Policy::isAdmin()) {
                    continue;
                }
                if ($item['role'] === 'hr_finance_admin' && !Policy::isAdmin() && !Policy::isHr() && !Policy::isFinance()) {
                    continue;
                }
            }

            // Check permission boundary
            if (isset($item['permission'])) {
                [$mod, $act] = explode('.', $item['permission'], 2);
                if (!PermissionService::can($mod, $act)) {
                    continue;
                }
            }

            $filtered[] = $item;
        }

        return $filtered;
    }
}
