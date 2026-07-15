<?php
/**
 * Raptor CRM — Nightly Travel Rollup (Sprint 4)
 * -------------------------------------------------------------------------
 * Computes each user's daily distance + route polyline into travel_summary,
 * then purges raw location points past the retention window.
 *
 * This is the first cron worker; later sprints add cron_reminders.php,
 * cron_scoring.php, etc. Bootstrap pattern (config + autoload) is shared.
 *
 * Usage (server crontab — run a few minutes after midnight):
 *   5 0 * * *  php /var/www/raptor/bin/cron_travel_rollup.php >> /var/log/raptor_cron.log 2>&1
 *
 * Optional arg: a specific date (YYYY-MM-DD). Defaults to yesterday, and also
 * refreshes today so a mid-day manual run stays useful.
 */

require_once dirname(__DIR__) . '/app/config/config.php';

// Minimal autoloader (mirrors public/index.php) so models resolve.
spl_autoload_register(function ($class) {
    foreach ([APPROOT . '/core/', APPROOT . '/models/'] as $dir) {
        if (is_file($dir . $class . '.php')) { require_once $dir . $class . '.php'; return; }
    }
});

$arg = $argv[1] ?? null;
$dates = ($arg && preg_match('/^\d{4}-\d{2}-\d{2}$/', $arg))
    ? [$arg]
    : [date('Y-m-d', strtotime('yesterday')), date('Y-m-d')]; // yesterday + today

$loc = new LocationLog();

$totalUsers = 0;
foreach ($dates as $date) {
    $users = $loc->getUsersWithPointsOn($date);
    foreach ($users as $uid) {
        $km = $loc->rollupDay($uid, $date);
        $totalUsers++;
        echo "[rollup] user $uid on $date => {$km} km\n";
    }
}

$purged = $loc->purgeOldPoints();
echo "[done] rolled up $totalUsers user-days across " . count($dates) . " date(s); purged $purged old points.\n";
