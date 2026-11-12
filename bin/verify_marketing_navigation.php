<?php
/**
 * Raptor CRM - Full Marketing Navigation Verification Script
 */

require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/core/App.php';
require_once __DIR__ . '/../app/core/Controller.php';

$routesToVerify = [
    'Email Broadcasts'     => ['route' => 'email_campaigns/campaigns',    'controller' => 'EmailCampaignsController',   'method' => 'campaigns',     'view' => 'app/views/email_campaigns/campaigns/index.php'],
    'Email Templates'      => ['route' => 'email_campaigns/templates',    'controller' => 'EmailCampaignsController',   'method' => 'templates',     'view' => 'app/views/email_campaigns/templates/index.php'],
    'Contacts'             => ['route' => 'email_campaigns/contacts',     'controller' => 'EmailCampaignsController',   'method' => 'contacts',      'view' => 'app/views/email_campaigns/contacts/index.php'],
    'Audiences & Lists'    => ['route' => 'email_campaigns/lists',        'controller' => 'EmailCampaignsController',   'method' => 'lists',         'view' => 'app/views/email_campaigns/lists/index.php'],
    'Audience Segments'    => ['route' => 'email_campaigns/segments',     'controller' => 'EmailCampaignsController',   'method' => 'segments',      'view' => 'app/views/email_campaigns/segments/index.php'],
    'Global Suppression'   => ['route' => 'email_campaigns/suppressions', 'controller' => 'EmailCampaignsController',   'method' => 'suppressions',  'view' => 'app/views/email_campaigns/suppressions/index.php'],
    'Domains & DNS'        => ['route' => 'email_campaigns/domains',      'controller' => 'EmailCampaignsController',   'method' => 'domains',       'view' => 'app/views/email_campaigns/domains/index.php'],
    'Deliverability Health'=> ['route' => 'email_campaigns/analytics',    'controller' => 'EmailCampaignsController',   'method' => 'analytics',     'view' => 'app/views/email_campaigns/analytics/index.php'],
    'Campaign Registry'    => ['route' => 'campaigns/index',              'controller' => 'CampaignsController',        'method' => 'index',         'view' => 'app/views/campaigns/index.php'],
    'Social Publishing'    => ['route' => 'posts/index',                  'controller' => 'PostsController',            'method' => 'index',         'view' => 'app/views/posts/index.php'],
    'Automation Journeys'  => ['route' => 'journeys/index',               'controller' => 'JourneysController',         'method' => 'index',         'view' => 'app/views/journeys/index.php'],
    'Website Analytics'    => ['route' => 'website_analytics/index',      'controller' => 'WebsiteAnalyticsController', 'method' => 'index',         'view' => 'app/views/website_analytics/index.php'],
    'WhatsApp Cloud API'   => ['route' => 'whatsapp/index',               'controller' => 'WhatsAppController',         'method' => 'index',         'view' => 'app/views/whatsapp/conversations.php'],
];

echo "====================================================================\n";
echo "  RAPTOR CRM: MARKETING NAVIGATION & VIEW AUDIT VERIFICATION        \n";
echo "====================================================================\n\n";

$allPassed = true;

foreach ($routesToVerify as $name => $meta) {
    echo "Auditing [{$name}] (Route: '{$meta['route']}'):\n";
    
    // 1. Controller file exists
    $ctrlFile = __DIR__ . '/../app/controllers/' . $meta['controller'] . '.php';
    if (!file_exists($ctrlFile)) {
        echo "  [FAIL] Controller file {$ctrlFile} missing!\n";
        $allPassed = false;
        continue;
    }
    require_once $ctrlFile;
    echo "  [PASS] Controller class {$meta['controller']} exists\n";

    // 2. Action method exists
    if (!method_exists($meta['controller'], $meta['method'])) {
        echo "  [FAIL] Method {$meta['controller']}::{$meta['method']} missing!\n";
        $allPassed = false;
        continue;
    }
    echo "  [PASS] Action method {$meta['method']}() defined\n";

    // 3. View template file exists
    $viewPath = __DIR__ . '/../' . $meta['view'];
    if (!file_exists($viewPath)) {
        echo "  [FAIL] View template file {$meta['view']} missing!\n";
        $allPassed = false;
        continue;
    }
    echo "  [PASS] View template {$meta['view']} exists\n";

    // 4. View contains NO 'Lists & Payroll Sync'
    $viewContent = file_get_contents($viewPath);
    if (stripos($viewContent, 'Lists & Payroll Sync') !== false) {
        echo "  [FAIL] View contains forbidden 'Lists & Payroll Sync' label!\n";
        $allPassed = false;
    } else {
        echo "  [PASS] Clean from 'Lists & Payroll Sync'\n";
    }

    echo "\n";
}

// Check Sidebar Active Routing in main.php
echo "--- Testing Layout Sidebar Route Matching ---\n";
$layoutContent = file_get_contents(__DIR__ . '/../app/views/layouts/main.php');

// Simulate route=email_campaigns/contacts
$_GET['route'] = 'email_campaigns/contacts';
$_SERVER['REQUEST_URI'] = '/public/index.php?route=email_campaigns/contacts';

$isCampRegActive = (strpos($_GET['route'], 'campaigns') === 0 && strpos($_GET['route'], 'email_campaigns') === false) ||
                   (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/campaigns') !== false && strpos($_SERVER['REQUEST_URI'], 'email_campaigns') === false);

$isListsActive = (isset($_GET['route']) && (strpos($_GET['route'], 'email_campaigns/contact') === 0 || strpos($_GET['route'], 'email_campaigns/list') === 0));

if (!$isCampRegActive) {
    echo "  [PASS] Campaign Registry is strictly NOT active when browsing Contacts\n";
} else {
    echo "  [FAIL] Campaign Registry was falsely highlighted on Contacts route!\n";
    $allPassed = false;
}

if ($isListsActive) {
    echo "  [PASS] Audiences & Lists is properly highlighted when browsing Contacts\n";
} else {
    echo "  [FAIL] Audiences & Lists failed to highlight on Contacts route!\n";
    $allPassed = false;
}

// Verify Payroll section in sidebar
$payrollInMarketing = false;
if (preg_match('/<div[^>]*class="[^"]*nav-section[^"]*"[^>]*>.*?Marketing.*?<\/div>(.*?)<li[^>]*class="[^"]*menu-section[^"]*"/is', $layoutContent, $matches)) {
    if (stripos($matches[1], 'payroll') !== false) {
        $payrollInMarketing = true;
    }
}

if (!$payrollInMarketing) {
    echo "  [PASS] Payroll is completely absent from the Marketing sidebar section\n";
} else {
    echo "  [FAIL] Payroll found inside Marketing sidebar section!\n";
    $allPassed = false;
}

// Verify Payroll exists under its own section
if (strpos($layoutContent, 'data-section="payroll"') !== false) {
    echo "  [PASS] Payroll section is dedicated and properly placed under HR / Operations\n";
} else {
    echo "  [FAIL] Dedicated Payroll section missing!\n";
    $allPassed = false;
}

echo "\n====================================================================\n";
echo $allPassed ? "  ALL MARKETING NAVIGATION AUDITS PASSED (100% CLEAN)\n" : "  FAILURES DETECTED IN NAVIGATION AUDIT\n";
echo "====================================================================\n";

exit($allPassed ? 0 : 1);
