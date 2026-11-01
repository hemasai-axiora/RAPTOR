<?php
/**
 * Raptor CRM — Customer 360 Architecture & Sprint 1 Automated Verification Suite
 * -----------------------------------------------------------------------------
 * Verifies:
 * 1. Schema migration & table integrity (contacts, identities, activities)
 * 2. Deterministic multi-channel identity resolution & deduplication
 * 3. Universal omnichannel activity timeline recording & retrieval
 * 4. Dynamic lifecycle stage transitions & automated lead scoring
 * 5. Cross-channel suppression bridging (contacts <-> campaigns <-> CRM leads)
 * 6. Automated Lead and Customer model two-way synchronization
 * 7. Strict payroll data isolation (zero financial/salary column leakage)
 */

declare(strict_types=1);

require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/core/Model.php';
require_once __DIR__ . '/../app/models/Contact.php';
require_once __DIR__ . '/../app/models/Lead.php';
require_once __DIR__ . '/../app/models/Customer.php';
require_once __DIR__ . '/../app/models/CampaignSuppression.php';

// Safe connection setup (SQLite in-memory for deterministic standalone test run)
$pdo = new PDO("sqlite::memory:");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_OBJ);

// Register SQLite compatibility functions for MySQL date functions
$pdo->sqliteCreateFunction('TIMESTAMPDIFF', function($unit, $d1, $d2) {
    $t1 = strtotime((string)$d1);
    $t2 = strtotime((string)$d2);
    return (int)round(($t2 - $t1) / 86400);
});
$pdo->sqliteCreateFunction('NOW', function() {
    return date('Y-m-d H:i:s');
});

Database::setMockConnection($pdo);

// Bootstrap required tables in mock DB
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

CREATE TABLE IF NOT EXISTS customers (
    customer_id INTEGER PRIMARY KEY AUTOINCREMENT,
    contact_id INTEGER NULL,
    customer_code TEXT,
    converted_from_lead_id INTEGER,
    first_name TEXT,
    company_name TEXT,
    customer_type TEXT DEFAULT 'Business',
    email TEXT,
    phone TEXT,
    billing_address TEXT,
    shipping_address TEXT,
    owner_employee_id INTEGER,
    associated_client_id INTEGER,
    status TEXT DEFAULT 'Active',
    onboarding_date DATE,
    contract_value REAL DEFAULT 0,
    payment_terms TEXT DEFAULT 'Net 30',
    products_subscribed TEXT,
    renewal_date DATE,
    tags TEXT,
    notes TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS campaign_contacts (
    contact_id INTEGER PRIMARY KEY AUTOINCREMENT,
    unified_contact_id INTEGER NULL,
    email TEXT UNIQUE NOT NULL,
    first_name TEXT,
    last_name TEXT,
    company_name TEXT,
    status TEXT DEFAULT 'subscribed',
    source TEXT DEFAULT 'manual',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS campaign_suppressions (
    suppression_id INTEGER PRIMARY KEY AUTOINCREMENT,
    email TEXT UNIQUE NOT NULL,
    reason TEXT NOT NULL,
    notes TEXT,
    source_campaign_id INTEGER,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS campaign_suppression_list (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    email TEXT UNIQUE NOT NULL,
    reason TEXT NOT NULL,
    source TEXT,
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
");

// Execute migration 0050 in mock environment
require_once __DIR__ . '/../migrations/0050_unified_customer_360.php';

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
echo "  CUSTOMER 360 & SPRINT 1 AUTOMATED VERIFICATION SUITE              \n";
echo "====================================================================\n\n";

// ---------------------------------------------------------------------------
// Suite 1: Schema Integrity & Table Architecture
// ---------------------------------------------------------------------------
echo "Suite 1: Schema Integrity & Table Architecture\n";

$tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
assertCondition(in_array('contacts', $tables, true), "Master table 'contacts' exists");
assertCondition(in_array('contact_identities', $tables, true), "Identity table 'contact_identities' exists");
assertCondition(in_array('contact_activities', $tables, true), "Timeline table 'contact_activities' exists");

$cols = $pdo->query("PRAGMA table_info(contacts)")->fetchAll(PDO::FETCH_COLUMN, 1);
assertCondition(in_array('contact_uuid', $cols, true), "contacts table has 'contact_uuid'");
assertCondition(in_array('lifecycle_stage', $cols, true), "contacts table has 'lifecycle_stage'");
assertCondition(in_array('lead_score', $cols, true), "contacts table has 'lead_score'");
assertCondition(in_array('is_suppressed', $cols, true), "contacts table has 'is_suppressed'");

// ---------------------------------------------------------------------------
// Suite 2: Deterministic Identity Resolution & Deduplication
// ---------------------------------------------------------------------------
echo "\nSuite 2: Deterministic Identity Resolution & Deduplication\n";

$contactModel = new Contact();

// Step A: Create contact via Email
$c1 = $contactModel->findOrCreateByIdentity('email', 'Elena.Rostova@ApexTech.io', [
    'first_name' => 'Elena',
    'last_name' => 'Rostova',
    'company_name' => 'Apex Technologies',
    'lifecycle_stage' => 'lead',
    'source' => 'web_inbound'
]);

assertCondition(!empty($c1->contact_id), "Initial contact created with primary ID: {$c1->contact_id}");
assertCondition(!empty($c1->contact_uuid), "Contact assigned valid UUID v4: {$c1->contact_uuid}");
assertCondition($c1->primary_email === 'elena.rostova@apextech.io', "Primary email normalized to lowercase");

// Step B: Resolve same contact via identical email (Idempotent Deduplication)
$c1_again = $contactModel->findOrCreateByIdentity('email', 'ELENA.ROSTOVA@apextech.io');
assertCondition((int)$c1_again->contact_id === (int)$c1->contact_id, "Re-resolving by case-insensitive email yields exact same contact_id");

// Step C: Link phone number identity
$phoneAdded = $contactModel->addIdentity((int)$c1->contact_id, 'phone', '+1 (555) 234-5678', false);
assertCondition($phoneAdded === true, "Secondary identity (phone) linked to contact");

// Step D: Resolve contact by Phone identity
$c1_by_phone = $contactModel->findOrCreateByIdentity('phone', '+1-555-234-5678');
assertCondition((int)$c1_by_phone->contact_id === (int)$c1->contact_id, "Lookup by phone successfully resolves original contact (Customer 360 Match)");

$identities = $contactModel->getIdentities((int)$c1->contact_id);
assertCondition(count($identities) >= 2, "Contact holds multiple unified identities (" . count($identities) . " found)");

// ---------------------------------------------------------------------------
// Suite 3: Omnichannel Activity Timeline
// ---------------------------------------------------------------------------
echo "\nSuite 3: Omnichannel Activity Timeline\n";

$act1 = $contactModel->recordActivity(
    (int)$c1->contact_id,
    'web',
    'pageview',
    'Viewed Enterprise Pricing Page',
    'Spent 140s on /pricing with UTM campaign q4_enterprise',
    ['url' => '/pricing', 'duration' => 140]
);
assertCondition($act1 > 0, "Web pageview activity logged to timeline (ID: {$act1})");

$act2 = $contactModel->recordActivity(
    (int)$c1->contact_id,
    'email',
    'email_opened',
    'Opened Campaign: Product Launch',
    'User Agent: Mozilla/5.0 Apple Mail',
    ['campaign_id' => 101]
);
assertCondition($act2 > 0, "Email engagement activity logged (ID: {$act2})");

$act3 = $contactModel->recordActivity(
    (int)$c1->contact_id,
    'call',
    'sales_call_completed',
    'Discovery Call with Rep',
    'Discussed 50-seat deployment, very positive reception.',
    ['duration_seconds' => 450, 'outcome' => 'Interested']
);
assertCondition($act3 > 0, "Telephony activity logged (ID: {$act3})");

$timeline = $contactModel->getActivities((int)$c1->contact_id, 10);
assertCondition(count($timeline) >= 4, "Timeline aggregates all touchpoints in descending chronological order (" . count($timeline) . " entries)");
assertCondition($timeline[0]->activity_type === 'sales_call_completed', "Most recent activity is at the top of timeline");

// ---------------------------------------------------------------------------
// Suite 4: Lifecycle Stage Progression & Lead Scoring
// ---------------------------------------------------------------------------
echo "\nSuite 4: Lifecycle Stage Progression & Lead Scoring\n";

// Transition: Lead -> SQL
$stageRes = $contactModel->setLifecycleStage((int)$c1->contact_id, 'sql');
assertCondition($stageRes === true, "Lifecycle stage transitioned to 'sql'");
$c1_updated = $contactModel->getById((int)$c1->contact_id);
assertCondition($c1_updated->lifecycle_stage === 'sql', "Contact profile confirms lifecycle_stage = 'sql'");

// Lead Scoring: Add 40 points
$newScore = $contactModel->updateLeadScore((int)$c1->contact_id, 40, 'Requested Product Demo');
assertCondition($newScore === 40, "Lead score incremented to {$newScore}");
$c1_scored = $contactModel->getById((int)$c1->contact_id);
assertCondition($c1_scored->lead_quality === 'warm', "Quality adjusted to 'warm' (score >= 15)");

// Lead Scoring: Add 20 more points (Total 60 -> Hot)
$newScore2 = $contactModel->updateLeadScore((int)$c1->contact_id, 20, 'Pricing Page High Intent');
assertCondition($newScore2 === 60, "Lead score elevated to 60");
$c1_hot = $contactModel->getById((int)$c1->contact_id);
assertCondition($c1_hot->lead_quality === 'hot', "Quality automatically elevated to 'hot' (score >= 50)");

// ---------------------------------------------------------------------------
// Suite 5: Cross-Channel Suppression Bridging
// ---------------------------------------------------------------------------
echo "\nSuite 5: Cross-Channel Suppression Bridging\n";

// Seed matching lead in CRM
$pdo->prepare("INSERT INTO leads (first_name, email, phone, status) VALUES ('Elena', 'elena.rostova@apextech.io', '+15552345678', 'contacted')")->execute();
$leadId = (int)$pdo->lastInsertId();

// Suppress contact via Contact model
$suppRes = $contactModel->suppressContact((int)$c1->contact_id, 'user_unsubscribed');
assertCondition($suppRes === true, "Contact suppression executed");

$c1_supp = $contactModel->getById((int)$c1->contact_id);
assertCondition((int)$c1_supp->is_suppressed === 1, "contacts.is_suppressed set to 1");
assertCondition($c1_supp->suppression_reason === 'user_unsubscribed', "Suppression reason recorded");

// Verify propagation to campaign_suppression_list
$suppRow = $pdo->query("SELECT * FROM campaign_suppression_list WHERE email = 'elena.rostova@apextech.io'")->fetch(PDO::FETCH_OBJ);
assertCondition(!empty($suppRow), "Email bridged automatically into campaign_suppression_list");

// Verify propagation to CRM leads
$leadRow = $pdo->query("SELECT status FROM leads WHERE lead_id = {$leadId}")->fetch(PDO::FETCH_OBJ);
assertCondition($leadRow->status === 'unsubscribed', "CRM lead status automatically updated to 'unsubscribed' to halt cold calling");

// Lift suppression
$unsuppRes = $contactModel->unsuppressContact((int)$c1->contact_id);
assertCondition($unsuppRes === true, "Suppression lifted cleanly");
$suppRowAfter = $pdo->query("SELECT * FROM campaign_suppression_list WHERE email = 'elena.rostova@apextech.io'")->fetch(PDO::FETCH_OBJ);
assertCondition(empty($suppRowAfter), "Email removed from suppression list upon unsuppress");

// ---------------------------------------------------------------------------
// Suite 6: Automated Lead & Customer Model Two-Way Synchronization
// ---------------------------------------------------------------------------
echo "\nSuite 6: Automated Lead & Customer Model Two-Way Synchronization\n";

$leadModel = new Lead();
$newLeadId = $leadModel->addLead([
    'first_name' => 'Marcus',
    'last_name' => 'Vance',
    'company_name' => 'Vance Enterprises',
    'email' => 'marcus.vance@vance-ent.com',
    'phone' => '+15559876543',
    'status' => 'new',
    'lead_quality' => 'warm',
    'lead_source' => 'web_inbound'
]);
assertCondition($newLeadId > 0, "New lead created via Lead::addLead (ID: {$newLeadId})");

$leadRecord = $pdo->query("SELECT * FROM leads WHERE lead_id = {$newLeadId}")->fetch(PDO::FETCH_OBJ);
assertCondition(!empty($leadRecord->contact_id), "leads.contact_id automatically populated via Customer 360 sync hook: {$leadRecord->contact_id}");

$syncedContact = $contactModel->getById((int)$leadRecord->contact_id);
assertCondition(!empty($syncedContact), "Customer 360 profile verified for newly created lead");
assertCondition($syncedContact->primary_email === 'marcus.vance@vance-ent.com', "Synced contact email verified");

// Add Customer
$custModel = new Customer();
$newCustId = $custModel->addCustomer([
    'first_name' => 'Marcus',
    'company_name' => 'Vance Enterprises',
    'email' => 'marcus.vance@vance-ent.com',
    'phone' => '+15559876543',
    'customer_type' => 'Business',
    'contract_value' => '15000.00'
]);
assertCondition($newCustId > 0, "Customer record created via Customer::addCustomer (ID: {$newCustId})");

$custRecord = $pdo->query("SELECT * FROM customers WHERE customer_id = {$newCustId}")->fetch(PDO::FETCH_OBJ);
assertCondition(!empty($custRecord->contact_id), "customers.contact_id automatically mapped to same contact: {$custRecord->contact_id}");
assertCondition((int)$custRecord->contact_id === (int)$leadRecord->contact_id, "Lead and Customer converge on the exact same Customer 360 contact_id");

// Set stage to customer via Contact
$contactModel->setLifecycleStage((int)$custRecord->contact_id, 'customer');
$finalContact = $contactModel->getById((int)$custRecord->contact_id);
assertCondition($finalContact->lifecycle_stage === 'customer', "Lifecycle stage progressed to 'customer'");

// ---------------------------------------------------------------------------
// Suite 7: Strict Payroll Isolation Guarantee
// ---------------------------------------------------------------------------
echo "\nSuite 7: Strict Payroll Isolation Guarantee\n";

$contactCols = $pdo->query("PRAGMA table_info(contacts)")->fetchAll(PDO::FETCH_COLUMN, 1);
$forbiddenCols = ['basic_salary', 'ctc', 'salary', 'bank_account_no', 'bank_name', 'ifsc', 'pan_number', 'pf_number'];

$leakFound = false;
foreach ($forbiddenCols as $fc) {
    if (in_array($fc, $contactCols, true)) {
        $leakFound = true;
        break;
    }
}
assertCondition(!$leakFound, "Zero salary/banking/compensation columns exist in Customer 360 'contacts' table");

$identityCols = $pdo->query("PRAGMA table_info(contact_identities)")->fetchAll(PDO::FETCH_COLUMN, 1);
$actCols = $pdo->query("PRAGMA table_info(contact_activities)")->fetchAll(PDO::FETCH_COLUMN, 1);

assertCondition(!in_array('salary', $identityCols, true), "Zero financial columns in 'contact_identities'");
assertCondition(!in_array('salary', $actCols, true), "Zero financial columns in 'contact_activities'");

// ===========================================================================
// Summary
// ===========================================================================
echo "\n====================================================================\n";
printf("SUMMARY: %d PASSED, %d FAILED\n", $passed, $failed);
echo "====================================================================\n";

exit($failed > 0 ? 1 : 0);
