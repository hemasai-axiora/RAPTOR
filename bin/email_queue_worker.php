<?php
/**
 * Raptor CRM - High-Throughput Background Email Queue Worker (CLI)
 * 
 * Process queue items without blocking HTTP requests.
 * Suitable for Crontab (* * * * *) or Supervisor daemon execution.
 * 
 * Usage:
 *   php bin/email_queue_worker.php --once
 *   php bin/email_queue_worker.php --daemon --batch=50 --rate=10 --sleep=3
 */

declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    die("This script can only be run from the command line.\n");
}

require_once __DIR__ . '/../app/config/config.php';
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}
require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/core/Model.php';

// Load Campaign models and services
require_once __DIR__ . '/../app/models/CampaignBroadcast.php';
require_once __DIR__ . '/../app/models/CampaignTemplate.php';
require_once __DIR__ . '/../app/models/CampaignSuppression.php';
require_once __DIR__ . '/../app/services/UnsubscribeService.php';
require_once __DIR__ . '/../app/services/WarmupScheduleService.php';
require_once __DIR__ . '/../app/services/EmailService.php';
require_once __DIR__ . '/../app/services/EmailQueueService.php';

// Load Mailers
require_once __DIR__ . '/../app/services/mailers/MailerSendResult.php';
require_once __DIR__ . '/../app/services/mailers/MailerAdapterInterface.php';
require_once __DIR__ . '/../app/services/mailers/SmtpAdapter.php';
require_once __DIR__ . '/../app/services/mailers/SesAdapter.php';
require_once __DIR__ . '/../app/services/mailers/PostmarkAdapter.php';
require_once __DIR__ . '/../app/services/mailers/SendGridAdapter.php';
require_once __DIR__ . '/../app/services/mailers/MailgunAdapter.php';
require_once __DIR__ . '/../app/services/mailers/MockMailerAdapter.php';
require_once __DIR__ . '/../app/services/mailers/MailerFactory.php';

// Parse CLI arguments
$options = getopt('', ['once', 'daemon', 'batch:', 'rate:', 'sleep:', 'help']);

if (isset($options['help'])) {
    echo "Raptor CRM Queue Worker\n";
    echo "Options:\n";
    echo "  --once       Process single batch and exit (standard cron mode)\n";
    echo "  --daemon     Run continuously in loop (Supervisor mode)\n";
    echo "  --batch=N    Items per batch (default: 50)\n";
    echo "  --rate=N     Max emails dispatched per second (default: 5)\n";
    echo "  --sleep=N    Sleep seconds between empty daemon iterations (default: 3)\n";
    exit(0);
}

$isOnce = isset($options['once']) || !isset($options['daemon']);
$batchSize = isset($options['batch']) ? max(1, (int)$options['batch']) : 50;
$rateLimit = isset($options['rate']) ? max(1, (int)$options['rate']) : 5;
$sleepSec = isset($options['sleep']) ? max(1, (int)$options['sleep']) : 3;

$workerId = 'worker_' . getmypid() . '_' . bin2hex(random_bytes(3));
$provider = MailerFactory::getAdapter()->getName();

echo "[" . date('Y-m-d H:i:s') . "] Starting Email Queue Worker [ID: {$workerId}] [Provider: {$provider}]\n";
echo "Config: Batch Size: {$batchSize}, Rate Limit: {$rateLimit}/sec, Mode: " . ($isOnce ? 'Once' : 'Daemon') . "\n";

do {
    try {
        $stats = EmailQueueService::processBatch($batchSize, $rateLimit, $workerId);

        if ($stats['processed'] > 0) {
            echo sprintf(
                "[%s] Batch: Processed: %d, Sent: %d, Failed: %d, Suppressed: %d, Throttled: %d\n",
                date('Y-m-d H:i:s'),
                $stats['processed'],
                $stats['sent'],
                $stats['failed'],
                $stats['suppressed'],
                $stats['throttled']
            );
        }

        gc_collect_cycles();

        if ($isOnce) {
            break;
        }

        // In daemon mode: if no items were processed, sleep before polling again
        if ($stats['processed'] === 0) {
            sleep($sleepSec);
        }
    } catch (Throwable $e) {
        echo "[" . date('Y-m-d H:i:s') . "] WORKER ERROR: " . $e->getMessage() . "\n";
        if ($isOnce) {
            exit(1);
        }
        sleep($sleepSec);
    }
} while (true);

echo "[" . date('Y-m-d H:i:s') . "] Queue Worker finished successfully.\n";
exit(0);

