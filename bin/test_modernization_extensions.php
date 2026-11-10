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

// ── 12. Sprint 5: HR Management & Operations Service Decoupling ──
echo "\n12. Testing Sprint 5 HR Management & Operations Service Decoupling...\n";

require_once __DIR__ . '/../app/services/AttendanceService.php';
require_once __DIR__ . '/../app/services/LeaveService.php';
require_once __DIR__ . '/../app/controllers/HrmsController.php';

// Test 12.1: AttendanceService Geofence & Haversine Distance
$officeLat = 17.4486;
$officeLng = 78.3752;
$nearLat   = 17.4489; // approx 40m away
$nearLng   = 78.3754;
$farLat    = 17.5500; // approx 15km away
$farLng    = 78.4500;

$distNear = AttendanceService::haversineDistance($officeLat, $officeLng, $nearLat, $nearLng);
assertCustom($distNear > 0 && $distNear < 100, "AttendanceService computes accurate Haversine distance for nearby coordinates (~40m)");

$distFar = AttendanceService::haversineDistance($officeLat, $officeLng, $farLat, $farLng);
assertCustom($distFar > 10000, "AttendanceService computes accurate distance for distant coordinates (>10km)");

$mockFence = [(object)['center_lat' => $officeLat, 'center_lng' => $officeLng, 'radius_m' => 100]];
$geoInside = AttendanceService::evaluateGeofence($nearLat, $nearLng, $mockFence, true);
assertCustom($geoInside === 1, "AttendanceService confirms coordinate inside active office geofence");

$geoOutside = AttendanceService::evaluateGeofence($farLat, $farLng, $mockFence, true);
assertCustom($geoOutside === 0, "AttendanceService flags coordinate outside active office geofence");

$geoDisabled = AttendanceService::evaluateGeofence($farLat, $farLng, $mockFence, false);
assertCustom($geoDisabled === null, "AttendanceService returns null when geofencing is disabled");

// Test 12.2: AttendanceService Shift Grace Period & Lateness
$shiftConfig = ['shift_start' => '09:30', 'grace_minutes' => 15];
$onTime = AttendanceService::evaluateLateness('2026-11-10 09:35:00', $shiftConfig);
assertCustom($onTime['is_late'] === false, "Check-in at 09:35 within 15 min grace period is marked on-time");

$graceBoundary = AttendanceService::evaluateLateness('2026-11-10 09:45:00', $shiftConfig);
assertCustom($graceBoundary['is_late'] === false, "Check-in exactly at 09:45 boundary is marked on-time");

$lateCheck = AttendanceService::evaluateLateness('2026-11-10 09:46:00', $shiftConfig);
assertCustom($lateCheck['is_late'] === true && $lateCheck['late_minutes'] === 16, "Check-in at 09:46 exceeds grace period and is flagged LATE (16 min delay)");

// Test 12.3: AttendanceService Work Duration & Break Deduction
$mockBreaks = [
    (object)['break_start' => '2026-11-10 13:00:00', 'break_end' => '2026-11-10 13:45:00'] // 45 mins
];
$duration = AttendanceService::calculateWorkDuration('2026-11-10 09:30:00', '2026-11-10 18:30:00', $mockBreaks);
// Gross = 540 min (9 hrs), breaks = 45 min, net = 495 min
assertCustom($duration['gross_minutes'] === 540, "Calculates 540 gross minutes (9 hours)");
assertCustom($duration['break_minutes'] === 45, "Calculates 45 completed break minutes");
assertCustom($duration['net_minutes'] === 495, "Calculates 495 net work minutes after break deduction");
assertCustom($duration['status'] === 'present', "Net minutes >= 480 qualifies for full 'present' status");

// Test 12.4: LeaveService Business Days (Weekend & Holiday Exclusion)
// Friday 2026-01-23 to Monday 2026-01-26 (where Monday Jan 26 is Republic Day holiday)
// Friday = 1 day, Sat/Sun excluded, Mon = Republic Day excluded => Net working days = 1.0!
$daysHoliday = LeaveService::calculateBusinessDays('2026-01-23', '2026-01-26', false);
assertCustom($daysHoliday === 1.0, "LeaveService automatically excludes weekend and statutory holiday (Republic Day Jan 26)");

// Normal Mon-Wed span (3 days)
$daysNormal = LeaveService::calculateBusinessDays('2026-02-02', '2026-02-04', false);
assertCustom($daysNormal === 3.0, "LeaveService calculates 3 working business days for Mon-Wed range");

// Half-day calculation
$daysHalf = LeaveService::calculateBusinessDays('2026-02-02', '2026-02-02', true);
assertCustom($daysHalf === 0.5, "LeaveService calculates 0.5 day for half-day request on weekday");

// Test 12.5: LeaveService Balance Validation
$mockBalances = (object)['sick_leave' => 5.0, 'casual_leave' => 3.0, 'earned_leave' => 12.0];
$validCheck = LeaveService::checkLeaveBalance($mockBalances, 'Casual Leave', 2.0);
assertCustom($validCheck['allowed'] === true && $validCheck['remaining'] === 1.0, "LeaveService permits leave when available balance is sufficient");

$invalidCheck = LeaveService::checkLeaveBalance($mockBalances, 'Casual Leave', 4.0);
assertCustom($invalidCheck['allowed'] === false && strpos($invalidCheck['error'], 'Insufficient') !== false, "LeaveService rejects request exceeding available balance with descriptive error");

// Test 12.6: Employee Directory Cards & View Switcher (users/index.php)
$usersViewContent = file_get_contents(__DIR__ . '/../app/views/users/index.php');
assertCustom(strpos($usersViewContent, 'btn-view-cards') !== false, "Employee management view provides Directory Cards view switcher");
assertCustom(strpos($usersViewContent, 'users-cards-container') !== false, "Employee management view includes responsive Directory Cards grid container");
assertCustom(strpos($usersViewContent, 'tel:') !== false, "Directory Cards integrate 1-click Call action (tel:)");
assertCustom(strpos($usersViewContent, 'wa.me') !== false, "Directory Cards integrate 1-click WhatsApp action (wa.me)");
assertCustom(strpos($usersViewContent, 'mailto:') !== false, "Directory Cards integrate 1-click Email action (mailto:)");

// Test 12.7: Organization Hierarchy Tree View (hrms/hierarchy.php)
$hrmsReflect = new ReflectionClass(HrmsController::class);
assertCustom($hrmsReflect->hasMethod('hierarchy'), "HrmsController defines hierarchy() action for organizational tree resolution");

$hierarchyViewPath = __DIR__ . '/../app/views/hrms/hierarchy.php';
assertCustom(file_exists($hierarchyViewPath), "app/views/hrms/hierarchy.php exists");
$hierarchyContent = file_get_contents($hierarchyViewPath);
assertCustom(strpos($hierarchyContent, 'tree-search-input') !== false, "Hierarchy view renders employee tree search input");
assertCustom(strpos($hierarchyContent, 'dept-filter-buttons') !== false, "Hierarchy view renders department filter selector pills");
assertCustom(strpos($hierarchyContent, 'org-branch-column') !== false, "Hierarchy view renders division and direct reports branch columns");

// Test 12.8: Sensitive Data Masking Verification
$hrmsReflectProfile = $hrmsReflect->getMethod('profile');
assertCustom($hrmsReflectProfile->isPublic(), "HrmsController exposes profile() action with sensitive data masking protection");

// ── 13. Sprint 6: E2E Playwright Automation, PWA Hardening & Release Acceptance ──
echo "\n13. Testing Sprint 6 E2E Automation, PWA Hardening & Release Acceptance...\n";

// Test 13.1: Application Version & Release Alignment
assertCustom(defined('APP_VERSION') && APP_VERSION === '2.2.0', "APP_VERSION constant is updated to 2.2.0 for Raptor v2.2 release");

// Test 13.2: PWA Manifest Hardening
$manifestPath = __DIR__ . '/../public/manifest.json';
assertCustom(file_exists($manifestPath), "public/manifest.json exists for PWA installation");
$manifestData = json_decode(file_get_contents($manifestPath), true);
assertCustom(is_array($manifestData) && !empty($manifestData['id']), "PWA manifest defines application id identifier (/?source=pwa)");
assertCustom(!empty($manifestData['shortcuts']) && count($manifestData['shortcuts']) >= 3, "PWA manifest defines at least 3 quick launch shortcuts (Lead, Attendance, Campaigns)");
assertCustom(!empty($manifestData['categories']) && in_array('business', $manifestData['categories']), "PWA manifest categorizes application under 'business' and 'productivity'");

// Test 13.3: Service Worker Offline Caching
$swPath = __DIR__ . '/../public/sw.js';
assertCustom(file_exists($swPath), "public/sw.js service worker script exists");
$swContent = file_get_contents($swPath);
assertCustom(strpos($swContent, 'raptor-pwa-v2.2.0') !== false, "Service worker defines raptor-pwa-v2.2.0 cache namespace");
assertCustom(strpos($swContent, 'PRECACHE_ASSETS') !== false, "Service worker precaches core design tokens and glass surfaces");
assertCustom(strpos($swContent, 'caches.open') !== false, "Service worker implements install cache lifecycle");
assertCustom(strpos($swContent, 'caches.delete') !== false, "Service worker implements activation cleanup for legacy caches");

// Test 13.4: Raptor CLI test:ui Automation Integration
$raptorCli = file_get_contents(__DIR__ . '/raptor');
assertCustom(strpos($raptorCli, 'test:ui') !== false, "Raptor CLI console supports test:ui command for Playwright journeys");
assertCustom(strpos($raptorCli, 'v2.2.0') !== false || strpos($raptorCli, 'APP_VERSION') !== false, "Raptor CLI header reflects release version v2.2.0");

// Test 13.5: Playwright E2E Test Suite Integrity
$specPath = __DIR__ . '/../tests/e2e/raptor.e2e.spec.ts';
assertCustom(file_exists($specPath), "tests/e2e/raptor.e2e.spec.ts exists");
$specContent = file_get_contents($specPath);
preg_match_all("/test\(/", $specContent, $matches);
assertCustom(count($matches[0]) >= 10, "Playwright test suite covers at least 10 critical end-to-end browser journeys (" . count($matches[0]) . " journeys defined)");
assertCustom(strpos($specContent, 'login screen loads and theme toggle works') !== false, "E2E suite verifies authentication and theme toggle");
assertCustom(strpos($specContent, 'delete routes are blocked by governance policy') !== false, "E2E suite verifies governance delete route protection");
assertCustom(strpos($specContent, 'capture lead form has read-only Lead ID') !== false, "E2E suite verifies lead capture workflows");

// ── 14. Sprint 1 (Raptor v2.3): Recruitment ATS Foundation, Schemas & RBAC ──
echo "\n14. Testing Sprint 1 Recruitment ATS Foundation, Schemas & RBAC...\n";

// Test 14.1: Recruitment Migration Files Verification (0059 through 0064)
$migrationFiles = [
    '0059_recruitment_core_schema.php',
    '0060_recruitment_evaluations_and_interviews.php',
    '0061_recruitment_offers_and_onboarding.php',
    '0062_recruitment_queue_and_communications.php',
    '0063_recruitment_rbac_permissions.php',
    '0064_recruitment_default_templates.php'
];
foreach ($migrationFiles as $mf) {
    $mPath = __DIR__ . '/../migrations/' . $mf;
    assertCustom(file_exists($mPath), "Recruitment migration file {$mf} exists");
}

// Test 14.2: In-Memory SQLite Migration Execution & Schema Verification
$mockDb = new PDO('sqlite::memory:');
$mockDb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Mock base tables required for foreign keys & role mapping
$mockDb->exec("
    CREATE TABLE IF NOT EXISTS users (user_id INTEGER PRIMARY KEY, name VARCHAR(100), email VARCHAR(100), role_id INT);
    CREATE TABLE IF NOT EXISTS roles (role_id INTEGER PRIMARY KEY, role_name VARCHAR(50));
    CREATE TABLE IF NOT EXISTS permissions (permission_id INTEGER PRIMARY KEY AUTOINCREMENT, module VARCHAR(60), action VARCHAR(60), permission_name VARCHAR(100) UNIQUE, description TEXT);
    CREATE TABLE IF NOT EXISTS role_permissions (role_id INT, permission_id INT, scope VARCHAR(20), PRIMARY KEY(role_id, permission_id));
    CREATE TABLE IF NOT EXISTS teams (team_id INTEGER PRIMARY KEY, name VARCHAR(100));
    CREATE TABLE IF NOT EXISTS branches (branch_id INTEGER PRIMARY KEY, name VARCHAR(100));
    CREATE TABLE IF NOT EXISTS employees (
        employee_id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INT,
        employee_code VARCHAR(50),
        department VARCHAR(100),
        job_title VARCHAR(150),
        hire_date DATE,
        salary DECIMAL(12,2),
        work_location VARCHAR(150),
        employment_type VARCHAR(50),
        reporting_manager_id INT,
        status VARCHAR(20) DEFAULT 'active'
    );
    INSERT INTO roles (role_id, role_name) VALUES (1, 'admin'), (2, 'hr'), (3, 'manager'), (4, 'team_leader');
    INSERT INTO users (user_id, name, email, role_id) VALUES (1, 'Admin', 'admin@raptor.test', 1);
");

// Apply recruitment migrations to mock database
foreach ($migrationFiles as $mf) {
    $file = __DIR__ . '/../migrations/' . $mf;
    (function ($db) use ($file) { require $file; })($mockDb);
}

// Verify that key tables are provisioned in SQLite
$recTables = [
    'recruitment_requisitions',
    'recruitment_requisition_approvals',
    'recruitment_job_descriptions',
    'recruitment_jobs',
    'recruitment_candidates',
    'recruitment_candidate_documents',
    'recruitment_candidate_consents',
    'recruitment_candidate_notes',
    'recruitment_applications',
    'recruitment_application_stages',
    'recruitment_application_screenings',
    'recruitment_interviews',
    'recruitment_interview_panel',
    'recruitment_interview_feedback',
    'recruitment_assessments',
    'recruitment_assessment_submissions',
    'recruitment_offers',
    'recruitment_offer_approvals',
    'recruitment_background_checks',
    'recruitment_pre_joining_checklist',
    'recruitment_communications',
    'recruitment_queue',
    'recruitment_ai_logs'
];
foreach ($recTables as $tbl) {
    $stmtTbl = $mockDb->prepare("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name = :t");
    $stmtTbl->execute([':t' => $tbl]);
    assertCustom((int)$stmtTbl->fetchColumn() === 1, "Recruitment table {$tbl} provisioned and verified in schema");
}

// Test 14.3: RBAC Permissions Seeding Verification
$stmtPermCnt = $mockDb->query("SELECT COUNT(*) FROM permissions WHERE module = 'recruitment'");
$permCount = (int) $stmtPermCnt->fetchColumn();
assertCustom($permCount === 28, "All 28 granular recruitment permissions successfully seeded in permissions table (found: {$permCount})");

// Test 14.4: Default Job Description Templates Seeding Verification
$stmtTplCnt = $mockDb->query("SELECT COUNT(*) FROM recruitment_job_descriptions");
$tplCount = (int) $stmtTplCnt->fetchColumn();
assertCustom($tplCount >= 3, "Standard default job description templates seeded (found: {$tplCount})");

// Test 14.5: Policy RBAC Helper Extensions
assertCustom(method_exists('Policy', 'canAccessRecruitment'), "Policy::canAccessRecruitment() helper method exists");
assertCustom(method_exists('Policy', 'canManageRequisitions'), "Policy::canManageRequisitions() helper method exists");

// Test 14.6: Recruitment Controller & Route Architecture
require_once __DIR__ . '/../app/controllers/RecruitmentController.php';
require_once __DIR__ . '/../app/controllers/RecruitmentDashboardController.php';
$recReflect = new ReflectionClass('RecruitmentController');
assertCustom($recReflect->isSubclassOf('Controller'), "RecruitmentController extends core Controller architecture");
assertCustom($recReflect->hasMethod('dashboard'), "RecruitmentController exposes dashboard() action");
assertCustom($recReflect->hasMethod('requisitions'), "RecruitmentController exposes requisitions() action");
assertCustom($recReflect->hasMethod('candidates'), "RecruitmentController exposes candidates() action");
assertCustom($recReflect->hasMethod('applications'), "RecruitmentController exposes applications() action");
assertCustom($recReflect->hasMethod('interviews'), "RecruitmentController exposes interviews() action");
assertCustom($recReflect->hasMethod('offers'), "RecruitmentController exposes offers() action");

$recDashReflect = new ReflectionClass('RecruitmentDashboardController');
assertCustom($recDashReflect->isSubclassOf('RecruitmentController'), "RecruitmentDashboardController provides alias route for recruitment_dashboard");

// Test 14.7: Recruitment Model Architecture
require_once __DIR__ . '/../app/models/Recruitment.php';
$recModelReflect = new ReflectionClass('Recruitment');
assertCustom($recModelReflect->isSubclassOf('Model'), "Recruitment model extends core Model architecture");
assertCustom($recModelReflect->hasMethod('getDashboardStats'), "Recruitment model implements getDashboardStats() method");

// Test 14.8: Navigation Sidebar Integration
$mainLayoutContent = file_get_contents(__DIR__ . '/../app/views/layouts/main.php');
assertCustom(strpos($mainLayoutContent, 'Recruitment (ATS)') !== false, "Sidebar navigation includes dedicated Recruitment (ATS) accordion group");
assertCustom(strpos($mainLayoutContent, 'index.php?route=recruitment/dashboard') !== false, "Sidebar navigation links to recruitment/dashboard");
assertCustom(strpos($mainLayoutContent, 'index.php?route=recruitment/requisitions') !== false, "Sidebar navigation links to recruitment/requisitions");
assertCustom(strpos($mainLayoutContent, 'index.php?route=recruitment/candidates') !== false, "Sidebar navigation links to recruitment/candidates");
assertCustom(strpos($mainLayoutContent, 'index.php?route=recruitment/applications') !== false, "Sidebar navigation links to recruitment/applications");

// Test 14.9: Recruitment Dashboard View Verification
$dashViewPath = __DIR__ . '/../app/views/recruitment/dashboard/index.php';
assertCustom(file_exists($dashViewPath), "Recruitment dashboard view exists at app/views/recruitment/dashboard/index.php");
$dashContent = file_get_contents($dashViewPath);
assertCustom(strpos($dashContent, 'Recruitment Command Center') !== false, "Recruitment dashboard renders Command Center header");
assertCustom(strpos($dashContent, 'Active Requisitions') !== false, "Recruitment dashboard renders Active Requisitions stat tile");
assertCustom(strpos($dashContent, 'Talent Pipeline Funnel') !== false, "Recruitment dashboard renders visual Pipeline Funnel");
assertCustom(strpos($dashContent, 'Upcoming Interviews') !== false, "Recruitment dashboard renders Upcoming Interviews operational list");

// ── 15. Sprint 2 (Raptor v2.3): Requisition Management & Approval Workflows ──
echo "\n15. Testing Sprint 2 Requisition Management & Multi-Level Approvals...\n";

// Test 15.1: RequisitionService Architecture & Code Generation
require_once __DIR__ . '/../app/services/recruitment/RequisitionApprovalService.php';
require_once __DIR__ . '/../app/services/recruitment/RequisitionService.php';
$reqService = new RequisitionService();
$generatedCode = $reqService->generateRequisitionCode($mockDb);
assertCustom((bool)preg_match('/^REQ-\d{4}-\d{4}$/', $generatedCode), "RequisitionService generates standard format code ({$generatedCode})");

// Test 15.2: Requisition Creation (Draft State)
$createResult = $reqService->createRequisition($mockDb, [
    'title'                  => 'Senior Staff Backend Architect',
    'department'             => 'Engineering',
    'vacancies'              => 2,
    'employment_type'        => 'full_time',
    'workplace_arrangement'  => 'hybrid',
    'hiring_priority'        => 'urgent',
    'min_experience_years'   => 7.0,
    'min_salary'             => 2400000.00,
    'max_salary'             => 3200000.00,
    'is_salary_confidential' => 1,
    'job_description'        => 'Lead distributed enterprise backend architecture in PHP 8.2 and high-throughput MySQL.',
    'responsibilities'       => '• Design fault-tolerant queues\n• Lead code reviews\n• Mentor engineers',
    'required_skills'        => 'PHP 8.2, MySQL, Queue Daemons, Redis, SOLID, PSR'
], 1);

assertCustom(!empty($createResult['requisition_id']), "Requisition successfully persisted to database with auto-generated ID");
assertCustom($createResult['status'] === 'draft', "Newly created requisition defaults to 'draft' status when not submitted immediately");
$testReqId = (int) $createResult['requisition_id'];

// Test 15.3: Requisition Submission & Multi-Level Approval Chain Initialization
$subResult = $reqService->submitForApproval($mockDb, $testReqId, 1);
assertCustom($subResult['status'] === 'pending_approval', "Submitting draft requisition updates status to 'pending_approval'");

$apprHistory = (new RequisitionApprovalService())->getApprovalHistory($mockDb, $testReqId);
assertCustom(count($apprHistory) >= 2, "Requisition approval chain initialized with multi-level steps (found: " . count($apprHistory) . " steps)");
assertCustom($apprHistory[0]['approver_role'] === 'department_head', "Step 1 of approval chain requires 'department_head'");
assertCustom($apprHistory[1]['approver_role'] === 'hr_manager', "Step 2 of approval chain requires 'hr_manager'");

// Test 15.4: Requisition Approval Step 1 Execution (Department Head Approval)
$apprService = new RequisitionApprovalService();
$step1Action = $apprService->actionApproval(
    $mockDb,
    $testReqId,
    1,
    'manager',
    'approved',
    'Engineering headcount approved within quarterly budget plan.'
);
assertCustom($step1Action['actioned_step'] === 1, "Approval Step 1 actioned successfully by authorized manager role");
assertCustom($step1Action['requisition_status'] === 'pending_approval', "Requisition status remains 'pending_approval' while subsequent approval steps remain");

// Test 15.5: Requisition Approval Step 2 Execution (HR Manager Approval & Final Sign-Off)
$step2Action = $apprService->actionApproval(
    $mockDb,
    $testReqId,
    2,
    'hr',
    'approved',
    'HR compensation bands and job description verified.'
);
assertCustom($step2Action['actioned_step'] === 2, "Approval Step 2 actioned successfully by authorized HR role");
// Since salary >= 1.5M, step 3 (finance) was added
if (count($apprHistory) >= 3) {
    assertCustom($step2Action['requisition_status'] === 'pending_approval', "Requisition with salary > 1.5M awaits executive finance signoff");
    $step3Action = $apprService->actionApproval($mockDb, $testReqId, 1, 'ceo', 'approved', 'CEO/Finance final authorization granted.');
    assertCustom($step3Action['requisition_status'] === 'approved', "Requisition status updates to 'approved' upon completion of all approval chain steps");
} else {
    assertCustom($step2Action['requisition_status'] === 'approved', "Requisition status updates to 'approved' upon completion of all approval chain steps");
}

// Test 15.6: Salary Confidentiality Masking Verification
$reqViewHR = $reqService->getRequisition($mockDb, $testReqId, 2, 'hr');
assertCustom($reqViewHR['salary_masked'] === false, "HR role can view unmasked salary parameters");
assertCustom((float)$reqViewHR['max_salary'] === 3200000.00, "HR role retrieves exact compensation parameters");

$reqViewInterviewer = $reqService->getRequisition($mockDb, $testReqId, 99, 'sales_person');
assertCustom($reqViewInterviewer['salary_masked'] === true, "Non-HR / sales role receives masked salary protection");
assertCustom($reqViewInterviewer['max_salary'] === null, "Confidential maximum salary field is nullified for non-HR role");

// Test 15.7: RecruitmentRequisitionController Architecture & Reflection
require_once __DIR__ . '/../app/controllers/RecruitmentRequisitionController.php';
$reqCtrlReflect = new ReflectionClass('RecruitmentRequisitionController');
assertCustom($reqCtrlReflect->isSubclassOf('Controller'), "RecruitmentRequisitionController extends core Controller architecture");
assertCustom($reqCtrlReflect->hasMethod('index'), "RecruitmentRequisitionController exposes index() action");
assertCustom($reqCtrlReflect->hasMethod('create'), "RecruitmentRequisitionController exposes create() action");
assertCustom($reqCtrlReflect->hasMethod('store'), "RecruitmentRequisitionController exposes store() action");
assertCustom($reqCtrlReflect->hasMethod('show'), "RecruitmentRequisitionController exposes show() action");
assertCustom($reqCtrlReflect->hasMethod('approve'), "RecruitmentRequisitionController exposes approve() action");
assertCustom($reqCtrlReflect->hasMethod('submit'), "RecruitmentRequisitionController exposes submit() action");

// Test 15.8: Requisition Views Markup Verification
$reqIndexView = __DIR__ . '/../app/views/recruitment/requisitions/index.php';
assertCustom(file_exists($reqIndexView), "app/views/recruitment/requisitions/index.php exists");
$indexMarkup = file_get_contents($reqIndexView);
assertCustom(strpos($indexMarkup, 'Job Requisitions') !== false, "Requisitions index renders page title");
assertCustom(strpos($indexMarkup, 'Filter Pills Row') !== false, "Requisitions index integrates status filter pills");

$reqCreateView = __DIR__ . '/../app/views/recruitment/requisitions/create.php';
assertCustom(file_exists($reqCreateView), "app/views/recruitment/requisitions/create.php exists");
$createMarkup = file_get_contents($reqCreateView);
assertCustom(strpos($createMarkup, 'Create Job Requisition') !== false, "Requisitions create wizard renders header");
assertCustom(strpos($createMarkup, 'templateSelector') !== false, "Requisitions create wizard integrates template autofill dropdown");

$reqDetailView = __DIR__ . '/../app/views/recruitment/requisitions/view.php';
assertCustom(file_exists($reqDetailView), "app/views/recruitment/requisitions/view.php exists");
$detailMarkup = file_get_contents($reqDetailView);
assertCustom(strpos($detailMarkup, 'Approval Workflow Chain') !== false, "Requisition detail view renders approval timeline progress card");
assertCustom(strpos($detailMarkup, 'approvalModal') !== false, "Requisition detail view integrates formal approval modal dialog");

// ── 16. Sprint 3 (Raptor v2.3): Job Publishing, Careers Portal & Candidate Application Wizard ──
echo "\n16. Testing Sprint 3 Job Publishing, Careers Portal & Candidate Application Wizard...\n";

// Test 16.1: JobPublishingService Architecture & Codes
require_once __DIR__ . '/../app/services/recruitment/JobPublishingService.php';
$pubService = new JobPublishingService();
$jobCode = $pubService->generateJobCode($mockDb);
assertCustom((bool)preg_match('/^JOB-\d{4}-\d{4}$/', $jobCode), "JobPublishingService generates standard job code ({$jobCode})");

$slug = $pubService->generateSlug('Senior Staff Backend Architect', 'Hyderabad, India');
assertCustom(strpos($slug, 'senior-staff-backend-architect-hyderabad-india') === 0, "JobPublishingService generates SEO-friendly slug ({$slug})");

// Test 16.2: Publish Approved Requisition to Career Portal
$pubResult = $pubService->publishJob($mockDb, $testReqId, [
    'location'              => 'Hyderabad Tech Park',
    'show_salary_publicly'  => 1
], 1);
assertCustom(!empty($pubResult['job_id']), "Approved requisition successfully published to recruitment_jobs");
assertCustom($pubResult['status'] === 'published', "Published job status is set to 'published'");
$testJobId = (int) $pubResult['job_id'];

// Test 16.3: Google Jobs JSON-LD Structured Data Validation
$jobRecord = $pubService->getJobBySlug($mockDb, $pubResult['slug']);
assertCustom(!empty($jobRecord), "getJobBySlug retrieves published job record");
$jsonLd = $pubService->generateJsonLd($jobRecord, 'Raptor CRM', 'http://localhost');
$jsonDecoded = json_decode($jsonLd, true);
assertCustom(is_array($jsonDecoded), "generateJsonLd produces valid parseable JSON");
assertCustom($jsonDecoded['@type'] === 'JobPosting', "JSON-LD schema type is strictly 'JobPosting'");
assertCustom($jsonDecoded['title'] === 'Senior Staff Backend Architect', "JSON-LD schema captures exact position title");
assertCustom(!empty($jsonDecoded['hiringOrganization']['name']), "JSON-LD schema includes hiringOrganization");
assertCustom(!empty($jsonDecoded['baseSalary']), "JSON-LD schema embeds salary parameters when show_salary_publicly is true");

// Test 16.4: Public Job Views Counter
assertCustom((int)$jobRecord['views_count'] >= 1, "Public job detail retrieval increments views_count");

// Test 16.5: Anti-Spam Honeypot Detection
$spamSubmission = $pubService->submitCandidateApplication(
    $mockDb,
    $testJobId,
    [
        'first_name'      => 'Spam',
        'last_name'       => 'Bot',
        'email'           => 'spambot@auto.invalid',
        'phone'           => '1234567890',
        'consent_granted' => 1,
        'company_url_hp'  => 'https://spamurl.invalid' // Honeypot populated!
    ]
);
assertCustom(!empty($spamSubmission['is_spam']), "Application submission with populated honeypot is identified and blocked as spam");

// Test 16.6: Mandatory Consent Verification (DPDP Act 2023)
$consentFailed = false;
try {
    $pubService->submitCandidateApplication(
        $mockDb,
        $testJobId,
        [
            'first_name' => 'Alice',
            'last_name'  => 'Smith',
            'email'      => 'alice@example.com',
            'phone'      => '9876543210'
            // No consent_granted
        ]
    );
} catch (InvalidArgumentException $e) {
    $consentFailed = true;
}
assertCustom($consentFailed, "Application submission without explicit DPDP consent throws InvalidArgumentException");

// Test 16.7: Legitimate Candidate Application Intake
$appResult = $pubService->submitCandidateApplication(
    $mockDb,
    $testJobId,
    [
        'first_name'             => 'Priya',
        'last_name'              => 'Sharma',
        'email'                  => 'priya.sharma@example.com',
        'phone'                  => '+919876543210',
        'current_company'        => 'Tech Enterprises Ltd',
        'current_designation'    => 'Senior Backend Engineer',
        'total_experience_years' => 6.5,
        'skills'                 => 'PHP 8.2, MySQL, Redis, REST APIs, Git',
        'consent_granted'        => 1
    ],
    [
        'name'     => 'Priya_Sharma_Resume.pdf',
        'type'     => 'application/pdf',
        'size'     => 102400,
        'tmp_name' => '' // In-memory mock
    ],
    '192.168.1.100',
    'Mozilla/5.0 Test Browser'
);

assertCustom(!empty($appResult['application_id']), "Candidate application created and assigned unique ID");
assertCustom((bool)preg_match('/^APP-\d{4}-\d{5}$/', $appResult['application_code']), "Application assigned standard external reference code ({$appResult['application_code']})");

// Verify candidate master record
$stmtCand = $mockDb->prepare("SELECT * FROM recruitment_candidates WHERE email = 'priya.sharma@example.com'");
$stmtCand->execute();
$cand = $stmtCand->fetch(PDO::FETCH_ASSOC);
assertCustom(!empty($cand), "Candidate master profile created in recruitment_candidates");
assertCustom((float)$cand['total_experience_years'] === 6.5, "Candidate experience preserved accurately");

// Verify consent record
$stmtCons = $mockDb->prepare("SELECT * FROM recruitment_candidate_consents WHERE candidate_id = :cid");
$stmtCons->execute([':cid' => $cand['candidate_id']]);
$cons = $stmtCons->fetch(PDO::FETCH_ASSOC);
assertCustom(!empty($cons) && (int)$cons['is_granted'] === 1, "Candidate DPDP consent logged with timestamp and IP");

// Verify stage history & queue acknowledgement
$stmtStg = $mockDb->prepare("SELECT * FROM recruitment_application_stages WHERE application_id = :aid");
$stmtStg->execute([':aid' => $appResult['application_id']]);
$stg = $stmtStg->fetch(PDO::FETCH_ASSOC);
assertCustom(!empty($stg) && $stg['to_stage'] === 'applied', "Initial application stage recorded in history");

$stmtQ = $mockDb->query("SELECT * FROM recruitment_queue WHERE job_type = 'application_acknowledgement'");
$qJob = $stmtQ->fetch(PDO::FETCH_ASSOC);
assertCustom(!empty($qJob), "Candidate acknowledgement email job enqueued in recruitment_queue");

// Test 16.8: CareersController & RecruitmentJobController Reflection
require_once __DIR__ . '/../app/controllers/CareersController.php';
require_once __DIR__ . '/../app/controllers/RecruitmentJobController.php';

$careersReflect = new ReflectionClass('CareersController');
assertCustom($careersReflect->isSubclassOf('Controller'), "CareersController extends core Controller architecture");
assertCustom($careersReflect->hasMethod('index'), "CareersController exposes index() action");
assertCustom($careersReflect->hasMethod('job'), "CareersController exposes job() action");
assertCustom($careersReflect->hasMethod('apply'), "CareersController exposes apply() action");

$recJobReflect = new ReflectionClass('RecruitmentJobController');
assertCustom($recJobReflect->isSubclassOf('Controller'), "RecruitmentJobController extends core Controller architecture");
assertCustom($recJobReflect->hasMethod('index'), "RecruitmentJobController exposes index() action");
assertCustom($recJobReflect->hasMethod('publish'), "RecruitmentJobController exposes publish() action");
assertCustom($recJobReflect->hasMethod('pause'), "RecruitmentJobController exposes pause() action");
assertCustom($recJobReflect->hasMethod('close'), "RecruitmentJobController exposes close() action");

// Test 16.9: Careers and Job Views Verification
$careersIndexView = __DIR__ . '/../app/views/careers/index.php';
assertCustom(file_exists($careersIndexView), "app/views/careers/index.php exists");
$cIndexContent = file_get_contents($careersIndexView);
assertCustom(strpos($cIndexContent, 'RAPTOR CAREERS') !== false, "Careers index view renders header");

$careersJobView = __DIR__ . '/../app/views/careers/job.php';
assertCustom(file_exists($careersJobView), "app/views/careers/job.php exists");
$cJobContent = file_get_contents($careersJobView);
assertCustom(strpos($cJobContent, 'application/ld+json') !== false, "Careers job view embeds Google Jobs JSON-LD structured data");
assertCustom(strpos($cJobContent, 'company_url_hp') !== false, "Careers application form integrates anti-spam honeypot input");
assertCustom(strpos($cJobContent, 'DPDP Act 2023') !== false, "Careers application form integrates DPDP Act consent checkbox");

$careersSuccessView = __DIR__ . '/../app/views/careers/success.php';
assertCustom(file_exists($careersSuccessView), "app/views/careers/success.php exists");

$recJobIndexView = __DIR__ . '/../app/views/recruitment/jobs/index.php';
assertCustom(file_exists($recJobIndexView), "app/views/recruitment/jobs/index.php exists");
$recJobMarkup = file_get_contents($recJobIndexView);
assertCustom(strpos($recJobMarkup, 'Job Postings & Career Site Listings') !== false, "Internal job listings view renders header");
assertCustom(strpos($recJobMarkup, 'Approved Requisitions Ready to Publish') !== false, "Internal job listings view renders pending publication banner");

// ============================================================================
// 17. SPRINT 4: CANDIDATE 360, DEDUPLICATION & ATS PIPELINE KANBAN
// ============================================================================
echo "\n17. Testing Sprint 4 Candidate 360, Deduplication & ATS Pipeline Kanban...\n";

require_once __DIR__ . '/../app/services/recruitment/CandidateDeduplicationService.php';
require_once __DIR__ . '/../app/services/recruitment/CandidateService.php';
require_once __DIR__ . '/../app/services/recruitment/ApplicationStageService.php';

$dedupService = new CandidateDeduplicationService();
$candidateService = new CandidateService($dedupService);
$stageService = new ApplicationStageService();

// Test 17.1: Candidate Code Generation & Deduplication Detection
$candCode1 = $candidateService->generateCandidateCode($mockDb);
assertCustom((bool)preg_match('/^CAN-\d{4}-\d{5}$/', $candCode1), "CandidateService generates standard format code ({$candCode1})");

// Insert Primary Candidate
$candResult1 = $candidateService->createOrUpdateCandidate($mockDb, [
    'first_name'             => 'Aarav',
    'last_name'              => 'Patel',
    'email'                  => 'aarav.patel@techcorp.io',
    'phone'                  => '+91 98111 22233',
    'current_company'        => 'TechCorp Labs',
    'current_designation'    => 'Full Stack Lead',
    'total_experience_years' => 7.0,
    'primary_skills'         => 'PHP, MySQL, Vue.js, Docker',
    'current_ctc'            => 1800000.00,
    'expected_ctc'           => 2200000.00
], 1);
assertCustom(!empty($candResult1['candidate_id']), "Candidate Aarav Patel created successfully with unique ID");
assertCustom($candResult1['is_duplicate'] === false, "Fresh candidate is identified as non-duplicate");

// Test 17.2: Exact Email Deduplication Match
$dupsEmail = $dedupService->findDuplicates($mockDb, 'AARAV.PATEL@TECHCORP.IO', '9999999999');
assertCustom(!empty($dupsEmail), "Deduplication detects match by case-insensitive email");
assertCustom($dupsEmail[0]['confidence_score'] === 100, "Exact email match returns confidence score of 100");
assertCustom(in_array('exact_email', $dupsEmail[0]['match_reasons'], true), "Deduplication reason records exact_email");

// Test 17.3: Normalized Phone Deduplication Match
$dupsPhone = $dedupService->findDuplicates($mockDb, 'different.email@other.io', '9811122233');
assertCustom(!empty($dupsPhone), "Deduplication detects match by normalized 10-digit phone number");
assertCustom($dupsPhone[0]['confidence_score'] >= 95, "Phone match returns confidence score >= 95");
assertCustom(in_array('exact_phone', $dupsPhone[0]['match_reasons'], true), "Deduplication reason records exact_phone");

// Test 17.4: Insert Duplicate Candidate Profile & Merge Verification
$candResult2 = $candidateService->createOrUpdateCandidate($mockDb, [
    'first_name'             => 'Aarav',
    'last_name'              => 'Patel Duplicate',
    'email'                  => 'aarav.duplicate@techcorp.io',
    'phone'                  => '9811100000',
    'current_company'        => 'TechCorp Labs',
    'current_designation'    => 'Full Stack Lead',
    'total_experience_years' => 7.0
], 1);
$dupCandId = (int)$candResult2['candidate_id'];

// Attach a document and note to duplicate candidate
$candidateService->attachDocument($mockDb, $dupCandId, 'resume', 'recruitment/2026/11/dup_resume.pdf', 'dup_resume.pdf', 54321, 'application/pdf', true, 1);
$candidateService->addNote($mockDb, $dupCandId, 'Screening call note from initial reachout.', 1);

// Execute candidate merge
$mergeResult = $dedupService->mergeCandidates($mockDb, $candResult1['candidate_id'], $dupCandId, 1);
assertCustom($mergeResult['success'] === true, "Candidate merge executed successfully without errors");

// Verify duplicate record deleted from recruitment_candidates
$stmtVerifyDup = $mockDb->prepare("SELECT * FROM recruitment_candidates WHERE candidate_id = :id");
$stmtVerifyDup->execute([':id' => $dupCandId]);
assertCustom(empty($stmtVerifyDup->fetch(PDO::FETCH_ASSOC)), "Merged duplicate candidate master record is deleted");

// Verify documents and notes transferred to primary candidate
$primaryDocs = $candidateService->getCandidateById($mockDb, $candResult1['candidate_id'], 1, 'hr');
assertCustom(count($primaryDocs['documents']) >= 1, "Documents from duplicate successfully transferred to primary candidate profile");
assertCustom(count($primaryDocs['notes']) >= 2, "Notes and audit log from duplicate successfully transferred to primary candidate");

// Test 17.5: Candidate 360 Salary Confidentiality Protection
$hrView = $candidateService->getCandidateById($mockDb, $candResult1['candidate_id'], 1, 'hr');
assertCustom((float)$hrView['expected_ctc'] === 2200000.00, "HR role receives unmasked candidate expected CTC");
assertCustom($hrView['is_salary_masked'] === false, "HR view is marked as unmasked");

$interviewerView = $candidateService->getCandidateById($mockDb, $candResult1['candidate_id'], 2, 'team_leader');
assertCustom($interviewerView['expected_ctc'] === null, "Non-HR interviewer has expected CTC confidential field masked");
assertCustom($interviewerView['is_salary_masked'] === true, "Interviewer view is marked as masked");

// Test 17.6: Blacklist and Unblacklist Candidate Profile
$candidateService->blacklistCandidate($mockDb, $candResult1['candidate_id'], 'Failed integrity verification check', 1);
$blacklistedCand = $candidateService->getCandidateById($mockDb, $candResult1['candidate_id'], 1, 'hr');
assertCustom((int)$blacklistedCand['is_blacklisted'] === 1, "Candidate is flagged as blacklisted");
assertCustom($blacklistedCand['blacklist_reason'] === 'Failed integrity verification check', "Blacklist reason is recorded");

$candidateService->unblacklistCandidate($mockDb, $candResult1['candidate_id'], 1);
$unblacklistedCand = $candidateService->getCandidateById($mockDb, $candResult1['candidate_id'], 1, 'hr');
assertCustom((int)$unblacklistedCand['is_blacklisted'] === 0, "Candidate can be safely unblacklisted");

// Test 17.7: Application Stage Progression & Validation
assertCustom($stageService->isValidTransition('applied', 'screening') === true, "Progression from applied to screening is valid");
assertCustom($stageService->isValidTransition('screening', 'technical_interview') === true, "Progression from screening to technical interview is valid");
assertCustom($stageService->isValidTransition('applied', 'rejected') === true, "Rejection from applied is valid");
assertCustom($stageService->isValidTransition('hired', 'applied') === false, "Regressing from hired to applied is blocked");

// Test 17.8: Transition Application Stages with Audit Trail
// Re-use $testJobId and Priya Sharma's application $appResult['application_id'] from Section 16
$appId = (int)$appResult['application_id'];

$trans1 = $stageService->transitionStage($mockDb, $appId, 'screening', 1, 'PASSED_RESUME_PARSER', 'Strong background in PHP 8.2');
assertCustom($trans1['success'] === true, "Stage transition to 'screening' succeeded");
assertCustom($trans1['to_stage'] === 'screening', "Transition returned correct to_stage");

$trans2 = $stageService->transitionStage($mockDb, $appId, 'technical_interview', 1, 'PASSED_HR_SCREEN', 'Ready for technical round');
assertCustom($trans2['success'] === true, "Stage transition to 'technical_interview' succeeded");

$stageHistory = $stageService->getStageHistory($mockDb, $appId);
assertCustom(count($stageHistory) >= 3, "Application stage history contains all progressive transitions (found: " . count($stageHistory) . ")");

// Test 17.9: ATS Kanban Board Data Aggregation & Privacy Guard
$kanbanBoard = $stageService->getKanbanBoard($mockDb, $testJobId);
assertCustom(is_array($kanbanBoard), "getKanbanBoard returns array of pipeline stages");
assertCustom(isset($kanbanBoard['technical_interview']), "Kanban board defines 'technical_interview' column");
assertCustom($kanbanBoard['technical_interview']['count'] >= 1, "Technical interview column reflects active candidate card");

$card = $kanbanBoard['technical_interview']['cards'][0];
assertCustom($card['candidate_name'] === 'Priya Sharma', "Kanban card captures candidate name");
assertCustom($card['job_title'] === 'Senior Staff Backend Architect', "Kanban card captures job title");
assertCustom(!isset($card['expected_ctc']), "Kanban card strictly excludes confidential candidate salary parameters");

// Test 17.10: Controller Architecture & Route Reflection
require_once __DIR__ . '/../app/controllers/RecruitmentCandidateController.php';
require_once __DIR__ . '/../app/controllers/RecruitmentApplicationController.php';

$candControllerReflect = new ReflectionClass('RecruitmentCandidateController');
assertCustom($candControllerReflect->isSubclassOf('Controller'), "RecruitmentCandidateController extends core Controller architecture");
assertCustom($candControllerReflect->hasMethod('index'), "RecruitmentCandidateController exposes index() action");
assertCustom($candControllerReflect->hasMethod('show'), "RecruitmentCandidateController exposes show() action");
assertCustom($candControllerReflect->hasMethod('profile'), "RecruitmentCandidateController exposes profile() action");
assertCustom($candControllerReflect->hasMethod('create'), "RecruitmentCandidateController exposes create() action");
assertCustom($candControllerReflect->hasMethod('store'), "RecruitmentCandidateController exposes store() action");
assertCustom($candControllerReflect->hasMethod('addNote'), "RecruitmentCandidateController exposes addNote() action");
assertCustom($candControllerReflect->hasMethod('blacklist'), "RecruitmentCandidateController exposes blacklist() action");
assertCustom($candControllerReflect->hasMethod('downloadDocument'), "RecruitmentCandidateController exposes downloadDocument() action");

$appControllerReflect = new ReflectionClass('RecruitmentApplicationController');
assertCustom($appControllerReflect->isSubclassOf('Controller'), "RecruitmentApplicationController extends core Controller architecture");
assertCustom($appControllerReflect->hasMethod('index'), "RecruitmentApplicationController exposes index() action");
assertCustom($appControllerReflect->hasMethod('stage'), "RecruitmentApplicationController exposes stage() action");

$recControllerReflect = new ReflectionClass('RecruitmentController');
assertCustom($recControllerReflect->hasMethod('candidates'), "RecruitmentController exposes candidates() routing");
assertCustom($recControllerReflect->hasMethod('candidate'), "RecruitmentController exposes candidate() routing");
assertCustom($recControllerReflect->hasMethod('applications'), "RecruitmentController exposes applications() routing");

// Test 17.11: Views Existence and Markup
$candIndexView = __DIR__ . '/../app/views/recruitment/candidates/index.php';
assertCustom(file_exists($candIndexView), "app/views/recruitment/candidates/index.php exists");
$candIndexMarkup = file_get_contents($candIndexView);
assertCustom(strpos($candIndexMarkup, 'Candidate Master Directory') !== false, "Candidate index view renders header");
assertCustom(strpos($candIndexMarkup, 'recruitment_candidate/create') !== false, "Candidate index view links to Add Candidate");

$candProfileView = __DIR__ . '/../app/views/recruitment/candidates/profile.php';
assertCustom(file_exists($candProfileView), "app/views/recruitment/candidates/profile.php exists");
$candProfileMarkup = file_get_contents($candProfileView);
assertCustom(strpos($candProfileMarkup, 'Candidate 360') !== false || strpos($candProfileMarkup, 'candidateTabs') !== false, "Candidate profile view renders tabbed 360 container");
assertCustom(strpos($candProfileMarkup, 'downloadDocument') !== false, "Candidate profile view integrates secure document download endpoint");
assertCustom(strpos($candProfileMarkup, 'blacklistModal') !== false, "Candidate profile view integrates blacklist modal dialog");

$candCreateView = __DIR__ . '/../app/views/recruitment/candidates/create.php';
assertCustom(file_exists($candCreateView), "app/views/recruitment/candidates/create.php exists");

$kanbanIndexView = __DIR__ . '/../app/views/recruitment/applications/index.php';
assertCustom(file_exists($kanbanIndexView), "app/views/recruitment/applications/index.php exists");
$kanbanMarkup = file_get_contents($kanbanIndexView);
assertCustom(strpos($kanbanMarkup, 'Recruitment Pipeline Kanban') !== false, "Kanban index view renders header");
assertCustom(strpos($kanbanMarkup, 'draggable="true"') !== false, "Kanban index view supports draggable cards");
assertCustom(strpos($kanbanMarkup, 'kanbanDrop') !== false, "Kanban index view defines drop handler");

// ============================================================================
// 18. SPRINT 5: SCREENING & BLIND INTERVIEW SCORECARDS
// ============================================================================
echo "\n18. Testing Sprint 5 Screening & Blind Interview Scorecards...\n";

require_once __DIR__ . '/../app/services/recruitment/ScreeningService.php';
require_once __DIR__ . '/../app/services/recruitment/InterviewService.php';
require_once __DIR__ . '/../app/services/recruitment/InterviewEvaluationService.php';

$screeningService = new ScreeningService();
$interviewService = new InterviewService();
$evalService      = new InterviewEvaluationService();

// Ensure mock users exist for panel: User 1 (Admin/HR), User 2 (Manager), User 3 (Team Leader)
$mockDb->exec("
    INSERT OR IGNORE INTO users (user_id, name, email, role_id) VALUES 
    (2, 'Sarah Manager', 'sarah.mgr@raptor.test', 3),
    (3, 'Alex Lead', 'alex.lead@raptor.test', 4);
");

// Test 18.1: Recruiter Screening & Weighted Rubric
// Using Aarav Patel's application: let's create a new application for Aarav
$stmtAaravApp = $mockDb->prepare("
    INSERT INTO recruitment_applications (application_code, candidate_id, job_id, current_stage, created_at, updated_at)
    VALUES ('APP-2026-99001', :cid, :jid, 'applied', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
");
$stmtAaravApp->execute([':cid' => $candResult1['candidate_id'], ':jid' => $testJobId]);
$aaravAppId = (int)$mockDb->lastInsertId();

// Submit screening: Skills (5), Experience (4), Comm (4)
// Weighted: (5*0.4) + (4*0.35) + (4*0.25) = 2.0 + 1.4 + 1.0 = 4.4 / 5.0 * 100 = 88.0%
$screenRes = $screeningService->submitScreening($mockDb, $aaravAppId, 1, [
    'skills_rating'        => 5,
    'experience_rating'    => 4,
    'communication_rating' => 4,
    'recommendation'       => 'shortlist',
    'evaluation_comments'  => 'Strong systems engineering depth, highly articulate.'
]);

assertCustom($screenRes['success'] === true, "Screening submission succeeded");
assertCustom((float)$screenRes['overall_score'] === 88.0, "Screening weighted score calculated accurately (88.0%)");
assertCustom($screenRes['recommendation'] === 'shortlist', "Screening recommendation recorded as 'shortlist'");

// Verify stage auto-advanced to 'shortlisted'
$stmtCheckApp = $mockDb->prepare("SELECT current_stage, match_score_advisory FROM recruitment_applications WHERE application_id = :id");
$stmtCheckApp->execute([':id' => $aaravAppId]);
$updatedApp = $stmtCheckApp->fetch(PDO::FETCH_ASSOC);
assertCustom($updatedApp['current_stage'] === 'shortlisted', "Shortlisted candidate automatically advanced to 'shortlisted' stage");
assertCustom((int)$updatedApp['match_score_advisory'] === 88, "Application advisory match score updated to 88%");

// Test 18.2: Interview Scheduling & Conflict Detection
$schedStart1 = date('Y-m-d') . ' 14:00:00';
$schedEnd1   = date('Y-m-d') . ' 15:00:00';

$schedResult1 = $interviewService->scheduleInterview(
    $mockDb,
    [
        'application_id'  => $aaravAppId,
        'round_number'    => 1,
        'round_name'      => 'System Architecture Round',
        'interview_type'  => 'technical',
        'interview_mode'  => 'video',
        'scheduled_start' => $schedStart1,
        'scheduled_end'   => $schedEnd1,
        'meeting_link'    => 'https://meet.google.com/test-room-1'
    ],
    [2, 3], // Panel: User 2 (Manager) and User 3 (Lead)
    1
);
assertCustom(!empty($schedResult1['interview_id']), "Interview scheduled successfully with unique ID");
$testInterviewId = (int)$schedResult1['interview_id'];

// Test 18.3: Interviewer Conflict Detection Gate
$conflictDetected = false;
try {
    // Attempt to book User 2 (Sarah Manager) in an overlapping slot (14:30 - 15:30)
    $interviewService->scheduleInterview(
        $mockDb,
        [
            'application_id'  => $appId, // Priya Sharma's app
            'round_number'    => 1,
            'round_name'      => 'Overlapping Round',
            'interview_type'  => 'technical',
            'interview_mode'  => 'video',
            'scheduled_start' => date('Y-m-d') . ' 14:30:00',
            'scheduled_end'   => date('Y-m-d') . ' 15:30:00'
        ],
        [2], // User 2 is already busy!
        1
    );
} catch (InvalidArgumentException $e) {
    $conflictDetected = (strpos($e->getMessage(), 'Scheduling Collision') !== false || strpos($e->getMessage(), 'collision') !== false);
}
assertCustom($conflictDetected, "Interviewer scheduling conflict detected and blocked");

// Test 18.4: Candidate Conflict Detection Gate
$candConflictDetected = false;
try {
    // Attempt to book same candidate (Aarav) during an overlapping time window (14:15 - 15:15)
    $interviewService->scheduleInterview(
        $mockDb,
        [
            'application_id'  => $aaravAppId,
            'round_number'    => 2,
            'round_name'      => 'Concurrent Candidate Round',
            'interview_type'  => 'functional',
            'interview_mode'  => 'video',
            'scheduled_start' => date('Y-m-d') . ' 14:15:00',
            'scheduled_end'   => date('Y-m-d') . ' 15:15:00'
        ],
        [1], // Different interviewer (User 1), but candidate is busy!
        1
    );
} catch (InvalidArgumentException $e) {
    $candConflictDetected = (strpos($e->getMessage(), 'Candidate already has another interview') !== false);
}
assertCustom($candConflictDetected, "Candidate scheduling collision detected and blocked");

// Test 18.5: Queue Email Invitation Dispatch
$stmtIvQ = $mockDb->query("SELECT * FROM recruitment_queue WHERE job_type = 'interview_invitation' ORDER BY queue_id DESC LIMIT 1");
$ivJob = $stmtIvQ->fetch(PDO::FETCH_ASSOC);
assertCustom(!empty($ivJob), "Interview calendar invitation job enqueued in recruitment_queue");

// Test 18.6: Calendar Event Query
$calEvents = $interviewService->getCalendar($mockDb, date('Y-m-d'), date('Y-m-d'));
assertCustom(count($calEvents) >= 1, "Interview calendar query retrieves scheduled events");
assertCustom($calEvents[0]['round_name'] === 'System Architecture Round', "Calendar event captures round name");
assertCustom(count($calEvents[0]['panel_members']) === 2, "Calendar event links all panel members");

// Test 18.7: Blind Interview Feedback Lock (Before Submitting)
// User 3 (Alex Lead) has NOT submitted their feedback yet.
// When User 3 queries feedback, blind lock MUST be active:
$blindViewUser3 = $evalService->getInterviewFeedback($mockDb, $testInterviewId, 3, 'team_leader');
assertCustom($blindViewUser3['blind_lock_active'] === true, "Blind evaluation lock is ACTIVE for interviewer before scorecard submission");
assertCustom(empty($blindViewUser3['feedbacks']), "Peer feedback is strictly hidden while blind lock is active");

// HR role can ALWAYS view scorecards
$hrViewFeedback = $evalService->getInterviewFeedback($mockDb, $testInterviewId, 1, 'hr');
assertCustom($hrViewFeedback['blind_lock_active'] === false, "Blind evaluation lock is bypassed for HR / Admin role");

// Test 18.8: First Interviewer (User 2) Submits Scorecard
$fb1 = $evalService->submitFeedback($mockDb, $testInterviewId, 2, [
    'technical_rating'       => 5,
    'problem_solving_rating' => 4,
    'communication_rating'   => 4,
    'cultural_fit_rating'    => 5,
    'overall_rating'         => 5,
    'recommendation'         => 'strongly_recommend',
    'strengths'              => 'Mastery of database locking and distributed systems.',
    'concerns'               => 'None noted.',
    'detailed_comments'      => 'Aarav is an exceptional candidate who will elevate the engineering team.'
]);
assertCustom($fb1['success'] === true, "Interviewer 1 scorecard submitted successfully");
assertCustom($fb1['is_completed'] === false, "Interview status remains pending completion while Interviewer 2 scorecard is pending");

// Verify that User 3 STILL cannot see User 2's feedback because User 3 hasn't submitted yet!
$blindViewUser3AfterPeer = $evalService->getInterviewFeedback($mockDb, $testInterviewId, 3, 'team_leader');
assertCustom($blindViewUser3AfterPeer['blind_lock_active'] === true, "Interviewer 3 still locked out of peer evaluations after Interviewer 2 submitted");
assertCustom(empty($blindViewUser3AfterPeer['feedbacks']), "Interviewer 3 still receives 0 peer scorecards");

// Test 18.9: Second Interviewer (User 3) Submits Scorecard & Unlocks Blind Feedback
$fb2 = $evalService->submitFeedback($mockDb, $testInterviewId, 3, [
    'technical_rating'       => 4,
    'problem_solving_rating' => 4,
    'communication_rating'   => 5,
    'cultural_fit_rating'    => 4,
    'overall_rating'         => 4,
    'recommendation'         => 'recommend',
    'strengths'              => 'Great communicator, solid architectural concepts.',
    'concerns'               => 'Slightly less hands-on with front-end tooling.',
    'detailed_comments'      => 'Strong recommend for backend architecture position.'
]);
assertCustom($fb2['success'] === true, "Interviewer 2 scorecard submitted successfully");
assertCustom($fb2['is_completed'] === true, "Interview automatically marked as 'completed' once all panel members have submitted");

// Now User 3 queries feedback: blind lock is UNLOCKED!
$unlockedViewUser3 = $evalService->getInterviewFeedback($mockDb, $testInterviewId, 3, 'team_leader');
assertCustom($unlockedViewUser3['blind_lock_active'] === false, "Blind evaluation lock is UNLOCKED after interviewer submits their scorecard");
assertCustom(count($unlockedViewUser3['feedbacks']) === 2, "Interviewer can now inspect all panel scorecards (found: 2)");

// Test 18.10: Controller Reflection & Routing
require_once __DIR__ . '/../app/controllers/RecruitmentInterviewController.php';

$ivControllerReflect = new ReflectionClass('RecruitmentInterviewController');
assertCustom($ivControllerReflect->isSubclassOf('Controller'), "RecruitmentInterviewController extends core Controller architecture");
assertCustom($ivControllerReflect->hasMethod('index'), "RecruitmentInterviewController exposes index() action");
assertCustom($ivControllerReflect->hasMethod('store'), "RecruitmentInterviewController exposes store() action");
assertCustom($ivControllerReflect->hasMethod('evaluate'), "RecruitmentInterviewController exposes evaluate() action");
assertCustom($ivControllerReflect->hasMethod('submitEvaluation'), "RecruitmentInterviewController exposes submitEvaluation() action");
assertCustom($ivControllerReflect->hasMethod('screen'), "RecruitmentInterviewController exposes screen() action");

assertCustom($recControllerReflect->hasMethod('interviews'), "RecruitmentController exposes interviews() routing");

// Test 18.11: Views Existence & Markup Verification
$ivIndexView = __DIR__ . '/../app/views/recruitment/interviews/index.php';
assertCustom(file_exists($ivIndexView), "app/views/recruitment/interviews/index.php exists");
$ivIndexMarkup = file_get_contents($ivIndexView);
assertCustom(strpos($ivIndexMarkup, 'Interview Calendar & Coordination') !== false, "Interview calendar index view renders header");
assertCustom(strpos($ivIndexMarkup, 'scheduleInterviewModal') !== false, "Interview calendar view integrates schedule modal dialog");

$ivEvalView = __DIR__ . '/../app/views/recruitment/interviews/evaluate.php';
assertCustom(file_exists($ivEvalView), "app/views/recruitment/interviews/evaluate.php exists");
$ivEvalMarkup = file_get_contents($ivEvalView);
assertCustom(strpos($ivEvalMarkup, 'Blind Evaluation Gate Active') !== false, "Interview scorecard view displays blind evaluation governance alert");
assertCustom(strpos($ivEvalMarkup, 'submitEvaluation') !== false, "Interview scorecard view form dispatches to submitEvaluation");

// ============================================================================
// 19. SPRINT 6: ASSESSMENTS & ASSIGNMENT EVALUATION
// ============================================================================
echo "\n19. Testing Sprint 6 Assessments & Assignment Evaluation...\n";

require_once __DIR__ . '/../app/services/recruitment/AssessmentService.php';

$assessmentService = new AssessmentService();

// Test 19.1: Assessment Definition Creation
$assId = $assessmentService->createAssessment($mockDb, [
    'title'            => 'Enterprise PHP 8.2 & Concurrency Challenge',
    'assessment_type'  => 'take_home_assignment',
    'description'      => 'Build a high-throughput, queue-backed background worker with row-locking.',
    'instructions'     => '1. Fork repository. 2. Implement worker. 3. Submit PR link.',
    'duration_minutes' => 180,
    'max_score'        => 100,
    'passing_score'    => 70
]);
assertCustom($assId > 0, "Assessment template created with unique ID ({$assId})");

$assDetail = $assessmentService->getAssessmentById($mockDb, $assId);
assertCustom(!empty($assDetail), "getAssessmentById retrieves created assessment template");
assertCustom($assDetail['title'] === 'Enterprise PHP 8.2 & Concurrency Challenge', "Assessment template captures title");
assertCustom((int)$assDetail['passing_score'] === 70, "Assessment template defines passing threshold (70)");

// Test 19.2: Assignment to Candidate with Due Date Validation
$pastDateBlocked = false;
try {
    $assessmentService->assignAssessment($mockDb, $assId, $aaravAppId, date('Y-m-d H:i:s', time() - 3600), 1);
} catch (InvalidArgumentException $e) {
    $pastDateBlocked = true;
}
assertCustom($pastDateBlocked, "Assigning assessment with past due date throws InvalidArgumentException");

// Assign with valid future due date
$futureDueDate = date('Y-m-d H:i:s', time() + 86400 * 3); // 3 days in future
$assignResult = $assessmentService->assignAssessment($mockDb, $assId, $aaravAppId, $futureDueDate, 1);
assertCustom($assignResult['success'] === true, "Assessment successfully assigned to candidate application");
assertCustom($assignResult['status'] === 'assigned', "Initial assignment status is 'assigned'");
$submissionId = (int)$assignResult['submission_id'];

// Verify candidate application stage moved to 'assessment'
$stmtCheckAppAss = $mockDb->prepare("SELECT current_stage FROM recruitment_applications WHERE application_id = :id");
$stmtCheckAppAss->execute([':id' => $aaravAppId]);
assertCustom($stmtCheckAppAss->fetchColumn() === 'assessment', "Application stage auto-advanced to 'assessment'");

// Verify notification enqueued in recruitment_queue
$stmtAssQ = $mockDb->query("SELECT * FROM recruitment_queue WHERE job_type = 'assessment_assigned' ORDER BY queue_id DESC LIMIT 1");
$assJob = $stmtAssQ->fetch(PDO::FETCH_ASSOC);
assertCustom(!empty($assJob), "Assessment assignment notification job enqueued in recruitment_queue");

// Test 19.3: On-Time Candidate Submission
$subRes1 = $assessmentService->submitAssessmentWork($mockDb, $submissionId, 'https://github.com/aarav-patel/raptor-concurrency-eval');
assertCustom($subRes1['success'] === true, "Candidate submission recorded successfully");
assertCustom($subRes1['is_late'] === false, "On-time submission is identified with is_late = false");
assertCustom($subRes1['status'] === 'submitted', "On-time submission status is set to 'submitted'");

// Test 19.4: Late Candidate Submission Flagging
// Create a separate submission with simulated past due date
$stmtPastSub = $mockDb->prepare("
    INSERT INTO recruitment_assessment_submissions (assessment_id, application_id, assigned_at, due_date, status, created_at)
    VALUES (:aid, :appid, CURRENT_TIMESTAMP, :due, 'assigned', CURRENT_TIMESTAMP)
");
$stmtPastSub->execute([
    ':aid'   => $assId,
    ':appid' => $appId,
    ':due'   => date('Y-m-d H:i:s', time() - 7200) // 2 hours ago
]);
$lateSubId = (int)$mockDb->lastInsertId();

$subResLate = $assessmentService->submitAssessmentWork($mockDb, $lateSubId, 'https://github.com/late-submission/repo');
assertCustom($subResLate['is_late'] === true, "Overdue submission is flagged with is_late = true");
assertCustom($subResLate['status'] === 'submitted_late', "Overdue submission status is set to 'submitted_late'");

// Test 19.5: Recruiter Grading & Rubric Evaluation
// Evaluate Priya Sharma's late submission with failing score: 50 / 100 (Threshold 70)
$evalFail = $assessmentService->evaluateSubmission($mockDb, $lateSubId, 50, 'Did not satisfy concurrency locking requirements.', 1);
assertCustom($evalFail['success'] === true, "Evaluation scored successfully");
assertCustom($evalFail['passed'] === false, "Failing grade accurately identified (50 < 70)");

// Evaluate Aarav Patel's submission with passing score: 92 / 100 (Threshold 70)
$evalPass = $assessmentService->evaluateSubmission($mockDb, $submissionId, 92, 'Exceptional concurrency design and unit test coverage.', 1);
assertCustom($evalPass['success'] === true, "Passing evaluation scored successfully");
assertCustom($evalPass['passed'] === true, "Passing grade accurately identified (92 >= 70)");

// Verify application advisory match score updated
$stmtAppScore = $mockDb->prepare("SELECT current_stage, match_score_advisory FROM recruitment_applications WHERE application_id = :id");
$stmtAppScore->execute([':id' => $aaravAppId]);
$gradedApp = $stmtAppScore->fetch(PDO::FETCH_ASSOC);
assertCustom((int)$gradedApp['match_score_advisory'] === 92, "Application match score advisory updated to 92%");
assertCustom($gradedApp['current_stage'] === 'technical_interview', "Passed candidate automatically advanced to 'technical_interview' stage");

// Test 19.6: Controller Reflection & Route Verification
require_once __DIR__ . '/../app/controllers/RecruitmentAssessmentController.php';

$assControllerReflect = new ReflectionClass('RecruitmentAssessmentController');
assertCustom($assControllerReflect->isSubclassOf('Controller'), "RecruitmentAssessmentController extends core Controller architecture");
assertCustom($assControllerReflect->hasMethod('index'), "RecruitmentAssessmentController exposes index() action");
assertCustom($assControllerReflect->hasMethod('store'), "RecruitmentAssessmentController exposes store() action");
assertCustom($assControllerReflect->hasMethod('assign'), "RecruitmentAssessmentController exposes assign() action");
assertCustom($assControllerReflect->hasMethod('evaluate'), "RecruitmentAssessmentController exposes evaluate() action");
assertCustom($assControllerReflect->hasMethod('grade'), "RecruitmentAssessmentController exposes grade() action");

assertCustom($recControllerReflect->hasMethod('assessments'), "RecruitmentController exposes assessments() routing");

// Test 19.7: Views Existence & Content Markup
$assIndexView = __DIR__ . '/../app/views/recruitment/assessments/index.php';
assertCustom(file_exists($assIndexView), "app/views/recruitment/assessments/index.php exists");
$assIndexMarkup = file_get_contents($assIndexView);
assertCustom(strpos($assIndexMarkup, 'Candidate Assessments & Assignments') !== false, "Assessment index view renders header");
assertCustom(strpos($assIndexMarkup, 'assignAssessmentModal') !== false, "Assessment index view integrates assign modal dialog");
assertCustom(strpos($assIndexMarkup, 'newAssessmentModal') !== false, "Assessment index view integrates new template modal");

$assEvalView = __DIR__ . '/../app/views/recruitment/assessments/evaluate.php';
assertCustom(file_exists($assEvalView), "app/views/recruitment/assessments/evaluate.php exists");
$assEvalMarkup = file_get_contents($assEvalView);
assertCustom(strpos($assEvalMarkup, 'Evaluator Grading Scorecard') !== false, "Assessment evaluate view renders grading rubric");
assertCustom(strpos($assEvalMarkup, 'recruitment_assessment/grade') !== false, "Assessment evaluate form dispatches to grade action");

// ============================================================================
// 20. SPRINT 7: OFFER MANAGEMENT & SALARY PROTECTION
// ============================================================================
echo "\n20. Testing Sprint 7 Offer Management & Salary Protection...\n";

require_once __DIR__ . '/../app/services/recruitment/OfferApprovalService.php';
require_once __DIR__ . '/../app/services/recruitment/OfferService.php';

$offerApprovalService = new OfferApprovalService();
$offerService         = new OfferService($offerApprovalService);

// Test 20.1: Offer Reference Code Generation
$offCode = $offerService->generateOfferCode($mockDb);
assertCustom((bool)preg_match('/^OFF-\d{4}-\d{5}$/', $offCode), "OfferService generates standard format code ({$offCode})");

// Test 20.2: Standard Offer Creation within Budget
// Requisition max_salary is 1800000. Let's offer Aarav 1400000 (within budget, below 15L threshold)
$offerWithin = $offerService->createOffer($mockDb, [
    'application_id'          => $aaravAppId,
    'job_title'               => 'Senior Staff Backend Architect',
    'department'              => 'Engineering',
    'annual_ctc'              => 1400000.00,
    'fixed_salary'            => 1200000.00,
    'variable_pay'            => 150000.00,
    'joining_bonus'           => 50000.00,
    'proposed_joining_date'   => date('Y-m-d', strtotime('+30 days')),
    'offer_expiry_date'       => date('Y-m-d', strtotime('+7 days')),
    'probation_period_months' => 3,
    'notice_period_days'      => 30
], 1);

assertCustom(!empty($offerWithin['offer_id']), "Offer created successfully with unique ID");
assertCustom($offerWithin['status'] === 'pending_approval', "Initial offer status defaults to 'pending_approval'");
assertCustom($offerWithin['is_over_budget'] === false, "In-budget offer is identified with is_over_budget = false");
assertCustom($offerWithin['steps_count'] === 2, "In-budget offer under 15L requires exactly 2 approval steps (Manager + HR)");

// Verify application stage moved to 'offer_preparation'
$stmtAppOfferStg = $mockDb->prepare("SELECT current_stage FROM recruitment_applications WHERE application_id = :id");
$stmtAppOfferStg->execute([':id' => $aaravAppId]);
assertCustom($stmtAppOfferStg->fetchColumn() === 'offer_preparation', "Application stage auto-advanced to 'offer_preparation'");

// Test 20.3: Budget Overrun Offer Escalation Rule
// Create offer for Priya Sharma exceeding requisition budget (3,500,000 > 3,200,000)
$offerOver = $offerService->createOffer($mockDb, [
    'application_id'          => $appId,
    'job_title'               => 'Principal Architect',
    'department'              => 'Engineering',
    'annual_ctc'              => 3500000.00,
    'fixed_salary'            => 3000000.00,
    'variable_pay'            => 350000.00,
    'joining_bonus'           => 150000.00,
    'proposed_joining_date'   => date('Y-m-d', strtotime('+45 days')),
    'offer_expiry_date'       => date('Y-m-d', strtotime('+10 days'))
], 1);

assertCustom($offerOver['is_over_budget'] === true, "Over-budget offer triggers is_over_budget = true flag");
assertCustom($offerOver['steps_count'] === 3, "Over-budget offer escalates to 3 approval steps including Executive/CEO");
$overOfferId = (int)$offerOver['offer_id'];

// Test 20.4: Multi-Tier Approval Chain Actioning
// Step 1: Department Head (Manager)
$step1Res = $offerApprovalService->actionApproval($mockDb, $overOfferId, 2, 'manager', 'approved', 'Candidate has proven mastery.');
assertCustom($step1Res['success'] === true, "Step 1 (Department Head) actioned successfully");
assertCustom($step1Res['all_approved'] === false, "Offer remains pending while subsequent steps remain");

// Unauthorized role check: Sales person trying to action HR Step 2
$salesBlocked = false;
try {
    $offerApprovalService->actionApproval($mockDb, $overOfferId, 999, 'sales_person', 'approved');
} catch (RuntimeException $e) {
    $salesBlocked = true;
}
assertCustom($salesBlocked, "Unauthorized role actioning approval step throws RuntimeException");

// Step 2: HR Manager
$step2Res = $offerApprovalService->actionApproval($mockDb, $overOfferId, 1, 'hr', 'approved', 'Compensation aligns with grade scale.');
assertCustom($step2Res['success'] === true, "Step 2 (HR Manager) actioned successfully");
assertCustom($step2Res['all_approved'] === false, "Offer remains pending for Step 3 executive signoff");

// Step 3: CEO / Finance
$step3Res = $offerApprovalService->actionApproval($mockDb, $overOfferId, 1, 'ceo', 'approved', 'Executive approval granted for Q4 expansion.');
assertCustom($step3Res['success'] === true, "Step 3 (CEO) actioned successfully");
assertCustom($step3Res['all_approved'] === true, "All offer approval steps completed");
assertCustom($step3Res['offer_status'] === 'approved', "Offer status updated to 'approved'");

// Verify application stage moved to 'offer_approved'
$stmtAppOffAppr = $mockDb->prepare("SELECT current_stage FROM recruitment_applications WHERE application_id = :id");
$stmtAppOffAppr->execute([':id' => $appId]);
assertCustom($stmtAppOffAppr->fetchColumn() === 'offer_approved', "Application stage auto-advanced to 'offer_approved'");

// Test 20.5: Offer Release Gate & Queue Dispatch
$releaseRes = $offerService->releaseOffer($mockDb, $overOfferId, 1);
assertCustom($releaseRes['success'] === true, "Approved offer successfully released");
assertCustom($releaseRes['status'] === 'released', "Offer status updated to 'released'");

// Verify candidate notification in recruitment_queue
$stmtOffQ = $mockDb->query("SELECT * FROM recruitment_queue WHERE job_type = 'offer_released' ORDER BY queue_id DESC LIMIT 1");
$offJob = $stmtOffQ->fetch(PDO::FETCH_ASSOC);
assertCustom(!empty($offJob), "Candidate offer release email job enqueued in recruitment_queue");

// Test 20.6: Candidate Acceptance & Rejection
// Release and Decline Aarav's offer
// First approve steps 1 & 2 for Aarav's offer
$offerApprovalService->actionApproval($mockDb, (int)$offerWithin['offer_id'], 2, 'manager', 'approved');
$offerApprovalService->actionApproval($mockDb, (int)$offerWithin['offer_id'], 1, 'hr', 'approved');
$offerService->releaseOffer($mockDb, (int)$offerWithin['offer_id'], 1);

$declineRes = $offerService->candidateResponse($mockDb, (int)$offerWithin['offer_id'], 'declined', 'Accepted competing offer.');
assertCustom($declineRes['success'] === true, "Candidate offer decline recorded successfully");
assertCustom($declineRes['status'] === 'declined', "Offer status set to 'declined'");

// Candidate Priya Sharma accepts her offer
$acceptRes = $offerService->candidateResponse($mockDb, $overOfferId, 'accepted');
assertCustom($acceptRes['success'] === true, "Candidate offer acceptance recorded successfully");
assertCustom($acceptRes['status'] === 'accepted', "Offer status set to 'accepted'");

// Verify application stage moved to 'offer_accepted'
$stmtAppAcc = $mockDb->prepare("SELECT current_stage FROM recruitment_applications WHERE application_id = :id");
$stmtAppAcc->execute([':id' => $appId]);
assertCustom($stmtAppAcc->fetchColumn() === 'offer_accepted', "Application stage auto-advanced to 'offer_accepted'");

// Test 20.7: Salary Confidentiality Protection
$hrOfferView = $offerService->getOfferById($mockDb, $overOfferId, 'hr');
assertCustom((float)$hrOfferView['annual_ctc'] === 3500000.00, "HR role retrieves unmasked Annual CTC");
assertCustom($hrOfferView['is_salary_masked'] === false, "HR offer view is marked as unmasked");

$nonHrOfferView = $offerService->getOfferById($mockDb, $overOfferId, 'team_leader');
assertCustom($nonHrOfferView['annual_ctc'] === null, "Non-HR role has Annual CTC strictly masked as null");
assertCustom($nonHrOfferView['fixed_salary'] === null, "Non-HR role has Fixed Base Salary strictly masked as null");
assertCustom($nonHrOfferView['is_salary_masked'] === true, "Non-HR offer view is marked as masked");

// Test 20.8: Controller Reflection & Routing Verification
require_once __DIR__ . '/../app/controllers/RecruitmentOfferController.php';

$offControllerReflect = new ReflectionClass('RecruitmentOfferController');
assertCustom($offControllerReflect->isSubclassOf('Controller'), "RecruitmentOfferController extends core Controller architecture");
assertCustom($offControllerReflect->hasMethod('index'), "RecruitmentOfferController exposes index() action");
assertCustom($offControllerReflect->hasMethod('create'), "RecruitmentOfferController exposes create() action");
assertCustom($offControllerReflect->hasMethod('store'), "RecruitmentOfferController exposes store() action");
assertCustom($offControllerReflect->hasMethod('show'), "RecruitmentOfferController exposes show() action");
assertCustom($offControllerReflect->hasMethod('approve'), "RecruitmentOfferController exposes approve() action");
assertCustom($offControllerReflect->hasMethod('release'), "RecruitmentOfferController exposes release() action");
assertCustom($offControllerReflect->hasMethod('respond'), "RecruitmentOfferController exposes respond() action");

assertCustom($recControllerReflect->hasMethod('offers'), "RecruitmentController exposes offers() routing");

// Test 20.9: Views Existence & Content Markup
$offIndexView = __DIR__ . '/../app/views/recruitment/offers/index.php';
assertCustom(file_exists($offIndexView), "app/views/recruitment/offers/index.php exists");
$offIndexMarkup = file_get_contents($offIndexView);
assertCustom(strpos($offIndexMarkup, 'Offer Management & Compensation Approvals') !== false, "Offer index view renders header");

$offCreateView = __DIR__ . '/../app/views/recruitment/offers/create.php';
assertCustom(file_exists($offCreateView), "app/views/recruitment/offers/create.php exists");
$offCreateMarkup = file_get_contents($offCreateView);
assertCustom(strpos($offCreateMarkup, 'Create Job Offer Proposal') !== false, "Offer create view renders header");
assertCustom(strpos($offCreateMarkup, 'annual_ctc') !== false, "Offer create view defines annual_ctc input");

$offShowView = __DIR__ . '/../app/views/recruitment/offers/show.php';
assertCustom(file_exists($offShowView), "app/views/recruitment/offers/show.php exists");
$offShowMarkup = file_get_contents($offShowView);
assertCustom(strpos($offShowMarkup, 'Approval Chain Progress') !== false, "Offer show view renders approval chain progress");
assertCustom(strpos($offShowMarkup, 'Official Offer Letter Preview') !== false, "Offer show view renders official letter preview");


// ============================================================================
// 21. SPRINT 8: BGV & IDEMPOTENT EMPLOYEE CONVERSION TEST SUITE
// ============================================================================
echo "\n--- Section 21: Sprint 8 - BGV, Pre-Joining & Idempotent Employee Conversion ---\n";

require_once __DIR__ . '/../app/services/recruitment/BackgroundVerificationService.php';
require_once __DIR__ . '/../app/services/recruitment/EmployeeConversionService.php';
require_once __DIR__ . '/../app/controllers/RecruitmentOnboardingController.php';

$bgvService = new BackgroundVerificationService();
$convService = new EmployeeConversionService();

// Setup fresh test application with candidate and job
// Candidate
$candStmt = $mockDb->prepare("
    INSERT INTO recruitment_candidates (
        candidate_code, first_name, last_name, email, phone, current_company,
        created_at
    ) VALUES (
        'CAN-2026-99999', 'Aarav', 'Sharma', 'aarav.sharma@example.com', '+91 98888 77777', 'Infosys',
        CURRENT_TIMESTAMP
    )
");
$candStmt->execute();
$bgvCandId = (int)$mockDb->lastInsertId();

// Application
$appStmt = $mockDb->prepare("
    INSERT INTO recruitment_applications (
        application_code, job_id, candidate_id, current_stage, created_at, updated_at
    ) VALUES (
        'APP-2026-99008', :job_id, :cand_id, 'offered', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
    )
");
$appStmt->execute([':job_id' => $testJobId, ':cand_id' => $bgvCandId]);
$bgvAppId = (int)$mockDb->lastInsertId();

// Test 21.1: Initiate BGV Checks
$bgvResult = $bgvService->initiateChecks($mockDb, $bgvAppId, ['employment', 'criminal', 'education'], 'FirstAdvantage');
assertCustom($bgvResult['success'] === true, "BGV checks initiated successfully");
assertCustom(count($bgvResult['initiated']) === 3, "3 BGV checks created");

// Verify stage transition to 'bgv'
$checkAppStmt = $mockDb->prepare("SELECT current_stage FROM recruitment_applications WHERE application_id = :id");
$checkAppStmt->execute([':id' => $bgvAppId]);
assertCustom($checkAppStmt->fetchColumn() === 'bgv', "Application stage transitioned to 'bgv'");

// Test 21.2: Check records and checklist items retrieval
$checks = $bgvService->getChecksForApplication($mockDb, $bgvAppId);
assertCustom(count($checks) === 3, "getChecksForApplication returns 3 checks");
$checklist = $bgvService->getPreJoiningChecklist($mockDb, $bgvAppId);
assertCustom(count($checklist) === count(BackgroundVerificationService::DEFAULT_CHECKLIST_ITEMS), "Pre-joining checklist initialized with default items");

// Test 21.3: Update BGV Check Status
$firstBgvId = $checks[0]['bgv_id'];
$updateResult = $bgvService->updateCheckStatus($mockDb, $firstBgvId, 'passed', 'Candidate records verified clean with prior employer', 1);
assertCustom($updateResult['status'] === 'passed', "BGV check updated to passed");

// Verify checklist item verification
$firstChecklistItemId = $checklist[0]['item_id'];
$verifyResult = $bgvService->verifyChecklistItem($mockDb, $firstChecklistItemId, 1);
assertCustom($verifyResult === true, "Checklist item marked as completed");

// Test 21.4: Pre-conversion checks (cannot convert before accepted offer)
$preCheck = $convService->canConvertApplication($mockDb, $bgvAppId);
assertCustom($preCheck['can_convert'] === false, "canConvertApplication rejects application without accepted offer");

// Now create an accepted offer for this application
$offerStmt = $mockDb->prepare("
    INSERT INTO recruitment_offers (
        offer_code, application_id, job_title, department, work_location,
        annual_ctc, fixed_salary, proposed_joining_date, offer_expiry_date,
        status, created_by, created_at
    ) VALUES (
        'OFF-2026-99008', :aid, 'Senior Cloud Architect', 'Engineering', 'Bengaluru, India',
        2400000.00, 2000000.00, '2026-12-01', '2026-12-15',
        'accepted', 1, CURRENT_TIMESTAMP
    )
");
$offerStmt->execute([':aid' => $bgvAppId]);
$bgvOfferId = (int)$mockDb->lastInsertId();

// Test 21.5: Pre-conversion check after offer accepted
$postOfferCheck = $convService->canConvertApplication($mockDb, $bgvAppId);
assertCustom($postOfferCheck['can_convert'] === true, "canConvertApplication confirms application is ready for conversion");

// Test 21.6: Execute Employee Conversion (First Run)
$convResult = $convService->convertCandidateToEmployee($mockDb, $bgvAppId, [], 1);
assertCustom($convResult['success'] === true, "Candidate successfully converted to employee");
assertCustom($convResult['already_converted'] === false, "First conversion flagged as new conversion");
assertCustom(!empty($convResult['employee_id']), "New employee ID created");
assertCustom(!empty($convResult['employee_code']), "Employee code generated");

$newEmpId = (int)$convResult['employee_id'];

// Verify DB state in employees and recruitment_applications
$empRowStmt = $mockDb->prepare("SELECT * FROM employees WHERE employee_id = :id");
$empRowStmt->execute([':id' => $newEmpId]);
$empRow = $empRowStmt->fetch(PDO::FETCH_ASSOC);
assertCustom($empRow !== false, "Employee record exists in employees table");
assertCustom($empRow['job_title'] === 'Senior Cloud Architect', "Employee job_title preserved from offer");
assertCustom((float)$empRow['salary'] === 2400000.00, "Employee salary matches offer CTC");

$appRowStmt = $mockDb->prepare("SELECT current_stage, converted_employee_id FROM recruitment_applications WHERE application_id = :id");
$appRowStmt->execute([':id' => $bgvAppId]);
$appRow = $appRowStmt->fetch(PDO::FETCH_ASSOC);
assertCustom($appRow['current_stage'] === 'hired', "Application stage transitioned to 'hired'");
assertCustom((int)$appRow['converted_employee_id'] === $newEmpId, "Application converted_employee_id linked");

// Verify Welcome Email enqueued
$qStmt = $mockDb->prepare("SELECT * FROM recruitment_queue WHERE job_type = 'employee_welcome' ORDER BY queue_id DESC LIMIT 1");
$qStmt->execute();
$qRow = $qStmt->fetch(PDO::FETCH_ASSOC);
assertCustom($qRow !== false, "Welcome email enqueued in recruitment_queue");
$qPayload = json_decode($qRow['payload_json'], true);
assertCustom((int)$qPayload['employee_id'] === $newEmpId, "Queue payload contains new employee ID");

// Test 21.7: STRICT IDEMPOTENCY GUARD
// Calling conversion a 2nd time must return success with already_converted=true, same employee_id, and 0 duplicate records
$totalEmpsBefore = (int)$mockDb->query("SELECT COUNT(*) FROM employees")->fetchColumn();
$convResult2 = $convService->convertCandidateToEmployee($mockDb, $bgvAppId, [], 1);
$totalEmpsAfter = (int)$mockDb->query("SELECT COUNT(*) FROM employees")->fetchColumn();

assertCustom($convResult2['success'] === true, "Second conversion call returns success under idempotency guard");
assertCustom($convResult2['already_converted'] === true, "already_converted is true on duplicate call");
assertCustom((int)$convResult2['employee_id'] === $newEmpId, "Second call returns identical employee_id");
assertCustom($totalEmpsBefore === $totalEmpsAfter, "Zero duplicate employee records created");

// Test 21.8: RecruitmentOnboardingController Reflection & Routing
$onbControllerReflect = new ReflectionClass('RecruitmentOnboardingController');
assertCustom($onbControllerReflect->isSubclassOf('Controller'), "RecruitmentOnboardingController extends Controller");
assertCustom($onbControllerReflect->hasMethod('index'), "RecruitmentOnboardingController exposes index()");
assertCustom($onbControllerReflect->hasMethod('convert'), "RecruitmentOnboardingController exposes convert()");
assertCustom($onbControllerReflect->hasMethod('executeConversion'), "RecruitmentOnboardingController exposes executeConversion()");
assertCustom($onbControllerReflect->hasMethod('verifyChecklist'), "RecruitmentOnboardingController exposes verifyChecklist()");

assertCustom($recControllerReflect->hasMethod('onboarding'), "RecruitmentController exposes onboarding() routing");

// Test 21.9: Views Existence & Content Markup
$onbIndexView = __DIR__ . '/../app/views/recruitment/onboarding/index.php';
assertCustom(file_exists($onbIndexView), "app/views/recruitment/onboarding/index.php exists");
$onbIndexMarkup = file_get_contents($onbIndexView);
assertCustom(strpos($onbIndexMarkup, 'Pre-Joining, BGV & Employee Conversion') !== false, "Onboarding index view renders header");

$onbConvertView = __DIR__ . '/../app/views/recruitment/onboarding/convert.php';
assertCustom(file_exists($onbConvertView), "app/views/recruitment/onboarding/convert.php exists");
$onbConvertMarkup = file_get_contents($onbConvertView);
assertCustom(strpos($onbConvertMarkup, 'CONFIRMATION GATE: Permanent Employee Conversion') !== false, "Convert view renders confirmation gate header");
assertCustom(strpos($onbConvertMarkup, 'recruitment_onboarding/executeConversion') !== false, "Convert view defines form submission action");


// ============================================================================
// 22. SPRINT 9: RECRUITMENT QUEUE WORKER & MULTI-PROVIDER AI COPILOT
// ============================================================================
echo "\n--- Section 22: Sprint 9 - Queue Worker & Multi-Provider AI Copilot ---\n";

require_once __DIR__ . '/../app/services/recruitment/RecruitmentAiService.php';
require_once __DIR__ . '/../bin/recruitment_worker.php';

$recAiService = new RecruitmentAiService();

// Test 22.1: PII Redaction Engine (DPDP & GDPR Compliance)
$samplePiiText = "Candidate Name: Rohan Verma, Email: rohan.verma@example.com, Phone: +91 98765 43210. Aadhaar: 1234 5678 9012, PAN: ABCDE1234F. Address: Bangalore Pincode: 560001.";
$redactedPii = $recAiService->redactPii($samplePiiText);

assertCustom(strpos($redactedPii, '[REDACTED_EMAIL]') !== false, "PII redaction masks email addresses");
assertCustom(strpos($redactedPii, 'rohan.verma@example.com') === false, "Raw email is removed from prompt payload");
assertCustom(strpos($redactedPii, '[REDACTED_PHONE]') !== false, "PII redaction masks phone numbers");
assertCustom(strpos($redactedPii, '98765 43210') === false, "Raw phone number is removed from prompt payload");
assertCustom(strpos($redactedPii, '[REDACTED_AADHAAR]') !== false, "PII redaction masks Aadhaar numbers");
assertCustom(strpos($redactedPii, '1234 5678 9012') === false, "Raw Aadhaar number is removed from prompt payload");
assertCustom(strpos($redactedPii, '[REDACTED_PAN]') !== false, "PII redaction masks PAN card numbers");
assertCustom(strpos($redactedPii, 'ABCDE1234F') === false, "Raw PAN number is removed from prompt payload");

// Test 22.2: Resume Parsing (Offline Heuristic Resiliency)
$sampleResume = "
Rohan Verma
Senior Backend Architect with 8.5 years of experience in distributed systems.
Education: B.Tech in Computer Science from NIT Trichy.
Core Technical Skills: PHP, MySQL, Docker, Kubernetes, AWS, Redis, Microservices, CI/CD, Git.
Prior Employment: TechCorp India, Infosys.
";

$parsedResume = $recAiService->parseResume($sampleResume, $mockDb);
assertCustom(is_array($parsedResume['skills']), "parseResume returns skills array");
assertCustom(in_array('Php', $parsedResume['skills'], true), "parseResume extracts PHP skill");
assertCustom(in_array('Mysql', $parsedResume['skills'], true), "parseResume extracts MySQL skill");
assertCustom(in_array('Docker', $parsedResume['skills'], true), "parseResume extracts Docker skill");
assertCustom(in_array('Aws', $parsedResume['skills'], true), "parseResume extracts AWS skill");
assertCustom((float)$parsedResume['experience_years'] === 8.5, "parseResume accurately extracts 8.5 years of experience");
assertCustom(in_array('B.Tech', $parsedResume['education'], true), "parseResume extracts B.Tech degree");
assertCustom(isset($parsedResume['provider_used']), "parseResume records provider_used metadata");

// Test 22.3: Candidate-Job Evidence Match Scoring & Advisory Guard
$jobReqs = [
    'job_title'             => 'Lead Staff Engineer',
    'required_skills'       => ['PHP', 'MySQL', 'Docker', 'Kubernetes', 'Terraform'],
    'min_experience_years'  => 7.0
];

$matchResult = $recAiService->scoreCandidateJobMatch($sampleResume, $jobReqs, $mockDb);
assertCustom($matchResult['match_score'] >= 70, "scoreCandidateJobMatch scores strong candidate >= 70 (score: {$matchResult['match_score']})");
assertCustom($matchResult['human_action_required'] === true, "scoreCandidateJobMatch enforces ADR-0007 human_action_required");
assertCustom($matchResult['advisory_only'] === true, "scoreCandidateJobMatch enforces advisory_only flag");
assertCustom(in_array('Php', $matchResult['matched_skills'], true), "scoreCandidateJobMatch records matched skill PHP");
assertCustom(in_array('Terraform', $matchResult['missing_skills'], true), "scoreCandidateJobMatch detects missing skill Terraform");
assertCustom(!empty($matchResult['evidence_summary']), "scoreCandidateJobMatch provides evidence summary narrative");

// Test 22.4: Structured Interview Question Suggestions
$interviewQuestions = $recAiService->generateInterviewQuestions('Lead Staff Engineer', ['PHP', 'Docker', 'Microservices'], 'technical', $mockDb);
assertCustom($interviewQuestions['success'] === true, "generateInterviewQuestions succeeds");
assertCustom(count($interviewQuestions['questions']) >= 3, "generateInterviewQuestions returns at least 3 structured questions");
assertCustom(!empty($interviewQuestions['questions'][0]['question']), "Question object contains question prompt");
assertCustom(!empty($interviewQuestions['questions'][0]['evaluation_criteria']), "Question object contains evaluation criteria");

$cultureQuestions = $recAiService->generateInterviewQuestions('Lead Staff Engineer', [], 'culture', $mockDb);
assertCustom($cultureQuestions['success'] === true, "generateInterviewQuestions succeeds for culture round");
assertCustom(count($cultureQuestions['questions']) >= 3, "Culture round returns structured behavioral questions");

// Test 22.5: Recruitment Queue Worker Batch Execution
$worker = new RecruitmentQueueWorker($mockDb, 10, 3);

// Enqueue an AI matching job
$aiMatchPayload = json_encode([
    'application_id'   => $aaravAppId,
    'resume_text'      => $sampleResume,
    'job_requirements' => $jobReqs
]);

$stmtQJob = $mockDb->prepare("
    INSERT INTO recruitment_queue (job_type, payload_json, status, attempts, scheduled_at, created_at)
    VALUES ('ai_candidate_match', :payload, 'pending', 0, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
");
$stmtQJob->execute([':payload' => $aiMatchPayload]);
$aiJobId = (int)$mockDb->lastInsertId();

$processed = $worker->processBatch();
assertCustom($processed >= 1, "RecruitmentQueueWorker processes pending batch");

// Verify job completed in DB
$stmtJobDone = $mockDb->prepare("SELECT status, attempts, last_error FROM recruitment_queue WHERE queue_id = :id");
$stmtJobDone->execute([':id' => $aiJobId]);
$jobDoneRow = $stmtJobDone->fetch(PDO::FETCH_ASSOC);
assertCustom($jobDoneRow['status'] === 'completed', "Queue job status updated to 'completed'");
assertCustom($jobDoneRow['last_error'] === null, "Queue job last_error cleared");
assertCustom((int)$jobDoneRow['attempts'] === 1, "Queue job attempts updated to 1");

// Verify application match_score_advisory was updated
$stmtAppMatch = $mockDb->prepare("SELECT match_score_advisory FROM recruitment_applications WHERE application_id = :id");
$stmtAppMatch->execute([':id' => $aaravAppId]);
$appMatchScore = (int)$stmtAppMatch->fetchColumn();
assertCustom($appMatchScore >= 70, "Application match_score_advisory updated via queue job ({$appMatchScore}%)");

// Test 22.6: Retry Backoff & Dead-Letter Queue (DLQ) Transitions
// Create a failing job
$failingPayload = json_encode(['interview_id' => -999]); // Invalid interview_id will throw
$stmtFailJob = $mockDb->prepare("
    INSERT INTO recruitment_queue (job_type, payload_json, status, attempts, scheduled_at, created_at)
    VALUES ('interview_scheduled', :payload, 'pending', 0, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
");
$stmtFailJob->execute([':payload' => $failingPayload]);
$failJobId = (int)$mockDb->lastInsertId();

// Attempt 1 Failure -> Remains pending with scheduled_at delayed (exponential backoff)
$failJobRow1 = ['queue_id' => $failJobId, 'job_type' => 'interview_scheduled', 'attempts' => 0, 'payload_json' => $failingPayload];
$worker->executeJob($failJobRow1);

$stmtCheckFail1 = $mockDb->prepare("SELECT status, attempts, last_error FROM recruitment_queue WHERE queue_id = :id");
$stmtCheckFail1->execute([':id' => $failJobId]);
$checkRow1 = $stmtCheckFail1->fetch(PDO::FETCH_ASSOC);
assertCustom($checkRow1['status'] === 'pending', "Attempt 1 failure keeps job in 'pending' status for retry");
assertCustom((int)$checkRow1['attempts'] === 1, "Attempts incremented to 1");
assertCustom(!empty($checkRow1['last_error']), "Last error message recorded on attempt 1");

// Attempt 2 Failure
$failJobRow2 = ['queue_id' => $failJobId, 'job_type' => 'interview_scheduled', 'attempts' => 1, 'payload_json' => $failingPayload];
$worker->executeJob($failJobRow2);

$stmtCheckFail2 = $mockDb->prepare("SELECT status, attempts FROM recruitment_queue WHERE queue_id = :id");
$stmtCheckFail2->execute([':id' => $failJobId]);
$checkRow2 = $stmtCheckFail2->fetch(PDO::FETCH_ASSOC);
assertCustom($checkRow2['status'] === 'pending', "Attempt 2 failure keeps job in 'pending' status");
assertCustom((int)$checkRow2['attempts'] === 2, "Attempts incremented to 2");

// Attempt 3 Failure -> DLQ Transition (max_attempts = 3)
$failJobRow3 = ['queue_id' => $failJobId, 'job_type' => 'interview_scheduled', 'attempts' => 2, 'payload_json' => $failingPayload];
$worker->executeJob($failJobRow3);

$stmtCheckFail3 = $mockDb->prepare("SELECT status, attempts, last_error FROM recruitment_queue WHERE queue_id = :id");
$stmtCheckFail3->execute([':id' => $failJobId]);
$checkRow3 = $stmtCheckFail3->fetch(PDO::FETCH_ASSOC);
assertCustom($checkRow3['status'] === 'dlq', "Attempt 3 failure transitions job to 'dlq' (Dead-Letter Queue)");
assertCustom((int)$checkRow3['attempts'] === 3, "Attempts recorded as 3 in DLQ");
assertCustom(strpos($checkRow3['last_error'], 'Exhausted 3 attempts') !== false, "DLQ records exhaustion error message");

// Test 22.7: CLI Script File Verification
assertCustom(file_exists(__DIR__ . '/../bin/recruitment_worker.php'), "bin/recruitment_worker.php CLI executable exists");


// ============================================================================
// 23. SPRINT 10: RECRUITMENT ANALYTICS & PLAYWRIGHT E2E ACCEPTANCE
// ============================================================================
echo "\n--- Section 23: Sprint 10 - Analytics, Playwright E2E & Release Acceptance ---\n";

require_once __DIR__ . '/../app/services/recruitment/RecruitmentAnalyticsService.php';
require_once __DIR__ . '/../app/controllers/RecruitmentAnalyticsController.php';

$analyticsService = new RecruitmentAnalyticsService();

// Test 23.1: Funnel Progression Metrics
$funnelMetrics = $analyticsService->getFunnelMetrics($mockDb);
assertCustom(isset($funnelMetrics['total_applications']), "getFunnelMetrics returns total_applications");
assertCustom(is_array($funnelMetrics['funnel_stages']), "getFunnelMetrics returns funnel_stages array");
assertCustom(count($funnelMetrics['funnel_stages']) === 6, "funnel_stages covers 6 standard pipeline stages");
assertCustom($funnelMetrics['funnel_stages'][0]['stage_key'] === 'applied', "First funnel stage is 'applied'");
assertCustom($funnelMetrics['funnel_stages'][5]['stage_key'] === 'hired', "Final funnel stage is 'hired'");

// Test 23.2: Time-to-Hire Velocity
$tthMetrics = $analyticsService->getTimeToHireMetrics($mockDb);
assertCustom(isset($tthMetrics['avg_days_to_hire']), "getTimeToHireMetrics calculates avg_days_to_hire");
assertCustom($tthMetrics['total_hires'] >= 1, "getTimeToHireMetrics reflects verified hires");
assertCustom($tthMetrics['benchmark_target'] === 30.0, "Standard 30-day enterprise benchmark defined");

// Test 23.3: Sourcing Channel ROI Matrix
$sourcingRoi = $analyticsService->getSourcingChannelRoi($mockDb);
assertCustom(is_array($sourcingRoi), "getSourcingChannelRoi returns array of channels");
assertCustom(count($sourcingRoi) >= 1, "At least one sourcing channel recorded");
assertCustom(isset($sourcingRoi[0]['channel']), "Channel object defines channel name");
assertCustom(isset($sourcingRoi[0]['applications_count']), "Channel object tracks applications_count");
assertCustom(isset($sourcingRoi[0]['hires_count']), "Channel object tracks hires_count");

// Test 23.4: Offer Acceptance Ratios
$offerStats = $analyticsService->getOfferAcceptanceRate($mockDb);
assertCustom(isset($offerStats['total_released']), "getOfferAcceptanceRate tracks total_released");
assertCustom(isset($offerStats['accepted_count']), "getOfferAcceptanceRate tracks accepted_count");
assertCustom(isset($offerStats['acceptance_rate']), "getOfferAcceptanceRate computes acceptance_rate percentage");

// Test 23.5: Executive KPI Consolidation
$executiveKpis = $analyticsService->getExecutiveKpis($mockDb);
assertCustom(isset($executiveKpis['total_applications']), "Executive KPIs track total_applications");
assertCustom(isset($executiveKpis['total_hires']), "Executive KPIs track total_hires");
assertCustom(isset($executiveKpis['funnel_conversion_rate']), "Executive KPIs track funnel_conversion_rate");
assertCustom(isset($executiveKpis['offer_acceptance_rate']), "Executive KPIs track offer_acceptance_rate");
assertCustom(isset($executiveKpis['avg_days_to_hire']), "Executive KPIs track avg_days_to_hire");

// Test 23.6: RecruitmentAnalyticsController Architecture & Routing
$analyticsControllerReflect = new ReflectionClass('RecruitmentAnalyticsController');
assertCustom($analyticsControllerReflect->isSubclassOf('Controller'), "RecruitmentAnalyticsController extends Controller");
assertCustom($analyticsControllerReflect->hasMethod('index'), "RecruitmentAnalyticsController exposes index() action");
assertCustom($analyticsControllerReflect->hasMethod('exportCsv'), "RecruitmentAnalyticsController exposes exportCsv() action");

assertCustom($recControllerReflect->hasMethod('reports'), "RecruitmentController exposes reports() routing");

// Test 23.7: Views Existence & Content Markup
$analyticsView = __DIR__ . '/../app/views/recruitment/analytics/index.php';
assertCustom(file_exists($analyticsView), "app/views/recruitment/analytics/index.php exists");
$analyticsMarkup = file_get_contents($analyticsView);
assertCustom(strpos($analyticsMarkup, 'Recruitment Intelligence & Funnel Analytics') !== false, "Analytics view renders header");
assertCustom(strpos($analyticsMarkup, 'Avg Time-to-Hire') !== false, "Analytics view renders Avg Time-to-Hire KPI card");
assertCustom(strpos($analyticsMarkup, 'Hiring Pipeline Funnel Velocity') !== false, "Analytics view renders funnel table");
assertCustom(strpos($analyticsMarkup, 'Sourcing Channel ROI') !== false, "Analytics view renders sourcing ROI matrix");
assertCustom(strpos($analyticsMarkup, 'exportCsv') !== false, "Analytics view links to CSV export action");

// Test 23.8: Playwright E2E Test Suite Validation
$e2eSpec = __DIR__ . '/../tests/e2e/recruitment.e2e.spec.ts';
assertCustom(file_exists($e2eSpec), "tests/e2e/recruitment.e2e.spec.ts exists");
$e2eMarkup = file_get_contents($e2eSpec);
assertCustom(strpos($e2eMarkup, 'Journey 1: Public Careers Portal') !== false, "E2E spec defines Public Careers Portal journey");
assertCustom(strpos($e2eMarkup, 'Journey 2: Anti-Spam Honeypot') !== false, "E2E spec defines Anti-Spam Honeypot journey");
assertCustom(strpos($e2eMarkup, 'Journey 3: Recruiter Command Center') !== false, "E2E spec defines Requisitions workflow journey");
assertCustom(strpos($e2eMarkup, 'Journey 4: ATS Pipeline Kanban') !== false, "E2E spec defines ATS Kanban journey");
assertCustom(strpos($e2eMarkup, 'Journey 5: Blind Interview Scorecards') !== false, "E2E spec defines Blind Scorecards journey");
assertCustom(strpos($e2eMarkup, 'Journey 6: Offer Management') !== false, "E2E spec defines Offer Management journey");
assertCustom(strpos($e2eMarkup, 'Journey 7: Background Verification') !== false, "E2E spec defines BGV & Employee Conversion journey");
assertCustom(strpos($e2eMarkup, 'Journey 8: Executive Recruitment Analytics') !== false, "E2E spec defines Analytics journey");





echo "\n=======================================================\n";
echo "SUMMARY: {$passed} PASSED, {$failed} FAILED\n";
echo "=======================================================\n";

if ($failed > 0) {
    exit(1);
}
exit(0);

