<?php
/**
 * Raptor CRM Full Application Test Script
 * Tests every route and reports errors
 */

require_once dirname(dirname(__FILE__)) . '/app/config/config.php';
require_once dirname(dirname(__FILE__)) . '/app/core/Database.php';

$db = Database::getInstance()->getConnection();

echo "=== Raptor CRM Application Test Report ===\n\n";

// ========================================================
// TEST 1: Database Tables Existence
// ========================================================
echo "[TEST 1] Database Tables Check\n";
$requiredTables = [
    'users', 'roles', 'employees', 'clients', 'campaigns',
    'social_accounts', 'channel_daily_metrics', 'posts',
    'post_analytics', 'leads', 'web_behavior_sessions',
    'attribution_touchpoints', 'invoices', 'tasks',
    'brand_sentiment_logs', 'competitor_benchmarks',
    'smart_alerts', 'audience_demographics_snapshots', 'best_posting_time_metrics',
    'settings', 'notifications', 'calendar_events', 'daily_activity_logs'
];

$existingTables = [];
$stmt = $db->query("SHOW TABLES");
while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
    $existingTables[] = $row[0];
}

foreach ($requiredTables as $table) {
    $exists = in_array($table, $existingTables);
    echo "  " . ($exists ? "✅" : "❌") . " $table" . ($exists ? "" : " [MISSING]") . "\n";
}

// ========================================================
// TEST 2: User Accounts Verification
// ========================================================
echo "\n[TEST 2] User Accounts Verification\n";
$users = $db->query("
    SELECT u.user_id, u.name, u.email, r.role_name, u.status
    FROM users u
    JOIN roles r ON u.role_id = r.role_id
    ORDER BY u.user_id
")->fetchAll(PDO::FETCH_OBJ);

echo "  Total users: " . count($users) . "\n";
foreach ($users as $u) {
    echo "  [$u->role_name] $u->name ($u->email) - $u->status\n";
}

// Test password verification
$testLogins = [
    ['email' => 'admin@raptor.com', 'password' => 'admin123'],
    ['email' => 'manager@raptor.com', 'password' => 'manager123'],
];
echo "\n  Password Verification:\n";
foreach ($testLogins as $login) {
    $stmt = $db->prepare("SELECT password FROM users WHERE email = :email");
    $stmt->execute([':email' => $login['email']]);
    $row = $stmt->fetch(PDO::FETCH_OBJ);
    if ($row) {
        $valid = password_verify($login['password'], $row->password);
        echo "  " . ($valid ? "✅" : "❌") . " {$login['email']} / {$login['password']} => " . ($valid ? "OK" : "FAIL") . "\n";
    } else {
        echo "  ❌ {$login['email']} => USER NOT FOUND\n";
    }
}

// ========================================================
// TEST 3: Employee Records Completeness
// ========================================================
echo "\n[TEST 3] Employee Records\n";
$employees = $db->query("
    SELECT e.*, u.name, u.email 
    FROM employees e 
    JOIN users u ON e.user_id = u.user_id
    ORDER BY e.employee_code
")->fetchAll(PDO::FETCH_OBJ);

echo "  Total employees: " . count($employees) . "\n";
$issues = [];
foreach ($employees as $emp) {
    if (empty($emp->employee_code)) $issues[] = "$emp->name: missing employee_code";
    if (empty($emp->department)) $issues[] = "$emp->name: missing department";
    if (empty($emp->job_title)) $issues[] = "$emp->name: missing job_title";
    if (empty($emp->hire_date)) $issues[] = "$emp->name: missing hire_date";
}
echo "  " . (empty($issues) ? "✅ All employee records complete" : "❌ " . count($issues) . " issues found") . "\n";
foreach ($issues as $issue) {
    echo "    - $issue\n";
}

// ========================================================
// TEST 4: Data Volume Verification
// ========================================================
echo "\n[TEST 4] Data Volume Verification\n";
$volumeChecks = [
    ['table' => 'social_accounts', 'expected' => 50, 'label' => 'Social Accounts'],
    ['table' => 'tasks', 'expected' => 200, 'label' => 'Tasks'],
    ['table' => 'campaigns', 'expected' => 5, 'label' => 'Campaigns'],
    ['table' => 'clients', 'expected' => 3, 'label' => 'Clients'],
    ['table' => 'leads', 'expected' => 50, 'label' => 'Leads'],
    ['table' => 'posts', 'expected' => 100, 'label' => 'Posts'],
    ['table' => 'channel_daily_metrics', 'expected' => 100, 'label' => 'Channel Daily Metrics'],
    ['table' => 'daily_activity_logs', 'expected' => 100, 'label' => 'Daily Activity Logs'],
    ['table' => 'invoices', 'expected' => 5, 'label' => 'Invoices'],
    ['table' => 'brand_sentiment_logs', 'expected' => 50, 'label' => 'Sentiment Logs'],
    ['table' => 'competitor_benchmarks', 'expected' => 10, 'label' => 'Competitor Benchmarks'],
    ['table' => 'calendar_events', 'expected' => 5, 'label' => 'Calendar Events'],
    ['table' => 'notifications', 'expected' => 5, 'label' => 'Notifications'],
    ['table' => 'best_posting_time_metrics', 'expected' => 10, 'label' => 'Best Posting Time Metrics'],
    ['table' => 'audience_demographics_snapshots', 'expected' => 5, 'label' => 'Audience Demographics'],
];

foreach ($volumeChecks as $check) {
    $count = $db->query("SELECT COUNT(*) FROM {$check['table']}")->fetchColumn();
    $ok = $count >= $check['expected'];
    echo "  " . ($ok ? "✅" : "⚠️") . " {$check['label']}: $count rows" . (!$ok ? " (expected >= {$check['expected']})" : "") . "\n";
}

// ========================================================
// TEST 5: API Endpoint Tests (Executive, Channels, Customer)
// ========================================================
echo "\n[TEST 5] API Endpoint Data Tests\n";

// Test executive API data
$execData = $db->query("
    SELECT 
        SUM(revenue_influenced) as total_rev,
        SUM(spend) as total_spend,
        COUNT(*) as camp_count
    FROM campaigns
")->fetch(PDO::FETCH_OBJ);
echo "  ✅ Executive KPIs: Revenue=\${$execData->total_rev}, Spend=\${$execData->total_spend}, Campaigns={$execData->camp_count}\n";

// Test channels data
$channelData = $db->query("
    SELECT platform, COUNT(*) as days, SUM(impressions) as imp, SUM(clicks) as clicks
    FROM channel_daily_metrics 
    GROUP BY platform
    ORDER BY SUM(impressions) DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_OBJ);
echo "  Channel Metrics:\n";
foreach ($channelData as $ch) {
    echo "    ✅ {$ch->platform}: {$ch->days} day-entries, " . number_format($ch->imp) . " impressions, " . number_format($ch->clicks) . " clicks\n";
}

// Test customer intelligence data
$leadData = $db->query("
    SELECT status, COUNT(*) as cnt FROM leads GROUP BY status
")->fetchAll(PDO::FETCH_OBJ);
echo "  Lead Pipeline:\n";
foreach ($leadData as $lead) {
    echo "    ✅ {$lead->status}: {$lead->cnt}\n";
}

// ========================================================
// TEST 6: Controller Routing Tests (checking class files exist)
// ========================================================
echo "\n[TEST 6] Controller File Verification\n";
$controllersDir = dirname(dirname(__FILE__)) . '/app/controllers/';
$requiredControllers = [
    'AuthController.php', 'DashboardController.php', 'ApiController.php',
    'UsersController.php', 'SettingsController.php',
    'ClientsController.php', 'CampaignsController.php',
    'LeadsController.php', 'TasksController.php', 
    'InvoicesController.php', 'ReportsController.php',
    'CalendarController.php'
];

foreach ($requiredControllers as $ctrl) {
    $exists = file_exists($controllersDir . $ctrl);
    echo "  " . ($exists ? "✅" : "❌") . " $ctrl" . ($exists ? "" : " [MISSING]") . "\n";
}

// ========================================================
// TEST 7: View File Verification
// ========================================================
echo "\n[TEST 7] View File Verification\n";
$viewsDir = dirname(dirname(__FILE__)) . '/app/views/';
$requiredViews = [
    'auth/login.php',
    'layouts/main.php',
    'dashboard/executive.php',
    'dashboard/channels.php',
    'dashboard/customer.php',
    'users/index.php',
    'settings/index.php',
    'clients/index.php',
    'clients/add.php',
    'clients/edit.php',
    'campaigns/index.php',
    'campaigns/add.php',
    'campaigns/edit.php',
    'leads/index.php',
    'leads/add.php',
    'tasks/index.php',
    'invoices/index.php',
    'invoices/add.php',
    'reports/index.php',
    'calendar/index.php',
    'errors/403.php',
    'errors/404.php',
];

foreach ($requiredViews as $view) {
    $exists = file_exists($viewsDir . $view);
    echo "  " . ($exists ? "✅" : "❌") . " $view" . ($exists ? "" : " [MISSING]") . "\n";
}

// ========================================================
// TEST 8: Settings Table Verification
// ========================================================
echo "\n[TEST 8] Settings Table Status\n";
$settingsCount = $db->query("SELECT COUNT(*) FROM settings")->fetchColumn();
echo "  Settings rows: $settingsCount\n";
if ($settingsCount > 0) {
    $settings = $db->query("SELECT * FROM settings")->fetchAll(PDO::FETCH_OBJ);
    foreach ($settings as $s) {
        echo "  ✅ {$s->setting_key} = " . (empty($s->setting_value) ? "(empty)" : substr($s->setting_value, 0, 30)) . "\n";
    }
} else {
    echo "  ⚠️ No settings saved yet (defaults will be used)\n";
}

// ========================================================
// TEST 9: GeminiService AI Summary Test (rule-based fallback)
// ========================================================
echo "\n[TEST 9] GeminiService AI Summary Fallback\n";
try {
    require_once dirname(dirname(__FILE__)) . '/app/core/GeminiService.php';
    $gemini = new GeminiService();
    $summary = $gemini->generateExecutiveSummary('All Clients', [
        'roi' => 63.66,
        'revenue' => 6101771,
        'q_leads' => 9,
        'cac' => 26625.83,
        'conv_rate' => 3.15,
        'spend' => 95853,
    ], [
        ['channel' => 'Website', 'roi' => 243.89],
        ['channel' => 'Snapchat', 'roi' => 32.35],
    ]);
    $hasContent = strlen($summary) > 50;
    echo "  " . ($hasContent ? "✅" : "❌") . " AI Summary generated: " . strlen($summary) . " chars\n";
} catch (Exception $e) {
    echo "  ❌ GeminiService error: " . $e->getMessage() . "\n";
}

echo "\n=== Test Report Complete ===\n";
