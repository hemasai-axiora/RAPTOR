<?php
/**
 * LeavesController — Handles employee leave applications, history, calendar, and approvals workflow.
 */
class LeavesController extends Controller {
    private $leaveModel;
    private $hrmsModel;
    private $notificationModel;

    public function __construct() {
        $this->requireAuth();
        $this->leaveModel = $this->model('Leave');
        $this->hrmsModel = $this->model('Hrms');
        $this->notificationModel = $this->model('Notification');
    }

    /** Display Employee Leave Dashboard, Apply form, and Leave History. */
    public function index() {
        $userId = (int) $_SESSION['user_id'];
        
        // Ensure leave balance record exists
        $this->leaveModel->ensureLeaveBalanceExists($userId);

        $balances = $this->leaveModel->getLeaveBalances($userId);
        $requests = $this->leaveModel->getLeaveRequests($userId);
        $holidays = $this->leaveModel->getHolidays();

        $data = [
            'title'      => 'My Leaves | Raptor CRM',
            'active_tab' => 'leaves',
            'balances'   => $balances,
            'requests'   => $requests,
            'holidays'   => $holidays
        ];
        $this->viewWithLayout('leaves/index', 'main', $data);
    }

    /** Apply for a new leave request (POST). */
    public function apply() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $_POST = filter_input_array(INPUT_POST, FILTER_SANITIZE_SPECIAL_CHARS) ?: [];
            
            $userId = (int) $_SESSION['user_id'];
            $leaveType = trim($_POST['leave_type'] ?? '');
            $fromDate = trim($_POST['from_date'] ?? '');
            $toDate = trim($_POST['to_date'] ?? '');
            $halfDay = isset($_POST['half_day']) ? 1 : 0;
            $reason = trim($_POST['reason'] ?? '');
            
            // Supporting document handling
            $docKey = null;
            if (isset($_FILES['supporting_doc']) && $_FILES['supporting_doc']['error'] === UPLOAD_ERR_OK) {
                try {
                    $docKey = Storage::put($_FILES['supporting_doc'], 'leaves');
                } catch (Exception $e) {
                    $_SESSION['leaves_error'] = 'Document upload failed: ' . $e->getMessage();
                    $this->redirect('index.php?route=leaves/index');
                    return;
                }
            }

            // Validations
            if (empty($leaveType) || empty($fromDate) || empty($toDate) || empty($reason)) {
                $_SESSION['leaves_error'] = 'All fields are required.';
                $this->redirect('index.php?route=leaves/index');
                return;
            }

            $tsFrom = strtotime($fromDate);
            $tsTo = strtotime($toDate);
            $todayTs = strtotime(date('Y-m-d'));
            
            if ($tsFrom < $todayTs) {
                $_SESSION['leaves_error'] = 'Leave application From Date must be present or future date.';
                $this->redirect('index.php?route=leaves/index');
                return;
            }

            if ($tsTo < $tsFrom) {
                $_SESSION['leaves_error'] = 'To Date cannot be earlier than From Date.';
                $this->redirect('index.php?route=leaves/index');
                return;
            }

            if (!empty($reason) && !Validation::validateHasAlphanumeric($reason)) {
                $_SESSION['leaves_error'] = 'Leave reason must contain alphanumeric characters.';
                $this->redirect('index.php?route=leaves/index');
                return;
            }

            // Calculate duration in days
            $days = $halfDay ? 0.5 : (int)round(($tsTo - $tsFrom) / 86400) + 1;

            // Check balance
            $balances = $this->leaveModel->getLeaveBalances($userId);
            $available = 0.0;
            if ($leaveType === 'Sick Leave')   $available = (float)($balances->sick_leave ?? 0);
            if ($leaveType === 'Casual Leave') $available = (float)($balances->casual_leave ?? 0);
            if ($leaveType === 'Earned Leave') $available = (float)($balances->earned_leave ?? 0);

            if ($days > $available) {
                $_SESSION['leaves_error'] = "Insufficient leave balance. Requested {$days} days, but only {$available} days available.";
                $this->redirect('index.php?route=leaves/index');
                return;
            }

            $applyData = [
                'user_id'             => $userId,
                'leave_type'          => $leaveType,
                'from_date'           => $fromDate,
                'to_date'             => $toDate,
                'half_day'            => $halfDay,
                'reason'              => $reason,
                'supporting_document' => $docKey
            ];

            if ($this->leaveModel->applyLeave($applyData)) {
                // Find reporting manager to notify them
                $profile = $this->hrmsModel->getProfileByUserId($userId);
                if ($profile && $profile->reporting_manager_id) {
                    $this->notificationModel->addNotification([
                        'user_id' => $profile->reporting_manager_id,
                        'title' => 'New Leave Request',
                        'message' => "{$_SESSION['user_name']} has applied for {$days} days of {$leaveType}.",
                        'type' => 'leave',
                        'action_url' => 'index.php?route=leaves/approvals',
                        'severity' => 'info'
                    ]);
                }
                
                $this->audit("Applied for {$leaveType} (From: {$fromDate} To: {$toDate})", 'leave_requests');
                $_SESSION['leaves_success'] = 'Leave request submitted successfully for approval.';
            } else {
                $_SESSION['leaves_error'] = 'Failed to submit leave request. Please try again.';
            }
        }
        $this->redirect('index.php?route=leaves/index');
    }

    /** Cancel a pending leave request (POST). */
    public function cancel($id = null) {
        $id = (int)$id;
        $userId = (int)$_SESSION['user_id'];
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if ($this->leaveModel->cancelLeaveRequest($id, $userId)) {
                $this->audit("Cancelled pending leave request #{$id}", 'leave_requests', $id);
                $_SESSION['leaves_success'] = 'Leave request has been cancelled.';
            } else {
                $_SESSION['leaves_error'] = 'Leave request could not be cancelled. It may have already been reviewed.';
            }
        }
        $this->redirect('index.php?route=leaves/index');
    }

    /** Display pending leave approvals queue. */
    public function approvals() {
        $userId = (int)$_SESSION['user_id'];
        $role = $_SESSION['user_role'];

        if (!in_array($role, ['admin', 'hr', 'manager', 'team_leader'], true)) {
            $this->redirect('index.php?route=leaves/index');
            return;
        }

        $pending = $this->leaveModel->getLeaveRequestsForApprover($userId, $role);

        $data = [
            'title'      => 'Leave Approvals | Raptor CRM',
            'active_tab' => 'leave_approvals',
            'pending'    => $pending
        ];
        $this->viewWithLayout('leaves/approvals', 'main', $data);
    }

    /** Approve a leave request (POST). */
    public function approve($id = null) {
        $id = (int)$id;
        $userId = (int)$_SESSION['user_id'];
        $role = $_SESSION['user_role'];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $req = $this->leaveModel->getLeaveRequestById($id);
            if (!$req) {
                $_SESSION['leaves_error'] = 'Leave request not found.';
                $this->redirect('index.php?route=leaves/approvals');
                return;
            }

            $comments = trim($_POST['comments'] ?? '');
            $ip = $_SERVER['REMOTE_ADDR'] ?? null;

            // Enforce Two-stage Workflow
            if ($role === 'manager' || $role === 'team_leader') {
                // Manager approval advances it to HR
                if ($req->status === 'pending_manager') {
                    $this->leaveModel->addLeaveApproval($id, $userId, 'manager', 'approved', $comments, $ip);
                    $this->leaveModel->updateLeaveRequestStatus($id, 'pending_hr');
                    
                    // Notify HR
                    // Fetch all HR users to notify them
                    $db = Database::getInstance()->getConnection();
                    $stmt = $db->query("SELECT user_id FROM users u JOIN roles r ON u.role_id = r.role_id WHERE r.role_name = 'hr'");
                    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $hrId) {
                        $this->notificationModel->addNotification([
                            'user_id' => $hrId,
                            'title' => 'Leave Request Approved by Manager',
                            'message' => "{$req->employee_name}'s request for {$req->leave_type} approved by Manager. Needs HR approval.",
                            'type' => 'leave',
                            'action_url' => 'index.php?route=leaves/approvals',
                            'severity' => 'info'
                        ]);
                    }
                    
                    $this->audit("Manager approved leave request #{$id}", 'leave_requests', $id);
                    $_SESSION['leaves_success'] = 'Request approved and forwarded to HR.';
                } else {
                    $_SESSION['leaves_error'] = 'Invalid request stage for manager approval.';
                }
            } elseif ($role === 'hr' || $role === 'admin') {
                // HR/Admin final approval
                if ($req->status === 'pending_hr' || ($role === 'admin' && $req->status === 'pending_manager')) {
                    $stage = ($req->status === 'pending_manager') ? 'manager' : 'hr';
                    
                    $this->leaveModel->addLeaveApproval($id, $userId, $stage, 'approved', $comments, $ip);
                    
                    // Deduct balance
                    $days = $req->half_day ? 0.5 : (int)round((strtotime($req->to_date) - strtotime($req->from_date)) / 86400) + 1;
                    $this->leaveModel->deductLeaveBalance($req->user_id, $req->leave_type, $days);
                    
                    $this->leaveModel->updateLeaveRequestStatus($id, 'approved');

                    // Notify employee
                    $this->notificationModel->addNotification([
                        'user_id' => $req->user_id,
                        'title' => 'Leave Request Approved',
                        'message' => "Your leave request for {$req->leave_type} (From: {$req->from_date} To: {$req->to_date}) has been approved.",
                        'type' => 'leave',
                        'action_url' => 'index.php?route=leaves/index',
                        'severity' => 'info'
                    ]);

                    $this->audit("Final approved leave request #{$id}", 'leave_requests', $id);
                    $_SESSION['leaves_success'] = 'Leave request has been final approved.';
                } else {
                    $_SESSION['leaves_error'] = 'Invalid request stage for HR/Admin approval.';
                }
            }
        }
        $this->redirect('index.php?route=leaves/approvals');
    }

    /** Reject a leave request (POST). */
    public function reject($id = null) {
        $id = (int)$id;
        $userId = (int)$_SESSION['user_id'];
        $role = $_SESSION['user_role'];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $req = $this->leaveModel->getLeaveRequestById($id);
            if (!$req) {
                $_SESSION['leaves_error'] = 'Leave request not found.';
                $this->redirect('index.php?route=leaves/approvals');
                return;
            }

            $comments = trim($_POST['comments'] ?? '');
            if (empty($comments)) {
                $_SESSION['leaves_error'] = 'Rejection comments are required.';
                $this->redirect('index.php?route=leaves/approvals');
                return;
            }

            $ip = $_SERVER['REMOTE_ADDR'] ?? null;
            $stage = in_array($role, ['hr', 'admin'], true) ? 'hr' : 'manager';

            $this->leaveModel->addLeaveApproval($id, $userId, $stage, 'rejected', $comments, $ip);
            $this->leaveModel->updateLeaveRequestStatus($id, 'rejected');

            // Notify employee
            $this->notificationModel->addNotification([
                'user_id' => $req->user_id,
                'title' => 'Leave Request Rejected',
                'message' => "Your leave request for {$req->leave_type} has been rejected: {$comments}",
                'type' => 'leave',
                'action_url' => 'index.php?route=leaves/index',
                'severity' => 'warning'
            ]);

            $this->audit("Rejected leave request #{$id}", 'leave_requests', $id);
            $_SESSION['leaves_success'] = 'Leave request has been rejected.';
        }
        $this->redirect('index.php?route=leaves/approvals');
    }

    /** Renders Calendar view of Approved Leaves and Holidays. */
    public function calendar() {
        $holidays = $this->leaveModel->getHolidays();
        $leaves = $this->leaveModel->getApprovedLeavesForCalendar();

        $data = [
            'title'      => 'Holidays & Leave Calendar | Raptor CRM',
            'active_tab' => 'leaves_calendar',
            'holidays'   => $holidays,
            'leaves'     => $leaves
        ];
        $this->viewWithLayout('leaves/calendar', 'main', $data);
    }
}
