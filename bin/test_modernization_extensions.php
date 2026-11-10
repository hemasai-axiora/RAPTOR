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

echo "\n=======================================================\n";
echo "SUMMARY: {$passed} PASSED, {$failed} FAILED\n";
echo "=======================================================\n";

if ($failed > 0) {
    exit(1);
}
exit(0);
