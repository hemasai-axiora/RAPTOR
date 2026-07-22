<?php
// Raptor CRM Payroll, Bonuses & Reimbursements Controller

class PayrollController extends Controller {
    private $payrollModel;
    private $userModel;

    public function __construct() {
        $this->requireAuth();
        $this->requirePermission('payroll', 'view');
        $this->payrollModel = $this->model('Payroll');
        $this->userModel = $this->model('User');
    }

    // 1. Payroll Dashboard
    public function dashboard() {
        $this->requirePermission('payroll', 'view');

        // Fetch payroll runs for overview
        $runs = $this->payrollModel->getPayrollRuns();
        $reimbursements = $this->payrollModel->getReimbursements(['status' => 'pending']);
        
        $data = [
            'title' => 'Payroll Dashboard | Raptor CRM',
            'active_tab' => 'payroll_dashboard',
            'runs' => $runs,
            'pending_claims' => count($reimbursements),
            'role' => $role
        ];

        $this->viewWithLayout('payroll/dashboard', 'main', $data);
    }

    // 2. Salary Structures Setup
    public function structures() {
        $this->requirePermission('payroll', 'edit');

        $employees = $this->payrollModel->getActiveEmployees();

        // Get salary structure for selected employee if any
        $selectedEmpId = (int)($_GET['employee_id'] ?? 0);
        $structure = null;
        if ($selectedEmpId > 0) {
            $structure = $this->payrollModel->getSalaryStructureByEmployeeId($selectedEmpId);
        }

        $data = [
            'title' => 'Salary Structures | Raptor CRM',
            'active_tab' => 'payroll_structures',
            'employees' => $employees,
            'selected_employee_id' => $selectedEmpId,
            'structure' => $structure
        ];

        $this->viewWithLayout('payroll/structures', 'main', $data);
    }

    // Save/Update Salary Structure
    public function save_structure() {
        $this->requirePermission('payroll', 'edit');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $_POST = filter_input_array(INPUT_POST, FILTER_SANITIZE_SPECIAL_CHARS) ?: [];
            
            $empId = (int)($_POST['employee_id'] ?? 0);
            if ($empId > 0) {
                $formData = [
                    'employee_id' => $empId,
                    'salary_type' => trim($_POST['salary_type'] ?? 'Monthly'),
                    'basic_salary' => (float)($_POST['basic_salary'] ?? 0),
                    'hra' => (float)($_POST['hra'] ?? 0),
                    'special_allowance' => (float)($_POST['special_allowance'] ?? 0),
                    'medical_allowance' => (float)($_POST['medical_allowance'] ?? 0),
                    'travel_allowance' => (float)($_POST['travel_allowance'] ?? 0),
                    'bonus' => (float)($_POST['bonus'] ?? 0),
                    'other_earnings' => (float)($_POST['other_earnings'] ?? 0),
                    'pf' => (float)($_POST['pf'] ?? 0),
                    'esic' => (float)($_POST['esic'] ?? 0),
                    'professional_tax' => (float)($_POST['professional_tax'] ?? 0),
                    'tds' => (float)($_POST['tds'] ?? 0)
                ];

                if ($this->payrollModel->saveSalaryStructure($formData)) {
                    // Update salary field on employees table as well
                    $db = Database::getInstance()->getConnection();
                    $stmt = $db->prepare('UPDATE employees SET salary = :sal WHERE employee_id = :emp_id');
                    $stmt->execute([
                        ':sal' => $formData['basic_salary'] + $formData['hra'] + $formData['special_allowance'] + $formData['medical_allowance'] + $formData['travel_allowance'] + $formData['other_earnings'],
                        ':emp_id' => $empId
                    ]);

                    $_SESSION['payroll_success'] = 'Salary structure saved successfully.';
                } else {
                    $_SESSION['payroll_error'] = 'Failed to save salary structure.';
                }
                $this->redirect('index.php?route=payroll/structures&employee_id=' . $empId);
                return;
            }
        }
        $this->redirect('index.php?route=payroll/structures');
    }

    // 3. Process Payroll Runs
    public function processing() {
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
            'role' => $role
        ];

        $this->viewWithLayout('payroll/processing', 'main', $data);
    }

    // Start/Generate payroll run for a month
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

                // Check if run already exists
                $existing = $this->payrollModel->getPayrollRunByMonth($month);
                if ($existing && $existing->status !== 'generated') {
                    $_SESSION['payroll_error'] = "Payroll for {$month} is in " . strtoupper($existing->status) . " status and cannot be regenerated.";
                    $this->redirect('index.php?route=payroll/processing&run_id=' . $existing->payroll_run_id);
                    return;
                }

                $db = Database::getInstance()->getConnection();
                if ($existing) {
                    $runId = $existing->payroll_run_id;
                    // Delete existing details so we can recalculate/regenerate them
                    $stmt = $db->prepare("DELETE FROM payroll_details WHERE payroll_run_id = :rid");
                    $stmt->execute([':rid' => $runId]);
                } else {
                    $runId = $this->payrollModel->createPayrollRun($month, $_SESSION['user_id']);
                }

                if ($runId) {
                    $employees = $this->payrollModel->getActiveEmployees();
                    
                    // Simple auto calculations per employee based on salary structures and mock attendance
                    foreach ($employees as $emp) {
                        $struct = $this->payrollModel->getSalaryStructureByEmployeeId($emp->employee_id);
                        
                        // Default structures if empty
                        $basic = $struct ? (float)$struct->basic_salary : 15000.00;
                        $hra = $struct ? (float)$struct->hra : 5000.00;
                        $special = $struct ? (float)$struct->special_allowance : 2000.00;
                        $med = $struct ? (float)$struct->medical_allowance : 1250.00;
                        $travel = $struct ? (float)$struct->travel_allowance : 1600.00;
                        $bonus = $struct ? (float)$struct->bonus : 0.00;
                        $other = $struct ? (float)$struct->other_earnings : 0.00;

                        // Add approved reimbursements for this employee and month
                        $reimbSum = 0.00;
                        try {
                            $db = Database::getInstance()->getConnection();
                            $reimbStmt = $db->prepare("SELECT SUM(amount) FROM reimbursements WHERE employee_id = :emp_id AND status = 'finance_approved' AND created_at LIKE :month");
                            $reimbStmt->execute([':emp_id' => $emp->employee_id, ':month' => $month . '-%']);
                            $reimbSum = (float)$reimbStmt->fetchColumn();
                        } catch (Exception $e) {}
                        $other += $reimbSum;
                        
                        $pf = $struct ? (float)$struct->pf : ($basic * 0.12);
                        $esic = $struct ? (float)$struct->esic : ($basic * 0.0075);
                        $pt = $struct ? (float)$struct->professional_tax : 200.00;
                        $tds = $struct ? (float)$struct->tds : 0.00;

                        // Mock present/absent days from attendance logs if available
                        $presentDays = 22; // fallback defaults
                        $absentDays = 0;
                        $workingDays = 22;

                        try {
                            $db = Database::getInstance()->getConnection();
                            $attStmt = $db->prepare("SELECT COUNT(*) FROM attendance WHERE user_id = :uid AND status = 'present' AND date_stamp LIKE :month");
                            $attStmt->execute([':uid' => $emp->user_id, ':month' => $month . '-%']);
                            $presentCount = (int)$attStmt->fetchColumn();
                            if ($presentCount > 0) {
                                $presentDays = $presentCount;
                            }
                        } catch (Exception $e) {}

                        // Gross & Net computations
                        $gross = $basic + $hra + $special + $med + $travel + $bonus + $other;
                        $deductions = $pf + $esic + $pt + $tds;
                        $net = $gross - $deductions;

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
                            'medical_allowance' => $med,
                            'travel_allowance' => $travel,
                            'bonus' => $bonus,
                            'other_earnings' => $other,
                            'pf' => $pf,
                            'esic' => $esic,
                            'professional_tax' => $pt,
                            'tds' => $tds,
                            'gross_salary' => $gross,
                            'total_deductions' => $deductions,
                            'net_salary' => $net
                        ];

                        $this->payrollModel->savePayrollDetails($detailData);
                    }

                    $_SESSION['payroll_success'] = "Payroll run for {$month} initialized successfully.";
                    $this->redirect('index.php?route=payroll/processing&run_id=' . $runId);
                    return;
                }
            }
        }
        $this->redirect('index.php?route=payroll/processing');
    }

    // Approve payroll run (Admin/HR/Finance can approve prep)
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

    // Lock payroll run (Finance only)
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

    // Release payslips and execute payments (Finance only)
    public function run_release($runId) {
        $this->requirePermission('payroll', 'approve');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if ($this->payrollModel->updateRunStatus($runId, 'released', 'released_by', $_SESSION['user_id'])) {
                // Mark all line items as paid
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

        // Generate CSV output stream
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=bank_transfer_' . $run->month_year . '.csv');

        $output = fopen('php://output', 'w');
        
        // CSV headers
        fputcsv($output, ['Serial No', 'Employee ID', 'Account Holder Name', 'Bank Name', 'Account Number', 'IFSC Code', 'Amount', 'Transfer Type']);

        $i = 1;
        foreach ($details as $row) {
            fputcsv($output, [
                $i++,
                $row->employee_code,
                $row->account_holder_name ?: $row->name,
                $row->bank_name ?: 'N/A',
                $row->account_number ? "'" . $row->account_number : 'N/A', // quote account number to preserve leading zeros
                $row->ifsc_code ?: 'N/A',
                number_format((float)$row->net_salary, 2, '.', ''),
                'NEFT'
            ]);
        }

        fclose($output);
        exit();
    }

    // 4. Employee Payslips Directory
    public function payslips() {
        $emp = $this->payrollModel->getEmployeeByUserId($_SESSION['user_id']);
        
        $myPayslips = [];
        $allPayslips = [];

        if ($emp) {
            $myPayslips = $this->payrollModel->getPayrollDetailsByEmployee($emp->employee_id);
        }

        $role = $_SESSION['user_role'];
        if (in_array($role, ['admin', 'hr', 'finance'], true)) {
            // Can see all runs
            $runs = $this->payrollModel->getPayrollRuns();
            $runId = (int)($_GET['run_id'] ?? 0);
            if ($runId > 0) {
                $allPayslips = $this->payrollModel->getPayrollDetailsByRunId($runId);
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
            'selected_run_id' => $_GET['run_id'] ?? 0,
            'role' => $role
        ];

        $this->viewWithLayout('payroll/payslips', 'main', $data);
    }

    // Bulk Email Payslips to employees in a run
    public function bulk_email_payslips() {
        $this->requirePermission('payroll', 'create');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $_POST = filter_input_array(INPUT_POST, FILTER_SANITIZE_SPECIAL_CHARS) ?: [];
            $runId = (int)($_POST['run_id'] ?? 0);

            if ($runId > 0) {
                $run = $this->payrollModel->getPayrollRunById($runId);
                if ($run) {
                    $details = $this->payrollModel->getPayrollDetailsByRunId($runId);
                    
                    $sentCount = 0;
                    foreach ($details as $slip) {
                        if (!empty($slip->email) && filter_var($slip->email, FILTER_VALIDATE_EMAIL)) {
                            $subject = "Payslip Released for " . $run->month_year;
                            $payslipUrl = URLROOT . '/index.php?route=payroll/payslip_view/' . $slip->payroll_detail_id;
                            
                            $body = "Hello " . htmlspecialchars($slip->name) . ",\n\n" .
                                    "Your payslip for the payroll period " . $run->month_year . " has been released.\n\n" .
                                    "Summary:\n" .
                                    "- Employee Code: " . $slip->employee_code . "\n" .
                                    "- Gross Salary: Rs. " . number_format((float)$slip->gross_salary, 2) . "\n" .
                                    "- Total Deductions: Rs. " . number_format((float)$slip->total_deductions, 2) . "\n" .
                                    "- Net Salary: Rs. " . number_format((float)$slip->net_salary, 2) . "\n\n" .
                                    "Please click the link below to view or print your detailed payslip:\n" .
                                    $payslipUrl . "\n\n" .
                                    "Thank you!\n\n" .
                                    "Best regards,\nRaptor Payroll Team";
                            
                            @mail($slip->email, $subject, $body);
                            $sentCount++;
                        }
                    }
                    $_SESSION['payroll_success'] = "Successfully emailed payslips to {$sentCount} employees.";
                } else {
                    $_SESSION['payroll_error'] = "Invalid payroll run.";
                }
                $this->redirect('index.php?route=payroll/payslips&run_id=' . $runId);
                return;
            }
        }
        $this->redirect('index.php?route=payroll/payslips');
    }

    // View Single Payslip (Print/Download friendly)
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

        $data = [
            'title' => 'Payslip ' . $detail->month_year,
            'detail' => $detail
        ];

        // Clean rendering suitable for browser print-to-pdf
        parent::view('payroll/payslip_print', $data);
    }

    // 5. Reimbursements Claim Workflow
    public function reimbursements() {
        $emp = $this->getOrProvisionEmployee();
        
        $role = $_SESSION['user_role'];
        
        // Filters — roles who can see/action all claims
        $filters = [];
        $canSeeAll = ['admin', 'hr', 'finance', 'manager', 'team_leader'];
        if (!in_array($role, $canSeeAll, true)) {
            // Pure employees only see their own claims
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
            $action = trim($_POST['action'] ?? ''); // 'approve' or 'reject'

            $claim = $this->payrollModel->getReimbursementById($claimId);
            if (!$claim) {
                $this->redirect('index.php?route=payroll/reimbursements');
                return;
            }

            $status    = null;
            $roleField = 'manager_id';

            if ($action === 'approve') {
                // Step 1: pending → manager_approved (manager/admin/team_leader)
                if ($claim->status === 'pending' && in_array($role, ['admin', 'manager', 'team_leader', 'hr'], true)) {
                    $status    = 'manager_approved';
                    $roleField = 'manager_id';
                // Step 2: manager_approved → finance_approved (finance/admin/hr)
                } elseif ($claim->status === 'manager_approved' && in_array($role, ['admin', 'hr', 'finance'], true)) {
                    $status    = 'finance_approved';
                    $roleField = 'finance_id';
                } else {
                    $_SESSION['payroll_error'] = 'You do not have permission to approve at this stage.';
                    $this->redirect('index.php?route=payroll/reimbursements');
                    return;
                }
            } elseif ($action === 'reject') {
                $status    = 'rejected';
                $roleField = in_array($role, ['admin', 'hr', 'finance'], true) ? 'finance_id' : 'manager_id';
            }

            if ($status && $this->payrollModel->updateReimbursementStatus($claimId, $status, $roleField, $_SESSION['user_id'])) {
                $_SESSION['payroll_success'] = 'Reimbursement status updated successfully.';
                
                // If newly approved by finance, sync it directly into the active payroll run (if generated and editable)
                if ($status === 'finance_approved') {
                    try {
                        $db = Database::getInstance()->getConnection();
                        $claim = $this->payrollModel->getReimbursementById($claimId);
                        if ($claim) {
                            $claimMonth = date('Y-m', strtotime($claim->created_at));
                            $runStmt = $db->prepare("SELECT payroll_run_id, status FROM payroll_runs WHERE month_year = :month");
                            $runStmt->execute([':month' => $claimMonth]);
                            $run = $runStmt->fetch(PDO::FETCH_ASSOC);
                            if ($run && in_array($run['status'], ['generated', 'approved'], true)) {
                                $runId = (int)$run['payroll_run_id'];
                                $detailStmt = $db->prepare("SELECT * FROM payroll_details WHERE payroll_run_id = :rid AND employee_id = :emp_id");
                                $detailStmt->execute([':rid' => $runId, ':emp_id' => $claim->employee_id]);
                                $detail = $detailStmt->fetch(PDO::FETCH_ASSOC);
                                if ($detail) {
                                    $newOther = (float)$detail['other_earnings'] + (float)$claim->amount;
                                    $newGross = (float)$detail['gross_salary'] + (float)$claim->amount;
                                    $newNet = (float)$detail['net_salary'] + (float)$claim->amount;
                                    
                                    $updStmt = $db->prepare("UPDATE payroll_details SET other_earnings = :other, gross_salary = :gross, net_salary = :net WHERE payroll_detail_id = :did");
                                    $updStmt->execute([
                                        ':other' => $newOther,
                                        ':gross' => $newGross,
                                        ':net' => $newNet,
                                        ':did' => $detail['payroll_detail_id']
                                    ]);
                                }
                            }
                        }
                    } catch (Exception $ex) {}
                }
            } elseif ($status) {
                $_SESSION['payroll_error'] = 'Failed to update reimbursement status.';
            }
        }
        $this->redirect('index.php?route=payroll/reimbursements');
    }

    // 6. Bonuses Setup
    public function bonuses() {
        $role = $_SESSION['user_role'];
        $emp = $this->payrollModel->getEmployeeByUserId($_SESSION['user_id']);
        
        $filters = [];
        if (!in_array($role, ['admin', 'hr', 'finance'], true)) {
            $filters['employee_id'] = $emp ? $emp->employee_id : -1;
        }

        $bonuses = $this->payrollModel->getBonuses($filters);
        $employees = $this->payrollModel->getActiveEmployees();

        $data = [
            'title' => 'Bonuses & Perks | Raptor CRM',
            'active_tab' => 'payroll_bonuses',
            'bonuses' => $bonuses,
            'employees' => $employees,
            'role' => $role
        ];

        $this->viewWithLayout('payroll/bonuses', 'main', $data);
    }

    // Add Bonus
    public function add_bonus() {
        $this->requirePermission('payroll', 'create');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $_POST = filter_input_array(INPUT_POST, FILTER_SANITIZE_SPECIAL_CHARS) ?: [];
            
            $bonusData = [
                'employee_id' => (int)($_POST['employee_id'] ?? 0),
                'bonus_type' => trim($_POST['bonus_type'] ?? 'Performance'),
                'amount' => (float)($_POST['amount'] ?? 0),
                'description' => trim($_POST['description'] ?? '')
            ];

            if ($bonusData['employee_id'] > 0 && $bonusData['amount'] > 0) {
                if ($this->payrollModel->addBonus($bonusData)) {
                    $_SESSION['payroll_success'] = 'Bonus allocated successfully.';
                } else {
                    $_SESSION['payroll_error'] = 'Failed to allocate bonus.';
                }
            }
        }
        $this->redirect('index.php?route=payroll/bonuses');
    }

    // Approve/Pay Bonus
    public function approve_bonus($bonusId) {
        $this->requirePermission('payroll', 'approve');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $_POST = filter_input_array(INPUT_POST, FILTER_SANITIZE_SPECIAL_CHARS) ?: [];
            $status = trim($_POST['status'] ?? 'approved'); // 'approved' or 'paid'

            if ($this->payrollModel->updateBonusStatus($bonusId, $status)) {
                $_SESSION['payroll_success'] = 'Bonus status updated.';
            } else {
                $_SESSION['payroll_error'] = 'Failed to update bonus status.';
            }
        }
        $this->redirect('index.php?route=payroll/bonuses');
    }

    /**
     * Retrieve current user's employee record, provisioning one if absent.
     */
    private function getOrProvisionEmployee() {
        $emp = $this->payrollModel->getEmployeeByUserId($_SESSION['user_id']);
        if (!$emp) {
            try {
                $db = Database::getInstance()->getConnection();
                $stmt = $db->prepare("INSERT INTO employees (user_id, employee_code, job_title, salary, date_of_joining, department, hire_date) VALUES (:uid, :code, :title, 0, CURDATE(), 'Administration', CURDATE())");
                $code = 'EMP-' . $_SESSION['user_id'];
                $stmt->execute([
                    ':uid' => $_SESSION['user_id'],
                    ':code' => $code,
                    ':title' => 'Staff'
                ]);
                $emp = $this->payrollModel->getEmployeeByUserId($_SESSION['user_id']);
            } catch (Exception $e) {}
        }
        return $emp;
    }
}
