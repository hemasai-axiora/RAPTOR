<?php
/**
 * Leave Model — Handles leaves logic, approvals, and holiday calendar.
 */
class Leave extends Model {

    public function getLeaveBalances(int $userId) {
        try {
            $this->query('SELECT * FROM leave_balances WHERE user_id = :uid');
            $this->bind(':uid', $userId);
            $res = $this->single();
            return $res ?: (object)['sick_leave' => 12.00, 'casual_leave' => 12.00, 'earned_leave' => 15.00];
        } catch (Throwable $e) {
            error_log("getLeaveBalances error: " . $e->getMessage());
            return (object)['sick_leave' => 12.00, 'casual_leave' => 12.00, 'earned_leave' => 15.00];
        }
    }

    public function ensureLeaveBalanceExists(int $userId) {
        try {
            $bal = $this->getLeaveBalances($userId);
            if (!$bal || !isset($bal->sick_leave)) {
                $this->query('INSERT IGNORE INTO leave_balances (user_id, sick_leave, casual_leave, earned_leave) VALUES (:uid, 12.00, 12.00, 15.00)');
                $this->bind(':uid', $userId);
                $this->execute();
            }
        } catch (Throwable $e) {
            error_log("ensureLeaveBalanceExists error: " . $e->getMessage());
        }
    }

    public function getLeaveRequests(int $userId): array {
        try {
            $this->query('SELECT * FROM leave_requests WHERE user_id = :uid ORDER BY created_at DESC');
            $this->bind(':uid', $userId);
            return $this->resultSet() ?: [];
        } catch (Throwable $e) {
            error_log("getLeaveRequests error: " . $e->getMessage());
            return [];
        }
    }

    public function getLeaveRequestById(int $id) {
        $this->query('SELECT lr.*, u.name AS employee_name, u.email AS employee_email,
                             e.department, e.job_title, e.reporting_manager_id
                      FROM leave_requests lr
                      JOIN users u ON lr.user_id = u.user_id
                      LEFT JOIN employees e ON u.user_id = e.user_id
                      WHERE lr.leave_request_id = :id');
        $this->bind(':id', $id);
        return $this->single();
    }

    public function getLeaveRequestsForApprover(int $userId, string $role): array {
        if (in_array($role, ['admin', 'ceo', 'employer'], true)) {
            $this->query('SELECT lr.*, u.name AS employee_name, e.department, e.job_title
                          FROM leave_requests lr
                          JOIN users u ON lr.user_id = u.user_id
                          LEFT JOIN employees e ON u.user_id = e.user_id
                          WHERE lr.status IN (\'pending_manager\', \'pending_hr\')
                          ORDER BY lr.created_at DESC');
            return $this->resultSet() ?: [];
        }

        if ($role === 'hr') {
            $this->query('SELECT lr.*, u.name AS employee_name, e.department, e.job_title
                          FROM leave_requests lr
                          JOIN users u ON lr.user_id = u.user_id
                          LEFT JOIN employees e ON u.user_id = e.user_id
                          WHERE lr.status = \'pending_hr\'
                          ORDER BY lr.created_at DESC');
            return $this->resultSet() ?: [];
        }

        // Manager / Team Leader
        $this->query('SELECT lr.*, u.name AS employee_name, e.department, e.job_title
                      FROM leave_requests lr
                      JOIN users u ON lr.user_id = u.user_id
                      JOIN employees e ON u.user_id = e.user_id
                      WHERE e.reporting_manager_id = :uid
                        AND lr.status = \'pending_manager\'
                      ORDER BY lr.created_at DESC');
        $this->bind(':uid', $userId);
        return $this->resultSet() ?: [];
    }

    public function applyLeave(array $data): bool {
        $this->query('INSERT INTO leave_requests (user_id, leave_type, from_date, to_date, half_day, reason, supporting_document, status)
                      VALUES (:uid, :type, :from, :to, :hd, :reason, :doc, \'pending_manager\')');
        $this->bind(':uid', $data['user_id']);
        $this->bind(':type', $data['leave_type']);
        $this->bind(':from', $data['from_date']);
        $this->bind(':to', $data['to_date']);
        $this->bind(':hd', $data['half_day']);
        $this->bind(':reason', $data['reason']);
        $this->bind(':doc', $data['supporting_document'] ?: null);
        return $this->execute();
    }

    public function cancelLeaveRequest(int $id, int $userId): bool {
        $this->query('UPDATE leave_requests SET status = \'cancelled\'
                      WHERE leave_request_id = :id AND user_id = :uid AND status IN (\'pending_manager\', \'pending_hr\')');
        $this->bind(':id', $id);
        $this->bind(':uid', $userId);
        $this->execute();
        return $this->rowCount() > 0;
    }

    public function getLeaveApprovals(int $requestId): array {
        $this->query('SELECT la.*, u.name AS approver_name
                      FROM leave_approvals la
                      JOIN users u ON la.approver_id = u.user_id
                      WHERE la.leave_request_id = :rid
                      ORDER BY la.created_at ASC');
        $this->bind(':rid', $requestId);
        return $this->resultSet() ?: [];
    }

    public function addLeaveApproval(int $requestId, int $approverId, string $stage, string $status, string $comments, ?string $ip): bool {
        $this->query('INSERT INTO leave_approvals (leave_request_id, approver_id, stage, status, comments, ip_address)
                      VALUES (:rid, :aid, :stage, :status, :comments, :ip)');
        $this->bind(':rid', $requestId);
        $this->bind(':aid', $approverId);
        $this->bind(':stage', $stage);
        $this->bind(':status', $status);
        $this->bind(':comments', $comments);
        $this->bind(':ip', $ip);
        return $this->execute();
    }

    public function updateLeaveRequestStatus(int $requestId, string $status): bool {
        $this->query('UPDATE leave_requests SET status = :status WHERE leave_request_id = :rid');
        $this->bind(':status', $status);
        $this->bind(':rid', $requestId);
        return $this->execute();
    }

    public function deductLeaveBalance(int $userId, string $leaveType, float $days): bool {
        $column = match($leaveType) {
            'Sick Leave'   => 'sick_leave',
            'Casual Leave' => 'casual_leave',
            'Earned Leave' => 'earned_leave',
            default        => null
        };

        if ($column) {
            $this->query("UPDATE leave_balances SET {$column} = GREATEST(0, {$column} - :days) WHERE user_id = :uid");
            $this->bind(':days', $days);
            $this->bind(':uid', $userId);
            $this->execute();
        }
        return true;
    }

    public function getHolidays(): array {
        $this->query('SELECT * FROM holidays ORDER BY holiday_date ASC');
        return $this->resultSet() ?: [];
    }

    public function getApprovedLeavesForCalendar(): array {
        $this->query('SELECT lr.*, u.name AS employee_name
                      FROM leave_requests lr
                      JOIN users u ON lr.user_id = u.user_id
                      WHERE lr.status = \'approved\'');
        return $this->resultSet() ?: [];
    }

    /** Ensure user has detailed leave balance rows for given year */
    public function ensureDetailedLeaveBalances(int $userId, int $year = 2026) {
        $types = ['Casual Leave', 'Sick Leave', 'Earned Leave', 'Comp-Off'];
        $quotas = ['Casual Leave' => 12.00, 'Sick Leave' => 10.00, 'Earned Leave' => 15.00, 'Comp-Off' => 6.00];

        foreach ($types as $t) {
            $this->query('SELECT id FROM employee_leave_balances WHERE user_id = :uid AND leave_type_name = :type AND leave_year = :yr');
            $this->bind(':uid', $userId);
            $this->bind(':type', $t);
            $this->bind(':yr', $year);
            if (!$this->single()) {
                $q = $quotas[$t] ?? 10.00;
                $this->query('INSERT INTO employee_leave_balances (user_id, leave_type_name, leave_year, allocated_days) VALUES (:uid, :type, :yr, :q)');
                $this->bind(':uid', $userId);
                $this->bind(':type', $t);
                $this->bind(':yr', $year);
                $this->bind(':q', $q);
                $this->execute();

                $this->query('INSERT INTO employee_leave_transactions (user_id, leave_type_name, transaction_type, days, remarks) VALUES (:uid, :type, \'Accrual\', :q, \'Annual policy quota initialization\')');
                $this->bind(':uid', $userId);
                $this->bind(':type', $t);
                $this->bind(':q', $q);
                $this->execute();
            }
        }
    }

    /** Fetch detailed leave balances for a specific user and year */
    public function getDetailedLeaveBalances(int $userId, int $year = 2026): array {
        $this->ensureDetailedLeaveBalances($userId, $year);
        $this->query('SELECT *, (allocated_days + carried_forward_days - consumed_days - pending_days) AS available_days 
                      FROM employee_leave_balances 
                      WHERE user_id = :uid AND leave_year = :yr 
                      ORDER BY leave_type_name ASC');
        $this->bind(':uid', $userId);
        $this->bind(':yr', $year);
        return $this->resultSet() ?: [];
    }

    /** Record pending hold transaction on leave request submission */
    public function holdPendingLeave(int $userId, string $leaveType, float $days, int $requestId): bool {
        $this->ensureDetailedLeaveBalances($userId, 2026);
        $this->query('UPDATE employee_leave_balances 
                      SET pending_days = pending_days + :days 
                      WHERE user_id = :uid AND leave_type_name = :type AND leave_year = 2026');
        $this->bind(':days', $days);
        $this->bind(':uid', $userId);
        $this->bind(':type', $leaveType);
        $this->execute();

        $this->query('INSERT INTO employee_leave_transactions (user_id, leave_type_name, transaction_type, days, reference_leave_request_id, remarks)
                      VALUES (:uid, :type, \'Pending Hold\', :days, :rid, \'Pending leave request submitted\')');
        $this->bind(':uid', $userId);
        $this->bind(':type', $leaveType);
        $this->bind(':days', $days);
        $this->bind(':rid', $requestId);
        return $this->execute();
    }

    /** Approve pending hold -> convert to Consumption */
    public function approveLeaveBalanceHold(int $userId, string $leaveType, float $days, int $requestId, int $approverId): bool {
        $this->query('UPDATE employee_leave_balances 
                      SET pending_days = GREATEST(0, pending_days - :days),
                          consumed_days = consumed_days + :days 
                      WHERE user_id = :uid AND leave_type_name = :type AND leave_year = 2026');
        $this->bind(':days', $days);
        $this->bind(':uid', $userId);
        $this->bind(':type', $leaveType);
        $this->execute();

        $this->query('INSERT INTO employee_leave_transactions (user_id, leave_type_name, transaction_type, days, reference_leave_request_id, created_by_user_id, remarks)
                      VALUES (:uid, :type, \'Consumption\', :days, :rid, :aid, \'Leave request approved\')');
        $this->bind(':uid', $userId);
        $this->bind(':type', $leaveType);
        $this->bind(':days', $days);
        $this->bind(':rid', $requestId);
        $this->bind(':aid', $approverId);
        return $this->execute();
    }

    /** Release pending hold on rejection or cancellation */
    public function releasePendingLeaveHold(int $userId, string $leaveType, float $days, int $requestId): bool {
        $this->query('UPDATE employee_leave_balances 
                      SET pending_days = GREATEST(0, pending_days - :days) 
                      WHERE user_id = :uid AND leave_type_name = :type AND leave_year = 2026');
        $this->bind(':days', $days);
        $this->bind(':uid', $userId);
        $this->bind(':type', $leaveType);
        $this->execute();

        $this->query('INSERT INTO employee_leave_transactions (user_id, leave_type_name, transaction_type, days, reference_leave_request_id, remarks)
                      VALUES (:uid, :type, \'Pending Release\', :days, :rid, \'Pending leave request rejected or cancelled\')');
        $this->bind(':uid', $userId);
        $this->bind(':type', $leaveType);
        $this->bind(':days', $days);
        $this->bind(':rid', $requestId);
        return $this->execute();
    }

    /** Admin Manual Adjustment */
    public function adjustLeaveBalance(int $userId, string $leaveType, string $txType, float $days, int $adminUserId, string $remarks): bool {
        $this->ensureDetailedLeaveBalances($userId, 2026);
        
        if (in_array($txType, ['Accrual', 'Carry-Forward'], true)) {
            $col = ($txType === 'Carry-Forward') ? 'carried_forward_days' : 'allocated_days';
            $this->query("UPDATE employee_leave_balances SET {$col} = {$col} + :days WHERE user_id = :uid AND leave_type_name = :type AND leave_year = 2026");
        } else {
            $this->query("UPDATE employee_leave_balances SET allocated_days = GREATEST(0, allocated_days + :days) WHERE user_id = :uid AND leave_type_name = :type AND leave_year = 2026");
        }
        $this->bind(':days', $days);
        $this->bind(':uid', $userId);
        $this->bind(':type', $leaveType);
        $this->execute();

        $this->query('INSERT INTO employee_leave_transactions (user_id, leave_type_name, transaction_type, days, created_by_user_id, remarks)
                      VALUES (:uid, :type, :txtype, :days, :aid, :rem)');
        $this->bind(':uid', $userId);
        $this->bind(':type', $leaveType);
        $this->bind(':txtype', $txType);
        $this->bind(':days', $days);
        $this->bind(':aid', $adminUserId);
        $this->bind(':rem', $remarks);
        return $this->execute();
    }

    /** Get transaction history for employee */
    public function getLeaveTransactions(int $userId, int $limit = 25): array {
        $this->query('SELECT tx.*, u.name AS created_by_name 
                      FROM employee_leave_transactions tx
                      LEFT JOIN users u ON tx.created_by_user_id = u.user_id
                      WHERE tx.user_id = :uid 
                      ORDER BY tx.created_at DESC LIMIT :lim');
        $this->bind(':uid', $userId);
        $this->bind(':lim', $limit);
        return $this->resultSet() ?: [];
    }

    /** Fetch all detailed leave balances pivoted by user */
    public function getAllDetailedLeaveBalances(array $filters = []): array {
        $year = (int)($filters['leave_year'] ?? 2026);
        
        // Ensure all active users have detailed leave balance records initialized
        try {
            $this->query("SELECT user_id FROM users WHERE status = 'active'");
            $activeUsers = $this->resultSet() ?: [];
            foreach ($activeUsers as $au) {
                $this->ensureDetailedLeaveBalances((int)$au->user_id, $year);
            }
        } catch (Throwable $e) {
            error_log("ensureDetailedLeaveBalances error: " . $e->getMessage());
        }

        try {
            $sql = "SELECT u.user_id, u.name AS employee_name, u.email, 
                           COALESCE(e.department, 'General') AS department,
                           COALESCE(NULLIF(e.employee_code, ''), CONCAT('EMP-', u.user_id)) AS emp_code,
                           b.leave_type_name, 
                           COALESCE(b.allocated_days, 0) AS allocated_days, 
                           COALESCE(b.carried_forward_days, 0) AS carried_forward_days, 
                           COALESCE(b.consumed_days, 0) AS consumed_days, 
                           COALESCE(b.pending_days, 0) AS pending_days,
                           COALESCE(b.allocated_days + b.carried_forward_days - b.consumed_days - b.pending_days, 0) AS available_days
                    FROM users u
                    LEFT JOIN employees e ON u.user_id = e.user_id
                    LEFT JOIN employee_leave_balances b ON u.user_id = b.user_id AND b.leave_year = :yr
                    WHERE u.status = 'active'";
            
            $params = [':yr' => $year];

            if (!empty($filters['search'])) {
                $sql .= " AND (u.name LIKE :s OR u.email LIKE :s OR e.department LIKE :s OR e.employee_code LIKE :s)";
                $params[':s'] = '%' . $filters['search'] . '%';
            }

            if (!empty($filters['department'])) {
                $sql .= " AND LOWER(TRIM(e.department)) = LOWER(TRIM(:dept))";
                $params[':dept'] = $filters['department'];
            }

            if (!empty($filters['leave_type'])) {
                $sql .= " AND b.leave_type_name = :ltype";
                $params[':ltype'] = $filters['leave_type'];
            }

            $sql .= " ORDER BY u.name ASC, b.leave_type_name ASC";

            $this->query($sql);
            foreach ($params as $k => $v) {
                $this->bind($k, $v);
            }
            $rows = $this->resultSet() ?: [];

            // Pivot by user
            $pivoted = [];
            foreach ($rows as $r) {
                $uid = $r->user_id;
                if (!isset($pivoted[$uid])) {
                    $pivoted[$uid] = (object)[
                        'user_id' => $r->user_id,
                        'employee_name' => $r->employee_name,
                        'email' => $r->email,
                        'department' => $r->department ?: 'General',
                        'emp_code' => $r->emp_code ?: ('EMP-' . $r->user_id),
                        'leave_year' => $year,
                        'balances' => [],
                        'total_available' => 0.0,
                        'is_low_balance' => false
                    ];
                }
                if (!empty($r->leave_type_name)) {
                    $avail = (float)$r->available_days;
                    $pivoted[$uid]->balances[$r->leave_type_name] = $r;
                    $pivoted[$uid]->total_available += $avail;
                    if ($avail < 3.00) {
                        $pivoted[$uid]->is_low_balance = true;
                    }
                }
            }

            if (!empty($filters['low_balance'])) {
                $pivoted = array_filter($pivoted, fn($item) => $item->is_low_balance);
            }

            return array_values($pivoted);
        } catch (Throwable $e) {
            error_log("getAllDetailedLeaveBalances error: " . $e->getMessage());
            return [];
        }
    }
}
