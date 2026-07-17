<?php
/**
 * HRMS Model — Manage employee profiles, departments, dashboards, and reporting mapping.
 */
class Hrms extends Model {

    public function getProfileByUserId(int $userId) {
        $this->query('SELECT u.user_id, u.name, u.email, u.status AS user_status, u.created_at AS user_created_at,
                             r.role_name,
                             e.*,
                             m.name AS manager_name,
                             ba.account_holder_name, ba.bank_name, ba.account_number, ba.ifsc_code, ba.branch_name, ba.account_type
                      FROM users u
                      JOIN roles r ON u.role_id = r.role_id
                      LEFT JOIN employees e ON u.user_id = e.user_id
                      LEFT JOIN users m ON e.reporting_manager_id = m.user_id
                      LEFT JOIN bank_accounts ba ON e.employee_id = ba.employee_id
                      WHERE u.user_id = :uid');
        $this->bind(':uid', $userId);
        return $this->single();
    }

    public function updateProfile(int $userId, array $data): bool {
        // First check if employee record exists
        $this->query('SELECT employee_id FROM employees WHERE user_id = :uid');
        $this->bind(':uid', $userId);
        $emp = $this->single();

        if ($emp) {
            $this->query('UPDATE employees SET 
                            blood_group = :bg,
                            address = :addr,
                            experience_years = :exp,
                            skills = :skills,
                            phone_number = :phone,
                            emergency_contact = :emer
                          WHERE user_id = :uid');
            $this->bind(':bg', $data['blood_group']);
            $this->bind(':addr', $data['address']);
            $this->bind(':exp', $data['experience_years']);
            $this->bind(':skills', $data['skills']);
            $this->bind(':phone', $data['phone_number']);
            $this->bind(':emer', $data['emergency_contact']);
            $this->bind(':uid', $userId);
            return $this->execute();
        }
        return false;
    }

    public function getActiveEmployeesCount(): int {
        $this->query('SELECT COUNT(*) FROM employees WHERE status = \'active\'');
        return (int) $this->fetchColumn();
    }

    public function getHRMSDashboardStats(string $role, int $userId): array {
        $stats = [];
        $db = $this->db;

        if ($role === 'admin' || $role === 'hr') {
            // Active employees count
            $stmt = $db->query("SELECT COUNT(*) FROM employees WHERE status = 'active'");
            $stats['active_employees'] = (int) $stmt->fetchColumn();

            // Pending leaves
            $stmt = $db->query("SELECT COUNT(*) FROM leave_requests WHERE status = 'pending_hr'");
            $stats['pending_leaves'] = (int) $stmt->fetchColumn();

            // Pending attendance approvals by role
            if ($role === 'admin') {
                $stmt = $db->query("SELECT COUNT(*) FROM attendance a JOIN users u ON a.user_id = u.user_id JOIN roles r ON u.role_id = r.role_id WHERE a.attendance_status = 'Pending' AND r.role_name = 'hr'");
                $stats['pending_hr_attendance'] = (int) $stmt->fetchColumn();
            } else { // hr
                $stmt = $db->query("SELECT COUNT(*) FROM attendance a JOIN users u ON a.user_id = u.user_id JOIN roles r ON u.role_id = r.role_id WHERE a.attendance_status = 'Pending' AND r.role_name IN ('manager', 'team_leader', 'finance', 'analyst')");
                $stats['pending_manager_attendance'] = (int) $stmt->fetchColumn();
            }

            // Upcoming birthdays (next 30 days)
            $this->query("SELECT u.name, e.date_of_birth, e.job_title, e.profile_photo
                          FROM employees e 
                          JOIN users u ON e.user_id = u.user_id
                          WHERE DATE_FORMAT(e.date_of_birth, '%m-%d') BETWEEN DATE_FORMAT(NOW(), '%m-%d') AND DATE_FORMAT(DATE_ADD(NOW(), INTERVAL 30 DAY), '%m-%d')
                          ORDER BY DATE_FORMAT(e.date_of_birth, '%m-%d') ASC");
            $stats['birthdays'] = $this->resultSet() ?: [];

            // Work anniversaries (next 30 days)
            $this->query("SELECT u.name, e.date_of_joining, e.job_title, e.profile_photo, YEAR(NOW()) - YEAR(e.date_of_joining) AS years
                          FROM employees e 
                          JOIN users u ON e.user_id = u.user_id
                          WHERE DATE_FORMAT(e.date_of_joining, '%m-%d') BETWEEN DATE_FORMAT(NOW(), '%m-%d') AND DATE_FORMAT(DATE_ADD(NOW(), INTERVAL 30 DAY), '%m-%d')
                            AND YEAR(e.date_of_joining) < YEAR(NOW())
                          ORDER BY DATE_FORMAT(e.date_of_joining, '%m-%d') ASC");
            $stats['anniversaries'] = $this->resultSet() ?: [];

            // Payroll runs
            $stmt = $db->query("SELECT COUNT(*) FROM payroll_runs");
            $stats['payroll_runs'] = (int) $stmt->fetchColumn();
        }

        if ($role === 'manager' || $role === 'team_leader') {
            // Team size
            $this->query("SELECT COUNT(*) FROM employees WHERE reporting_manager_id = :uid AND status = 'active'");
            $this->bind(':uid', $userId);
            $stats['team_size'] = (int) $this->fetchColumn();

            // Pending leave requests for their team
            $this->query("SELECT COUNT(*) 
                          FROM leave_requests lr
                          JOIN employees e ON lr.user_id = e.user_id
                          WHERE e.reporting_manager_id = :uid AND lr.status = 'pending_manager'");
            $this->bind(':uid', $userId);
            $stats['pending_leaves'] = (int) $this->fetchColumn();

            // Pending attendance approvals for their team
            $this->query("SELECT COUNT(*) 
                          FROM attendance a
                          JOIN employees e ON a.user_id = e.user_id
                          WHERE e.reporting_manager_id = :uid AND a.attendance_status = 'Pending'");
            $this->bind(':uid', $userId);
            $stats['pending_attendance'] = (int) $this->fetchColumn();

            // Today's attendance requests for their team
            $this->query("SELECT COUNT(*) 
                          FROM attendance a
                          JOIN employees e ON a.user_id = e.user_id
                          WHERE e.reporting_manager_id = :uid AND a.work_date = CURDATE()");
            $this->bind(':uid', $userId);
            $stats['today_requests'] = (int) $this->fetchColumn();

            // Approved today for their team
            $this->query("SELECT COUNT(*) 
                          FROM attendance a
                          JOIN employees e ON a.user_id = e.user_id
                          WHERE e.reporting_manager_id = :uid AND a.attendance_status = 'Approved' AND DATE(a.approved_at) = CURDATE()");
            $this->bind(':uid', $userId);
            $stats['approved_today'] = (int) $this->fetchColumn();

            // Rejected today for their team
            $this->query("SELECT COUNT(*) 
                          FROM attendance a
                          JOIN employees e ON a.user_id = e.user_id
                          WHERE e.reporting_manager_id = :uid AND a.attendance_status = 'Rejected' AND DATE(a.approved_at) = CURDATE()");
            $this->bind(':uid', $userId);
            $stats['rejected_today'] = (int) $this->fetchColumn();
        }

        return $stats;
    }

    public function getDepartments(): array {
        $this->query('SELECT DISTINCT department FROM employees WHERE department IS NOT NULL AND department <> \'\'');
        $rows = $this->resultSet() ?: [];
        $depts = [];
        foreach ($rows as $r) {
            $depts[] = $r->department;
        }
        return $depts;
    }

    public function getSystemHealth(): array {
        return [
            'php_version' => PHP_VERSION,
            'db_version' => $this->db->getAttribute(PDO::ATTR_SERVER_VERSION),
            'os' => PHP_OS,
            'server_time' => date('Y-m-d H:i:s'),
            'max_upload' => ini_get('upload_max_filesize')
        ];
    }

    private function fetchColumn() {
        $this->execute();
        return $this->stmt->fetchColumn();
    }
}
