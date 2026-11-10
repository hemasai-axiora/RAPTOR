<?php
/**
 * QuickSearchController — Real-time search provider for Raptor Global Command Palette (Ctrl+K).
 * Performs lightning-fast fuzzy querying across navigation links, employees, leads, and campaigns.
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

        // 1. Static Navigation & Quick Actions Matching
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

        // 2. Query Database for Entities (Employees, Leads, Campaigns)
        try {
            $db = Database::getInstance()->getConnection();
            $param = '%' . $q . '%';

            // A. Employees / Team Members
            try {
                $stmtEmp = $db->prepare("
                    SELECT u.user_id, u.name, u.email, 
                           COALESCE(e.employee_id, '') AS emp_id,
                           COALESCE(e.designation, r.role_name) AS subtitle
                    FROM users u
                    LEFT JOIN roles r ON u.role_id = r.role_id
                    LEFT JOIN employees e ON u.user_id = e.user_id
                    WHERE (u.name LIKE :p1 OR u.email LIKE :p2 OR e.employee_id LIKE :p3 OR e.designation LIKE :p4)
                      AND u.status = 'active'
                    ORDER BY u.name ASC
                    LIMIT 5
                ");
                $stmtEmp->execute([':p1' => $param, ':p2' => $param, ':p3' => $param, ':p4' => $param]);
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

            // B. Leads & Opportunities
            try {
                $stmtLead = $db->prepare("
                    SELECT lead_id, 
                           TRIM(CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, ''))) AS full_name,
                           COALESCE(company_name, '') AS company,
                           COALESCE(email, '') AS email,
                           COALESCE(phone, '') AS phone,
                           COALESCE(status, 'New') AS lead_status
                    FROM leads
                    WHERE (first_name LIKE :p1 OR last_name LIKE :p2 OR email LIKE :p3 OR phone LIKE :p4 OR company_name LIKE :p5)
                    ORDER BY lead_id DESC
                    LIMIT 5
                ");
                $stmtLead->execute([':p1' => $param, ':p2' => $param, ':p3' => $param, ':p4' => $param, ':p5' => $param]);
                $leads = $stmtLead->fetchAll(PDO::FETCH_ASSOC) ?: [];

                foreach ($leads as $lead) {
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

            // C. Email Campaigns & Broadcasts
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
        return [
            [
                'category' => 'Quick Actions',
                'title'    => 'New Email Campaign',
                'subtitle' => 'Compose & launch deliverability-guarded broadcast',
                'url'      => 'index.php?route=emailcampaigns/create',
                'icon'     => 'fa-plus-circle',
                'badge'    => 'Action'
            ],
            [
                'category' => 'Quick Actions',
                'title'    => 'Add New Lead',
                'subtitle' => 'Quickly capture prospective customer record',
                'url'      => 'index.php?route=leads/index',
                'icon'     => 'fa-user-plus',
                'badge'    => 'Action'
            ],
            [
                'category' => 'Quick Actions',
                'title'    => 'Run Pre-Payroll Checklist',
                'subtitle' => 'Verify attendance, deductions, and active CTC',
                'url'      => 'index.php?route=payroll/checklist',
                'icon'     => 'fa-calculator',
                'badge'    => 'Payroll'
            ],
            [
                'category' => 'Frequent Places',
                'title'    => 'Employee Directory',
                'subtitle' => 'HR & Payroll staff directory and compensation',
                'url'      => 'index.php?route=users/index',
                'icon'     => 'fa-users',
                'badge'    => 'HR'
            ],
            [
                'category' => 'Frequent Places',
                'title'    => 'Shared Omnichannel Inbox',
                'subtitle' => 'Unified WhatsApp, Email & Customer messages',
                'url'      => 'index.php?route=inbox/index',
                'icon'     => 'fa-inbox',
                'badge'    => 'Live'
            ],
            [
                'category' => 'Frequent Places',
                'title'    => 'Deliverability Preflight & Alerts',
                'subtitle' => 'Spam score rules D-001 through D-020 verification',
                'url'      => 'index.php?route=emailcampaigns/preflight',
                'icon'     => 'fa-shield-halved',
                'badge'    => 'Deliverability'
            ]
        ];
    }

    /**
     * Comprehensive registry of Raptor pages and actions.
     */
    private function getAllNavigationItems(): array {
        return [
            // HR & Payroll
            ['title' => 'Employee Directory', 'subtitle' => 'HR & Payroll Staff Directory', 'url' => 'index.php?route=users/index', 'icon' => 'fa-users', 'keywords' => 'employees staff team directory people hr', 'badge' => 'HR'],
            ['title' => 'Salary Structures & Revision', 'subtitle' => 'CTC, Basic, HRA, PF, Gratuity configuration', 'url' => 'index.php?route=payroll/salary_structures', 'icon' => 'fa-file-invoice-dollar', 'keywords' => 'salary ctc basic hra pf gratuity revision compensation', 'badge' => 'Payroll'],
            ['title' => 'Pay Bands & Grades', 'subtitle' => 'Band 1 to Band 8 salary bands', 'url' => 'index.php?route=payroll/pay_bands', 'icon' => 'fa-layer-group', 'keywords' => 'pay bands grades compensation tiers', 'badge' => 'Payroll'],
            ['title' => 'Pre-Payroll Checklist', 'subtitle' => 'Attendance, LOP, and salary readiness audit', 'url' => 'index.php?route=payroll/checklist', 'icon' => 'fa-list-check', 'keywords' => 'checklist pre payroll lop attendance audit', 'badge' => 'Payroll'],
            ['title' => 'Payroll Runs', 'subtitle' => 'Monthly payroll execution, approval & lock', 'url' => 'index.php?route=payroll/runs', 'icon' => 'fa-money-check-dollar', 'keywords' => 'payroll run execute approve lock payout', 'badge' => 'Payroll'],
            ['title' => 'Reconciliation Report', 'subtitle' => 'Month-over-month variance analysis', 'url' => 'index.php?route=payroll/reconciliation', 'icon' => 'fa-scale-balanced', 'keywords' => 'reconciliation report variance diff month', 'badge' => 'Payroll'],
            ['title' => 'My Payslips', 'subtitle' => 'Download monthly payslip PDF', 'url' => 'index.php?route=payroll/my_payslips', 'icon' => 'fa-receipt', 'keywords' => 'payslip download salary slip pdf', 'badge' => 'Employee'],
            ['title' => 'Leave Balances', 'subtitle' => 'Leave approvals, casual, sick and earned leaves', 'url' => 'index.php?route=leaves/index', 'icon' => 'fa-calendar-check', 'keywords' => 'leave vacation sick casual holidays', 'badge' => 'HR'],
            ['title' => 'Attendance & Check-In', 'subtitle' => 'Punch in/out and daily work logs', 'url' => 'index.php?route=attendance/index', 'icon' => 'fa-fingerprint', 'keywords' => 'attendance clock in check in punch work date', 'badge' => 'Portal'],

            // CRM & Omnichannel
            ['title' => 'Customer 360', 'subtitle' => 'Holistic customer profiles & activity streams', 'url' => 'index.php?route=customers/index', 'icon' => 'fa-address-card', 'keywords' => 'customer 360 accounts clients profile history', 'badge' => 'CRM'],
            ['title' => 'Leads Management', 'subtitle' => 'Lead capture, scoring & pipeline progression', 'url' => 'index.php?route=leads/index', 'icon' => 'fa-crosshairs', 'keywords' => 'leads prospect sales scoring pipeline ingestion', 'badge' => 'CRM'],
            ['title' => 'Contacts Directory', 'subtitle' => 'Individual contact records and preferences', 'url' => 'index.php?route=contacts/index', 'icon' => 'fa-address-book', 'keywords' => 'contacts list phone email person', 'badge' => 'CRM'],
            ['title' => 'Shared Omnichannel Inbox', 'subtitle' => 'WhatsApp, email and customer messages', 'url' => 'index.php?route=inbox/index', 'icon' => 'fa-inbox', 'keywords' => 'inbox shared conversations messages chat whatsapp', 'badge' => 'Omnichannel'],
            ['title' => 'WhatsApp Cloud API', 'subtitle' => 'Meta WhatsApp broadcast and templates', 'url' => 'index.php?route=whatsapp/index', 'icon' => 'fa-brands fa-whatsapp', 'keywords' => 'whatsapp meta cloud api templates broadcast', 'badge' => 'Messaging'],
            ['title' => 'Telephony & Call Logs', 'subtitle' => 'Inbound/outbound calls and call tracking', 'url' => 'index.php?route=telephony/index', 'icon' => 'fa-phone-volume', 'keywords' => 'phone telephony call call logs recording ivr', 'badge' => 'Voice'],
            ['title' => 'Sales Pipeline & Opportunities', 'subtitle' => 'Deal stages, forecasts and growth opportunities', 'url' => 'index.php?route=accountsales/opportunities', 'icon' => 'fa-chart-line', 'keywords' => 'deals opportunities pipeline stages forecast', 'badge' => 'Sales'],
            ['title' => 'Automation Journeys', 'subtitle' => 'Visual trigger and drip automation workflows', 'url' => 'index.php?route=journeys/index', 'icon' => 'fa-diagram-project', 'keywords' => 'automation journeys drip trigger sequence workflows', 'badge' => 'Marketing'],

            // Email Marketing & Deliverability
            ['title' => 'Email Campaigns', 'subtitle' => 'Mass broadcast campaigns and dispatch status', 'url' => 'index.php?route=emailcampaigns/index', 'icon' => 'fa-envelope-open-text', 'keywords' => 'campaigns email mass broadcast mailer newsletters', 'badge' => 'Marketing'],
            ['title' => 'New Email Campaign', 'subtitle' => 'Compose campaign with preflight spam check', 'url' => 'index.php?route=emailcampaigns/create', 'icon' => 'fa-paper-plane', 'keywords' => 'new campaign create compose broadcast send', 'badge' => 'Action'],
            ['title' => 'Campaign Templates', 'subtitle' => 'Responsive email templates and merge tags', 'url' => 'index.php?route=emailcampaigns/templates', 'icon' => 'fa-palette', 'keywords' => 'templates html email design layouts', 'badge' => 'Marketing'],
            ['title' => 'Marketing Contacts & Lists', 'subtitle' => 'Audience lists, CSV import and dynamic segments', 'url' => 'index.php?route=emailcampaigns/contacts', 'icon' => 'fa-list-ul', 'keywords' => 'lists contacts segments audience subscribers', 'badge' => 'Marketing'],
            ['title' => 'Global Suppression List', 'subtitle' => 'Hard bounces, complaints and unsubscribed emails', 'url' => 'index.php?route=emailcampaigns/suppression', 'icon' => 'fa-ban', 'keywords' => 'suppression unsubscribe bounce complaint optout blacklist', 'badge' => 'Deliverability'],
            ['title' => 'Sender Domains & DKIM', 'subtitle' => 'SPF, DKIM 2048-bit, DMARC authentication', 'url' => 'index.php?route=emailcampaigns/domains', 'icon' => 'fa-shield-halved', 'keywords' => 'domains dkim spf dmarc dns authentication mx', 'badge' => 'Deliverability'],
            ['title' => 'Campaign Analytics', 'subtitle' => 'Real-time opens, clicks, CTR and bounce tracking', 'url' => 'index.php?route=emailcampaigns/analytics', 'icon' => 'fa-chart-pie', 'keywords' => 'analytics opens clicks ctr bounce rates stats metrics', 'badge' => 'Marketing'],
            ['title' => 'Deliverability Preflight & Rules', 'subtitle' => 'Inspect rules D-001 through D-020 engine', 'url' => 'index.php?route=emailcampaigns/preflight', 'icon' => 'fa-traffic-light', 'keywords' => 'preflight deliverability spam rules d001 d020 score', 'badge' => 'Deliverability'],
            ['title' => 'Marketing Alert Center', 'subtitle' => 'Real-time alert thresholds and automated pauses', 'url' => 'index.php?route=emailcampaigns/alerts', 'icon' => 'fa-bell', 'keywords' => 'alerts center notifications threshold warnings', 'badge' => 'Deliverability'],

            // Operations & Settings
            ['title' => 'Task Management', 'subtitle' => 'Assign, track and complete team tasks', 'url' => 'index.php?route=tasks/index', 'icon' => 'fa-list-check', 'keywords' => 'tasks todo assignments kanban', 'badge' => 'Operations'],
            ['title' => 'Targets & Milestones', 'subtitle' => 'Monthly revenue and sales targets', 'url' => 'index.php?route=targets/index', 'icon' => 'fa-bullseye', 'keywords' => 'targets quotas goals kpi', 'badge' => 'Sales'],
            ['title' => 'Invoices & Billing', 'subtitle' => 'Generate and send client invoices', 'url' => 'index.php?route=invoices/index', 'icon' => 'fa-file-invoice', 'keywords' => 'invoices billing payments finance', 'badge' => 'Finance'],
            ['title' => 'System Settings', 'subtitle' => 'Application configurations and integrations', 'url' => 'index.php?route=settings/index', 'icon' => 'fa-gear', 'keywords' => 'settings configuration security api keys preferences', 'badge' => 'Admin'],
            ['title' => 'Roles & RBAC Permissions', 'subtitle' => 'Access control policies and role boundaries', 'url' => 'index.php?route=roles/index', 'icon' => 'fa-user-shield', 'keywords' => 'roles permissions rbac access security policy', 'badge' => 'Admin']
        ];
    }
}
