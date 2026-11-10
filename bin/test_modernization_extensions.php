<?php
/**
 * Test Suite: Raptor Next-Gen Architectural & UI Modernization Extensions
 * -------------------------------------------------------------------------
 * Verifies:
 * 1. EventDispatcher registration, priority order, dispatching, and cancellation.
 * 2. QuickSearchController responses, formatting, and navigation items.
 * 3. OpenAPI 3.1 specification schema validity.
 * 4. Main layout modern additions (Command Palette, Toast Container, Skeletons).
 * 5. Raptor CLI Console (bin/raptor) health check and commands.
 */

declare(strict_types=1);

require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/core/Controller.php';
require_once __DIR__ . '/../app/core/EventDispatcher.php';
require_once __DIR__ . '/../app/controllers/QuickSearchController.php';

$passed = 0;
$failed = 0;

function assertCustom(bool $cond, string $msg): void {
    global $passed, $failed;
    if ($cond) {
        $passed++;
        echo "  [PASS] {$msg}\n";
    } else {
        $failed++;
        echo "  [FAIL] {$msg}\n";
    }
}

echo "=== RAPTOR MODERNIZATION & ADVANCEMENTS EXTENSIONS SUITE ===\n\n";

// ── 1. EventDispatcher Unit Tests ──
echo "1. Testing EventDispatcher Subsystem...\n";
EventDispatcher::reset();

$executionLog = [];

EventDispatcher::addListener('order.completed', function($payload) use (&$executionLog) {
    $executionLog[] = 'normal:' . $payload['id'];
}, 0);

EventDispatcher::addListener('order.completed', function($payload) use (&$executionLog) {
    $executionLog[] = 'high_priority:' . $payload['id'];
}, 10);

EventDispatcher::addListener('order.completed', function($payload) use (&$executionLog) {
    $executionLog[] = 'low_priority:' . $payload['id'];
}, -5);

assertCustom(EventDispatcher::hasListeners('order.completed'), "Listeners are successfully registered");
assertCustom(!EventDispatcher::hasListeners('random.event'), "Unregistered events report no listeners");

EventDispatcher::dispatch('order.completed', ['id' => 42]);

assertCustom(count($executionLog) === 3, "All three listeners were executed");
assertCustom($executionLog[0] === 'high_priority:42', "Priority 10 executed first");
assertCustom($executionLog[1] === 'normal:42', "Priority 0 executed second");
assertCustom($executionLog[2] === 'low_priority:42', "Priority -5 executed third");

// Test Stopping Propagation
EventDispatcher::reset();
$stoppedLog = [];
EventDispatcher::addListener('gate.check', function($p) use (&$stoppedLog) {
    $stoppedLog[] = 'blocker';
    return false; // Stop propagation
}, 10);
EventDispatcher::addListener('gate.check', function($p) use (&$stoppedLog) {
    $stoppedLog[] = 'should_not_run';
}, 0);

$result = EventDispatcher::dispatch('gate.check');
assertCustom($result === false, "Dispatch returns false when propagation is stopped");
assertCustom($stoppedLog === ['blocker'], "Subsequent listeners are halted when propagation is cancelled");

// ── 2. QuickSearch Navigation & Suggestion Engine ──
echo "\n2. Testing QuickSearch Navigation & Fuzzy Suggestion Engine...\n";
$reflector = new ReflectionClass(QuickSearchController::class);
$getDefault = $reflector->getMethod('getDefaultNavigationSuggestions');
$getDefault->setAccessible(true);

$qs = $reflector->newInstanceWithoutConstructor();
$suggestions = $getDefault->invoke($qs);

assertCustom(is_array($suggestions) && count($suggestions) >= 5, "QuickSearch returns at least 5 instant default suggestions");
$hasHr = false;
$hasCampaign = false;
foreach ($suggestions as $s) {
    if (strpos($s['title'], 'Campaign') !== false) $hasCampaign = true;
    if (strpos($s['title'], 'Employee') !== false) $hasHr = true;
}
assertCustom($hasHr, "Default suggestions include HR/Employee Directory");
assertCustom($hasCampaign, "Default suggestions include Email Campaigns");

$getAll = $reflector->getMethod('getAllNavigationItems');
$getAll->setAccessible(true);
$allNav = $getAll->invoke($qs);

assertCustom(count($allNav) >= 20, "Navigation index covers at least 20 primary portal destinations");

// ── 3. OpenAPI 3.1 Specification Integrity ──
echo "\n3. Testing OpenAPI 3.1 Specification...\n";
$openapiPath = __DIR__ . '/../public/openapi.json';
assertCustom(file_exists($openapiPath), "public/openapi.json exists");
$specContent = file_get_contents($openapiPath);
$jsonSpec = json_decode($specContent, true);
assertCustom(json_last_error() === JSON_ERROR_NONE, "public/openapi.json is strictly valid JSON");
assertCustom(($jsonSpec['openapi'] ?? '') === '3.1.0', "OpenAPI version is specified as 3.1.0");
assertCustom(isset($jsonSpec['paths']['/index.php?route=quicksearch/query']), "OpenAPI specification documents quicksearch endpoint");
assertCustom(isset($jsonSpec['paths']['/index.php?route=api/leads']), "OpenAPI specification documents lead capture endpoint");

// ── 4. Main Layout Advancements Integrity ──
echo "\n4. Testing Main Layout Advancements...\n";
$mainLayoutPath = __DIR__ . '/../app/views/layouts/main.php';
$mainContent = file_get_contents($mainLayoutPath);

assertCustom(strpos($mainContent, 'id="raptor-cmd-trigger"') !== false, "Main layout renders Command Palette quick search trigger button");
assertCustom(strpos($mainContent, 'id="raptor-cmd-backdrop"') !== false, "Main layout renders Command Palette dialog modal");
assertCustom(strpos($mainContent, 'id="raptor-toast-container"') !== false, "Main layout renders modern Toast notification container");
assertCustom(strpos($mainContent, 'window.RaptorToast') !== false, "Main layout includes RaptorToast JavaScript engine");
assertCustom(strpos($mainContent, 'raptor-skeleton') !== false, "Main layout includes CSS skeleton shimmer utilities");

// ── 5. Raptor CLI Tool (bin/raptor) Integrity ──
echo "\n5. Testing Raptor Unified CLI Console (bin/raptor)...\n";
$cliPath = __DIR__ . '/raptor';
assertCustom(file_exists($cliPath), "bin/raptor executable script exists");
$cliContent = file_get_contents($cliPath);
assertCustom(strpos($cliContent, 'runHealthCheck') !== false, "bin/raptor defines system health diagnostics");
assertCustom(strpos($cliContent, 'showQueueStatus') !== false, "bin/raptor defines real-time queue inspector");

// ── 6. PWA Manifest, DLQ Inspector & Correlation Tracing ──
echo "\n6. Testing PWA, DLQ Inspector & Request Correlation Tracing...\n";
$manifestPath = __DIR__ . '/../public/manifest.json';
assertCustom(file_exists($manifestPath), "public/manifest.json exists");
$manifest = json_decode(file_get_contents($manifestPath), true);
assertCustom(isset($manifest['name']) && strpos($manifest['name'], 'RAPTOR') !== false, "PWA manifest defines valid app name");

require_once __DIR__ . '/../app/controllers/SettingsController.php';
$settingsReflect = new ReflectionClass(SettingsController::class);
assertCustom($settingsReflect->hasMethod('dlq'), "SettingsController defines dlq inspection route");
assertCustom($settingsReflect->hasMethod('retryDlq'), "SettingsController defines retryDlq recovery route");

$controllerReflect = new ReflectionClass(Controller::class);
assertCustom($controllerReflect->hasMethod('initCorrelationId'), "Controller defines distributed correlation tracing (X-Request-ID)");

// ── 7. Design System Semantic Tokens & Glassmorphic CSS Integrity (Sprint 1) ──
echo "\n7. Testing Design System Semantic Tokens & Glass Surfaces...\n";
$tokensPath = __DIR__ . '/../public/assets/css/raptor-tokens.css';
$glassPath  = __DIR__ . '/../public/assets/css/raptor-glass.css';

assertCustom(file_exists($tokensPath), "public/assets/css/raptor-tokens.css exists");
$tokensContent = file_get_contents($tokensPath);
assertCustom(strpos($tokensContent, '--raptor-primary') !== false, "raptor-tokens.css defines primary brand tokens");
assertCustom(strpos($tokensContent, '--raptor-surface-panel') !== false, "raptor-tokens.css defines surface tokens");
assertCustom(strpos($tokensContent, '[data-raptor-theme="light"]') !== false, "raptor-tokens.css supports light theme token palette");

assertCustom(file_exists($glassPath), "public/assets/css/raptor-glass.css exists");
$glassContent = file_get_contents($glassPath);
assertCustom(strpos($glassContent, '.raptor-glass-panel') !== false, "raptor-glass.css defines hardware-accelerated glass panel");
assertCustom(strpos($glassContent, 'prefers-reduced-transparency') !== false, "raptor-glass.css satisfies WCAG accessibility fallback for reduced transparency");

// ── 8. Reusable Blade-Style Component Library & Controller Helper (Sprint 1) ──
echo "\n8. Testing Reusable Component Library & Controller Helper...\n";
assertCustom($controllerReflect->hasMethod('component'), "Controller defines \$this->component(\$name, \$props) helper");

$testController = new class extends Controller {
    public function renderTest(string $comp, array $props): string {
        return (string) $this->component($comp, $props, true);
    }
};

$badgeHtml = $testController->renderTest('badge', ['label' => 'Active', 'variant' => 'success']);
assertCustom(strpos($badgeHtml, 'raptor-badge') !== false && strpos($badgeHtml, 'Active') !== false, "Component helper renders badge component with correct HTML");

$cardHtml = $testController->renderTest('card', ['title' => 'Sprint 1 Card', 'body' => 'Core Architecture']);
assertCustom(strpos($cardHtml, 'raptor-card') !== false && strpos($cardHtml, 'Sprint 1 Card') !== false, "Component helper renders card component with header and body");

$statHtml = $testController->renderTest('stat_tile', ['title' => 'Total Users', 'value' => '1,420', 'icon' => 'fa-users']);
assertCustom(strpos($statHtml, 'raptor-stat-tile') !== false && strpos($statHtml, '1,420') !== false, "Component helper renders stat_tile component with value and icon");

$emptyHtml = $testController->renderTest('empty_state', ['title' => 'No Records', 'description' => 'Try expanding filters']);
assertCustom(strpos($emptyHtml, 'raptor-empty-state') !== false && strpos($emptyHtml, 'No Records') !== false, "Component helper renders empty_state component");

// ── 9. QuickSearch RBAC Defense-in-Depth & AUD-003 Subtree Scoping (Sprint 1) ──
echo "\n9. Testing QuickSearch RBAC Defense-in-Depth (AUD-003)...\n";
$mockDb = new PDO('sqlite::memory:');
$mockDb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$mockDb->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_OBJ);
Database::setMockConnection($mockDb);

// Create minimal schema for PermissionService
$mockDb->exec("
    CREATE TABLE IF NOT EXISTS users (user_id INTEGER PRIMARY KEY, status TEXT, role_id INTEGER);
    CREATE TABLE IF NOT EXISTS roles (role_id INTEGER PRIMARY KEY, role_name TEXT);
    CREATE TABLE IF NOT EXISTS permissions (permission_id INTEGER PRIMARY KEY, module TEXT, action TEXT, permission_name TEXT, description TEXT);
    CREATE TABLE IF NOT EXISTS role_permissions (role_id INTEGER, permission_id INTEGER, scope TEXT);
    CREATE TABLE IF NOT EXISTS user_permission_overrides (user_id INTEGER, permission_id INTEGER, scope TEXT, type TEXT);
    CREATE TABLE IF NOT EXISTS employees (employee_id TEXT, user_id INTEGER, reporting_manager_id INTEGER, team_id INTEGER, designation TEXT);
    CREATE TABLE IF NOT EXISTS teams (team_id INTEGER, team_leader_user_id INTEGER, manager_user_id INTEGER);
    CREATE TABLE IF NOT EXISTS leads (lead_id INTEGER PRIMARY KEY, first_name TEXT, last_name TEXT, company_name TEXT, email TEXT, phone TEXT, status TEXT, assigned_to_user_id INTEGER, created_by_user_id INTEGER);
    CREATE TABLE IF NOT EXISTS marketing_campaigns (campaign_id INTEGER PRIMARY KEY, name TEXT, subject TEXT, status TEXT);
");

if (!isset($_SESSION)) {
    $_SESSION = [];
}
$_SESSION['user_id'] = 999;
$_SESSION['user_role'] = 'sales_person';
$_SESSION['role_id'] = 4;
$_SESSION['user_status'] = 'active';

$scopedNav = $getAll->invoke($qs);
$scopedUrls = array_column($scopedNav, 'url');

assertCustom(!in_array('index.php?route=settings/index', $scopedUrls, true), "Sales person cannot view admin System Settings in QuickSearch");
assertCustom(!in_array('index.php?route=roles/index', $scopedUrls, true), "Sales person cannot view Roles & Permissions in QuickSearch");
assertCustom(!in_array('index.php?route=payroll/salary_structures', $scopedUrls, true), "Sales person cannot view Salary Structures in QuickSearch");

// Clean up session mock
unset($_SESSION['user_id'], $_SESSION['user_role'], $_SESSION['role_id'], $_SESSION['user_status']);

// ── 10. Slide-Over Drawer & Component Standards (Sprint 2) ──
echo "\n10. Testing Slide-Over Drawer & Reusable Architecture...\n";
$drawerPath = __DIR__ . '/../app/views/components/drawer.php';
assertCustom(file_exists($drawerPath), "app/views/components/drawer.php exists");

$drawerHtml = $testController->renderTest('drawer', [
    'id'       => 'testPreviewDrawer',
    'title'    => 'Test Drawer',
    'subtitle' => 'Drawer Subtitle',
    'body'     => '<div class="test-body">Content</div>',
    'footer'   => '<button>Action</button>'
]);
assertCustom(strpos($drawerHtml, 'raptor-drawer') !== false, "Drawer component renders raptor-drawer container");
assertCustom(strpos($drawerHtml, 'testPreviewDrawer-backdrop') !== false, "Drawer component renders backdrop container");
assertCustom(strpos($drawerHtml, 'Test Drawer') !== false, "Drawer component renders title");

$glassContent = file_get_contents($glassPath);
assertCustom(strpos($glassContent, '.raptor-drawer') !== false, "raptor-glass.css defines slide-over drawer surface");
assertCustom(strpos($glassContent, '.raptor-drawer-backdrop') !== false, "raptor-glass.css defines drawer backdrop blur layer");

// ── 11. CRM, Leads Preview & Customer 360 AI Smart Reply (Sprint 2) ──
echo "\n11. Testing CRM Leads Preview & AI Smart Reply Engine...\n";
require_once __DIR__ . '/../app/controllers/LeadsController.php';
require_once __DIR__ . '/../app/controllers/CustomersController.php';

$leadsReflect = new ReflectionClass(LeadsController::class);
assertCustom($leadsReflect->hasMethod('preview'), "LeadsController defines preview() endpoint for slide-over drawer");

$custReflect = new ReflectionClass(CustomersController::class);
assertCustom($custReflect->hasMethod('smartReply'), "CustomersController defines smartReply() endpoint for AI copilot");

$leadsViewPath = __DIR__ . '/../app/views/leads/index.php';
$leadsViewContent = file_get_contents($leadsViewPath);
assertCustom(strpos($leadsViewContent, 'Quick Filters:') !== false, "Leads index renders quick filter pills (Hot, Warm, Cold, Qualified)");
assertCustom(strpos($leadsViewContent, 'leadPreviewDrawer') !== false, "Leads index integrates slide-over preview drawer");
assertCustom(strpos($leadsViewContent, 'csvHtml5') !== false, "Leads index integrates DataTables CSV export button");

$custDetailPath = __DIR__ . '/../app/views/customers/detail.php';
$custDetailContent = file_get_contents($custDetailPath);
assertCustom(strpos($custDetailContent, 'aiSmartReplyForm') !== false, "Customer detail renders AI Smart Reply Copilot form");
assertCustom(strpos($custDetailContent, 'customers/smartReply') !== false, "Customer detail dispatches to smartReply endpoint");

// ── 12. Statutory Payroll Polish & Pre-Payroll Checklist Gates (Sprint 3) ──
echo "\n12. Testing Statutory Payroll Polish & Pre-Payroll Gates...\n";
require_once __DIR__ . '/../app/core/PayslipPdfService.php';
require_once __DIR__ . '/../app/controllers/PayrollController.php';

$pdfReflect = new ReflectionClass(PayslipPdfService::class);
assertCustom($pdfReflect->hasMethod('getOrGenerateCachedPdf'), "PayslipPdfService defines getOrGenerateCachedPdf() for disk caching");
assertCustom($pdfReflect->hasMethod('invalidateCache'), "PayslipPdfService defines invalidateCache() for revised runs");

$dummyDetail = (object)[
    'payroll_detail_id' => 9991,
    'employee_id' => 101,
    'employee_code' => 'AX-TEST-01',
    'month_year' => '2026-10',
    'name' => 'Test Employee',
    'basic_salary' => 25000,
    'gross_salary' => 50000,
    'net_salary' => 45000,
    'pf' => 1800,
    'total_deductions' => 5000
];

$cachedBinary1 = PayslipPdfService::getOrGenerateCachedPdf($dummyDetail);
assertCustom(!empty($cachedBinary1) && strpos($cachedBinary1, '%PDF') === 0, "PayslipPdfService creates valid PDF binary on initial cache miss");

$cachedBinary2 = PayslipPdfService::getOrGenerateCachedPdf($dummyDetail);
assertCustom($cachedBinary1 === $cachedBinary2, "PayslipPdfService delivers identical cached PDF on subsequent access");

$invalidationSuccess = PayslipPdfService::invalidateCache(9991, '2026-10', 'AX-TEST-01');
assertCustom($invalidationSuccess, "PayslipPdfService successfully invalidates cached payslip on revision");

$payrollReflect = new ReflectionClass(PayrollController::class);
assertCustom($payrollReflect->hasMethod('reconciliation'), "PayrollController defines reconciliation() endpoint for MoM variance matrix");

$procViewPath = __DIR__ . '/../app/views/payroll/processing.php';
$procContent = file_get_contents($procViewPath);
assertCustom(strpos($procContent, 'Verification Incomplete') !== false || strpos($procContent, 'is_checklist_done') !== false, "Payroll processing view integrates pre-payroll checklist warning gate");
assertCustom(strpos($procContent, 'payroll/reconciliation') !== false, "Payroll processing view links to reconciliation report");

$reconViewPath = __DIR__ . '/../app/views/payroll/reconciliation.php';
assertCustom(file_exists($reconViewPath), "app/views/payroll/reconciliation.php exists");
$reconContent = file_get_contents($reconViewPath);
assertCustom(strpos($reconContent, 'table-sticky-header') !== false, "Reconciliation view renders sticky-header matrix view");
assertCustom(strpos($reconContent, 'csvHtml5') !== false, "Reconciliation view provides DataTables CSV export button");

// ── 11. Sprint 4: Marketing AI Copilot & Deliverability Intelligence ──
echo "\n11. Testing Sprint 4 Marketing AI Copilot & Deliverability Intelligence...\n";

require_once __DIR__ . '/../app/services/PreflightEngine.php';
require_once __DIR__ . '/../app/services/PreSendChecklistService.php';
require_once __DIR__ . '/../app/services/AiService.php';
require_once __DIR__ . '/../app/core/Model.php';
require_once __DIR__ . '/../app/models/CampaignDomain.php';
require_once __DIR__ . '/../app/models/CampaignContact.php';
require_once __DIR__ . '/../app/models/CampaignList.php';
require_once __DIR__ . '/../app/models/CampaignTag.php';
require_once __DIR__ . '/../app/models/CampaignSegment.php';
require_once __DIR__ . '/../app/models/CampaignSuppression.php';
require_once __DIR__ . '/../app/models/CampaignTemplate.php';
require_once __DIR__ . '/../app/models/CampaignBroadcast.php';
require_once __DIR__ . '/../app/controllers/EmailCampaignsController.php';

// Test 11.1: PreflightEngine Score Calculation
$scorePerfect = PreflightEngine::calculateScore(['block' => 0, 'high' => 0, 'warning' => 0, 'info' => 0]);
assertCustom($scorePerfect === 100, "PreflightEngine computes 100/100 score for pristine broadcast");

$scoreWarning = PreflightEngine::calculateScore(['block' => 0, 'high' => 1, 'warning' => 2, 'info' => 0]);
// 100 - (1*15) - (2*5) = 75
assertCustom($scoreWarning === 75, "PreflightEngine computes 75/100 score with 1 HIGH and 2 WARNING findings");

$scoreBlock = PreflightEngine::calculateScore(['block' => 1, 'high' => 0, 'warning' => 0, 'info' => 0]);
// 100 - 30 = 70 capped at 50
assertCustom($scoreBlock === 50, "PreflightEngine caps score at 50/100 when any BLOCK finding is present");

$scoreZero = PreflightEngine::calculateScore(['block' => 5, 'high' => 5, 'warning' => 5, 'info' => 5]);
assertCustom($scoreZero === 0, "PreflightEngine bounds score to minimum 0");

// Test 11.2: Rule Catalog & 4 Pillar Categorization
$catalog = PreflightEngine::getRuleCatalog();
assertCustom(count($catalog) === 20, "PreflightEngine provides rule catalog for all 20 rules (D-001 through D-020)");
assertCustom(isset($catalog['D-001']) && $catalog['D-001']['pillar'] === 'auth', "Rule D-001 correctly mapped to 'auth' pillar");
assertCustom(isset($catalog['D-005']) && $catalog['D-005']['pillar'] === 'legal', "Rule D-005 (RFC 8058) correctly mapped to 'legal' pillar");
assertCustom(isset($catalog['D-010']) && $catalog['D-010']['pillar'] === 'content', "Rule D-010 (Spam Signals) correctly mapped to 'content' pillar");
assertCustom(isset($catalog['D-011']) && $catalog['D-011']['pillar'] === 'delivery', "Rule D-011 (CTA Destinations) correctly mapped to 'delivery' pillar");

// Test 11.3: EmailCampaignsController Sprint 4 Actions
$campReflect = new ReflectionClass(EmailCampaignsController::class);
assertCustom($campReflect->hasMethod('aiSubjectLines'), "EmailCampaignsController defines aiSubjectLines() action for AI Copilot");
assertCustom($campReflect->hasMethod('testPreview'), "EmailCampaignsController defines testPreview() action for spam scanner and preview");

// Test 11.4: AI Subject Line Fallback Matrix
$compellingSubs = EmailCampaignsController::generateFallbackSubjectLines('Product Launch', 'promotional', 'compelling');
assertCustom(count($compellingSubs) === 5, "generateFallbackSubjectLines returns 5 compelling subject line suggestions");
assertCustom(!empty($compellingSubs[0]['subject']) && !empty($compellingSubs[0]['preview']), "Suggestions include subject and preview text");

$urgentSubs = EmailCampaignsController::generateFallbackSubjectLines('Quarterly Renewal', 'promotional', 'urgent');
assertCustom(count($urgentSubs) === 5 && strpos($urgentSubs[0]['angle'], 'Time') !== false, "Urgent tone returns time-sensitive subject line angles");

// Test 11.5: Spam Word Scanner and Highlight Engine
$testHtml = '<p>Hello world. This is a 100% free special gift! Please act now to claim your bonus.</p>';
$spamScore = PreSendChecklistService::calculateSpamScore('ACT NOW FOR FREE BONUS', $testHtml);
assertCustom($spamScore['score'] > 0 && count($spamScore['triggers']) > 0, "PreSendChecklistService detects spam trigger phrases in content");

// Test 11.6: UI Elements in Campaign Builder View
$createCampView = file_get_contents(__DIR__ . '/../app/views/email_campaigns/campaigns/create.php');
assertCustom(strpos($createCampView, 'aiSubjectModal') !== false, "Campaign builder view includes AI Subject Copilot modal");
assertCustom(strpos($createCampView, 'AI Subject Copilot') !== false, "Campaign builder view provides AI Subject Copilot button");
assertCustom(strpos($createCampView, 'health-meter-box') !== false, "Campaign builder view includes Deliverability Health Meter container");
assertCustom(strpos($createCampView, 'meter-circle') !== false, "Campaign builder view renders circular SVG Deliverability Health Gauge");
assertCustom(strpos($createCampView, 'testPreviewModal') !== false, "Campaign builder view includes 1-Click Test Broadcast Preview modal");
assertCustom(strpos($createCampView, 'toggle-spam-highlight') !== false, "Preview modal provides toggle to highlight spam trigger words");
assertCustom(strpos($createCampView, 'btn-view-mobile') !== false, "Preview modal provides Desktop vs Mobile 375px switcher");

echo "\n=======================================================\n";
echo "SUMMARY: {$passed} PASSED, {$failed} FAILED\n";
echo "=======================================================\n";

if ($failed > 0) {
    exit(1);
}
exit(0);

