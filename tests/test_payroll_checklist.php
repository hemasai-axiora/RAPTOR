<?php
/**
 * Test script for Pre-Payroll Processing Checklist and Gatekeeping
 */
require_once __DIR__ . '/../app/config/config.php';
require_once APPROOT . '/core/Database.php';
require_once APPROOT . '/core/Model.php';
require_once APPROOT . '/core/PayslipHelper.php';
require_once APPROOT . '/models/Payroll.php';

$payrollModel = new Payroll();
$db = Database::getInstance()->getConnection();

echo "=== START PRE-PAYROLL CHECKLIST INTEGRATION TEST ===\n\n";

$testMonth = '2026-12';

// Clean test data for 2026-12 if any exists
$db->exec("DELETE FROM payroll_checklist_items WHERE checklist_id IN (SELECT checklist_id FROM payroll_checklists WHERE month_year = '$testMonth')");
$db->exec("DELETE FROM payroll_checklists WHERE month_year = '$testMonth'");
$db->exec("DELETE FROM payroll_salary_holds WHERE month_year = '$testMonth'");
$db->exec("DELETE FROM payroll_arrears WHERE month_year = '$testMonth'");
$db->exec("DELETE FROM payroll_details WHERE payroll_run_id IN (SELECT payroll_run_id FROM payroll_runs WHERE month_year = '$testMonth')");
$db->exec("DELETE FROM payroll_runs WHERE month_year = '$testMonth'");

// Test 1: Auto-generate Checklist & 9 Items
echo "[Test 1] Auto-generating checklist for $testMonth...\n";
$checklist = $payrollModel->getChecklistByMonth($testMonth);
assert($checklist !== null, "Checklist was not created");
echo "✓ Checklist ID: {$checklist->checklist_id}, Status: {$checklist->status}\n";

$items = $payrollModel->getChecklistItems($checklist->checklist_id);
assert(count($items) === 9, "Expected 9 items, got " . count($items));
echo "✓ Exactly 9 items initialized:\n";
foreach ($items as $idx => $item) {
    echo "   " . ($idx + 1) . ". [{$item->item_key}] {$item->item_title} (Status: {$item->status})\n";
}

// Test 2: Verify Gatekeeping when Pending
echo "\n[Test 2] Verifying Gatekeeping blocks payroll generation when items are pending...\n";
$isCompleted = $payrollModel->isChecklistCompleted($testMonth);
assert($isCompleted === false, "Checklist should not be completed yet");
echo "✓ isChecklistCompleted('$testMonth') = FALSE (Gatekeeping active)\n";

// Test 3: Updating Checklist Items
echo "\n[Test 3] Updating checklist items with remarks & user attribution...\n";
$userStmt = $db->query("SELECT user_id FROM users LIMIT 1");
$testUserId = (int)$userStmt->fetchColumn();
foreach ($items as $i => $item) {
    $newStatus = ($i === 8) ? 'in_progress' : 'completed';
    $remarks = "Verified and confirmed item '{$item->item_title}' for audit compliance.";
    $updated = $payrollModel->updateChecklistItem($item->item_id, $newStatus, $remarks, $testUserId);
    assert($updated === true, "Failed to update item {$item->item_id}");
}

// Check status again (8 completed, 1 in_progress)
$isCompleted = $payrollModel->isChecklistCompleted($testMonth);
assert($isCompleted === false, "Checklist should still be FALSE with 1 in_progress item");
echo "✓ Gatekeeping correctly blocks when 8/9 completed and 1 in_progress\n";

// Now complete the 9th item
$lastItem = end($items);
$payrollModel->updateChecklistItem($lastItem->item_id, 'completed', 'TDS records verified against slab computations.', $testUserId);
$isCompleted = $payrollModel->isChecklistCompleted($testMonth);
assert($isCompleted === true, "Checklist should now be TRUE when 9/9 are completed");
echo "✓ isChecklistCompleted('$testMonth') = TRUE when 9/9 completed and checklist marked completed\n";

// Test 4: Salary Hold functionality
echo "\n[Test 4] Testing Salary Hold...\n";
$empStmt = $db->query("SELECT employee_id FROM employees LIMIT 1");
$emp = $empStmt->fetch(PDO::FETCH_OBJ);
if ($emp) {
    $empId = $emp->employee_id;
    $res = $payrollModel->saveSalaryHold($testMonth, $empId, "Pending clearance of company assets upon resignation", $testUserId);
    assert($res === true, "Failed to create salary hold");
    echo "✓ Salary hold created for Employee ID: $empId\n";

    $holds = $payrollModel->getSalaryHolds($testMonth);
    assert(count($holds) === 1, "Expected 1 salary hold");
    echo "✓ Retrieved " . count($holds) . " active salary hold (ID: {$holds[0]->hold_id}): '{$holds[0]->reason}'\n";

    // Test releasing salary hold
    $released = $payrollModel->releaseSalaryHold($holds[0]->hold_id);
    assert($released === true, "Failed to release salary hold");
    $holdsAfter = $payrollModel->getSalaryHolds($testMonth);
    assert(count($holdsAfter) === 0, "Expected 0 active salary holds after release");
    echo "✓ Salary hold successfully released\n";
}

// Test 5: Arrears functionality
echo "\n[Test 5] Testing Arrears Management...\n";
if ($emp) {
    $empId = $emp->employee_id;
    $res = $payrollModel->saveArrear($testMonth, $empId, 2500.00, "Underpayment adjustment for previous month overtime", $testUserId);
    assert($res === true, "Failed to create arrear");
    echo "✓ Arrear added for Rs. 2500.00\n";

    $arrears = $payrollModel->getArrears($testMonth);
    assert(count($arrears) === 1, "Expected 1 arrear");
    assert((float)$arrears[0]->amount === 2500.00, "Arrear amount mismatch");
    echo "✓ Retrieved arrear successfully: '{$arrears[0]->reason}' - Amount: {$arrears[0]->amount}\n";
}

echo "\n=== ALL PRE-PAYROLL CHECKLIST INTEGRATION TESTS PASSED SUCCESSFULLY! ===\n";
