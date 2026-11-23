<?php
/**
 * Raptor CRM - AWS SES, Email Warmup, 120s Pacing & Spam-Free Verification Suite
 * 
 * Verifies:
 * 1. AWS SES adapter configuration, headers, and MailerFactory resolution
 * 2. Email warmup option (daily stage caps and domain throttling)
 * 3. 120-second minimum dispatch gap between consecutive recipient emails (anti-bulk)
 * 4. Spam-free content engine (zero-tolerance pre-send gate & content scanner)
 */

declare(strict_types=1);

echo "====================================================================\n";
echo "  RAPTOR CRM: AWS SES, WARMUP, 120S PACING & SPAM-FREE VERIFICATION\n";
echo "====================================================================\n\n";

require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/core/Model.php';

// Setup SQLite in-memory test database for isolated unit testing
$testDb = new PDO('sqlite::memory:');
$testDb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$testDb->exec("CREATE TABLE users (
    user_id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT,
    email TEXT
);
INSERT INTO users (user_id, name, email) VALUES (1, 'Admin', 'admin@raptor.local');

CREATE TABLE campaign_lists (
    list_id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL
);

CREATE TABLE campaign_segments (
    segment_id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL
);

CREATE TABLE campaign_templates (
    template_id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL
);

CREATE TABLE campaigns_broadcast (
    campaign_id INTEGER PRIMARY KEY AUTOINCREMENT,
    campaign_code TEXT,
    name TEXT,
    subject_a TEXT,
    subject_b TEXT,
    ab_split_percent INTEGER DEFAULT 0,
    ab_winner_metric TEXT DEFAULT 'opens',
    preheader TEXT,
    from_name TEXT,
    from_email TEXT,
    reply_to TEXT,
    list_id INTEGER,
    segment_id INTEGER,
    template_id INTEGER,
    html_content TEXT,
    plain_text_content TEXT,
    track_opens INTEGER DEFAULT 1,
    track_clicks INTEGER DEFAULT 1,
    is_warmup_enabled INTEGER DEFAULT 1,
    send_interval_seconds INTEGER DEFAULT 120,
    communication_intent TEXT DEFAULT 'promotional',
    content_sha256 TEXT,
    approved_snapshot_hash TEXT,
    pre_send_checklist_passed INTEGER DEFAULT 0,
    pre_send_checklist_json TEXT,
    status TEXT DEFAULT 'draft',
    total_recipients INTEGER DEFAULT 0,
    sent_count INTEGER DEFAULT 0,
    bounced_count INTEGER DEFAULT 0,
    complained_count INTEGER DEFAULT 0,
    delivered_count INTEGER DEFAULT 0,
    opened_count INTEGER DEFAULT 0,
    clicked_count INTEGER DEFAULT 0,
    created_by INTEGER DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    started_at DATETIME
);

CREATE TABLE campaign_recipients (
    recipient_id INTEGER PRIMARY KEY AUTOINCREMENT,
    campaign_id INTEGER,
    contact_id INTEGER,
    email TEXT,
    subject_variant TEXT DEFAULT 'A',
    status TEXT DEFAULT 'queued',
    unsubscribe_token TEXT,
    sent_at DATETIME
);

CREATE TABLE campaign_email_queue (
    queue_id INTEGER PRIMARY KEY AUTOINCREMENT,
    campaign_id INTEGER,
    recipient_id INTEGER,
    status TEXT DEFAULT 'pending',
    priority INTEGER DEFAULT 10,
    scheduled_at DATETIME,
    attempts INTEGER DEFAULT 0,
    max_attempts INTEGER DEFAULT 3,
    last_error TEXT,
    locked_at DATETIME,
    locked_by TEXT
);

CREATE TABLE campaign_contacts (
    contact_id INTEGER PRIMARY KEY AUTOINCREMENT,
    email TEXT,
    first_name TEXT,
    last_name TEXT,
    company_name TEXT,
    status TEXT DEFAULT 'subscribed',
    custom_fields TEXT
);

CREATE TABLE campaign_domains (
    domain_id INTEGER PRIMARY KEY AUTOINCREMENT,
    domain_name TEXT UNIQUE,
    is_default INTEGER DEFAULT 0
);

CREATE TABLE campaign_warmup_schedules (
    warmup_id INTEGER PRIMARY KEY AUTOINCREMENT,
    domain_id INTEGER NOT NULL,
    current_stage INTEGER NOT NULL DEFAULT 1,
    daily_cap INTEGER NOT NULL DEFAULT 50,
    sent_today INTEGER NOT NULL DEFAULT 0,
    date_stamp TEXT NOT NULL,
    UNIQUE(domain_id, date_stamp)
);

CREATE TABLE campaign_suppressions (
    suppression_id INTEGER PRIMARY KEY AUTOINCREMENT,
    email TEXT UNIQUE,
    reason TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE settings (
    setting_key TEXT PRIMARY KEY,
    setting_value TEXT
);
");

// Insert test contacts
$testDb->exec("INSERT INTO campaign_contacts (email, first_name, last_name, status) VALUES 
    ('user1@example.com', 'Alice', 'Smith', 'subscribed'),
    ('user2@example.com', 'Bob', 'Jones', 'subscribed'),
    ('user3@example.com', 'Carol', 'White', 'subscribed')");

// Override Database singleton connection
$dbProp = (new ReflectionClass('Database'))->getProperty('instance');
$dbProp->setAccessible(true);
$mockDatabase = new class($testDb) {
    private PDO $pdo;
    public function __construct(PDO $pdo) { $this->pdo = $pdo; }
    public function getConnection(): PDO { return $this->pdo; }
};
$dbProp->setValue(null, $mockDatabase);

// Load models and services
require_once __DIR__ . '/../app/models/CampaignBroadcast.php';
require_once __DIR__ . '/../app/models/CampaignTemplate.php';
require_once __DIR__ . '/../app/models/CampaignSuppression.php';
require_once __DIR__ . '/../app/services/UnsubscribeService.php';
require_once __DIR__ . '/../app/services/WarmupScheduleService.php';
require_once __DIR__ . '/../app/services/TrackingService.php';
require_once __DIR__ . '/../app/services/EmailService.php';
require_once __DIR__ . '/../app/services/EmailQueueService.php';
require_once __DIR__ . '/../app/services/PreSendChecklistService.php';
require_once __DIR__ . '/../app/services/mailers/MailerSendResult.php';
require_once __DIR__ . '/../app/services/mailers/MailerAdapterInterface.php';
require_once __DIR__ . '/../app/services/mailers/SmtpAdapter.php';
require_once __DIR__ . '/../app/services/mailers/SesAdapter.php';
require_once __DIR__ . '/../app/services/mailers/MockMailerAdapter.php';
require_once __DIR__ . '/../app/services/mailers/MailerFactory.php';

$passed = 0;
$failed = 0;

$assertCondition = function(bool $cond, string $desc) use (&$passed, &$failed) {
    if ($cond) {
        echo "  [PASS] $desc\n";
        $passed++;
    } else {
        echo "  [FAIL] $desc\n";
        $failed++;
    }
};

// -----------------------------------------------------------------------------
// 1. AWS SES Adapter & MailerFactory Integration Tests
// -----------------------------------------------------------------------------
echo "\n--- 1. AWS SES Provider Adapter & MailerFactory Integration ---\n";

$sesConfig = [
    'region'            => 'us-east-1',
    'smtp_host'         => 'email-smtp.us-east-1.amazonaws.com',
    'smtp_user'         => 'AKIAIOSFODNN7EXAMPLE',
    'smtp_pass'         => 'wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY',
    'smtp_port'         => 587,
    'configuration_set' => 'raptor-marketing-set'
];

$ses = new SesAdapter($sesConfig);
$assertCondition($ses->getName() === 'ses', 'SesAdapter reports name as "ses"');

$builtConfig = $ses->getConfig();
$assertCondition($builtConfig['region'] === 'us-east-1', 'SesAdapter correctly configured with us-east-1 region');
$assertCondition($builtConfig['smtp_host'] === 'email-smtp.us-east-1.amazonaws.com', 'SesAdapter defaults host to regional SES endpoint');
$assertCondition($builtConfig['configuration_set'] === 'raptor-marketing-set', 'SesAdapter preserves AWS SES configuration set');

// Test MailerFactory AWS SES resolution
MailerFactory::reset();
$_ENV['MAIL_PROVIDER'] = 'ses';
$factorySes = MailerFactory::getAdapter();
$assertCondition($factorySes instanceof SesAdapter, 'MailerFactory resolves SesAdapter when MAIL_PROVIDER=ses');

// Test auto-generation of SES endpoint for different regions
$mumbaiSes = new SesAdapter(['region' => 'ap-south-1']);
$mumbaiConfig = $mumbaiSes->getConfig();
$assertCondition($mumbaiConfig['smtp_host'] === 'email-smtp.ap-south-1.amazonaws.com', 'SesAdapter dynamically constructs regional endpoint for ap-south-1');

unset($_ENV['MAIL_PROVIDER']);
MailerFactory::reset();

// -----------------------------------------------------------------------------
// 2. Email Warmup Option & Domain Ramp Throttling Tests
// -----------------------------------------------------------------------------
echo "\n--- 2. Email Warmup Option & Throttling Guardrails ---\n";

$testDomain = 'warmup-test-' . bin2hex(random_bytes(3)) . '.com';

// Verify initial warmup state (Stage 1: 50 emails/day)
$status = WarmupScheduleService::getDailyStatus($testDomain);
$assertCondition($status['stage'] === 1, 'New sender domain starts at Warm-up Stage 1');
$assertCondition($status['daily_cap'] === 50, 'Stage 1 daily cap is strictly 50 emails/day');
$assertCondition($status['sent_today'] === 0, 'New domain has 0 emails recorded today');
$assertCondition($status['remaining_today'] === 50, '50 sends remaining in Stage 1 quota');

// Record test sends
WarmupScheduleService::recordSend($testDomain);
WarmupScheduleService::recordSend($testDomain);
$updatedStatus = WarmupScheduleService::getDailyStatus($testDomain);
$assertCondition($updatedStatus['sent_today'] === 2, 'WarmupScheduleService records dispatches against domain');
$assertCondition($updatedStatus['remaining_today'] === 48, 'Remaining daily warm-up quota accurately decrements');

// -----------------------------------------------------------------------------
// 3. 120-Second Pacing & Anti-Bulk Schedule Staggering Tests
// -----------------------------------------------------------------------------
echo "\n--- 3. 120-Second Pacing & Non-Bulk Schedule Staggering ---\n";

// Create campaign with 120s pacing
$campModel = new CampaignBroadcast();
$campId = $campModel->saveCampaign([
    'name' => 'Non-Bulk Paced Campaign',
    'subject_a' => 'Operational Quarterly Briefing',
    'from_name' => 'Axiora Global',
    'from_email' => 'contacts@axioraglobalsolutions.com',
    'html_content' => '<p>Hello {{first_name}}, please find attached your briefing. Axiora Global Solutions, Cyber City, Hyderabad, India | <a href="{{unsubscribe_url}}">Unsubscribe</a></p>',
    'plain_text_content' => "Hello {{first_name}},\n\nQuarterly Briefing.\n\nAxiora Global Solutions, Cyber City, Hyderabad, India | Unsubscribe: {{unsubscribe_url}}",
    'list_id' => -1, // All contacts
    'is_warmup_enabled' => 1,
    'send_interval_seconds' => 120
], 1);

$savedCamp = $campModel->getById($campId);
$assertCondition((int)$savedCamp->send_interval_seconds === 120, 'Campaign saves send_interval_seconds = 120s');
$assertCondition((int)$savedCamp->is_warmup_enabled === 1, 'Campaign saves is_warmup_enabled = 1');

// Launch campaign and verify queue scheduling
$launchRes = $campModel->launchCampaign($campId, 1);
$assertCondition($launchRes['success'] === true, 'Paced campaign passes launch validation');
$assertCondition($launchRes['queued_count'] === 3, 'All 3 eligible contacts queued');

// Inspect scheduled_at timestamps for all 3 recipients
$qItems = $testDb->query("SELECT * FROM campaign_email_queue WHERE campaign_id = {$campId} ORDER BY queue_id ASC")->fetchAll(PDO::FETCH_OBJ);
$assertCondition(count($qItems) === 3, 'Found 3 queued messages in database');

$t0 = strtotime($qItems[0]->scheduled_at);
$t1 = strtotime($qItems[1]->scheduled_at);
$t2 = strtotime($qItems[2]->scheduled_at);

$diff01 = $t1 - $t0;
$diff12 = $t2 - $t1;

$assertCondition($diff01 === 120, "Strict 120s gap between recipient 1 and recipient 2 (Gap: {$diff01}s)");
$assertCondition($diff12 === 120, "Strict 120s gap between recipient 2 and recipient 3 (Gap: {$diff12}s)");

// -----------------------------------------------------------------------------
// 4. Spam-Free Content Verification Engine Tests
// -----------------------------------------------------------------------------
echo "\n--- 4. Spam-Free Content Scanner & Blocker Verification ---\n";

// Test A: 100% Spam-free clean email
$cleanHtml = '<p>Dear Customer, your service update has been published. Axiora Global Solutions, Cyber City, Hyderabad, India | <a href="https://axiora.in/unsubscribe">Unsubscribe</a></p>';
$cleanPlain = "Dear Customer, your service update has been published.\n\nAxiora Global Solutions, Cyber City, Hyderabad, India | Unsubscribe: https://axiora.in/unsubscribe";
$cleanScan = PreSendChecklistService::scanContentForSpam('Your Monthly Statement & System Update', $cleanHtml, $cleanPlain);

$assertCondition($cleanScan['is_spam_free'] === true, 'Legitimate transactional/corporate copy is verified 100% spam-free');
$assertCondition($cleanScan['spam_score'] === 0.0, 'Spam score is strictly 0.0/10 for clean email');
$assertCondition(empty($cleanScan['blockers']), 'Zero pre-send blockers for clean content');

// Test B: High-risk spam copy (Must be BLOCKED)
$spamHtml = '<p>CONGRATULATIONS WINNER! Click here to claim your 100% FREE CASINO CASH BONUS! Double your income with pure profit! $$$</p>';
$spamPlain = "CONGRATULATIONS WINNER! Claim 100% FREE CASH BONUS! Make money now!";
$spamScan = PreSendChecklistService::scanContentForSpam('WINNER: CLAIM YOUR CASH BONUS NOW $$$', $spamHtml, $spamPlain);

$assertCondition($spamScan['is_spam_free'] === false, 'Spammy promotional copy is REJECTED by spam scanner');
$assertCondition($spamScan['spam_score'] >= 5.0, "Spam score reflects severe risk (Score: {$spamScan['spam_score']}/10)");
$assertCondition(count($spamScan['blockers']) > 0, 'Spam scanner issues critical pre-send BLOCKERS');
$assertCondition(count($spamScan['triggers']) >= 3, 'Identified specific prohibited spam trigger phrases');

// Test C: PreSendChecklistService::run() blocks spammy campaign
$spammyCamp = [
    'name' => 'Spam Blast Attempt',
    'subject_a' => 'WIN BIG FAST CASH 100% FREE $$$',
    'html_content' => '<p>Make money double your income risk free casino! Axiora Global Solutions, Cyber City, Hyderabad. <a href="{{unsubscribe_url}}">Unsubscribe</a></p>',
    'plain_text_content' => 'Make money double your income risk free casino! Axiora Global Solutions, Cyber City, Hyderabad. Unsubscribe: {{unsubscribe_url}}',
    'from_email' => 'contacts@axioraglobalsolutions.com'
];
$checklistSpam = PreSendChecklistService::run($spammyCamp);
$assertCondition($checklistSpam['passed'] === false, 'PreSendChecklistService strictly blocks spammy campaign launch');
$assertCondition(strpos(implode(' ', $checklistSpam['blockers']), 'Spam score too high') !== false, 'Blocker specifically cites Spam Score violation');

echo "\n====================================================================\n";
echo "  SUMMARY: {$passed} PASSED, {$failed} FAILED\n";
echo "====================================================================\n";

if ($failed > 0) {
    exit(1);
}
exit(0);
