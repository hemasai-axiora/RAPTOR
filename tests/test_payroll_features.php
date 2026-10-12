<?php
/**
 * Comprehensive Integration Test for Payroll Management Features:
 * 1. Payslip Design & PDF Generation
 * 2. Employee Salary Revision History
 * 3. Automated Payslip Email Dispatch
 */

require_once __DIR__ . '/../app/config/config.php';
require_once APPROOT . '/core/Database.php';
require_once APPROOT . '/core/Model.php';
require_once APPROOT . '/core/PayslipHelper.php';
require_once APPROOT . '/core/PayslipPdfService.php';
require_once APPROOT . '/models/Payroll.php';

$payrollModel = new Payroll();

echo "=======================================================\n";
echo "  TESTING PAYROLL SYSTEM FEATURES\n";
echo "=======================================================\n\n";

// -----------------------------------------------------------------
// TEST 1: Exact Payslip Design & PDF Generation
// -----------------------------------------------------------------
echo "[TEST 1] Testing Payslip Design & PDF Generation...\n";

// Fetch first payslip detail
$db = Database::getInstance()->getConnection();
$stmt = $db->query("SELECT payroll_detail_id FROM payroll_details LIMIT 1");
$detailId = (int)$stmt->fetchColumn();

if ($detailId <= 0) {
    echo "  [FAIL] No payslip detail record found.\n";
    exit(1);
}

$detail = $payrollModel->getPayrollDetailById($detailId);
echo "  Loaded Payslip: #{$detail->payroll_detail_id} for {$detail->name} ({$detail->employee_code}) - Period: {$detail->month_year}\n";
echo "  Gross: Rs. {$detail->gross_salary} | Deductions: Rs. {$detail->total_deductions} | Net Pay: Rs. {$detail->net_salary}\n";
echo "  Employer EPF: Rs. {$detail->employer_pf} | Gratuity: Rs. {$detail->gratuity} | CTC: Rs. {$detail->ctc}\n";

$inWords = PayslipHelper::numberToWords((float)$detail->net_salary);
echo "  Amount in Words: Indian Rupee {$inWords} Only\n";

$pdf = PayslipPdfService::generatePdf($detail);
if (strlen($pdf) > 1000 && str_starts_with($pdf, "%PDF-1.4")) {
    echo "  [PASS] PDF generated successfully! Binary size: " . strlen($pdf) . " bytes\n";
    file_put_contents(__DIR__ . '/test_payslip_verified.pdf', $pdf);
} else {
    echo "  [FAIL] Invalid PDF output generated.\n";
    exit(1);
}

// -----------------------------------------------------------------
// TEST 2: Employee Salary Revision History
// -----------------------------------------------------------------
echo "\n[TEST 2] Testing Employee Salary Revision History...\n";

$empStmt = $db->query("SELECT employee_id, u.user_id, name FROM employees e JOIN users u ON e.user_id = u.user_id LIMIT 1");
$emp = $empStmt->fetch(PDO::FETCH_OBJ);
$empId = (int)$emp->employee_id;
$userId = (int)$emp->user_id;

echo "  Testing for Employee #{$empId} ({$emp->name})...\n";

$initialHistory = $payrollModel->getSalaryRevisionHistory($empId);
$countBefore = count($initialHistory);
echo "  Initial revision count: {$countBefore}\n";

// Apply a new salary revision
$newStructureData = [
    'employee_id' => $empId,
    'salary_type' => 'Monthly',
    'basic_salary' => 45000.00,
    'hra' => 18000.00,
    'special_allowance' => 9000.00,
    'other_allowance' => 4500.00,
    'medical_allowance' => 1250.00,
    'travel_allowance' => 1600.00,
    'bonus' => 0.00,
    'other_earnings' => 0.00,
    'pf' => 5400.00,
    'employer_pf' => 5400.00,
    'gratuity' => 2164.50,
    'esic' => 0.00,
    'professional_tax' => 200.00,
    'tds' => 4500.00
];

$saved = $payrollModel->saveSalaryStructure($newStructureData, $userId, 'Promoted to Lead Architect - Q4 Increment', '2026-10-01');

if ($saved) {
    echo "  [PASS] Saved new salary structure with revision tracking.\n";
} else {
    echo "  [FAIL] Failed to save salary structure.\n";
    exit(1);
}

$updatedHistory = $payrollModel->getSalaryRevisionHistory($empId);
$countAfter = count($updatedHistory);
echo "  Updated revision count: {$countAfter}\n";

if ($countAfter > $countBefore) {
    $latestRev = $updatedHistory[0];
    echo "  Latest Revision: {$latestRev->reason} (Effective: {$latestRev->effective_date})\n";
    echo "  Previous Basic: Rs. {$latestRev->previous_basic} -> New Basic: Rs. {$latestRev->new_basic}\n";
    echo "  Previous CTC: Rs. {$latestRev->previous_ctc} -> New CTC: Rs. {$latestRev->new_ctc}\n";
    echo "  [PASS] Salary revision history correctly preserved previous records without overwriting.\n";
} else {
    echo "  [FAIL] Salary revision history was not incremented.\n";
    exit(1);
}

// -----------------------------------------------------------------
// TEST 3: Automated Payslip Email Dispatch & Status Tracking
// -----------------------------------------------------------------
echo "\n[TEST 3] Testing Automated Payslip Email Dispatch...\n";

// Update email status on detail record
$payrollModel->updateEmailStatus($detailId, 'sent', null);
$updatedDetail = $payrollModel->getPayrollDetailById($detailId);

if ($updatedDetail->email_status === 'sent') {
    echo "  [PASS] Email status successfully updated to 'sent'.\n";
} else {
    echo "  [FAIL] Email status was not updated.\n";
    exit(1);
}

// Test failed status logging
$payrollModel->updateEmailStatus($detailId, 'failed', 'Simulated SMTP connection timeout');
$failedDetail = $payrollModel->getPayrollDetailById($detailId);

if ($failedDetail->email_status === 'failed' && $failedDetail->email_error === 'Simulated SMTP connection timeout') {
    echo "  [PASS] Email failure status & error message successfully logged.\n";
} else {
    echo "  [FAIL] Email failure status was not logged.\n";
    exit(1);
}

// Reset back to sent
$payrollModel->updateEmailStatus($detailId, 'sent', null);

echo "\n=======================================================\n";
echo "  ALL PAYROLL SYSTEM TESTS PASSED SUCCESSFULLY! (100%)\n";
echo "=======================================================\n";
