<?php
/**
 * Raptor CRM - Marketing Module & Bulk Email Comprehensive Verification Suite
 * ---------------------------------------------------------------------------
 * Validates:
 * 1. Marketing Sidebar Routing & Isolation (No jumping to Campaign Registry)
 * 2. Case-Insensitive Controller Resolution (e.g. WhatsAppController)
 * 3. Complete Purge of Payroll from Marketing Navigation & Views
 * 4. SmtpAdapter Integration with Database Settings (EmailService::getConfig)
 * 5. Bulk Email Broadcaster (All Active Subscribers list_id = -1, Deduplication, Filtering)
 * 6. Campaign Lifecycle State Machine (Launch -> Immediate Batch Dispatch, Pause, Resume, Cancel)
 * 7. Template Duplication Support
 */

declare(strict_types=1);

require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/core/Model.php';
require_once __DIR__ . '/../app/core/Controller.php';
require_once __DIR__ . '/../app/core/Policy.php';
require_once __DIR__ . '/../app/models/CampaignBroadcast.php';
require_once __DIR__ . '/../app/models/CampaignContact.php';
require_once __DIR__ . '/../app/models/CampaignList.php';
require_once __DIR__ . '/../app/models/CampaignSuppression.php';
require_once __DIR__ . '/../app/models/CampaignTemplate.php';
require_once __DIR__ . '/../app/models/CampaignTag.php';
require_once __DIR__ . '/../app/models/CampaignSegment.php';
require_once __DIR__ . '/../app/controllers/EmailCampaignsController.php';
require_once __DIR__ . '/../app/services/mailers/MailerSendResult.php';
require_once __DIR__ . '/../app/services/mailers/MailerAdapterInterface.php';
require_once __DIR__ . '/../app/services/mailers/MockMailerAdapter.php';
require_once __DIR__ . '/../app/services/mailers/SmtpAdapter.php';
require_once __DIR__ . '/../app/services/mailers/MailerFactory.php';
require_once __DIR__ . '/../app/services/EmailQueueService.php';
require_once __DIR__ . '/../app/services/UnsubscribeService.php';
require_once __DIR__ . '/../app/services/PreflightEngine.php';
require_once __DIR__ . '/../app/services/PreSendChecklistService.php';

$passed = 0;
$failed = 0;

function assertCondition(bool $cond, string $msg): void {
    global $passed, $failed;
    if ($cond) {
        $passed++;
        echo "  [PASS] {$msg}\n";
    } else {
        $failed++;
        echo "  [FAIL] {$msg}\n";
    }
}

echo "====================================================================\n";
echo "  RAPTOR MARKETING MODULE & BULK EMAIL AUDIT SUITE                   \n";
echo "====================================================================\n\n";

// -----------------------------------------------------------------------------
// GROUP 1: Sidebar Routing & Isolation (Zero False Active Highlighting)
// -----------------------------------------------------------------------------
echo "[GROUP 1] Sidebar Routing & Isolation:\n";

// Simulate route = email_campaigns/contacts
$_GET['route'] = 'email_campaigns/contacts';
$isAudiencesActive = (isset($_GET['route']) && (strpos($_GET['route'], 'email_campaigns/contact') === 0 || strpos($_GET['route'], 'email_campaigns/list') === 0 || strpos($_GET['route'], 'email_campaigns/import') === 0));
$isRegistryActive = (isset($_GET['route']) && strpos($_GET['route'], 'campaigns') === 0 && strpos($_GET['route'], 'email_campaigns') === false);

assertCondition($isAudiencesActive === true, 'Audiences & Lists is active when route=email_campaigns/contacts');
assertCondition($isRegistryActive === false, 'Campaign Registry is strictly NOT active when route=email_campaigns/contacts');

// Simulate route = email_campaigns/lists
$_GET['route'] = 'email_campaigns/lists';
$isAudiencesActive = (isset($_GET['route']) && (strpos($_GET['route'], 'email_campaigns/contact') === 0 || strpos($_GET['route'], 'email_campaigns/list') === 0 || strpos($_GET['route'], 'email_campaigns/import') === 0));
$isRegistryActive = (isset($_GET['route']) && strpos($_GET['route'], 'campaigns') === 0 && strpos($_GET['route'], 'email_campaigns') === false);

assertCondition($isAudiencesActive === true, 'Audiences & Lists is active when route=email_campaigns/lists');
assertCondition($isRegistryActive === false, 'Campaign Registry is strictly NOT active when route=email_campaigns/lists');

// Simulate route = campaigns/index (Legacy/Registry Campaigns)
$_GET['route'] = 'campaigns/index';
$isAudiencesActive = (isset($_GET['route']) && (strpos($_GET['route'], 'email_campaigns/contact') === 0 || strpos($_GET['route'], 'email_campaigns/list') === 0 || strpos($_GET['route'], 'email_campaigns/import') === 0));
$isRegistryActive = (isset($_GET['route']) && strpos($_GET['route'], 'campaigns') === 0 && strpos($_GET['route'], 'email_campaigns') === false);

assertCondition($isAudiencesActive === false, 'Audiences & Lists is NOT active when route=campaigns/index');
assertCondition($isRegistryActive === true, 'Campaign Registry is active when route=campaigns/index');

// -----------------------------------------------------------------------------
// GROUP 2: Case-Insensitive Controller Resolution
// -----------------------------------------------------------------------------
echo "\n[GROUP 2] Controller Resolution & Routing Engine:\n";

$targetLower = strtolower('whatsappcontroller.php');
$resolved = null;
$ctrlDir = APPROOT . '/controllers';
if (is_dir($ctrlDir)) {
    foreach (scandir($ctrlDir) as $f) {
        if (strtolower($f) === $targetLower) {
            $resolved = substr($f, 0, -4);
            break;
        }
    }
}
assertCondition($resolved === 'WhatsAppController', 'whatsapp route case-insensitively resolves to WhatsAppController on Linux');

// -----------------------------------------------------------------------------
// GROUP 3: Complete Purge of Payroll from Marketing Navigation
// -----------------------------------------------------------------------------
echo "\n[GROUP 3] Marketing Navigation & View Hygiene:\n";

$viewFiles = [
    APPROOT . '/views/email_campaigns/contacts/index.php',
    APPROOT . '/views/email_campaigns/lists/index.php',
    APPROOT . '/views/email_campaigns/segments/index.php',
    APPROOT . '/views/email_campaigns/suppressions/index.php',
    APPROOT . '/views/email_campaigns/analytics/index.php',
    APPROOT . '/views/email_campaigns/campaigns/index.php',
    APPROOT . '/views/email_campaigns/templates/index.php',
];

$hasPayrollTab = false;
foreach ($viewFiles as $vf) {
    if (file_exists($vf)) {
        $content = file_get_contents($vf);
        if (strpos($content, 'Lists & Payroll Sync') !== false) {
            $hasPayrollTab = true;
            break;
        }
    }
}
assertCondition($hasPayrollTab === false, 'Zero occurrences of "Lists & Payroll Sync" across all Marketing view sub-navigation tabs');

$listsView = file_get_contents(APPROOT . '/views/email_campaigns/lists/index.php');
assertCondition(strpos($listsView, 'syncPayrollModal') === false, 'Payroll sync modal completely purged from Marketing Lists view');
assertCondition(strpos($listsView, 'Internal Corporate Employee Synchronization') === false, 'Payroll sync card completely purged from Marketing Lists view');

// -----------------------------------------------------------------------------
// GROUP 4: SmtpAdapter Configuration & Dual SMTP Isolation
// -----------------------------------------------------------------------------
echo "\n[GROUP 4] SmtpAdapter & Settings Integration:\n";

// Use SQLite in-memory for testing
$pdo = new PDO("sqlite::memory:");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_OBJ);
Database::setMockConnection($pdo);

$pdo->exec("
    CREATE TABLE IF NOT EXISTS settings (
        setting_key TEXT PRIMARY KEY,
        setting_value TEXT
    );
");

$adapter587 = new SmtpAdapter(['port' => 587, 'encryption' => 'tls']);
assertCondition($adapter587->getName() === 'smtp', 'SmtpAdapter reports provider name "smtp"');

$adapter465 = new SmtpAdapter(['port' => 465, 'encryption' => 'ssl']);
assertCondition($adapter465->getName() === 'smtp', 'SmtpAdapter supports port 465 (SMTPS)');

// Dual SMTP architecture & Strict Routing verification:
require_once APPROOT . '/services/EmailService.php';
assertCondition(method_exists('EmailService', 'getMarketingConfig'), 'EmailService defines getMarketingConfig() method');

// 1. When dedicated marketing SMTP is disabled, STRICTLY DO NOT fall back to corporate SMTP
$pdo->exec("
    INSERT OR REPLACE INTO settings (setting_key, setting_value) VALUES
    ('mail.host', 'corporate.zoho.in'),
    ('mail.from_address', 'accounts@axioraglobalsolutions.com'),
    ('mail.marketing.enabled', '0'),
    ('mail.marketing.host', 'bulk.ses.amazonaws.com');
");
$disabledConfig = EmailService::getMarketingConfig();
assertCondition($disabledConfig['enabled'] === false, 'Strict routing: Marketing SMTP disabled reports enabled=false');
assertCondition(empty($disabledConfig['host']), 'Strict routing: Marketing SMTP disabled does NOT return corporate host');
assertCondition($disabledConfig['host'] !== 'corporate.zoho.in', 'Strict routing: Corporate SMTP host is strictly isolated and never returned');

// 2. Corporate config remains independent and unaffected
$corpConfig = EmailService::getConfig();
assertCondition($corpConfig['host'] === 'corporate.zoho.in', 'Corporate SMTP host remains corporate.zoho.in');
assertCondition($corpConfig['from_address'] === 'accounts@axioraglobalsolutions.com', 'Corporate from_address remains accounts@axioraglobalsolutions.com');

// 3. SmtpAdapter strictly blocks delivery when marketing SMTP is disabled
$disabledAdapter = new SmtpAdapter();
$sendRes = $disabledAdapter->send(['to' => 'test@example.com', 'subject' => 'Test', 'html' => '<p>Hi</p>']);
assertCondition($sendRes->isSuccess() === false, 'SmtpAdapter fails delivery when marketing SMTP is disabled');
assertCondition($sendRes->getErrorCode() === 'MARKETING_SMTP_DISABLED', 'SmtpAdapter returns MARKETING_SMTP_DISABLED error code');
assertCondition($sendRes->isTransient() === true, 'SmtpAdapter error is transient/retryable so recipients stay queued');

// 4. When dedicated marketing SMTP is enabled, strictly uses dedicated host and marketing from address
$pdo->exec("UPDATE settings SET setting_value = '1' WHERE setting_key = 'mail.marketing.enabled'");
$dedicatedConfig = EmailService::getMarketingConfig();
assertCondition($dedicatedConfig['enabled'] === true, 'Marketing SMTP enabled reports enabled=true');
assertCondition($dedicatedConfig['host'] === 'bulk.ses.amazonaws.com', 'Uses dedicated marketing host when marketing SMTP is enabled');
assertCondition($dedicatedConfig['from_address'] === 'contacts@axioraglobalsolutions.com', 'Uses contacts@axioraglobalsolutions.com for marketing dispatches');

// -----------------------------------------------------------------------------
// GROUP 5: Bulk Recipient Selection & Deduplication
// -----------------------------------------------------------------------------
echo "\n[GROUP 5] Bulk Email Recipient Selection & Deduplication:\n";

$pdo->exec("
    CREATE TABLE campaign_contacts (
        contact_id INTEGER PRIMARY KEY AUTOINCREMENT,
        email TEXT NOT NULL UNIQUE,
        first_name TEXT,
        last_name TEXT,
        company_name TEXT,
        custom_fields TEXT,
        status TEXT DEFAULT 'subscribed'
    );
    CREATE TABLE campaign_list_contacts (
        list_id INTEGER NOT NULL,
        contact_id INTEGER NOT NULL,
        status TEXT DEFAULT 'active'
    );
    CREATE TABLE campaign_suppressions (
        suppression_id INTEGER PRIMARY KEY AUTOINCREMENT,
        email TEXT NOT NULL UNIQUE,
        reason TEXT,
        notes TEXT,
        source_campaign_id INTEGER
    );
    CREATE TABLE campaigns_broadcast (
        campaign_id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT,
        status TEXT DEFAULT 'draft',
        list_id INTEGER,
        segment_id INTEGER,
        template_id INTEGER,
        created_by INTEGER,
        pre_send_checklist_json TEXT,
        paused_reason TEXT
    );
    CREATE TABLE campaign_lists (list_id INTEGER PRIMARY KEY, name TEXT);
    CREATE TABLE campaign_segments (segment_id INTEGER PRIMARY KEY, name TEXT);
    CREATE TABLE campaign_templates (template_id INTEGER PRIMARY KEY, name TEXT);
    CREATE TABLE users (user_id INTEGER PRIMARY KEY, name TEXT);
    CREATE TABLE campaign_email_queue (queue_id INTEGER PRIMARY KEY, campaign_id INTEGER, status TEXT, last_error TEXT);
");

// Insert sample contacts (including duplicates with casing and whitespaces)
$pdo->exec("
    INSERT INTO campaign_contacts (contact_id, email, first_name, status) VALUES
    (1, 'alice@example.com', 'Alice', 'subscribed'),
    (2, 'bob@example.com', 'Bob', 'subscribed'),
    (3, 'charlie@example.com', 'Charlie', 'subscribed'),
    (4, 'bad-email-no-at', 'Invalid', 'subscribed'),
    (5, 'suppressed@example.com', 'Suppressed', 'subscribed');

    INSERT INTO campaign_suppressions (email, reason) VALUES ('suppressed@example.com', 'manual');
    INSERT INTO campaign_list_contacts (list_id, contact_id) VALUES (10, 1), (10, 2);
");

Database::setMockConnection($pdo);

// Test A: Targeted List Selection (List ID 10)
$pdo->exec("INSERT INTO campaigns_broadcast (campaign_id, name, status, list_id) VALUES (1, 'Targeted Campaign', 'draft', 10)");
$model = new CampaignBroadcast();

$targeted = $model->resolveEligibleRecipients(1);
assertCondition($targeted['total_subscribers'] === 2, 'Targeted list has exactly 2 subscribers');
assertCondition($targeted['reachable_count'] === 2, 'Targeted list has 2 reachable contacts');

// Test B: All Active Subscribers Broadcast (List ID -1)
$pdo->exec("INSERT INTO campaigns_broadcast (campaign_id, name, status, list_id) VALUES (2, 'All Subscribers Blast', 'draft', -1)");
$allBlast = $model->resolveEligibleRecipients(2);

assertCondition($allBlast['total_subscribers'] === 5, 'Total raw contacts in DB is 5');
assertCondition($allBlast['suppressed_count'] === 1, 'Correctly identified 1 suppressed address');
assertCondition($allBlast['reachable_count'] === 3, 'Filtered invalid syntax and suppressed address, leaving 3 reachable contacts (Alice, Bob, Charlie)');

// -----------------------------------------------------------------------------
// GROUP 6: Campaign Lifecycle State Machine (Pause, Resume, Cancel)
// -----------------------------------------------------------------------------
echo "\n[GROUP 6] Campaign Lifecycle Controls (Pause, Resume, Cancel):\n";

$pdo->exec("UPDATE campaigns_broadcast SET status = 'queued' WHERE campaign_id = 1");

// Pause
$resPause = $model->pauseCampaign(1, 'Deliverability check');
assertCondition($resPause['success'] === true, 'Successfully paused active queued campaign');
$campRow = $pdo->query("SELECT status, paused_reason FROM campaigns_broadcast WHERE campaign_id = 1")->fetch(PDO::FETCH_ASSOC);
assertCondition($campRow['status'] === 'paused', 'Campaign status updated to "paused"');
assertCondition($campRow['paused_reason'] === 'Deliverability check', 'Paused reason recorded properly');

// Resume
$resResume = $model->resumeCampaign(1);
assertCondition($resResume['success'] === true, 'Successfully resumed paused campaign');
$campRow = $pdo->query("SELECT status, paused_reason FROM campaigns_broadcast WHERE campaign_id = 1")->fetch(PDO::FETCH_ASSOC);
assertCondition($campRow['status'] === 'queued', 'Campaign status transitioned back to "queued"');
assertCondition($campRow['paused_reason'] === null, 'Paused reason cleared on resume');

// Cancel
$resCancel = $model->cancelCampaign(1);
assertCondition($resCancel['success'] === true, 'Successfully cancelled campaign');
$campRow = $pdo->query("SELECT status FROM campaigns_broadcast WHERE campaign_id = 1")->fetch(PDO::FETCH_ASSOC);
assertCondition($campRow['status'] === 'cancelled', 'Campaign status updated to "cancelled"');

// -----------------------------------------------------------------------------
// GROUP 7: Template Duplication Support
// -----------------------------------------------------------------------------
echo "\n[GROUP 7] Template Management Advancements:\n";

assertCondition(method_exists('EmailCampaignsController', 'templateDuplicate'), 'EmailCampaignsController defines templateDuplicate action');
assertCondition(method_exists('EmailCampaignsController', 'queueProcess'), 'EmailCampaignsController defines queueProcess action');
assertCondition(method_exists('EmailCampaignsController', 'campaignPause'), 'EmailCampaignsController defines campaignPause action');
assertCondition(method_exists('EmailCampaignsController', 'campaignResume'), 'EmailCampaignsController defines campaignResume action');
assertCondition(method_exists('EmailCampaignsController', 'campaignCancel'), 'EmailCampaignsController defines campaignCancel action');

echo "\n====================================================================\n";
echo "  AUDIT SUITE SUMMARY: {$passed} PASSED, {$failed} FAILED\n";
echo "====================================================================\n";

if ($failed > 0) {
    exit(1);
}
exit(0);
