<?php
// Raptor CRM Payroll Model

class Payroll extends Model {
    
    // ---------------- Pre-Payroll Checklists ----------------

    public static function getDefaultChecklistItems(): array {
        return [
            [
                'key' => 'employee_status',
                'title' => '1. Employee Status Verification',
                'description' => 'Verify active and inactive employees. Ensure only eligible employees are included in the payroll. Identify new joiners and employees who have left the organization.'
            ],
            [
                'key' => 'salary_hold',
                'title' => '2. Salary Processing / Hold',
                'description' => 'Identify employees whose salaries need to be processed. Allow salaries to be placed on hold when required. Record the reason for holding a salary.'
            ],
            [
                'key' => 'joiners_relieved',
                'title' => '3. New Joiners and Relieved Employees',
                'description' => 'Verify newly joined employees and their date of joining. Verify employees who have resigned or been relieved and their last working day. Calculate salary based on applicable employment dates.'
            ],
            [
                'key' => 'salary_revisions',
                'title' => '4. Salary Revisions',
                'description' => 'Check for any salary revisions effective during the payroll period. Ensure the revised salary structure is applied from the correct effective date.'
            ],
            [
                'key' => 'lop_deductions',
                'title' => '5. Loss of Pay (LOP)',
                'description' => 'Verify employee attendance and approved leave records. Confirm LOP days and ensure the corresponding salary deductions are calculated correctly.'
            ],
            [
                'key' => 'other_deductions',
                'title' => '6. Other Deductions',
                'description' => 'Verify any applicable deductions apart from regular deductions. Confirm the deduction amount and reason before processing.'
            ],
            [
                'key' => 'arrears',
                'title' => '7. Arrears',
                'description' => 'Check for pending salary arrears from previous months. Verify and include applicable arrears in the current payroll.'
            ],
            [
                'key' => 'bonuses_incentives',
                'title' => '8. Bonus / Incentives',
                'description' => 'Verify eligible employees and approved bonus or incentive amounts. Ensure approved amounts are included in the payroll calculation.'
            ],
            [
                'key' => 'tds_deductions',
                'title' => '9. TDS Deductions',
                'description' => 'Verify applicable employee TDS deductions. Confirm the amounts before finalizing payroll.'
            ]
        ];
    }

    public function getChecklistByMonth($monthYear) {
        $this->query('SELECT c.*, u.name as completed_by_name 
                      FROM payroll_checklists c 
                      LEFT JOIN users u ON c.completed_by = u.user_id 
                      WHERE c.month_year = :month');
        $this->bind(':month', $monthYear);
        $checklist = $this->single();

        if (!$checklist) {
            // Auto-initialize checklist and default 9 items
            $this->query('INSERT INTO payroll_checklists (month_year, status) VALUES (:month, "in_progress")');
            $this->bind(':month', $monthYear);
            $this->execute();
            $checklistId = $this->lastInsertId();

            if ($checklistId) {
                $defaultItems = self::getDefaultChecklistItems();
                foreach ($defaultItems as $item) {
                    $this->query('INSERT INTO payroll_checklist_items (checklist_id, item_key, item_title, item_description, status) 
                                  VALUES (:cid, :key, :title, :desc, "pending")');
                    $this->bind(':cid', $checklistId);
                    $this->bind(':key', $item['key']);
                    $this->bind(':title', $item['title']);
                    $this->bind(':desc', $item['description']);
                    $this->execute();
                }

                $this->query('SELECT c.*, u.name as completed_by_name 
                              FROM payroll_checklists c 
                              LEFT JOIN users u ON c.completed_by = u.user_id 
                              WHERE c.checklist_id = :cid');
                $this->bind(':cid', $checklistId);
                $checklist = $this->single();
            }
        }

        // Ensure all 9 items exist for older checklists
        $this->query('SELECT COUNT(*) as cnt FROM payroll_checklist_items WHERE checklist_id = :cid');
        $this->bind(':cid', $checklist->checklist_id);
        $count = (int)$this->single()->cnt;

        if ($count < 9) {
            $defaultItems = self::getDefaultChecklistItems();
            foreach ($defaultItems as $item) {
                $this->query('INSERT IGNORE INTO payroll_checklist_items (checklist_id, item_key, item_title, item_description, status) 
                              VALUES (:cid, :key, :title, :desc, "pending")');
                $this->bind(':cid', $checklist->checklist_id);
                $this->bind(':key', $item['key']);
                $this->bind(':title', $item['title']);
                $this->bind(':desc', $item['description']);
                $this->execute();
            }
        }

        return $checklist;
    }

    public function getChecklistItems($checklistId) {
        $this->query('SELECT i.*, u.name as verified_by_name, u.email as verified_by_email 
                      FROM payroll_checklist_items i 
                      LEFT JOIN users u ON i.verified_by = u.user_id 
                      WHERE i.checklist_id = :cid 
                      ORDER BY i.item_id ASC');
        $this->bind(':cid', $checklistId);
        return $this->resultSet();
    }

    public function updateChecklistItem($itemId, $status, $remarks, $userId) {
        $this->query('UPDATE payroll_checklist_items 
                      SET status = :status, remarks = :remarks, verified_by = :uid, verified_at = CURRENT_TIMESTAMP 
                      WHERE item_id = :id');
        $this->bind(':status', $status);
        $this->bind(':remarks', $remarks);
        $this->bind(':uid', $userId);
        $this->bind(':id', $itemId);
        $res = $this->execute();

        if ($res) {
            // Find parent checklist id
            $this->query('SELECT checklist_id FROM payroll_checklist_items WHERE item_id = :id');
            $this->bind(':id', $itemId);
            $item = $this->single();
            if ($item) {
                $checklistId = $item->checklist_id;
                // Check if all items are completed
                $this->query('SELECT COUNT(*) as total_items, 
                                     SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as completed_items 
                              FROM payroll_checklist_items 
                              WHERE checklist_id = :cid');
                $this->bind(':cid', $checklistId);
                $stat = $this->single();

                if ((int)$stat->total_items === (int)$stat->completed_items && (int)$stat->total_items >= 9) {
                    $this->query('UPDATE payroll_checklists 
                                  SET status = "completed", completed_by = :uid, completed_at = CURRENT_TIMESTAMP 
                                  WHERE checklist_id = :cid');
                    $this->bind(':uid', $userId);
                    $this->bind(':cid', $checklistId);
                    $this->execute();
                } else {
                    $this->query('UPDATE payroll_checklists 
                                  SET status = "in_progress", completed_by = NULL, completed_at = NULL 
                                  WHERE checklist_id = :cid');
                    $this->bind(':cid', $checklistId);
                    $this->execute();
                }
            }
        }

        return $res;
    }

    public function isChecklistCompleted($monthYear): bool {
        $this->query('SELECT status FROM payroll_checklists WHERE month_year = :month');
        $this->bind(':month', $monthYear);
        $c = $this->single();
        if (!$c) {
            return false;
        }
        if ($c->status === 'completed') {
            return true;
        }

        // Also double check if all 9 items are marked completed
        $this->query('SELECT COUNT(*) as total_items, 
                             SUM(CASE WHEN i.status = "completed" THEN 1 ELSE 0 END) as completed_items 
                      FROM payroll_checklists c 
                      JOIN payroll_checklist_items i ON c.checklist_id = i.checklist_id 
                      WHERE c.month_year = :month');
        $this->bind(':month', $monthYear);
        $stat = $this->single();
        return ($stat && (int)$stat->total_items >= 9 && (int)$stat->total_items === (int)$stat->completed_items);
    }

    // ---------------- Salary Holds ----------------

    public function getSalaryHolds($monthYear) {
        $this->query('SELECT h.*, e.employee_code, u.name as employee_name, u.email as employee_email,
                             hby.name as hold_by_name 
                      FROM payroll_salary_holds h 
                      JOIN employees e ON h.employee_id = e.employee_id 
                      JOIN users u ON e.user_id = u.user_id 
                      LEFT JOIN users hby ON h.hold_by = hby.user_id 
                      WHERE h.month_year = :month 
                      ORDER BY h.created_at DESC');
        $this->bind(':month', $monthYear);
        return $this->resultSet();
    }

    public function saveSalaryHold($monthYear, $employeeId, $reason, $userId) {
        $this->query('INSERT INTO payroll_salary_holds (month_year, employee_id, reason, hold_by, status) 
                      VALUES (:month, :eid, :reason, :uid, "on_hold") 
                      ON DUPLICATE KEY UPDATE reason = VALUES(reason), hold_by = VALUES(hold_by), status = "on_hold"');
        $this->bind(':month', $monthYear);
        $this->bind(':eid', $employeeId);
        $this->bind(':reason', $reason);
        $this->bind(':uid', $userId);
        return $this->execute();
    }

    public function releaseSalaryHold($holdId) {
        $this->query('DELETE FROM payroll_salary_holds WHERE hold_id = :id');
        $this->bind(':id', $holdId);
        return $this->execute();
    }

    // ---------------- Arrears ----------------

    public function getArrears($monthYear) {
        $this->query('SELECT a.*, e.employee_code, u.name as employee_name, u2.name as created_by_name 
                      FROM payroll_arrears a 
                      JOIN employees e ON a.employee_id = e.employee_id 
                      JOIN users u ON e.user_id = u.user_id 
                      LEFT JOIN users u2 ON a.created_by = u2.user_id 
                      WHERE a.month_year = :month 
                      ORDER BY a.created_at DESC');
        $this->bind(':month', $monthYear);
        return $this->resultSet();
    }

    public function saveArrear($monthYear, $employeeId, $amount, $reason, $userId) {
        $this->query('INSERT INTO payroll_arrears (month_year, employee_id, amount, reason, created_by) 
                      VALUES (:month, :eid, :amount, :reason, :uid)');
        $this->bind(':month', $monthYear);
        $this->bind(':eid', $employeeId);
        $this->bind(':amount', $amount);
        $this->bind(':reason', $reason);
        $this->bind(':uid', $userId);
        return $this->execute();
    }

    public function deleteArrear($arrearId) {
        $this->query('DELETE FROM payroll_arrears WHERE arrear_id = :id');
        $this->bind(':id', $arrearId);
        return $this->execute();
    }

    // ---------------- Loss of Pay (LOP) ----------------

    public function getLops($monthYear) {
        $this->query('SELECT l.*, e.employee_code, u.name as employee_name, u2.name as created_by_name 
                      FROM payroll_lops l 
                      JOIN employees e ON l.employee_id = e.employee_id 
                      JOIN users u ON e.user_id = u.user_id 
                      LEFT JOIN users u2 ON l.created_by = u2.user_id 
                      WHERE l.month_year = :month 
                      ORDER BY l.created_at DESC');
        $this->bind(':month', $monthYear);
        return $this->resultSet();
    }

    public function saveLop($monthYear, $employeeId, $lopDays, $reason, $userId) {
        $this->query('INSERT INTO payroll_lops (month_year, employee_id, lop_days, reason, created_by) 
                      VALUES (:month, :eid, :days, :reason, :uid)
                      ON DUPLICATE KEY UPDATE lop_days = VALUES(lop_days), reason = VALUES(reason), created_by = VALUES(created_by)');
        $this->bind(':month', $monthYear);
        $this->bind(':eid', $employeeId);
        $this->bind(':days', $lopDays);
        $this->bind(':reason', $reason);
        $this->bind(':uid', $userId);
        return $this->execute();
    }

    public function deleteLop($lopId) {
        $this->query('DELETE FROM payroll_lops WHERE lop_id = :id');
        $this->bind(':id', $lopId);
        return $this->execute();
    }

    // ---------------- Other Deductions ----------------

    public function getDeductions($monthYear) {
        $this->query('SELECT d.*, e.employee_code, u.name as employee_name, u2.name as created_by_name 
                      FROM payroll_deductions d 
                      JOIN employees e ON d.employee_id = e.employee_id 
                      JOIN users u ON e.user_id = u.user_id 
                      LEFT JOIN users u2 ON d.created_by = u2.user_id 
                      WHERE d.month_year = :month 
                      ORDER BY d.created_at DESC');
        $this->bind(':month', $monthYear);
        return $this->resultSet();
    }

    public function saveDeduction($monthYear, $employeeId, $deductionType, $amount, $reason, $userId) {
        $this->query('INSERT INTO payroll_deductions (month_year, employee_id, deduction_type, amount, reason, created_by) 
                      VALUES (:month, :eid, :dtype, :amount, :reason, :uid)');
        $this->bind(':month', $monthYear);
        $this->bind(':eid', $employeeId);
        $this->bind(':dtype', $deductionType);
        $this->bind(':amount', $amount);
        $this->bind(':reason', $reason);
        $this->bind(':uid', $userId);
        return $this->execute();
    }

    public function deleteDeduction($deductionId) {
        $this->query('DELETE FROM payroll_deductions WHERE deduction_id = :id');
        $this->bind(':id', $deductionId);
        return $this->execute();
    }

    // ---------------- Salary Structures ----------------
    
    public function getSalaryStructureByEmployeeId($employeeId) {
        $this->query('SELECT * FROM salary_structures WHERE employee_id = :emp_id');
        $this->bind(':emp_id', $employeeId);
        return $this->single();
    }

    public function getSalaryStructure($employeeId) {
        return $this->getSalaryStructureByEmployeeId($employeeId);
    }

    public function saveSalaryStructure($data, $revisedBy = null, $reason = '', $effectiveDate = null) {
        $existing = $this->getSalaryStructureByEmployeeId($data['employee_id']);
        
        $basic = round((float)($data['basic_salary'] ?? 0));
        $hra = round((float)($data['hra'] ?? 0));
        $special = round((float)($data['special_allowance'] ?? 0));
        $otherAll = round((float)($data['other_allowance'] ?? $data['other_earnings'] ?? 0));
        $med = round((float)($data['medical_allowance'] ?? 0));
        $travel = round((float)($data['travel_allowance'] ?? 0));
        $bonus = round((float)($data['bonus'] ?? 0));
        $otherEarn = round((float)($data['other_earnings'] ?? 0));
        
        $pf = round((float)($data['pf'] ?? ($basic * 0.12)));
        $esic = round((float)($data['esic'] ?? ($basic * 0.0075)));
        $pt = round((float)($data['professional_tax'] ?? 200.00));
        $tds = round((float)($data['tds'] ?? 0));
        
        $employerPf = round((float)($data['employer_pf'] ?? $pf));
        $gratuity = round((float)($data['gratuity'] ?? round($basic * 0.0481)));
        
        $newGross = round($basic + $hra + $special + $otherAll + $med + $travel + $bonus + $otherEarn);
        $newBenefits = round($employerPf + $gratuity);
        $newCtc = round($newGross + $newBenefits);

        // If existing record exists, track in salary revision history
        if ($existing) {
            $prevBasic = round((float)$existing->basic_salary);
            $prevHra = round((float)$existing->hra);
            $prevSpecial = round((float)$existing->special_allowance);
            $prevOther = round((float)($existing->other_allowance ?? $existing->other_earnings ?? 0));
            $prevGross = round($prevBasic + $prevHra + $prevSpecial + $prevOther + (float)$existing->medical_allowance + (float)$existing->travel_allowance + (float)$existing->bonus);
            $prevEmpPf = round((float)($existing->employer_pf ?? $existing->pf));
            $prevGrat = round((float)($existing->gratuity ?? round($prevBasic * 0.0481)));
            $prevCtc = round($prevGross + $prevEmpPf + $prevGrat);

            // Check if any value changed or explicit revision reason given
            $hasChanged = (
                abs($prevBasic - $basic) >= 1 ||
                abs($prevHra - $hra) >= 1 ||
                abs($prevSpecial - $special) >= 1 ||
                abs($prevOther - $otherAll) >= 1 ||
                !empty($reason)
            );

            if ($hasChanged) {
                $revDate = date('Y-m-d');
                $effDate = !empty($effectiveDate) ? $effectiveDate : $revDate;
                $revBy = $revisedBy ?? ($_SESSION['user_id'] ?? null);
                $revReason = !empty($reason) ? trim($reason) : 'Salary Structure Revision';

                $historyData = [
                    'employee_id' => $data['employee_id'],
                    'revision_date' => $revDate,
                    'effective_date' => $effDate,
                    'revised_by' => $revBy,
                    'reason' => $revReason,
                    'previous_basic' => $prevBasic,
                    'previous_hra' => $prevHra,
                    'previous_special_allowance' => $prevSpecial,
                    'previous_other_allowance' => $prevOther,
                    'previous_gross' => $prevGross,
                    'previous_ctc' => $prevCtc,
                    'new_basic' => $basic,
                    'new_hra' => $hra,
                    'new_special_allowance' => $special,
                    'new_other_allowance' => $otherAll,
                    'new_gross' => $newGross,
                    'new_ctc' => $newCtc,
                    'details_json' => json_encode([
                        'previous' => (array)$existing,
                        'new' => $data
                    ])
                ];

                $this->recordSalaryRevision($historyData);
            }

            $this->query('UPDATE salary_structures SET 
                            salary_type = :type, basic_salary = :basic, hra = :hra, 
                            special_allowance = :special, other_allowance = :other_all, medical_allowance = :med, 
                            travel_allowance = :travel, bonus = :bonus, other_earnings = :other, 
                            pf = :pf, employer_pf = :emp_pf, gratuity = :grat, esic = :esic, professional_tax = :pt, tds = :tds
                          WHERE employee_id = :emp_id');
        } else {
            // First time structure creation - also record initial baseline in revision history
            $revDate = date('Y-m-d');
            $effDate = !empty($effectiveDate) ? $effectiveDate : $revDate;
            $revBy = $revisedBy ?? ($_SESSION['user_id'] ?? null);
            $revReason = !empty($reason) ? trim($reason) : 'Initial Salary Structure';

            $this->recordSalaryRevision([
                'employee_id' => $data['employee_id'],
                'revision_date' => $revDate,
                'effective_date' => $effDate,
                'revised_by' => $revBy,
                'reason' => $revReason,
                'previous_basic' => 0.00,
                'previous_hra' => 0.00,
                'previous_special_allowance' => 0.00,
                'previous_other_allowance' => 0.00,
                'previous_gross' => 0.00,
                'previous_ctc' => 0.00,
                'new_basic' => $basic,
                'new_hra' => $hra,
                'new_special_allowance' => $special,
                'new_other_allowance' => $otherAll,
                'new_gross' => $newGross,
                'new_ctc' => $newCtc,
                'details_json' => json_encode(['initial' => $data])
            ]);

            $this->query('INSERT INTO salary_structures 
                            (employee_id, salary_type, basic_salary, hra, special_allowance, other_allowance, 
                             medical_allowance, travel_allowance, bonus, other_earnings, pf, employer_pf, gratuity, esic, professional_tax, tds)
                          VALUES 
                            (:emp_id, :type, :basic, :hra, :special, :other_all, :med, :travel, :bonus, :other, :pf, :emp_pf, :grat, :esic, :pt, :tds)');
        }

        $this->bind(':emp_id', $data['employee_id']);
        $this->bind(':type', $data['salary_type'] ?? 'Monthly');
        $this->bind(':basic', $basic);
        $this->bind(':hra', $hra);
        $this->bind(':special', $special);
        $this->bind(':other_all', $otherAll);
        $this->bind(':med', $med);
        $this->bind(':travel', $travel);
        $this->bind(':bonus', $bonus);
        $this->bind(':other', $otherEarn);
        $this->bind(':pf', $pf);
        $this->bind(':emp_pf', $employerPf);
        $this->bind(':grat', $gratuity);
        $this->bind(':esic', $esic);
        $this->bind(':pt', $pt);
        $this->bind(':tds', $tds);

        return $this->execute();
    }

    // ---------------- Salary Revision History ----------------

    public function recordSalaryRevision($data) {
        $revBy = !empty($data['revised_by']) ? (int)$data['revised_by'] : null;
        if ($revBy !== null) {
            $this->query('SELECT user_id FROM users WHERE user_id = :uid LIMIT 1');
            $this->bind(':uid', $revBy);
            if (!$this->single()) {
                $revBy = null;
            }
        }

        $this->query('INSERT INTO salary_revision_history 
                        (employee_id, revision_date, effective_date, revised_by, reason, 
                         previous_basic, previous_hra, previous_special_allowance, previous_other_allowance, previous_gross, previous_ctc,
                         new_basic, new_hra, new_special_allowance, new_other_allowance, new_gross, new_ctc, details_json)
                      VALUES 
                        (:emp_id, :rev_date, :eff_date, :rev_by, :reason, 
                         :prev_basic, :prev_hra, :prev_spec, :prev_other, :prev_gross, :prev_ctc,
                         :new_basic, :new_hra, :new_spec, :new_other, :new_gross, :new_ctc, :details)');

        $this->bind(':emp_id', $data['employee_id']);
        $this->bind(':rev_date', $data['revision_date']);
        $this->bind(':eff_date', $data['effective_date']);
        $this->bind(':rev_by', $revBy);
        $this->bind(':reason', $data['reason']);
        $this->bind(':prev_basic', $data['previous_basic']);
        $this->bind(':prev_hra', $data['previous_hra']);
        $this->bind(':prev_spec', $data['previous_special_allowance']);
        $this->bind(':prev_other', $data['previous_other_allowance']);
        $this->bind(':prev_gross', $data['previous_gross']);
        $this->bind(':prev_ctc', $data['previous_ctc']);
        $this->bind(':new_basic', $data['new_basic']);
        $this->bind(':new_hra', $data['new_hra']);
        $this->bind(':new_spec', $data['new_special_allowance']);
        $this->bind(':new_other', $data['new_other_allowance']);
        $this->bind(':new_gross', $data['new_gross']);
        $this->bind(':new_ctc', $data['new_ctc']);
        $this->bind(':details', $data['details_json'] ?? null);

        return $this->execute();
    }

    public function getSalaryRevisionHistory($employeeId) {
        $this->query('SELECT h.*, u.name as revised_by_name, u.email as revised_by_email
                      FROM salary_revision_history h
                      LEFT JOIN users u ON h.revised_by = u.user_id
                      WHERE h.employee_id = :emp_id
                      ORDER BY h.effective_date DESC, h.revision_id DESC');
        $this->bind(':emp_id', $employeeId);
        return $this->resultSet();
    }

    public function getSalaryRevisionById($revisionId) {
        $this->query('SELECT h.*, u.name as revised_by_name, e.employee_code, un.name as employee_name
                      FROM salary_revision_history h
                      JOIN employees e ON h.employee_id = e.employee_id
                      JOIN users un ON e.user_id = un.user_id
                      LEFT JOIN users u ON h.revised_by = u.user_id
                      WHERE h.revision_id = :id');
        $this->bind(':id', $revisionId);
        return $this->single();
    }

    // ---------------- Payroll Runs ----------------

    public function getPayrollRuns() {
        $this->query('SELECT r.*, u.name as creator_name, a.name as approver_name, l.name as releaser_name,
                             COUNT(d.payroll_detail_id) as total_employees,
                             SUM(CASE WHEN d.email_status = "sent" THEN 1 ELSE 0 END) as emails_sent,
                             SUM(CASE WHEN d.email_status = "failed" THEN 1 ELSE 0 END) as emails_failed,
                             SUM(CASE WHEN d.email_status = "pending" THEN 1 ELSE 0 END) as emails_pending
                      FROM payroll_runs r
                      LEFT JOIN users u ON r.created_by = u.user_id
                      LEFT JOIN users a ON r.approved_by = a.user_id
                      LEFT JOIN users l ON r.released_by = l.user_id
                      LEFT JOIN payroll_details d ON r.payroll_run_id = d.payroll_run_id
                      WHERE r.month_year <= :current_month
                      GROUP BY r.payroll_run_id
                      ORDER BY r.month_year DESC');
        $this->bind(':current_month', date('Y-m'));
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
        $this->query('SELECT d.*, e.employee_code, e.job_title, e.department, 
                             COALESCE(e.date_of_joining, e.hire_date) as date_of_joining, 
                             e.hire_date, e.work_location, e.uan, e.pan_number,
                             u.name, u.email,
                             b.account_holder_name, b.bank_name, b.account_number, b.ifsc_code, b.branch_name, b.account_type,
                             r.month_year, r.status as run_status
                      FROM payroll_details d
                      JOIN employees e ON d.employee_id = e.employee_id
                      JOIN users u ON e.user_id = u.user_id
                      JOIN payroll_runs r ON d.payroll_run_id = r.payroll_run_id
                      LEFT JOIN bank_accounts b ON e.employee_id = b.employee_id
                      WHERE d.payroll_run_id = :run_id
                      ORDER BY u.name ASC');
        $this->bind(':run_id', $runId);
        return $this->resultSet();
    }

    public function getPayrollDetailById($detailId) {
        $this->query('SELECT d.*, e.employee_code, e.job_title, e.department, 
                             COALESCE(e.date_of_joining, e.hire_date) as date_of_joining, 
                             e.hire_date, e.work_location, e.uan, e.pan_number,
                             u.name, u.email, r.month_year, r.status as run_status,
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
                      WHERE d.employee_id = :emp_id AND r.status = "released" AND r.month_year <= :current_month
                      ORDER BY r.month_year DESC');
        $this->bind(':emp_id', $employeeId);
        $this->bind(':current_month', date('Y-m'));
        return $this->resultSet();
    }

    public function savePayrollDetails($data) {
        $this->query('INSERT INTO payroll_details 
                        (payroll_run_id, employee_id, working_days, present_days, absent_days, leave_days, 
                         overtime_hours, late_marks, basic_salary, hra, special_allowance, other_allowance, medical_allowance, 
                         travel_allowance, bonus, other_earnings, actual_basic, actual_hra, actual_special_allowance, actual_other_allowance,
                         pf, employer_pf, gratuity, esic, professional_tax, tds, other_deductions, gross_salary, total_deductions, net_salary, ctc)
                      VALUES 
                        (:run_id, :emp_id, :working, :present, :absent, :leave, 
                         :overtime, :late, :basic, :hra, :special, :other_all, :med, 
                         :travel, :bonus, :other, :act_basic, :act_hra, :act_special, :act_other_all,
                         :pf, :emp_pf, :grat, :esic, :pt, :tds, :other_ded, :gross, :deductions, :net, :ctc)
                      ON DUPLICATE KEY UPDATE 
                        working_days = VALUES(working_days), present_days = VALUES(present_days), absent_days = VALUES(absent_days), leave_days = VALUES(leave_days),
                        overtime_hours = VALUES(overtime_hours), late_marks = VALUES(late_marks),
                        basic_salary = VALUES(basic_salary), hra = VALUES(hra), special_allowance = VALUES(special_allowance), other_allowance = VALUES(other_allowance),
                        medical_allowance = VALUES(medical_allowance), travel_allowance = VALUES(travel_allowance), bonus = VALUES(bonus), other_earnings = VALUES(other_earnings),
                        actual_basic = VALUES(actual_basic), actual_hra = VALUES(actual_hra), actual_special_allowance = VALUES(actual_special_allowance), actual_other_allowance = VALUES(actual_other_allowance),
                        pf = VALUES(pf), employer_pf = VALUES(employer_pf), gratuity = VALUES(gratuity), esic = VALUES(esic), professional_tax = VALUES(professional_tax), tds = VALUES(tds), other_deductions = VALUES(other_deductions),
                        gross_salary = VALUES(gross_salary), total_deductions = VALUES(total_deductions), net_salary = VALUES(net_salary), ctc = VALUES(ctc)');
        
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
        $this->bind(':other_all', $data['other_allowance'] ?? 0);
        $this->bind(':med', $data['medical_allowance'] ?? 0);
        $this->bind(':travel', $data['travel_allowance'] ?? 0);
        $this->bind(':bonus', $data['bonus'] ?? 0);
        $this->bind(':other', $data['other_earnings'] ?? 0);
        $this->bind(':act_basic', $data['actual_basic'] ?? ($data['basic_salary'] ?? 0));
        $this->bind(':act_hra', $data['actual_hra'] ?? ($data['hra'] ?? 0));
        $this->bind(':act_special', $data['actual_special_allowance'] ?? ($data['special_allowance'] ?? 0));
        $this->bind(':act_other_all', $data['actual_other_allowance'] ?? ($data['other_allowance'] ?? 0));
        $this->bind(':pf', $data['pf'] ?? 0);
        $this->bind(':emp_pf', $data['employer_pf'] ?? ($data['pf'] ?? 0));
        $this->bind(':grat', $data['gratuity'] ?? 0);
        $this->bind(':esic', $data['esic'] ?? 0);
        $this->bind(':pt', $data['professional_tax'] ?? 0);
        $this->bind(':tds', $data['tds'] ?? 0);
        $this->bind(':other_ded', $data['other_deductions'] ?? 0);
        $this->bind(':gross', $data['gross_salary'] ?? 0);
        $this->bind(':deductions', $data['total_deductions'] ?? 0);
        $this->bind(':net', $data['net_salary'] ?? 0);
        $this->bind(':ctc', $data['ctc'] ?? 0);

        return $this->execute();
    }

    public function updateEmailStatus($detailId, $status, $error = null, $subject = null, $body = null) {
        if ($subject !== null || $body !== null) {
            $this->query('UPDATE payroll_details 
                          SET email_status = :status, email_sent_at = CURRENT_TIMESTAMP, email_error = :err, email_attempts = email_attempts + 1, email_subject = :subj, email_body = :body 
                          WHERE payroll_detail_id = :id');
            $this->bind(':status', $status);
            $this->bind(':err', $error);
            $this->bind(':subj', $subject);
            $this->bind(':body', $body);
            $this->bind(':id', $detailId);
        } else {
            $this->query('UPDATE payroll_details 
                          SET email_status = :status, email_sent_at = CURRENT_TIMESTAMP, email_error = :err, email_attempts = email_attempts + 1 
                          WHERE payroll_detail_id = :id');
            $this->bind(':status', $status);
            $this->bind(':err', $error);
            $this->bind(':id', $detailId);
        }
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
        $this->query('SELECT e.*, u.name, u.email, r.role_name
                      FROM employees e
                      JOIN users u ON e.user_id = u.user_id
                      JOIN roles r ON u.role_id = r.role_id
                      WHERE u.status = "active" AND r.role_name != "admin"
                      ORDER BY u.name ASC');
        return $this->resultSet();
    }

    public function getAllEmployeesWithStatus() {
        $this->query('SELECT e.*, u.name, u.email, u.status as user_status, r.role_name
                      FROM employees e
                      JOIN users u ON e.user_id = u.user_id
                      JOIN roles r ON u.role_id = r.role_id
                      ORDER BY u.name ASC');
        return $this->resultSet();
    }

    public function getEmployeeByUserId($userId) {
        $this->query('SELECT e.*, u.name, u.email FROM employees e JOIN users u ON e.user_id = u.user_id WHERE e.user_id = :uid');
        $this->bind(':uid', $userId);
        return $this->single();
    }

    public function getEmployeeById($employeeId) {
        $this->query('SELECT e.*, u.name, u.email FROM employees e JOIN users u ON e.user_id = u.user_id WHERE e.employee_id = :id');
        $this->bind(':id', $employeeId);
        return $this->single();
    }
}
