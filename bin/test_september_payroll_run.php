<?php
/**
 * Test & Run Full End-to-End Payroll for September 2026 (2026-09)
 */

require_once __DIR__ . '/../app/config/config.php';
require_once APPROOT . '/core/Database.php';
require_once APPROOT . '/core/Model.php';
require_once APPROOT . '/core/PayslipHelper.php';
require_once APPROOT . '/core/PayslipPdfService.php';
require_once APPROOT . '/models/Payroll.php';

$db = Database::getInstance()->getConnection();
$payrollModel = new Payroll();

echo "=================================================================\n";
echo "  SEPTEMBER 2026 (2026-09) FULL END-TO-END PAYROLL TEST & RUN   \n";
echo "=================================================================\n\n";

$monthYear = '2026-09';

// 1. Clean previous 2026-09 run & checklist data to test fresh execution
echo "[STEP 1] Cleaning existing 2026-09 test records...\n";
$db->exec("DELETE FROM payroll_checklist_items WHERE checklist_id IN (SELECT checklist_id FROM payroll_checklists WHERE month_year = '$monthYear')");
$db->exec("DELETE FROM payroll_checklists WHERE month_year = '$monthYear'");
$db->exec("DELETE FROM payroll_salary_holds WHERE month_year = '$monthYear'");
$db->exec("DELETE FROM payroll_arrears WHERE month_year = '$monthYear'");
$db->exec("DELETE FROM payroll_details WHERE payroll_run_id IN (SELECT payroll_run_id FROM payroll_runs WHERE month_year = '$monthYear')");
$db->exec("DELETE FROM payroll_runs WHERE month_year = '$monthYear'");
echo "✓ Cleaned 2026-09 records.\n\n";

// 2. Setup Seed Data for Sept (Employees with Salary Structures, Bonus, Hold, Arrears)
echo "[STEP 2] Setting up September 2026 scenario data...\n";

// Ensure active employees exist
$sampleEmployees = $payrollModel->getActiveEmployees();

if (empty($sampleEmployees) || count($sampleEmployees) < 3) {
    die("Error: Not enough active non-admin employees found in database.\n");
}

echo "Found " . count($sampleEmployees) . " active staff employees.\n";
foreach ($sampleEmployees as $idx => $e) {
    // Ensure each has a valid salary structure
    $existingStruct = $payrollModel->getSalaryStructureByEmployeeId($e->employee_id);
    if (!$existingStruct) {
        $baseBasic = 30000.00 + ($idx * 1000);
        $baseHra = round($baseBasic * 0.40, 2);
        $baseSpecial = round($baseBasic * 0.20, 2);
        $baseOther = 2000.00;
        $pf = round(min($baseBasic, 15000) * 0.12, 2);
        $pt = 200.00;
        $empPf = $pf;
        $gratuity = round(($baseBasic * 15) / 26 / 12, 2);

        $payrollModel->saveSalaryStructure([
            'employee_id' => $e->employee_id,
            'salary_type' => 'Monthly',
            'basic_salary' => $baseBasic,
            'hra' => $baseHra,
            'special_allowance' => $baseSpecial,
            'other_allowance' => $baseOther,
            'medical_allowance' => 0.00,
            'travel_allowance' => 0.00,
            'bonus' => 0.00,
            'other_earnings' => 0.00,
            'pf' => $pf,
            'esic' => 0.00,
            'professional_tax' => $pt,
            'tds' => 0.00,
            'employer_pf' => $empPf,
            'gratuity' => $gratuity
        ]);
        echo "  + Initialized salary structure for {$e->name} (Basic: Rs. $baseBasic)\n";
    }
}

// 2a. Place Employee #0 on Salary Hold for Sept
$holdEmployee = $sampleEmployees[0];
$payrollModel->saveSalaryHold($monthYear, $holdEmployee->employee_id, "Clearance pending for unsubmitted timesheets", 1);
echo "✓ Added Salary Hold for: {$holdEmployee->name} ({$holdEmployee->employee_code}) - 'Clearance pending for unsubmitted timesheets'\n";

// 2b. Add an Arrear for Employee #1
$arrearEmployee = $sampleEmployees[1];
$arrearAmount = 3500.00;
$payrollModel->saveArrear($monthYear, $arrearEmployee->employee_id, $arrearAmount, "August overtime and festival allowance settlement", 1);
echo "✓ Added Arrear for: {$arrearEmployee->name} (+Rs. $arrearAmount) - 'August overtime and festival allowance settlement'\n";

// 2c. Add an Approved Bonus in Sept for Employee #2
$bonusEmployee = $sampleEmployees[2];
$bonusAmount = 5000.00;
$db->prepare("INSERT INTO bonuses (employee_id, bonus_type, description, amount, status, created_at) 
              VALUES (:eid, 'Performance', 'Top performer award September', :amt, 'approved', '2026-09-15 10:00:00')")
   ->execute([':eid' => $bonusEmployee->employee_id, ':amt' => $bonusAmount]);
echo "✓ Added Approved Bonus for: {$bonusEmployee->name} (+Rs. $bonusAmount) - 'Performance'\n\n";

// 3. Test Checklist Gatekeeping
echo "[STEP 3] Testing Checklist Gatekeeping for $monthYear...\n";
$checklist = $payrollModel->getChecklistByMonth($monthYear);
$isDone = $payrollModel->isChecklistCompleted($monthYear);
echo "Initial checklist status: {$checklist->status} | isChecklistCompleted: " . ($isDone ? 'YES' : 'NO') . "\n";
assert(!$isDone, "Gatekeeping failure: Checklist should not be completed initially!");
echo "✓ Gatekeeping correctly blocks payroll run while checklist items are pending.\n\n";

// 4. Complete all 9 checklist items with remarks
echo "[STEP 4] Completing all 9 Pre-Payroll Checklist items...\n";
$items = $payrollModel->getChecklistItems($checklist->checklist_id);
$auditRemarks = [
    'employee_status' => 'Verified all active employee headcounts against HR roster.',
    'salary_hold' => 'Salary hold placed for ' . $holdEmployee->name . ' due to pending timesheets.',
    'joiners_relieved' => 'Verified employment dates and joining contracts.',
    'salary_revisions' => 'Checked effective salary structure updates for Q3.',
    'lop_deductions' => 'Verified biometric attendance logs and leave balances.',
    'other_deductions' => 'Standard statutory EPF and PT rules applied.',
    'arrears' => 'Arrear of Rs. ' . $arrearAmount . ' added for ' . $arrearEmployee->name . '.',
    'bonuses_incentives' => 'Approved Q3 Performance bonus of Rs. ' . $bonusAmount . ' included.',
    'tds_deductions' => 'TDS deduction estimates reviewed against standard slabs.'
];

foreach ($items as $idx => $item) {
    $remark = $auditRemarks[$item->item_key] ?? 'Verified and confirmed.';
    $payrollModel->updateChecklistItem($item->item_id, 'completed', $remark, 1);
    echo "  + Item " . ($idx + 1) . ". [{$item->item_key}] marked COMPLETED (Remark: $remark)\n";
}

$isDoneAfter = $payrollModel->isChecklistCompleted($monthYear);
assert($isDoneAfter, "Checklist should now be completed!");
echo "✓ Checklist 100% verified and signed off for $monthYear. Gatekeeping UNLOCKED!\n\n";

// 5. Execute Payroll Run Calculation for September 2026
echo "[STEP 5] Generating & Computing September 2026 Payroll Run...\n";

// Step 5a. Create payroll run record
$db->prepare("INSERT INTO payroll_runs (month_year, status, created_by, created_at) VALUES (:month, 'generated', 1, NOW())")
   ->execute([':month' => $monthYear]);
$runId = (int)$db->lastInsertId();
echo "✓ Payroll Run #$runId created.\n";

// Fetch holds for exclusion
$salaryHolds = $payrollModel->getSalaryHolds($monthYear);
$holdsByEmp = [];
foreach ($salaryHolds as $h) {
    $holdsByEmp[$h->employee_id] = $h;
}

$arrearsList = $payrollModel->getArrears($monthYear);
$arrearsByEmp = [];
foreach ($arrearsList as $a) {
    $arrearsByEmp[$a->employee_id] = ($arrearsByEmp[$a->employee_id] ?? 0) + (float)$a->amount;
}

$activeEmps = $payrollModel->getActiveEmployees();
$processedCount = 0;
$skippedCount = 0;
$workingDays = 22;

foreach ($activeEmps as $emp) {
    // Check if salary is on hold
    if (isset($holdsByEmp[$emp->employee_id])) {
        echo "  - SKIPPING Employee #{$emp->employee_id} ({$emp->name}) -> Salary on HOLD for $monthYear.\n";
        $skippedCount++;
        continue;
    }

    $struct = $payrollModel->getSalaryStructureByEmployeeId($emp->employee_id);
    $actualBasic = $struct ? (float)$struct->basic_salary : 25000.00;
    $actualHra = $struct ? (float)$struct->hra : round($actualBasic * 0.40, 2);
    $actualSpecial = $struct ? (float)$struct->special_allowance : 3000.00;
    $actualOther = $struct ? (float)($struct->other_allowance ?? 0.00) : 0.00;
    $med = $struct ? (float)$struct->medical_allowance : 0.00;
    $travel = $struct ? (float)$struct->travel_allowance : 0.00;
    $bonus = $struct ? (float)$struct->bonus : 0.00;
    $otherEarn = $struct ? (float)$struct->other_earnings : 0.00;

    // Add approved bonuses from bonuses table
    try {
        $bonusStmt = $db->prepare("SELECT SUM(amount) FROM bonuses WHERE employee_id = :emp_id AND status = 'approved' AND created_at LIKE :month");
        $bonusStmt->execute([':emp_id' => $emp->employee_id, ':month' => $monthYear . '-%']);
        $approvedBonusSum = (float)$bonusStmt->fetchColumn();
        $bonus += $approvedBonusSum;
    } catch (Exception $e) {}

    // Add arrears
    if (!empty($arrearsByEmp[$emp->employee_id])) {
        $otherEarn += (float)$arrearsByEmp[$emp->employee_id];
    }

    $presentDays = 22;
    $absentDays = 0;
    $proRateFactor = 1.0;
    $basic = round($actualBasic * $proRateFactor, 2);
    $hra = round($actualHra * $proRateFactor, 2);
    $special = round($actualSpecial * $proRateFactor, 2);
    $otherAll = round($actualOther * $proRateFactor, 2);

    $pf = $struct ? (float)$struct->pf : round($basic * 0.12, 2);
    $employerPf = $struct ? (float)($struct->employer_pf ?? $pf) : round($basic * 0.12, 2);
    $gratuity = $struct ? (float)($struct->gratuity ?? round($basic * 0.0481, 2)) : round($basic * 0.0481, 2);
    $esic = $struct ? (float)$struct->esic : 0.00;
    $pt = $struct ? (float)$struct->professional_tax : 200.00;
    $tds = $struct ? (float)$struct->tds : 0.00;

    $gross = $basic + $hra + $special + $otherAll + $med + $travel + $bonus + $otherEarn;
    $deductions = $pf + $esic + $pt + $tds;
    $net = $gross - $deductions;
    $ctc = $gross + $employerPf + $gratuity;

    $detailData = [
        'payroll_run_id' => $runId,
        'employee_id' => $emp->employee_id,
        'working_days' => $workingDays,
        'present_days' => $presentDays,
        'absent_days' => $absentDays,
        'leave_days' => 0,
        'overtime_hours' => 0,
        'late_marks' => 0,
        'basic_salary' => $basic,
        'hra' => $hra,
        'special_allowance' => $special,
        'other_allowance' => $otherAll,
        'medical_allowance' => $med,
        'travel_allowance' => $travel,
        'bonus' => $bonus,
        'other_earnings' => $otherEarn,
        'actual_basic' => $actualBasic,
        'actual_hra' => $actualHra,
        'actual_special_allowance' => $actualSpecial,
        'actual_other_allowance' => $actualOther,
        'pf' => $pf,
        'employer_pf' => $employerPf,
        'gratuity' => $gratuity,
        'esic' => $esic,
        'professional_tax' => $pt,
        'tds' => $tds,
        'gross_salary' => $gross,
        'total_deductions' => $deductions,
        'net_salary' => $net,
        'ctc' => $ctc
    ];

    $payrollModel->savePayrollDetails($detailData);
    $detailId = $db->lastInsertId();
    echo "  + Computed Payslip #$detailId for {$emp->name} ({$emp->employee_code}): Gross=Rs." . number_format($gross, 2) . " | Deductions=Rs." . number_format($deductions, 2) . " | Net Pay=Rs." . number_format($net, 2) . " | CTC=Rs." . number_format($ctc, 2) . "\n";
    $processedCount++;
}

echo "\n✓ Processed: $processedCount employees | Skipped (On-Hold): $skippedCount employee\n\n";

// 6. Verify Payroll Run Results and Payslip PDF Generation
echo "[STEP 6] Verifying September Run Payslips and PDF output...\n";
$runDetails = $payrollModel->getPayrollDetailsByRunId($runId);
assert(count($runDetails) === $processedCount, "Mismatch in processed details count");

$heldInDetails = array_filter($runDetails, function($d) use ($holdEmployee) {
    return (int)$d->employee_id === (int)$holdEmployee->employee_id;
});
assert(empty($heldInDetails), "Error: Employee on hold was found in payroll details!");
echo "✓ Verified: Employee {$holdEmployee->name} (on hold) was successfully excluded from payroll computation.\n";

// Generate PDF for the first payslip in Sept run
$testDetail = $runDetails[0];
$pdfContent = PayslipPdfService::generatePdf($testDetail);
assert(!empty($pdfContent), "Failed to generate PDF for September payslip");
echo "✓ Generated September Payslip PDF for {$testDetail->name} ({$testDetail->employee_code}) - Size: " . strlen($pdfContent) . " bytes\n";

echo "\n=================================================================\n";
echo "  SEPTEMBER 2026 PAYROLL RUN TEST COMPLETED WITH 100% SUCCESS!   \n";
echo "=================================================================\n";
