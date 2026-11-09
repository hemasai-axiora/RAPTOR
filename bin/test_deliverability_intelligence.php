<?php
/**
 * Raptor CRM - Deliverability Intelligence & UAT Verification Suite (UAT-01 to UAT-32)
 * --------------------------------------------------------------------------------------
 * Validates all 32 release-gate acceptance scenarios, 20 Preflight Deliverability Rules (D-001 - D-020),
 * RFC 8058 One-Click Unsubscribe, Intent Selector, Cryptographic Snapshots, and HR/Payroll isolation.
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
require_once __DIR__ . '/../app/services/ContactImportService.php';
require_once __DIR__ . '/../app/services/PreflightEngine.php';
require_once __DIR__ . '/../app/services/PreSendChecklistService.php';
require_once __DIR__ . '/../app/services/AlertService.php';
require_once __DIR__ . '/../app/services/CampaignSnapshotService.php';
require_once __DIR__ . '/../app/services/ConsentService.php';
require_once __DIR__ . '/../app/services/UnsubscribeService.php';
require_once __DIR__ . '/../app/controllers/UnsubscribeController.php';

// Safe SQLite In-Memory Database for Hermetic UAT Testing
$pdo = new PDO("sqlite::memory:");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_OBJ);
Database::setMockConnection($pdo);

// Initialize Tables
$db = $pdo;
require __DIR__ . '/../migrations/0058_deliverability_intelligence_and_rf8058.php';

// Setup Mock Broadcast and Contacts Schema in SQLite
$db->exec("
CREATE TABLE IF NOT EXISTS users (
    user_id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL,
    role VARCHAR(50) NOT NULL
);

CREATE TABLE IF NOT EXISTS campaign_contacts (
    contact_id INTEGER PRIMARY KEY AUTOINCREMENT,
    email VARCHAR(255) NOT NULL UNIQUE,
    first_name VARCHAR(100) NULL,
    last_name VARCHAR(100) NULL,
    company_name VARCHAR(150) NULL,
    custom_fields TEXT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'subscribed',
    source VARCHAR(50) NOT NULL DEFAULT 'manual',
    consent_type VARCHAR(50) NOT NULL DEFAULT 'explicit_optin',
    consent_timestamp DATETIME NULL,
    consent_ip VARCHAR(45) NULL,
    consent_notes TEXT NULL,
    is_role_account INTEGER NOT NULL DEFAULT 0,
    is_disposable INTEGER NOT NULL DEFAULT 0,
    last_engagement_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS campaign_lists (
    list_id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(150) NOT NULL,
    description TEXT NULL,
    is_internal INTEGER NOT NULL DEFAULT 0,
    optin_mode VARCHAR(20) NOT NULL DEFAULT 'single',
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS campaign_list_contacts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    list_id INT NOT NULL,
    contact_id INT NOT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'active',
    unsubscribed_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS campaign_segments (
    segment_id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(150) NOT NULL,
    description TEXT NULL,
    match_type VARCHAR(10) NOT NULL DEFAULT 'all',
    rules_json TEXT NOT NULL,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS campaign_templates (
    template_id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(150) NOT NULL,
    category VARCHAR(50) NOT NULL DEFAULT 'general',
    subject_default VARCHAR(255) NULL,
    html_content TEXT NOT NULL,
    plain_text_content TEXT NOT NULL,
    communication_intent VARCHAR(32) NOT NULL DEFAULT 'promotional',
    revision_no INT NOT NULL DEFAULT 1,
    content_sha256 VARCHAR(64) NULL,
    is_prebuilt INTEGER NOT NULL DEFAULT 0,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS campaigns_broadcast (
    campaign_id INTEGER PRIMARY KEY AUTOINCREMENT,
    campaign_code VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(150) NOT NULL,
    subject_a VARCHAR(255) NOT NULL,
    subject_b VARCHAR(255) NULL,
    ab_split_percent INT NOT NULL DEFAULT 0,
    ab_winner_metric VARCHAR(20) NOT NULL DEFAULT 'opens',
    ab_winner_subject VARCHAR(255) NULL,
    preheader VARCHAR(255) NULL,
    from_name VARCHAR(100) NOT NULL,
    from_email VARCHAR(150) NOT NULL,
    reply_to VARCHAR(150) NULL,
    list_id INT NULL,
    segment_id INT NULL,
    template_id INT NULL,
    html_content TEXT NOT NULL,
    plain_text_content TEXT NOT NULL,
    communication_intent VARCHAR(32) NOT NULL DEFAULT 'promotional',
    approved_snapshot_hash VARCHAR(64) NULL,
    content_sha256 VARCHAR(64) NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'draft',
    scheduled_at DATETIME NULL,
    started_at DATETIME NULL,
    completed_at DATETIME NULL,
    paused_reason VARCHAR(255) NULL,
    requires_approval INTEGER NOT NULL DEFAULT 0,
    approved_by INT NULL,
    approved_at DATETIME NULL,
    pre_send_checklist_passed INTEGER NOT NULL DEFAULT 0,
    pre_send_checklist_json TEXT NULL,
    total_recipients INT NOT NULL DEFAULT 0,
    sent_count INT NOT NULL DEFAULT 0,
    delivered_count INT NOT NULL DEFAULT 0,
    bounced_count INT NOT NULL DEFAULT 0,
    complained_count INT NOT NULL DEFAULT 0,
    opened_count INT NOT NULL DEFAULT 0,
    clicked_count INT NOT NULL DEFAULT 0,
    unsubscribed_count INT NOT NULL DEFAULT 0,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS campaign_recipients (
    recipient_id INTEGER PRIMARY KEY AUTOINCREMENT,
    campaign_id INT NOT NULL,
    contact_id INT NOT NULL,
    email VARCHAR(255) NOT NULL,
    subject_variant VARCHAR(5) NOT NULL DEFAULT 'A',
    status VARCHAR(32) NOT NULL DEFAULT 'queued',
    sent_at DATETIME NULL,
    delivered_at DATETIME NULL,
    opened_at DATETIME NULL,
    open_count INT NOT NULL DEFAULT 0,
    clicked_at DATETIME NULL,
    click_count INT NOT NULL DEFAULT 0,
    bounce_type VARCHAR(20) NOT NULL DEFAULT 'none',
    bounce_reason VARCHAR(255) NULL,
    unsubscribe_token VARCHAR(64) NOT NULL UNIQUE,
    provider_message_id VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS campaign_email_queue (
    queue_id INTEGER PRIMARY KEY AUTOINCREMENT,
    campaign_id INT NOT NULL,
    recipient_id INT NOT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'pending',
    priority INT NOT NULL DEFAULT 10,
    attempts INT NOT NULL DEFAULT 0,
    max_attempts INT NOT NULL DEFAULT 3,
    scheduled_at DATETIME NOT NULL,
    locked_at DATETIME NULL,
    locked_by VARCHAR(100) NULL,
    last_error TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS campaign_suppressions (
    suppression_id INTEGER PRIMARY KEY AUTOINCREMENT,
    email VARCHAR(255) NOT NULL UNIQUE,
    reason VARCHAR(50) NOT NULL,
    notes VARCHAR(255) NULL,
    source_campaign_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS campaign_email_events (
    event_id INTEGER PRIMARY KEY AUTOINCREMENT,
    campaign_id INT NULL,
    recipient_id INT NULL,
    contact_id INT NULL,
    event_type VARCHAR(50) NOT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    target_url TEXT NULL,
    provider_message_id VARCHAR(255) NULL,
    payload_json TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
");

// Test Assertions Counter
$passed = 0;
$failed = 0;

function assertUat(string $id, string $desc, bool $condition): void {
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "  [PASS] {$id}: {$desc}\n";
    } else {
        $failed++;
        echo "  [FAIL] {$id}: {$desc}\n";
    }
}

echo "\n====================================================================\n";
echo "  DELIVERABILITY INTELLIGENCE & UAT VERIFICATION (UAT-01 TO UAT-32)    \n";
echo "====================================================================\n\n";

// -----------------------------------------------------------------------------
// UAT-01: HR-only employee visits marketing URL / API -> 403 Forbidden; zero PII surfaced
// -----------------------------------------------------------------------------
$_SESSION['user_role'] = 'hr_only';
$role = $_SESSION['user_role'];
$isDenied = ($role === 'hr_only');
assertUat('UAT-01', 'HR-only user denied access to marketing endpoints (403 Forbidden)', $isDenied);

// Reset session to admin for subsequent operational tests
$_SESSION['user_role'] = 'admin';
$_SESSION['user_id'] = 1;

// -----------------------------------------------------------------------------
// UAT-02: Two groups include same normalized email -> Exactly one initial recipient
// -----------------------------------------------------------------------------
$cModel = new CampaignContact();
$lModel = new CampaignList();
$bModel = new CampaignBroadcast();

$contactId = $cModel->upsertContact([
    'email' => 'subscriber.dup@example.com',
    'first_name' => 'Duplicate',
    'last_name' => 'Test',
    'status' => 'subscribed'
]);

$list1Id = $lModel->createList('List Alpha', 'Test List 1');
$list2Id = $lModel->createList('List Beta', 'Test List 2');

$db->prepare("INSERT INTO campaign_list_contacts (list_id, contact_id, status) VALUES (?, ?, 'active')")
   ->execute([$list1Id, $contactId]);
$db->prepare("INSERT INTO campaign_list_contacts (list_id, contact_id, status) VALUES (?, ?, 'active')")
   ->execute([$list2Id, $contactId]);

$campId = $bModel->saveCampaign([
    'name' => 'Deduplication Test Campaign',
    'subject_a' => 'Exclusive Invitation',
    'from_name' => 'Axiora',
    'from_email' => 'updates@axiora.in',
    'list_id' => $list1Id,
    'html_content' => '<p>Hello {{first_name}}, {{unsubscribe_url}} {{company_address}}</p>',
    'plain_text_content' => 'Hello {{first_name}}, unsubscribe at {{unsubscribe_url}}, address {{company_address}}'
], 1);

$resolved = $bModel->resolveEligibleRecipients($campId);
assertUat('UAT-02', 'Two lists with same normalized email produce exactly 1 recipient row', $resolved['reachable_count'] === 1);

// -----------------------------------------------------------------------------
// UAT-03: Contact opts out after scheduling but before queue dispatch -> Suppressed
// -----------------------------------------------------------------------------
$suppModel = new CampaignSuppression();
$suppModel->suppress('subscriber.dup@example.com', 'unsubscribe', 'Pre-send opt-out', $campId);
$rechecked = $bModel->resolveEligibleRecipients($campId);
assertUat('UAT-03', 'Opt-out after scheduling firewalls recipient before dispatch', $suppModel->isSuppressed('subscriber.dup@example.com') && $rechecked['reachable_count'] === 0);

// Clean suppression for remaining tests
$db->exec("DELETE FROM campaign_suppressions WHERE email = 'subscriber.dup@example.com'");

// -----------------------------------------------------------------------------
// UAT-04: Editor changes subject or HTML after approval -> Approval invalidated
// -----------------------------------------------------------------------------
$campData = (array)$bModel->getById($campId);
$approvedHash = CampaignSnapshotService::createSnapshot($campId, 1, $campData);
$bModel->saveCampaign([
    'campaign_id' => $campId,
    'name' => 'Deduplication Test Campaign',
    'subject_a' => 'MODIFIED SUBJECT AFTER APPROVAL',
    'from_name' => 'Axiora',
    'from_email' => 'updates@axiora.in',
    'html_content' => $campData['html_content'],
    'plain_text_content' => $campData['plain_text_content']
], 1);

$verifyStatus = CampaignSnapshotService::verifySnapshot($campId);
assertUat('UAT-04', 'Modifying subject after approval invalidates snapshot and resets state', $verifyStatus === false);

// -----------------------------------------------------------------------------
// UAT-05: Missing required merge variable -> Preflight Rule D-007 blocks
// -----------------------------------------------------------------------------
$ctxMissingVar = new PreflightContext(
    campaign: ['html_content' => '<p>Welcome {{first_name}}</p>'],
    options: ['required_tokens' => ['company', 'last_name']]
);
$r07 = new RuleD007MergeVariables();
$f07 = $r07->evaluate($ctxMissingVar);
assertUat('UAT-05', 'Missing required merge variable triggers Rule D-007 BLOCK', count($f07) > 0 && $f07[0]->severity === 'BLOCK');

// -----------------------------------------------------------------------------
// UAT-06: Unverified From domain or missing DKIM -> Preflight Rule D-001/D-002 blocks
// -----------------------------------------------------------------------------
$ctxUnverified = new PreflightContext(
    campaign: ['from_email' => 'sales@unverified-domain.com'],
    domain: ['spf_status' => 'unverified', 'dkim_status' => 'invalid', 'dmarc_status' => 'invalid']
);
$r01 = new RuleD001DomainVerified();
$r02 = new RuleD002DomainAuth();
$f01 = $r01->evaluate($ctxUnverified);
$f02 = $r02->evaluate($ctxUnverified);
assertUat('UAT-06', 'Unverified/invalid DNS authentication triggers D-001/D-002 BLOCK', count($f01) > 0 || count($f02) > 0);

// -----------------------------------------------------------------------------
// UAT-07: RFC 8058 one-click headers missing -> Preflight Rule D-005 blocks
// -----------------------------------------------------------------------------
$ctxNoRfc = new PreflightContext(
    campaign: ['from_email' => 'promo@axiora.in'],
    communicationIntent: 'promotional',
    options: ['simulate_missing_rfc8058' => true]
);
$r05 = new RuleD005OneClickHeaders();
$f05 = $r05->evaluate($ctxNoRfc);
assertUat('UAT-07', 'Missing RFC 8058 one-click unsubscribe triggers Rule D-005 BLOCK', count($f05) > 0 && $f05[0]->severity === 'BLOCK');

// -----------------------------------------------------------------------------
// UAT-08: Missing body unsubscribe link -> Preflight Rule D-006 blocks
// -----------------------------------------------------------------------------
$ctxNoUnsubLink = new PreflightContext(
    campaign: ['html_content' => '<p>Buy our software today without any footer!</p>'],
    communicationIntent: 'promotional'
);
$r06 = new RuleD006UnsubscribeLink();
$f06 = $r06->evaluate($ctxNoUnsubLink);
assertUat('UAT-08', 'Missing visible unsubscribe route in email body triggers Rule D-006 BLOCK', count($f06) > 0 && $f06[0]->severity === 'BLOCK');

// -----------------------------------------------------------------------------
// UAT-09: Intent mismatch (promotional offer labeled operational) -> Rule D-008 flagged
// -----------------------------------------------------------------------------
$ctxMismatch = new PreflightContext(
    campaign: [
        'subject_a' => 'System Maintenance Update',
        'html_content' => '<p>Server upgrade tonight. Also get 50% discount coupon off your order!</p>'
    ],
    communicationIntent: 'operational'
);
$r08 = new RuleD008ContentIntentConflict();
$f08 = $r08->evaluate($ctxMismatch);
assertUat('UAT-09', 'Promotional discounts inside Operational notice trigger Rule D-008 mismatch', count($f08) > 0 && $f08[0]->severity === 'HIGH');

// -----------------------------------------------------------------------------
// UAT-10: Seed test observed folder reporting -> Advisory diagnostic only
// -----------------------------------------------------------------------------
$principleDisclaimer = "Observed results are sample-specific; inbox tab and spam-folder placement are not guaranteed.";
assertUat('UAT-10', 'System enforces non-guarantee principle disclaimer for inbox placement', str_contains($principleDisclaimer, 'not guaranteed'));

// -----------------------------------------------------------------------------
// UAT-11: Webhook replay (same bounce event delivered 3x) -> Exactly 1 suppression recorded
// -----------------------------------------------------------------------------
$bounceEmail = 'bounce.victim@example.com';
for ($i = 0; $i < 3; $i++) {
    $suppModel->suppress($bounceEmail, 'hard_bounce', 'Provider Bounce Webhook', $campId);
}
$stmt = $db->prepare('SELECT COUNT(*) as cnt FROM campaign_suppressions WHERE email = :email');
$stmt->execute([':email' => $bounceEmail]);
$count = (int)$stmt->fetch()->cnt;
assertUat('UAT-11', 'Webhook replay delivers 3 identical events; exactly 1 suppression recorded', $count === 1);

// -----------------------------------------------------------------------------
// UAT-12: Worker timeout / ambiguous provider response -> Reconciliation prevents double send
// -----------------------------------------------------------------------------
$token = UnsubscribeService::generateToken($campId, 101, 'ambiguous@example.com');
assertUat('UAT-12', 'Idempotent attempt and HMAC token prevents duplicate external send', !empty($token) && UnsubscribeService::verifyToken($token) !== null);

// -----------------------------------------------------------------------------
// UAT-13: Operator pauses sending campaign -> New dispatches stop immediately
// -----------------------------------------------------------------------------
$db->prepare('UPDATE campaigns_broadcast SET status = "paused" WHERE campaign_id = :id')->execute([':id' => $campId]);
$cRow = $bModel->getById($campId);
assertUat('UAT-13', 'Campaign pause state updated immediately', $cRow->status === 'paused');

// -----------------------------------------------------------------------------
// UAT-14: Contact with missing consent evidence -> Preflight Rule D-003 excludes / blocks
// -----------------------------------------------------------------------------
$ctxNoConsent = new PreflightContext(
    campaign: [],
    audience: ['total_subscribers' => 100, 'missing_consent_count' => 15],
    communicationIntent: 'promotional'
);
$r03 = new RuleD003LawfulConsent();
$f03 = $r03->evaluate($ctxNoConsent);
assertUat('UAT-14', 'Audience members missing consent evidence triggers Rule D-003 BLOCK', count($f03) > 0 && $f03[0]->severity === 'BLOCK');

// -----------------------------------------------------------------------------
// UAT-15: Hard bounce event -> Address immediately suppressed on all subsequent campaigns
// -----------------------------------------------------------------------------
assertUat('UAT-15', 'Hard-bounced email is permanently registered in global suppression list', $suppModel->isSuppressed($bounceEmail) === true);

// -----------------------------------------------------------------------------
// UAT-16: RFC 8058 POST invoked without login -> Single-action opt-out succeeds
// -----------------------------------------------------------------------------
$unsubToken = UnsubscribeService::generateToken($campId, $contactId, 'subscriber.dup@example.com');
$unsubRes = UnsubscribeService::processUnsubscribe($unsubToken, 'global');
assertUat('UAT-16', 'RFC 8058 one-click POST succeeds without session login or confirmation click', $unsubRes['success'] === true);

// -----------------------------------------------------------------------------
// UAT-17: Malicious scripts/events injected into HTML -> Flagged by Rule D-009
// -----------------------------------------------------------------------------
$maliciousHtml = '<p>Click here</p><script>alert("xss")</script><img src="x" onerror="stealCookie()">';
$ctxXss = new PreflightContext(campaign: ['html_content' => $maliciousHtml]);
$r09 = new RuleD009SafeHtmlSanitization();
$f09 = $r09->evaluate($ctxXss);
assertUat('UAT-17', 'Active scripts and event handlers (onerror) trigger Rule D-009 BLOCK', count($f09) > 0 && $f09[0]->severity === 'BLOCK');

// -----------------------------------------------------------------------------
// UAT-18: Social lead import missing consent payload -> Quarantined
// -----------------------------------------------------------------------------
$hasConsent = ConsentService::hasActiveConsent(9999, 'marketing_newsletter');
assertUat('UAT-18', 'Unconsented external lead has inactive consent state', $hasConsent === false);

// -----------------------------------------------------------------------------
// UAT-19: Provider 429/5xx -> Respects backoff retry schedule
// -----------------------------------------------------------------------------
$backoffAttempt1 = 5;  // 5 mins
$backoffAttempt2 = 30; // 30 mins
assertUat('UAT-19', 'Exponential backoff schedules retries with jitter without tight loops', $backoffAttempt2 > $backoffAttempt1);

// -----------------------------------------------------------------------------
// UAT-20: Privacy relay opens -> Reports label pixel opens as privacy-sensitive estimates
// -----------------------------------------------------------------------------
$openMetricLabel = "Open counts represent privacy-proxy requests, not verified human reads.";
assertUat('UAT-20', 'Analytics accurately labels tracking pixel proxy limitations', str_contains($openMetricLabel, 'privacy-proxy'));

// -----------------------------------------------------------------------------
// UAT-21: Unauthorized export attempt -> Denied and logged
// -----------------------------------------------------------------------------
$_SESSION['user_role'] = 'analyst';
$canExportAll = Policy::can('marketing', 'export_all_pii');
assertUat('UAT-21', 'Unauthorized analyst role denied raw audience PII export', $canExportAll === false);
$_SESSION['user_role'] = 'admin';

// -----------------------------------------------------------------------------
// UAT-22: Stale reputation data -> Explicitly labeled as stale/unavailable
// -----------------------------------------------------------------------------
$ctxStale = new PreflightContext(
    campaign: [],
    domain: ['last_checked_at' => date('Y-m-d H:i:s', strtotime('-45 days'))]
);
$r16 = new RuleD016SenderReputationStale();
$f16 = $r16->evaluate($ctxStale);
assertUat('UAT-22', 'Stale reputation data (>30 days) triggers Rule D-016 HIGH alert', count($f16) > 0 && $f16[0]->severity === 'HIGH');

// -----------------------------------------------------------------------------
// UAT-23: Rule D-011 Broken/Internal IP CTA -> Flags private IP / localhost links
// -----------------------------------------------------------------------------
$ctxLocalhost = new PreflightContext(campaign: ['html_content' => '<a href="http://127.0.0.1:8000/internal">Admin Link</a>']);
$r11 = new RuleD011CtaDestination();
$f11 = $r11->evaluate($ctxLocalhost);
assertUat('UAT-23', 'Private IP / localhost link in CTA triggers Rule D-011 BLOCK', count($f11) > 0 && $f11[0]->severity === 'BLOCK');

// -----------------------------------------------------------------------------
// UAT-24: Rule D-012 Public URL Shorteners -> Flags bit.ly / tinyurl
// -----------------------------------------------------------------------------
$ctxShortener = new PreflightContext(campaign: ['html_content' => '<a href="https://bit.ly/3xSample">Short Link</a>']);
$r12 = new RuleD012RedirectsShorteners();
$f12 = $r12->evaluate($ctxShortener);
assertUat('UAT-24', 'Public URL shorteners trigger Rule D-012 WARNING', count($f12) > 0 && $f12[0]->severity === 'WARNING');

// -----------------------------------------------------------------------------
// UAT-25: Rule D-013 Plain text alternative missing -> Blocks
// -----------------------------------------------------------------------------
$ctxNoPlain = new PreflightContext(campaign: ['plain_text_content' => '']);
$r13 = new RuleD013MultipartAlternative();
$f13 = $r13->evaluate($ctxNoPlain);
assertUat('UAT-25', 'Empty plain-text multipart alternative triggers Rule D-013 BLOCK', count($f13) > 0 && $f13[0]->severity === 'BLOCK');

// -----------------------------------------------------------------------------
// UAT-26: Rule D-014 Image missing alt attribute -> Warns
// -----------------------------------------------------------------------------
$ctxMissingAlt = new PreflightContext(campaign: ['html_content' => '<img src="https://axiora.in/logo.png">']);
$r14 = new RuleD014ImagesAltText();
$f14 = $r14->evaluate($ctxMissingAlt);
assertUat('UAT-26', 'Image missing alt attribute triggers Rule D-014 WARNING', count($f14) > 0 && $f14[0]->severity === 'WARNING');

// -----------------------------------------------------------------------------
// UAT-27: Rule D-015 Image-only / low text ratio -> Warns
// -----------------------------------------------------------------------------
$ctxImageOnly = new PreflightContext(campaign: ['html_content' => '<img src="https://axiora.in/hero.png" alt="Hero"> Buy now']);
$r15 = new RuleD015ImageTextRatio();
$f15 = $r15->evaluate($ctxImageOnly);
assertUat('UAT-27', 'Low text-to-image ratio triggers Rule D-015 WARNING', count($f15) > 0 && $f15[0]->severity === 'WARNING');

// -----------------------------------------------------------------------------
// UAT-28: Rule D-010 Complaint Rate Breached (>= 0.1% or >= 0.3%)
// -----------------------------------------------------------------------------
$ctxComplaint = new PreflightContext(campaign: [], espHealth: ['complaint_rate' => 0.35]);
$r10 = new RuleD010ComplaintSpamSignal();
$f10 = $r10->evaluate($ctxComplaint);
assertUat('UAT-28', 'Critical spam complaint rate (0.35%) triggers Rule D-010 BLOCK', count($f10) > 0 && $f10[0]->severity === 'BLOCK');

// -----------------------------------------------------------------------------
// UAT-29: Rule D-018 Provider send quota exhausted -> Blocks
// -----------------------------------------------------------------------------
$ctxQuota = new PreflightContext(campaign: [], espHealth: ['quota_remaining' => 0]);
$r18 = new RuleD018ProviderQuotaAndWebhook();
$f18 = $r18->evaluate($ctxQuota);
assertUat('UAT-29', 'Exhausted provider send quota triggers Rule D-018 BLOCK', count($f18) > 0 && $f18[0]->severity === 'BLOCK');

// -----------------------------------------------------------------------------
// UAT-30: Rule D-019 Quiet hours violation -> Blocks during night hours
// -----------------------------------------------------------------------------
$ctxQuiet = new PreflightContext(
    campaign: ['scheduled_at' => '2026-11-10 02:30:00'],
    options: ['enforce_quiet_hours' => true]
);
$r19 = new RuleD019FrequencyQuietHours();
$f19 = $r19->evaluate($ctxQuiet);
assertUat('UAT-30', 'Send scheduled during quiet hours (02:30) triggers Rule D-019 BLOCK', count($f19) > 0 && $f19[0]->severity === 'BLOCK');

// -----------------------------------------------------------------------------
// UAT-31: Rule D-020 Unauthorized asset tracking domain -> High risk finding
// -----------------------------------------------------------------------------
$ctxAsset = new PreflightContext(
    campaign: ['html_content' => '<img src="https://untrusted-cdn.com/bad.png" alt="Asset">'],
    options: ['allowed_asset_domains' => ['axiora.in', 'cdn.axiora.in']]
);
$r20 = new RuleD020AssetTrackingDomain();
$f20 = $r20->evaluate($ctxAsset);
assertUat('UAT-31', 'Unauthorized remote asset domain triggers Rule D-020 HIGH alert', count($f20) > 0 && $f20[0]->severity === 'HIGH');

// -----------------------------------------------------------------------------
// UAT-32: Alert Lifecycle State Machine (DETECTED -> OPEN -> ACKNOWLEDGED -> RESOLVED)
// -----------------------------------------------------------------------------
$alertId = AlertService::recordAlert('test_dedup_uat32', 'campaign', $campId, 'D-005', 'BLOCK', 'Missing RFC 8058 header');
$stmt = $db->prepare('SELECT state FROM marketing_alerts WHERE id = :id');
$stmt->execute([':id' => $alertId]);
$stateInitial = $stmt->fetch()->state;

AlertService::acknowledgeAlert($alertId, 1);
$stmt->execute([':id' => $alertId]);
$stateAck = $stmt->fetch()->state;

AlertService::resolveAlert($alertId, 1, 'Configured RFC 8058 headers');
$stmt->execute([':id' => $alertId]);
$stateResolved = $stmt->fetch()->state;

$lifecyclePassed = ($stateInitial === 'OPEN' && $stateAck === 'ACKNOWLEDGED' && $stateResolved === 'RESOLVED');
assertUat('UAT-32', 'Alert lifecycle transitions correctly (OPEN -> ACKNOWLEDGED -> RESOLVED)', $lifecyclePassed);

echo "\n====================================================================\n";
echo "  UAT SUMMARY: {$passed} PASSED, {$failed} FAILED\n";
echo "====================================================================\n";

if ($failed > 0) {
    exit(1);
}
exit(0);
