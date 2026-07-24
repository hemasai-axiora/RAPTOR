<?php
/**
 * HrmsController — Central module for employee profiles, dashboards, and reports generation.
 */
class HrmsController extends Controller {
    private $hrmsModel;
    private $leaveModel;
    private $attendanceModel;

    public function __construct() {
        $this->requireAuth();
        $this->hrmsModel = $this->model('Hrms');
        $this->leaveModel = $this->model('Leave');
        $this->attendanceModel = $this->model('Attendance');
    }

    /** Display dynamic HRMS Dashboards. */
    public function dashboard() {
        $userId = (int) $_SESSION['user_id'];
        $role = $_SESSION['user_role'];

        // Get role-specific dashboard metrics
        $stats = $this->hrmsModel->getHRMSDashboardStats($role, $userId);

        // Fetch employee's personal stats for Employee Dashboard fallback
        $personal = [];
        if (!in_array($role, ['admin', 'hr'], true)) {
            $personal['balance'] = $this->leaveModel->getLeaveBalances($userId);
            $personal['today'] = $this->attendanceModel->getToday($userId);
            $personal['tasks'] = []; // Placeholder for task count
        }

        $holidays = $this->leaveModel->getHolidays();

        $data = [
            'title'      => 'HRMS Dashboard | Raptor CRM',
            'active_tab' => 'hrms_dashboard',
            'stats'      => $stats,
            'personal'   => $personal,
            'holidays'   => $holidays
        ];
        $this->viewWithLayout('hrms/dashboard', 'main', $data);
    }

    /** Display Employee Profile. */
    public function profile($id = null) {
        $id = $id ? (int)$id : (int)$_SESSION['user_id'];
        $userId = (int)$_SESSION['user_id'];
        $role = $_SESSION['user_role'];

        // Security boundary: Non-admin/non-hr users can only view their own profile
        if ($id !== $userId && !in_array($role, ['admin', 'hr'], true)) {
            $this->redirect('index.php?route=hrms/profile/' . $userId);
            return;
        }

        $profile = $this->hrmsModel->getProfileByUserId($id);
        if (!$profile) {
            $_SESSION['user_error'] = 'Employee profile not found.';
            $this->redirect('index.php?route=dashboard/index');
            return;
        }

        // Calculate attendance percentage (mocked or actual)
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT COUNT(*) FROM attendance WHERE user_id = :uid");
        $stmt->execute([':uid' => $id]);
        $totalAtt = (int) $stmt->fetchColumn();

        $stmt2 = $db->prepare("SELECT COUNT(*) FROM attendance WHERE user_id = :uid AND status = 'present'");
        $stmt2->execute([':uid' => $id]);
        $presentAtt = (int) $stmt2->fetchColumn();

        $attPercent = ($totalAtt > 0) ? round(($presentAtt / $totalAtt) * 100, 2) : 100.00;

        $balances = $this->leaveModel->getLeaveBalances($id);

        $data = [
            'title'        => $profile->name . '\'s Profile | Raptor CRM',
            'active_tab'   => 'profile',
            'profile'      => $profile,
            'att_percent'  => $attPercent,
            'balances'     => $balances
        ];
        $this->viewWithLayout('hrms/profile', 'main', $data);
    }

    /** Edit Profile Details (POST). */
    public function editProfile($id = null) {
        $id = $id ? (int)$id : (int)$_SESSION['user_id'];
        $userId = (int)$_SESSION['user_id'];
        $role = $_SESSION['user_role'];

        // Security boundary: Non-admin/non-hr users can only edit their own profile
        if ($id !== $userId && !in_array($role, ['admin', 'hr'], true)) {
            $this->redirect('index.php?route=hrms/profile/' . $userId);
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $_POST = filter_input_array(INPUT_POST, FILTER_SANITIZE_SPECIAL_CHARS) ?: [];

            $updateData = [
                'blood_group'       => trim($_POST['blood_group'] ?? ''),
                'address'           => trim($_POST['address'] ?? ''),
                'experience_years'  => (float)($_POST['experience_years'] ?? 0.0),
                'skills'            => trim($_POST['skills'] ?? ''),
                'phone_number'      => trim($_POST['phone_number'] ?? ''),
                'emergency_contact' => trim($_POST['emergency_contact'] ?? '')
            ];

            if ($this->hrmsModel->updateProfile($id, $updateData)) {
                $this->audit("Updated employee profile details for user #{$id}", 'employees', $id);
                $_SESSION['profile_success'] = 'Profile updated successfully.';
            } else {
                $_SESSION['profile_error'] = 'Failed to update profile.';
            }
        }
        $this->redirect('index.php?route=hrms/profile/' . $id);
    }

    /** Display HRMS Reports interface. */
    public function reports() {
        $role = $_SESSION['user_role'];
        if (!in_array($role, ['admin', 'ceo', 'hr', 'analyst'], true)) {
            $this->redirect('index.php?route=dashboard/index');
            return;
        }

        $data = [
            'title'      => 'HRMS Reports | Raptor CRM',
            'active_tab' => 'hrms_reports'
        ];
        $this->viewWithLayout('hrms/reports', 'main', $data);
    }

    /** Export CSV Reports based on type. */
    public function exportReport($type = '') {
        $role = $_SESSION['user_role'];
        if (!in_array($role, ['admin', 'ceo', 'hr', 'analyst'], true)) {
            $this->redirect('index.php?route=dashboard/index');
            return;
        }

        $from = $_GET['from'] ?? date('Y-m-01');
        $to = $_GET['to'] ?? date('Y-m-d');
        $today = date('Y-m-d');

        if ($to < $from) {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="error.csv"');
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Error', 'To Date cannot be earlier than From Date.']);
            fclose($out);
            exit();
        }
        if ($type !== 'employees' && ($from > $today || $to > $today)) {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="error.csv"');
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Error', 'From Date and To Date cannot be in the future.']);
            fclose($out);
            exit();
        }
        
        $db = Database::getInstance()->getConnection();

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="hrms_' . $type . '_' . $from . '_to_' . $to . '.csv"');
        
        $out = fopen('php://output', 'w');

        if ($type === 'attendance') {
            fputcsv($out, ['Date', 'Employee', 'Check-in', 'Check-out', 'Worked (min)', 'Status', 'Approval']);
            $stmt = $db->prepare("SELECT a.work_date, u.name, a.login_at, a.logout_at, a.worked_minutes, a.status, a.approval_status
                                  FROM attendance a JOIN users u ON a.user_id = u.user_id
                                  WHERE a.work_date BETWEEN :from AND :to ORDER BY a.work_date DESC");
            $stmt->execute([':from' => $from, ':to' => $to]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                fputcsv($out, $row);
            }
        } elseif ($type === 'leaves') {
            fputcsv($out, ['Employee', 'Type', 'From Date', 'To Date', 'Half Day', 'Reason', 'Status']);
            $stmt = $db->prepare("SELECT u.name, lr.leave_type, lr.from_date, lr.to_date, lr.half_day, lr.reason, lr.status
                                  FROM leave_requests lr JOIN users u ON lr.user_id = u.user_id
                                  WHERE lr.from_date BETWEEN :from AND :to ORDER BY lr.from_date DESC");
            $stmt->execute([':from' => $from, ':to' => $to]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                fputcsv($out, $row);
            }
        } elseif ($type === 'late_coming') {
            fputcsv($out, ['Date', 'Employee', 'Check-in Time', 'Delay (min)']);
            $stmt = $db->prepare("SELECT a.work_date, u.name, a.login_at, TIMESTAMPDIFF(MINUTE, CONCAT(a.work_date, ' 09:30:00'), a.login_at) AS delay
                                  FROM attendance a JOIN users u ON a.user_id = u.user_id
                                  WHERE a.is_late = 1 AND a.work_date BETWEEN :from AND :to ORDER BY a.work_date DESC");
            $stmt->execute([':from' => $from, ':to' => $to]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                fputcsv($out, $row);
            }
        } elseif ($type === 'employees') {
            fputcsv($out, ['EmpID', 'Name', 'Email', 'Phone', 'Job Title', 'Department', 'Joining Date', 'Status']);
            $stmt = $db->query("SELECT e.employee_code, u.name, u.email, e.phone_number, e.job_title, e.department, e.date_of_joining, e.status
                                FROM employees e JOIN users u ON e.user_id = u.user_id ORDER BY e.employee_code ASC");
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                fputcsv($out, $row);
            }
        } else {
            fputcsv($out, ['Error', 'Unsupported Report Type']);
        }

        fclose($out);
        exit();
    }
}
