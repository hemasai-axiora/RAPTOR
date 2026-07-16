<?php
/**
 * Leave Model — Handles leaves logic, approvals, and holiday calendar.
 */
class Leave extends Model {

    public function getLeaveBalances(int $userId) {
        $this->query('SELECT * FROM leave_balances WHERE user_id = :uid');
        $this->bind(':uid', $userId);
        return $this->single();
    }

    public function ensureLeaveBalanceExists(int $userId) {
        $bal = $this->getLeaveBalances($userId);
        if (!$bal) {
            $this->query('INSERT IGNORE INTO leave_balances (user_id, sick_leave, casual_leave, earned_leave) VALUES (:uid, 12.00, 12.00, 15.00)');
            $this->bind(':uid', $userId);
            $this->execute();
        }
    }

    public function getLeaveRequests(int $userId): array {
        $this->query('SELECT * FROM leave_requests WHERE user_id = :uid ORDER BY created_at DESC');
        $this->bind(':uid', $userId);
        return $this->resultSet() ?: [];
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
        if ($role === 'admin') {
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

        if (!$column) return false;

        $this->query("UPDATE leave_balances SET {$column} = {$column} - :days WHERE user_id = :uid");
        $this->bind(':days', $days);
        $this->bind(':uid', $userId);
        return $this->execute();
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
}
