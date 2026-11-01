<?php
/**
 * Raptor CRM - Daily Deliverability Health Report CLI Command
 * 
 * Usage:
 *   php bin/daily_health_report.php [--to=admin@company.com] [--dry-run]
 * 
 * Schedules via cron (e.g. daily at 06:00 UTC):
 *   0 6 * * * php /path/to/raptor/bin/daily_health_report.php >> /var/log/raptor_health.log 2>&1
 */

declare(strict_types=1);

require_once __DIR__ . '/../app/config/config.php';

$composerAutoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($composerAutoload)) {
    require_once $composerAutoload;
}

spl_autoload_register(function ($className) {
    $paths = [
        APPROOT . '/core/' . $className . '.php',
        APPROOT . '/models/' . $className . '.php',
        APPROOT . '/services/' . $className . '.php',
        APPROOT . '/services/mailers/' . $className . '.php'
    ];
    foreach ($paths as $p) {
        if (file_exists($p)) {
            require_once $p;
            return;
        }
    }
});

// Parse CLI options
$options = getopt('', ['to:', 'dry-run']);
$dryRun = isset($options['dry-run']);
$to = $options['to'] ?? ($_ENV['ADMIN_ALERT_EMAIL'] ?? 'admin@raptor.internal');

// Safe connection setup (connects to MySQL if reachable, or provides memory mock if in dry-run mode on offline system)
try {
    $dbHost = DB_HOST;
    $dbName = DB_NAME;
    $dbUser = DB_USER;
    $dbPass = DB_PASS;
    $pdo = new PDO("mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4", $dbUser, $dbPass, [
        PDO::ATTR_TIMEOUT => 2,
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    Database::setMockConnection($pdo);
} catch (Throwable $e) {
    if ($dryRun) {
        $mockPdo = new PDO('sqlite::memory:');
        $mockPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $mockPdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_OBJ);
        $mockPdo->exec('
            CREATE TABLE IF NOT EXISTS campaign_domains (domain_name TEXT, is_default INT, spf_status TEXT, dkim_status TEXT, dmarc_status TEXT, mx_status TEXT);
            CREATE TABLE IF NOT EXISTS campaign_email_events (event_type TEXT, created_at DATETIME);
            CREATE TABLE IF NOT EXISTS campaign_email_queue (status TEXT);
            CREATE TABLE IF NOT EXISTS campaign_suppressions (email TEXT);
            CREATE TABLE IF NOT EXISTS campaigns_broadcast (sent_count INT, delivered_count INT, bounced_count INT, complained_count INT);
        ');
        Database::setMockConnection($mockPdo);
    } else {
        echo "[ERROR] Database connection failed: " . $e->getMessage() . "\n";
        exit(1);
    }
}

echo "[" . date('Y-m-d H:i:s') . "] Starting Daily Deliverability Health Check...\n";

try {
    $data = DeliverabilityHealthReportService::collectHealthData();

    echo "  - Sent past 24h:       " . $data['metrics_24h']['sent'] . "\n";
    echo "  - Bounce Rate:         " . $data['metrics_24h']['bounce_rate'] . "% (" . strtoupper($data['metrics_24h']['bounce_status']) . ")\n";
    echo "  - Complaint Rate:      " . $data['metrics_24h']['complaint_rate'] . "% (" . strtoupper($data['metrics_24h']['complaint_status']) . ")\n";
    echo "  - Queue Pending:       " . $data['queue']['pending'] . "\n";
    echo "  - Total Suppressions:  " . $data['suppressions_total'] . "\n";

    if (!empty($data['blacklist'])) {
        echo "  - Blacklist Monitor:   " . ($data['blacklist']['is_clean'] ? "CLEAN" : "LISTED (" . $data['blacklist']['listed_count'] . ")") . "\n";
    }

    if ($dryRun) {
        echo "[DRY RUN] Email dispatch skipped. Report generation successful.\n";
        exit(0);
    }

    echo "  - Dispatching report to {$to}...\n";
    $success = DeliverabilityHealthReportService::dispatchReport($to);

    if ($success) {
        echo "[SUCCESS] Daily deliverability health report dispatched successfully.\n";
        exit(0);
    } else {
        echo "[ERROR] Failed to dispatch health report email.\n";
        exit(1);
    }
} catch (Throwable $e) {
    echo "[FATAL ERROR] " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
