<?php
// Raptor CRM Payroll, Bonuses & Reimbursements Controller

require_once APPROOT . '/core/PayslipHelper.php';
require_once APPROOT . '/core/PayslipPdfService.php';
require_once APPROOT . '/services/BulkImportService.php';
require_once APPROOT . '/services/EmailService.php';

class PayrollController extends Controller {
    private $payrollModel;
    private $userModel;

    public function __construct() {
        $this->requireAuth();
        $this->payrollModel = $this->model('Payroll');
        $this->userModel = $this->model('User');
    }

    public function index() {
        $this->dashboard();
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

    // ---------------- Bulk Upload Salary Structures ----------------

    /**
     * Download CSV template for salary structures.
     * Optional ?blank=1 returns an empty template with 2 sample rows.
     * Default includes all active employees with current compensation.
     */
    public function download_salary_template() {
        if (in_array($_SESSION['user_role'], ['analyst', 'employee', 'sales_person'], true)) {
            $this->redirect('index.php?route=dashboard/index');
            return;
        }
        $this->requirePermission('payroll', 'view');

        $isBlank = isset($_GET['blank']) && $_GET['blank'] == '1';
        $filename = 'salary_structures_template_' . ($isBlank ? 'blank' : 'active_employees') . '_' . date('Ymd') . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);

        $out = fopen('php://output', 'w');
        // UTF-8 BOM for Microsoft Excel compatibility
        fputs($out, "\xEF\xBB\xBF");

        $headers = [
            'Employee_Code',
            'Employee_Name',
            'Monthly_CTC',
            'Basic_Salary',
            'HRA',
            'Special_Allowance',
            'Other_Allowance',
            'Medical_Allowance',
            'Travel_Allowance',
            'Bonus',
            'Other_Earnings',
            'PF',
            'Employer_PF',
            'Gratuity',
            'ESIC',
            'Professional_Tax',
            'TDS',
            'Salary_Type',
            'Effective_Date',
            'Revision_Reason'
        ];
        fputcsv($out, $headers);

        if ($isBlank) {
            fputcsv($out, [
                'AGS26010',
                'Sample Employee One',
                '35000',
                '17500',
                '8750',
                '1600',
                '4508',
                '0',
                '0',
                '0',
                '0',
                '1800',
                '1800',
                '842',
                '0',
                '200',
                '0',
                'Monthly',
                date('Y-m-d'),
                'Annual Appraisal Revision'
            ]);
            fputcsv($out, [
                'AGS26011',
                'Sample Employee Two',
                '45000',
                '22500',
                '11250',
                '1600',
                '6768',
                '0',
                '0',
                '0',
                '0',
                '1800',
                '1800',
                '1082',
                '0',
                '200',
                '0',
                'Monthly',
                date('Y-m-d'),
                'Market Rate Adjustment'
            ]);
            fputcsv($out, [
                'AGS26019',
                'Sample Employee Three',
                '23000',
                '11500',
                '5750',
                '1600',
                '2217',
                '0',
                '0',
                '0',
                '0',
                '1380',
                '1380',
                '553',
                '0',
                '200',
                '0',
                'Monthly',
                date('Y-m-d'),
                'New Joiner Baseline Structure'
            ]);
        } else {
            $employees = $this->payrollModel->getActiveEmployees();
            foreach ($employees as $emp) {
                $struct = $this->payrollModel->getSalaryStructureByEmployeeId($emp->employee_id);
                $currBasic = $struct ? (float)$struct->basic_salary : round((float)($emp->salary ?? 35000) * 0.50, 2);
                $currHra = $struct ? (float)$struct->hra : round($currBasic * 0.50, 2);
                $currSpecial = $struct ? (float)$struct->special_allowance : 1600.00;
                $currMed = $struct ? (float)$struct->medical_allowance : 0.00;
                $currTravel = $struct ? (float)$struct->travel_allowance : 0.00;
                $currBonus = $struct ? (float)$struct->bonus : 0.00;
                $currOtherEarn = $struct ? (float)$struct->other_earnings : 0.00;
                $currPf = $struct ? (float)$struct->pf : (($currBasic >= 15000) ? 1800.00 : round($currBasic * 0.12, 2));
                $currEmpPf = $struct ? (float)($struct->employer_pf ?? $currPf) : $currPf;
                $currGratuity = $struct ? (float)($struct->gratuity ?? round($currBasic * 0.0481, 2)) : round($currBasic * 0.0481, 2);
                $currEsic = $struct ? (float)$struct->esic : 0.00;
                $currPt = $struct ? (float)$struct->professional_tax : 200.00;
                $currTds = $struct ? (float)$struct->tds : 0.00;
                $salaryType = $struct ? ($struct->salary_type ?? 'Monthly') : 'Monthly';

                $currGross = $currBasic + $currHra + $currSpecial + (float)($struct->other_allowance ?? 0) + $currMed + $currTravel + $currBonus + $currOtherEarn;
                $currCtc = round($currGross + $currEmpPf + $currGratuity);
                $currOther = $struct ? (float)($struct->other_allowance ?? 0) : max(0, round($currCtc - ($currBasic + $currHra + $currSpecial + $currEmpPf + $currGratuity)));

                fputcsv($out, [
                    $emp->employee_code,
                    $emp->name,
                    $currCtc,
                    $currBasic > 0 ? $currBasic : '17500',
                    $currHra,
                    $currSpecial,
                    $currOther,
                    $currMed,
                    $currTravel,
                    $currBonus,
                    $currOtherEarn,
                    $currPf,
                    $currEmpPf,
                    $currGratuity,
                    $currEsic,
                    $currPt,
                    $currTds,
                    $salaryType,
                    date('Y-m-d'),
                    'Bulk Salary Structure Update'
                ]);
            }
        }

        fclose($out);
        exit();
    }

    /**
     * AJAX Validate CSV and return parsed preview.
     */
    public function validate_bulk_structures() {
        if (in_array($_SESSION['user_role'], ['analyst', 'employee', 'sales_person'], true)) {
            $this->jsonError('Access Denied.', 403);
            return;
        }
        $this->requirePermission('payroll', 'edit');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonError('Invalid request method.', 405);
            return;
        }

        $csrf = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_GET['csrf_token'] ?? '';
        if (!$this->validateCsrfToken($csrf)) {
            $this->jsonError('Security Error: CSRF token validation failed.', 403);
            return;
        }

        if (empty($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
            $this->jsonError('No CSV file uploaded or an upload error occurred.');
            return;
        }

        $file = $_FILES['csv_file'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($ext !== 'csv') {
            $this->jsonError('Only CSV files are allowed.');
            return;
        }

        $defaultEffectiveDate = !empty($_POST['effective_date']) ? trim($_POST['effective_date']) : date('Y-m-d');
        $defaultReason = !empty($_POST['revision_reason']) ? trim($_POST['revision_reason']) : 'Bulk Salary Structure Upload';

        try {
            $importer = new BulkImportService();
            $rawRows = $importer->parseCsv($file['tmp_name']);

            if (empty($rawRows)) {
                $this->jsonError('The uploaded CSV file is empty or contains only headers.');
                return;
            }

            // Fetch active employees and create lookup indexes
            $activeEmployees = $this->payrollModel->getActiveEmployees();
            $codeMap = [];
            $emailMap = [];
            $idMap = [];

            foreach ($activeEmployees as $emp) {
                if (!empty($emp->employee_code)) {
                    $codeMap[strtolower(trim($emp->employee_code))] = $emp;
                }
                if (!empty($emp->email)) {
                    $emailMap[strtolower(trim($emp->email))] = $emp;
                }
                $idMap[(int)$emp->employee_id] = $emp;
            }

            $aliasMap = [
                'employee_code' => ['employee_code', 'emp_code', 'emp_id', 'employee_id', 'code', 'id', 'empid'],
                'email' => ['email', 'email_address', 'emp_email', 'employee_email', 'mail'],
                'employee_name' => ['employee_name', 'name', 'full_name', 'emp_name'],
                'ctc' => ['ctc', 'monthly_ctc', 'cost_to_company', 'monthly_cost_to_company'],
                'basic_salary' => ['basic_salary', 'basic', 'basic_pay', 'basic_sal', 'abasic', 'actual_basic'],
                'hra' => ['hra', 'house_rent_allowance', 'h_r_a', 'ahra', 'actual_hra'],
                'special_allowance' => ['special_allowance', 'special', 'special_all', 'special_allow', 'asa', 'special_al'],
                'other_allowance' => ['other_allowance', 'other_allow', 'other_all', 'other_allowances', 'spec_allo', 'other_al'],
                'medical_allowance' => ['medical_allowance', 'medical', 'med_allowance', 'med'],
                'travel_allowance' => ['travel_allowance', 'travel', 'conveyance', 'conveyance_allowance', 'transport_allowance'],
                'bonus' => ['bonus', 'statutory_bonus', 'annual_bonus', 'incentive'],
                'other_earnings' => ['other_earnings', 'other_earning', 'additional_earnings'],
                'pf' => ['pf', 'provident_fund', 'epf', 'employee_pf'],
                'employer_pf' => ['employer_pf', 'company_pf', 'pf_employer', 'erpf', 'er_pf'],
                'gratuity' => ['gratuity', 'grat'],
                'esic' => ['esic', 'esi', 'employee_esic'],
                'professional_tax' => ['professional_tax', 'pt', 'prof_tax'],
                'tds' => ['tds', 'tax', 'income_tax', 'tds_deduction'],
                'salary_type' => ['salary_type', 'type', 'wage_type'],
                'effective_date' => ['effective_date', 'rev_effective_date', 'date', 'pay_date'],
                'revision_reason' => ['revision_reason', 'reason', 'remarks', 'note']
            ];

            $processedRows = [];
            $structuredErrors = [];
            $validCount = 0;
            $updateCount = 0;
            $newCount = 0;
            $errorCount = 0;
            $seenInFile = [];

            foreach ($rawRows as $idx => $r) {
                $rowNumber = $idx + 2; // account for header line (1-indexed)
                $mapped = [];

                foreach ($aliasMap as $canonKey => $aliases) {
                    $foundVal = null;
                    foreach ($r as $colHeader => $cellVal) {
                        $cleanCol = preg_replace('/[^a-z0-9]+/', '_', strtolower(trim($colHeader)));
                        $cleanCol = trim($cleanCol, '_');
                        if ($cleanCol === $canonKey || in_array($cleanCol, $aliases, true)) {
                            $foundVal = trim($cellVal);
                            break;
                        }
                    }
                    $mapped[$canonKey] = $foundVal;
                }

                $errors = [];
                $emp = null;

                // 1. Identify Employee
                $rawCode = $mapped['employee_code'] ?? '';
                $rawEmail = $mapped['email'] ?? '';

                if (!empty($rawCode) && isset($codeMap[strtolower($rawCode)])) {
                    $emp = $codeMap[strtolower($rawCode)];
                } elseif (!empty($rawEmail) && isset($emailMap[strtolower($rawEmail)])) {
                    $emp = $emailMap[strtolower($rawEmail)];
                } elseif (is_numeric($rawCode) && isset($idMap[(int)$rawCode])) {
                    $emp = $idMap[(int)$rawCode];
                }

                if (!$emp) {
                    $identifier = !empty($rawCode) ? $rawCode : (!empty($rawEmail) ? $rawEmail : "(Blank Identifier)");
                    $errors['employee'] = "Employee '{$identifier}' not found in active employees directory.";
                }

                // Check duplicate inside file
                if ($emp) {
                    if (isset($seenInFile[$emp->employee_id])) {
                        $errors['duplicate'] = "Duplicate entry: Employee {$emp->name} ({$emp->employee_code}) appears multiple times in this CSV file.";
                    } else {
                        $seenInFile[$emp->employee_id] = true;
                    }
                }

                // 2. Validate Salary Numbers (Supports Monthly CTC or Component breakdown)
                $rawCtc = (isset($mapped['ctc']) && $mapped['ctc'] !== '' && is_numeric($mapped['ctc'])) ? (float)$mapped['ctc'] : 0.00;
                $rawBasic = $mapped['basic_salary'];

                if (($rawBasic === null || $rawBasic === '' || !is_numeric($rawBasic)) && $rawCtc > 0) {
                    // Derive Basic as 50% of CTC
                    $basic = round($rawCtc * 0.50, 2);
                } elseif ($rawBasic !== null && $rawBasic !== '' && is_numeric($rawBasic)) {
                    $basic = round((float)$rawBasic, 2);
                    if ($basic <= 0 && $rawCtc <= 0) {
                        $errors['basic_salary'] = "Basic salary must be greater than zero.";
                    }
                } else {
                    $errors['basic_salary'] = "Basic salary or Monthly CTC is required and must be a numeric value.";
                    $basic = 0.00;
                }

                // Standard Axiora formulas: HRA = 50% Basic, Special = 1600
                $hra = (isset($mapped['hra']) && $mapped['hra'] !== '' && is_numeric($mapped['hra']))
                    ? round((float)$mapped['hra'], 2)
                    : round($basic * 0.50, 2);

                $special = (isset($mapped['special_allowance']) && $mapped['special_allowance'] !== '' && is_numeric($mapped['special_allowance']))
                    ? round((float)$mapped['special_allowance'], 2)
                    : 1600.00;

                $empPf = (isset($mapped['employer_pf']) && $mapped['employer_pf'] !== '' && is_numeric($mapped['employer_pf']))
                    ? round((float)$mapped['employer_pf'], 2)
                    : (($basic >= 15000) ? 1800.00 : round($basic * 0.12, 2));

                $gratuity = (isset($mapped['gratuity']) && $mapped['gratuity'] !== '' && is_numeric($mapped['gratuity']))
                    ? round((float)$mapped['gratuity'], 2)
                    : round($basic * 0.0481, 2);

                $med = (isset($mapped['medical_allowance']) && $mapped['medical_allowance'] !== '' && is_numeric($mapped['medical_allowance']))
                    ? round((float)$mapped['medical_allowance'], 2)
                    : 0.00;

                $travel = (isset($mapped['travel_allowance']) && $mapped['travel_allowance'] !== '' && is_numeric($mapped['travel_allowance']))
                    ? round((float)$mapped['travel_allowance'], 2)
                    : 0.00;

                $bonus = (isset($mapped['bonus']) && $mapped['bonus'] !== '' && is_numeric($mapped['bonus']))
                    ? round((float)$mapped['bonus'], 2)
                    : 0.00;

                $otherEarn = (isset($mapped['other_earnings']) && $mapped['other_earnings'] !== '' && is_numeric($mapped['other_earnings']))
                    ? round((float)$mapped['other_earnings'], 2)
                    : 0.00;

                // Other Allowance: If provided in file use it; if CTC provided compute balancing figure
                if (isset($mapped['other_allowance']) && $mapped['other_allowance'] !== '' && is_numeric($mapped['other_allowance'])) {
                    $otherAll = round((float)$mapped['other_allowance'], 2);
                } elseif ($rawCtc > 0) {
                    $otherAll = max(0, round($rawCtc - ($basic + $hra + $special + $med + $travel + $bonus + $otherEarn + $empPf + $gratuity), 2));
                } else {
                    $otherAll = 0.00;
                }

                $gross = $basic + $hra + $special + $otherAll + $med + $travel + $bonus + $otherEarn;

                $pf = (isset($mapped['pf']) && $mapped['pf'] !== '' && is_numeric($mapped['pf']))
                    ? round((float)$mapped['pf'], 2)
                    : $empPf;

                $esic = (isset($mapped['esic']) && $mapped['esic'] !== '' && is_numeric($mapped['esic']))
                    ? round((float)$mapped['esic'], 2)
                    : 0.00;

                $pt = (isset($mapped['professional_tax']) && $mapped['professional_tax'] !== '' && is_numeric($mapped['professional_tax']))
                    ? round((float)$mapped['professional_tax'], 2)
                    : ($gross > 20000 ? 200.00 : ($gross > 15000 ? 150.00 : 0.00));

                $tds = (isset($mapped['tds']) && $mapped['tds'] !== '' && is_numeric($mapped['tds']))
                    ? round((float)$mapped['tds'], 2)
                    : 0.00;

                $totalDeductions = $pf + $esic + $pt + $tds;
                $netSalary = $gross - $totalDeductions;
                $ctc = $gross + $empPf + $gratuity;

                $salaryType = !empty($mapped['salary_type']) && in_array(ucfirst(strtolower($mapped['salary_type'])), ['Monthly', 'Hourly'], true)
                    ? ucfirst(strtolower($mapped['salary_type']))
                    : 'Monthly';

                $effectiveDate = !empty($mapped['effective_date']) ? trim($mapped['effective_date']) : $defaultEffectiveDate;
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $effectiveDate)) {
                    $effectiveDate = $defaultEffectiveDate;
                }

                $revisionReason = !empty($mapped['revision_reason']) ? trim($mapped['revision_reason']) : $defaultReason;

                // Check existing structure
                $existingStruct = $emp ? $this->payrollModel->getSalaryStructureByEmployeeId($emp->employee_id) : null;
                $action = $existingStruct ? 'update' : 'create';

                $prevGross = 0.00;
                $prevCtc = 0.00;
                if ($existingStruct) {
                    $prevGross = (float)$existingStruct->basic_salary + (float)$existingStruct->hra + (float)$existingStruct->special_allowance + (float)($existingStruct->other_allowance ?? 0) + (float)$existingStruct->medical_allowance + (float)$existingStruct->travel_allowance + (float)$existingStruct->bonus + (float)$existingStruct->other_earnings;
                    $prevCtc = $prevGross + (float)($existingStruct->employer_pf ?? $existingStruct->pf) + (float)($existingStruct->gratuity ?? round((float)$existingStruct->basic_salary * 0.0481, 2));
                }

                $status = empty($errors) ? 'valid' : 'error';
                if ($status === 'valid') {
                    $validCount++;
                    if ($action === 'update') {
                        $updateCount++;
                    } else {
                        $newCount++;
                    }
                } else {
                    $errorCount++;
                    foreach ($errors as $field => $msg) {
                        $structuredErrors[] = [
                            'row' => $rowNumber,
                            'field' => $field,
                            'message' => $msg
                        ];
                    }
                }

                $processedRows[] = [
                    'row' => $rowNumber,
                    'status' => $status,
                    'action' => $action,
                    'errors' => $errors,
                    'employee_id' => $emp ? $emp->employee_id : null,
                    'employee_code' => $emp ? $emp->employee_code : ($rawCode ?: 'N/A'),
                    'employee_name' => $emp ? $emp->name : ($mapped['employee_name'] ?: 'Unknown'),
                    'job_title' => $emp ? ($emp->job_title ?? '') : '',
                    'department' => $emp ? ($emp->department ?? '') : '',
                    'salary_type' => $salaryType,
                    'basic_salary' => $basic,
                    'hra' => $hra,
                    'special_allowance' => $special,
                    'other_allowance' => $otherAll,
                    'medical_allowance' => $med,
                    'travel_allowance' => $travel,
                    'bonus' => $bonus,
                    'other_earnings' => $otherEarn,
                    'gross_salary' => $gross,
                    'pf' => $pf,
                    'employer_pf' => $empPf,
                    'gratuity' => $gratuity,
                    'esic' => $esic,
                    'professional_tax' => $pt,
                    'tds' => $tds,
                    'total_deductions' => $totalDeductions,
                    'net_salary' => $netSalary,
                    'ctc' => $ctc,
                    'previous_gross' => $prevGross,
                    'previous_ctc' => $prevCtc,
                    'effective_date' => $effectiveDate,
                    'revision_reason' => $revisionReason
                ];
            }

            // Save in session for import confirmation
            $_SESSION['bulk_salary_rows'] = $processedRows;
            $_SESSION['bulk_salary_meta'] = [
                'filename' => htmlspecialchars($file['name']),
                'total_rows' => count($processedRows),
                'valid_count' => $validCount,
                'uploaded_at' => time()
            ];

            $this->json([
                'success' => true,
                'total_rows' => count($processedRows),
                'valid_count' => $validCount,
                'update_count' => $updateCount,
                'new_count' => $newCount,
                'error_count' => $errorCount,
                'rows' => $processedRows,
                'structured_errors' => $structuredErrors
            ]);
        } catch (Exception $e) {
            error_log("[Bulk Salary Structure Upload Error] " . $e->getMessage());
            $this->jsonError($e->getMessage(), 400);
        }
    }

    /**
     * AJAX Execute bulk import after user confirmation.
     */
    public function bulk_import_structures() {
        if (in_array($_SESSION['user_role'], ['analyst', 'employee', 'sales_person'], true)) {
            $this->jsonError('Access Denied.', 403);
            return;
        }
        $this->requirePermission('payroll', 'edit');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonError('Invalid request method.', 405);
            return;
        }

        $input = $this->jsonInput();
        $csrf = $input['csrf_token'] ?? $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_GET['csrf_token'] ?? '';
        if (!$this->validateCsrfToken($csrf)) {
            $this->jsonError('Security Error: CSRF token validation failed.', 403);
            return;
        }

        $duplicateStrategy = $input['duplicate_strategy'] ?? $_POST['duplicate_strategy'] ?? 'update';
        $batchEffectiveDate = $input['batch_effective_date'] ?? $_POST['batch_effective_date'] ?? date('Y-m-d');
        $batchReason = $input['batch_revision_reason'] ?? $_POST['batch_revision_reason'] ?? 'Bulk Salary Structure Upload';

        if (empty($_SESSION['bulk_salary_rows'])) {
            $this->jsonError('No validated upload data found in session. Please upload the CSV again.');
            return;
        }

        $rows = $_SESSION['bulk_salary_rows'];
        $validRows = array_filter($rows, function($r) {
            return $r['status'] === 'valid' && !empty($r['employee_id']);
        });

        if (empty($validRows)) {
            $this->jsonError('No valid salary structure rows found to import.');
            return;
        }

        $imported = 0;
        $updated = 0;
        $skipped = 0;
        $failed = 0;
        $errors = [];

        $db = Database::getInstance()->getConnection();
        $empSalaryUpdateStmt = $db->prepare('UPDATE employees SET salary = :sal WHERE employee_id = :emp_id');

        foreach ($validRows as $row) {
            $empId = (int)$row['employee_id'];
            $isUpdate = ($row['action'] === 'update');

            if ($isUpdate && $duplicateStrategy === 'skip') {
                $skipped++;
                continue;
            }

            $effectiveDate = !empty($row['effective_date']) ? $row['effective_date'] : $batchEffectiveDate;
            $reason = !empty($row['revision_reason']) ? $row['revision_reason'] : $batchReason;

            $formData = [
                'employee_id' => $empId,
                'salary_type' => $row['salary_type'] ?? 'Monthly',
                'basic_salary' => $row['basic_salary'],
                'hra' => $row['hra'],
                'special_allowance' => $row['special_allowance'],
                'other_allowance' => $row['other_allowance'],
                'medical_allowance' => $row['medical_allowance'],
                'travel_allowance' => $row['travel_allowance'],
                'bonus' => $row['bonus'],
                'other_earnings' => $row['other_earnings'],
                'pf' => $row['pf'],
                'employer_pf' => $row['employer_pf'],
                'gratuity' => $row['gratuity'],
                'esic' => $row['esic'],
                'professional_tax' => $row['professional_tax'],
                'tds' => $row['tds']
            ];

            try {
                if ($this->payrollModel->saveSalaryStructure($formData, $_SESSION['user_id'], $reason, $effectiveDate)) {
                    // Sync salary field on employees table
                    $empSalaryUpdateStmt->execute([
                        ':sal' => $row['gross_salary'],
                        ':emp_id' => $empId
                    ]);

                    if ($isUpdate) {
                        $updated++;
                    } else {
                        $imported++;
                    }
                } else {
                    $failed++;
                    $errors[] = [
                        'row' => $row['row'],
                        'employee_code' => $row['employee_code'],
                        'employee_name' => $row['employee_name'],
                        'message' => 'Failed to save salary structure to database.',
                        'basic_salary' => $row['basic_salary'],
                        'gross_salary' => $row['gross_salary']
                    ];
                }
            } catch (Exception $e) {
                $failed++;
                $errors[] = [
                    'row' => $row['row'],
                    'employee_code' => $row['employee_code'],
                    'employee_name' => $row['employee_name'],
                    'message' => $e->getMessage(),
                    'basic_salary' => $row['basic_salary'],
                    'gross_salary' => $row['gross_salary']
                ];
            }
        }

        // Store failed errors in session for error report download
        $_SESSION['bulk_salary_errors'] = $errors;

        // Log in import_logs
        try {
            $logStmt = $db->prepare('INSERT INTO import_logs (user_id, module, filename, rows_imported, rows_skipped, rows_failed) VALUES (:uid, :mod, :fn, :imp, :skip, :fail)');
            $logStmt->execute([
                ':uid' => $_SESSION['user_id'] ?? 0,
                ':mod' => 'salary_structures',
                ':fn' => $_SESSION['bulk_salary_meta']['filename'] ?? 'salary_structures_bulk.csv',
                ':imp' => ($imported + $updated),
                ':skip' => $skipped,
                ':fail' => $failed
            ]);
        } catch (Exception $e) {
            // Ignore logging failures
        }

        // Clean session rows
        unset($_SESSION['bulk_salary_rows']);
        unset($_SESSION['bulk_salary_meta']);

        $this->json([
            'success' => true,
            'imported' => $imported,
            'updated' => $updated,
            'total_processed' => ($imported + $updated),
            'skipped' => $skipped,
            'failed' => $failed,
            'errors' => $errors
        ]);
    }

    /**
     * Download CSV report of failed rows during salary structure import.
     */
    public function download_salary_import_errors() {
        if (in_array($_SESSION['user_role'], ['analyst', 'employee', 'sales_person'], true)) {
            $this->redirect('index.php?route=dashboard/index');
            return;
        }
        $this->requirePermission('payroll', 'view');

        $errors = $_SESSION['bulk_salary_errors'] ?? [];
        if (empty($errors)) {
            die("No error report available.");
        }

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=salary_structure_failed_rows_' . date('Ymd_His') . '.csv');

        $out = fopen('php://output', 'w');
        fputs($out, "\xEF\xBB\xBF");

        fputcsv($out, [
            'Row_Number',
            'Employee_Code',
            'Employee_Name',
            'Failure_Reason',
            'Basic_Salary',
            'Gross_Salary'
        ]);

        foreach ($errors as $err) {
            fputcsv($out, [
                $err['row'] ?? '',
                $err['employee_code'] ?? '',
                $err['employee_name'] ?? '',
                $err['message'] ?? 'Import failed',
                $err['basic_salary'] ?? '',
                $err['gross_salary'] ?? ''
            ]);
        }

        fclose($out);
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
            $actualHra = $struct ? (float)$struct->hra : round($actualBasic * 0.50);
            $actualSpecial = $struct ? (float)$struct->special_allowance : 1600.00;
            $actualOther = $struct ? (float)($struct->other_allowance ?? 0.00) : 0.00;
            $med = $struct ? (float)$struct->medical_allowance : 0.00;
            $travel = $struct ? (float)$struct->travel_allowance : 0.00;
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
            $presentDays = 30;
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
            $pf = $struct ? round((float)$struct->pf) : (($basic >= 15000) ? 1800 : round($basic * 0.12));
            $employerPf = $struct ? round((float)($struct->employer_pf ?? $pf)) : (($actualBasic >= 15000) ? 1800 : round($actualBasic * 0.12));
            $gratuity = $struct ? round((float)($struct->gratuity ?? round($actualBasic * 0.0481))) : round($actualBasic * 0.0481);
            $esic = $struct ? round((float)$struct->esic) : 0;
            $pt = $struct ? round((float)$struct->professional_tax) : (($actualBasic + $actualHra + $actualSpecial + $actualOther > 20000) ? 200 : 150);
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

                    // Email Integration: Automatically send payslips to employee emails upon generation
                    $mailConfig = EmailService::getConfig();
                    $formRequested = isset($_POST['send_email_on_generate']) ? ((int)$_POST['send_email_on_generate'] === 1) : null;
                    $autoSendEnabled = ($formRequested !== null) ? $formRequested : ($mailConfig['auto_send'] === '1');

                    $emailSummary = '';
                    if ($autoSendEnabled) {
                        $details = $this->payrollModel->getPayrollDetailsByRunId($runId);
                        $sentCount = 0;
                        $failedCount = 0;
                        $lastError = '';
                        foreach ($details as $slip) {
                            $res = EmailService::sendPayslip($slip);
                            if ($res['success']) {
                                $this->payrollModel->updateEmailStatus($slip->payroll_detail_id, 'sent', null, $res['subject'], $res['body']);
                                $sentCount++;
                            } else {
                                $lastError = $res['error'] ?? 'Mail dispatch error';
                                $this->payrollModel->updateEmailStatus($slip->payroll_detail_id, 'failed', $lastError, $res['subject'], $res['body']);
                                $failedCount++;
                            }
                        }
                        if ($sentCount > 0) {
                            $emailSummary = " Dispatched {$sentCount} salary slip email(s) with official PDF attachments.";
                        }
                        if ($failedCount > 0) {
                            $emailSummary .= " ({$failedCount} email(s) pending/failed" . (!empty($lastError) ? ": " . htmlspecialchars($lastError) : "") . ").";
                        }
                    }

                    $_SESSION['payroll_success'] = "Payroll run for " . PayslipHelper::formatMonthYear($month) . " calculated and synchronized successfully.{$emailSummary}";
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

                $emailSummary = '';
                if (!empty($_POST['send_email_on_release'])) {
                    $details = $this->payrollModel->getPayrollDetailsByRunId($runId);
                    $sentCount = 0;
                    $failedCount = 0;
                    $lastError = '';
                    foreach ($details as $slip) {
                        $res = EmailService::sendPayslip($slip);
                        if ($res['success']) {
                            $this->payrollModel->updateEmailStatus($slip->payroll_detail_id, 'sent', null, $res['subject'], $res['body']);
                            $sentCount++;
                        } else {
                            $lastError = $res['error'] ?? 'Mail dispatch error';
                            $this->payrollModel->updateEmailStatus($slip->payroll_detail_id, 'failed', $lastError, $res['subject'], $res['body']);
                            $failedCount++;
                        }
                    }
                    if ($sentCount > 0) {
                        $emailSummary = " Dispatched {$sentCount} payslip email(s) with official PDF attachments to employees.";
                    }
                    if ($failedCount > 0) {
                        $emailSummary .= " ({$failedCount} email(s) pending/failed" . (!empty($lastError) ? ": " . htmlspecialchars($lastError) : "") . ").";
                    }
                }

                $_SESSION['payroll_success'] = 'Payroll has been released and payslips are now available to employees.' . (!empty($emailSummary) ? ' ' . $emailSummary : '');
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
            $redirect = !empty($_POST['redirect_to_processing']) ? 'payroll/processing' : 'payroll/payslips';
            $this->redirect('index.php?route=' . $redirect . '&run_id=' . $detail->payroll_run_id);
            return;
        }

        // Custom subject & body from POST or defaults
        $customSubject = trim($_POST['email_subject'] ?? '');
        $customBody = trim($_POST['email_body'] ?? '');
        $customNotes = trim($_POST['email_notes'] ?? '');

        $res = EmailService::sendPayslip(
            $detail, 
            !empty($customSubject) ? $customSubject : null, 
            !empty($customBody) ? $customBody : null, 
            !empty($customNotes) ? $customNotes : null
        );

        if ($res['success']) {
            $this->payrollModel->updateEmailStatus($detailId, 'sent', null, $res['subject'], $res['body']);
            $_SESSION['payroll_success'] = "Payslip PDF successfully emailed to " . htmlspecialchars($detail->name) . " (" . htmlspecialchars($detail->email) . ").";
        } else {
            $errMsg = $res['error'] ?? 'Mail dispatch failed';
            $this->payrollModel->updateEmailStatus($detailId, 'failed', $errMsg, $res['subject'], $res['body']);
            $_SESSION['payroll_error'] = "Failed to dispatch payslip email: " . htmlspecialchars($errMsg);
        }

        $redirect = !empty($_POST['redirect_to_processing']) ? 'payroll/processing' : 'payroll/payslips';
        $this->redirect('index.php?route=' . $redirect . '&run_id=' . $detail->payroll_run_id);
    }

    // Bulk Email Payslips to employees in a run with PDF attachments
    public function bulk_email_payslips() {
        $this->requirePermission('payroll', 'create');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $runId = (int)($_POST['run_id'] ?? 0);
            $templateSubject = trim($_POST['email_subject'] ?? '');
            $templateBody = trim($_POST['email_body'] ?? '');
            $templateNotes = trim($_POST['email_notes'] ?? '');
            $redirectTarget = !empty($_POST['redirect_to_processing']) ? 'payroll/processing' : 'payroll/payslips';

            if ($runId > 0) {
                $run = $this->payrollModel->getPayrollRunById($runId);
                if ($run) {
                    $details = $this->payrollModel->getPayrollDetailsByRunId($runId);
                    
                    $sentCount = 0;
                    $failedCount = 0;
                    $lastError = '';

                    foreach ($details as $slip) {
                        $res = EmailService::sendPayslip(
                            $slip,
                            !empty($templateSubject) ? $templateSubject : null,
                            !empty($templateBody) ? $templateBody : null,
                            !empty($templateNotes) ? $templateNotes : null
                        );

                        if ($res['success']) {
                            $this->payrollModel->updateEmailStatus($slip->payroll_detail_id, 'sent', null, $res['subject'], $res['body']);
                            $sentCount++;
                        } else {
                            $lastError = $res['error'] ?? 'Delivery failed';
                            $this->payrollModel->updateEmailStatus($slip->payroll_detail_id, 'failed', $lastError, $res['subject'], $res['body']);
                            $failedCount++;
                        }
                    }

                    $_SESSION['payroll_success'] = "Bulk email processed: {$sentCount} payslips emailed successfully." . ($failedCount > 0 ? " ({$failedCount} failed/skipped" . (!empty($lastError) ? ": " . htmlspecialchars($lastError) : "") . ")" : "");
                } else {
                    $_SESSION['payroll_error'] = "Invalid payroll run.";
                }
                $this->redirect('index.php?route=' . $redirectTarget . '&run_id=' . $runId);
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
            $redirectTarget = !empty($_POST['redirect_to_processing']) ? 'payroll/processing' : 'payroll/payslips';

            if ($runId > 0) {
                $run = $this->payrollModel->getPayrollRunById($runId);
                $details = $this->payrollModel->getPayrollDetailsByRunId($runId);

                $retriedCount = 0;
                $failedCount = 0;
                $lastError = '';

                foreach ($details as $slip) {
                    if ($slip->email_status === 'failed' || $slip->email_status === 'pending') {
                        $res = EmailService::sendPayslip($slip);
                        if ($res['success']) {
                            $this->payrollModel->updateEmailStatus($slip->payroll_detail_id, 'sent', null, $res['subject'], $res['body']);
                            $retriedCount++;
                        } else {
                            $lastError = $res['error'] ?? 'Delivery failed';
                            $this->payrollModel->updateEmailStatus($slip->payroll_detail_id, 'failed', $lastError, $res['subject'], $res['body']);
                            $failedCount++;
                        }
                    }
                }
                $_SESSION['payroll_success'] = "Retried sending emails: {$retriedCount} delivered successfully." . ($failedCount > 0 ? " ({$failedCount} still failed" . (!empty($lastError) ? ": " . htmlspecialchars($lastError) : "") . ")" : "");
                $this->redirect('index.php?route=' . $redirectTarget . '&run_id=' . $runId);
                return;
            }
        }
        $this->redirect('index.php?route=payroll/payslips');
    }

    // Helper to send email with PDF attachment
    private function dispatchEmailWithAttachment(string $toEmail, string $toName, string $subject, string $bodyText, string $pdfBinary, string $pdfFilename): bool {
        $attachments = [];
        if (!empty($pdfBinary)) {
            $attachments[] = [
                'name'    => $pdfFilename,
                'content' => $pdfBinary,
                'type'    => 'application/pdf'
            ];
        }

        $res = EmailService::send($toEmail, $toName, $subject, nl2br(htmlspecialchars($bodyText)), $bodyText, $attachments);
        return $res['success'];
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
