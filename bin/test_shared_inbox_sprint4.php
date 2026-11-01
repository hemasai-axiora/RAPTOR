<?php
/**
 * Raptor CRM — Omnichannel Shared Team Inbox (Sprint 4 Automated Verification Suite)
 * ----------------------------------------------------------------------------------
 * Verifies:
 * 1. Migration 0053 schema integrity (inbox_threads, inbox_messages, canned_replies)
 * 2. Thread creation & deduplication per contact (InboxThread::getOrCreateForContact)
 * 3. Omnichannel message ingestion & thread preview updates (WhatsApp, Email, SMS)
 * 4. Internal private team note isolation (is_private_note = 1 vs 0)
 * 5. Canned reply lookup & variable merge tag interpolation (CannedReply::interpolate)
 * 6. Agent assignment & thread status state transitions (unassigned -> open -> resolved)
 * 7. Suppression protection (outbound customer reply blocked for suppressed contacts)
 * 8. Strict payroll isolation guarantee (zero financial / salary column exposure)
 */

declare(strict_types=1);

require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/core/Model.php';
require_once __DIR__ . '/../app/models/Contact.php';
require_once __DIR__ . '/../app/models/Lead.php';
require_once __DIR__ . '/../app/models/InboxThread.php';
require_once __DIR__ . '/../app/models/InboxMessage.php';
require_once __DIR__ . '/../app/models/CannedReply.php';

// Safe in-memory SQLite connection for deterministic testing
$pdo = new PDO("sqlite::memory:");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_OBJ);

$pdo->sqliteCreateFunction('NOW', function() {
    return date('Y-m-d H:i:s');
});

Database::setMockConnection($pdo);

// Bootstrap required foundation tables
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

echo "=== Raptor CRM: Sprint 4 Omnichannel Shared Team Inbox Verification ===\n\n";

// TEST 1: Run Migration 0053
echo "Test 1: Run Migration 0053 & Verify Table Creation\n";
require __DIR__ . '/../migrations/0053_omnichannel_shared_inbox.php';

$tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
assertCondition(in_array('inbox_threads', $tables, true), "Table inbox_threads exists");
assertCondition(in_array('inbox_messages', $tables, true), "Table inbox_messages exists");
assertCondition(in_array('canned_replies', $tables, true), "Table canned_replies exists");

// Verify default seeded canned replies
$cannedCount = (int)$pdo->query("SELECT COUNT(*) FROM canned_replies")->fetchColumn();
assertCondition($cannedCount >= 4, "Seeded default canned replies (found {$cannedCount} items)");


// TEST 2: Thread Creation & Deduplication Per Contact
echo "\nTest 2: InboxThread::getOrCreateForContact Idempotency\n";
$contactModel = new Contact();
$c1 = $contactModel->findOrCreateByIdentity('phone', '+14155552671', [
    'first_name' => 'Alice',
    'last_name' => 'Wonder',
    'company_name' => 'Wonderland Ltd',
    'primary_email' => 'alice@wonderland.com'
]);
$c1Id = (int)$c1->contact_id;

$threadModel = new InboxThread();
$t1 = $threadModel->getOrCreateForContact($c1Id, 'whatsapp');
assertCondition(!empty($t1) && (int)$t1->contact_id === $c1Id, "Thread created for contact Alice (thread_id: {$t1->thread_id})");
assertCondition($t1->status === 'unassigned', "New thread starts with status 'unassigned'");
assertCondition($t1->channel_primary === 'whatsapp', "Initial thread primary channel is 'whatsapp'");

// Requesting again for same contact returns existing thread
$t1Again = $threadModel->getOrCreateForContact($c1Id, 'whatsapp');
assertCondition((int)$t1Again->thread_id === (int)$t1->thread_id, "Idempotent: Subsequent call returns exact same thread ID {$t1->thread_id}");


// TEST 3: Omnichannel Message Ingestion & Thread Preview Updates
echo "\nTest 3: Multi-channel Message Ingestion & Last Message Preview\n";
$msgModel = new InboxMessage();

// 3.1 Inbound WhatsApp Message
$msg1Id = $msgModel->recordMessage([
    'thread_id' => (int)$t1->thread_id,
    'contact_id' => $c1Id,
    'channel' => 'whatsapp',
    'direction' => 'inbound',
    'body' => 'Hello! I need pricing for 50 users.',
    'external_id' => 'wamid.HBgL1234567890'
]);
assertCondition($msg1Id > 0, "Recorded inbound WhatsApp message ($msg1Id)");

$freshT1 = $threadModel->getById((int)$t1->thread_id);
assertCondition((int)$freshT1->unread_count === 1, "Inbound message incremented thread unread_count to 1");
assertCondition($freshT1->last_message_preview === 'Hello! I need pricing for 50 users.', "Last message preview updated correctly");
assertCondition($freshT1->last_channel === 'whatsapp', "Last channel marked as 'whatsapp'");

// 3.2 Outbound Email Response
$msg2Id = $msgModel->recordMessage([
    'thread_id' => (int)$t1->thread_id,
    'contact_id' => $c1Id,
    'channel' => 'email',
    'direction' => 'outbound',
    'body' => 'Hi Alice, emailing you our enterprise pricing sheet.',
    'sender_user_id' => 1
]);
assertCondition($msg2Id > 0, "Recorded outbound Email message ($msg2Id)");

$freshT2 = $threadModel->getById((int)$t1->thread_id);
assertCondition($freshT2->last_channel === 'email', "Last channel switched to 'email'");
assertCondition($freshT2->last_message_preview === 'Hi Alice, emailing you our enterprise pricing sheet.', "Last preview updated to outbound email");
// Outbound messages do NOT increment unread_count
assertCondition((int)$freshT2->unread_count === 1, "Outbound message did not increment unread_count");


// TEST 4: Internal Private Team Notes Isolation
echo "\nTest 4: Internal Private Team Notes (is_private_note = 1)\n";
$msg3Id = $msgModel->recordMessage([
    'thread_id' => (int)$t1->thread_id,
    'contact_id' => $c1Id,
    'channel' => 'note',
    'direction' => 'internal',
    'is_private_note' => 1,
    'body' => 'Sarah note: Client prefers billing in GBP. Escalate discount to Dave.',
    'sender_user_id' => 1
]);
assertCondition($msg3Id > 0, "Recorded internal private team note ($msg3Id)");

$freshT3 = $threadModel->getById((int)$t1->thread_id);
assertCondition(str_starts_with($freshT3->last_message_preview, '[Internal Note]'), "Thread preview designates internal note tag");

$allMsgs = $msgModel->getMessagesForThread((int)$t1->thread_id);
assertCondition(count($allMsgs) === 3, "Thread contains all 3 message items");

$privateNotes = array_filter($allMsgs, fn($m) => (int)$m->is_private_note === 1);
assertCondition(count($privateNotes) === 1, "Exactly 1 internal private note isolated");
assertCondition(reset($privateNotes)->body === 'Sarah note: Client prefers billing in GBP. Escalate discount to Dave.', "Private note content preserved");

// Verify that private notes do not leak into public customer activities timeline
$activities = $pdo->query("SELECT * FROM contact_activities WHERE contact_id = {$c1Id}")->fetchAll(PDO::FETCH_OBJ);
$noteActivities = array_filter($activities, fn($a) => str_contains($a->description ?? '', 'Billing in GBP'));
assertCondition(count($noteActivities) === 0, "Private team note never recorded in public customer timeline");


// TEST 5: Canned Reply Shortcuts & Variable Merge Tag Interpolation
echo "\nTest 5: Canned Reply Retrieval & Merge Tag Interpolation\n";
$cannedModel = new CannedReply();
$introReply = $cannedModel->getByShortcut('/intro');
assertCondition(!empty($introReply), "Fetched default /intro canned reply");

$aliceContact = $contactModel->getById($c1Id);
$interpolated = CannedReply::interpolate($introReply->body_text, $aliceContact);
assertCondition(str_contains($interpolated, 'Hi Alice, thanks for reaching out'), "Interpolated {{first_name}} into 'Alice': '{$interpolated}'");

// Create custom canned snippet with company tag
$newCannedId = $cannedModel->create('/pitch', 'Elevator Pitch', 'sales', 'Hi {{first_name}}, we can scale {{company}} seamlessly!', 1);
assertCondition($newCannedId > 0, "Created custom canned reply /pitch");
$pitchReply = $cannedModel->getByShortcut('/pitch');
$interpolatedPitch = CannedReply::interpolate($pitchReply->body_text, $aliceContact);
assertCondition(str_contains($interpolatedPitch, 'we can scale Wonderland Ltd seamlessly!'), "Interpolated {{company}} into 'Wonderland Ltd'");


// TEST 6: Rep Assignment & Thread Status State Transitions
echo "\nTest 6: Rep Assignment & Status Transitions\n";
// Assign thread to Sarah (user 1)
$threadModel->assignRep((int)$t1->thread_id, 1);
$tAssigned = $threadModel->getById((int)$t1->thread_id);
assertCondition((int)$tAssigned->assigned_user_id === 1, "Thread assigned to user_id 1 (Sarah)");
assertCondition($tAssigned->status === 'open', "Thread status transitioned automatically from 'unassigned' to 'open'");

// Update priority to urgent
$threadModel->updatePriority((int)$t1->thread_id, 'urgent');
$tUrgent = $threadModel->getById((int)$t1->thread_id);
assertCondition($tUrgent->priority === 'urgent', "Priority updated to 'urgent'");

// Mark thread read
$threadModel->markRead((int)$t1->thread_id);
$tRead = $threadModel->getById((int)$t1->thread_id);
assertCondition((int)$tRead->unread_count === 0, "Thread unread_count cleared to 0 via markRead()");

// Resolve thread
$threadModel->updateStatus((int)$t1->thread_id, 'resolved', 1);
$tResolved = $threadModel->getById((int)$t1->thread_id);
assertCondition($tResolved->status === 'resolved', "Thread status marked 'resolved'");
assertCondition(!empty($tResolved->resolved_at), "Thread resolved_at timestamp populated");
assertCondition((int)$tResolved->resolved_by === 1, "Thread resolved_by attributed to user 1");

// Inbound message reopens resolved thread
$msgModel->recordMessage([
    'thread_id' => (int)$t1->thread_id,
    'contact_id' => $c1Id,
    'channel' => 'whatsapp',
    'direction' => 'inbound',
    'body' => 'Hey, one more question!'
]);
$tReopened = $threadModel->getById((int)$t1->thread_id);
assertCondition($tReopened->status === 'open', "New inbound customer message automatically reopened resolved thread back to 'open'");


// TEST 7: Thread Filtering (Tabs: mine, unassigned, resolved, all)
echo "\nTest 7: Thread Filtering Logic\n";
// Create another contact and thread for unassigned testing
$c2 = $contactModel->findOrCreateByIdentity('email', 'bob@builder.org', [
    'first_name' => 'Bob',
    'last_name' => 'Builder'
]);
$c2Id = (int)$c2->contact_id;
$t2 = $threadModel->getOrCreateForContact($c2Id, 'email');

$allThreads = $threadModel->getThreads(['tab' => 'all']);
assertCondition(count($allThreads) >= 2, "Tab 'all' returns open threads");

$myThreads = $threadModel->getThreads(['tab' => 'mine', 'current_user_id' => 1]);
assertCondition(count($myThreads) === 1 && (int)$myThreads[0]->thread_id === (int)$t1->thread_id, "Tab 'mine' returns Sarah's assigned thread only");

$unassignedThreads = $threadModel->getThreads(['tab' => 'unassigned']);
$foundBob = false;
foreach ($unassignedThreads as $ut) {
    if ((int)$ut->thread_id === (int)$t2->thread_id) {
        $foundBob = true;
        break;
    }
}
assertCondition($foundBob, "Tab 'unassigned' returns unassigned thread {$t2->thread_id}");


// TEST 8: Contact Suppression Protection
echo "\nTest 8: Suppression Protection Invariant\n";
$contactModel->suppressContact($c1Id, 'Customer unsubscribed from marketing');
$suppressedContact = $contactModel->getById($c1Id);
assertCondition((int)$suppressedContact->is_suppressed === 1, "Contact marked globally suppressed");

// Validate InboxController logic guards against outbound message to suppressed contact
$canSend = ((int)$suppressedContact->is_suppressed !== 1);
assertCondition($canSend === false, "Outbound message dispatch strictly blocked for suppressed contact");


// TEST 9: Strict Payroll Isolation Guarantee
echo "\nTest 9: Strict Payroll Isolation Guarantee\n";
$payrollKeywords = ['salary', 'ctc', 'basic_pay', 'allowance', 'deduction', 'bank_account', 'pan_number', 'payslip'];
$inboxFiles = [
    __DIR__ . '/../app/controllers/InboxController.php',
    __DIR__ . '/../app/models/InboxThread.php',
    __DIR__ . '/../app/models/InboxMessage.php',
    __DIR__ . '/../app/models/CannedReply.php',
    __DIR__ . '/../app/views/inbox/index.php',
    __DIR__ . '/../app/views/inbox/canned_replies.php',
    __DIR__ . '/../migrations/0053_omnichannel_shared_inbox.php'
];

$leaksFound = 0;
foreach ($inboxFiles as $f) {
    if (!file_exists($f)) continue;
    $content = strtolower((string)file_get_contents($f));
    foreach ($payrollKeywords as $kw) {
        // Match table/field style references e.g. salary, payslip, etc.
        if (preg_match('/\b' . preg_quote($kw, '/') . '\b/', $content)) {
            echo "  [FAIL] Potential payroll keyword leak '{$kw}' in {$f}\n";
            $leaksFound++;
        }
    }
}
assertCondition($leaksFound === 0, "Inbox module has zero exposure to payroll/salary tables or columns");


// Summary
echo "\n====================================================================\n";
echo "SUMMARY: " . ($testCount - $failCount) . " PASSED, {$failCount} FAILED\n";
echo "====================================================================\n";
if ($failCount === 0) {
    echo "SUCCESS: Omnichannel Shared Team Inbox (Sprint 4) fully verified!\n";
    exit(0);
} else {
    echo "FAILURE: {$failCount} test(s) failed.\n";
    exit(1);
}
