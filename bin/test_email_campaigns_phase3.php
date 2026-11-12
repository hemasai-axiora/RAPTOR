<?php
/**
 * Raptor CRM - Email Campaigns Phase 3 Automated Verification Suite
 * 
 * Tests:
 * 1. Merge Tag Replacement Engine with Fallbacks & Safe Escaping
 * 2. Mandatory Plain-Text Alternative Auto-Generator
 * 3. Deliverability HTML Sanitizer (stripping scripts, forms, and base64 images)
 * 4. Pre-Send Deliverability Checklist (blocking invalid campaigns)
 * 5. Spam Trigger Word & ALL-CAPS Subject Scoring
 * 6. Campaign Launch Enforcement & Queue Seeding with HMAC Unsubscribe Tokens
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
            consent_type TEXT DEFAULT "explicit_optin",
            consent_timestamp DATETIME,
            consent_ip TEXT,
            consent_notes TEXT,
            is_role_account INTEGER DEFAULT 0,
            is_disposable INTEGER DEFAULT 0,
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
            ab_split_percent INTEGER DEFAULT 0,
            ab_winner_metric TEXT DEFAULT "opens",
            ab_winner_subject TEXT,
            preheader TEXT,
            from_name TEXT NOT NULL,
            from_email TEXT NOT NULL,
            reply_to TEXT,
            list_id INTEGER,
            segment_id INTEGER,
            template_id INTEGER,
            html_content TEXT NOT NULL,
            plain_text_content TEXT NOT NULL,
            status TEXT DEFAULT "draft",
            scheduled_at DATETIME,
            started_at DATETIME,
            completed_at DATETIME,
            paused_reason TEXT,
            requires_approval INTEGER DEFAULT 0,
            approved_by INTEGER,
            approved_at DATETIME,
            pre_send_checklist_passed INTEGER DEFAULT 0,
            pre_send_checklist_json TEXT,
            total_recipients INTEGER DEFAULT 0,
            sent_count INTEGER DEFAULT 0,
            delivered_count INTEGER DEFAULT 0,
            bounced_count INTEGER DEFAULT 0,
            complained_count INTEGER DEFAULT 0,
            opened_count INTEGER DEFAULT 0,
            clicked_count INTEGER DEFAULT 0,
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
            sent_at DATETIME
        );
        CREATE TABLE campaign_email_queue (
            queue_id INTEGER PRIMARY KEY AUTOINCREMENT,
            campaign_id INTEGER,
            recipient_id INTEGER,
            status TEXT DEFAULT "pending",
            priority INTEGER DEFAULT 10,
            attempts INTEGER DEFAULT 0,
            max_attempts INTEGER DEFAULT 3,
            scheduled_at DATETIME,
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
            is_default INTEGER DEFAULT 0,
            spf_status TEXT DEFAULT "valid",
            dkim_status TEXT DEFAULT "valid",
            dmarc_status TEXT DEFAULT "valid"
        );
        CREATE TABLE campaign_audit_logs (
            audit_id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER,
            action TEXT NOT NULL,
            entity_type TEXT NOT NULL,
            entity_id INTEGER,
            details_json TEXT,
            ip_address TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE users (
            user_id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            email TEXT NOT NULL,
            status TEXT DEFAULT "active"
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
echo "  RAPTOR CRM: EMAIL CAMPAIGNS & MASS MAILING (PHASE 3 TEST SUITE)   \n";
echo "====================================================================\n\n";

require_once __DIR__ . '/../app/models/CampaignTemplate.php';
require_once __DIR__ . '/../app/models/CampaignBroadcast.php';
require_once __DIR__ . '/../app/models/CampaignContact.php';
require_once __DIR__ . '/../app/models/CampaignList.php';
require_once __DIR__ . '/../app/models/CampaignSegment.php';
require_once __DIR__ . '/../app/models/CampaignSuppression.php';
require_once __DIR__ . '/../app/services/ContactImportService.php';
require_once __DIR__ . '/../app/services/PreSendChecklistService.php';
require_once __DIR__ . '/../app/services/UnsubscribeService.php';

// -----------------------------------------------------------------------------
// TEST GROUP 1: MERGE TAG ENGINE & ESCAPING
// -----------------------------------------------------------------------------
echo "[GROUP 1] Merge Tag Replacement Engine & Safe Escaping:\n";

$templateText = "Hello {{first_name}}, welcome to {{company_name|Acme}}! Unsubscribe: {{unsubscribe_url}}";
$contact1 = [
    'first_name' => 'John',
    'company_name' => 'Stark Industries',
    'email' => 'john@stark.com'
];
$rendered1 = CampaignTemplate::renderMergeTags($templateText, $contact1, ['unsubscribe_url' => 'https://axiora.in/unsub']);
assertCondition(strpos($rendered1, 'Hello John') !== false, 'Replaced {{first_name}} with actual contact name');
assertCondition(strpos($rendered1, 'Stark Industries') !== false, 'Replaced {{company_name}} with actual company');
assertCondition(strpos($rendered1, 'https://axiora.in/unsub') !== false, 'Replaced {{unsubscribe_url}} with unsubscribe link');

// Test fallback value when attribute is empty
$contact2 = [
    'first_name' => '',
    'company_name' => '',
    'email' => 'anon@example.com'
];
$templateFallback = "Hi {{first_name|Valued Customer}}, welcome to {{company_name|Our Platform}}!";
$rendered2 = CampaignTemplate::renderMergeTags($templateFallback, $contact2);
assertCondition(strpos($rendered2, 'Hi Valued Customer') !== false, 'Applied fallback value "Valued Customer" when first_name is empty');
assertCondition(strpos($rendered2, 'Our Platform') !== false, 'Applied fallback value "Our Platform" when company_name is empty');

// Test XSS escaping
$contactXss = [
    'first_name' => '<script>alert("XSS")</script>',
    'company_name' => 'Hacked "Corp"'
];
$renderedXss = CampaignTemplate::renderMergeTags("Hi {{first_name}} at {{company_name}}", $contactXss);
assertCondition(strpos($renderedXss, '<script>') === false, 'XSS script tags are strictly escaped');
assertCondition(strpos($renderedXss, '&lt;script&gt;') !== false, 'HTML entities safely encoded for output');

// -----------------------------------------------------------------------------
// TEST GROUP 2: PLAIN-TEXT MULTIPART ALTERNATIVE GENERATOR
// -----------------------------------------------------------------------------
echo "\n[GROUP 2] Plain-Text Alternative Auto-Generator:\n";

$sampleHtml = <<<HTML
<html>
<head><style>body { color: red; }</style></head>
<body>
    <h2>Quarterly Update</h2>
    <p>Dear {{first_name}},</p>
    <p>We are delighted to announce our new portal. Please visit <a href="https://portal.axiora.in">our portal link</a> to review.</p>
    <ul>
        <li>Feature A</li>
        <li>Feature B</li>
    </ul>
    <p>Best regards,<br>The Team</p>
</body>
</html>
HTML;

$plainOutput = CampaignTemplate::htmlToPlainText($sampleHtml);
assertCondition(strpos($plainOutput, 'style {') === false, 'Style block completely removed from plain-text output');
assertCondition(strpos($plainOutput, 'our portal link (https://portal.axiora.in)') !== false, 'Hyperlink converted to "text (URL)" syntax');
assertCondition(strpos($plainOutput, '• Feature A') !== false, 'List items converted to bullet points');
assertCondition(strpos($plainOutput, '<h2>') === false, 'All HTML tags stripped from output');

// -----------------------------------------------------------------------------
// TEST GROUP 3: DELIVERABILITY HTML SANITIZER & SECURITY
// -----------------------------------------------------------------------------
echo "\n[GROUP 3] HTML Deliverability Sanitizer & Security Guard:\n";

$dangerousHtml = '<div>Safe content <script>evil_payload();</script><iframe src="phish.com"></iframe><form action="steal.php"><input name="pass"></form></div>';
$sanitized = CampaignTemplate::sanitizeHtml($dangerousHtml);
assertCondition(strpos($sanitized, '<script>') === false, 'Stripped <script> tags from HTML');
assertCondition(strpos($sanitized, '<iframe>') === false, 'Stripped <iframe> tags from HTML');
assertCondition(strpos($sanitized, '<form') === false, 'Stripped <form> tags from HTML');

// Base64 image rejection test
$base64Html = '<p>Check logo</p><img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=" alt="logo">';
$threwBase64 = false;
try {
    CampaignTemplate::sanitizeHtml($base64Html);
} catch (InvalidArgumentException $e) {
    $threwBase64 = true;
}
assertCondition($threwBase64 === true, 'Disallowed embedded base64 image (throws deliverability error)');

// 100KB size limit test
$oversizedHtml = str_repeat('<div>Spam spam spam</div>', 10000); // ~230 KB
$threwSize = false;
try {
    CampaignTemplate::sanitizeHtml($oversizedHtml);
} catch (InvalidArgumentException $e) {
    $threwSize = true;
}
assertCondition($threwSize === true, 'Disallowed oversized email (>100 KB) to prevent Gmail clipping');

// -----------------------------------------------------------------------------
// TEST GROUP 4: SPAM SCORE CALCULATOR
// -----------------------------------------------------------------------------
echo "\n[GROUP 4] Spam Trigger & Heuristic Subject Line Scoring:\n";

$cleanSubject = "Your Monthly Account Statement & Operational Update";
$cleanBody = "<p>Please find attached your monthly performance summary.</p>";
$cleanSpam = PreSendChecklistService::calculateSpamScore($cleanSubject, $cleanBody);
assertCondition($cleanSpam['score'] < 2.0, "Clean email receives low spam score (Score: {$cleanSpam['score']})");

$spammySubject = "BUY NOW 100% FREE CASINO MAKE MONEY $$$$ !!!!!!!!";
$spammyBody = "<p>Join millions! Fast cash, double your income, risk free!</p>";
$spammySpam = PreSendChecklistService::calculateSpamScore($spammySubject, $spammyBody);
assertCondition($spammySpam['score'] >= 8.0, "Spammy email receives high spam score (Score: {$spammySpam['score']})");
assertCondition(count($spammySpam['triggers']) >= 3, 'Identified spam trigger phrases and punctuation');

// -----------------------------------------------------------------------------
// TEST GROUP 5: PRE-SEND QUALITY GATE BLOCKER
// -----------------------------------------------------------------------------
echo "\n[GROUP 5] Pre-Send Deliverability Checklist Blocker:\n";

// 1. Missing unsubscribe link must fail
$badCampaign1 = [
    'name' => 'Bad Campaign 1',
    'subject_a' => 'Hello',
    'html_content' => '<p>Hello world. Address: Cyber City, Hyderabad.</p>',
    'plain_text_content' => 'Hello world. Address: Cyber City, Hyderabad.',
    'from_email' => 'news@axiora.in'
];
$audit1 = PreSendChecklistService::run($badCampaign1);
assertCondition($audit1['passed'] === false, 'Blocked campaign with missing unsubscribe link');
assertCondition($audit1['checks']['unsubscribe_link']['passed'] === false, 'Unsubscribe check failed');

// 2. Missing physical address must fail
$badCampaign2 = [
    'name' => 'Bad Campaign 2',
    'subject_a' => 'Hello',
    'html_content' => '<p>Hello world. {{unsubscribe_url}}</p>',
    'plain_text_content' => 'Hello world. {{unsubscribe_url}}',
    'from_email' => 'news@axiora.in'
];
$audit2 = PreSendChecklistService::run($badCampaign2);
assertCondition($audit2['passed'] === false, 'Blocked campaign with missing physical postal address');
assertCondition($audit2['checks']['physical_address']['passed'] === false, 'Physical address check failed');

// 3. Missing plain text must fail
$badCampaign3 = [
    'name' => 'Bad Campaign 3',
    'subject_a' => 'Hello',
    'html_content' => '<p>Hello world. {{unsubscribe_url}} {{company_address}}</p>',
    'plain_text_content' => '',
    'from_email' => 'news@axiora.in'
];
$audit3 = PreSendChecklistService::run($badCampaign3);
assertCondition($audit3['passed'] === false, 'Blocked campaign with missing plain-text alternative');
assertCondition($audit3['checks']['plain_text_alternative']['passed'] === false, 'Plain-text check failed');

// 4. Valid compliant campaign must pass
$validCampaign = [
    'name' => 'Compliant Broadcast',
    'subject_a' => 'Quarterly Product Announcement',
    'html_content' => '<p>Hello {{first_name}}. {{company_address}} <a href="{{unsubscribe_url}}">Unsubscribe</a></p>',
    'plain_text_content' => 'Hello {{first_name}}. Axiora Cyber City, Hyderabad. Unsubscribe: {{unsubscribe_url}}',
    'from_email' => 'updates@mail.axiora.in',
    'test_email_verified' => 1
];
$auditValid = PreSendChecklistService::run($validCampaign);
assertCondition($auditValid['passed'] === true, 'Passed all critical pre-send deliverability checks');
assertCondition(empty($auditValid['blockers']), 'Zero critical blockers identified');

// -----------------------------------------------------------------------------
// TEST GROUP 6: CAMPAIGN LAUNCH & QUEUE SEEDING
// -----------------------------------------------------------------------------
echo "\n[GROUP 6] Campaign Launch Enforcement & Queue Seeding:\n";

$listModel = new CampaignList();
$listId = $listModel->createList('Q4 VIP Audience', 'Test broadcast list', false, 'single', 1);

$contactModel = new CampaignContact();
$cid1 = $contactModel->upsertContact([
    'email' => 'target.user1@example.com',
    'first_name' => 'Edward',
    'list_ids' => [$listId]
]);
$cid2 = $contactModel->upsertContact([
    'email' => 'target.user2@example.com',
    'first_name' => 'Eleanor',
    'list_ids' => [$listId]
]);

$campaignModel = new CampaignBroadcast();

// 1. Create compliant campaign
$campId = $campaignModel->saveCampaign([
    'name' => 'Production Launch Test',
    'subject_a' => 'System Update Announcement',
    'subject_b' => 'New Platform Enhancements Live',
    'ab_split_percent' => 50,
    'from_name' => 'Axiora Product Team',
    'from_email' => 'updates@mail.axiora.in',
    'list_id' => $listId,
    'html_content' => '<p>Hi {{first_name}}. Review updates. {{company_address}} <a href="{{unsubscribe_url}}">Unsubscribe</a></p>',
    'plain_text_content' => 'Hi {{first_name}}. Review updates. Cyber City, Hyderabad. Unsubscribe: {{unsubscribe_url}}'
], 1);

assertCondition($campId > 0, 'Created campaign record');

// 2. Launch campaign
$launchRes = $campaignModel->launchCampaign($campId, 1);
assertCondition($launchRes['success'] === true, 'Campaign successfully passed quality gate and launched');
assertCondition($launchRes['queued_count'] >= 2, 'Seeded eligible recipients into dispatch queue');

// 3. Verify recipient records and tokens
$recipients = $pdo->query("SELECT * FROM campaign_recipients WHERE campaign_id = {$campId}")->fetchAll(PDO::FETCH_ASSOC);
assertCondition(count($recipients) >= 2, 'Created recipient records in campaign_recipients table');
assertCondition(!empty($recipients[0]['unsubscribe_token']), 'Generated HMAC unsubscribe token on recipient row');

// Verify token decodes to the recipient
$decoded = UnsubscribeService::verifyToken($recipients[0]['unsubscribe_token']);
assertCondition($decoded !== null && $decoded['campaign_id'] === $campId, 'Recipient unsubscribe token decodes and matches campaign');

// 4. Verify queue items
$queueItems = $pdo->query("SELECT * FROM campaign_email_queue WHERE campaign_id = {$campId}")->fetchAll(PDO::FETCH_ASSOC);
assertCondition(count($queueItems) === count($recipients), 'Created corresponding queue tasks in campaign_email_queue');
assertCondition($queueItems[0]['status'] === 'pending', 'Queue items are in "pending" status ready for background worker');

// Clean up test data
$pdo->exec("DELETE FROM campaign_email_queue WHERE campaign_id = {$campId}");
$pdo->exec("DELETE FROM campaign_recipients WHERE campaign_id = {$campId}");
$pdo->exec("DELETE FROM campaigns_broadcast WHERE campaign_id = {$campId}");
$listModel->deleteList($listId);

// -----------------------------------------------------------------------------
// SUMMARY
// -----------------------------------------------------------------------------
echo "\n====================================================================\n";
echo "  PHASE 3 TEST SUMMARY: {$testsPassed} PASSED, {$testsFailed} FAILED\n";
echo "====================================================================\n";

if ($testsFailed > 0) {
    exit(1);
}
exit(0);
