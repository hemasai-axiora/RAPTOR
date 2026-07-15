<?php
// Raptor CRM Payroll Model

class Payroll extends Model {
    
    // ---------------- Salary Structures ----------------
    
    public function getSalaryStructureByEmployeeId($employeeId) {
        $this->query('SELECT * FROM salary_structures WHERE employee_id = :emp_id');
        $this->bind(':emp_id', $employeeId);
        return $this->single();
    }

    public function saveSalaryStructure($data) {
        $existing = $this->getSalaryStructureByEmployeeId($data['employee_id']);
        
        if ($existing) {
            $this->query('UPDATE salary_structures SET 
                            salary_type = :type, basic_salary = :basic, hra = :hra, 
                            special_allowance = :special, medical_allowance = :med, 
                            travel_allowance = :travel, bonus = :bonus, other_earnings = :other, 
                            pf = :pf, esic = :esic, professional_tax = :pt, tds = :tds
                          WHERE employee_id = :emp_id');
        } else {
            $this->query('INSERT INTO salary_structures 
                            (employee_id, salary_type, basic_salary, hra, special_allowance, 
                             medical_allowance, travel_allowance, bonus, other_earnings, pf, esic, professional_tax, tds)
                          VALUES 
                            (:emp_id, :type, :basic, :hra, :special, :med, :travel, :bonus, :other, :pf, :esic, :pt, :tds)');
        }

        $this->bind(':emp_id', $data['employee_id']);
        $this->bind(':type', $data['salary_type'] ?? 'Monthly');
        $this->bind(':basic', $data['basic_salary'] ?? 0);
        $this->bind(':hra', $data['hra'] ?? 0);
        $this->bind(':special', $data['special_allowance'] ?? 0);
        $this->bind(':med', $data['medical_allowance'] ?? 0);
        $this->bind(':travel', $data['travel_allowance'] ?? 0);
        $this->bind(':bonus', $data['bonus'] ?? 0);
        $this->bind(':other', $data['other_earnings'] ?? 0);
        $this->bind(':pf', $data['pf'] ?? 0);
        $this->bind(':esic', $data['esic'] ?? 0);
        $this->bind(':pt', $data['professional_tax'] ?? 0);
        $this->bind(':tds', $data['tds'] ?? 0);

        return $this->execute();
    }

    // ---------------- Payroll Runs ----------------

    public function getPayrollRuns() {
        $this->query('SELECT r.*, u.name as creator_name, a.name as approver_name, l.name as releaser_name
                      FROM payroll_runs r
                      LEFT JOIN users u ON r.created_by = u.user_id
                      LEFT JOIN users a ON r.approved_by = a.user_id
                      LEFT JOIN users l ON r.released_by = l.user_id
                      ORDER BY r.month_year DESC');
        return $this->resultSet();
    }

    public function getPayrollRunById($runId) {
        $this->query('SELECT * FROM payroll_runs WHERE payroll_run_id = :id');
        $this->bind(':id', $runId);
        return $this->single();
    }

    public function getPayrollRunByMonth($monthYear) {
        $this->query('SELECT * FROM payroll_runs WHERE month_year = :month_year');
        $this->bind(':month_year', $monthYear);
        return $this->single();
    }

    public function createPayrollRun($monthYear, $userId) {
        $this->query('INSERT INTO payroll_runs (month_year, status, created_by) VALUES (:month_year, "generated", :uid)');
        $this->bind(':month_year', $monthYear);
        $this->bind(':uid', $userId);
        if ($this->execute()) {
            return $this->lastInsertId();
        }
        return false;
    }

    public function updateRunStatus($runId, $status, $userIdField = null, $userId = null) {
        if ($userIdField) {
            $this->query("UPDATE payroll_runs SET status = :status, {$userIdField} = :uid WHERE payroll_run_id = :id");
            $this->bind(':uid', $userId);
        } else {
            $this->query('UPDATE payroll_runs SET status = :status WHERE payroll_run_id = :id');
        }
        $this->bind(':status', $status);
        $this->bind(':id', $runId);
        return $this->execute();
    }

    // ---------------- Payroll Details ----------------

    public function getPayrollDetailsByRunId($runId) {
        $this->query('SELECT d.*, e.employee_code, u.name, u.email,
                             b.account_holder_name, b.bank_name, b.account_number, b.ifsc_code, b.branch_name, b.account_type
                      FROM payroll_details d
                      JOIN employees e ON d.employee_id = e.employee_id
                      JOIN users u ON e.user_id = u.user_id
                      LEFT JOIN bank_accounts b ON e.employee_id = b.employee_id
                      WHERE d.payroll_run_id = :run_id');
        $this->bind(':run_id', $runId);
        return $this->resultSet();
    }

    public function getPayrollDetailById($detailId) {
        $this->query('SELECT d.*, e.employee_code, e.job_title, e.department, u.name, u.email, r.month_year, r.status as run_status,
                             b.account_holder_name, b.bank_name, b.account_number, b.ifsc_code, b.branch_name, b.account_type
                      FROM payroll_details d
                      JOIN employees e ON d.employee_id = e.employee_id
                      JOIN users u ON e.user_id = u.user_id
                      JOIN payroll_runs r ON d.payroll_run_id = r.payroll_run_id
                      LEFT JOIN bank_accounts b ON e.employee_id = b.employee_id
                      WHERE d.payroll_detail_id = :id');
        $this->bind(':id', $detailId);
        return $this->single();
    }

    public function getPayrollDetailsByEmployee($employeeId) {
        $this->query('SELECT d.*, r.month_year, r.status as run_status
                      FROM payroll_details d
                      JOIN payroll_runs r ON d.payroll_run_id = r.payroll_run_id
                      WHERE d.employee_id = :emp_id AND r.status = "released"
                      ORDER BY r.month_year DESC');
        $this->bind(':emp_id', $employeeId);
        return $this->resultSet();
    }

    public function savePayrollDetails($data) {
        $this->query('INSERT INTO payroll_details 
                        (payroll_run_id, employee_id, working_days, present_days, absent_days, leave_days, 
                         overtime_hours, late_marks, basic_salary, hra, special_allowance, medical_allowance, 
                         travel_allowance, bonus, other_earnings, pf, esic, professional_tax, tds, gross_salary, total_deductions, net_salary)
                      VALUES 
                        (:run_id, :emp_id, :working, :present, :absent, :leave, 
                         :overtime, :late, :basic, :hra, :special, :med, 
                         :travel, :bonus, :other, :pf, :esic, :pt, :tds, :gross, :deductions, :net)
                      ON DUPLICATE KEY UPDATE 
                        working_days = VALUES(working_days), present_days = VALUES(present_days), absent_days = VALUES(absent_days), leave_days = VALUES(leave_days),
                        overtime_hours = VALUES(overtime_hours), late_marks = VALUES(late_marks),
                        basic_salary = VALUES(basic_salary), hra = VALUES(hra), special_allowance = VALUES(special_allowance), medical_allowance = VALUES(medical_allowance),
                        travel_allowance = VALUES(travel_allowance), bonus = VALUES(bonus), other_earnings = VALUES(other_earnings),
                        pf = VALUES(pf), esic = VALUES(esic), professional_tax = VALUES(professional_tax), tds = VALUES(tds),
                        gross_salary = VALUES(gross_salary), total_deductions = VALUES(total_deductions), net_salary = VALUES(net_salary)');
        
        $this->bind(':run_id', $data['payroll_run_id']);
        $this->bind(':emp_id', $data['employee_id']);
        $this->bind(':working', $data['working_days'] ?? 0);
        $this->bind(':present', $data['present_days'] ?? 0);
        $this->bind(':absent', $data['absent_days'] ?? 0);
        $this->bind(':leave', $data['leave_days'] ?? 0);
        $this->bind(':overtime', $data['overtime_hours'] ?? 0);
        $this->bind(':late', $data['late_marks'] ?? 0);
        $this->bind(':basic', $data['basic_salary'] ?? 0);
        $this->bind(':hra', $data['hra'] ?? 0);
        $this->bind(':special', $data['special_allowance'] ?? 0);
        $this->bind(':med', $data['medical_allowance'] ?? 0);
        $this->bind(':travel', $data['travel_allowance'] ?? 0);
        $this->bind(':bonus', $data['bonus'] ?? 0);
        $this->bind(':other', $data['other_earnings'] ?? 0);
        $this->bind(':pf', $data['pf'] ?? 0);
        $this->bind(':esic', $data['esic'] ?? 0);
        $this->bind(':pt', $data['professional_tax'] ?? 0);
        $this->bind(':tds', $data['tds'] ?? 0);
        $this->bind(':gross', $data['gross_salary'] ?? 0);
        $this->bind(':deductions', $data['total_deductions'] ?? 0);
        $this->bind(':net', $data['net_salary'] ?? 0);

        return $this->execute();
    }

    public function markRunDetailsPaid($runId) {
        $this->query('UPDATE payroll_details SET payment_status = "paid", paid_at = CURRENT_TIMESTAMP WHERE payroll_run_id = :id');
        $this->bind(':id', $runId);
        return $this->execute();
    }

    // ---------------- Reimbursements ----------------

    public function getReimbursements($filters = []) {
        $where = [];
        $params = [];

        if (!empty($filters['employee_id'])) {
            $where[] = 'r.employee_id = :emp_id';
            $params[':emp_id'] = $filters['employee_id'];
        }
        if (!empty($filters['status'])) {
            $where[] = 'r.status = :status';
            $params[':status'] = $filters['status'];
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $this->query("SELECT r.*, e.employee_code, u.name as employee_name, m.name as manager_name, f.name as finance_name
                      FROM reimbursements r
                      JOIN employees e ON r.employee_id = e.employee_id
                      JOIN users u ON e.user_id = u.user_id
                      LEFT JOIN users m ON r.manager_id = m.user_id
                      LEFT JOIN users f ON r.finance_id = f.user_id
                      {$whereSql}
                      ORDER BY r.created_at DESC");
        
        foreach ($params as $key => $val) {
            $this->bind($key, $val);
        }

        return $this->resultSet();
    }

    public function getReimbursementById($id) {
        $this->query('SELECT * FROM reimbursements WHERE reimbursement_id = :id');
        $this->bind(':id', $id);
        return $this->single();
    }

    public function addReimbursement($data) {
        $this->query('INSERT INTO reimbursements (employee_id, claim_type, amount, description, attachment_url, status, created_at) 
                      VALUES (:emp_id, :type, :amount, :desc, :url, "pending", :created_at)');
        $this->bind(':emp_id', $data['employee_id']);
        $this->bind(':type', $data['claim_type']);
        $this->bind(':amount', $data['amount']);
        $this->bind(':desc', $data['description']);
        $this->bind(':url', $data['attachment_url']);
        $this->bind(':created_at', !empty($data['created_at']) ? $data['created_at'] : date('Y-m-d H:i:s'));
        return $this->execute();
    }

    public function updateReimbursementStatus($id, $status, $roleField, $userId) {
        $this->query("UPDATE reimbursements SET status = :status, {$roleField} = :uid WHERE reimbursement_id = :id");
        $this->bind(':status', $status);
        $this->bind(':uid', $userId);
        $this->bind(':id', $id);
        return $this->execute();
    }

    // ---------------- Bonuses ----------------

    public function getBonuses($filters = []) {
        $where = [];
        $params = [];

        if (!empty($filters['employee_id'])) {
            $where[] = 'b.employee_id = :emp_id';
            $params[':emp_id'] = $filters['employee_id'];
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $this->query("SELECT b.*, e.employee_code, u.name as employee_name
                      FROM bonuses b
                      JOIN employees e ON b.employee_id = e.employee_id
                      JOIN users u ON e.user_id = u.user_id
                      {$whereSql}
                      ORDER BY b.created_at DESC");
        
        foreach ($params as $key => $val) {
            $this->bind($key, $val);
        }

        return $this->resultSet();
    }

    public function addBonus($data) {
        $this->query('INSERT INTO bonuses (employee_id, bonus_type, amount, description, status) 
                      VALUES (:emp_id, :type, :amount, :desc, "pending")');
        $this->bind(':emp_id', $data['employee_id']);
        $this->bind(':type', $data['bonus_type']);
        $this->bind(':amount', $data['amount']);
        $this->bind(':desc', $data['description']);
        return $this->execute();
    }

    public function updateBonusStatus($id, $status) {
        $this->query('UPDATE bonuses SET status = :status WHERE bonus_id = :id');
        $this->bind(':status', $status);
        $this->bind(':id', $id);
        return $this->execute();
    }

    // ---------------- Employee Fetch Helper ----------------

    public function getActiveEmployees() {
        $this->query('SELECT e.*, u.name, u.email
                      FROM employees e
                      JOIN users u ON e.user_id = u.user_id
                      JOIN roles r ON u.role_id = r.role_id
                      WHERE u.status = "active" AND r.role_name != "admin"
                      ORDER BY u.name ASC');
        return $this->resultSet();
    }

    public function getEmployeeByUserId($userId) {
        $this->query('SELECT * FROM employees WHERE user_id = :uid');
        $this->bind(':uid', $userId);
        return $this->single();
    }
}
