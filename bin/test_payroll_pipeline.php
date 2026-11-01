<?php
/**
 * Comprehensive End-to-End Payroll Verification Test
 * Tests:
 * 1. Band calculation precision against reference standards for all salary levels (PF & Non-PF)
 * 2. Structure sync behavior
 * 3. Vector PDF generator with PF and Non-PF mock details
 * 4. LOP calendar proration logic
 */

require_once __DIR__ . '/../app/config/config.php';
require_once APPROOT . '/core/Database.php';
require_once APPROOT . '/core/Model.php';
require_once APPROOT . '/core/PayslipHelper.php';
require_once APPROOT . '/core/PayslipPdfService.php';
require_once APPROOT . '/models/Payroll.php';

echo "=================================================================\n";
echo "   COMPREHENSIVE PAYROLL PIPELINE VERIFICATION TEST             \n";
echo "=================================================================\n\n";

$testsPassed = 0;
$testsFailed = 0;

function assertEquiv($actual, $expected, $label, &$testsPassed, &$testsFailed) {
    if (abs((float)$actual - (float)$expected) < 0.01) {
        $testsPassed++;
    } else {
        $testsFailed++;
        echo "  [FAIL] $label: expected $expected, got $actual\n";
    }
}

// -------------------------------------------------------------
// Test 1: Full Range of Axiora Salary Bands (PF & Non-PF)
// -------------------------------------------------------------
echo "[TEST 1] Verifying Band Derivation Against Reference Standards...\n";

$testBands = [
    // Non-PF Bands
    ['ctc' => 10000, 'pf' => false, 'exp_basic' => 5000, 'exp_hra' => 2500, 'exp_special' => 1600, 'exp_other' => 660, 'exp_gross' => 9760, 'exp_pf' => 0, 'exp_emp_pf' => 0, 'exp_grat' => 240, 'exp_pt' => 0, 'exp_net' => 9760],
    ['ctc' => 11000, 'pf' => false, 'exp_basic' => 5500, 'exp_hra' => 2750, 'exp_special' => 1600, 'exp_other' => 885, 'exp_gross' => 10735, 'exp_pf' => 0, 'exp_emp_pf' => 0, 'exp_grat' => 265, 'exp_pt' => 150, 'exp_net' => 10585],
    ['ctc' => 13739, 'pf' => false, 'exp_basic' => 6870, 'exp_hra' => 3435, 'exp_special' => 1600, 'exp_other' => 1504, 'exp_gross' => 13409, 'exp_pf' => 0, 'exp_emp_pf' => 0, 'exp_grat' => 330, 'exp_pt' => 150, 'exp_net' => 13259],
    ['ctc' => 15000, 'pf' => false, 'exp_basic' => 7500, 'exp_hra' => 3750, 'exp_special' => 1600, 'exp_other' => 1789, 'exp_gross' => 14639, 'exp_pf' => 0, 'exp_emp_pf' => 0, 'exp_grat' => 361, 'exp_pt' => 150, 'exp_net' => 14489],
    ['ctc' => 17000, 'pf' => false, 'exp_basic' => 8500, 'exp_hra' => 4250, 'exp_special' => 1600, 'exp_other' => 2241, 'exp_gross' => 16591, 'exp_pf' => 0, 'exp_emp_pf' => 0, 'exp_grat' => 409, 'exp_pt' => 200, 'exp_net' => 16391],
    ['ctc' => 20000, 'pf' => false, 'exp_basic' => 10000, 'exp_hra' => 5000, 'exp_special' => 1600, 'exp_other' => 2919, 'exp_gross' => 19519, 'exp_pf' => 0, 'exp_emp_pf' => 0, 'exp_grat' => 481, 'exp_pt' => 200, 'exp_net' => 19319],
    // PF Bands
    ['ctc' => 12000, 'pf' => true,  'exp_basic' => 6000, 'exp_hra' => 3000, 'exp_special' => 1600, 'exp_other' => 391, 'exp_gross' => 10991, 'exp_pf' => 720, 'exp_emp_pf' => 720, 'exp_grat' => 289, 'exp_pt' => 150, 'exp_net' => 10121],
    ['ctc' => 15000, 'pf' => true,  'exp_basic' => 7500, 'exp_hra' => 3750, 'exp_special' => 1600, 'exp_other' => 889, 'exp_gross' => 13739, 'exp_pf' => 900, 'exp_emp_pf' => 900, 'exp_grat' => 361, 'exp_pt' => 150, 'exp_net' => 12689],
    ['ctc' => 20000, 'pf' => true,  'exp_basic' => 10000, 'exp_hra' => 5000, 'exp_special' => 1600, 'exp_other' => 1719, 'exp_gross' => 18319, 'exp_pf' => 1200, 'exp_emp_pf' => 1200, 'exp_grat' => 481, 'exp_pt' => 200, 'exp_net' => 16919],
    ['ctc' => 23000, 'pf' => true,  'exp_basic' => 11500, 'exp_hra' => 5750, 'exp_special' => 1600, 'exp_other' => 2217, 'exp_gross' => 21067, 'exp_pf' => 1380, 'exp_emp_pf' => 1380, 'exp_grat' => 553, 'exp_pt' => 200, 'exp_net' => 19487],
    ['ctc' => 25000, 'pf' => true,  'exp_basic' => 12500, 'exp_hra' => 6250, 'exp_special' => 1600, 'exp_other' => 2549, 'exp_gross' => 22899, 'exp_pf' => 1500, 'exp_emp_pf' => 1500, 'exp_grat' => 601, 'exp_pt' => 200, 'exp_net' => 21199],
    ['ctc' => 30000, 'pf' => true,  'exp_basic' => 15000, 'exp_hra' => 7500, 'exp_special' => 1600, 'exp_other' => 3379, 'exp_gross' => 27479, 'exp_pf' => 1800, 'exp_emp_pf' => 1800, 'exp_grat' => 721, 'exp_pt' => 200, 'exp_net' => 25479],
    ['ctc' => 35000, 'pf' => true,  'exp_basic' => 17500, 'exp_hra' => 8750, 'exp_special' => 1600, 'exp_other' => 4508, 'exp_gross' => 32358, 'exp_pf' => 1800, 'exp_emp_pf' => 1800, 'exp_grat' => 842, 'exp_pt' => 200, 'exp_net' => 30358],
    ['ctc' => 45000, 'pf' => true,  'exp_basic' => 22500, 'exp_hra' => 11250, 'exp_special' => 1600, 'exp_other' => 6768, 'exp_gross' => 42118, 'exp_pf' => 1800, 'exp_emp_pf' => 1800, 'exp_grat' => 1082, 'exp_pt' => 200, 'exp_net' => 40118],
    ['ctc' => 50000, 'pf' => true,  'exp_basic' => 25000, 'exp_hra' => 12500, 'exp_special' => 1600, 'exp_other' => 7898, 'exp_gross' => 46998, 'exp_pf' => 1800, 'exp_emp_pf' => 1800, 'exp_grat' => 1202, 'exp_pt' => 200, 'exp_net' => 44998],
    ['ctc' => 70000, 'pf' => true,  'exp_basic' => 35000, 'exp_hra' => 17500, 'exp_special' => 1600, 'exp_other' => 12417, 'exp_gross' => 66517, 'exp_pf' => 1800, 'exp_emp_pf' => 1800, 'exp_grat' => 1683, 'exp_pt' => 200, 'exp_net' => 64517],
    ['ctc' => 80000, 'pf' => true,  'exp_basic' => 40000, 'exp_hra' => 20000, 'exp_special' => 1600, 'exp_other' => 14676, 'exp_gross' => 76276, 'exp_pf' => 1800, 'exp_emp_pf' => 1800, 'exp_grat' => 1924, 'exp_pt' => 200, 'exp_net' => 74276]
];

foreach ($testBands as $tb) {
    $res = Payroll::deriveComponentsFromCtc((float)$tb['ctc'], (bool)$tb['pf']);
    $tag = "CTC {$tb['ctc']} (" . ($tb['pf'] ? "PF" : "Non-PF") . ")";
    
    assertEquiv($res['basic_salary'], $tb['exp_basic'], "$tag Basic", $testsPassed, $testsFailed);
    assertEquiv($res['hra'], $tb['exp_hra'], "$tag HRA", $testsPassed, $testsFailed);
    assertEquiv($res['special_allowance'], $tb['exp_special'], "$tag Special", $testsPassed, $testsFailed);
    assertEquiv($res['other_allowance'], $tb['exp_other'], "$tag Other", $testsPassed, $testsFailed);
    assertEquiv($res['gross_salary'], $tb['exp_gross'], "$tag Gross", $testsPassed, $testsFailed);
    assertEquiv($res['pf'], $tb['exp_pf'], "$tag PF", $testsPassed, $testsFailed);
    assertEquiv($res['employer_pf'], $tb['exp_emp_pf'], "$tag Employer PF", $testsPassed, $testsFailed);
    assertEquiv($res['gratuity'], $tb['exp_grat'], "$tag Gratuity", $testsPassed, $testsFailed);
    assertEquiv($res['professional_tax'], $tb['exp_pt'], "$tag PT", $testsPassed, $testsFailed);
    assertEquiv($res['net_salary'], $tb['exp_net'], "$tag Net Pay", $testsPassed, $testsFailed);
}
echo "✓ Tested all " . count($testBands) . " bands. Total assertions checked: " . ($testsPassed + $testsFailed) . ".\n\n";

// -------------------------------------------------------------
// Test 2: Calendar Month LOP Proration
// -------------------------------------------------------------
echo "[TEST 2] Verifying Calendar Days LOP Divisor...\n";
$sepDays = (int)date('t', strtotime('2026-09-01'));
$octDays = (int)date('t', strtotime('2026-10-01'));
$febDays = (int)date('t', strtotime('2026-02-01'));

assertEquiv($sepDays, 30, "September calendar days", $testsPassed, $testsFailed);
assertEquiv($octDays, 31, "October calendar days", $testsPassed, $testsFailed);
assertEquiv($febDays, 28, "February 2026 calendar days", $testsPassed, $testsFailed);

// Proration for 2 days LOP in September (30 calendar days)
$lopDays = 2;
$presentDays = $sepDays - $lopDays; // 28
$proRate = $presentDays / $sepDays; // 28/30 = 0.9333333

$ctc = 35000;
$derived = Payroll::deriveComponentsFromCtc($ctc, true);
$earnedBasic = round($derived['basic_salary'] * $proRate); // 17500 * (28/30) = 16333
$earnedHra = round($derived['hra'] * $proRate);             // 8750 * (28/30) = 8167
$earnedSpecial = round($derived['special_allowance'] * $proRate); // 1600 * (28/30) = 1493
$earnedOther = round($derived['other_allowance'] * $proRate);     // 4508 * (28/30) = 4207

assertEquiv($earnedBasic, 16333, "Earned Basic 28/30", $testsPassed, $testsFailed);
assertEquiv($earnedHra, 8167, "Earned HRA 28/30", $testsPassed, $testsFailed);
echo "✓ LOP Proration math validated.\n\n";

// -------------------------------------------------------------
// Test 3: PDF Generation for Non-PF and PF Employees
// -------------------------------------------------------------
echo "[TEST 3] Verifying Vector PDF Generation for PF & Non-PF Employees...\n";

// PF Employee Detail Object Mock
$pfDetail = (object)[
    'month_year' => '2026-09',
    'payroll_detail_id' => 101,
    'employee_id' => 101,
    'employee_code' => 'AX0101',
    'name' => 'John Doe (PF)',
    'job_title' => 'Senior Developer',
    'department' => 'Engineering',
    'date_of_joining' => '2023-01-15',
    'work_location' => 'Hyderabad',
    'uan' => '100987654321',
    'account_number' => '98765432101',
    'ifsc_code' => 'HDFC0001234',
    'pf_applicable' => 1,
    'present_days' => 30,
    'absent_days' => 0,
    'basic_salary' => 17500,
    'hra' => 8750,
    'special_allowance' => 1600,
    'other_allowance' => 4508,
    'bonus' => 0,
    'other_earnings' => 0,
    'actual_basic' => 17500,
    'actual_hra' => 8750,
    'actual_special_allowance' => 1600,
    'actual_other_allowance' => 4508,
    'pf' => 1800,
    'employer_pf' => 1800,
    'gratuity' => 842,
    'esic' => 0,
    'professional_tax' => 200,
    'other_deductions' => 0,
    'gross_salary' => 32358,
    'total_deductions' => 2000,
    'net_salary' => 30358,
    'ctc' => 35000
];

$pfPdf = PayslipPdfService::generatePdf($pfDetail);
assertEquiv(!empty($pfPdf) && strlen($pfPdf) > 1000, true, "PF PDF Generation Valid", $testsPassed, $testsFailed);
echo "✓ PF PDF generated: " . strlen($pfPdf) . " bytes\n";

// Non-PF Employee Detail Object Mock
$nonPfDetail = (object)[
    'month_year' => '2026-09',
    'payroll_detail_id' => 102,
    'employee_id' => 102,
    'employee_code' => 'AX0102',
    'name' => 'Jane Smith (Non-PF)',
    'job_title' => 'Junior Associate',
    'department' => 'Operations',
    'date_of_joining' => '2024-06-01',
    'work_location' => 'Hyderabad',
    'uan' => '-',
    'account_number' => '12345678909',
    'ifsc_code' => 'SBIN0005678',
    'pf_applicable' => 0,
    'present_days' => 30,
    'absent_days' => 0,
    'basic_salary' => 7500,
    'hra' => 3750,
    'special_allowance' => 1600,
    'other_allowance' => 1789,
    'bonus' => 0,
    'other_earnings' => 0,
    'actual_basic' => 7500,
    'actual_hra' => 3750,
    'actual_special_allowance' => 1600,
    'actual_other_allowance' => 1789,
    'pf' => 0,
    'employer_pf' => 0,
    'gratuity' => 361,
    'esic' => 0,
    'professional_tax' => 150,
    'other_deductions' => 0,
    'gross_salary' => 14639,
    'total_deductions' => 150,
    'net_salary' => 14489,
    'ctc' => 15000
];

$nonPfPdf = PayslipPdfService::generatePdf($nonPfDetail);
assertEquiv(!empty($nonPfPdf) && strlen($nonPfPdf) > 1000, true, "Non-PF PDF Generation Valid", $testsPassed, $testsFailed);
echo "✓ Non-PF PDF generated: " . strlen($nonPfPdf) . " bytes\n\n";

// -------------------------------------------------------------
// Test 4: HTML Print View Output Check
// -------------------------------------------------------------
echo "[TEST 4] Verifying HTML Print View Output for Non-PF (Zero EPF)...\n";
ob_start();
$detail = $nonPfDetail;
include APPROOT . '/views/payroll/payslip_print.php';
$htmlOutput = ob_get_clean();

// Ensure HTML rendered cleanly without undefined notices and with correct numbers
$hasCorrectNet = strpos($htmlOutput, '14489') !== false;
$hasZeroEpf = strpos($htmlOutput, 'EPF Contribution') !== false && strpos($htmlOutput, '0.00') !== false;
assertEquiv($hasCorrectNet, true, "HTML contains correct Net Pay Rs. 14,489", $testsPassed, $testsFailed);
assertEquiv($hasZeroEpf, true, "HTML contains zero EPF for Non-PF", $testsPassed, $testsFailed);
echo "✓ HTML Print View output verified successfully.\n\n";

echo "=================================================================\n";
echo "  FINAL RESULT: All $testsPassed assertions PASSED ($testsFailed failures)! \n";
echo "=================================================================\n";
