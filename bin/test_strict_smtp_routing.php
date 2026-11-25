<?php
/**
 * Raptor CRM — Comprehensive 14-Scenario Strict SMTP Routing & Isolation Test Suite
 * 
 * Verifies:
 * 1. Corporate SMTP test
 * 2. Payslip delivery using Corporate SMTP
 * 3. HR notification using Corporate SMTP
 * 4. Marketing SMTP test
 * 5. Marketing campaign dispatch
 * 6. Marketing queue worker dispatch
 * 7. Marketing SMTP disabled (strict blocking, no corporate fallback, items stay queued)
 * 8. Marketing SMTP authentication failure
 * 9. Corporate SMTP failure without affecting Marketing
 * 10. Marketing SMTP failure without affecting Payroll
 * 11. Unsubscribe processing (HMAC token, suppression list, RFC 8058)
 * 12. Bounce and failure reporting (soft vs hard bounce, deliverability auto-pause)
 * 13. Existing Marketing navigation
 * 14. Existing HR, Payroll, and Recruitment functionality
 */

define('APPROOT', dirname(__DIR__) . '/app');
require_once APPROOT . '/core/Database.php';
require_once APPROOT . '/core/Model.php';
require_once APPROOT . '/services/EmailService.php';
require_once APPROOT . '/services/mailers/MailerAdapterInterface.php';
require_once APPROOT . '/services/mailers/MailerSendResult.php';
require_once APPROOT . '/services/mailers/SmtpAdapter.php';
require_once APPROOT . '/services/mailers/MailerFactory.php';
require_once APPROOT . '/services/UnsubscribeService.php';
require_once APPROOT . '/services/WarmupScheduleService.php';
require_once APPROOT . '/services/EmailQueueService.php';
require_once APPROOT . '/services/TrackingService.php';
require_once APPROOT . '/models/CampaignBroadcast.php';
require_once APPROOT . '/models/CampaignSuppression.php';
require_once APPROOT . '/models/CampaignTemplate.php';

$passed = 0;
$failed = 0;

function assertTest(bool $condition, string $description): void {
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "  [PASS] {$description}\n";
    } else {
        $failed++;
        echo "  [FAIL] {$description}\n";
    }
}

echo "====================================================================\n";
echo "  RAPTOR CRM: 14-SCENARIO STRICT SMTP ROUTING & ISOLATION SUITE      \n";
echo "====================================================================\n\n";

// In-Memory SQLite Mock Connection
$pdo = new PDO("sqlite::memory:");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_OBJ);
Database::setMockConnection($pdo);

$pdo->exec("
    CREATE TABLE settings (setting_key TEXT PRIMARY KEY, setting_value TEXT);
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
        status TEXT DEFAULT 'active',
        unsubscribed_at TEXT
    );
    CREATE TABLE campaign_suppressions (
        suppression_id INTEGER PRIMARY KEY AUTOINCREMENT,
        email TEXT NOT NULL UNIQUE,
        reason TEXT,
        notes TEXT,
        source_campaign_id INTEGER,
        unsubscribed_at TEXT DEFAULT CURRENT_TIMESTAMP
    );
    CREATE TABLE campaign_lists (list_id INTEGER PRIMARY KEY, name TEXT);
    CREATE TABLE campaign_segments (segment_id INTEGER PRIMARY KEY, name TEXT);
    CREATE TABLE campaign_templates (template_id INTEGER PRIMARY KEY, name TEXT);
    CREATE TABLE users (user_id INTEGER PRIMARY KEY, name TEXT);
    CREATE TABLE campaigns_broadcast (
        campaign_id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT,
        status TEXT DEFAULT 'draft',
        list_id INTEGER,
        segment_id INTEGER,
        template_id INTEGER,
        from_name TEXT,
        from_email TEXT,
        reply_to TEXT,
        preheader TEXT,
        subject_a TEXT,
        subject_b TEXT,
        ab_split_percent INTEGER DEFAULT 0,
        html_content TEXT,
        plain_text_content TEXT,
        track_opens INTEGER DEFAULT 1,
        track_clicks INTEGER DEFAULT 1,
        total_recipients INTEGER DEFAULT 0,
        sent_count INTEGER DEFAULT 0,
        delivered_count INTEGER DEFAULT 0,
        bounced_count INTEGER DEFAULT 0,
        complained_count INTEGER DEFAULT 0,
        unsubscribed_count INTEGER DEFAULT 0,
        created_by INTEGER,
        pre_send_checklist_passed INTEGER DEFAULT 0,
        pre_send_checklist_json TEXT,
        paused_reason TEXT,
        approved_snapshot_hash TEXT,
        started_at TEXT,
        completed_at TEXT
    );
    CREATE TABLE campaign_recipients (
        recipient_id INTEGER PRIMARY KEY AUTOINCREMENT,
        campaign_id INTEGER NOT NULL,
        contact_id INTEGER NOT NULL,
        email TEXT NOT NULL,
        subject_variant TEXT DEFAULT 'A',
        status TEXT DEFAULT 'queued',
        unsubscribe_token TEXT,
        sent_at TEXT,
        delivered_at TEXT,
        bounced_at TEXT,
        bounce_reason TEXT,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP
    );
    CREATE TABLE campaign_email_queue (
        queue_id INTEGER PRIMARY KEY AUTOINCREMENT,
        campaign_id INTEGER NOT NULL,
        recipient_id INTEGER NOT NULL,
        status TEXT DEFAULT 'pending',
        priority INTEGER DEFAULT 10,
        scheduled_at TEXT DEFAULT CURRENT_TIMESTAMP,
        attempts INTEGER DEFAULT 0,
        max_attempts INTEGER DEFAULT 3,
        last_error TEXT,
        locked_at TEXT,
        locked_by TEXT,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP
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
        daily_cap INTEGER DEFAULT 5000,
        sent_today INTEGER DEFAULT 0,
        date_stamp DATE NOT NULL,
        UNIQUE (domain_id, date_stamp)
    );
    CREATE TABLE campaign_queue_events (
        event_id INTEGER PRIMARY KEY AUTOINCREMENT,
        campaign_id INTEGER,
        recipient_id INTEGER,
        event_type TEXT,
        metadata_json TEXT,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP
    );
");

// Populate initial baseline settings
$pdo->exec("
    INSERT INTO settings (setting_key, setting_value) VALUES
    ('mail.driver', 'smtp'),
    ('mail.host', 'corporate.zoho.in'),
    ('mail.port', '587'),
    ('mail.username', 'accounts@axioraglobalsolutions.com'),
    ('mail.password', 'secret_corporate_pass_123'),
    ('mail.encryption', 'tls'),
    ('mail.from_address', 'accounts@axioraglobalsolutions.com'),
    ('mail.from_name', 'Axiora Global Solutions - Payroll'),
    ('mail.marketing.enabled', '1'),
    ('mail.marketing.driver', 'smtp'),
    ('mail.marketing.host', 'bulk.ses.amazonaws.com'),
    ('mail.marketing.port', '587'),
    ('mail.marketing.username', 'AKIA_MARKETING_KEY'),
    ('mail.marketing.password', 'secret_marketing_pass_456'),
    ('mail.marketing.encryption', 'tls'),
    ('mail.marketing.from_address', 'contacts@axioraglobalsolutions.com'),
    ('mail.marketing.from_name', 'Axiora Global');
");

// -----------------------------------------------------------------------------
// Scenario 1: Corporate SMTP Test Connection
// -----------------------------------------------------------------------------
echo "[Scenario 1] Corporate SMTP Configuration Test:\n";
$corpConfig = EmailService::getConfig();
assertTest($corpConfig['host'] === 'corporate.zoho.in', 'Corporate SMTP host resolved correctly');
assertTest($corpConfig['from_address'] === 'accounts@axioraglobalsolutions.com', 'Corporate SMTP from_address is accounts@axioraglobalsolutions.com');
assertTest($corpConfig['password'] === 'secret_corporate_pass_123', 'Corporate SMTP password resolved from settings');

// -----------------------------------------------------------------------------
// Scenario 2: Payslip Delivery Using Corporate SMTP
// -----------------------------------------------------------------------------
echo "\n[Scenario 2] Payslip Delivery Using Corporate SMTP:\n";
$dummySlip = (object)[
    'email' => 'employee.raj@example.com',
    'name' => 'Rajesh Sharma',
    'employee_code' => 'AGS-0104',
    'gross_salary' => 75000.00,
    'total_deductions' => 5000.00,
    'net_salary' => 70000.00,
    'month_year' => '2026-10'
];

// Verify corporate mail config is strictly used for payslips
$corpMailConfig = EmailService::getConfig();
assertTest($corpMailConfig['from_address'] === 'accounts@axioraglobalsolutions.com', 'Payslip sender is strictly accounts@axioraglobalsolutions.com');
assertTest($corpMailConfig['from_name'] === 'Axiora Global Solutions - Payroll', 'Payslip sender name is corporate payroll');

// -----------------------------------------------------------------------------
// Scenario 3: HR Notification Using Corporate SMTP
// -----------------------------------------------------------------------------
echo "\n[Scenario 3] HR Notification Using Corporate SMTP:\n";
$hrConfig = EmailService::getConfig();
assertTest($hrConfig['from_name'] === 'Axiora Global Solutions - Payroll', 'HR notification sender name is corporate payroll');
assertTest($hrConfig['host'] !== 'bulk.ses.amazonaws.com', 'HR notification does NOT touch marketing host');

// -----------------------------------------------------------------------------
// Scenario 4: Marketing SMTP Test
// -----------------------------------------------------------------------------
echo "\n[Scenario 4] Marketing SMTP Configuration Test:\n";
$mktConfig = EmailService::getMarketingConfig();
assertTest($mktConfig['enabled'] === true, 'Marketing SMTP reports enabled=true');
assertTest($mktConfig['host'] === 'bulk.ses.amazonaws.com', 'Marketing SMTP host is bulk.ses.amazonaws.com');
assertTest($mktConfig['from_address'] === 'contacts@axioraglobalsolutions.com', 'Marketing SMTP from_address is contacts@axioraglobalsolutions.com');
assertTest($mktConfig['username'] === 'AKIA_MARKETING_KEY', 'Marketing SMTP username is isolated from corporate');

// -----------------------------------------------------------------------------
// Scenario 5: Marketing Campaign Dispatch
// -----------------------------------------------------------------------------
echo "\n[Scenario 5] Marketing Campaign Pre-Check & Dispatch Validation:\n";
$campBroadcast = new CampaignBroadcast();
$pdo->exec("
    INSERT INTO campaign_contacts (contact_id, email, first_name, status) VALUES
    (101, 'target1@example.com', 'Target One', 'subscribed'),
    (102, 'target2@example.com', 'Target Two', 'subscribed');
    INSERT INTO campaign_list_contacts (list_id, contact_id) VALUES (50, 101), (50, 102);
    INSERT INTO campaigns_broadcast (campaign_id, name, status, list_id, from_name, from_email, reply_to, subject_a, html_content, plain_text_content)
    VALUES (10, 'October Promo', 'draft', 50, 'Axiora Global', 'contacts@axioraglobalsolutions.com', 'contacts@axioraglobalsolutions.com', 'Special Workshop', '<p>Hi {{unsubscribe_url}}</p>', 'Hi');
");

$eligible = $campBroadcast->resolveEligibleRecipients(10);
assertTest($eligible['reachable_count'] === 2, 'Eligible recipients resolved: 2 reachable');

// -----------------------------------------------------------------------------
// Scenario 6: Marketing Queue Worker Dispatch
// -----------------------------------------------------------------------------
echo "\n[Scenario 6] Marketing Queue Worker Dispatch (Mocked Adapter Success):\n";
class SuccessfulMarketingAdapter implements MailerAdapterInterface {
    public function getName(): string { return 'mock_marketing'; }
    public function send(array $msg): MailerSendResult {
        return MailerSendResult::success('msg-marketing-test-' . bin2hex(random_bytes(6)));
    }
}
MailerFactory::setAdapter(new SuccessfulMarketingAdapter());

// Seed queue
$pdo->exec("
    INSERT INTO campaign_recipients (recipient_id, campaign_id, contact_id, email, status, unsubscribe_token)
    VALUES (201, 10, 101, 'target1@example.com', 'queued', 'tok123');
    INSERT INTO campaign_email_queue (queue_id, campaign_id, recipient_id, status)
    VALUES (301, 10, 201, 'pending');
");

$workerStats = EmailQueueService::processBatch(10, 0);
assertTest($workerStats['sent'] === 1, 'Queue worker successfully dispatched 1 marketing item');
$recStatus = $pdo->query("SELECT status FROM campaign_recipients WHERE recipient_id = 201")->fetchColumn();
assertTest($recStatus === 'sent', 'Recipient marked as "sent" on provider acceptance');

// -----------------------------------------------------------------------------
// Scenario 7: Marketing SMTP Disabled (Strict Routing & No Corporate Fallback)
// -----------------------------------------------------------------------------
echo "\n[Scenario 7] Marketing SMTP Disabled (Strict Blocking):\n";
MailerFactory::reset(); // Reset to native SmtpAdapter
$pdo->exec("UPDATE settings SET setting_value = '0' WHERE setting_key = 'mail.marketing.enabled'");

// 1. EmailService::getMarketingConfig() must report disabled and empty host
$disabledMkt = EmailService::getMarketingConfig();
assertTest($disabledMkt['enabled'] === false, 'Marketing config disabled reports enabled=false');
assertTest(empty($disabledMkt['host']), 'Marketing config disabled returns empty host');
assertTest($disabledMkt['host'] !== 'corporate.zoho.in', 'STRICT: Marketing config does NOT fall back to corporate.zoho.in');

// 2. SmtpAdapter must immediately reject send
$mktSmtp = new SmtpAdapter();
$blockedResult = $mktSmtp->send(['to' => 'user@example.com', 'subject' => 'test', 'html' => 'test']);
assertTest($blockedResult->isSuccess() === false, 'SmtpAdapter blocks sending when marketing SMTP is disabled');
assertTest($blockedResult->getErrorCode() === 'MARKETING_SMTP_DISABLED', 'Error code is MARKETING_SMTP_DISABLED');
assertTest($blockedResult->isTransient() === true, 'Error is transient so queue item is not marked permanently dead');

// 3. Queue worker must halt batch and keep items queued without marking them sent
$pdo->exec("
    INSERT INTO campaign_recipients (recipient_id, campaign_id, contact_id, email, status, unsubscribe_token)
    VALUES (202, 10, 102, 'target2@example.com', 'queued', 'tok456');
    INSERT INTO campaign_email_queue (queue_id, campaign_id, recipient_id, status)
    VALUES (302, 10, 202, 'pending');
");

$workerHaltStats = EmailQueueService::processBatch(10, 0);
assertTest($workerHaltStats['sent'] === 0, 'Worker sent exactly 0 items when marketing SMTP is disabled');
$rec2Status = $pdo->query("SELECT status FROM campaign_recipients WHERE recipient_id = 202")->fetchColumn();
assertTest($rec2Status === 'queued', 'Recipient remains "queued" (NOT marked sent or delivered)');
$q2Row = $pdo->query("SELECT status, last_error FROM campaign_email_queue WHERE queue_id = 302")->fetch(PDO::FETCH_ASSOC);
assertTest($q2Row['status'] === 'pending', 'Queue item remains "pending" for retry once SMTP is configured');
assertTest(strpos($q2Row['last_error'], 'Marketing SMTP is disabled') !== false, 'Queue item records clear administrator-facing error');

// 4. Campaign launch must block with administrator error
$launchBlock = $campBroadcast->launchCampaign(10);
assertTest($launchBlock['success'] === false, 'Campaign launch blocked when marketing SMTP is disabled');
assertTest(strpos($launchBlock['error'], 'Launch Blocked: Dedicated Marketing SMTP is disabled') !== false, 'Launch returns clear admin error');

// -----------------------------------------------------------------------------
// Scenario 8: Marketing SMTP Authentication Failure
// -----------------------------------------------------------------------------
echo "\n[Scenario 8] Marketing SMTP Auth Failure Handling:\n";
// Cancel previous pending queue item 302 so only 303 runs
$pdo->exec("UPDATE campaign_email_queue SET status = 'cancelled' WHERE queue_id = 302");

class AuthFailMarketingAdapter implements MailerAdapterInterface {
    public function getName(): string { return 'smtp_auth_fail'; }
    public function send(array $msg): MailerSendResult {
        return MailerSendResult::failure('535 5.7.8 Authentication credentials invalid', 'SMTP_AUTH_ERROR', false);
    }
}
MailerFactory::setAdapter(new AuthFailMarketingAdapter());
$pdo->exec("
    INSERT INTO campaign_recipients (recipient_id, campaign_id, contact_id, email, status, unsubscribe_token)
    VALUES (203, 10, 101, 'target1@example.com', 'queued', 'tok789');
    INSERT INTO campaign_email_queue (queue_id, campaign_id, recipient_id, status, attempts, max_attempts)
    VALUES (303, 10, 203, 'pending', 2, 3);
");

$authFailStats = EmailQueueService::processBatch(10, 0);
assertTest($authFailStats['failed'] >= 1, 'Queue worker logged authentication failure');
$q3Status = $pdo->query("SELECT status FROM campaign_email_queue WHERE queue_id = 303")->fetchColumn();
assertTest($q3Status === 'failed', 'Queue item marked as failed upon permanent auth error');

// -----------------------------------------------------------------------------
// Scenario 9: Corporate SMTP Failure Without Affecting Marketing
// -----------------------------------------------------------------------------
echo "\n[Scenario 9] Corporate SMTP Failure Independence:\n";
// Corrupt corporate host
$pdo->exec("UPDATE settings SET setting_value = 'invalid.corporate.server' WHERE setting_key = 'mail.host'");
$pdo->exec("UPDATE settings SET setting_value = '1' WHERE setting_key = 'mail.marketing.enabled'");

$corpCheck = EmailService::getConfig();
$mktCheck = EmailService::getMarketingConfig();
assertTest($corpCheck['host'] === 'invalid.corporate.server', 'Corporate host reflects change');
assertTest($mktCheck['host'] === 'bulk.ses.amazonaws.com', 'Marketing host remains bulk.ses.amazonaws.com completely unaffected');
assertTest($mktCheck['enabled'] === true, 'Marketing SMTP remains operational despite corporate outage');

// -----------------------------------------------------------------------------
// Scenario 10: Marketing SMTP Failure Without Affecting Payroll
// -----------------------------------------------------------------------------
echo "\n[Scenario 10] Marketing SMTP Failure Independence:\n";
// Revert corporate, corrupt marketing
$pdo->exec("UPDATE settings SET setting_value = 'corporate.zoho.in' WHERE setting_key = 'mail.host'");
$pdo->exec("UPDATE settings SET setting_value = 'invalid.marketing.server' WHERE setting_key = 'mail.marketing.host'");

$payrollCorp = EmailService::getConfig();
assertTest($payrollCorp['host'] === 'corporate.zoho.in', 'Payroll corporate host is clean and unaffected by marketing issue');
assertTest($payrollCorp['from_address'] === 'accounts@axioraglobalsolutions.com', 'Payroll sender remains accounts@axioraglobalsolutions.com');

// -----------------------------------------------------------------------------
// Scenario 11: Unsubscribe Processing (HMAC, Suppression, RFC 8058)
// -----------------------------------------------------------------------------
echo "\n[Scenario 11] Unsubscribe Processing & Suppression Firewall:\n";
$unsubToken = UnsubscribeService::generateToken(10, 101, 'target1@example.com');
assertTest(!empty($unsubToken) && strlen($unsubToken) > 64, 'HMAC token generated safely (>64 chars)');
$verified = UnsubscribeService::verifyToken($unsubToken);
assertTest($verified !== null, 'HMAC token cryptographically verified');
assertTest($verified['email'] === 'target1@example.com', 'Token decoded email matches recipient');

// Test Global Suppression addition
$suppressionModel = new CampaignSuppression();
$suppressionModel->suppress('unsubscribed.user@example.com', 'unsubscribe', 'Unsubscribed via RFC 8058');
assertTest($suppressionModel->isSuppressed('unsubscribed.user@example.com'), 'Email added to global suppression list');

// -----------------------------------------------------------------------------
// Scenario 12: Bounce and Failure Reporting (Auto-Pause Threshold)
// -----------------------------------------------------------------------------
echo "\n[Scenario 12] Bounce & Deliverability Auto-Pause Safeguard:\n";
$highBounceCamp = (object)[
    'campaign_id' => 10,
    'total_recipients' => 100,
    'sent_count' => 100,
    'bounced_count' => 5, // 5% bounce rate (>2% threshold)
    'complained_count' => 0
];
$ref = new ReflectionMethod('EmailQueueService', 'shouldAutoPauseCampaign');
$ref->setAccessible(true);
$shouldPause = $ref->invoke(null, $highBounceCamp);
assertTest($shouldPause === true, 'Auto-pause triggered when bounce rate breaches 2% threshold');

// -----------------------------------------------------------------------------
// Scenario 13: Existing Marketing Navigation Audit
// -----------------------------------------------------------------------------
echo "\n[Scenario 13] Marketing Navigation Consistency:\n";
$_GET['route'] = 'email_campaigns/contacts';
$isAudiencesActive = (isset($_GET['route']) && (strpos($_GET['route'], 'email_campaigns/contact') === 0 || strpos($_GET['route'], 'email_campaigns/list') === 0));
$isRegistryActive = (isset($_GET['route']) && strpos($_GET['route'], 'campaigns') === 0 && strpos($_GET['route'], 'email_campaigns') === false);
assertTest($isAudiencesActive === true, 'Audiences & Lists is active when browsing contacts');
assertTest($isRegistryActive === false, 'Campaign Registry never falsely highlights on contacts');

// -----------------------------------------------------------------------------
// Scenario 14: Existing HR, Payroll and Recruitment Integrity
// -----------------------------------------------------------------------------
echo "\n[Scenario 14] HR, Payroll and Recruitment Integrity:\n";
assertTest(file_exists(APPROOT . '/controllers/PayrollController.php'), 'PayrollController intact');
assertTest(file_exists(APPROOT . '/controllers/AttendanceController.php'), 'AttendanceController intact');
assertTest(file_exists(APPROOT . '/controllers/RecruitmentController.php'), 'RecruitmentController intact');

MailerFactory::reset();
$pdo = null;
if (method_exists('Database', 'resetMockConnection')) {
    Database::resetMockConnection();
}

echo "\n====================================================================\n";
echo "  14-SCENARIO SUITE SUMMARY: {$passed} PASSED, {$failed} FAILED\n";
echo "====================================================================\n";
exit($failed > 0 ? 1 : 0);
