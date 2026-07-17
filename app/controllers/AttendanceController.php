<?php
/**
 * Raptor CRM — Attendance (Sprint 2)
 * Sales persons check in/out with a selfie + GPS. Managers/team leaders can
 * view (approvals come in Sprint 3). Check-in/out are JSON/AJAX endpoints so
 * the responsive UI can capture camera + location before posting.
 */

class AttendanceController extends Controller {
    private $att;

    public function __construct() {
        $this->requireAuth();
        $this->att = $this->model('Attendance');
    }

    /** Self attendance screen. */
    public function index() {
        $this->requirePermission('attendance', 'view');
        if ($_SESSION['user_role'] === 'admin') {
            $this->redirect('index.php?route=dashboard/index');
        }
        $userId = (int) $_SESSION['user_id'];
        $today  = $this->att->getToday($userId);
        $data = [
            'title'       => 'My Attendance | Raptor CRM',
            'active_tab'  => 'attendance',
            'today'       => $today,
            'has_consent' => $this->att->hasConsent($userId),
            'shift'       => $this->att->getShiftConfig(),
            'open_break'  => $today ? $this->att->getOpenBreak($today->attendance_id) : null,
        ];
        $this->viewWithLayout('attendance/index', 'main', $data);
    }

    /** Start a break (JSON). */
    public function breakStart() {
        $this->requireAuthApi();
        $reason = isset($_POST['reason']) ? substr(trim($_POST['reason']), 0, 150) : null;
        $res = $this->att->startBreak((int) $_SESSION['user_id'], $reason);
        if (!$res['ok']) { $this->jsonError($res['message']); }
        $this->audit('Break started', 'attendance');
        $this->jsonOk(null, $res['message']);
    }

    /** End a break (JSON). */
    public function breakEnd() {
        $this->requireAuthApi();
        $res = $this->att->endBreak((int) $_SESSION['user_id']);
        if (!$res['ok']) { $this->jsonError($res['message']); }
        $this->audit('Break ended', 'attendance');
        $this->jsonOk(['minutes' => $res['minutes'] ?? 0], $res['message']);
    }

    /** Record location-tracking consent (JSON). */
    public function consent() {
        $this->requireAuthApi();
        $cfg = $this->att->getShiftConfig();
        $ok = $this->att->saveConsent(
            (int) $_SESSION['user_id'],
            $_SERVER['REMOTE_ADDR'] ?? null,
            $cfg['policy_version']
        );
        if (!$ok) { $this->jsonError('Could not save consent.'); }
        $this->audit('Attendance consent granted', 'location_consent');
        $this->jsonOk(null, 'Consent recorded.');
    }

    /** Check in (JSON): expects selfie data URL + lat/lng/accuracy. */
    public function checkin() {
        $this->requireAuthApi();
        $userId = (int) $_SESSION['user_id'];

        if (!$this->att->hasConsent($userId)) {
            $this->jsonError('Location consent is required before checking in.', 403);
        }

        $in = $this->collectCapture();
        if ($in === null) { return; } // error already emitted

        $res = $this->att->checkIn($userId, $in);
        if (!$res['ok']) { $this->jsonError($res['message']); }

        $this->audit('Checked in' . ($res['is_late'] ? ' (LATE)' : ''), 'attendance');
        $this->jsonOk(['is_late' => $res['is_late']], $res['message']);
    }

    /** Check out (JSON). */
    public function checkout() {
        $this->requireAuthApi();
        $userId = (int) $_SESSION['user_id'];

        $in = $this->collectCapture();
        if ($in === null) { return; }

        $res = $this->att->checkOut($userId, $in);
        if (!$res['ok']) { $this->jsonError($res['message']); }

        $this->audit('Checked out', 'attendance');
        $this->jsonOk(['worked_minutes' => $res['worked_minutes'], 'is_early' => $res['is_early']], $res['message']);
    }

    // ================= Oversight: approvals & report =================

    private function requireOversight() {
        $this->requirePermission('attendance', 'approve');
    }

    /** Pending attendance exceptions queue (scoped to the viewer's team subtree). */
    public function approvals() {
        $this->requireOversight();
        $data = [
            'title'      => 'Attendance Approvals | Raptor CRM',
            'active_tab' => 'attendance_approvals',
            'pending'    => $this->att->getPendingApprovals($this->visibleUserIds()),
        ];
        $this->viewWithLayout('attendance/approvals', 'main', $data);
    }

    public function approve($id = 0) {
        $this->requireOversight();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->requireAuth(); // re-validates CSRF on POST
            if ($this->inScope((int) $id)) {
                $ok = $this->att->setApproval((int) $id, 'Approved', (int) $_SESSION['user_id']);
                if ($ok) { $this->audit('Approved attendance #' . (int) $id, 'attendance', (int) $id); }
            }
        }
        $this->redirect('index.php?route=attendance/approvals');
    }

    public function reject($id = 0) {
        $this->requireOversight();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->requireAuth();
            $remark = isset($_POST['remark']) ? substr(trim($_POST['remark']), 0, 255) : null;
            if ($this->inScope((int) $id)) {
                $ok = $this->att->setApproval((int) $id, 'Rejected', (int) $_SESSION['user_id'], $remark);
                if ($ok) { $this->audit('Rejected attendance #' . (int) $id, 'attendance', (int) $id); }
            }
        }
        $this->redirect('index.php?route=attendance/approvals');
    }

    /** Ensure the target attendance row belongs to a user the approver may see, and matches hierarchy. */
    private function inScope(int $attendanceId): bool {
        $rec = $this->att->getById($attendanceId);
        if (!$rec) { return false; }

        $role = $_SESSION['user_role'];
        $uid  = (int) $_SESSION['user_id'];

        // Enforce: Cannot approve their own attendance
        if ((int) $rec->user_id === $uid) {
            return false;
        }

        // Fetch owner details
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT u.user_id, r.role_name, e.reporting_manager_id 
                              FROM users u 
                              JOIN roles r ON u.role_id = r.role_id
                              LEFT JOIN employees e ON u.user_id = e.user_id 
                              WHERE u.user_id = :uid");
        $stmt->execute([':uid' => $rec->user_id]);
        $owner = $stmt->fetch(PDO::FETCH_OBJ);
        if (!$owner) { return false; }

        // Enforce specific approval workflow hierarchy:
        if ($role === 'manager' || $role === 'team_leader') {
            // Manager/Team Leader can only approve employees assigned to them (Employee / Sales Person)
            return in_array($owner->role_name, ['employee', 'sales_person'], true) && (int) $owner->reporting_manager_id === $uid;
        }

        if ($role === 'hr') {
            // HR can only approve Manager, Team Leader, Finance, and Analyst attendance
            return in_array($owner->role_name, ['manager', 'team_leader', 'finance', 'analyst'], true);
        }

        if ($role === 'admin') {
            // Admin can only approve HR attendance
            return $owner->role_name === 'hr';
        }

        return false;
    }

    /** Attendance report with date range + team scoping. */
    public function report() {
        $this->requireOversight();
        [$from, $to] = $this->reportRange();
        $error = '';
        $rows = [];
        if ($to < $from) {
            $error = 'To Date cannot be earlier than From Date.';
        } else {
            $rows = $this->att->getReport(['from' => $from, 'to' => $to, 'user_ids' => $this->visibleUserIds()]);
        }
        $data = [
            'title'      => 'Attendance Report | Raptor CRM',
            'active_tab' => 'attendance_report',
            'from'       => $from,
            'to'         => $to,
            'rows'       => $rows,
            'error'      => $error,
        ];
        $this->viewWithLayout('attendance/report', 'main', $data);
    }

    /** CSV export (native fputcsv, no external library). */
    public function exportReport() {
        $this->requireOversight();
        [$from, $to] = $this->reportRange();
        if ($to < $from) {
            $_SESSION['report_error'] = 'To Date cannot be earlier than From Date.';
            $this->redirect('index.php?route=attendance/report&from=' . urlencode($from) . '&to=' . urlencode($to));
            return;
        }
        $rows = $this->att->getReport(['from' => $from, 'to' => $to, 'user_ids' => $this->visibleUserIds()]);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="attendance_' . $from . '_to_' . $to . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Date', 'Employee', 'Team', 'Check-in', 'Check-out', 'Worked (min)', 'Break (min)', 'Late', 'Early Logout', 'Geofence', 'Status', 'Approval']);
        foreach ($rows as $r) {
            fputcsv($out, [
                $r->work_date,
                $r->employee_name,
                $r->team_name ?? '',
                $r->login_at ?? '',
                $r->logout_at ?? '',
                (int) $r->worked_minutes,
                (int) $r->break_minutes,
                $r->is_late ? 'Yes' : 'No',
                $r->is_early_logout ? 'Yes' : 'No',
                $r->geofence_ok === null ? 'N/A' : ($r->geofence_ok ? 'In' : 'Out'),
                $r->status,
                $r->approval_status,
            ]);
        }
        fclose($out);
        exit();
    }

    /** Parse from/to GET params, defaulting to the last 30 days. */
    private function reportRange(): array {
        $to   = (!empty($_GET['to'])   && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to']))   ? $_GET['to']   : date('Y-m-d');
        $from = (!empty($_GET['from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from'])) ? $_GET['from'] : date('Y-m-d', strtotime('-29 days'));
        return [$from, $to];
    }

    /**
     * Shared: pull selfie + geo + device from the request, store the selfie,
     * and return a normalized array. Emits a JSON error and returns null on failure.
     */
    private function collectCapture(): ?array {
        $selfie = $_POST['selfie'] ?? '';
        if ($selfie === '') {
            $this->jsonError('A selfie is required.');
            return null;
        }

        try {
            $selfieKey = Storage::putDataUrl($selfie, 'attendance');
        } catch (Exception $e) {
            $this->jsonError('Selfie upload failed: ' . $e->getMessage());
            return null;
        }

        // Latitude/longitude are optional but strongly encouraged; store null if absent.
        $lat = (isset($_POST['lat']) && is_numeric($_POST['lat'])) ? (float) $_POST['lat'] : null;
        $lng = (isset($_POST['lng']) && is_numeric($_POST['lng'])) ? (float) $_POST['lng'] : null;
        $acc = (isset($_POST['accuracy']) && is_numeric($_POST['accuracy'])) ? (int) $_POST['accuracy'] : null;

        return [
            'selfie_key' => $selfieKey,
            'lat'        => $lat,
            'lng'        => $lng,
            'accuracy'   => $acc,
            'device'     => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
            'ip'         => $_SERVER['REMOTE_ADDR'] ?? null,
        ];
    }
}
