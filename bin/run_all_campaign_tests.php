<?php
/**
 * Raptor CRM - Unified Email Campaigns & Deliverability Master Test Runner
 * 
 * Runs the complete automated test suite across all 7 phases:
 * - Phase 2: Contacts, Lists, Import Validation & Global Suppression
 * - Phase 3: Templates, Merge Tags & Pre-Send Deliverability Gate
 * - Phase 4: Queue Engine, Provider Adapters & Safe Row Locking
 * - Phase 5: Webhooks, Real-Time Suppression, Tracking & Analytics
 * - Phase 6: Domain Authentication, 2048-bit DKIM, DNSBL & Health Reports
 * - Phase 7: End-to-End Campaign Lifecycle Integration
 * - Payroll: Regression Pipeline Verification
 */

declare(strict_types=1);

$startTime = microtime(true);

$suites = [
    'Sprint 1: Customer 360 & Foundations' => 'bin/test_customer_360_sprint1.php',
    'Sprint 2: Lead Ingestion & Tracking'   => 'bin/test_lead_capture_sprint2.php',
    'Sprint 3: Meta WhatsApp Cloud API'     => 'bin/test_whatsapp_sprint3.php',
    'Phase 2: Contacts & Suppression'  => 'bin/test_email_campaigns_phase2.php',
    'Phase 3: Templates & Pre-Send Gate' => 'bin/test_email_campaigns_phase3.php',
    'Phase 4: Queue & Provider Adapters' => 'bin/test_email_campaigns_phase4.php',
    'Phase 5: Webhooks, Tracking & Stats' => 'bin/test_email_campaigns_phase5.php',
    'Phase 6: Domains, DNS & Health Report' => 'bin/test_email_campaigns_phase6.php',
    'Phase 7: End-to-End Lifecycle Suite' => 'bin/test_email_campaigns_e2e.php',
    'Regression: Payroll Calculations'   => 'bin/test_payroll_pipeline.php'
];

echo "====================================================================\n";
echo "  RAPTOR CRM: UNIFIED EMAIL CAMPAIGNS & MASS MAILING TEST RUNNER     \n";
echo "====================================================================\n\n";

$results = [];
$totalPassed = 0;
$totalFailed = 0;

foreach ($suites as $title => $script) {
    echo "Running [{$title}]...\n";
    $output = [];
    $returnVar = 0;
    exec("php " . escapeshellarg($script) . " 2>&1", $output, $returnVar);

    $outputText = implode("\n", $output);
    $passed = 0;
    $failed = 0;

    if (preg_match('/SUMMARY:\s*(\d+)\s*PASSED,\s*(\d+)\s*FAILED/i', $outputText, $m)) {
        $passed = (int)$m[1];
        $failed = (int)$m[2];
    } elseif (preg_match('/All\s*(\d+)\s*assertions\s*PASSED/i', $outputText, $m)) {
        $passed = (int)$m[1];
        $failed = 0;
    }

    $status = ($returnVar === 0 && $failed === 0) ? 'PASS' : 'FAIL';
    $results[] = [
        'title' => $title,
        'status' => $status,
        'passed' => $passed,
        'failed' => $failed,
        'exit_code' => $returnVar
    ];

    $totalPassed += $passed;
    $totalFailed += $failed;

    echo "  Status: [{$status}] - {$passed} passed, {$failed} failed\n\n";
}

$elapsed = round(microtime(true) - $startTime, 2);

echo "====================================================================\n";
echo "  CONSOLIDATED VERIFICATION SCORECARD\n";
echo "====================================================================\n";
printf("%-42s | %-8s | %-7s | %-7s\n", "Test Suite", "Status", "Passed", "Failed");
echo str_repeat("-", 70) . "\n";

foreach ($results as $r) {
    printf("%-42s | %-8s | %-7d | %-7d\n", $r['title'], $r['status'], $r['passed'], $r['failed']);
}
echo str_repeat("=", 70) . "\n";
printf("TOTAL ASSERTIONS: %d PASSED, %d FAILED (Execution Time: %.2fs)\n", $totalPassed, $totalFailed, $elapsed);
echo str_repeat("=", 70) . "\n";

exit($totalFailed > 0 ? 1 : 0);
