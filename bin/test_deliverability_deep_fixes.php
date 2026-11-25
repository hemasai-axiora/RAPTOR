<?php
/**
 * Raptor CRM: Targeted Deliverability Deep Fixes Verification Suite
 */

require_once dirname(__DIR__) . '/app/config/config.php';
require_once dirname(__DIR__) . '/app/core/Database.php';
require_once dirname(__DIR__) . '/app/core/Model.php';
require_once dirname(__DIR__) . '/app/services/TrackingService.php';
require_once dirname(__DIR__) . '/app/services/UnsubscribeService.php';
require_once dirname(__DIR__) . '/app/models/CampaignTemplate.php';

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

echo "====================================================================\n";
echo "  RAPTOR CRM: DELIVERABILITY DEEP FIXES & ANTI-PHISHING VERIFICATION\n";
echo "====================================================================\n\n";

// -------------------------------------------------------------------------
// 1. URL Display Mismatch & Anti-Phishing Link Rewriting
// -------------------------------------------------------------------------
echo "--- 1. Anti-Phishing: URL Display Mismatch Prevention ---\n";
$token = 'test_token_12345';

// Case A: Raw URL in anchor text (e.g. Microsoft Form link)
$rawUrlHtml = '<p>Register here: <a href="https://forms.office.com/r/zGPmq1YAaJ">https://forms.office.com/r/zGPmq1YAaJ</a></p>';
$trackedRaw = TrackingService::injectTracking($rawUrlHtml, $token, true, true);
$assertCondition(
    strpos($trackedRaw, 'href="https://forms.office.com/r/zGPmq1YAaJ"') !== false,
    'Raw URL in anchor text is preserved without redirect rewriting (prevents phishing trigger)'
);
$assertCondition(
    strpos($trackedRaw, 'route=campaign_tracking/click') === false,
    'Tracking redirect was NOT injected into raw URL anchor'
);

// Case B: Natural anchor text (e.g. "Register for Workshop")
$naturalTextHtml = '<p><a href="https://forms.office.com/r/zGPmq1YAaJ">Register for the Workshop Session</a></p>';
$trackedNatural = TrackingService::injectTracking($naturalTextHtml, $token, false, true);
$assertCondition(
    strpos($trackedNatural, 'route=campaign_tracking/click') !== false,
    'Natural anchor text link IS wrapped with signed click tracking'
);
$assertCondition(
    strpos($trackedNatural, 'Register for the Workshop Session') !== false,
    'Natural anchor text content preserved inside tracked link'
);

// Case C: Unsubscribe link must NEVER be rewritten
$unsubHtml = '<p><a href="https://www.agsraptor.com/public/index.php?route=unsubscribe/view&token=abc">Unsubscribe here</a></p>';
$trackedUnsub = TrackingService::injectTracking($unsubHtml, $token, false, true);
$assertCondition(
    strpos($trackedUnsub, 'route=campaign_tracking/click') === false,
    'Unsubscribe URL is strictly excluded from click tracking rewrite'
);

// Case D: Open tracking pixel injection
$pixelHtml = TrackingService::injectTracking('<body><p>Hello World</p></body>', $token, true, false);
$assertCondition(
    strpos($pixelHtml, 'route=campaign_tracking/open') !== false,
    'Open tracking pixel correctly injected'
);

// -------------------------------------------------------------------------
// 2. EmailQueueService Query Verification (c.track_opens, c.track_clicks)
// -------------------------------------------------------------------------
echo "\n--- 2. EmailQueueService: Campaign-Level Tracking Control ---\n";
$eqsContent = file_get_contents(dirname(__DIR__) . '/app/services/EmailQueueService.php');
$assertCondition(
    strpos($eqsContent, 'c.track_opens') !== false && strpos($eqsContent, 'c.track_clicks') !== false,
    'EmailQueueService respects c.track_opens and c.track_clicks from campaigns_broadcast'
);
$assertCondition(
    strpos($eqsContent, '1 as track_clicks') === false,
    'Hardcoded "1 as track_clicks" successfully eliminated'
);

// -------------------------------------------------------------------------
// 3. Transactional Routing Verification (Invoices, Meetings, Auth)
// -------------------------------------------------------------------------
echo "\n--- 3. Transactional Routing via EmailService ---\n";
$invContent = file_get_contents(dirname(__DIR__) . '/app/controllers/InvoicesController.php');
$assertCondition(
    strpos($invContent, 'EmailService::send') !== false,
    'InvoicesController routes invoice dispatches via authenticated EmailService'
);
$assertCondition(
    substr_count($invContent, '@mail(') === 0,
    'Unauthenticated @mail() completely eliminated from InvoicesController'
);

$meetContent = file_get_contents(dirname(__DIR__) . '/app/controllers/MeetingsController.php');
$assertCondition(
    strpos($meetContent, 'EmailService::send') !== false,
    'MeetingsController routes meeting alerts via authenticated EmailService'
);
$assertCondition(
    substr_count($meetContent, '@mail(') === 0,
    'Unauthenticated @mail() completely eliminated from MeetingsController'
);

$authContent = file_get_contents(dirname(__DIR__) . '/app/controllers/AuthController.php');
$assertCondition(
    strpos($authContent, 'EmailService::send') !== false,
    'AuthController dispatches password reset OTP via EmailService'
);

// -------------------------------------------------------------------------
// 4. Dedicated Transactional Templates Verification
// -------------------------------------------------------------------------
echo "\n--- 4. Dedicated Transactional Email Templates ---\n";
$welcomeHtml = CampaignTemplate::getPrebuiltTemplateWelcomeRegistration();
$assertCondition(
    strpos($welcomeHtml, 'Welcome to Raptor CRM') !== false,
    'Welcome template contains account onboarding content'
);
$assertCondition(
    stripos($welcomeHtml, 'Workshop') === false,
    'Welcome template contains ZERO promotional workshop content'
);
$assertCondition(
    strpos($welcomeHtml, 'Sign in to Raptor Portal') !== false,
    'Welcome template provides direct portal sign-in link'
);

$otpHtml = CampaignTemplate::getPrebuiltTemplatePasswordResetOtp();
$assertCondition(
    strpos($otpHtml, 'Password Reset Request') !== false && strpos($otpHtml, '10 minutes') !== false,
    'Password Reset template provides verification code box with 10-minute expiry'
);

$cleanWorkshopHtml = CampaignTemplate::getPrebuiltTemplateCleanWorkshop();
$assertCondition(
    stripos($cleanWorkshopHtml, 'FREE') === false,
    'Clean Workshop template eliminated spam trigger word "FREE"'
);
$assertCondition(
    strpos($cleanWorkshopHtml, '>Register for the Workshop Session<') !== false,
    'Clean Workshop template uses natural anchor text (avoids URL display mismatch)'
);

// -------------------------------------------------------------------------
// 5. Unsubscribe & Suppression Safety
// -------------------------------------------------------------------------
echo "\n--- 5. Unsubscribe & Global Suppression Invariance ---\n";
$tokenData = UnsubscribeService::verifyToken('invalid.token.123');
$assertCondition(
    $tokenData === null,
    'Invalid unsubscribe tokens remain safely rejected'
);

$testToken = UnsubscribeService::generateToken(0, 0, 'test-preview@raptor.internal');
$simData = UnsubscribeService::verifyToken($testToken);
$assertCondition(
    $simData !== null && $simData['email'] === 'test-preview@raptor.internal',
    'Test preview simulation tokens remain valid for safe deliverability testing'
);

echo "\n====================================================================\n";
echo "  SUMMARY: $passed PASSED, $failed FAILED\n";
echo "====================================================================\n";

exit($failed > 0 ? 1 : 0);
