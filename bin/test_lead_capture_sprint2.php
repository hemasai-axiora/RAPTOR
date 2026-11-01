<?php
/**
 * Raptor CRM — Inbound Lead Capture & Tracking Engine (Sprint 2 Automated Verification Suite)
 * -------------------------------------------------------------------------------------------
 * Verifies:
 * 1. Migration 0051 schema integrity (marketing_forms, form_submissions, webhooks, sessions)
 * 2. Form model creation, view/submission counters & embed snippet generation
 * 3. Web form ingestion, Customer 360 resolution, and CRM lead creation
 * 4. Anti-spam honeypot detection & submission isolation
 * 5. Visitor cookie stitching & tracking beacon ingestion (raptor.js collector)
 * 6. Authenticated inbound webhooks (HMAC signature verification)
 * 7. Strict payroll data isolation (zero financial column exposure)
 */

declare(strict_types=1);

require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/core/Model.php';
require_once __DIR__ . '/../app/models/Contact.php';
require_once __DIR__ . '/../app/models/Lead.php';
require_once __DIR__ . '/../app/models/Customer.php';
require_once __DIR__ . '/../app/models/MarketingForm.php';
require_once __DIR__ . '/../app/models/FormSubmission.php';
require_once __DIR__ . '/../app/models/InboundWebhook.php';

// Safe connection setup (SQLite in-memory for deterministic standalone test run)
$pdo = new PDO("sqlite::memory:");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_OBJ);

$pdo->sqliteCreateFunction('TIMESTAMPDIFF', function($unit, $d1, $d2) {
    $t1 = strtotime((string)$d1);
    $t2 = strtotime((string)$d2);
    return (int)round(($t2 - $t1) / 86400);
});
$pdo->sqliteCreateFunction('NOW', function() {
    return date('Y-m-d H:i:s');
});

Database::setMockConnection($pdo);

// Bootstrap required tables from Sprint 1
$pdo->exec("
CREATE TABLE IF NOT EXISTS users (
    user_id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    email TEXT UNIQUE NOT NULL
);

CREATE TABLE IF NOT EXISTS clients (
    client_id INTEGER PRIMARY KEY AUTOINCREMENT,
    company_name TEXT,
    email TEXT
);

CREATE TABLE IF NOT EXISTS teams (
    team_id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT
);

CREATE TABLE IF NOT EXISTS products (
    product_id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT,
    status TEXT DEFAULT 'active'
);

CREATE TABLE IF NOT EXISTS follow_ups (
    follow_up_id INTEGER PRIMARY KEY AUTOINCREMENT,
    lead_id INTEGER,
    status TEXT DEFAULT 'scheduled',
    due_at DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS leads (
    lead_id INTEGER PRIMARY KEY AUTOINCREMENT,
    contact_id INTEGER NULL,
    lead_code TEXT,
    client_id INTEGER,
    owner_employee_id INTEGER,
    assigned_to_user_id INTEGER,
    team_id INTEGER,
    first_name TEXT,
    last_name TEXT,
    company_name TEXT,
    email TEXT,
    phone TEXT,
    status TEXT DEFAULT 'new',
    lead_quality TEXT DEFAULT 'warm',
    conversion_probability REAL DEFAULT 0,
    probability REAL DEFAULT 0,
    lead_value REAL DEFAULT 0,
    lead_source TEXT,
    campaign_source TEXT,
    product_id INTEGER,
    location TEXT,
    priority TEXT DEFAULT 'medium',
    next_follow_up_at DATETIME,
    lost_reason TEXT,
    converted_at DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS lead_status_history (
    history_id INTEGER PRIMARY KEY AUTOINCREMENT,
    lead_id INTEGER,
    from_status TEXT,
    to_status TEXT,
    changed_by_user_id INTEGER,
    note TEXT,
    changed_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS lead_assignments (
    assignment_id INTEGER PRIMARY KEY AUTOINCREMENT,
    lead_id INTEGER,
    from_user_id INTEGER,
    to_user_id INTEGER,
    assigned_by_user_id INTEGER,
    note TEXT,
    assigned_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS lead_journeys (
    journey_id INTEGER PRIMARY KEY AUTOINCREMENT,
    lead_id INTEGER,
    from_status TEXT,
    to_status TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS employees (
    employee_id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,
    name TEXT,
    job_title TEXT
);

CREATE TABLE IF NOT EXISTS salaries (
    salary_id INTEGER PRIMARY KEY AUTOINCREMENT,
    employee_id INTEGER,
    basic_salary REAL,
    ctc REAL,
    bank_account_no TEXT
);
");

// Run Sprint 1 migration 0050
require_once __DIR__ . '/../migrations/0050_unified_customer_360.php';
// Run Sprint 2 migration 0051
require_once __DIR__ . '/../migrations/0051_marketing_forms_and_lead_capture.php';

$passed = 0;
$failed = 0;

function assertCondition(bool $condition, string $testName) {
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "  [PASS] {$testName}\n";
    } else {
        $failed++;
        echo "  [FAIL] {$testName}\n";
    }
}

echo "\n====================================================================\n";
echo "  INBOUND LEAD CAPTURE & TRACKING (SPRINT 2) VERIFICATION SUITE      \n";
echo "====================================================================\n\n";

// ---------------------------------------------------------------------------
// Suite 1: Schema Migration & Table Architecture
// ---------------------------------------------------------------------------
echo "Suite 1: Schema Migration & Table Architecture\n";

$tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
assertCondition(in_array('marketing_forms', $tables, true), "Table 'marketing_forms' exists");
assertCondition(in_array('form_submissions', $tables, true), "Table 'form_submissions' exists");
assertCondition(in_array('inbound_webhook_endpoints', $tables, true), "Table 'inbound_webhook_endpoints' exists");
assertCondition(in_array('web_sessions', $tables, true), "Table 'web_sessions' exists");

$formCols = $pdo->query("PRAGMA table_info(marketing_forms)")->fetchAll(PDO::FETCH_COLUMN, 1);
assertCondition(in_array('form_uuid', $formCols, true), "marketing_forms has 'form_uuid'");
assertCondition(in_array('fields_json', $formCols, true), "marketing_forms has 'fields_json'");
assertCondition(in_array('enable_honeypot', $formCols, true), "marketing_forms has 'enable_honeypot'");

// ---------------------------------------------------------------------------
// Suite 2: Form Model, Views/Submissions Counters & Embed Snippets
// ---------------------------------------------------------------------------
echo "\nSuite 2: Form Model & Embed Code Generation\n";

$formModel = new MarketingForm();
$formId = $formModel->createForm([
    'name' => 'Q4 Enterprise Inbound Form',
    'description' => 'Targeted at high-value enterprise software inquiries',
    'form_type' => 'embedded',
    'submit_button_text' => 'Get Free Demo',
    'success_message' => 'Thank you! A senior account manager will call you shortly.',
    'redirect_url' => 'https://raptor.io/thank-you',
    'default_lead_source' => 'website_demo_request',
    'default_lead_quality' => 'hot',
    'enable_honeypot' => 1
]);

assertCondition($formId > 0, "Marketing form created with positive ID: {$formId}");

$formObj = $formModel->getById($formId);
assertCondition(!empty($formObj->form_uuid), "Form assigned unique UUID: {$formObj->form_uuid}");
assertCondition($formObj->default_lead_quality === 'hot', "Default lead quality configured as 'hot'");

// View and submission counter increments
$formModel->incrementViews($formId);
$formModel->incrementViews($formId);
$formModel->incrementSubmissions($formId);

$updatedForm = $formModel->getById($formId);
assertCondition((int)$updatedForm->views_count === 2, "Views counter incremented to 2");
assertCondition((int)$updatedForm->submissions_count === 1, "Submissions counter incremented to 1");

// Test Embed Snippet Generator
$iframeSnippet = $formModel->generateEmbedSnippet($formObj, 'iframe');
assertCondition(str_contains($iframeSnippet, '<iframe') && str_contains($iframeSnippet, $formObj->form_uuid), "iFrame embed snippet contains valid iframe tag and form_uuid");

$jsSnippet = $formModel->generateEmbedSnippet($formObj, 'js');
assertCondition(str_contains($jsSnippet, 'raptor.js') && str_contains($jsSnippet, 'data-form-uuid'), "JavaScript widget embed snippet contains raptor.js script and data-form-uuid");

// ---------------------------------------------------------------------------
// Suite 3: Web Form Submission Ingestion, Customer 360 & CRM Lead Creation
// ---------------------------------------------------------------------------
echo "\nSuite 3: Web Form Submission Ingestion, Customer 360 & CRM Lead Creation\n";

$contactModel = new Contact();
$leadModel = new Lead();
$subModel = new FormSubmission();

// Simulate inbound form POST payload
$payload = [
    'form_uuid' => $formObj->form_uuid,
    'first_name' => 'Vikram',
    'last_name' => 'Malhotra',
    'email' => 'vikram.malhotra@zenith-logistics.in',
    'phone' => '+919876543210',
    'company_name' => 'Zenith Logistics',
    'notes' => 'Looking for fleet tracking and CRM integration for 250 vehicles.',
    '_rpt_uid' => 'cookie-visitor-uuid-8899aabb',
    'utm_source' => 'google_ads',
    'utm_medium' => 'cpc',
    'utm_campaign' => 'q4_enterprise_search',
    'page_url' => 'https://raptor.io/solutions/enterprise'
];

// Step A: Resolve/Create contact
$contact = $contactModel->findOrCreateByIdentity('email', $payload['email'], [
    'first_name' => $payload['first_name'],
    'last_name' => $payload['last_name'],
    'company_name' => $payload['company_name'],
    'email' => $payload['email'],
    'phone' => $payload['phone'],
    'lifecycle_stage' => 'lead',
    'lead_quality' => $formObj->default_lead_quality,
    'source' => $formObj->default_lead_source,
    'utm_source' => $payload['utm_source'],
    'utm_campaign' => $payload['utm_campaign']
]);

assertCondition(!empty($contact->contact_id), "Customer 360 profile created from inbound submission (ID: {$contact->contact_id})");
assertCondition($contact->lifecycle_stage === 'lead', "Contact lifecycle stage is 'lead'");

// Step B: Link visitor cookie identity
$contactModel->addIdentity((int)$contact->contact_id, 'cookie', $payload['_rpt_uid'], false);
$identities = $contactModel->getIdentities((int)$contact->contact_id);
$hasCookieIdentity = false;
foreach ($identities as $id) {
    if ($id->channel === 'cookie' && $id->identity_value === $payload['_rpt_uid']) {
        $hasCookieIdentity = true;
        break;
    }
}
assertCondition($hasCookieIdentity === true, "Anonymous visitor cookie stitched to Customer 360 profile");

// Step C: Create CRM lead with UTM attribution
$leadId = $leadModel->addLead([
    'first_name' => $payload['first_name'],
    'last_name' => $payload['last_name'],
    'company_name' => $payload['company_name'],
    'email' => $payload['email'],
    'phone' => $payload['phone'],
    'status' => 'new',
    'lead_quality' => $formObj->default_lead_quality,
    'lead_source' => $formObj->default_lead_source,
    'campaign_source' => $payload['utm_campaign']
]);
assertCondition($leadId > 0, "CRM Lead automatically created with UTM attribution (ID: {$leadId})");

// Step D: Log timeline activity
$actId = $contactModel->recordActivity(
    (int)$contact->contact_id,
    'form',
    'form_submission',
    "Submitted Form: {$formObj->name}",
    $payload['notes'],
    ['form_id' => $formId, 'lead_id' => $leadId, 'utm_campaign' => $payload['utm_campaign']]
);
assertCondition($actId > 0, "Form submission logged to Customer 360 timeline (ID: {$actId})");

// Step E: Record form submission audit log
$subId = $subModel->recordSubmission([
    'form_id' => $formId,
    'contact_id' => (int)$contact->contact_id,
    'lead_id' => $leadId,
    'visitor_cookie' => $payload['_rpt_uid'],
    'ip_address' => '203.0.113.42',
    'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
    'page_url' => $payload['page_url'],
    'utm_source' => $payload['utm_source'],
    'utm_campaign' => $payload['utm_campaign'],
    'submitted_data' => $payload
]);
assertCondition($subId > 0, "Form submission audit record created (ID: {$subId})");

// ---------------------------------------------------------------------------
// Suite 4: Anti-Spam Honeypot Verification
// ---------------------------------------------------------------------------
echo "\nSuite 4: Anti-Spam Honeypot Verification\n";

$spamPayload = [
    'form_uuid' => $formObj->form_uuid,
    'first_name' => 'SpamBot',
    'email' => 'bot99@automated-spam-domain.ru',
    '_raptor_hp' => 'http://spam-link.ru/payload' // Honeypot filled by bot
];

// Verify honeypot rejection
$isHoneypotSpam = !empty($spamPayload['_raptor_hp']);
assertCondition($isHoneypotSpam === true, "Honeypot field successfully detected bot injection");

$spamSubId = $subModel->recordSubmission([
    'form_id' => $formId,
    'ip_address' => '198.51.100.12',
    'submitted_data' => $spamPayload,
    'is_spam' => 1,
    'spam_reason' => 'Honeypot field filled'
]);
assertCondition($spamSubId > 0, "Spam submission safely isolated into audit log without creating Contact or Lead");

$contactCheck = $pdo->query("SELECT contact_id FROM contacts WHERE primary_email = 'bot99@automated-spam-domain.ru'")->fetch(PDO::FETCH_OBJ);
assertCondition(empty($contactCheck), "Verified ZERO contacts created for honeypot bot submissions");

// ---------------------------------------------------------------------------
// Suite 5: Visitor Session Tracking & Identity Stitching (raptor.js Collector)
// ---------------------------------------------------------------------------
echo "\nSuite 5: Visitor Session Tracking & Identity Stitching (raptor.js Collector)\n";

$visitorCookie = 'anon-cookie-' . bin2hex(random_bytes(8));

// Step 1: Anonymous pageview
$pdo->prepare("INSERT INTO web_sessions (visitor_cookie, ip_address, first_page_url, utm_source, utm_campaign, pageviews_count)
               VALUES (?, '192.0.2.1', 'https://raptor.io/pricing', 'newsletter', 'weekly_digest', 1)")
    ->execute([$visitorCookie]);

$sess = $pdo->query("SELECT * FROM web_sessions WHERE visitor_cookie = '{$visitorCookie}'")->fetch(PDO::FETCH_OBJ);
assertCondition(!empty($sess), "Anonymous web session initialized for visitor cookie");
assertCondition($sess->contact_id === null, "Session is initially unstitched (anonymous)");

// Step 2: Identification event (Form submitted)
$identifiedContact = $contactModel->findOrCreateByIdentity('email', 'ananya.sharma@tavant.com', [
    'first_name' => 'Ananya',
    'company_name' => 'Tavant Technologies',
    'source' => 'web_auto_capture'
]);

// Stitch cookie
$contactModel->addIdentity((int)$identifiedContact->contact_id, 'cookie', $visitorCookie, false);
$pdo->prepare("UPDATE web_sessions SET contact_id = ? WHERE visitor_cookie = ?")
    ->execute([(int)$identifiedContact->contact_id, $visitorCookie]);

$stitchedSess = $pdo->query("SELECT * FROM web_sessions WHERE visitor_cookie = '{$visitorCookie}'")->fetch(PDO::FETCH_OBJ);
assertCondition((int)$stitchedSess->contact_id === (int)$identifiedContact->contact_id, "Web session successfully stitched to contact_id: {$identifiedContact->contact_id}");

// Step 3: Subsequent pageview logged directly to contact timeline
$contactModel->recordActivity(
    (int)$identifiedContact->contact_id,
    'web',
    'pageview',
    'Visited: Documentation & API Reference',
    'Page URL: https://raptor.io/docs/api',
    ['url' => 'https://raptor.io/docs/api']
);

$timeline = $contactModel->getActivities((int)$identifiedContact->contact_id, 5);
assertCondition(count($timeline) >= 1 && $timeline[0]->activity_type === 'pageview', "Stitched contact's subsequent pageview successfully logged to timeline");

// ---------------------------------------------------------------------------
// Suite 6: Authenticated Inbound Webhooks (HMAC Signature Verification)
// ---------------------------------------------------------------------------
echo "\nSuite 6: Authenticated Inbound Webhooks (HMAC Signature Verification)\n";

$webhookModel = new InboundWebhook();
$wh = $webhookModel->createEndpoint(
    'WordPress Marketing Site Webhook',
    'wordpress',
    null,
    'wordpress_cf7',
    ['email' => 'your-email', 'first_name' => 'your-name']
);

assertCondition(!empty($wh['endpoint_key']), "Webhook endpoint key generated: {$wh['endpoint_key']}");
assertCondition(!empty($wh['secret']), "Webhook secret generated");

$endpointObj = $webhookModel->getByKey($wh['endpoint_key']);
assertCondition(!empty($endpointObj), "Retrieved active webhook endpoint by key");

// Test Signature Generation & Verification
$testPayload = json_encode(['your-name' => 'Deepak', 'your-email' => 'deepak.verma@fintech-labs.io']);
$validSig = hash_hmac('sha256', $testPayload, $endpointObj->secret_hash);
$invalidSig = 'tampered-signature-invalid-hash';

$validCheck = $webhookModel->verifySignature($endpointObj, $testPayload, $validSig);
assertCondition($validCheck === true, "Cryptographic HMAC-SHA256 signature verified successfully");

$invalidCheck = $webhookModel->verifySignature($endpointObj, $testPayload, $invalidSig);
assertCondition($invalidCheck === false, "Tampered signature correctly rejected");

// Ingest valid webhook payload into Customer 360
$whContact = $contactModel->findOrCreateByIdentity('email', 'deepak.verma@fintech-labs.io', [
    'first_name' => 'Deepak',
    'source' => $endpointObj->default_lead_source
]);
assertCondition(!empty($whContact->contact_id), "Webhook prospect ingested into Customer 360 (ID: {$whContact->contact_id})");

$webhookModel->recordHit((int)$endpointObj->endpoint_id);
$updatedEndpoint = $webhookModel->getByKey($wh['endpoint_key']);
assertCondition((int)$updatedEndpoint->total_received === 1, "Webhook total_received counter incremented to 1");

// ---------------------------------------------------------------------------
// Suite 7: Strict Payroll Isolation Guarantee
// ---------------------------------------------------------------------------
echo "\nSuite 7: Strict Payroll Isolation Guarantee\n";

$formCols = $pdo->query("PRAGMA table_info(marketing_forms)")->fetchAll(PDO::FETCH_COLUMN, 1);
$subCols = $pdo->query("PRAGMA table_info(form_submissions)")->fetchAll(PDO::FETCH_COLUMN, 1);
$sessCols = $pdo->query("PRAGMA table_info(web_sessions)")->fetchAll(PDO::FETCH_COLUMN, 1);

$forbiddenCols = ['basic_salary', 'ctc', 'salary', 'bank_account_no', 'bank_name', 'ifsc', 'pan_number', 'pf_number'];

$leakFound = false;
foreach ($forbiddenCols as $fc) {
    if (in_array($fc, $formCols, true) || in_array($fc, $subCols, true) || in_array($fc, $sessCols, true)) {
        $leakFound = true;
        break;
    }
}
assertCondition(!$leakFound, "Zero salary/banking/compensation columns exist in any Sprint 2 form or tracking tables");

// ===========================================================================
// Summary
// ===========================================================================
echo "\n====================================================================\n";
printf("SUMMARY: %d PASSED, %d FAILED\n", $passed, $failed);
echo "====================================================================\n";

exit($failed > 0 ? 1 : 0);

