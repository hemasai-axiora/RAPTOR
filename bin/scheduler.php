<?php
/**
 * Raptor CRM Unified Cron Scheduler
 * Checks schedules and executes due jobs.
 *
 * Usage:
 *   php bin/scheduler.php [--force] [--dry-run] [--job=<name>]
 */

require_once dirname(__DIR__) . '/app/config/config.php';

// Define the jobs and their cron schedules
$jobs = [
    'cron_alerts' => [
        'schedule' => '*/5 * * * *',
        'file' => 'cron_alerts.php',
        'desc' => 'Evaluate alert rules'
    ],
    'cron_push_notifications' => [
        'schedule' => '*/5 * * * *',
        'file' => 'cron_push_notifications.php',
        'desc' => 'Send push notifications'
    ],
    'cron_followups' => [
        'schedule' => '15 * * * *',
        'file' => 'cron_followups.php',
        'desc' => 'Followups overdue warnings'
    ],
    'cron_travel_rollup' => [
        'schedule' => '30 0 * * *',
        'file' => 'cron_travel_rollup.php',
        'desc' => 'Daily travel summary rollup'
    ],
    'cron_task_carry_forward' => [
        'schedule' => '45 0 * * *',
        'file' => 'cron_task_carry_forward.php',
        'desc' => 'Carry forward pending tasks'
    ],
    'cron_target_progress' => [
        'schedule' => '0 1 * * *',
        'file' => 'cron_target_progress.php',
        'desc' => 'Update target progress stats'
    ],
    'cron_scoring' => [
        'schedule' => '15 1 * * *',
        'file' => 'cron_scoring.php',
        'desc' => 'Recompute performance scores'
    ],
    'cron_report_summaries' => [
        'schedule' => '30 1 * * *',
        'file' => 'cron_report_summaries.php',
        'desc' => 'Generate daily/monthly summaries'
    ],
    'cron_retention' => [
        'schedule' => '0 2 * * *',
        'file' => 'cron_retention.php',
        'desc' => 'Clean old logs & database entries'
    ],
    'cron_invoice_reminders' => [
        'schedule' => '0 9 * * *',
        'file' => 'cron_invoice_reminders.php',
        'desc' => 'Send invoice due reminders daily'
    ],
    'backup_to_s3' => [
        'schedule' => '0 0 * * *',
        'file' => 'backup_to_s3.php',
        'desc' => 'Nightly database and storage S3 backup'
    ]
];

// Parse CLI options
$options = getopt('', ['force', 'dry-run', 'job::']);
$force = isset($options['force']);
$dryRun = isset($options['dry-run']);
$specificJob = $options['job'] ?? null;

$timestamp = time();
$binDir = __DIR__;

echo "=== Raptor CRM Scheduler started at " . date('Y-m-d H:i:s', $timestamp) . " ===\n";

if ($specificJob) {
    if (!isset($jobs[$specificJob])) {
        echo "[ERROR] Job '{$specificJob}' is not defined.\n";
        exit(1);
    }
    runJob($specificJob, $jobs[$specificJob], $binDir, $dryRun);
} else {
    $executed = 0;
    foreach ($jobs as $name => $job) {
        if ($force || shouldRun($job['schedule'], $timestamp)) {
            runJob($name, $job, $binDir, $dryRun);
            $executed++;
        }
    }
    if ($executed === 0) {
        echo "No jobs are due at this time.\n";
    }
}

echo "=== Scheduler completed ===\n";

/**
 * Execute a specific job.
 */
function runJob($name, $job, $binDir, $dryRun) {
    $filePath = $binDir . '/' . $job['file'];
    echo "[JOB] {$name} ({$job['desc']}) - Schedule: {$job['schedule']}\n";
    
    if (!file_exists($filePath)) {
        echo "  [ERROR] File not found: {$filePath}\n";
        return;
    }
    
    if ($dryRun) {
        echo "  [DRY-RUN] Would execute: " . PHP_BINARY . " {$job['file']}\n";
        return;
    }
    
    // Execute job using current running PHP binary
    $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($filePath);
    $output = [];
    $retval = 0;
    
    exec($cmd, $output, $retval);
    
    foreach ($output as $line) {
        echo "  {$line}\n";
    }
    
    if ($retval === 0) {
        echo "  [SUCCESS] Completed.\n";
    } else {
        echo "  [FAILED] Exit code: {$retval}\n";
    }
}

/**
 * Check if a cron expression matches the given timestamp.
 */
function shouldRun($expression, $timestamp) {
    $parts = preg_split('/\s+/', trim($expression));
    if (count($parts) !== 5) return false;
    
    $minute = (int) date('i', $timestamp);
    $hour   = (int) date('G', $timestamp);
    $day    = (int) date('j', $timestamp);
    $month  = (int) date('n', $timestamp);
    $dow    = (int) date('w', $timestamp); // 0 (Sunday) to 6 (Saturday)
    
    return cronMatches($parts[0], $minute) &&
           cronMatches($parts[1], $hour) &&
           cronMatches($parts[2], $day) &&
           cronMatches($parts[3], $month) &&
           cronMatches($parts[4], $dow);
}

function cronMatches($pattern, $currentValue) {
    if ($pattern === '*') return true;
    
    if (strpos($pattern, ',') !== false) {
        foreach (explode(',', $pattern) as $item) {
            if (cronMatches($item, $currentValue)) return true;
        }
        return false;
    }
    
    if (strpos($pattern, '*/') === 0) {
        $step = (int) substr($pattern, 2);
        return $currentValue % $step === 0;
    }
    
    if (strpos($pattern, '-') !== false) {
        list($start, $end) = explode('-', $pattern);
        return $currentValue >= (int)$start && $currentValue <= (int)$end;
    }
    
    return (int)$pattern === (int)$currentValue;
}
