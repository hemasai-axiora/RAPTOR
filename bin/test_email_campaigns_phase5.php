<?php
/**
 * Raptor CRM - Email Campaigns Phase 5 Automated Verification Suite
 * 
 * Tests:
 * 1. Open Tracking, 1x1 Transparent GIF & Apple Mail Privacy Protection (MPP) Detection
 * 2. HMAC Signed Click Tracking & Open-Redirect Protection
 * 3. Webhook Bounce Processing (Hard Bounce immediate suppression; 3x Soft Bounce threshold)
 * 4. Webhook Spam Complaint Processing (Immediate suppression & contact status cascade)
 * 5. Webhook Delivery Processing & Event Auditing
 * 6. Analytics KPI Aggregation, Delivery/Open/CTR/CTOR Rates & Domain Breakdown
 * 7. Inactive Subscriber Re-Engagement Flagger (90-day inactivity filter)
 * 8. Analytics CSV Report Generation
 */

declare(strict_types=1);

require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../vendor/autoload.php';
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
            slug TEXT UNIQUE NOT NULL,
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
        CREATE TABLE campaign_suppressions (
            suppression_id INTEGER PRIMARY KEY AUTOINCREMENT,
            email TEXT UNIQUE NOT NULL,
            reason TEXT NOT NULL,
            notes TEXT,
            source_campaign_id INTEGER,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
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
            opened_count INTEGER DEFAULT 0,
            clicked_count INTEGER DEFAULT 0,
            bounced_count INTEGER DEFAULT 0,
            complained_count INTEGER DEFAULT 0,
            unsubscribed_count INTEGER DEFAULT 0,
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
echo "  RAPTOR CRM: EMAIL CAMPAIGNS & MASS MAILING (PHASE 5 TEST SUITE)   \n";
echo "====================================================================\n\n";

// Load models & services
require_once __DIR__ . '/../app/models/CampaignBroadcast.php';
require_once __DIR__ . '/../app/models/CampaignContact.php';
require_once __DIR__ . '/../app/models/CampaignList.php';
require_once __DIR__ . '/../app/models/CampaignSuppression.php';
require_once __DIR__ . '/../app/services/ContactImportService.php';
require_once __DIR__ . '/../app/services/UnsubscribeService.php';
require_once __DIR__ . '/../app/services/TrackingService.php';
require_once __DIR__ . '/../app/services/WebhookProcessorService.php';
require_once __DIR__ . '/../app/services/CampaignAnalyticsService.php';

// ====================================================================
// GROUP 1: Open Tracking & Apple Mail Privacy Protection (MPP) Detection
// ====================================================================
echo "[GROUP 1] Open Tracking Pixel & Apple MPP Detection:\n";

$gif = TrackingService::getTransparentGif();
assertCondition(strlen($gif) === 42, 'Generated 42-byte valid 1x1 transparent GIF');
assertCondition(str_starts_with($gif, 'GIF89a'), 'Transparent GIF has valid GIF89a magic bytes header');

$mppUserAgent = 'Mozilla/5.0 AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148 (Apple; iPhone; CPU OS 15_0 like Mac OS X) Apple-Mail-Privacy-Proxy/1.0';
$normalChromeUA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

assertCondition(TrackingService::isAppleMpp($mppUserAgent) === true, 'Correctly detected Apple Mail Privacy Protection proxy user agent');
assertCondition(TrackingService::isAppleMpp($normalChromeUA) === false, 'Standard Chrome desktop browser is not flagged as Apple MPP');

// HTML Injection Test
$rawHtml = '<html><body><p>Hello world!</p><a href="https://example.com/learn">Learn More</a></body></html>';
$token = 'test_unsub_hmac_token_12345';
$trackedHtml = TrackingService::injectTracking($rawHtml, $token, true, true);

assertCondition(str_contains($trackedHtml, 'campaign_tracking/open'), 'Injected open tracking pixel route into HTML');
assertCondition(str_contains($trackedHtml, 'campaign_tracking/click'), 'Wrapped hyperlink with signed click tracking endpoint');
assertCondition(str_contains($trackedHtml, 'width="1" height="1"'), 'Tracking pixel has 1x1 invisible dimensions');

// ====================================================================
// GROUP 2: HMAC Signed Click Tracking & Anti-Open Redirect Protection
// ====================================================================
echo "\n[GROUP 2] Signed Click Tracking & Anti-Open Redirect Protection:\n";

$targetUrl = 'https://raptor-crm.internal/portal/features?ref=newsletter';
$signedPayload = TrackingService::signUrl($targetUrl);

assertCondition(!empty($signedPayload), 'Successfully generated HMAC signed payload for target URL');

$verifiedUrl = TrackingService::verifyUrl($signedPayload);
assertCondition($verifiedUrl === $targetUrl, 'Legitimate signed URL verified and restored without modification');

// Security Tamper Test: Modify payload
$tamperedPayload = $signedPayload . 'evil';
$tamperResult = TrackingService::verifyUrl($tamperedPayload);
assertCondition($tamperResult === null, 'TAMPER PROTECTION: Corrupted or forged URL payload rejected');

// Open Redirect Exploitation Test: Malicious JavaScript or file pseudo-protocols
$forgedData = base64_encode(json_encode(['u' => 'javascript:alert(1)', 's' => 'invalidsig']));
$exploitResult = TrackingService::verifyUrl($forgedData);
assertCondition($exploitResult === null, 'SECURITY SAFEGUARD: Non-HTTP(S) schemes rejected (no open redirect / XSS)');

// ====================================================================
// GROUP 3: Webhook Bounce Processing & Suppression Enforcement
// ====================================================================
echo "\n[GROUP 3] Webhook Bounce Processing & Suppression Rules:\n";

$suppressionModel = new CampaignSuppression();
$hardBounceEmail = 'hardbounce.user@example.com';
$softBounceEmail = 'softbounce.user@example.com';

// Seed contact
$contactModel = new CampaignContact();
$contactModel->upsertContact([
    'email' => $hardBounceEmail,
    'first_name' => 'Hard',
    'last_name' => 'Bouncer',
    'status' => 'subscribed'
]);
$contactModel->upsertContact([
    'email' => $softBounceEmail,
    'first_name' => 'Soft',
    'last_name' => 'Bouncer',
    'status' => 'subscribed'
]);

// 1. Process Hard Bounce -> Must immediately suppress
$hbResult = WebhookProcessorService::processBounce(
    $hardBounceEmail,
    'hard',
    '550 5.1.1 User unknown',
    1,
    ['smtp_code' => 550]
);

assertCondition($hbResult['success'] === true, 'Hard bounce processed successfully');
assertCondition($hbResult['action'] === 'hard_bounced_and_suppressed', 'Hard bounce triggered immediate suppression action');
assertCondition($suppressionModel->isSuppressed($hardBounceEmail) === true, 'Hard-bounced email is strictly recorded in campaign_suppressions');

$contactAfterHB = $contactModel->getByEmail($hardBounceEmail);
assertCondition($contactAfterHB->status === 'bounced', 'Contact status cascaded to "bounced"');

// 2. Process Soft Bounce 1 & 2 -> Log only, not yet suppressed
$sb1 = WebhookProcessorService::processBounce($softBounceEmail, 'soft', '452 4.2.2 Mailbox full', 1);
assertCondition($sb1['action'] === 'soft_bounce_logged', '1st soft bounce logged without suppression');
assertCondition($suppressionModel->isSuppressed($softBounceEmail) === false, '1st soft bounce does NOT suppress contact');

// Insert mock event records for soft bounces to simulate repeated occurrences
$db = Database::getInstance()->getConnection();
$db->prepare("INSERT INTO campaign_recipients (campaign_id, email, status) VALUES (1, :email, 'sent')")
   ->execute([':email' => $softBounceEmail]);
$rid = (int)$db->lastInsertId();

$db->prepare("INSERT INTO campaign_email_events (campaign_id, recipient_id, event_type, payload_json) VALUES (1, :rid, 'soft_bounce', '{}')")
   ->execute([':rid' => $rid]);
$db->prepare("INSERT INTO campaign_email_events (campaign_id, recipient_id, event_type, payload_json) VALUES (1, :rid, 'soft_bounce', '{}')")
   ->execute([':rid' => $rid]);

// 3rd Soft Bounce -> Must auto-suppress
$sb3 = WebhookProcessorService::processBounce($softBounceEmail, 'soft', '452 4.2.2 Mailbox full', 1);
assertCondition($sb3['action'] === 'soft_bounce_threshold_suppressed', '3rd soft bounce automatically triggered repeat threshold suppression');
assertCondition($suppressionModel->isSuppressed($softBounceEmail) === true, 'Soft bounced recipient (>= 3 times) is now globally suppressed');

// ====================================================================
// GROUP 4: Webhook Spam Complaint Processing
// ====================================================================
echo "\n[GROUP 4] Webhook Spam Complaint Processing:\n";

$complaintEmail = 'complainer@yahoo.com';
$contactModel->upsertContact([
    'email' => $complaintEmail,
    'first_name' => 'Angry',
    'last_name' => 'Recipient',
    'status' => 'subscribed'
]);

$compResult = WebhookProcessorService::processComplaint($complaintEmail, 'abuse', 1, ['feedback_id' => '1234']);
assertCondition($compResult['success'] === true, 'Spam complaint webhook processed successfully');
assertCondition($compResult['action'] === 'complaint_suppressed', 'Spam complaint immediately triggered suppression');
assertCondition($suppressionModel->isSuppressed($complaintEmail) === true, 'Spam complainer email is strictly globally suppressed');

$contactAfterComp = $contactModel->getByEmail($complaintEmail);
assertCondition($contactAfterComp->status === 'complained', 'Contact status transitioned to "complained"');

// ====================================================================
// GROUP 5: Webhook Delivery Processing
// ====================================================================
echo "\n[GROUP 5] Webhook Delivery Processing:\n";

$deliveryEmail = 'delivered.user@gmail.com';
$db->prepare("INSERT INTO campaign_recipients (campaign_id, email, status) VALUES (1, :email, 'sent')")
   ->execute([':email' => $deliveryEmail]);

$delivResult = WebhookProcessorService::processDelivery($deliveryEmail, 'msg-id-12345678', 1);
assertCondition($delivResult['success'] === true, 'Delivery notification processed successfully');
assertCondition($delivResult['action'] === 'delivered', 'Delivery recorded');

$recipDeliv = $db->query("SELECT * FROM campaign_recipients WHERE email = '{$deliveryEmail}' AND campaign_id = 1")->fetch(PDO::FETCH_OBJ);
assertCondition($recipDeliv->status === 'delivered', 'Recipient status updated to "delivered"');
assertCondition(!empty($recipDeliv->delivered_at), 'Delivered timestamp recorded on recipient row');

// ====================================================================
// GROUP 6: Analytics Engine & KPI Deliverability Ratios
// ====================================================================
echo "\n[GROUP 6] Analytics KPI Deliverability Ratios:\n";

// Seed realistic broadcast campaign record for metric calculations
$db->prepare("INSERT INTO campaigns_broadcast (
    campaign_code, name, subject_a, from_name, from_email, html_content, plain_text_content,
    sent_count, delivered_count, opened_count, clicked_count, bounced_count, complained_count, unsubscribed_count
) VALUES (
    'CMP-TEST-001', 'Analytics Test Campaign', 'Q4 Updates', 'Marketing', 'news@mail.example.com', '<p>Test</p>', 'Test',
    1000, 950, 475, 190, 50, 1, 5
)")->execute();
$cid = (int)$db->lastInsertId();

$metrics = CampaignAnalyticsService::getSummaryMetrics($cid);

assertCondition($metrics['sent'] === 1000, 'Reported sent count: 1000');
assertCondition($metrics['delivered'] === 950, 'Reported delivered count: 950');
assertCondition($metrics['opened'] === 475, 'Reported opened count: 475');
assertCondition($metrics['clicked'] === 190, 'Reported clicked count: 190');
assertCondition($metrics['bounced'] === 50, 'Reported bounced count: 50');
assertCondition($metrics['complained'] === 1, 'Reported complained count: 1');

// Verify deliverability health ratios
$rates = $metrics['rates'];
assertCondition($rates['delivery_rate'] === 95.0, 'Delivery Rate accurately calculated: 95.0% (950 / 1000)');
assertCondition($rates['open_rate'] === 50.0, 'Open Rate accurately calculated: 50.0% (475 / 950 delivered)');
assertCondition($rates['click_rate'] === 20.0, 'Click Rate (CTR) accurately calculated: 20.0% (190 / 950 delivered)');
assertCondition($rates['ctor'] === 40.0, 'Click-to-Open Rate (CTOR) accurately calculated: 40.0% (190 / 475 opened)');
assertCondition($rates['bounce_rate'] === 5.0, 'Bounce Rate accurately calculated: 5.0% (50 / 1000 sent)');
assertCondition($rates['complaint_rate'] === 0.1, 'Complaint Rate accurately calculated: 0.1% (1 / 1000 sent)');

// ====================================================================
// GROUP 7: Domain-Level Breakdown & Top Links
// ====================================================================
echo "\n[GROUP 7] Domain-Level Breakdown & Top Links:\n";

// Clear any test recipients for clean isolated domain breakdown calculation
$db->prepare("DELETE FROM campaign_recipients WHERE campaign_id = {$cid}")->execute();

// Seed recipients across domain families
$db->prepare("INSERT INTO campaign_recipients (campaign_id, email, status, open_count, click_count) VALUES 
    ({$cid}, 'alice@gmail.com', 'delivered', 1, 1),
    ({$cid}, 'bob@googlemail.com', 'delivered', 1, 0),
    ({$cid}, 'charlie@outlook.com', 'delivered', 1, 1),
    ({$cid}, 'david@yahoo.com', 'delivered', 0, 0),
    ({$cid}, 'eve@corporatecorp.com', 'bounced', 0, 0)")
   ->execute();

$domainBreakdown = CampaignAnalyticsService::getDomainBreakdown($cid);

assertCondition($domainBreakdown['Gmail / Google']['sent'] === 2, 'Domain Family Gmail sent count is 2');
assertCondition($domainBreakdown['Gmail / Google']['opened'] === 2, 'Domain Family Gmail opened count is 2');
assertCondition($domainBreakdown['Microsoft / Outlook']['sent'] === 1, 'Domain Family Microsoft sent count is 1');
assertCondition($domainBreakdown['Yahoo / AOL']['sent'] === 1, 'Domain Family Yahoo sent count is 1');
assertCondition($domainBreakdown['Corporate / Other']['bounced'] === 1, 'Domain Family Corporate bounced count is 1');

// Seed click events for Top Links
$db->prepare("INSERT INTO campaign_email_events (campaign_id, recipient_id, event_type, target_url) VALUES 
    ({$cid}, 1, 'click', 'https://example.com/pricing'),
    ({$cid}, 2, 'click', 'https://example.com/pricing'),
    ({$cid}, 1, 'click', 'https://example.com/docs')")
   ->execute();

$topLinks = CampaignAnalyticsService::getTopClickedLinks($cid);
assertCondition(count($topLinks) >= 2, 'Retrieved top clicked URLs list');
assertCondition($topLinks[0]['target_url'] === 'https://example.com/pricing', 'Top ranked URL is pricing with most clicks');
assertCondition((int)$topLinks[0]['click_count'] === 2, 'Pricing URL click count is 2');

// ====================================================================
// GROUP 8: Inactive Subscriber Re-Engagement Flagger & CSV Export
// ====================================================================
echo "\n[GROUP 8] Inactive Subscriber Flagger & CSV Export:\n";

// Seed an inactive contact (no engagement in 120 days)
$oldDate = date('Y-m-d H:i:s', strtotime('-120 days'));
$db->prepare("INSERT INTO campaign_contacts (email, first_name, status, last_engagement_at, created_at) 
              VALUES ('inactive.user@example.com', 'Sleeping', 'subscribed', '{$oldDate}', '{$oldDate}')")
   ->execute();

// Seed an active contact (engagement 5 days ago)
$recentDate = date('Y-m-d H:i:s', strtotime('-5 days'));
$db->prepare("INSERT INTO campaign_contacts (email, first_name, status, last_engagement_at, created_at) 
              VALUES ('active.user@example.com', 'Active', 'subscribed', '{$recentDate}', '{$recentDate}')")
   ->execute();

$flaggedCount = CampaignAnalyticsService::flagInactiveContacts(90);
assertCondition($flaggedCount >= 1, 'Re-engagement rule identified and flagged inactive contact (> 90 days)');

$inactiveCheck = $contactModel->getByEmail('inactive.user@example.com');
assertCondition($inactiveCheck->status === 'unsubscribed', 'Inactive subscriber status auto-transitioned out of active audience');

$activeCheck = $contactModel->getByEmail('active.user@example.com');
assertCondition($activeCheck->status === 'subscribed', 'Active subscriber retains subscribed status');

// Test CSV Export
$csv = CampaignAnalyticsService::exportAnalyticsCsv($cid);
assertCondition(!empty($csv), 'Generated analytics CSV export stream');
assertCondition(str_contains($csv, 'Email,Variant,Status'), 'CSV export contains standard deliverability headers');
assertCondition(str_contains($csv, 'alice@gmail.com'), 'CSV export includes campaign recipient data rows');

echo "\n====================================================================\n";
echo "  PHASE 5 TEST SUMMARY: {$testsPassed} PASSED, {$testsFailed} FAILED\n";
echo "====================================================================\n";

exit($testsFailed > 0 ? 1 : 0);
