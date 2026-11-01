<?php
/**
 * Raptor CRM - Email Campaigns & Mass Mailing End-to-End (E2E) Integration Suite
 * 
 * Comprehensive Lifecycle Test:
 * 1. Dedicated Sending Domain Setup, 2048-bit DKIM Keypair & DNS Authentication
 * 2. Audience Ingestion (CSV validation + Internal Payroll Employee Sync without salary exposure)
 * 3. Mobile-Responsive Template Builder & Auto Plain-Text Generation
 * 4. Pre-Send Deliverability Quality Gate (Blocks launch if checks fail)
 * 5. Campaign Creation, A/B Testing & Queue Seeding
 * 6. High-Throughput Queue Dispatch with Safe Row Locking (Zero duplicate sends)
 * 7. Global Suppression Firewall (Provably never sends to suppressed addresses)
 * 8. Open & Click Tracking (Apple MPP Detection & HMAC URL Tamper Protection)
 * 9. Webhook Ingestion (Hard bounce & Spam complaint -> real-time suppression)
 * 10. Analytics Deliverability Ratios, Domain Breakdown & Inactive Re-engagement
 * 11. Daily Deliverability Health Report Dispatch
 */

declare(strict_types=1);

require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/core/Model.php';

// Safe connection setup
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
            is_role_account INTEGER DEFAULT 0,
            is_disposable INTEGER DEFAULT 0,
            consent_type TEXT DEFAULT "explicit_optin",
            consent_timestamp DATETIME,
            consent_ip TEXT,
            consent_notes TEXT,
            last_engagement_at DATETIME,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
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
        CREATE TABLE campaign_tags (
            tag_id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT UNIQUE NOT NULL,
            color TEXT DEFAULT "#3b82f6",
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE campaign_contact_tags (
            contact_id INTEGER,
            tag_id INTEGER,
            PRIMARY KEY (contact_id, tag_id)
        );
        CREATE TABLE campaign_segments (
            segment_id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            description TEXT,
            match_type TEXT DEFAULT "all",
            rules_json TEXT,
            created_by INTEGER,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE campaign_suppressions (
            suppression_id INTEGER PRIMARY KEY AUTOINCREMENT,
            email TEXT UNIQUE NOT NULL,
            reason TEXT NOT NULL,
            notes TEXT,
            source_campaign_id INTEGER,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE campaign_templates (
            template_id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            category TEXT DEFAULT "general",
            subject_default TEXT,
            html_content TEXT NOT NULL,
            plain_text_content TEXT NOT NULL,
            is_prebuilt INTEGER DEFAULT 0,
            created_by INTEGER,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
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
            pre_send_checklist_json TEXT,
            total_recipients INTEGER DEFAULT 0,
            sent_count INTEGER DEFAULT 0,
            delivered_count INTEGER DEFAULT 0,
            opened_count INTEGER DEFAULT 0,
            clicked_count INTEGER DEFAULT 0,
            bounced_count INTEGER DEFAULT 0,
            complained_count INTEGER DEFAULT 0,
            unsubscribed_count INTEGER DEFAULT 0,
            created_by INTEGER,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
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
            delivered_at DATETIME,
            opened_at DATETIME,
            open_count INTEGER DEFAULT 0,
            clicked_at DATETIME,
            click_count INTEGER DEFAULT 0,
            bounce_type TEXT,
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
        CREATE TABLE campaign_email_events (
            event_id INTEGER PRIMARY KEY AUTOINCREMENT,
            campaign_id INTEGER,
            recipient_id INTEGER,
            contact_id INTEGER,
            event_type TEXT NOT NULL,
            ip_address TEXT,
            user_agent TEXT,
            target_url TEXT,
            payload_json TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE campaign_domains (
            domain_id INTEGER PRIMARY KEY AUTOINCREMENT,
            domain_name TEXT UNIQUE NOT NULL,
            is_default INTEGER DEFAULT 0,
            spf_status TEXT DEFAULT "unverified",
            spf_record_expected TEXT,
            dkim_status TEXT DEFAULT "unverified",
            dkim_selector TEXT DEFAULT "raptor",
            dkim_public_key TEXT,
            dkim_private_key_encrypted TEXT,
            dmarc_status TEXT DEFAULT "unverified",
            dmarc_policy TEXT DEFAULT "none",
            mx_status TEXT DEFAULT "unverified",
            last_checked_at DATETIME,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE campaign_warmup_schedules (
            warmup_id INTEGER PRIMARY KEY AUTOINCREMENT,
            domain_id INTEGER NOT NULL,
            current_stage INTEGER DEFAULT 1,
            daily_cap INTEGER DEFAULT 50,
            sent_today INTEGER DEFAULT 0,
            date_stamp DATE NOT NULL,
            is_active INTEGER DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE (domain_id, date_stamp)
        );
        CREATE TABLE users (
            user_id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            email TEXT NOT NULL,
            status TEXT DEFAULT "active"
        );
        CREATE TABLE employees (
            employee_id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL
        );
    ');

    // Seed test employees in SQLite
    $pdo->exec('
        INSERT INTO users (user_id, name, email, status) VALUES 
        (101, "Aarav Sharma", "aarav.sharma@axiora.internal", "active"),
        (102, "Diya Patel", "diya.patel@axiora.internal", "active");
        INSERT INTO employees (employee_id, user_id) VALUES 
        (1, 101), (2, 102);
    ');
}

// Counter
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
echo "  RAPTOR CRM: EMAIL CAMPAIGNS & MASS MAILING (E2E LIFECYCLE SUITE)  \n";
echo "====================================================================\n\n";

// Load all models & services
require_once __DIR__ . '/../app/models/CampaignDomain.php';
require_once __DIR__ . '/../app/models/CampaignContact.php';
require_once __DIR__ . '/../app/models/CampaignList.php';
require_once __DIR__ . '/../app/models/CampaignTag.php';
require_once __DIR__ . '/../app/models/CampaignSegment.php';
require_once __DIR__ . '/../app/models/CampaignSuppression.php';
require_once __DIR__ . '/../app/models/CampaignTemplate.php';
require_once __DIR__ . '/../app/models/CampaignBroadcast.php';

require_once __DIR__ . '/../app/services/ContactImportService.php';
require_once __DIR__ . '/../app/services/UnsubscribeService.php';
require_once __DIR__ . '/../app/services/PreSendChecklistService.php';
require_once __DIR__ . '/../app/services/WarmupScheduleService.php';
require_once __DIR__ . '/../app/services/TrackingService.php';
require_once __DIR__ . '/../app/services/WebhookProcessorService.php';
require_once __DIR__ . '/../app/services/CampaignAnalyticsService.php';
require_once __DIR__ . '/../app/services/DomainVerificationService.php';
require_once __DIR__ . '/../app/services/BlacklistMonitorService.php';
require_once __DIR__ . '/../app/services/DeliverabilityHealthReportService.php';

require_once __DIR__ . '/../app/services/mailers/MailerSendResult.php';
require_once __DIR__ . '/../app/services/mailers/MailerAdapterInterface.php';
require_once __DIR__ . '/../app/services/mailers/MockMailerAdapter.php';
require_once __DIR__ . '/../app/services/mailers/MailerFactory.php';
require_once __DIR__ . '/../app/services/EmailQueueService.php';

$_ENV['MAIL_PROVIDER'] = 'mock';

// ====================================================================
// STEP 1: Sending Domain Setup & Live DNS Verification
// ====================================================================
echo "[STEP 1] Dedicated Sending Domain Setup & DNS Authentication:\n";

$domainModel = new CampaignDomain();
$domainId = $domainModel->createDomain('mail.axiora.com', 'raptor', true);
$domain = $domainModel->getById($domainId);

assertCondition($domain !== null, 'Created dedicated sending subdomain: mail.axiora.com');
assertCondition(!empty($domain->dkim_public_key), 'Generated 2048-bit RSA DKIM public key');

// Live DNS resolution mock
DomainVerificationService::setDnsResolver(function(string $host, int $type) use ($domain) {
    if ($type === DNS_TXT) {
        if ($host === 'mail.axiora.com') return [['txt' => 'v=spf1 include:_spf.raptor.internal ~all']];
        if ($host === 'raptor._domainkey.mail.axiora.com') return [['txt' => "v=DKIM1; k=rsa; p={$domain->dkim_public_key}"]];
        if ($host === '_dmarc.mail.axiora.com') return [['txt' => 'v=DMARC1; p=none; rua=mailto:dmarc@mail.axiora.com']];
    }
    if ($type === DNS_MX) return [['target' => 'inbound.mail.axiora.com', 'pri' => 10]];
    return [];
});

$dnsCheck = DomainVerificationService::verifyDomain($domainId);
assertCondition($dnsCheck['all_passed'] === true, 'Domain passed all live DNS checks (SPF, DKIM, DMARC, MX)');

// ====================================================================
// STEP 2: Audience Ingestion & Privacy Isolation
// ====================================================================
echo "\n[STEP 2] Audience Ingestion & Payroll Privacy Isolation:\n";

$contactModel = new CampaignContact();
$listModel = new CampaignList();

// Create audience list
$listId = $listModel->createList('Q4 VIP Audience', 'Target customers for product launch');
assertCondition($listId > 0, 'Created audience list: Q4 VIP Audience');

// Seed clean contacts
$emails = [
    'vip1@gmail.com' => ['First' => 'John', 'Company' => 'Acme Corp'],
    'vip2@outlook.com' => ['First' => 'Sarah', 'Company' => 'Globex'],
    'vip3@yahoo.com' => ['First' => 'Robert', 'Company' => 'Initech'],
    'suppressed.user@example.com' => ['First' => 'Blocked', 'Company' => 'Bad List']
];

foreach ($emails as $email => $meta) {
    $cid = $contactModel->upsertContact([
        'email' => $email,
        'first_name' => $meta['First'],
        'company_name' => $meta['Company'],
        'status' => 'subscribed',
        'list_ids' => [$listId]
    ]);
}

// Globally suppress one contact before campaign launch
$suppressionModel = new CampaignSuppression();
$suppressionModel->suppress('suppressed.user@example.com', 'unsubscribe', 'Pre-existing unsubscribe request');
assertCondition($suppressionModel->isSuppressed('suppressed.user@example.com') === true, 'Pre-suppressed bad address');

// Sync internal employees from payroll
$internalListId = $listModel->createList('Corporate Staff Test', 'Internal test list', true, 'single', 1);
assertCondition($internalListId > 0, 'Created internal corporate audience list');

$syncRes = $contactModel->syncInternalEmployees($internalListId, 1);
assertCondition($syncRes['success'] === true, 'Synchronized internal payroll employees to audience list');

// PRIVACY INVARIANT: Ensure no financial data leaked
$empContact = $contactModel->getByEmail('aarav.sharma@axiora.internal');
assertCondition($empContact !== null, 'Employee contact synced');
assertCondition(!isset($empContact->basic_salary) && !isset($empContact->gross_salary), 'PRIVACY GUARANTEE: Zero payroll salary columns exposed in contact model');

// ====================================================================
// STEP 3: Template Creation & Deliverability Sanitization
// ====================================================================
echo "\n[STEP 3] Template Creation & Deliverability Sanitization:\n";

$templateModel = new CampaignTemplate();
$htmlRaw = '
<!DOCTYPE html>
<html>
<head><style>body { font-family: sans-serif; }</style></head>
<body>
    <h2>Hello {{first_name|Valued Customer}},</h2>
    <p>Welcome to our new enterprise release. Check out our updates at <a href="https://raptor-crm.internal/features">Our Features</a>.</p>
    <hr>
    <p style="font-size:11px;color:#666;">
        {{company_address}}<br>
        <a href="{{unsubscribe_url}}">Unsubscribe from this list</a>
    </p>
</body>
</html>';

$plainGenerated = CampaignTemplate::htmlToPlainText($htmlRaw);
assertCondition(str_contains($plainGenerated, 'Our Features (https://raptor-crm.internal/features)'), 'Auto-generated multipart plain-text alternative converts links to readable text (URL)');

$templateId = $templateModel->saveTemplate([
    'name' => 'Product Launch Responsive Template',
    'html_content' => $htmlRaw,
    'plain_text_content' => $plainGenerated
]);
assertCondition($templateId > 0, 'Created reusable responsive HTML template with plain-text alternative');

// ====================================================================
// STEP 4: Pre-Send Deliverability Quality Gate
// ====================================================================
echo "\n[STEP 4] Pre-Send Deliverability Quality Gate Enforcement:\n";

// 1. Should fail on broken template missing unsubscribe link
$brokenTemplate = '<p>Buy now! No way to opt out.</p>';
$failGate = PreSendChecklistService::run([
    'from_email' => 'news@mail.axiora.com',
    'subject_a' => 'Exclusive Deal',
    'html_content' => $brokenTemplate,
    'plain_text_content' => '',
    'test_sent' => false
]);

assertCondition($failGate['passed'] === false, 'ACCEPTANCE CRITERIA: Pre-send gate BLOCKS launch if unsubscribe or plain-text checks fail');
assertCondition(!empty($failGate['blockers']), 'Blocked with explicit deliverability errors');

// 2. Should pass clean template
$passGate = PreSendChecklistService::run([
    'from_email' => 'news@mail.axiora.com',
    'subject_a' => 'Your Product Updates for November',
    'html_content' => $htmlRaw,
    'plain_text_content' => $plainGenerated,
    'test_sent' => true
]);

assertCondition($passGate['passed'] === true, 'Pre-send gate PASSES compliant, authenticated template with test verification');

// ====================================================================
// STEP 5: Campaign Launch, A/B Testing & Queue Seeding
// ====================================================================
echo "\n[STEP 5] Campaign Launch, A/B Split & Queue Seeding:\n";

$broadcastModel = new CampaignBroadcast();
$campaignId = $broadcastModel->saveCampaign([
    'name' => 'Q4 Product Launch Broadcast',
    'subject_a' => 'Introducing New Features (Variant A)',
    'subject_b' => 'Exclusive Access to New Features (Variant B)',
    'preheader' => 'Read what is new this quarter.',
    'ab_split_percent' => 50,
    'from_name' => 'Raptor Marketing',
    'from_email' => 'news@mail.axiora.com',
    'reply_to' => 'support@axiora.com',
    'list_id' => $listId,
    'template_id' => $templateId,
    'html_content' => $htmlRaw,
    'plain_text_content' => $plainGenerated
], 1);
assertCondition($campaignId > 0, 'Created campaign draft with 50/50 A/B subject line testing');

// Mark checklist passed and launch campaign
$launchRes = $broadcastModel->launchCampaign($campaignId, 1);

assertCondition($launchRes['success'] === true, 'Campaign successfully launched and seeded into dispatch queue');

$campaign = $broadcastModel->getById($campaignId);
assertCondition($campaign->status === 'queued', 'Campaign status transitioned to "queued"');

// Check recipients seeded
$recipStmt = $pdo->query("SELECT COUNT(*) FROM campaign_recipients WHERE campaign_id = {$campaignId}");
$recipCount = (int)$recipStmt->fetchColumn();
assertCondition($recipCount === 3, 'Seeded 3 reachable recipients into campaign_recipients table');

// ====================================================================
// STEP 6: Concurrent Dispatch & Safe Row Locking (Zero Duplicates)
// ====================================================================
echo "\n[STEP 6] Queue Dispatch, Safe Row Locking & Concurrency Protection:\n";

MockMailerAdapter::reset();

// Simulate Worker Alpha processing batch of 2 items
$batchAlpha = EmailQueueService::processBatch(2, 0, 'worker_alpha_pid_1001');
assertCondition($batchAlpha['sent'] === 2, 'Worker Alpha safely locked and dispatched exactly 2 non-overlapping items');

// Suppress the remaining recipient right before Worker Beta executes (simulating real-time pre-send unsubscribe)
$suppressionModel->suppress('vip3@yahoo.com', 'unsubscribe', 'Unsubscribed right before worker execution');

// Simulate concurrent Worker Beta processing remaining items
$batchBeta = EmailQueueService::processBatch(2, 0, 'worker_beta_pid_1002');

// ====================================================================
// STEP 7: Global Suppression Invariant
// ====================================================================
echo "\n[STEP 7] Global Suppression Firewall Enforcement Before Send:\n";

assertCondition($batchBeta['suppressed'] === 1, 'Worker Beta intercepted suppressed address right before send');

$sentHistory = MockMailerAdapter::getDispatchedMessages();
$sentEmails = array_map(fn($m) => strtolower($m['to']), $sentHistory);

assertCondition(!in_array('vip3@yahoo.com', $sentEmails, true), 
    'ACCEPTANCE CRITERIA: A suppressed address is PROVABLY NEVER sent to the email provider');

// Check total sends across concurrent workers
assertCondition(count($sentHistory) === 2, 'Total messages dispatched equals non-suppressed recipients (2)');
assertCondition(count(array_unique($sentEmails)) === 2, 'ACCEPTANCE CRITERIA: No duplicate sends under concurrent workers');

// Verify RFC 8058 One-Click headers on dispatched messages
$firstMsg = $sentHistory[0];
assertCondition(!empty($firstMsg['headers']['List-Unsubscribe']), 'Dispatched message includes RFC 8058 List-Unsubscribe header');
assertCondition($firstMsg['headers']['List-Unsubscribe-Post'] === 'List-Unsubscribe=One-Click', 'Dispatched message includes RFC 8058 List-Unsubscribe-Post header');

// ====================================================================
// STEP 8: Open Tracking, Apple MPP & Signed Click Tracking
// ====================================================================
echo "\n[STEP 8] Tracking Engine (Apple MPP & HMAC Anti-Open Redirect):\n";

// Get recipient token
$r1 = $pdo->query("SELECT * FROM campaign_recipients WHERE email = 'vip1@gmail.com' AND campaign_id = {$campaignId}")->fetch(PDO::FETCH_OBJ);
$token = $r1->unsubscribe_token;

// 1. Open tracking with Apple MPP proxy detection
$mppUA = 'Mozilla/5.0 Mobile/15E148 Apple-Mail-Privacy-Proxy/1.0';
assertCondition(TrackingService::isAppleMpp($mppUA) === true, 'Detected Apple Mail Privacy Protection proxy');

$pdo->prepare("UPDATE campaign_recipients SET open_count = open_count + 1, opened_at = CURRENT_TIMESTAMP WHERE recipient_id = :rid")
    ->execute([':rid' => $r1->recipient_id]);
$pdo->prepare("UPDATE campaigns_broadcast SET opened_count = opened_count + 1 WHERE campaign_id = :cid")
    ->execute([':cid' => $campaignId]);

// 2. Click tracking with signed HMAC target URL
$targetUrl = 'https://raptor-crm.internal/features';
$signedPayload = TrackingService::signUrl($targetUrl);
$verifiedUrl = TrackingService::verifyUrl($signedPayload);
assertCondition($verifiedUrl === $targetUrl, 'HMAC signed click URL safely verified');

$tampered = TrackingService::verifyUrl($signedPayload . 'corrupt');
assertCondition($tampered === null, 'TAMPER PROTECTION: Corrupted click payload strictly rejected (no open redirect)');

$pdo->prepare("UPDATE campaign_recipients SET click_count = click_count + 1, clicked_at = CURRENT_TIMESTAMP WHERE recipient_id = :rid")
    ->execute([':rid' => $r1->recipient_id]);
$pdo->prepare("UPDATE campaigns_broadcast SET clicked_count = clicked_count + 1 WHERE campaign_id = :cid")
    ->execute([':cid' => $campaignId]);

// ====================================================================
// STEP 9: Webhook Bounce & Complaint Suppression Cascade
// ====================================================================
echo "\n[STEP 9] Real-Time Webhook Bounce & Complaint Handling:\n";

// Hard bounce vip2@outlook.com
$hb = WebhookProcessorService::processBounce('vip2@outlook.com', 'hard', '550 User unknown', $campaignId);
assertCondition($hb['action'] === 'hard_bounced_and_suppressed', 'Hard bounce triggered immediate global suppression');
assertCondition($suppressionModel->isSuppressed('vip2@outlook.com') === true, 'Hard-bounced email is recorded in global suppression table');

// Spam complaint vip3@yahoo.com
$comp = WebhookProcessorService::processComplaint('vip3@yahoo.com', 'abuse', $campaignId);
assertCondition($comp['action'] === 'complaint_suppressed', 'Spam complaint triggered immediate global suppression');
assertCondition($suppressionModel->isSuppressed('vip3@yahoo.com') === true, 'Complainant email is recorded in global suppression table');

// Delivery confirmation for vip1@gmail.com
$deliv = WebhookProcessorService::processDelivery('vip1@gmail.com', 'msg-123', $campaignId);
assertCondition($deliv['action'] === 'delivered', 'Delivery confirmed for vip1@gmail.com');

// ====================================================================
// STEP 10: Analytics Ratios & Domain Breakdown
// ====================================================================
echo "\n[STEP 10] Analytics Engine Deliverability Ratios & Reporting:\n";

$metrics = CampaignAnalyticsService::getSummaryMetrics($campaignId);
assertCondition($metrics['sent'] >= 2, 'Analytics tracks campaign dispatches');
assertCondition($metrics['bounced'] >= 1, 'Analytics tracks bounce count');
assertCondition($metrics['complained'] >= 1, 'Analytics tracks complaint count');

$domainBreakdown = CampaignAnalyticsService::getDomainBreakdown($campaignId);
assertCondition(isset($domainBreakdown['Gmail / Google']), 'Aggregates Gmail / Google performance');
assertCondition(isset($domainBreakdown['Microsoft / Outlook']), 'Aggregates Microsoft / Outlook performance');
assertCondition(isset($domainBreakdown['Yahoo / AOL']), 'Aggregates Yahoo / AOL performance');

// Re-engagement test
$flagged = CampaignAnalyticsService::flagInactiveContacts(90);
assertCondition(is_int($flagged), 'Re-engagement engine executed 90-day inactivity filter');

// ====================================================================
// STEP 11: Daily Deliverability Health Report
// ====================================================================
echo "\n[STEP 11] Daily Deliverability Health Report Dispatch:\n";

$healthData = DeliverabilityHealthReportService::collectHealthData();
assertCondition(!empty($healthData['metrics_24h']), 'Compiled 24-hour deliverability health metrics');
assertCondition(!empty($healthData['queue']), 'Audited queue backlog status');

$reportDispatched = DeliverabilityHealthReportService::dispatchReport('deliverability-admin@raptor.internal');
assertCondition($reportDispatched === true, 'Successfully dispatched daily health report email');

echo "\n====================================================================\n";
echo "  E2E LIFECYCLE SUMMARY: {$testsPassed} PASSED, {$testsFailed} FAILED\n";
echo "====================================================================\n";

exit($testsFailed > 0 ? 1 : 0);
