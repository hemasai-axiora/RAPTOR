<?php
/**
 * Raptor CRM — Social Media Scheduled Publishing Background Worker
 * -------------------------------------------------------------------------
 * Processes scheduled social posts due for publishing across LinkedIn,
 * Facebook, Instagram, and X.
 *
 * Usage:
 *   php bin/social_publish_worker.php --once
 *   php bin/social_publish_worker.php (continuous loop)
 */

declare(strict_types=1);

require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/core/Model.php';
require_once __DIR__ . '/../app/services/SocialMediaService.php';

$once = in_array('--once', $argv, true);
$batchSize = 25;

echo "[" . date('Y-m-d H:i:s') . "] Starting Raptor Social Media Publishing Worker...\n";
if ($once) {
    echo "Running in single-pass mode (--once).\n";
} else {
    echo "Running in continuous daemon mode (poll interval: 10s)...\n";
}

$db = Database::getInstance()->getConnection();

do {
    try {
        $now = date('Y-m-d H:i:s');
        $stmt = $db->prepare("SELECT * FROM social_publish_queue 
                              WHERE status = 'pending' AND scheduled_at <= :now 
                              ORDER BY scheduled_at ASC LIMIT :lim");
        $stmt->bindValue(':now', $now);
        $stmt->bindValue(':lim', $batchSize, PDO::PARAM_INT);
        $stmt->execute();
        $items = $stmt->fetchAll(PDO::FETCH_OBJ);

        $processed = 0;
        foreach ($items as $item) {
            $processed++;
            $res = SocialMediaService::publishPost(
                (int)$item->queue_id,
                $item->platform,
                $item->message_text,
                $item->media_url
            );
            echo "[" . date('Y-m-d H:i:s') . "] Published post #{$item->queue_id} on {$item->platform}: {$res['external_post_id']}\n";
        }

        if ($processed > 0) {
            echo "[" . date('Y-m-d H:i:s') . "] Batch complete: {$processed} posts published.\n";
        }
    } catch (Throwable $e) {
        echo "[" . date('Y-m-d H:i:s') . "] ERROR: " . $e->getMessage() . "\n";
    }

    if ($once) {
        break;
    }

    sleep(10);
} while (true);

echo "[" . date('Y-m-d H:i:s') . "] Social publisher worker terminated gracefully.\n";

