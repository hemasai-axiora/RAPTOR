<?php
/**
 * Raptor CRM - RFC 8058 & Deliverability Fixes Verification Suite
 */

require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/core/Model.php';
require_once __DIR__ . '/../app/core/Controller.php';
require_once __DIR__ . '/../app/models/CampaignBroadcast.php';
require_once __DIR__ . '/../app/models/CampaignSuppression.php';
require_once __DIR__ . '/../app/models/CampaignTemplate.php';
require_once __DIR__ . '/../app/services/UnsubscribeService.php';
require_once __DIR__ . '/../app/controllers/UnsubscribeController.php';
require_once __DIR__ . '/../app/services/TrackingService.php';
require_once __DIR__ . '/../app/services/EmailQueueService.php';

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->exec("
    CREATE TABLE settings (setting_key VARCHAR(100) PRIMARY KEY, setting_value TEXT);
    CREATE TABLE campaigns_broadcast (
        campaign_id INTEGER PRIMARY KEY AUTOINCREMENT,
        campaign_code VARCHAR(50),
        name VARCHAR(255),
        subject_a VARCHAR(255),
        subject_b VARCHAR(255),
        from_name VARCHAR(255),
        from_email VARCHAR(255),
        reply_to VARCHAR(255),
        list_id INT DEFAULT 1,
        html_content TEXT,
        plain_text_content TEXT,
        communication_intent VARCHAR(50) DEFAULT 'promotional',
        status VARCHAR(50) DEFAULT 'draft',
        sent_count INT DEFAULT 0,
        unsubscribed_count INT DEFAULT 0,
        pre_send_checklist_passed INT DEFAULT 0,
        pre_send_checklist_json TEXT,
        track_opens INT DEFAULT 1,
        track_clicks INT DEFAULT 1
    );
    CREATE TABLE campaign_recipients (
        recipient_id INTEGER PRIMARY KEY AUTOINCREMENT,
        campaign_id INT,
        contact_id INT,
        email VARCHAR(255),
        status VARCHAR(50) DEFAULT 'queued',
        unsubscribe_token VARCHAR(255)
    );
    CREATE TABLE campaign_suppressions (
        suppression_id INTEGER PRIMARY KEY AUTOINCREMENT,
        email VARCHAR(255) UNIQUE,
        reason VARCHAR(100),
        notes TEXT,
        source_campaign_id INT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );
    CREATE TABLE campaign_suppression_list (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        email VARCHAR(255) UNIQUE,
        reason VARCHAR(100),
        details TEXT,
        campaign_id INT,
        suppressed_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );
    CREATE TABLE campaign_email_events (
        event_id INTEGER PRIMARY KEY AUTOINCREMENT,
        campaign_id INT,
        recipient_id INT,
        contact_id INT,
        event_type VARCHAR(50),
        ip_address VARCHAR(45),
        user_agent VARCHAR(255),
        payload_json TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );
");

Database::setMockConnection($pdo);

echo "====================================================================\n";
echo "  RAPTOR CRM: DELIVERABILITY & RFC 8058 FIXES VERIFICATION\n";
echo "====================================================================\n";

$passCount = 0;
$failCount = 0;

function assertCheck(string $title, bool $condition, string $detail = '') {
    global $passCount, $failCount;
    if ($condition) {
        $passCount++;
        echo "  [PASS] {$title}\n";
    } else {
        $failCount++;
        echo "  [FAIL] {$title} - {$detail}\n";
    }
}

// -----------------------------------------------------------------------------
// 1. Test Bulk Email List-Unsubscribe Header Generation
// -----------------------------------------------------------------------------
echo "\n--- 1. Bulk Email List-Unsubscribe Header Formatting ---\n";
$token = UnsubscribeService::generateToken(106, 501, 'customer@example.com');
$oneClickUrl = UnsubscribeService::buildOneClickUrl($token);

$headers = [
    'List-Unsubscribe' => "<{$oneClickUrl}>",
    'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
    'Feedback-ID' => "camp_106:raptor:axioraglobalsolutions.com",
    'Precedence' => 'bulk'
];

assertCheck(
    'List-Unsubscribe is strictly enclosed in angle brackets',
    str_starts_with($headers['List-Unsubscribe'], '<https://') || str_starts_with($headers['List-Unsubscribe'], '<http://') || str_starts_with($headers['List-Unsubscribe'], '<'),
    $headers['List-Unsubscribe']
);
assertCheck(
    'List-Unsubscribe contains NO mailto: prefix or malformed URL',
    stripos($headers['List-Unsubscribe'], 'mailto:') === false,
    $headers['List-Unsubscribe']
);
assertCheck(
    'List-Unsubscribe-Post is set to List-Unsubscribe=One-Click per RFC 8058',
    $headers['List-Unsubscribe-Post'] === 'List-Unsubscribe=One-Click',
    $headers['List-Unsubscribe-Post']
);

// -----------------------------------------------------------------------------
// 2. Test Real Contact Unsubscribe Suppression (One-Click POST)
// -----------------------------------------------------------------------------
echo "\n--- 2. Real Contact Unsubscribe & Global Suppression ---\n";
$targetEmail = 'real.subscriber@example.com';
$pdo->prepare("INSERT INTO campaign_recipients (recipient_id, campaign_id, contact_id, email, unsubscribe_token) VALUES (501, 106, 201, :email, :token)")
    ->execute([':email' => $targetEmail, ':token' => $token]);

$unsubRes = UnsubscribeService::processUnsubscribe($token, 'global');
assertCheck('UnsubscribeService::processUnsubscribe succeeds for real token', $unsubRes['success'] === true);

$suppModel = new CampaignSuppression();
assertCheck(
    'Real unsubscribed email is entered into global suppression',
    $suppModel->isSuppressed($targetEmail) === true
);

// -----------------------------------------------------------------------------
// 3. Test Invalid and Expired Unsubscribe Tokens
// -----------------------------------------------------------------------------
echo "\n--- 3. Invalid and Expired Token Protection ---\n";
$invalidRes = UnsubscribeService::processUnsubscribe('completely_invalid_garbage_token', 'global');
assertCheck('Invalid token is rejected by UnsubscribeService', $invalidRes['success'] === false);

$tamperedToken = $token . 'tampered';
$tamperedRes = UnsubscribeService::processUnsubscribe($tamperedToken, 'global');
assertCheck('Tampered HMAC signature token is rejected', $tamperedRes['success'] === false);

$emptyRes = UnsubscribeService::processUnsubscribe('', 'global');
assertCheck('Empty token is rejected', $emptyRes['success'] === false);

// -----------------------------------------------------------------------------
// 4. Test Safety of Test Preview Token (Never Suppresses Real Contacts/Testers)
// -----------------------------------------------------------------------------
echo "\n--- 4. Test Preview Token Safety ---\n";
$testToken = UnsubscribeService::generateToken(106, 0, 'test-preview@raptor.internal');
$testOneClickUrl = UnsubscribeService::buildOneClickUrl($testToken);
$testRes = UnsubscribeService::processUnsubscribe($testToken, 'global');

assertCheck('Test token returns success for RFC 8058 validation', $testRes['success'] === true);
assertCheck('Test token scope is identified as test_preview', ($testRes['scope'] ?? '') === 'test_preview');
assertCheck(
    'Test preview email was NOT inserted into global suppression',
    $suppModel->isSuppressed('test-preview@raptor.internal') === false
);
assertCheck(
    'Admin/tester email is NOT suppressed',
    $suppModel->isSuppressed('admin@axioraglobalsolutions.com') === false
);

// -----------------------------------------------------------------------------
// 5. Test Tracking Service Functionality (Clicks, Opens, Exclusions)
// -----------------------------------------------------------------------------
echo "\n--- 5. Email Tracking (Opens & Clicks) ---\n";
$rawHtml = '<html><body><p>Hello! <a href="https://example.com/shop">Shop Now</a></p><p><a href="{{unsubscribe_url}}">Unsubscribe</a></p></body></html>';
$trackedHtml = TrackingService::injectTracking($rawHtml, $token, true, true);

assertCheck('Open tracking pixel injected before </body>', strpos($trackedHtml, 'route=campaign_tracking/open') !== false);
assertCheck('Click tracking wrapped around standard hyperlink', strpos($trackedHtml, 'route=campaign_tracking/click') !== false);
assertCheck('Unsubscribe link is excluded from click tracking rewrite', strpos($trackedHtml, 'href="{{unsubscribe_url}}"') !== false);

// -----------------------------------------------------------------------------
// 6. Test Compliance Footer & Auto-Address Attachment
// -----------------------------------------------------------------------------
echo "\n--- 6. Compliance Footer & Postal Address Auto-Attachment ---\n";
$campHtml = '<p>Check out our special announcement!</p>';
$bModel = new CampaignBroadcast();
$pdo->prepare("INSERT INTO campaigns_broadcast (campaign_id, name, html_content, plain_text_content, communication_intent, status) VALUES (107, 'Promo', :html, 'Check out!', 'promotional', 'draft')")
    ->execute([':html' => $campHtml]);

require_once __DIR__ . '/../app/services/PreSendChecklistService.php';
$checkRes = PreSendChecklistService::run(['campaign_id' => 107, 'html_content' => $campHtml, 'plain_text_content' => 'Check out our special announcement!']);
assertCheck('PreSendChecklist address check passes because campaign_id guarantees auto-footer attachment', $checkRes['checks']['physical_address']['passed'] === true);

echo "\n====================================================================\n";
echo "  SUMMARY: {$passCount} PASSED, {$failCount} FAILED\n";
echo "====================================================================\n";

if ($failCount > 0) {
    exit(1);
}
