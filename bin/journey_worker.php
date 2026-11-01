<?php
/**
 * Raptor CRM — Marketing Automation Journey Background Worker
 * -------------------------------------------------------------------------
 * Processes due automation journey enrollments, executes multi-channel actions,
 * evaluates delays and stage transitions.
 *
 * Usage:
 *   php bin/journey_worker.php --once
 *   php bin/journey_worker.php (continuous loop)
 */

declare(strict_types=1);

require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/core/Model.php';
require_once __DIR__ . '/../app/models/Contact.php';
require_once __DIR__ . '/../app/models/MarketingJourney.php';
require_once __DIR__ . '/../app/models/JourneyRunner.php';
require_once __DIR__ . '/../app/models/LeadScoringRule.php';
require_once __DIR__ . '/../app/services/WhatsAppService.php';

$once = in_array('--once', $argv, true);
$batchSize = 50;

echo "[" . date('Y-m-d H:i:s') . "] Starting Raptor Marketing Automation Journey Worker...\n";
if ($once) {
    echo "Running in single-pass mode (--once).\n";
} else {
    echo "Running in continuous daemon mode (poll interval: 5s)...\n";
}

$runner = new JourneyRunner();

do {
    try {
        $res = $runner->processPendingEnrollments($batchSize);

        if ($res['processed'] > 0) {
            echo "[" . date('Y-m-d H:i:s') . "] Batch complete: {$res['processed']} processed, {$res['completed']} completed, {$res['delayed']} delayed, {$res['suppressed_cancelled']} suppressed/cancelled.\n";
        }
    } catch (Throwable $e) {
        echo "[" . date('Y-m-d H:i:s') . "] ERROR: " . $e->getMessage() . "\n";
    }

    if ($once) {
        break;
    }

    sleep(5);
} while (true);

echo "[" . date('Y-m-d H:i:s') . "] Journey worker terminated gracefully.\n";

