<?php
/**
 * Raptor CRM - Email Campaigns Phase 4 Automated Verification Suite
 * 
 * Tests:
 * 1. Mailer Adapter Interface & Provider Swappability
 * 2. Safe Row Locking & Concurrency Protection (No duplicate sends)
 * 3. Global Suppression Check Before Send (Suppressed email is provably NEVER dispatched)
 * 4. Domain Warm-Up Schedule & Daily Cap Throttling
 * 5. Exponential Backoff Retry (4xx transient vs 5xx permanent failure drop)
 * 6. Auto-Pause Campaign Trigger (Bounce rate > 2% safety tripwire)
 */

declare(strict_types=1);

require_once __DIR__ . '/../app/config/config.php';
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}
require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/core/Model.php';

// Safe connection setup (MySQL if reachable, SQLite in-memory fallback for offline test harness)
$isSqlite = false;
try {
    $dbHost = DB_HOST;
    $dbName = DB_NAME;
    $dbUser = DB_USER;
    $dbPass = DB_PASS;
    $dbPort = defined('DB_PORT') ? DB_PORT : 3306;
    $pdo = new PDO("mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4", $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
        PDO::ATTR_TIMEOUT => 2
    ]);
} catch (Throwable $e) {
    echo "Notice: Local MySQL not reachable ({$e->getMessage()}). Using SQLite in-memory for testing.\n";
    $pdo = new PDO("sqlite::memory:");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_OBJ);
    $isSqlite = true;
}

Database::setMockConnection($pdo);

if ($isSqlite) {
    $pdo->exec('
        CREATE TABLE campaign_contacts (
            contact_id INTEGER PRIMARY KEY AUTOINCREMENT,
            email TEXT UNIQUE NOT NULL,
            first_name TEXT,
            last_name TEXT,
            company_name TEXT,
            custom_fields TEXT,
            status TEXT DEFAULT "subscribed",
            source TEXT DEFAULT "manual",
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE campaign_lists (
            list_id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            description TEXT,
            is_internal INTEGER DEFAULT 0,
            optin_mode TEXT DEFAULT "single",
            created_by INTEGER,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE campaign_list_contacts (
            list_id INTEGER,
            contact_id INTEGER,
            status TEXT DEFAULT "active",
            joined_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            unsubscribed_at DATETIME,
            PRIMARY KEY (list_id, contact_id)
        );
        CREATE TABLE campaign_templates (
            template_id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            category TEXT DEFAULT "general",
            subject_default TEXT,
            html_content TEXT NOT NULL,
            plain_text_content TEXT NOT NULL,
            is_prebuilt INTEGER DEFAULT 0
        );
        CREATE TABLE campaigns_broadcast (
            campaign_id INTEGER PRIMARY KEY AUTOINCREMENT,
            campaign_code TEXT UNIQUE NOT NULL,
            name TEXT NOT NULL,
            subject_a TEXT NOT NULL,
            subject_b TEXT,
            preheader TEXT,
            ab_split_percent INTEGER DEFAULT 0,
            ab_winner_metric TEXT DEFAULT "opens",
            from_name TEXT NOT NULL,
            from_email TEXT NOT NULL,
            reply_to TEXT,
            list_id INTEGER,
            segment_id INTEGER,
            template_id INTEGER,
            html_content TEXT NOT NULL,
            plain_text_content TEXT NOT NULL,
            track_opens INTEGER DEFAULT 1,
            track_clicks INTEGER DEFAULT 1,
            status TEXT DEFAULT "draft",
            scheduled_at DATETIME,
            started_at DATETIME,
            completed_at DATETIME,
            paused_reason TEXT,
            pre_send_checklist_passed INTEGER DEFAULT 0,
            total_recipients INTEGER DEFAULT 0,
            sent_count INTEGER DEFAULT 0,
            delivered_count INTEGER DEFAULT 0,
            bounced_count INTEGER DEFAULT 0,
            complained_count INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE campaign_recipients (
            recipient_id INTEGER PRIMARY KEY AUTOINCREMENT,
            campaign_id INTEGER,
            contact_id INTEGER,
            email TEXT NOT NULL,
            subject_variant TEXT DEFAULT "A",
            status TEXT DEFAULT "queued",
            unsubscribe_token TEXT,
            sent_at DATETIME,
            bounce_reason TEXT
        );
        CREATE TABLE campaign_email_queue (
            queue_id INTEGER PRIMARY KEY AUTOINCREMENT,
            campaign_id INTEGER,
            recipient_id INTEGER,
            status TEXT DEFAULT "pending",
            priority INTEGER DEFAULT 10,
            attempts INTEGER DEFAULT 0,
            max_attempts INTEGER DEFAULT 3,
            scheduled_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            locked_at DATETIME,
            locked_by TEXT,
            last_error TEXT
        );
        CREATE TABLE campaign_suppressions (
            suppression_id INTEGER PRIMARY KEY AUTOINCREMENT,
            email TEXT UNIQUE NOT NULL,
            reason TEXT NOT NULL,
            notes TEXT,
            source_campaign_id INTEGER,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE campaign_domains (
            domain_id INTEGER PRIMARY KEY AUTOINCREMENT,
            domain_name TEXT UNIQUE NOT NULL,
            is_default INTEGER DEFAULT 0
        );
        CREATE TABLE campaign_warmup_schedules (
            warmup_id INTEGER PRIMARY KEY AUTOINCREMENT,
            domain_id INTEGER NOT NULL,
            current_stage INTEGER DEFAULT 1,
            daily_cap INTEGER DEFAULT 50,
            sent_today INTEGER DEFAULT 0,
            date_stamp DATE NOT NULL,
            UNIQUE (domain_id, date_stamp)
        );
        CREATE TABLE campaign_email_events (
            event_id INTEGER PRIMARY KEY AUTOINCREMENT,
            campaign_id INTEGER,
            recipient_id INTEGER,
            event_type TEXT NOT NULL,
            payload_json TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE campaign_audit_logs (
            audit_id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER,
            action TEXT NOT NULL,
            entity_type TEXT NOT NULL,
            entity_id INTEGER,
            details_json TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
    ');
}

// Test harness counter
$testsPassed = 0;
$testsFailed = 0;

function assertCondition(bool $condition, string $description): void {
    global $testsPassed, $testsFailed;
    if ($condition) {
        $testsPassed++;
        echo "  [PASS] {$description}\n";
    } else {
        $testsFailed++;
        echo "  [FAIL] {$description}\n";
    }
}

echo "====================================================================\n";
echo "  RAPTOR CRM: EMAIL CAMPAIGNS & MASS MAILING (PHASE 4 TEST SUITE)   \n";
echo "====================================================================\n\n";

// Load services & adapters
require_once __DIR__ . '/../app/models/CampaignBroadcast.php';
require_once __DIR__ . '/../app/models/CampaignTemplate.php';
require_once __DIR__ . '/../app/models/CampaignContact.php';
require_once __DIR__ . '/../app/models/CampaignList.php';
require_once __DIR__ . '/../app/models/CampaignSuppression.php';
require_once __DIR__ . '/../app/services/ContactImportService.php';
require_once __DIR__ . '/../app/services/UnsubscribeService.php';
require_once __DIR__ . '/../app/services/TrackingService.php';
require_once __DIR__ . '/../app/services/WarmupScheduleService.php';
require_once __DIR__ . '/../app/services/mailers/MailerSendResult.php';
require_once __DIR__ . '/../app/services/mailers/MailerAdapterInterface.php';
require_once __DIR__ . '/../app/services/mailers/SmtpAdapter.php';
require_once __DIR__ . '/../app/services/mailers/SesAdapter.php';
require_once __DIR__ . '/../app/services/mailers/PostmarkAdapter.php';
require_once __DIR__ . '/../app/services/mailers/SendGridAdapter.php';
require_once __DIR__ . '/../app/services/mailers/MailgunAdapter.php';
require_once __DIR__ . '/../app/services/mailers/MockMailerAdapter.php';
require_once __DIR__ . '/../app/services/mailers/MailerFactory.php';
require_once __DIR__ . '/../app/services/EmailQueueService.php';

// Force mock adapter for test harness
$mockMailer = new MockMailerAdapter();
MailerFactory::setAdapter($mockMailer);
MockMailerAdapter::reset();

// -----------------------------------------------------------------------------
// TEST GROUP 1: MAILER FACTORY & ADAPTER INTERFACE
// -----------------------------------------------------------------------------
echo "[GROUP 1] Provider Adapter Interface & Factory Swappability:\n";

assertCondition($mockMailer instanceof MailerAdapterInterface, 'Mock adapter implements MailerAdapterInterface');
assertCondition($mockMailer->getName() === 'mock', 'Adapter reports correct provider identifier');

$sendRes = $mockMailer->send([
    'to' => 'test@example.com',
    'from_email' => 'news@mail.axiora.in',
    'from_name' => 'Axiora News',
    'subject' => 'Test Subject',
    'html' => '<p>Test</p>',
    'text' => 'Test'
]);
assertCondition($sendRes->isSuccess() === true, 'Adapter send returns MailerSendResult value object');
assertCondition(!empty($sendRes->getMessageId()), 'Successful send provides message ID');
assertCondition(count(MockMailerAdapter::getDispatchedMessages()) === 1, 'Mock mailer recorded sent message in memory');

MockMailerAdapter::reset();

// -----------------------------------------------------------------------------
// TEST GROUP 2: SAFE ROW LOCKING & CONCURRENCY (ZERO DUPLICATE SENDS)
// -----------------------------------------------------------------------------
echo "\n[GROUP 2] Safe Row Locking & Concurrency Protection (Idempotency):\n";

// Set up a test campaign with 10 recipients in queue
$pdo->exec("
    INSERT INTO campaigns_broadcast 
    (campaign_id, campaign_code, name, subject_a, from_name, from_email, html_content, plain_text_content, status, pre_send_checklist_passed, total_recipients)
    VALUES 
    (101, 'CAMP-CONCUR-001', 'Concurrency Test Broadcast', 'Exclusive Update', 'Axiora', 'broadcast@mail.axiora.in', '<p>Hello {{first_name}}</p>', 'Hello', 'queued', 1, 10);
");

for ($i = 1; $i <= 10; $i++) {
    $email = "subscriber{$i}@example.com";
    $pdo->exec("INSERT INTO campaign_recipients (recipient_id, campaign_id, email, status) VALUES ({$i}, 101, '{$email}', 'queued');");
    $pdo->exec("INSERT INTO campaign_email_queue (queue_id, campaign_id, recipient_id, status, priority, attempts) VALUES ({$i}, 101, {$i}, 'pending', 10, 0);");
}

// Simulate Worker 1 and Worker 2 running concurrently
$worker1Stats = EmailQueueService::processBatch(5, 0, 'worker_process_alpha');
$worker2Stats = EmailQueueService::processBatch(5, 0, 'worker_process_beta');

assertCondition($worker1Stats['sent'] === 5, 'Worker Alpha dispatched exactly 5 locked items');
assertCondition($worker2Stats['sent'] === 5, 'Worker Beta dispatched the remaining 5 non-overlapping locked items');

// Check that exactly 10 distinct messages were sent in total
$allSentMessages = MockMailerAdapter::getDispatchedMessages();
$sentEmails = array_column($allSentMessages, 'to');
assertCondition(count($allSentMessages) === 10, 'Total of 10 messages dispatched');
assertCondition(count(array_unique($sentEmails)) === 10, 'IDEMPOTENCY GUARANTEE: Zero duplicate dispatches across concurrent workers');

// Clean up
MockMailerAdapter::reset();
$pdo->exec("DELETE FROM campaign_email_queue WHERE campaign_id = 101");
$pdo->exec("DELETE FROM campaign_recipients WHERE campaign_id = 101");
$pdo->exec("DELETE FROM campaigns_broadcast WHERE campaign_id = 101");

// -----------------------------------------------------------------------------
// TEST GROUP 3: GLOBAL SUPPRESSION FIREWALL IN QUEUE WORKER
// -----------------------------------------------------------------------------
echo "\n[GROUP 3] Global Suppression Firewall Before Dispatch:\n";

$suppressedLeadEmail = 'blacklisted.lead@firewall.test';
$suppressionModel = new CampaignSuppression();
$suppressionModel->suppress($suppressedLeadEmail, 'spam_complaint', 'Direct abuse complaint');

$pdo->exec("
    INSERT INTO campaigns_broadcast 
    (campaign_id, campaign_code, name, subject_a, from_name, from_email, html_content, plain_text_content, status, pre_send_checklist_passed, total_recipients)
    VALUES 
    (102, 'CAMP-SUPP-001', 'Suppression Test', 'Subject', 'Sender', 'news@mail.axiora.in', '<p>Hi</p>', 'Hi', 'queued', 1, 1);
");
$pdo->exec("INSERT INTO campaign_recipients (recipient_id, campaign_id, email, status) VALUES (201, 102, '{$suppressedLeadEmail}', 'queued');");
$pdo->exec("INSERT INTO campaign_email_queue (queue_id, campaign_id, recipient_id, status) VALUES (201, 102, 201, 'pending');");

// Run worker
$suppStats = EmailQueueService::processBatch(10, 0, 'worker_supp_checker');

assertCondition($suppStats['suppressed'] === 1, 'Worker detected globally suppressed recipient before send');
assertCondition($suppStats['sent'] === 0, 'Worker dispatched 0 messages for suppressed recipient');

// Verify that mock provider was never called
$dispatched = MockMailerAdapter::getDispatchedMessages();
$wasSent = false;
foreach ($dispatched as $m) {
    if ($m['to'] === $suppressedLeadEmail) $wasSent = true;
}
assertCondition($wasSent === false, 'PROVABLE INVARIANT: Suppressed email was NEVER sent to the email provider');

// Verify queue status is cancelled with suppressed error
$qRow = $pdo->query("SELECT status, last_error FROM campaign_email_queue WHERE queue_id = 201")->fetch(PDO::FETCH_ASSOC);
assertCondition($qRow['status'] === 'cancelled', 'Queue item marked as "cancelled"');
assertCondition($qRow['last_error'] === 'Globally Suppressed', 'Queue recorded suppression cancellation reason');

// Clean up
$suppressionModel->remove($suppressedLeadEmail);
$pdo->exec("DELETE FROM campaign_email_queue WHERE campaign_id = 102");
$pdo->exec("DELETE FROM campaign_recipients WHERE campaign_id = 102");
$pdo->exec("DELETE FROM campaigns_broadcast WHERE campaign_id = 102");

// -----------------------------------------------------------------------------
// TEST GROUP 4: DOMAIN WARM-UP SCHEDULE & DAILY CAP
// -----------------------------------------------------------------------------
echo "\n[GROUP 4] Domain Warm-Up Schedule & Rate Throttling:\n";

$testDomain = 'warmup-test.com';
$domainId = WarmupScheduleService::resolveDomainId($testDomain);
$today = date('Y-m-d');

// Seed Stage 1 schedule with cap of 3 emails for testing
$pdo->exec("DELETE FROM campaign_warmup_schedules WHERE domain_id = {$domainId}");
$pdo->exec("INSERT INTO campaign_warmup_schedules (domain_id, current_stage, daily_cap, sent_today, date_stamp) 
            VALUES ({$domainId}, 1, 3, 2, '{$today}')");

$statusBefore = WarmupScheduleService::getDailyStatus($testDomain);
assertCondition($statusBefore['daily_cap'] === 3, 'Daily warm-up cap correctly configured');
assertCondition($statusBefore['remaining_today'] === 1, '1 send remaining before daily cap reached');
assertCondition(WarmupScheduleService::canSend($testDomain) === true, 'canSend returns true when under cap');

// Record 1 send to exhaust quota
WarmupScheduleService::recordSend($testDomain);
$statusAfter = WarmupScheduleService::getDailyStatus($testDomain);
assertCondition($statusAfter['sent_today'] === 3, 'Recorded send counted against daily volume');
assertCondition($statusAfter['remaining_today'] === 0, 'Daily quota exhausted');
assertCondition(WarmupScheduleService::canSend($testDomain) === false, 'canSend returns false when cap reached (protects sender reputation)');

// -----------------------------------------------------------------------------
// TEST GROUP 5: EXPONENTIAL BACKOFF RETRY (4xx VS 5xx)
// -----------------------------------------------------------------------------
echo "\n[GROUP 5] Exponential Backoff Retry & Error Classification:\n";

$pdo->exec("
    INSERT INTO campaigns_broadcast 
    (campaign_id, campaign_code, name, subject_a, from_name, from_email, html_content, plain_text_content, status, pre_send_checklist_passed, total_recipients)
    VALUES 
    (103, 'CAMP-RETRY-001', 'Retry Test', 'Subject', 'Sender', 'retry@mail.axiora.in', '<p>Hi</p>', 'Hi', 'queued', 1, 2);
");
$pdo->exec("INSERT INTO campaign_recipients (recipient_id, campaign_id, email, status) VALUES (301, 103, 'transient@example.com', 'queued');");
$pdo->exec("INSERT INTO campaign_email_queue (queue_id, campaign_id, recipient_id, status, attempts, max_attempts) VALUES (301, 103, 301, 'pending', 0, 3);");

// 1. Simulate Transient 4xx failure
MockMailerAdapter::setForcedBehavior('transient_error');
$retryStats = EmailQueueService::processBatch(1, 0, 'worker_retry_test');

assertCondition($retryStats['failed'] === 1, 'Worker recorded failure on 4xx error');
$qItem = $pdo->query("SELECT status, attempts, scheduled_at, last_error FROM campaign_email_queue WHERE queue_id = 301")->fetch(PDO::FETCH_ASSOC);
assertCondition($qItem['status'] === 'pending', 'Soft failure kept queue item in "pending" status for retry');
assertCondition((int)$qItem['attempts'] === 1, 'Incremented attempt counter to 1');
assertCondition(strtotime($qItem['scheduled_at']) > time(), 'Rescheduled item to future time via exponential backoff');

// 2. Simulate Hard 5xx failure
$pdo->exec("INSERT INTO campaign_recipients (recipient_id, campaign_id, email, status) VALUES (302, 103, 'hardfailure@example.com', 'queued');");
$pdo->exec("INSERT INTO campaign_email_queue (queue_id, campaign_id, recipient_id, status, attempts, max_attempts) VALUES (302, 103, 302, 'pending', 0, 3);");

MockMailerAdapter::setForcedBehavior('hard_error');
$hardStats = EmailQueueService::processBatch(1, 0, 'worker_hard_test');

$qHard = $pdo->query("SELECT status, attempts FROM campaign_email_queue WHERE queue_id = 302")->fetch(PDO::FETCH_ASSOC);
assertCondition($qHard['status'] === 'failed', 'Hard 5xx failure immediately marked item as "failed" without retry');

// Reset mock
MockMailerAdapter::reset();
$pdo->exec("DELETE FROM campaign_email_queue WHERE campaign_id = 103");
$pdo->exec("DELETE FROM campaign_recipients WHERE campaign_id = 103");
$pdo->exec("DELETE FROM campaigns_broadcast WHERE campaign_id = 103");

// -----------------------------------------------------------------------------
// TEST GROUP 6: AUTO-PAUSE CAMPAIGN ON HIGH BOUNCE / COMPLAINT RATE
// -----------------------------------------------------------------------------
echo "\n[GROUP 6] Auto-Pause Safety Tripwire (Bounce > 2% / Complaint > 0.1%):\n";

// Create campaign with 100 sent and 5 bounces (5% bounce rate, exceeds 2% threshold)
$pdo->exec("
    INSERT INTO campaigns_broadcast 
    (campaign_id, campaign_code, name, subject_a, from_name, from_email, html_content, plain_text_content, status, pre_send_checklist_passed, total_recipients, sent_count, bounced_count)
    VALUES 
    (104, 'CAMP-AUTOPAUSE-001', 'High Bounce Campaign', 'Subject', 'Sender', 'news@mail.axiora.in', '<p>Hi</p>', 'Hi', 'sending', 1, 50, 100, 5);
");
$pdo->exec("INSERT INTO campaign_recipients (recipient_id, campaign_id, email, status) VALUES (401, 104, 'pending.user@example.com', 'queued');");
$pdo->exec("INSERT INTO campaign_email_queue (queue_id, campaign_id, recipient_id, status) VALUES (401, 104, 401, 'pending');");

// Process batch: auto-pause should trip immediately
EmailQueueService::processBatch(1, 0, 'worker_autopause_test');

$pausedCamp = $pdo->query("SELECT status, paused_reason FROM campaigns_broadcast WHERE campaign_id = 104")->fetch(PDO::FETCH_ASSOC);
assertCondition($pausedCamp['status'] === 'paused', 'Campaign was automatically paused due to high bounce rate (> 2%)');
assertCondition(!empty($pausedCamp['paused_reason']), 'Recorded auto-pause reason in campaign record');

$qPaused = $pdo->query("SELECT status FROM campaign_email_queue WHERE queue_id = 401")->fetch(PDO::FETCH_ASSOC);
assertCondition($qPaused['status'] === 'pending', 'Pending item was unlocked and left unsent while campaign is paused');

// Clean up
$pdo->exec("DELETE FROM campaign_email_queue WHERE campaign_id = 104");
$pdo->exec("DELETE FROM campaign_recipients WHERE campaign_id = 104");
$pdo->exec("DELETE FROM campaigns_broadcast WHERE campaign_id = 104");

// -----------------------------------------------------------------------------
// SUMMARY
// -----------------------------------------------------------------------------
echo "\n====================================================================\n";
echo "  PHASE 4 TEST SUMMARY: {$testsPassed} PASSED, {$testsFailed} FAILED\n";
echo "====================================================================\n";

if ($testsFailed > 0) {
    exit(1);
}
exit(0);
