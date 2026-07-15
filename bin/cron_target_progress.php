<?php
/**
 * Raptor CRM - Target Progress Worker (Sprint 9)
 * -------------------------------------------------------------------------
 * Recomputes achieved values for approved targets from communications,
 * meetings/demos, leads, revenue, and approved tasks.
 *
 * Suggested crontab:
 *   */30 * * * * php /var/www/raptor/bin/cron_target_progress.php >> /var/log/raptor_cron.log 2>&1
 */

require_once dirname(__DIR__) . '/app/config/config.php';

spl_autoload_register(function ($class) {
    foreach ([APPROOT . '/core/', APPROOT . '/models/'] as $dir) {
        if (is_file($dir . $class . '.php')) {
            require_once $dir . $class . '.php';
            return;
        }
    }
});

$target = new Target();
$count = $target->recomputeAll();

echo '[targets] recomputed=' . $count . ' at=' . date('c') . "\n";
