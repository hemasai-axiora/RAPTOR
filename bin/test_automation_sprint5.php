<?php
/**
 * Raptor CRM — Marketing Automation Journeys & Lead Scoring (Sprint 5 Automated Verification Suite)
 * ------------------------------------------------------------------------------------------------
 * Verifies:
 * 1. Migration 0054 schema integrity (journeys, steps, enrollments, scoring rules, score logs)
 * 2. Seeded behavioral lead scoring rules & point matrix
 * 3. Dynamic behavioral lead scoring & automated quality tier progression (Cold -> Warm -> Hot)
 * 4. Frequency cap enforcement on lead scoring events
 * 5. Multi-channel Journey creation (Email -> Delay -> WhatsApp -> Score -> Stage -> Assign)
 * 6. Event-driven enrollment trigger (form_submitted auto-enrollment)
 * 7. Execution pipeline: step progression, delay rescheduling, and journey completion
 * 8. Suppression protection: journeys halted and cancelled for suppressed contacts
 * 9. Standalone CLI runner verification (bin/journey_worker.php --once)
 * 10. Strict payroll isolation guarantee (zero financial/salary column exposure)
 */

declare(strict_types=1);

require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/core/Model.php';
require_once __DIR__ . '/../app/models/Contact.php';
require_once __DIR__ . '/../app/models/Lead.php';
require_once __DIR__ . '/../app/services/WhatsAppService.php';
require_once __DIR__ . '/../app/models/MarketingJourney.php';
require_once __DIR__ . '/../app/models/JourneyRunner.php';
require_once __DIR__ . '/../app/models/LeadScoringRule.php';

// Safe SQLite in-memory setup for deterministic testing
$pdo = new PDO("sqlite::memory:");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_OBJ);

$pdo->sqliteCreateFunction('NOW', function() {
    return date('Y-m-d H:i:s');
});

Database::setMockConnection($pdo);

// Bootstrap foundation tables
$pdo->exec("
CREATE TABLE IF NOT EXISTS users (
    user_id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    email TEXT UNIQUE NOT NULL,
    role TEXT DEFAULT 'agent'
);

CREATE TABLE IF NOT EXISTS clients (
    client_id INTEGER PRIMARY KEY AUTOINCREMENT,
    company_name TEXT,
    email TEXT
);

CREATE TABLE IF NOT EXISTS teams (
    team_id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT
);

CREATE TABLE IF NOT EXISTS products (
    product_id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT,
    status TEXT DEFAULT 'active'
);

CREATE TABLE IF NOT EXISTS follow_ups (
    follow_up_id INTEGER PRIMARY KEY AUTOINCREMENT,
    lead_id INTEGER,
    status TEXT DEFAULT 'scheduled',
    due_at DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS leads (
    lead_id INTEGER PRIMARY KEY AUTOINCREMENT,
    contact_id INTEGER NULL,
    lead_code TEXT,
    client_id INTEGER,
    source TEXT,
    lead_source TEXT,
    status TEXT DEFAULT 'new',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS customers (
    customer_id INTEGER PRIMARY KEY AUTOINCREMENT,
    contact_id INTEGER NULL,
    customer_code TEXT,
    email TEXT,
    phone TEXT,
    status TEXT DEFAULT 'Active',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS campaign_suppressions (
    suppression_id INTEGER PRIMARY KEY AUTOINCREMENT,
    email TEXT UNIQUE NOT NULL,
    reason TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
");

// Run Migration 0050 to ensure full Customer 360 schema
require_once __DIR__ . '/../migrations/0050_unified_customer_360.php';

// Insert mock users
$pdo->exec("
INSERT INTO users (user_id, name, email, role) VALUES 
(1, 'Sarah SalesRep', 'sarah@raptor.io', 'sales_rep'),
(2, 'Dave SupportLead', 'dave@raptor.io', 'support_agent')
");

$testCount = 0;
$failCount = 0;

function assertCondition(bool $cond, string $msg): void {
    global $testCount, $failCount;
    $testCount++;
    if ($cond) {
        echo "  [PASS] {$msg}\n";
    } else {
        $failCount++;
        echo "  [FAIL] {$msg}\n";
    }
}

echo "=== Raptor CRM: Sprint 5 Automation Journeys & Lead Scoring Verification ===\n\n";

// TEST 1: Run Migration 0054
echo "Test 1: Run Migration 0054 & Verify Table Schema\n";
require __DIR__ . '/../migrations/0054_marketing_automation_and_journeys.php';

$tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
assertCondition(in_array('marketing_journeys', $tables, true), "Table marketing_journeys exists");
assertCondition(in_array('journey_steps', $tables, true), "Table journey_steps exists");
assertCondition(in_array('journey_enrollments', $tables, true), "Table journey_enrollments exists");
assertCondition(in_array('lead_scoring_rules', $tables, true), "Table lead_scoring_rules exists");
assertCondition(in_array('lead_score_logs', $tables, true), "Table lead_score_logs exists");

// Verify seeded default rules
$rulesCount = (int)$pdo->query("SELECT COUNT(*) FROM lead_scoring_rules")->fetchColumn();
assertCondition($rulesCount >= 6, "Seeded default scoring rules (found {$rulesCount} rules)");


// TEST 2: Dynamic Lead Scoring & Quality Tier Progression
echo "\nTest 2: Behavioral Lead Scoring & Quality Progression\n";
$contactModel = new Contact();
$c1 = $contactModel->findOrCreateByIdentity('email', 'victor.navorski@krakozhia.gov', [
    'first_name' => 'Victor',
    'last_name' => 'Navorski',
    'company_name' => 'Terminal Logistics',
    'phone' => '+14155557788',
    'lead_quality' => 'cold'
]);
$c1Id = (int)$c1->contact_id;

$scoringModel = new LeadScoringRule();

// Initial state: score 0, cold
assertCondition((int)$c1->lead_score === 0, "Initial lead score is 0");
assertCondition(strtolower($c1->lead_quality ?? '') === 'cold', "Initial quality tier is Cold");

// 2.1 Web Form Submission (+25)
$newScore1 = $scoringModel->recordEventScore($c1Id, 'form_submit');
assertCondition($newScore1 === 25, "Form submit added +25 points (new score: {$newScore1})");

$c1Fresh1 = $contactModel->getById($c1Id);
assertCondition($c1Fresh1->lead_quality === 'warm', "Quality automatically elevated from Cold to Warm (score >= 15)");

// 2.2 Email Link Click (+15)
$newScore2 = $scoringModel->recordEventScore($c1Id, 'email_click');
assertCondition($newScore2 === 40, "Email click added +15 points (new score: {$newScore2})");

// 2.3 WhatsApp Customer Reply (+20) -> Score reaches 60 -> Hot!
$newScore3 = $scoringModel->recordEventScore($c1Id, 'whatsapp_reply');
assertCondition($newScore3 === 60, "WhatsApp reply added +20 points (new score: {$newScore3})");

$c1Fresh3 = $contactModel->getById($c1Id);
assertCondition($c1Fresh3->lead_quality === 'hot', "Quality automatically elevated to Hot (score >= 50)");

// Verify score logs audit trail
$scoreLogs = $pdo->query("SELECT * FROM lead_score_logs WHERE contact_id = {$c1Id}")->fetchAll(PDO::FETCH_OBJ);
assertCondition(count($scoreLogs) === 3, "Exactly 3 audit log entries recorded in lead_score_logs");


// TEST 3: Frequency Cap on Scoring Events
echo "\nTest 3: Daily Frequency Cap Enforcement\n";
// The inbound_call rule has max_daily_occurrences = 2
$scoreBefore = (int)$contactModel->getById($c1Id)->lead_score;
$scoringModel->recordEventScore($c1Id, 'inbound_call'); // call 1 (+30) -> 90
$scoringModel->recordEventScore($c1Id, 'inbound_call'); // call 2 (+30) -> 120
$scoreAfterTwo = (int)$contactModel->getById($c1Id)->lead_score;
assertCondition($scoreAfterTwo === 120, "Two inbound calls awarded 60 points total (score: {$scoreAfterTwo})");

// 3rd call exceeds max_daily_occurrences = 2, so it must be ignored
$excessScore = $scoringModel->recordEventScore($c1Id, 'inbound_call');
$scoreAfterThree = (int)$contactModel->getById($c1Id)->lead_score;
assertCondition($excessScore === 0 && $scoreAfterThree === 120, "Daily frequency cap blocked 3rd call from awarding duplicate points");


// TEST 4: Multi-Step Automation Journey Creation
echo "\nTest 4: Journey Creation & Step Sequence Configuration\n";
$journeyModel = new MarketingJourney();
$journeyId = $journeyModel->create([
    'name' => 'Enterprise Inbound Lead Nurturing',
    'trigger_type' => 'form_submitted',
    'description' => 'Multi-channel demo nurture flow: Email -> 1hr Delay -> WhatsApp -> Score +15 -> SQL stage -> Rep assign',
    'status' => 'active',
    'created_by' => 1
]);
assertCondition($journeyId > 0, "Created marketing journey (ID: {$journeyId})");

$stepsToSet = [
    [
        'step_type' => 'action',
        'action_type' => 'send_email',
        'action_config' => ['subject' => 'Thank you for requesting an Enterprise Demo!']
    ],
    [
        'step_type' => 'delay',
        'action_type' => 'wait_delay',
        'action_config' => ['delay_seconds' => 3600]
    ],
    [
        'step_type' => 'action',
        'action_type' => 'send_whatsapp',
        'action_config' => ['template_name' => 'demo_reminder', 'language' => 'en_US']
    ],
    [
        'step_type' => 'action',
        'action_type' => 'adjust_lead_score',
        'action_config' => ['score_delta' => 15]
    ],
    [
        'step_type' => 'action',
        'action_type' => 'update_stage',
        'action_config' => ['lifecycle_stage' => 'sql']
    ],
    [
        'step_type' => 'action',
        'action_type' => 'assign_rep',
        'action_config' => ['user_id' => 1]
    ]
];

$journeyModel->setSteps($journeyId, $stepsToSet);
$savedSteps = $journeyModel->getSteps($journeyId);
assertCondition(count($savedSteps) === 6, "Saved 6 ordered journey steps successfully");


// TEST 5: Event-Driven Enrollment
echo "\nTest 5: Inbound Event Trigger & Enrollment\n";
// Create prospect contact
$c2 = $contactModel->findOrCreateByIdentity('email', 'clara.oswald@tardis.ac.uk', [
    'first_name' => 'Clara',
    'last_name' => 'Oswald',
    'company_name' => 'Coal Hill School',
    'phone' => '+447911123456'
]);
$c2Id = (int)$c2->contact_id;

// Trigger 'form_submitted' event
$enrolledCount = MarketingJourney::triggerEvent('form_submitted', $c2Id);
assertCondition($enrolledCount === 1, "Contact Clara auto-enrolled into active journey upon form submission");

$enrollment = $pdo->query("SELECT * FROM journey_enrollments WHERE contact_id = {$c2Id} AND journey_id = {$journeyId}")->fetch(PDO::FETCH_OBJ);
assertCondition(!empty($enrollment), "Enrollment record exists in DB");
assertCondition($enrollment->status === 'active', "Initial enrollment status is 'active'");
assertCondition((int)$enrollment->current_step_order === 1, "Initial step is Step 1");

// Test Idempotency: Triggering event again does not duplicate active enrollment
$enrolledAgain = MarketingJourney::triggerEvent('form_submitted', $c2Id);
$totalEnrollmentsForC2 = (int)$pdo->query("SELECT COUNT(*) FROM journey_enrollments WHERE contact_id = {$c2Id} AND journey_id = {$journeyId}")->fetchColumn();
assertCondition($totalEnrollmentsForC2 === 1, "Idempotent: Subsequent trigger event does not create duplicate active enrollment");


// TEST 6: Execution Pipeline (Runner & Delay Advance)
echo "\nTest 6: JourneyRunner Step Execution & Rescheduling\n";
$runner = new JourneyRunner();

// Step 1: Execute immediate action (send_email) -> advances to Step 2
$batch1 = $runner->processPendingEnrollments(10);
assertCondition($batch1['processed'] >= 1, "Batch 1 processed Clara's enrollment (executed Step 1: email)");

// Step 2: Execute delay step (wait_delay) -> advances to Step 3 and sets next_run_at in future
$batch2 = $runner->processPendingEnrollments(10);
assertCondition($batch2['delayed'] >= 1, "Batch 2 processed Step 2 delay (scheduled +3600s)");

$freshEnrollment1 = $pdo->query("SELECT * FROM journey_enrollments WHERE enrollment_id = {$enrollment->enrollment_id}")->fetch(PDO::FETCH_OBJ);
assertCondition((int)$freshEnrollment1->current_step_order === 3, "Next step is 3 (WhatsApp)");
assertCondition($freshEnrollment1->next_run_at > date('Y-m-d H:i:s'), "next_run_at rescheduled into future (+3600s)");

// Running batch now should find 0 pending (delay is in effect)
$batchEmpty = $runner->processPendingEnrollments(10);
assertCondition($batchEmpty['processed'] === 0, "No pending enrollments while delay is in effect");

// Simulate time passing: manually set next_run_at = NOW()
$pdo->exec("UPDATE journey_enrollments SET next_run_at = datetime('now', '-5 minutes') WHERE enrollment_id = {$enrollment->enrollment_id}");

// Run Batch 2: Should process remaining steps (Step 3: WhatsApp, Step 4: Score, Step 5: Stage, Step 6: Assign) and complete journey
$runner->processPendingEnrollments(10); // step 3 (WhatsApp)
$runner->processPendingEnrollments(10); // step 4 (Score +15)
$runner->processPendingEnrollments(10); // step 5 (Stage -> sql)
$runner->processPendingEnrollments(10); // step 6 (Assign -> 1)
$runner->processPendingEnrollments(10); // complete

$finalEnrollment = $pdo->query("SELECT * FROM journey_enrollments WHERE enrollment_id = {$enrollment->enrollment_id}")->fetch(PDO::FETCH_OBJ);
assertCondition($finalEnrollment->status === 'completed', "Journey reached end and marked 'completed'");
assertCondition(!empty($finalEnrollment->completed_at), "completed_at timestamp populated");

// Verify Contact state after journey completion
$c2Final = $contactModel->getById($c2Id);
assertCondition((int)$c2Final->lead_score === 15, "Contact received +15 points from journey score step");
assertCondition($c2Final->lifecycle_stage === 'sql', "Contact lifecycle stage updated to 'sql'");
assertCondition((int)$c2Final->assigned_user_id === 1, "Contact assigned to Rep #1 (Sarah)");

$journeyFinal = $journeyModel->getById($journeyId);
assertCondition((int)$journeyFinal->total_completed === 1, "Journey total_completed counter incremented to 1");


// TEST 7: Suppression Invariant Enforcement
echo "\nTest 7: Suppression Protection During Journey Execution\n";
// Create another contact, enroll, then suppress before next step
$c3 = $contactModel->findOrCreateByIdentity('email', 'suppressed.user@domain.com', [
    'first_name' => 'Suppressed',
    'last_name' => 'User'
]);
$c3Id = (int)$c3->contact_id;
$journeyModel->enrollContact($journeyId, $c3Id);

// Mark globally suppressed
$contactModel->suppressContact($c3Id, 'Unsubscribed from marketing communications');

// Process pending enrollments
$suppBatch = $runner->processPendingEnrollments(10);
assertCondition($suppBatch['suppressed_cancelled'] >= 1, "Runner detected suppressed contact and flagged cancellation");

$c3Enrollment = $pdo->query("SELECT * FROM journey_enrollments WHERE contact_id = {$c3Id} AND journey_id = {$journeyId}")->fetch(PDO::FETCH_OBJ);
assertCondition($c3Enrollment->status === 'cancelled', "Suppressed contact's active enrollment was immediately cancelled");
assertCondition(str_contains($c3Enrollment->execution_log_json, 'globally suppressed'), "Cancellation reason logged in execution audit history");


// TEST 8: Standalone CLI Worker Test (bin/journey_worker.php --once)
echo "\nTest 8: Standalone CLI Runner Execution\n";
$workerCmd = "php " . escapeshellarg(__DIR__ . '/journey_worker.php') . " --once 2>&1";
$output = [];
$retVal = 0;
exec($workerCmd, $output, $retVal);
$outputText = implode("\n", $output);
assertCondition($retVal === 0, "CLI journey worker executed cleanly with return code 0");
assertCondition(str_contains($outputText, 'single-pass mode'), "CLI runner recognized --once flag");


// TEST 9: Strict Payroll Isolation Guarantee
echo "\nTest 9: Strict Payroll Isolation Guarantee\n";
$payrollKeywords = ['salary', 'ctc', 'basic_pay', 'allowance', 'deduction', 'bank_account', 'pan_number', 'payslip'];
$automationFiles = [
    __DIR__ . '/../app/controllers/JourneysController.php',
    __DIR__ . '/../app/models/MarketingJourney.php',
    __DIR__ . '/../app/models/JourneyRunner.php',
    __DIR__ . '/../app/models/LeadScoringRule.php',
    __DIR__ . '/../app/views/journeys/index.php',
    __DIR__ . '/../app/views/journeys/builder.php',
    __DIR__ . '/../app/views/journeys/scoring.php',
    __DIR__ . '/../bin/journey_worker.php',
    __DIR__ . '/../migrations/0054_marketing_automation_and_journeys.php'
];

$leaksFound = 0;
foreach ($automationFiles as $f) {
    if (!file_exists($f)) continue;
    $content = strtolower((string)file_get_contents($f));
    foreach ($payrollKeywords as $kw) {
        if (preg_match('/\b' . preg_quote($kw, '/') . '\b/', $content)) {
            echo "  [FAIL] Potential payroll keyword leak '{$kw}' in {$f}\n";
            $leaksFound++;
        }
    }
}
assertCondition($leaksFound === 0, "Marketing Automation module has zero exposure to payroll/salary tables or columns");


// Summary
echo "\n====================================================================\n";
echo "SUMMARY: " . ($testCount - $failCount) . " PASSED, {$failCount} FAILED\n";
echo "====================================================================\n";
if ($failCount === 0) {
    echo "SUCCESS: Automation Journeys & Lead Scoring (Sprint 5) fully verified!\n";
    exit(0);
} else {
    echo "FAILURE: {$failCount} test(s) failed.\n";
    exit(1);
}
