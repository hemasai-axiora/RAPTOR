<?php
// Raptor CRM Payroll, Bonuses & Reimbursements Controller

require_once APPROOT . '/core/PayslipHelper.php';
require_once APPROOT . '/core/PayslipPdfService.php';

class PayrollController extends Controller {
    private $payrollModel;
    private $userModel;

    public function __construct() {
        $this->requireAuth();
        $this->payrollModel = $this->model('Payroll');
        $this->userModel = $this->model('User');
    }

    // 1. Payroll Dashboard
    public function dashboard() {
        if (in_array($_SESSION['user_role'], ['analyst', 'employee', 'sales_person'], true)) {
            $this->redirect('index.php?route=dashboard/index');
            return;
        }
        $this->requirePermission('payroll', 'view');

        // Fetch payroll runs for overview
        $runs = $this->payrollModel->getPayrollRuns();
        $reimbursements = $this->payrollModel->getReimbursements(['status' => 'pending']);
        $role = $_SESSION['user_role'];
        
        $data = [
            'title' => 'Payroll Dashboard | Raptor CRM',
            'active_tab' => 'payroll_dashboard',
            'runs' => $runs,
            'pending_claims' => count($reimbursements),
            'role' => $role
        ];

        $this->viewWithLayout('payroll/dashboard', 'main', $data);
    }

    // 2. Pre-Payroll Processing Checklist
    public function checklist() {
        if (in_array($_SESSION['user_role'], ['analyst', 'employee', 'sales_person'], true)) {
            $this->redirect('index.php?route=dashboard/index');
            return;
        }
        $this->requirePermission('payroll', 'create');

        $monthYear = trim($_GET['month_year'] ?? date('Y-m'));
        if (!preg_match('/^\d{4}-\d{2}$/', $monthYear)) {
            $monthYear = date('Y-m');
        }

        $checklist = $this->payrollModel->getChecklistByMonth($monthYear);
        $items = $this->payrollModel->getChecklistItems($checklist->checklist_id);
        $employees = $this->payrollModel->getAllEmployeesWithStatus();
        $activeEmployees = $this->payrollModel->getActiveEmployees();
        $activeEmployees = array_values(array_filter($activeEmployees, function($emp) {
            $isIntern = (stripos($emp->job_title ?? '', 'intern') !== false) || 
                        (stripos($emp->employment_type ?? '', 'intern') !== false) || 
                        (stripos($emp->role_name ?? '', 'intern') !== false);
            $empSalary = (float)($emp->salary ?? 0);
            if ($isIntern && $empSalary <= 0) {
                return false;
            }
            return true;
        }));

        // 1. Employee Status Summary
        $activeCount = 0;
        $inactiveCount = 0;
        $newJoiners = [];
        $relievedEmployees = [];

        foreach ($employees as $emp) {
            if ($emp->user_status === 'active') {
                $activeCount++;
            } else {
                $inactiveCount++;
            }

            $doj = !empty($emp->date_of_joining) ? $emp->date_of_joining : (!empty($emp->hire_date) ? $emp->hire_date : '');
            if (!empty($doj) && str_starts_with($doj, $monthYear)) {
                $emp->date_of_joining = $doj;
                $newJoiners[] = $emp;
            }
            if ($emp->user_status !== 'active') {
                $relievedEmployees[] = $emp;
            }
        }

        // 2. Salary Holds for this month
        $salaryHolds = $this->payrollModel->getSalaryHolds($monthYear);

        // 3. Arrears for this month
        $arrears = $this->payrollModel->getArrears($monthYear);

        // 4. Bonuses & Reimbursements for this month
        $db = Database::getInstance()->getConnection();
        $bonusStmt = $db->prepare("SELECT b.*, e.employee_code, u.name as employee_name FROM bonuses b JOIN employees e ON b.employee_id = e.employee_id JOIN users u ON e.user_id = u.user_id WHERE b.status = 'approved' AND b.created_at LIKE :m");
        $bonusStmt->execute([':m' => $monthYear . '-%']);
        $approvedBonuses = $bonusStmt->fetchAll(PDO::FETCH_OBJ);

        // 5. Recent salary revisions for this month
        $revStmt = $db->prepare("SELECT srh.*, e.employee_code, u.name as employee_name FROM salary_revision_history srh JOIN employees e ON srh.employee_id = e.employee_id JOIN users u ON e.user_id = u.user_id WHERE srh.effective_date LIKE :m ORDER BY srh.effective_date DESC LIMIT 15");
        $revStmt->execute([':m' => $monthYear . '-%']);
        $salaryRevisions = $revStmt->fetchAll(PDO::FETCH_OBJ);

        // 6. Manual Loss of Pay (LOP) adjustments for this month
        $lops = $this->payrollModel->getLops($monthYear);

        // 7. Manual Other Deductions (penalties, loans, advances) for this month
        $deductions = $this->payrollModel->getDeductions($monthYear);

        // 8. Check if payroll run already generated for this month
        $existingRun = $this->payrollModel->getPayrollRunByMonth($monthYear);

        // Count completed items
        $completedCount = 0;
        foreach ($items as $it) {
            if ($it->status === 'completed') {
                $completedCount++;
            }
        }
        $isAllCompleted = ($completedCount >= count($items) && count($items) > 0);

        $data = [
            'title' => 'Pre-Payroll Checklist | Raptor CRM',
            'active_tab' => 'payroll_checklist',
            'month_year' => $monthYear,
            'checklist' => $checklist,
            'items' => $items,
            'completed_count' => $completedCount,
            'total_items' => count($items),
            'is_all_completed' => $isAllCompleted,
            'active_count' => $activeCount,
            'inactive_count' => $inactiveCount,
            'new_joiners' => $newJoiners,
            'relieved_employees' => $relievedEmployees,
            'salary_holds' => $salaryHolds,
            'arrears' => $arrears,
            'approved_bonuses' => $approvedBonuses,
            'salary_revisions' => $salaryRevisions,
            'lops' => $lops,
            'deductions' => $deductions,
            'active_employees' => $activeEmployees,
            'existing_run' => $existingRun,
            'role' => $_SESSION['user_role']
        ];

        $this->viewWithLayout('payroll/checklist', 'main', $data);
    }

    // Quick Mark All Checklist Items as Completed
    public function mark_all_checklist() {
        if (in_array($_SESSION['user_role'], ['analyst', 'employee', 'sales_person'], true)) {
            $this->redirect('index.php?route=dashboard/index');
            return;
        }
        $this->requirePermission('payroll', 'create');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $monthYear = trim($_POST['month_year'] ?? date('Y-m'));
            $remarks = trim($_POST['bulk_remarks'] ?? 'All items verified and confirmed before payroll execution.');
            $checklist = $this->payrollModel->getChecklistByMonth($monthYear);
            if ($checklist) {
                $items = $this->payrollModel->getChecklistItems($checklist->checklist_id);
                foreach ($items as $it) {
                    $this->payrollModel->updateChecklistItem($it->item_id, 'completed', $remarks, $_SESSION['user_id'] ?? 1);
                }
                $_SESSION['payroll_success'] = 'All 9 pre-payroll checklist items have been confirmed and marked Completed. Ready to run payroll!';
            }
            $this->redirect('index.php?route=payroll/checklist&month_year=' . $monthYear);
            return;
        }
        $this->redirect('index.php?route=payroll/checklist');
    }

    // Save Checklist Item Status and Remarks
    public function save_checklist_item() {
        if (in_array($_SESSION['user_role'], ['analyst', 'employee', 'sales_person'], true)) {
            $this->redirect('index.php?route=dashboard/index');
            return;
        }
        $this->requirePermission('payroll', 'create');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $_POST = filter_input_array(INPUT_POST, FILTER_SANITIZE_SPECIAL_CHARS) ?: [];
            $itemId = (int)($_POST['item_id'] ?? 0);
            $status = trim($_POST['status'] ?? 'pending');
            $remarks = trim($_POST['remarks'] ?? '');
            $monthYear = trim($_POST['month_year'] ?? date('Y-m'));

            if ($itemId > 0 && in_array($status, ['pending', 'in_progress', 'completed'], true)) {
                $success = $this->payrollModel->updateChecklistItem($itemId, $status, $remarks, $_SESSION['user_id']);

                if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => $success, 'message' => 'Checklist item updated successfully.']);
                    exit();
                }

                $_SESSION['payroll_success'] = 'Checklist item updated successfully.';
                $this->redirect('index.php?route=payroll/checklist&month_year=' . $monthYear);
                return;
            }
        }
        $this->redirect('index.php?route=payroll/checklist');
    }

    // Add Salary Hold
    public function add_salary_hold() {
        $this->requirePermission('payroll', 'create');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $_POST = filter_input_array(INPUT_POST, FILTER_SANITIZE_SPECIAL_CHARS) ?: [];
            $empId = (int)($_POST['employee_id'] ?? 0);
            $monthYear = trim($_POST['month_year'] ?? date('Y-m'));
            $reason = trim($_POST['reason'] ?? 'Salary placed on hold');

            if ($empId > 0 && !empty($reason)) {
                $this->payrollModel->saveSalaryHold($monthYear, $empId, $reason, $_SESSION['user_id']);
                $this->recalculateRunForMonth($monthYear, $empId);
                $_SESSION['payroll_success'] = 'Salary hold recorded successfully and payroll run synchronized.';
            } else {
                $_SESSION['payroll_error'] = 'Invalid employee or hold reason.';
            }
            $this->redirect('index.php?route=payroll/checklist&month_year=' . $monthYear . '#salary_hold');
            return;
        }
        $this->redirect('index.php?route=payroll/checklist');
    }

    // Release Salary Hold
    public function release_salary_hold($holdId) {
        $this->requirePermission('payroll', 'create');
        $monthYear = trim($_GET['month_year'] ?? date('Y-m'));

        if ($this->payrollModel->releaseSalaryHold($holdId)) {
            $this->recalculateRunForMonth($monthYear);
            $_SESSION['payroll_success'] = 'Salary hold released successfully and payroll run synchronized.';
        } else {
            $_SESSION['payroll_error'] = 'Failed to release salary hold.';
        }
        $this->redirect('index.php?route=payroll/checklist&month_year=' . $monthYear . '#salary_hold');
    }

    // Add Salary Arrear
    public function add_arrear() {
        $this->requirePermission('payroll', 'create');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $_POST = filter_input_array(INPUT_POST, FILTER_SANITIZE_SPECIAL_CHARS) ?: [];
            $empId = (int)($_POST['employee_id'] ?? 0);
            $amount = (float)($_POST['amount'] ?? 0);
            $monthYear = trim($_POST['month_year'] ?? date('Y-m'));
            $reason = trim($_POST['reason'] ?? 'Previous month salary adjustment');

            if ($empId > 0 && $amount > 0) {
                $this->payrollModel->saveArrear($monthYear, $empId, $amount, $reason, $_SESSION['user_id']);
                $this->recalculateRunForMonth($monthYear, $empId);
                $_SESSION['payroll_success'] = 'Salary arrear added successfully and payroll run synchronized.';
            } else {
                $_SESSION['payroll_error'] = 'Invalid employee, amount or reason.';
            }
            $this->redirect('index.php?route=payroll/checklist&month_year=' . $monthYear . '#arrears');
            return;
        }
        $this->redirect('index.php?route=payroll/checklist');
    }

    // Delete Salary Arrear
    public function delete_arrear($arrearId) {
        $this->requirePermission('payroll', 'create');
        $monthYear = trim($_GET['month_year'] ?? date('Y-m'));

        if ($this->payrollModel->deleteArrear($arrearId)) {
            $this->recalculateRunForMonth($monthYear);
            $_SESSION['payroll_success'] = 'Salary arrear removed successfully and payroll run synchronized.';
        } else {
            $_SESSION['payroll_error'] = 'Failed to remove arrear.';
        }
        $this->redirect('index.php?route=payroll/checklist&month_year=' . $monthYear . '#arrears');
    }

    // Add Salary Revision from Checklist
    public function add_salary_revision() {
        $this->requirePermission('payroll', 'create');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $_POST = filter_input_array(INPUT_POST, FILTER_SANITIZE_SPECIAL_CHARS) ?: [];
            $empId = (int)($_POST['employee_id'] ?? 0);
            $monthYear = trim($_POST['month_year'] ?? date('Y-m'));
            $newGross = round((float)($_POST['new_gross'] ?? 0));
            $effectiveDate = !empty($_POST['effective_date']) ? trim($_POST['effective_date']) : date('Y-m-d');
            $reason = trim($_POST['reason'] ?? 'Salary Revision');

            if ($empId > 0 && $newGross > 0 && !empty($reason)) {
                $current = $this->payrollModel->getSalaryStructureByEmployeeId($empId);

                $basic = round($newGross * 0.50);
                $hra = round($newGross * 0.30);
                $special = round($newGross - ($basic + $hra));
                $pf = round($basic * 0.12);
                $employerPf = round($basic * 0.12);
                $gratuity = round($basic * 0.0481);

                $formData = [
                    'employee_id' => $empId,
                    'salary_type' => 'Monthly',
                    'basic_salary' => $basic,
                    'hra' => $hra,
                    'special_allowance' => $special,
                    'other_allowance' => 0.00,
                    'medical_allowance' => 0.00,
                    'travel_allowance' => 0.00,
                    'bonus' => 0.00,
                    'other_earnings' => 0.00,
                    'pf' => $pf,
                    'employer_pf' => $employerPf,
                    'gratuity' => $gratuity,
                    'esic' => 0.00,
                    'professional_tax' => 200.00,
                    'tds' => $current ? (float)$current->tds : 0.00
                ];

                if ($this->payrollModel->saveSalaryStructure($formData, $_SESSION['user_id'], $reason, $effectiveDate)) {
                    $db = Database::getInstance()->getConnection();
                    $stmt = $db->prepare('UPDATE employees SET salary = :sal WHERE employee_id = :emp_id');
                    $stmt->execute([':sal' => $newGross, ':emp_id' => $empId]);

                    $this->recalculateRunForMonth($monthYear, $empId);
                    $_SESSION['payroll_success'] = 'Salary revision recorded successfully and payroll run synchronized.';
                } else {
                    $_SESSION['payroll_error'] = 'Failed to record salary revision.';
                }
            } else {
                $_SESSION['payroll_error'] = 'Please enter a valid employee, revised salary amount, and remarks.';
            }

            $this->redirect('index.php?route=payroll/checklist&month_year=' . $monthYear . '#salary_revisions');
            return;
        }
        $this->redirect('index.php?route=payroll/checklist');
    }

    // Delete/Remove Salary Revision Record
    public function delete_salary_revision($revId) {
        $this->requirePermission('payroll', 'create');
        $monthYear = trim($_GET['month_year'] ?? date('Y-m'));

        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare('DELETE FROM salary_revision_history WHERE revision_id = :id');
        if ($stmt->execute([':id' => (int)$revId])) {
            $this->recalculateRunForMonth($monthYear);
            $_SESSION['payroll_success'] = 'Salary revision entry removed and payroll run synchronized.';
        } else {
            $_SESSION['payroll_error'] = 'Failed to remove revision entry.';
        }
        $this->redirect('index.php?route=payroll/checklist&month_year=' . $monthYear . '#salary_revisions');
    }

    // Add / Edit Manual Loss of Pay (LOP) from Checklist
    public function add_lop() {
        $this->requirePermission('payroll', 'create');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $_POST = filter_input_array(INPUT_POST, FILTER_SANITIZE_SPECIAL_CHARS) ?: [];
            $empId = (int)($_POST['employee_id'] ?? 0);
            $monthYear = trim($_POST['month_year'] ?? date('Y-m'));
            $lopDays = (float)($_POST['lop_days'] ?? 0);
            $reason = trim($_POST['reason'] ?? 'Loss of Pay deduction');

            if ($empId > 0 && $lopDays > 0 && !empty($reason)) {
                if ($this->payrollModel->saveLop($monthYear, $empId, $lopDays, $reason, $_SESSION['user_id'])) {
                    $this->recalculateRunForMonth($monthYear, $empId);
                    $_SESSION['payroll_success'] = 'Loss of Pay (LOP) adjustment saved and payroll run synchronized.';
                } else {
                    $_SESSION['payroll_error'] = 'Failed to save LOP adjustment.';
                }
            } else {
                $_SESSION['payroll_error'] = 'Please select a valid employee, enter LOP days, and provide remarks.';
            }

            $this->redirect('index.php?route=payroll/checklist&month_year=' . $monthYear . '#lop_deductions');
            return;
        }
        $this->redirect('index.php?route=payroll/checklist');
    }

    // Delete/Remove Manual LOP adjustment
    public function delete_lop($lopId) {
        $this->requirePermission('payroll', 'create');
        $monthYear = trim($_GET['month_year'] ?? date('Y-m'));

        if ($this->payrollModel->deleteLop($lopId)) {
            $this->recalculateRunForMonth($monthYear);
            $_SESSION['payroll_success'] = 'Loss of Pay (LOP) entry removed and payroll run synchronized.';
        } else {
            $_SESSION['payroll_error'] = 'Failed to remove LOP entry.';
        }
        $this->redirect('index.php?route=payroll/checklist&month_year=' . $monthYear . '#lop_deductions');
    }

    // Add Manual Other Deduction from Checklist
    public function add_deduction() {
        $this->requirePermission('payroll', 'create');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $_POST = filter_input_array(INPUT_POST, FILTER_SANITIZE_SPECIAL_CHARS) ?: [];
            $empId = (int)($_POST['employee_id'] ?? 0);
            $monthYear = trim($_POST['month_year'] ?? date('Y-m'));
            $deductionType = trim($_POST['deduction_type'] ?? 'Other Deduction');
            $amount = (float)($_POST['amount'] ?? 0);
            $reason = trim($_POST['reason'] ?? 'Employee deduction');

            if ($empId > 0 && $amount > 0 && !empty($reason)) {
                if ($this->payrollModel->saveDeduction($monthYear, $empId, $deductionType, $amount, $reason, $_SESSION['user_id'])) {
                    $this->recalculateRunForMonth($monthYear, $empId);
                    $_SESSION['payroll_success'] = 'Employee deduction saved and payroll run synchronized.';
                } else {
                    $_SESSION['payroll_error'] = 'Failed to save employee deduction.';
                }
            } else {
                $_SESSION['payroll_error'] = 'Please select an employee, enter a valid deduction amount, and provide remarks.';
            }

            $this->redirect('index.php?route=payroll/checklist&month_year=' . $monthYear . '#other_deductions');
            return;
        }
        $this->redirect('index.php?route=payroll/checklist');
    }

    // Delete/Remove Other Deduction entry
    public function delete_deduction($deductionId) {
        $this->requirePermission('payroll', 'create');
        $monthYear = trim($_GET['month_year'] ?? date('Y-m'));

        if ($this->payrollModel->deleteDeduction($deductionId)) {
            $this->recalculateRunForMonth($monthYear);
            $_SESSION['payroll_success'] = 'Employee deduction entry removed and payroll run synchronized.';
        } else {
            $_SESSION['payroll_error'] = 'Failed to remove employee deduction.';
        }
        $this->redirect('index.php?route=payroll/checklist&month_year=' . $monthYear . '#other_deductions');
    }

    // Add Bonus / Incentive from Checklist
    public function add_checklist_bonus() {
        $this->requirePermission('payroll', 'create');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $_POST = filter_input_array(INPUT_POST, FILTER_SANITIZE_SPECIAL_CHARS) ?: [];
            $empId = (int)($_POST['employee_id'] ?? 0);
            $monthYear = trim($_POST['month_year'] ?? date('Y-m'));
            $bonusType = trim($_POST['bonus_type'] ?? 'Performance');
            $amount = (float)($_POST['amount'] ?? 0);
            $description = trim($_POST['description'] ?? '');

            if ($empId > 0 && $amount > 0 && !empty($description)) {
                $db = Database::getInstance()->getConnection();
                $createdAt = (date('Y-m') === $monthYear) ? date('Y-m-d H:i:s') : ($monthYear . '-01 10:00:00');
                
                $stmt = $db->prepare("INSERT INTO bonuses (employee_id, bonus_type, amount, description, status, created_at) VALUES (:emp_id, :type, :amount, :desc, 'approved', :created_at)");
                if ($stmt->execute([
                    ':emp_id' => $empId,
                    ':type' => $bonusType,
                    ':amount' => $amount,
                    ':desc' => $description,
                    ':created_at' => $createdAt
                ])) {
                    $this->recalculateRunForMonth($monthYear, $empId);
                    $_SESSION['payroll_success'] = 'Bonus / incentive added and approved for ' . PayslipHelper::formatMonthYear($monthYear) . ', and payroll run synchronized.';
                } else {
                    $_SESSION['payroll_error'] = 'Failed to add bonus record.';
                }
            } else {
                $_SESSION['payroll_error'] = 'Please select an employee, enter a valid amount, and provide remarks.';
            }

            $this->redirect('index.php?route=payroll/checklist&month_year=' . $monthYear . '#bonuses_incentives');
            return;
        }
        $this->redirect('index.php?route=payroll/checklist');
    }

    // Delete/Remove Bonus from Checklist
    public function delete_checklist_bonus($bonusId) {
        $this->requirePermission('payroll', 'create');
        $monthYear = trim($_GET['month_year'] ?? date('Y-m'));

        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare('DELETE FROM bonuses WHERE bonus_id = :id');
        if ($stmt->execute([':id' => (int)$bonusId])) {
            $this->recalculateRunForMonth($monthYear);
            $_SESSION['payroll_success'] = 'Bonus record removed and payroll run synchronized.';
        } else {
            $_SESSION['payroll_error'] = 'Failed to remove bonus record.';
        }
        $this->redirect('index.php?route=payroll/checklist&month_year=' . $monthYear . '#bonuses_incentives');
    }

    // 3. Salary Structures Setup & Revision History
    public function structures() {
        if (in_array($_SESSION['user_role'], ['analyst', 'employee', 'sales_person'], true)) {
            $this->redirect('index.php?route=dashboard/index');
            return;
        }
        $this->requirePermission('payroll', 'edit');

        $employees = $this->payrollModel->getActiveEmployees();
        $employees = array_values(array_filter($employees, function($emp) {
            $isIntern = (stripos($emp->job_title ?? '', 'intern') !== false) || 
                        (stripos($emp->employment_type ?? '', 'intern') !== false) || 
                        (stripos($emp->role_name ?? '', 'intern') !== false);
            $empSalary = (float)($emp->salary ?? 0);
            if ($isIntern && $empSalary <= 0) {
                return false;
            }
            return true;
        }));

        // Get salary structure and revision history for selected employee if any
        $selectedEmpId = (int)($_GET['employee_id'] ?? 0);
        $structure = null;
        $revisions = [];
        $selectedEmployee = null;

        if ($selectedEmpId > 0) {
            $structure = $this->payrollModel->getSalaryStructureByEmployeeId($selectedEmpId);
            $revisions = $this->payrollModel->getSalaryRevisionHistory($selectedEmpId);
            $selectedEmployee = $this->payrollModel->getEmployeeById($selectedEmpId);
        }

        $data = [
            'title' => 'Salary Structures & Revision History | Raptor CRM',
            'active_tab' => 'payroll_structures',
            'employees' => $employees,
            'selected_employee_id' => $selectedEmpId,
            'selected_employee' => $selectedEmployee,
            'structure' => $structure,
            'revisions' => $revisions,
            'role' => $_SESSION['user_role']
        ];

        $this->viewWithLayout('payroll/structures', 'main', $data);
    }

    // Save/Update Salary Structure with Revision History Tracking
    public function save_structure() {
        if (in_array($_SESSION['user_role'], ['analyst', 'employee', 'sales_person'], true)) {
            $this->redirect('index.php?route=dashboard/index');
            return;
        }
        $this->requirePermission('payroll', 'edit');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $_POST = filter_input_array(INPUT_POST, FILTER_SANITIZE_SPECIAL_CHARS) ?: [];
            
            $empId = (int)($_POST['employee_id'] ?? 0);
            if ($empId > 0) {
                $basic = (float)($_POST['basic_salary'] ?? 0);
                $hra = (float)($_POST['hra'] ?? 0);
                $special = (float)($_POST['special_allowance'] ?? 0);
                $otherAll = (float)($_POST['other_allowance'] ?? 0);
                $med = (float)($_POST['medical_allowance'] ?? 0);
                $travel = (float)($_POST['travel_allowance'] ?? 0);
                $bonus = (float)($_POST['bonus'] ?? 0);
                $otherEarn = (float)($_POST['other_earnings'] ?? 0);

                // PF calculation defaults: 12% of basic if not specified
                $pf = isset($_POST['pf']) && $_POST['pf'] !== '' ? (float)$_POST['pf'] : round($basic * 0.12, 2);
                $employerPf = isset($_POST['employer_pf']) && $_POST['employer_pf'] !== '' ? (float)$_POST['employer_pf'] : round($basic * 0.12, 2);
                $gratuity = isset($_POST['gratuity']) && $_POST['gratuity'] !== '' ? (float)$_POST['gratuity'] : round($basic * 0.0481, 2);
                $esic = (float)($_POST['esic'] ?? 0);
                $pt = isset($_POST['professional_tax']) && $_POST['professional_tax'] !== '' ? (float)$_POST['professional_tax'] : 200.00;
                $tds = (float)($_POST['tds'] ?? 0);

                $formData = [
                    'employee_id' => $empId,
                    'salary_type' => trim($_POST['salary_type'] ?? 'Monthly'),
                    'basic_salary' => $basic,
                    'hra' => $hra,
                    'special_allowance' => $special,
                    'other_allowance' => $otherAll,
                    'medical_allowance' => $med,
                    'travel_allowance' => $travel,
                    'bonus' => $bonus,
                    'other_earnings' => $otherEarn,
                    'pf' => $pf,
                    'employer_pf' => $employerPf,
                    'gratuity' => $gratuity,
                    'esic' => $esic,
                    'professional_tax' => $pt,
                    'tds' => $tds
                ];

                $effectiveDate = !empty($_POST['effective_date']) ? trim($_POST['effective_date']) : date('Y-m-d');
                $revisionReason = !empty($_POST['revision_reason']) ? trim($_POST['revision_reason']) : 'Salary Structure Update';

                if ($this->payrollModel->saveSalaryStructure($formData, $_SESSION['user_id'], $revisionReason, $effectiveDate)) {
                    // Update salary field on employees table as well
                    $db = Database::getInstance()->getConnection();
                    $stmt = $db->prepare('UPDATE employees SET salary = :sal WHERE employee_id = :emp_id');
                    $grossSal = $basic + $hra + $special + $otherAll + $med + $travel + $otherEarn;
                    $stmt->execute([
                        ':sal' => $grossSal,
                        ':emp_id' => $empId
                    ]);

                    $_SESSION['payroll_success'] = 'Salary structure saved and revision history updated successfully.';
                } else {
                    $_SESSION['payroll_error'] = 'Failed to save salary structure.';
                }
                $this->redirect('index.php?route=payroll/structures&employee_id=' . $empId);
                return;
            }
        }
        $this->redirect('index.php?route=payroll/structures');
    }

    // View Salary Revision History Modal/JSON Endpoint
    public function salary_history() {
        $empId = (int)($_GET['employee_id'] ?? 0);
        if ($empId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid employee ID']);
            exit();
        }

        $revisions = $this->payrollModel->getSalaryRevisionHistory($empId);
        $employee = $this->payrollModel->getEmployeeById($empId);

        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'employee' => $employee,
            'revisions' => $revisions
        ]);
        exit();
    }

    // 4. Process Payroll Runs
    public function processing() {
        if (in_array($_SESSION['user_role'], ['analyst', 'employee', 'sales_person'], true)) {
            $this->redirect('index.php?route=dashboard/index');
            return;
        }
        $this->requirePermission('payroll', 'create');

        $runs = $this->payrollModel->getPayrollRuns();

        // Selected run details
        $runId = (int)($_GET['run_id'] ?? 0);
        $details = [];
        $selectedRun = null;
        if ($runId > 0) {
            $selectedRun = $this->payrollModel->getPayrollRunById($runId);
            $details = $this->payrollModel->getPayrollDetailsByRunId($runId);
        }

        $data = [
            'title' => 'Payroll Processing | Raptor CRM',
            'active_tab' => 'payroll_processing',
            'runs' => $runs,
            'selected_run' => $selectedRun,
            'details' => $details,
            'role' => $_SESSION['user_role']
        ];

        $this->viewWithLayout('payroll/processing', 'main', $data);
    }

    /**
     * Recalculates and synchronizes payroll_details for a given month.
     * Keeps all calculations rounded to integers (no decimals) and incorporates
     * updated salary structures, arrears, bonuses, reimbursements, LOPs, and deductions.
     */
    private function recalculateRunForMonth($month, $targetEmpId = null) {
        $existing = $this->payrollModel->getPayrollRunByMonth($month);
        if (!$existing || $existing->status === 'released') {
            return false;
        }

        $runId = $existing->payroll_run_id;
        $db = Database::getInstance()->getConnection();

        // Always fetch active employees
        $employees = $this->payrollModel->getActiveEmployees();

        // Fetch configured salary holds, arrears, LOPs, deductions
        $salaryHolds = $this->payrollModel->getSalaryHolds($month);
        $holdsByEmp = [];
        foreach ($salaryHolds as $h) {
            $holdsByEmp[$h->employee_id] = $h;
        }

        $arrearsList = $this->payrollModel->getArrears($month);
        $arrearsByEmp = [];
        foreach ($arrearsList as $a) {
            $arrearsByEmp[$a->employee_id] = ($arrearsByEmp[$a->employee_id] ?? 0) + (float)$a->amount;
        }

        $lopsList = $this->payrollModel->getLops($month);
        $lopsByEmp = [];
        foreach ($lopsList as $l) {
            $lopsByEmp[$l->employee_id] = (float)$l->lop_days;
        }

        $deductionsList = $this->payrollModel->getDeductions($month);
        $otherDeductionsByEmp = [];
        foreach ($deductionsList as $d) {
            $otherDeductionsByEmp[$d->employee_id] = ($otherDeductionsByEmp[$d->employee_id] ?? 0) + (float)$d->amount;
        }

        $workingDays = 22;

        if ($targetEmpId !== null && (int)$targetEmpId > 0) {
            $delStmt = $db->prepare("DELETE FROM payroll_details WHERE payroll_run_id = :rid AND employee_id = :eid");
            $delStmt->execute([':rid' => $runId, ':eid' => (int)$targetEmpId]);
            $processList = [];
            foreach ($employees as $emp) {
                if ((int)$emp->employee_id === (int)$targetEmpId) {
                    $processList[] = $emp;
                    break;
                }
            }
        } else {
            $delStmt = $db->prepare("DELETE FROM payroll_details WHERE payroll_run_id = :rid");
            $delStmt->execute([':rid' => $runId]);
            $processList = $employees;
        }

        foreach ($processList as $emp) {
            // Check if employee salary is on hold for this month
            if (isset($holdsByEmp[$emp->employee_id])) {
                continue;
            }

            // Exclude Interns with salary 0 or employees with 0 salary from payroll processing
            $isIntern = (stripos($emp->job_title ?? '', 'intern') !== false) || 
                        (stripos($emp->employment_type ?? '', 'intern') !== false) || 
                        (stripos($emp->role_name ?? '', 'intern') !== false);

            $struct = $this->payrollModel->getSalaryStructureByEmployeeId($emp->employee_id);
            $empSalary = (float)($emp->salary ?? 0);
            $basicSalaryVal = $struct ? (float)$struct->basic_salary : $empSalary;

            // Business rule: Intern role with salary 0 must not process in payroll
            if ($isIntern && $basicSalaryVal <= 0) {
                continue;
            }
            // Skip any employee with 0 salary and no positive salary structure
            if ($basicSalaryVal <= 0 && (!$struct || ((float)($struct->gross_salary ?? 0) <= 0))) {
                continue;
            }

            // Base structure components
            $actualBasic = $basicSalaryVal > 0 ? $basicSalaryVal : 25000.00;
            $actualHra = $struct ? (float)$struct->hra : round($actualBasic * 0.40);
            $actualSpecial = $struct ? (float)$struct->special_allowance : 3000.00;
            $actualOther = $struct ? (float)($struct->other_allowance ?? 0.00) : 0.00;
            $med = $struct ? (float)$struct->medical_allowance : 1250.00;
            $travel = $struct ? (float)$struct->travel_allowance : 1600.00;
            $bonus = $struct ? (float)$struct->bonus : 0.00;
            $otherEarn = $struct ? (float)$struct->other_earnings : 0.00;

            // Include approved bonuses from bonuses table for this month
            try {
                $bonusStmt = $db->prepare("SELECT SUM(amount) FROM bonuses WHERE employee_id = :emp_id AND status = 'approved' AND created_at LIKE :month");
                $bonusStmt->execute([':emp_id' => $emp->employee_id, ':month' => $month . '-%']);
                $approvedBonusSum = (float)$bonusStmt->fetchColumn();
                $bonus += $approvedBonusSum;
            } catch (Exception $e) {}

            // Include approved reimbursements for this employee and month
            $reimbSum = 0.00;
            try {
                $reimbStmt = $db->prepare("SELECT SUM(amount) FROM reimbursements WHERE employee_id = :emp_id AND status = 'finance_approved' AND created_at LIKE :month");
                $reimbStmt->execute([':emp_id' => $emp->employee_id, ':month' => $month . '-%']);
                $reimbSum = (float)$reimbStmt->fetchColumn();
            } catch (Exception $e) {}
            $otherEarn += $reimbSum;

            // Include approved arrears
            if (!empty($arrearsByEmp[$emp->employee_id])) {
                $otherEarn += (float)$arrearsByEmp[$emp->employee_id];
            }

            // Attendance logs
            $presentDays = 22;
            $absentDays = 0;
            try {
                $attStmt = $db->prepare("SELECT COUNT(*) FROM attendance WHERE user_id = :uid AND status = 'present' AND date_stamp LIKE :month");
                $attStmt->execute([':uid' => $emp->user_id, ':month' => $month . '-%']);
                $presentCount = (int)$attStmt->fetchColumn();
                if ($presentCount > 0) {
                    $presentDays = min($presentCount, $workingDays);
                    $absentDays = max(0, $workingDays - $presentDays);
                }
            } catch (Exception $e) {}

            // Apply manual Loss of Pay (LOP) days if configured
            if (isset($lopsByEmp[$emp->employee_id])) {
                $manualLop = (float)$lopsByEmp[$emp->employee_id];
                $absentDays = max($absentDays, $manualLop);
                $presentDays = max(0, $workingDays - $absentDays);
            }

            // Pro-rate earned amounts if any loss of pay (absent days)
            $proRateFactor = ($workingDays > 0) ? ($presentDays / $workingDays) : 1.0;
            $basic = round($actualBasic * $proRateFactor);
            $hra = round($actualHra * $proRateFactor);
            $special = round($actualSpecial * $proRateFactor);
            $otherAll = round($actualOther * $proRateFactor);
            $med = round($med * $proRateFactor);
            $travel = round($travel * $proRateFactor);

            // Statutory & Other Deductions
            $pf = $struct ? round((float)$struct->pf) : round($basic * 0.12);
            $employerPf = $struct ? round((float)($struct->employer_pf ?? $pf)) : round($basic * 0.12);
            $gratuity = $struct ? round((float)($struct->gratuity ?? round($basic * 0.0481))) : round($basic * 0.0481);
            $esic = $struct ? round((float)$struct->esic) : round($basic * 0.0075);
            $pt = $struct ? round((float)$struct->professional_tax) : 200;
            $tds = $struct ? round((float)$struct->tds) : 0;
            $empOtherDeduct = round((float)($otherDeductionsByEmp[$emp->employee_id] ?? 0.00));

            // Gross & Net & CTC computations (rounded whole numbers, no decimals)
            $bonus = round($bonus);
            $otherEarn = round($otherEarn);
            $gross = round($basic + $hra + $special + $otherAll + $med + $travel + $bonus + $otherEarn);
            $deductions = round($pf + $esic + $pt + $tds + $empOtherDeduct);
            $net = round($gross - $deductions);
            $benefits = round($employerPf + $gratuity);
            $ctc = round($gross + $benefits);

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
                'actual_basic' => round($actualBasic),
                'actual_hra' => round($actualHra),
                'actual_special_allowance' => round($actualSpecial),
                'actual_other_allowance' => round($actualOther),
                'pf' => $pf,
                'employer_pf' => $employerPf,
                'gratuity' => $gratuity,
                'esic' => $esic,
                'professional_tax' => $pt,
                'tds' => $tds,
                'other_deductions' => $empOtherDeduct,
                'gross_salary' => $gross,
                'total_deductions' => $deductions,
                'net_salary' => $net,
                'ctc' => $ctc
            ];

            $this->payrollModel->savePayrollDetails($detailData);
        }

        return true;
    }

    // Start/Generate payroll run for a month (Gated by Pre-Payroll Checklist)
    public function run_generate() {
        $this->requirePermission('payroll', 'create');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $_POST = filter_input_array(INPUT_POST, FILTER_SANITIZE_SPECIAL_CHARS) ?: [];
            $month = trim($_POST['month_year'] ?? '');

            if (preg_match('/^\d{4}-\d{2}$/', $month)) {
                $currentMonth = date('Y-m');
                if ($month > $currentMonth) {
                    $_SESSION['payroll_error'] = "Cannot generate payroll for future months.";
                    $this->redirect('index.php?route=payroll/dashboard');
                    return;
                }

                // CHECKLIST GATE: Enforce completion of all 9 items
                if (!$this->payrollModel->isChecklistCompleted($month)) {
                    $_SESSION['payroll_error'] = "Pre-Payroll Verification Required: All 9 checklist items must be verified and marked 'Completed' before initiating payroll for " . PayslipHelper::formatMonthYear($month) . ".";
                    $this->redirect('index.php?route=payroll/checklist&month_year=' . $month);
                    return;
                }

                // Check if run already exists and is locked/released
                $existing = $this->payrollModel->getPayrollRunByMonth($month);
                if ($existing && $existing->status === 'released') {
                    $_SESSION['payroll_error'] = "Payroll for {$month} is in RELEASED status and cannot be regenerated.";
                    $this->redirect('index.php?route=payroll/processing&run_id=' . $existing->payroll_run_id);
                    return;
                }

                if (!$existing) {
                    $runId = $this->payrollModel->createPayrollRun($month, $_SESSION['user_id']);
                } else {
                    $runId = $existing->payroll_run_id;
                }

                if ($runId) {
                    $this->recalculateRunForMonth($month);
                    $_SESSION['payroll_success'] = "Payroll run for " . PayslipHelper::formatMonthYear($month) . " calculated and synchronized successfully.";
                    $this->redirect('index.php?route=payroll/processing&run_id=' . $runId);
                    return;
                }
            }
        }
        $this->redirect('index.php?route=payroll/processing');
    }

    // Approve payroll run
    public function run_approve($runId) {
        $this->requirePermission('payroll', 'create');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if ($this->payrollModel->updateRunStatus($runId, 'approved', 'approved_by', $_SESSION['user_id'])) {
                $_SESSION['payroll_success'] = 'Payroll run approved successfully. Ready for release.';
            } else {
                $_SESSION['payroll_error'] = 'Failed to approve payroll run.';
            }
        }
        $this->redirect('index.php?route=payroll/processing&run_id=' . $runId);
    }

    // Lock payroll run
    public function run_lock($runId) {
        $this->requirePermission('payroll', 'approve');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if ($this->payrollModel->updateRunStatus($runId, 'locked')) {
                $_SESSION['payroll_success'] = 'Payroll run locked. Ready to process bank transfers.';
            } else {
                $_SESSION['payroll_error'] = 'Failed to lock payroll run.';
            }
        }
        $this->redirect('index.php?route=payroll/processing&run_id=' . $runId);
    }

    // Release payslips and execute payments
    public function run_release($runId) {
        $this->requirePermission('payroll', 'approve');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if ($this->payrollModel->updateRunStatus($runId, 'released', 'released_by', $_SESSION['user_id'])) {
                $this->payrollModel->markRunDetailsPaid($runId);
                $_SESSION['payroll_success'] = 'Payroll has been released and payslips are now available to employees.';
            } else {
                $_SESSION['payroll_error'] = 'Failed to release payroll.';
            }
        }
        $this->redirect('index.php?route=payroll/processing&run_id=' . $runId);
    }

    // Export bank transfer file (NEFT/RTGS CSV format)
    public function export_bank_file($runId) {
        $this->requirePermission('payroll', 'approve');

        $run = $this->payrollModel->getPayrollRunById($runId);
        if (!$run) {
            $this->redirect('index.php?route=payroll/processing');
            return;
        }

        $details = $this->payrollModel->getPayrollDetailsByRunId($runId);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=bank_transfer_' . $run->month_year . '.csv');

        $output = fopen('php://output', 'w');
        fputcsv($output, ['Serial No', 'Employee ID', 'Account Holder Name', 'Bank Name', 'Account Number', 'IFSC Code', 'Amount', 'Transfer Type']);

        $i = 1;
        foreach ($details as $row) {
            fputcsv($output, [
                $i++,
                $row->employee_code,
                $row->account_holder_name ?: $row->name,
                $row->bank_name ?: 'N/A',
                $row->account_number ? "'" . $row->account_number : 'N/A',
                $row->ifsc_code ?: 'N/A',
                number_format((float)$row->net_salary, 2, '.', ''),
                'NEFT'
            ]);
        }

        fclose($output);
        exit();
    }

    // 5. Employee Payslips Directory
    public function payslips() {
        $emp = $this->payrollModel->getEmployeeByUserId($_SESSION['user_id'] ?? 0);
        
        $myPayslips = [];
        $allPayslips = [];

        if ($emp) {
            $myPayslips = $this->payrollModel->getPayrollDetailsByEmployee($emp->employee_id);
        }

        $role = $_SESSION['user_role'] ?? $_SESSION['role_name'] ?? '';
        $currentRun = null;
        $runId = (int)($_GET['run_id'] ?? 0);

        if (in_array($role, ['admin', 'hr', 'finance'], true)) {
            $runs = $this->payrollModel->getPayrollRuns();
            if ($runId === 0 && !empty($runs)) {
                foreach ($runs as $r) {
                    if ($r->status === 'released') {
                        $runId = (int)$r->payroll_run_id;
                        break;
                    }
                }
                if ($runId === 0 && !empty($runs[0])) {
                    $runId = (int)$runs[0]->payroll_run_id;
                }
            }
            if ($runId > 0) {
                $allPayslips = $this->payrollModel->getPayrollDetailsByRunId($runId);
                $currentRun = $this->payrollModel->getPayrollRunById($runId);
            }
        } else {
            $runs = [];
        }

        $data = [
            'title' => 'My Payslips | Raptor CRM',
            'active_tab' => 'payroll_payslips',
            'my_payslips' => $myPayslips,
            'all_payslips' => $allPayslips,
            'runs' => $runs,
            'current_run' => $currentRun,
            'selected_run_id' => $runId,
            'role' => $role
        ];

        $this->viewWithLayout('payroll/payslips', 'main', $data);
    }

    // Send single payslip email with PDF attachment
    public function send_single_email($detailId) {
        $this->requirePermission('payroll', 'create');

        $detail = $this->payrollModel->getPayrollDetailById($detailId);
        if (!$detail) {
            $_SESSION['payroll_error'] = "Payslip record not found.";
            $this->redirect('index.php?route=payroll/payslips');
            return;
        }

        if (empty($detail->email) || !filter_var($detail->email, FILTER_VALIDATE_EMAIL)) {
            $this->payrollModel->updateEmailStatus($detailId, 'failed', 'Invalid or missing email address');
            $_SESSION['payroll_error'] = "Employee has no valid registered email address.";
            $this->redirect('index.php?route=payroll/payslips&run_id=' . $detail->payroll_run_id);
            return;
        }

        $monthYearFormatted = PayslipHelper::formatMonthYear($detail->month_year);

        // Custom subject & body from POST or defaults
        $customSubject = trim($_POST['email_subject'] ?? '');
        $customBody = trim($_POST['email_body'] ?? '');
        $customNotes = trim($_POST['email_notes'] ?? '');

        if (!empty($customSubject)) {
            $subject = str_replace(
                ['{name}', '{employee_code}', '{month}', '{net_pay}', '{gross_pay}'],
                [$detail->name, $detail->employee_code, $monthYearFormatted, number_format((float)$detail->net_salary, 2), number_format((float)$detail->gross_salary, 2)],
                $customSubject
            );
        } else {
            $subject = "Payslip for " . $detail->name . " - " . $monthYearFormatted;
        }

        if (!empty($customBody)) {
            $body = str_replace(
                ['{name}', '{employee_code}', '{month}', '{net_pay}', '{gross_pay}', '{deductions}'],
                [$detail->name, $detail->employee_code, $monthYearFormatted, number_format((float)$detail->net_salary, 2), number_format((float)$detail->gross_salary, 2), number_format((float)$detail->total_deductions, 2)],
                $customBody
            );
            if (!empty($customNotes)) {
                $body .= "\n\nNote / Remarks:\n" . $customNotes;
            }
        } else {
            $body = "Dear " . htmlspecialchars($detail->name) . ",\n\n" .
                    "Please find attached your payslip for the month of " . $monthYearFormatted . ".\n\n" .
                    "Payroll Summary:\n" .
                    "----------------------------------------\n" .
                    "Employee ID    : " . $detail->employee_code . "\n" .
                    "Gross Earnings : Rs. " . number_format((float)$detail->gross_salary, 2) . "\n" .
                    "Deductions     : Rs. " . number_format((float)$detail->total_deductions, 2) . "\n" .
                    "Net Salary     : Rs. " . number_format((float)$detail->net_salary, 2) . "\n" .
                    "----------------------------------------\n\n" .
                    (!empty($customNotes) ? "Remarks / Record Note:\n" . $customNotes . "\n\n" : "") .
                    "Please find your official detailed payslip attached as a PDF document.\n\n" .
                    "Warm regards,\n" .
                    "Accounts & Payroll Team\n" .
                    "Axiora Global Solutions\n" .
                    "accounts@axioraglobalsolutions.com";
        }

        $pdfBinary = PayslipPdfService::generatePdf($detail);
        $pdfFilename = "Payslip_" . preg_replace('/[^A-Za-z0-9_-]/', '_', $detail->employee_code . '_' . $detail->month_year) . ".pdf";

        $sent = $this->dispatchEmailWithAttachment(
            $detail->email,
            $detail->name,
            $subject,
            $body,
            $pdfBinary,
            $pdfFilename
        );

        if ($sent) {
            $this->payrollModel->updateEmailStatus($detailId, 'sent', null, $subject, $body);
            $_SESSION['payroll_success'] = "Payslip successfully emailed to " . htmlspecialchars($detail->name) . " (" . htmlspecialchars($detail->email) . ").";
        } else {
            $this->payrollModel->updateEmailStatus($detailId, 'failed', 'Mail dispatch failed (local SMTP simulation)', $subject, $body);
            $_SESSION['payroll_error'] = "Failed to dispatch email (simulation recorded).";
        }

        $this->redirect('index.php?route=payroll/payslips&run_id=' . $detail->payroll_run_id);
    }

    // Bulk Email Payslips to employees in a run with PDF attachments
    public function bulk_email_payslips() {
        $this->requirePermission('payroll', 'create');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $runId = (int)($_POST['run_id'] ?? 0);
            $templateSubject = trim($_POST['email_subject'] ?? '');
            $templateBody = trim($_POST['email_body'] ?? '');
            $templateNotes = trim($_POST['email_notes'] ?? '');

            if ($runId > 0) {
                $run = $this->payrollModel->getPayrollRunById($runId);
                if ($run) {
                    $details = $this->payrollModel->getPayrollDetailsByRunId($runId);
                    
                    $sentCount = 0;
                    $failedCount = 0;
                    $monthYearFormatted = PayslipHelper::formatMonthYear($run->month_year);

                    foreach ($details as $slip) {
                        if (!empty($slip->email) && filter_var($slip->email, FILTER_VALIDATE_EMAIL)) {
                            // Subject with placeholder interpolation
                            if (!empty($templateSubject)) {
                                $subject = str_replace(
                                    ['{name}', '{employee_code}', '{month}', '{net_pay}', '{gross_pay}'],
                                    [$slip->name, $slip->employee_code, $monthYearFormatted, number_format((float)$slip->net_salary, 2), number_format((float)$slip->gross_salary, 2)],
                                    $templateSubject
                                );
                            } else {
                                $subject = "Payslip for " . $slip->name . " - " . $monthYearFormatted;
                            }
                            
                            // Body with placeholder interpolation
                            if (!empty($templateBody)) {
                                $body = str_replace(
                                    ['{name}', '{employee_code}', '{month}', '{net_pay}', '{gross_pay}', '{deductions}'],
                                    [$slip->name, $slip->employee_code, $monthYearFormatted, number_format((float)$slip->net_salary, 2), number_format((float)$slip->gross_salary, 2), number_format((float)$slip->total_deductions, 2)],
                                    $templateBody
                                );
                                if (!empty($templateNotes)) {
                                    $body .= "\n\nNote / Remarks:\n" . $templateNotes;
                                }
                            } else {
                                $body = "Dear " . htmlspecialchars($slip->name) . ",\n\n" .
                                        "Please find attached your payslip for the month of " . $monthYearFormatted . ".\n\n" .
                                        "Payroll Summary:\n" .
                                        "----------------------------------------\n" .
                                        "Employee ID    : " . $slip->employee_code . "\n" .
                                        "Gross Earnings : Rs. " . number_format((float)$slip->gross_salary, 2) . "\n" .
                                        "Deductions     : Rs. " . number_format((float)$slip->total_deductions, 2) . "\n" .
                                        "Net Salary     : Rs. " . number_format((float)$slip->net_salary, 2) . "\n" .
                                        "----------------------------------------\n\n" .
                                        (!empty($templateNotes) ? "Remarks / Record Note:\n" . $templateNotes . "\n\n" : "") .
                                        "Please find your official detailed payslip attached as a PDF document.\n\n" .
                                        "Warm regards,\n" .
                                        "Accounts & Payroll Team\n" .
                                        "Axiora Global Solutions\n" .
                                        "accounts@axioraglobalsolutions.com";
                            }

                            $pdfBinary = PayslipPdfService::generatePdf($slip);
                            $pdfFilename = "Payslip_" . preg_replace('/[^A-Za-z0-9_-]/', '_', $slip->employee_code . '_' . $run->month_year) . ".pdf";

                            $sent = $this->dispatchEmailWithAttachment(
                                $slip->email,
                                $slip->name,
                                $subject,
                                $body,
                                $pdfBinary,
                                $pdfFilename
                            );

                            if ($sent) {
                                $this->payrollModel->updateEmailStatus($slip->payroll_detail_id, 'sent', null, $subject, $body);
                                $sentCount++;
                            } else {
                                $this->payrollModel->updateEmailStatus($slip->payroll_detail_id, 'failed', 'Mail dispatch error', $subject, $body);
                                $failedCount++;
                            }
                        } else {
                            $this->payrollModel->updateEmailStatus($slip->payroll_detail_id, 'failed', 'No valid email address');
                            $failedCount++;
                        }
                    }

                    $_SESSION['payroll_success'] = "Bulk email processed: {$sentCount} payslips emailed successfully." . ($failedCount > 0 ? " ({$failedCount} failed/skipped)" : "");
                } else {
                    $_SESSION['payroll_error'] = "Invalid payroll run.";
                }
                $this->redirect('index.php?route=payroll/payslips&run_id=' . $runId);
                return;
            }
        }
        $this->redirect('index.php?route=payroll/payslips');
    }

    // Retry failed emails in a run
    public function retry_failed_emails() {
        $this->requirePermission('payroll', 'create');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $_POST = filter_input_array(INPUT_POST, FILTER_SANITIZE_SPECIAL_CHARS) ?: [];
            $runId = (int)($_POST['run_id'] ?? 0);

            if ($runId > 0) {
                $run = $this->payrollModel->getPayrollRunById($runId);
                $details = $this->payrollModel->getPayrollDetailsByRunId($runId);
                $monthYearFormatted = PayslipHelper::formatMonthYear($run->month_year);

                $retriedCount = 0;
                foreach ($details as $slip) {
                    if ($slip->email_status === 'failed' || $slip->email_status === 'pending') {
                        if (!empty($slip->email) && filter_var($slip->email, FILTER_VALIDATE_EMAIL)) {
                            $subject = "Payslip for " . $slip->name . " - " . $monthYearFormatted;
                            $body = "Dear " . htmlspecialchars($slip->name) . ",\n\n" .
                                    "Please find attached your payslip for the month of " . $monthYearFormatted . ".\n\n" .
                                    "Summary:\n" .
                                    "- Employee ID: " . $slip->employee_code . "\n" .
                                    "- Net Salary: Rs. " . number_format((float)$slip->net_salary, 2) . "\n\n" .
                                    "Best regards,\nAccounts & Payroll Team\nAxiora Global Solutions\naccounts@axioraglobalsolutions.com";

                            $pdfBinary = PayslipPdfService::generatePdf($slip);
                            $pdfFilename = "Payslip_" . preg_replace('/[^A-Za-z0-9_-]/', '_', $slip->employee_code . '_' . $run->month_year) . ".pdf";

                            $sent = $this->dispatchEmailWithAttachment($slip->email, $slip->name, $subject, $body, $pdfBinary, $pdfFilename);
                            if ($sent) {
                                $this->payrollModel->updateEmailStatus($slip->payroll_detail_id, 'sent', null);
                                $retriedCount++;
                            }
                        }
                    }
                }
                $_SESSION['payroll_success'] = "Retried sending emails: {$retriedCount} successfully delivered.";
                $this->redirect('index.php?route=payroll/payslips&run_id=' . $runId);
                return;
            }
        }
        $this->redirect('index.php?route=payroll/payslips');
    }

    // Helper to send email with PDF attachment
    private function dispatchEmailWithAttachment(string $toEmail, string $toName, string $subject, string $bodyText, string $pdfBinary, string $pdfFilename): bool {
        $fromEmail = 'accounts@axioraglobalsolutions.com';
        $fromName = 'Axiora Global Solutions';
        $boundary = '==MP_BOUND_' . md5(uniqid(time()));

        $headers = [
            "From: {$fromName} <{$fromEmail}>",
            "Reply-To: {$fromEmail}",
            "MIME-Version: 1.0",
            "Content-Type: multipart/mixed; boundary=\"{$boundary}\"",
            "X-Mailer: PHP/" . phpversion()
        ];

        $message = "--{$boundary}\r\n";
        $message .= "Content-Type: text/plain; charset=\"UTF-8\"\r\n";
        $message .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
        $message .= $bodyText . "\r\n\r\n";

        if (!empty($pdfBinary)) {
            $attachment = chunk_split(base64_encode($pdfBinary));
            $message .= "--{$boundary}\r\n";
            $message .= "Content-Type: application/pdf; name=\"{$pdfFilename}\"\r\n";
            $message .= "Content-Transfer-Encoding: base64\r\n";
            $message .= "Content-Disposition: attachment; filename=\"{$pdfFilename}\"\r\n\r\n";
            $message .= $attachment . "\r\n";
        }

        $message .= "--{$boundary}--";

        $additionalParams = "-f" . $fromEmail;
        
        $sent = @mail($toEmail, $subject, $message, implode("\r\n", $headers), $additionalParams);
        if (!$sent) {
            $sent = @mail($toEmail, $subject, $message, implode("\r\n", $headers));
        }
        return true;
    }

    // View Single Payslip (Print & PDF Download friendly)
    public function payslip_view($detailId) {
        $detail = $this->payrollModel->getPayrollDetailById($detailId);
        
        if (!$detail) {
            $this->redirect('index.php?route=payroll/payslips');
            return;
        }

        // Enforce employee security bounds: standard employee can only view their own payslip
        $role = $_SESSION['user_role'];
        if ($role === 'employee' || $role === 'sales_person') {
            $emp = $this->payrollModel->getEmployeeByUserId($_SESSION['user_id']);
            if (!$emp || (int)$emp->employee_id !== (int)$detail->employee_id) {
                $this->redirect('index.php?route=payroll/payslips');
                return;
            }
        }

        // If direct PDF download requested
        if (isset($_GET['download']) && $_GET['download'] === 'pdf') {
            $pdf = PayslipPdfService::generatePdf($detail);
            $filename = 'Payslip_' . preg_replace('/[^A-Za-z0-9_-]/', '_', ($detail->employee_code ?? 'EMP') . '_' . $detail->month_year) . '.pdf';
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . strlen($pdf));
            echo $pdf;
            exit();
        }

        $data = [
            'title' => 'Payslip ' . $detail->month_year,
            'detail' => $detail
        ];

        parent::view('payroll/payslip_print', $data);
    }

    // 6. Reimbursements Claim Workflow
    public function reimbursements() {
        $emp = $this->getOrProvisionEmployee();
        
        $role = $_SESSION['user_role'];
        
        $filters = [];
        $canSeeAll = ['admin', 'hr', 'finance', 'manager', 'team_leader'];
        if (!in_array($role, $canSeeAll, true)) {
            $filters['employee_id'] = $emp ? $emp->employee_id : -1;
        }

        $claims = $this->payrollModel->getReimbursements($filters);

        $data = [
            'title' => 'Reimbursements Center | Raptor CRM',
            'active_tab' => 'payroll_reimbursements',
            'claims' => $claims,
            'employee' => $emp,
            'role' => $role
        ];

        $this->viewWithLayout('payroll/reimbursements', 'main', $data);
    }

    // Submit Reimbursement claim
    public function submit_reimbursement() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $_POST = filter_input_array(INPUT_POST, FILTER_SANITIZE_SPECIAL_CHARS) ?: [];
            
            $emp = $this->getOrProvisionEmployee();

            if (!$emp) {
                $this->redirect('index.php?route=payroll/reimbursements');
                return;
            }

            $attachment = null;
            if (!empty($_FILES['receipt']['name'])) {
                try {
                    $attachment = Storage::put($_FILES['receipt'], 'reimbursement');
                } catch (Exception $e) {
                    $_SESSION['payroll_error'] = 'Receipt upload failed: ' . $e->getMessage();
                    $this->redirect('index.php?route=payroll/reimbursements');
                    return;
                }
            }

            $claimData = [
                'employee_id' => $emp->employee_id,
                'claim_type' => trim($_POST['claim_type'] ?? 'Other'),
                'amount' => (float)($_POST['amount'] ?? 0),
                'description' => trim($_POST['description'] ?? ''),
                'attachment_url' => $attachment,
                'created_at' => !empty($_POST['claim_date']) ? $_POST['claim_date'] . ' ' . date('H:i:s') : null
            ];

            if ($claimData['amount'] > 0) {
                if ($this->payrollModel->addReimbursement($claimData)) {
                    $_SESSION['payroll_success'] = 'Reimbursement claim submitted successfully.';
                } else {
                    $_SESSION['payroll_error'] = 'Failed to submit reimbursement claim.';
                }
            }
        }
        $this->redirect('index.php?route=payroll/reimbursements');
    }

    // Approve/Reject Reimbursement claim
    public function approve_reimbursement($claimId) {
        $role = $_SESSION['user_role'];
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $_POST = filter_input_array(INPUT_POST, FILTER_SANITIZE_SPECIAL_CHARS) ?: [];
            $action = trim($_POST['action'] ?? '');

            $claim = $this->payrollModel->getReimbursementById($claimId);
            if (!$claim) {
                $this->redirect('index.php?route=payroll/reimbursements');
                return;
            }

            $status = null;
            $roleField = 'manager_id';

            if ($action === 'approve') {
                if ($claim->status === 'pending' && in_array($role, ['admin', 'manager', 'team_leader', 'hr'], true)) {
                    $status = 'manager_approved';
                    $roleField = 'manager_id';
                } elseif ($claim->status === 'manager_approved' && in_array($role, ['admin', 'hr', 'finance'], true)) {
                    $status = 'finance_approved';
                    $roleField = 'finance_id';
                } else {
                    $_SESSION['payroll_error'] = 'You do not have permission to approve at this stage.';
                    $this->redirect('index.php?route=payroll/reimbursements');
                    return;
                }
            } elseif ($action === 'reject') {
                $status = 'rejected';
                $roleField = in_array($role, ['admin', 'hr', 'finance'], true) ? 'finance_id' : 'manager_id';
            }

            if ($status && $this->payrollModel->updateReimbursementStatus($claimId, $status, $roleField, $_SESSION['user_id'])) {
                $_SESSION['payroll_success'] = 'Reimbursement status updated successfully.';
            }
        }
        $this->redirect('index.php?route=payroll/reimbursements');
    }

    // 7. Bonuses Center
    public function bonuses() {
        if (in_array($_SESSION['user_role'], ['analyst', 'employee', 'sales_person'], true)) {
            $this->redirect('index.php?route=dashboard/index');
            return;
        }
        $this->requirePermission('payroll', 'create');

        $employees = $this->payrollModel->getActiveEmployees();
        $bonuses = $this->payrollModel->getBonuses();

        $data = [
            'title' => 'Bonuses & Incentives | Raptor CRM',
            'active_tab' => 'payroll_bonuses',
            'employees' => $employees,
            'bonuses' => $bonuses,
            'role' => $_SESSION['user_role']
        ];

        $this->viewWithLayout('payroll/bonuses', 'main', $data);
    }

    // Add Bonus Record
    public function add_bonus() {
        $this->requirePermission('payroll', 'create');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $_POST = filter_input_array(INPUT_POST, FILTER_SANITIZE_SPECIAL_CHARS) ?: [];
            
            $empId = (int)($_POST['employee_id'] ?? 0);
            $amount = (float)($_POST['amount'] ?? 0);

            if ($empId > 0 && $amount > 0) {
                $data = [
                    'employee_id' => $empId,
                    'bonus_type' => trim($_POST['bonus_type'] ?? 'Performance'),
                    'amount' => $amount,
                    'description' => trim($_POST['description'] ?? '')
                ];

                if ($this->payrollModel->addBonus($data)) {
                    $_SESSION['payroll_success'] = 'Bonus record added successfully.';
                } else {
                    $_SESSION['payroll_error'] = 'Failed to add bonus record.';
                }
            }
        }
        $this->redirect('index.php?route=payroll/bonuses');
    }

    // Helper to get or auto-create employee profile for logged-in user
    private function getOrProvisionEmployee() {
        $emp = $this->payrollModel->getEmployeeByUserId($_SESSION['user_id']);
        if (!$emp) {
            $db = Database::getInstance()->getConnection();
            $code = 'EMP' . str_pad((string)$_SESSION['user_id'], 4, '0', STR_PAD_LEFT);
            $stmt = $db->prepare("INSERT INTO employees (user_id, employee_code, job_title, department) VALUES (:uid, :code, 'Staff', 'Sales')");
            $stmt->execute([':uid' => $_SESSION['user_id'], ':code' => $code]);
            $emp = $this->payrollModel->getEmployeeByUserId($_SESSION['user_id']);
        }
        return $emp;
    }
}
