<?php
/**
 * Raptor CRM — Cloud Telephony, Call Management & TRAI DND (Sprint 6 Automated Verification Suite)
 * ------------------------------------------------------------------------------------------------
 * Verifies:
 * 1. Migration 0055 schema integrity (telephony_calls, dnd_registry, call_dispositions)
 * 2. Seeded standard call dispositions & categories
 * 3. International E.164 phone normalization
 * 4. Legal calling hours gate enforcement (09:00 AM - 08:00 PM)
 * 5. TRAI DND Registry scrubbing & telemarketing call blocking
 * 6. Click-to-Call dialing bridge initiation
 * 7. Status webhook processing, audio recording ingestion & Customer 360 timeline sync
 * 8. Call outcome disposition tagging & automated callback task scheduling
 * 9. Contact suppression protection invariant
 * 10. Strict payroll isolation guarantee (zero financial/salary column exposure)
 */

declare(strict_types=1);

require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/core/Model.php';
require_once __DIR__ . '/../app/models/Contact.php';
require_once __DIR__ . '/../app/models/Lead.php';
require_once __DIR__ . '/../app/models/InboxThread.php';
require_once __DIR__ . '/../app/models/InboxMessage.php';
require_once __DIR__ . '/../app/services/TelephonyService.php';
require_once __DIR__ . '/../app/models/TelephonyCall.php';
require_once __DIR__ . '/../app/models/DndRegistry.php';

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

// Run Migration 0050 (Customer 360) and 0053 (Shared Inbox)
require_once __DIR__ . '/../migrations/0050_unified_customer_360.php';
require_once __DIR__ . '/../migrations/0053_omnichannel_shared_inbox.php';

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

echo "=== Raptor CRM: Sprint 6 Cloud Telephony & Call Management Verification ===\n\n";

// TEST 1: Run Migration 0055
echo "Test 1: Run Migration 0055 & Verify Table Schema\n";
require __DIR__ . '/../migrations/0055_cloud_telephony_and_call_management.php';

$tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
assertCondition(in_array('telephony_calls', $tables, true), "Table telephony_calls exists");
assertCondition(in_array('dnd_registry', $tables, true), "Table dnd_registry exists");
assertCondition(in_array('call_dispositions', $tables, true), "Table call_dispositions exists");

// Verify default seeded call dispositions
$dispCount = (int)$pdo->query("SELECT COUNT(*) FROM call_dispositions")->fetchColumn();
assertCondition($dispCount >= 7, "Seeded default call dispositions (found {$dispCount} items)");


// TEST 2: International E.164 Phone Normalization
echo "\nTest 2: Phone Normalization Engine\n";
assertCondition(TelephonyService::normalizePhone('00919876543210') === '+919876543210', "Normalized 00 prefix to +919876543210");
assertCondition(TelephonyService::normalizePhone('9876543210') === '+919876543210', "Auto-prefixed +91 to Indian 10-digit number");
assertCondition(TelephonyService::normalizePhone('+1 (415) 555-0199') === '+14155550199', "Cleaned US formatting to +14155550199");
assertCondition(TelephonyService::normalizePhone('invalid') === '', "Handled invalid string gracefully");


// TEST 3: Calling Hours Gate Enforcement
echo "\nTest 3: Calling Hours Gate (09:00 - 20:00)\n";
// Outside legal hours without bypass must fail
$isHours = TelephonyService::isWithinCallingHours();
echo "  [INFO] System calling hours status: " . ($isHours ? 'OPEN' : 'RESTRICTED') . "\n";

// Force test calling hours gate logic
$resBlocked = TelephonyService::initiateCall('+18005550199', '+919876543210', [
    'bypass_calling_hours' => false
]);
if (!$isHours) {
    assertCondition($resBlocked['success'] === false && $resBlocked['code'] === 'CALLING_HOURS_RESTRICTION', "Blocked call outside legal calling hours");
} else {
    assertCondition($resBlocked['success'] === true, "Allowed call during active calling hours");
}


// TEST 4: TRAI DND Registry Enforcement
echo "\nTest 4: TRAI DND Registry Scrubbing & Blocking\n";
$dndModel = new DndRegistry();
$dndPhone = '+919999988888';
$dndModel->addNumber($dndPhone, 'full', 'trai_scrubbed');

assertCondition($dndModel->isDnd($dndPhone) === true, "Phone {$dndPhone} verified in DND registry");
assertCondition(TelephonyService::isDndRestricted($dndPhone) === true, "TelephonyService detects DND restriction");

// Attempt outbound sales call to DND number
$dndCallRes = TelephonyService::initiateCall('+18005550199', $dndPhone, [
    'bypass_calling_hours' => true
]);
assertCondition($dndCallRes['success'] === false && $dndCallRes['code'] === 'TRAI_DND_BLOCKED', "Outbound promotional call to DND number blocked with TRAI_DND_BLOCKED");

// Batch scrubbing test
$batchScrub = $dndModel->scrubBatch(['+919999988888', '+919876543210', '+14155550199']);
assertCondition(count($batchScrub['restricted']) === 1 && $batchScrub['restricted'][0] === '+919999988888', "Batch scrubber partitioned restricted DND number correctly");
assertCondition(count($batchScrub['clean']) === 2, "Batch scrubber identified 2 clean callable numbers");


// TEST 5: Click-to-Call Dialing Bridge
echo "\nTest 5: Click-to-Call Outbound Bridge\n";
$contactModel = new Contact();
$c1 = $contactModel->findOrCreateByIdentity('phone', '+919812345678', [
    'first_name' => 'Rajesh',
    'last_name' => 'Kumar',
    'company_name' => 'Kumar Infotech',
    'email' => 'rajesh@kumartech.in'
]);
$c1Id = (int)$c1->contact_id;

$callModel = new TelephonyCall();
$callRes = TelephonyService::initiateCall('+18005550199', '+919812345678', [
    'contact_id' => $c1Id,
    'user_id' => 1,
    'bypass_calling_hours' => true
]);

assertCondition($callRes['success'] === true, "Initiated Click-to-Call connection successfully");
assertCondition(!empty($callRes['call_sid']), "Assigned external provider call SID: {$callRes['call_sid']}");

$callRecord = $callModel->getById((int)$callRes['call_id']);
assertCondition(!empty($callRecord), "Call record retrieved from database");
assertCondition($callRecord->status === 'initiated', "Call status initialized to 'initiated'");
assertCondition($callRecord->dnd_status === 'scrubbed_clean', "DND status marked 'scrubbed_clean'");


// TEST 6: Status Webhook Processing & Customer 360 Timeline Sync
echo "\nTest 6: Status Webhook Processing & Timeline Synchronization\n";
$callSid = $callRes['call_sid'];
$recordingUrl = 'https://telephony-storage.s3.amazonaws.com/recordings/call_rec_12345.mp3';

$webhookSuccess = TelephonyService::handleStatusCallback($callSid, 'completed', 145, $recordingUrl);
assertCondition($webhookSuccess === true, "Status webhook processed successfully");

$callCompleted = $callModel->getByCallSid($callSid);
assertCondition($callCompleted->status === 'completed', "Call status updated to 'completed'");
assertCondition((int)$callCompleted->duration_seconds === 145, "Call duration updated to 145 seconds");
assertCondition($callCompleted->recording_url === $recordingUrl, "Call audio recording URL ingested correctly");

// Verify Customer 360 timeline activity
$activities = $pdo->query("SELECT * FROM contact_activities WHERE contact_id = {$c1Id} AND channel = 'call'")->fetchAll(PDO::FETCH_OBJ);
assertCondition(count($activities) >= 1, "Call touchpoint recorded in Customer 360 timeline");
assertCondition(str_contains($activities[0]->title, '145s'), "Timeline specifies call duration: {$activities[0]->title}");

// Verify Shared Inbox thread integration
$threadModel = new InboxThread();
$thread = $threadModel->getByContactId($c1Id);
assertCondition(!empty($thread), "Consolidated thread exists for contact");
$inboxMessages = $pdo->query("SELECT * FROM inbox_messages WHERE contact_id = {$c1Id} AND channel = 'call'")->fetchAll(PDO::FETCH_OBJ);
assertCondition(count($inboxMessages) >= 1, "Call event delivered into Omnichannel Shared Inbox stream");


// TEST 7: Call Outcome Disposition & Scheduled Callback
echo "\nTest 7: Call Disposition Tagging & Follow-up Scheduling\n";
// Create lead associated with contact
$pdo->exec("INSERT INTO leads (contact_id, lead_code, status) VALUES ({$c1Id}, 'LEAD-901', 'contacted')");
$leadId = (int)$pdo->lastInsertId();
$pdo->exec("UPDATE telephony_calls SET lead_id = {$leadId} WHERE call_id = {$callCompleted->call_id}");

$callbackDate = date('Y-m-d H:i:s', strtotime('+2 days 10:00:00'));
$dispSuccess = $callModel->updateDisposition(
    (int)$callCompleted->call_id,
    'callback_requested',
    'Prospect requested pricing proposal and callback at 10 AM',
    $callbackDate
);
assertCondition($dispSuccess === true, "Updated call disposition to 'callback_requested'");

$freshCall = $callModel->getById((int)$callCompleted->call_id);
assertCondition($freshCall->disposition_code === 'callback_requested', "Disposition code stored correctly");
assertCondition(str_contains($freshCall->disposition_notes, 'pricing proposal'), "Call notes saved");

// Verify automatic follow_ups task scheduled
$followUp = $pdo->query("SELECT * FROM follow_ups WHERE lead_id = {$leadId}")->fetch(PDO::FETCH_OBJ);
assertCondition(!empty($followUp), "Scheduled follow-up task created automatically for sales agent");
assertCondition($followUp->due_at === $callbackDate, "Follow-up due date matches requested callback time");


// TEST 8: Telephony Analytics Aggregator
echo "\nTest 8: Telephony Analytics Aggregator\n";
$analytics = $callModel->getAnalytics();
assertCondition($analytics['total_calls'] >= 1, "Analytics reports total calls: {$analytics['total_calls']}");
assertCondition($analytics['completed_calls'] >= 1, "Analytics reports completed calls: {$analytics['completed_calls']}");
assertCondition($analytics['answer_rate'] > 0, "Analytics reports answer rate: {$analytics['answer_rate']}%");
assertCondition($analytics['total_talk_minutes'] > 0, "Analytics reports total talk time: {$analytics['total_talk_minutes']} mins");


// TEST 9: Contact Global Suppression Protection
echo "\nTest 9: Contact Suppression Protection\n";
$c2 = $contactModel->findOrCreateByIdentity('phone', '+919877711122', [
    'first_name' => 'Suppressed',
    'last_name' => 'Prospect'
]);
$c2Id = (int)$c2->contact_id;
$contactModel->suppressContact($c2Id, 'Prospect requested DNC / Unsubscribe');

$suppCallRes = TelephonyService::initiateCall('+18005550199', '+919877711122', [
    'contact_id' => $c2Id,
    'bypass_calling_hours' => true
]);
assertCondition($suppCallRes['success'] === false && $suppCallRes['code'] === 'CONTACT_SUPPRESSED', "Outbound click-to-call strictly blocked for globally suppressed contact");


// TEST 10: Strict Payroll Isolation Guarantee
echo "\nTest 10: Strict Payroll Isolation Guarantee\n";
$payrollKeywords = ['salary', 'ctc', 'basic_pay', 'allowance', 'deduction', 'bank_account', 'pan_number', 'payslip'];
$telephonyFiles = [
    __DIR__ . '/../app/controllers/TelephonyController.php',
    __DIR__ . '/../app/controllers/TelephonyWebhookController.php',
    __DIR__ . '/../app/services/TelephonyService.php',
    __DIR__ . '/../app/models/TelephonyCall.php',
    __DIR__ . '/../app/models/DndRegistry.php',
    __DIR__ . '/../app/views/telephony/index.php',
    __DIR__ . '/../app/views/telephony/dnd.php',
    __DIR__ . '/../migrations/0055_cloud_telephony_and_call_management.php'
];

$leaksFound = 0;
foreach ($telephonyFiles as $f) {
    if (!file_exists($f)) continue;
    $content = strtolower((string)file_get_contents($f));
    foreach ($payrollKeywords as $kw) {
        if (preg_match('/\b' . preg_quote($kw, '/') . '\b/', $content)) {
            echo "  [FAIL] Potential payroll keyword leak '{$kw}' in {$f}\n";
            $leaksFound++;
        }
    }
}
assertCondition($leaksFound === 0, "Telephony module has zero exposure to payroll/salary tables or columns");


// Summary
echo "\n====================================================================\n";
echo "SUMMARY: " . ($testCount - $failCount) . " PASSED, {$failCount} FAILED\n";
echo "====================================================================\n";
if ($failCount === 0) {
    echo "SUCCESS: Cloud Telephony & Call Management (Sprint 6) fully verified!\n";
    exit(0);
} else {
    echo "FAILURE: {$failCount} test(s) failed.\n";
    exit(1);
}

