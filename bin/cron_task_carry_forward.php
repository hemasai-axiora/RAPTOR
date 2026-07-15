<?php
/**
 * Raptor CRM - Task Carry-forward Worker (Sprint 7)
 * -------------------------------------------------------------------------
 * Copies incomplete overdue tasks into the current day, linked by
 * source_task_id, so sales users do not lose unfinished work.
 *
 * Suggested crontab:
 *   10 0 * * * php /var/www/raptor/bin/cron_task_carry_forward.php >> /var/log/raptor_cron.log 2>&1
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

$date = $argv[1] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    fwrite(STDERR, "Usage: php bin/cron_task_carry_forward.php [YYYY-MM-DD]\n");
    exit(1);
}

$task = new Task();
$count = $task->carryForwardIncomplete($date);

echo '[tasks] carried_forward=' . $count . ' date=' . $date . ' at=' . date('c') . "\n";
