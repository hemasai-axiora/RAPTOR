<?php
/**
 * Sprint 12 cron: generate daily, weekly, and monthly report summaries.
 *
 * Usage:
 *   php bin/cron_report_summaries.php
 */

require_once dirname(__DIR__) . '/app/config/config.php';
require_once APPROOT . '/core/Database.php';
require_once APPROOT . '/core/Model.php';
require_once APPROOT . '/models/ReportSuite.php';

$reports = new ReportSuite();
$jobs = [];

$yesterday = date('Y-m-d', strtotime('-1 day'));
$jobs[] = ['daily', $yesterday, $yesterday, 'daily_summary'];

if (date('N') === '1') {
    $jobs[] = ['weekly', date('Y-m-d', strtotime('last monday -7 days')), date('Y-m-d', strtotime('last sunday')), 'daily_summary'];
}

if (date('j') === '1') {
    $jobs[] = ['monthly', date('Y-m-01', strtotime('first day of last month')), date('Y-m-t', strtotime('last month')), 'monthly_summary'];
}

foreach ($jobs as [$period, $from, $to, $key]) {
    $result = $reports->run($key, ['from' => $from, 'to' => $to], null);
    $reports->saveSummary($key, $period, $from, $to, $result['rows'], null);
    echo "[OK] {$period} {$key} {$from}..{$to} saved\n";

    $recipients = $reports->digestRecipients();
    if ($period === 'monthly' && $recipients) {
        $body = $result['title'] . " ({$from} to {$to})\n\n";
        foreach ($result['rows'] as $row) {
            $body .= $row['metric'] . ': ' . $row['value'] . "\n";
        }

        foreach ($recipients as $email) {
            @mail($email, 'Raptor monthly sales summary', $body);
        }
        $reports->markEmailed($key, $period, $from, $to);
        echo "[OK] monthly digest attempted for " . count($recipients) . " recipient(s)\n";
    }
}
