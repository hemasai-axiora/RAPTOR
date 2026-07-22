<?php
/**
 * Raptor CRM - Follow-up Reminder Worker (Sprint 6)
 * -------------------------------------------------------------------------
 * Marks overdue follow-ups as missed, notifies owners/leaders, sends due-today
 * reminders, escalates leads not contacted within the configured SLA, and
 * refreshes lead ageing quality.
 *
 * Suggested crontab schedule:
 *   Every 15 minutes: php /var/www/raptor/bin/cron_followups.php >> /var/log/raptor_cron.log 2>&1
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

$followUps = new FollowUp();

$missed = $followUps->markOverdueAndNotify();
$dueToday = $followUps->notifyDueToday();
$sla = $followUps->escalateContactSla();
$reclassified = $followUps->reclassifyLeadAgeing();

echo '[followups] missed=' . $missed
    . ' due_today=' . $dueToday
    . ' sla_escalations=' . $sla
    . ' reclassified=' . $reclassified
    . ' at=' . date('c') . "\n";
