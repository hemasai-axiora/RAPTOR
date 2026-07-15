<?php
/**
 * Raptor CRM - Performance Scoring Worker (Sprint 10)
 * -------------------------------------------------------------------------
 * Recomputes rolling weekly and monthly scores, bands, and team ranks.
 *
 * Suggested crontab:
 *   20 0 * * * php /var/www/raptor/bin/cron_scoring.php >> /var/log/raptor_cron.log 2>&1
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
$targets = $target->recomputeAll();

$perf = new Performance();
$weekly = $perf->recompute('weekly');
$monthly = $perf->recompute('monthly');

echo '[scoring] targets=' . $targets . ' weekly=' . $weekly . ' monthly=' . $monthly . ' at=' . date('c') . "\n";
