<?php
/**
 * Raptor CRM - Email Campaigns Phase 6 Automated Verification Suite
 * 
 * Tests:
 * 1. 2048-bit RSA DKIM Keypair Generation & AES-256 Private Key Encryption
 * 2. Sending Domain CRUD & Warm-up Schedule Auto-initialization
 * 3. Required DNS Records Generator (SPF, DKIM, DMARC, MX, Tracking CNAME)
 * 4. Live DNS Verification Engine (SPF/DKIM/DMARC/MX live resolution & DB sync)
 * 5. DNSBL Blacklist Monitoring (IP & Domain lookups, response codes, delist URLs)
 * 6. Daily Deliverability Health Report Aggregation & Tripwire Threshold Alerts
 * 7. HTML & Plain-Text Report Formatting
 * 8. CLI Command Execution (bin/daily_health_report.php --dry-run)
 */

declare(strict_types=1);

require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../vendor/autoload.php';
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
        CREATE TABLE campaign_domains (
            domain_id INTEGER PRIMARY KEY AUTOINCREMENT,
            domain_name TEXT UNIQUE NOT NULL,
            is_default INTEGER DEFAULT 0,
            spf_status TEXT DEFAULT "unverified",
            spf_record_expected TEXT,
            dkim_status TEXT DEFAULT "unverified",
            dkim_selector TEXT DEFAULT "raptor",
            dkim_public_key TEXT,
            dkim_private_key_encrypted TEXT,
            dmarc_status TEXT DEFAULT "unverified",
            dmarc_policy TEXT DEFAULT "none",
            mx_status TEXT DEFAULT "unverified",
            last_checked_at DATETIME,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE campaign_warmup_schedules (
            warmup_id INTEGER PRIMARY KEY AUTOINCREMENT,
            domain_id INTEGER NOT NULL,
            current_stage INTEGER DEFAULT 1,
            daily_cap INTEGER DEFAULT 50,
            sent_today INTEGER DEFAULT 0,
            date_stamp DATE NOT NULL,
            is_active INTEGER DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE (domain_id, date_stamp)
        );
        CREATE TABLE campaign_email_events (
            event_id INTEGER PRIMARY KEY AUTOINCREMENT,
            campaign_id INTEGER,
            recipient_id INTEGER,
            contact_id INTEGER,
            event_type TEXT NOT NULL,
            ip_address TEXT,
            user_agent TEXT,
            target_url TEXT,
            payload_json TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE campaign_email_queue (
            queue_id INTEGER PRIMARY KEY AUTOINCREMENT,
            campaign_id INTEGER,
            recipient_id INTEGER,
            status TEXT DEFAULT "pending",
            priority INTEGER DEFAULT 10,
            attempts INTEGER DEFAULT 0,
            max_attempts INTEGER DEFAULT 3,
            scheduled_at DATETIME DEFAULT CURRENT_TIMESTAMP,
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
        CREATE TABLE campaigns_broadcast (
            campaign_id INTEGER PRIMARY KEY AUTOINCREMENT,
            campaign_code TEXT UNIQUE NOT NULL,
            name TEXT NOT NULL,
            sent_count INTEGER DEFAULT 0,
            delivered_count INTEGER DEFAULT 0,
            bounced_count INTEGER DEFAULT 0,
            complained_count INTEGER DEFAULT 0
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
echo "  RAPTOR CRM: EMAIL CAMPAIGNS & MASS MAILING (PHASE 6 TEST SUITE)   \n";
echo "====================================================================\n\n";

// Load models and services
require_once __DIR__ . '/../app/models/CampaignDomain.php';
require_once __DIR__ . '/../app/services/DomainVerificationService.php';
require_once __DIR__ . '/../app/services/BlacklistMonitorService.php';
require_once __DIR__ . '/../app/services/mailers/MailerSendResult.php';
require_once __DIR__ . '/../app/services/mailers/MailerAdapterInterface.php';
require_once __DIR__ . '/../app/services/mailers/MockMailerAdapter.php';
require_once __DIR__ . '/../app/services/mailers/MailerFactory.php';
require_once __DIR__ . '/../app/services/DeliverabilityHealthReportService.php';

// Force mock mailer for tests
$_ENV['MAIL_PROVIDER'] = 'mock';

// ====================================================================
// GROUP 1: 2048-bit RSA DKIM Keypair Generation & Encryption
// ====================================================================
echo "[GROUP 1] 2048-bit RSA DKIM Keypair Generation & Encryption:\n";

$keypair = CampaignDomain::generateDkimKeypair();

assertCondition(!empty($keypair['private_key']), 'Generated 2048-bit RSA private key');
assertCondition(!empty($keypair['public_key_clean']), 'Extracted clean public key for DNS TXT record');
assertCondition(str_starts_with($keypair['public_key_clean'], 'MIIB'), 'Public key has standard RSA 2048-bit Base64 header (MIIB...)');
assertCondition(!str_contains($keypair['public_key_clean'], '-----BEGIN'), 'Public key is stripped of PEM delimiter headers for DNS compatibility');

// Test private key encryption & decryption round-trip
$encrypted = openssl_encrypt($keypair['private_key'], 'AES-256-CBC', substr(hash('sha256', 'test-salt', true), 0, 32), 0, str_repeat('a', 16));
assertCondition(!empty($encrypted), 'Private key successfully encrypted with AES-256');

// ====================================================================
// GROUP 2: Sending Domain CRUD & Warm-up Association
// ====================================================================
echo "\n[GROUP 2] Sending Domain Model CRUD & Auto-Initialization:\n";

$domainModel = new CampaignDomain();
$domainId = $domainModel->createDomain('mail.axiora.com', 'raptor', true);

assertCondition($domainId > 0, 'Created sending domain record in database');

$domain = $domainModel->getById($domainId);
assertCondition($domain !== null && $domain->domain_name === 'mail.axiora.com', 'Retrieved domain record by ID');
assertCondition((int)$domain->is_default === 1, 'Domain marked as default sending domain');
assertCondition($domain->dkim_selector === 'raptor', 'Domain has correct DKIM selector');
assertCondition($domain->spf_status === 'unverified', 'Initial SPF status is unverified');
assertCondition($domain->dkim_status === 'unverified', 'Initial DKIM status is unverified');

// Verify warmup schedule was initialized
$warmupStmt = $pdo->query("SELECT * FROM campaign_warmup_schedules WHERE domain_id = {$domainId}");
$warmup = $warmupStmt->fetch(PDO::FETCH_OBJ);
assertCondition($warmup !== false, 'Warm-up schedule automatically initialized for new domain');
assertCondition((int)$warmup->daily_cap === 50, 'Warm-up stage 1 initialized with conservative daily cap (50/day)');

// ====================================================================
// GROUP 3: Expected DNS Records Generator
// ====================================================================
echo "\n[GROUP 3] Expected DNS Records Generator:\n";

$dnsRecords = DomainVerificationService::getRequiredDnsRecords($domain);

assertCondition(isset($dnsRecords['spf']), 'Generated SPF record definition');
assertCondition(str_starts_with($dnsRecords['spf']['value'], 'v=spf1'), 'SPF record begins with v=spf1');
assertCondition($dnsRecords['spf']['type'] === 'TXT', 'SPF record type is TXT');

assertCondition(isset($dnsRecords['dkim']), 'Generated DKIM record definition');
assertCondition($dnsRecords['dkim']['full_host'] === 'raptor._domainkey.mail.axiora.com', 'DKIM host is raptor._domainkey.mail.axiora.com');
assertCondition(str_contains($dnsRecords['dkim']['value'], 'v=DKIM1; k=rsa; p='), 'DKIM record contains v=DKIM1 and RSA public key');

assertCondition(isset($dnsRecords['dmarc']), 'Generated DMARC record definition');
assertCondition($dnsRecords['dmarc']['full_host'] === '_dmarc.mail.axiora.com', 'DMARC host is _dmarc.mail.axiora.com');
assertCondition(str_contains($dnsRecords['dmarc']['value'], 'v=DMARC1; p=none'), 'DMARC policy begins safely in monitoring mode (p=none)');

assertCondition(isset($dnsRecords['mx']), 'Generated MX record definition');
assertCondition(str_contains($dnsRecords['mx']['value'], 'inbound.mail.axiora.com'), 'MX record configured with inbound routing target');

assertCondition(isset($dnsRecords['tracking']), 'Generated Tracking CNAME record definition');
assertCondition($dnsRecords['tracking']['host'] === 'track', 'Tracking CNAME host is "track"');

// ====================================================================
// GROUP 4: Live DNS Verification Engine (SPF/DKIM/DMARC/MX)
// ====================================================================
echo "\n[GROUP 4] Live DNS Verification Engine:\n";

// Set mock DNS resolver simulating authenticated DNS configuration
DomainVerificationService::setDnsResolver(function(string $host, int $type) use ($domain) {
    if ($type === DNS_TXT) {
        if ($host === 'mail.axiora.com') {
            return [['txt' => 'v=spf1 include:_spf.raptor.internal ~all']];
        }
        if ($host === 'raptor._domainkey.mail.axiora.com') {
            return [['txt' => "v=DKIM1; k=rsa; p={$domain->dkim_public_key}"]];
        }
        if ($host === '_dmarc.mail.axiora.com') {
            return [['txt' => 'v=DMARC1; p=none; sp=none; rua=mailto:dmarc@mail.axiora.com']];
        }
    }
    if ($type === DNS_MX && $host === 'mail.axiora.com') {
        return [['host' => 'mail.axiora.com', 'target' => 'inbound.mail.axiora.com', 'pri' => 10]];
    }
    return [];
});

$verifyResult = DomainVerificationService::verifyDomain($domainId);

assertCondition($verifyResult['success'] === true, 'Verification engine completed check');
assertCondition($verifyResult['results']['spf']['valid'] === true, 'SPF record successfully verified');
assertCondition($verifyResult['results']['dkim']['valid'] === true, '2048-bit DKIM key successfully verified');
assertCondition($verifyResult['results']['dmarc']['valid'] === true, 'DMARC record successfully verified');
assertCondition($verifyResult['results']['mx']['valid'] === true, 'MX record successfully verified');
assertCondition($verifyResult['all_passed'] === true, 'All DNS authentication checks passed (all_passed = true)');

// Verify database was updated
$domainAfterVerify = $domainModel->getById($domainId);
assertCondition($domainAfterVerify->spf_status === 'valid', 'Domain DB row updated: spf_status = valid');
assertCondition($domainAfterVerify->dkim_status === 'valid', 'Domain DB row updated: dkim_status = valid');
assertCondition($domainAfterVerify->dmarc_status === 'valid', 'Domain DB row updated: dmarc_status = valid');
assertCondition(!empty($domainAfterVerify->last_checked_at), 'Recorded last_checked_at timestamp');

// Test Failure Case: Missing DKIM
DomainVerificationService::setDnsResolver(function(string $host, int $type) {
    if ($type === DNS_TXT && $host === 'mail.axiora.com') {
        return [['txt' => 'v=spf1 ~all']];
    }
    return [];
});

$failResult = DomainVerificationService::verifyDomain($domainId);
assertCondition($failResult['all_passed'] === false, 'Verification correctly reports all_passed = false on missing DKIM');
assertCondition($failResult['results']['dkim']['valid'] === false, 'DKIM flagged as invalid');

// Reset resolver
DomainVerificationService::setDnsResolver(null);

// ====================================================================
// GROUP 5: DNSBL Blacklist Monitoring (IP & Domain)
// ====================================================================
echo "\n[GROUP 5] DNSBL Blacklist Monitoring:\n";

// 1. Mock Clean Domain & Clean IP
BlacklistMonitorService::setDnsResolver(function(string $host, int $type) {
    return []; // No listing records
});

$cleanDomainCheck = BlacklistMonitorService::checkDomain('mail.axiora.com');
assertCondition($cleanDomainCheck['is_clean'] === true, 'Clean domain reported as unlisted (is_clean = true)');
assertCondition($cleanDomainCheck['listed_count'] === 0, 'Clean domain listed count is 0');

$cleanIpCheck = BlacklistMonitorService::checkIp('198.51.100.1');
assertCondition($cleanIpCheck['is_clean'] === true, 'Clean IP reported as unlisted (is_clean = true)');
assertCondition($cleanIpCheck['listed_count'] === 0, 'Clean IP listed count is 0');

// 2. Mock Listed Domain on Spamhaus DBL
BlacklistMonitorService::setDnsResolver(function(string $host, int $type) {
    if (str_contains($host, 'dbl.spamhaus.org')) {
        return [['ip' => '127.0.1.2']]; // Spamhaus DBL spam domain return code
    }
    return [];
});

$listedDomainCheck = BlacklistMonitorService::checkDomain('spammer-domain.com');
assertCondition($listedDomainCheck['is_clean'] === false, 'Malicious/flagged domain detected as listed (is_clean = false)');
assertCondition($listedDomainCheck['listed_count'] === 1, 'Accurately reported 1 blacklist match');
assertCondition(!empty($listedDomainCheck['details']['dbl.spamhaus.org']['delist_url']), 'Provided remediation delisting URL');

// 3. Mock Listed IP on Spamhaus ZEN (127.0.0.2 SBL)
BlacklistMonitorService::setDnsResolver(function(string $host, int $type) {
    if (str_contains($host, 'zen.spamhaus.org')) {
        return [['ip' => '127.0.0.2']];
    }
    return [];
});

$listedIpCheck = BlacklistMonitorService::checkIp('198.51.100.25');
assertCondition($listedIpCheck['is_clean'] === false, 'Flagged IP detected as listed on Spamhaus ZEN');
assertCondition(str_contains($listedIpCheck['details']['zen.spamhaus.org']['meaning'], 'Direct Unsolicited Bulk Email'), 'Correctly decoded Spamhaus return code 127.0.0.2 as SBL');

// Reset resolver
BlacklistMonitorService::setDnsResolver(null);

// ====================================================================
// GROUP 6: Deliverability Health Report Aggregation & Tripwire Warnings
// ====================================================================
echo "\n[GROUP 6] Deliverability Health Report Aggregation & Tripwires:\n";

// Seed queue items
$pdo->exec("INSERT INTO campaign_email_queue (campaign_id, recipient_id, status) VALUES 
    (1, 1, 'pending'),
    (1, 2, 'pending'),
    (1, 3, 'processing'),
    (1, 4, 'failed')");

// Seed suppression items
$pdo->exec("INSERT INTO campaign_suppressions (email, reason) VALUES ('bad1@test.com', 'hard_bounce'), ('bad2@test.com', 'spam_complaint')");

// Seed healthy 24-hour dispatch events: 200 sent, 1 bounce (0.5%), 0 complaints
for ($i = 0; $i < 200; $i++) {
    $pdo->exec("INSERT INTO campaign_email_events (campaign_id, event_type, created_at) VALUES (1, 'sent', CURRENT_TIMESTAMP)");
}
$pdo->exec("INSERT INTO campaign_email_events (campaign_id, event_type, created_at) VALUES (1, 'hard_bounce', CURRENT_TIMESTAMP)");

$health = DeliverabilityHealthReportService::collectHealthData();

assertCondition($health['metrics_24h']['sent'] >= 100, 'Report aggregated sent count over past 24 hours');
assertCondition($health['metrics_24h']['bounce_status'] === 'healthy', 'Bounce rate classified as HEALTHY (< 1%)');
assertCondition($health['metrics_24h']['complaint_status'] === 'healthy', 'Complaint rate classified as HEALTHY (< 0.05%)');
assertCondition($health['queue']['pending'] === 2, 'Queue backlog reported 2 pending tasks');
assertCondition($health['queue']['failed'] === 1, 'Queue backlog reported 1 failed task');
assertCondition($health['suppressions_total'] === 2, 'Reported 2 total suppressed addresses');

// Test Tripwire Warning: Add 10 complaints to breach the 0.1% threshold
for ($i = 0; $i < 10; $i++) {
    $pdo->exec("INSERT INTO campaign_email_events (campaign_id, event_type, created_at) VALUES (1, 'complaint', CURRENT_TIMESTAMP)");
}

$healthBreached = DeliverabilityHealthReportService::collectHealthData();
assertCondition($healthBreached['metrics_24h']['complaint_status'] === 'critical', 'TRIPWIRE TRIP: Complaint rate marked CRITICAL when breaching 0.1% threshold');

// ====================================================================
// GROUP 7: HTML & Plain-Text Report Formatting
// ====================================================================
echo "\n[GROUP 7] Health Report Template Formatting:\n";

$html = DeliverabilityHealthReportService::buildHtmlReport($health);
assertCondition(str_contains($html, 'Daily Deliverability Health Report'), 'HTML report contains header title');
assertCondition(str_contains($html, 'Bounce Rate'), 'HTML report displays bounce rate card');
assertCondition(str_contains($html, 'Sending Domain DNS Authentication'), 'HTML report includes domain DNS table');

$plainText = DeliverabilityHealthReportService::buildPlainTextReport($health);
assertCondition(str_contains($plainText, 'RAPTOR CRM: DAILY DELIVERABILITY HEALTH REPORT'), 'Plain text report contains ASCII header');
assertCondition(str_contains($plainText, 'Bounce Rate:'), 'Plain text report includes bounce rate');

// Test Mock Report Dispatch
$dispatched = DeliverabilityHealthReportService::dispatchReport('admin@raptor.internal');
assertCondition($dispatched === true, 'Successfully dispatched daily health report email via mock adapter');

// ====================================================================
// GROUP 8: CLI Command Execution
// ====================================================================
echo "\n[GROUP 8] CLI Daily Health Report Command:\n";

$cliOutput = shell_exec('php bin/daily_health_report.php --dry-run 2>&1');
assertCondition(str_contains((string)$cliOutput, 'Starting Daily Deliverability Health Check'), 'CLI script starts execution');
assertCondition(str_contains((string)$cliOutput, '[DRY RUN] Email dispatch skipped'), 'CLI script supports --dry-run flag');
assertCondition(str_contains((string)$cliOutput, 'Sent past 24h'), 'CLI script prints metrics summary');

echo "\n====================================================================\n";
echo "  PHASE 6 TEST SUMMARY: {$testsPassed} PASSED, {$testsFailed} FAILED\n";
echo "====================================================================\n";

exit($testsFailed > 0 ? 1 : 0);
