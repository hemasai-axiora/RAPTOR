<?php
// Raptor CRM — Attendance model (Sprint 2)

class Attendance extends Model {

    /** Shift config read from settings with safe defaults. */
    public function getShiftConfig(): array {
        $this->query("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'attendance.%'");
        $rows = $this->resultSet();
        $cfg = [];
        foreach ($rows as $r) { $cfg[$r->setting_key] = $r->setting_value; }
        return [
            'shift_start'    => $cfg['attendance.shift_start']    ?? '09:30',
            'shift_end'      => $cfg['attendance.shift_end']      ?? '18:30',
            'grace_minutes'  => (int)($cfg['attendance.grace_minutes'] ?? 15),
            'halfday_minutes'=> (int)($cfg['attendance.halfday_minutes'] ?? 240),
            'policy_version' => $cfg['attendance.policy_version'] ?? 'v1',
        ];
    }

    // ---------------- Consent ----------------

    public function hasConsent($userId): bool {
        $this->query('SELECT consented FROM location_consents
                      WHERE user_id = :uid ORDER BY consented_at DESC LIMIT 1');
        $this->bind(':uid', (int) $userId);
        $row = $this->single();
        return $row && (int) $row->consented === 1;
    }

    public function saveConsent($userId, $ip, $policyVersion): bool {
        $this->query('INSERT INTO location_consents (user_id, consented, policy_version, ip)
                      VALUES (:uid, 1, :pv, :ip)');
        $this->bind(':uid', (int) $userId);
        $this->bind(':pv', $policyVersion);
        $this->bind(':ip', $ip);
        return $this->execute();
    }

    // ---------------- Today's record ----------------

    public function getToday($userId, $date = null) {
        $date = $date ?: date('Y-m-d');
        $this->query('SELECT * FROM attendance WHERE user_id = :uid AND work_date = :d');
        $this->bind(':uid', (int) $userId);
        $this->bind(':d', $date);
        return $this->single();
    }

    // ---------------- Check-in ----------------

    /**
     * @param array $d  ['selfie_key','lat','lng','accuracy','device','ip']
     * @return array    ['ok'=>bool, 'message'=>string, 'is_late'=>bool]
     */
    public function checkIn($userId, array $d): array {
        $existing = $this->getToday($userId);
        if ($existing && $existing->login_at) {
            return ['ok' => false, 'message' => 'You have already checked in today.'];
        }

        $cfg      = $this->getShiftConfig();
        $date     = date('Y-m-d');
        $now      = date('Y-m-d H:i:s');
        $localNow = formatToLocalTime($now, 'Y-m-d H:i:s');
        $localTime = date('H:i:s', strtotime($localNow));
        $latestOk = date('H:i:s', strtotime($cfg['shift_start']) + $cfg['grace_minutes'] * 60);
        $isLate   = $localTime > $latestOk;

        // Geofence: null = not evaluated, 1 = inside a fence, 0 = outside all fences.
        $geoOk = $this->evalGeofence($d['lat'], $d['lng']);

        // All roles except Admin default to Pending Approval. Admin is automatically Approved.
        $role = $_SESSION['user_role'] ?? '';
        $approval = ($role === 'admin') ? 'Approved' : 'Pending';

        $this->query('INSERT INTO attendance
            (user_id, work_date, login_at, login_lat, login_lng, login_accuracy_m,
             login_selfie_url, login_device, login_ip, is_late, geofence_ok, status, 
             attendance_status, requested_at, approved_at, approved_by)
            VALUES
            (:uid, :d, :now, :lat, :lng, :acc, :selfie, :device, :ip, :late, :geo, \'present\', 
             :appr, :req_at, :appr_at, :appr_by)');
        $this->bind(':uid', (int) $userId);
        $this->bind(':d', $date);
        $this->bind(':now', $now);
        $this->bind(':lat', $d['lat'] !== null ? $d['lat'] : null);
        $this->bind(':lng', $d['lng'] !== null ? $d['lng'] : null);
        $this->bind(':acc', $d['accuracy'] !== null ? (int) $d['accuracy'] : null);
        $this->bind(':selfie', $d['selfie_key']);
        $this->bind(':device', $d['device']);
        $this->bind(':ip', $d['ip']);
        $this->bind(':late', $isLate ? 1 : 0, PDO::PARAM_INT);
        $this->bind(':geo', $geoOk === null ? null : $geoOk, $geoOk === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $this->bind(':appr', $approval);
        $this->bind(':req_at', $now);
        $this->bind(':appr_at', ($role === 'admin') ? $now : null);
        $this->bind(':appr_by', ($role === 'admin') ? $userId : null, ($role === 'admin') ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $ok = $this->execute();

        if ($ok && $approval === 'Pending') {
            // Find approver(s) based on hierarchy
            $approvers = [];
            $db = Database::getInstance()->getConnection();
            $employeeName = $_SESSION['user_name'] ?? 'Employee';

            if (in_array($role, ['employee', 'sales_person'], true)) {
                // Employee -> Manager
                $stmt = $db->prepare('SELECT reporting_manager_id FROM employees WHERE user_id = :uid');
                $stmt->execute([':uid' => $userId]);
                $mgrId = $stmt->fetchColumn();
                if ($mgrId) {
                    $approvers[] = (int) $mgrId;
                } else {
                    $stmtMgrs = $db->query("SELECT u.user_id FROM users u JOIN roles r ON u.role_id = r.role_id WHERE r.role_name = 'manager'");
                    $approvers = array_map('intval', $stmtMgrs->fetchAll(PDO::FETCH_COLUMN)) ?: [];
                }
            } elseif (in_array($role, ['manager', 'team_leader', 'finance', 'analyst'], true)) {
                // Manager/Finance/Analyst -> HR
                $stmtHrs = $db->query("SELECT u.user_id FROM users u JOIN roles r ON u.role_id = r.role_id WHERE r.role_name = 'hr'");
                $approvers = array_map('intval', $stmtHrs->fetchAll(PDO::FETCH_COLUMN)) ?: [];
            } elseif ($role === 'hr') {
                // HR -> Admin
                $stmtAdmins = $db->query("SELECT u.user_id FROM users u JOIN roles r ON u.role_id = r.role_id WHERE r.role_name = 'admin'");
                $approvers = array_map('intval', $stmtAdmins->fetchAll(PDO::FETCH_COLUMN)) ?: [];
            }

            // Send in-app notification to all approvers
            if (!empty($approvers)) {
                $notifModel = new Notification();
                foreach ($approvers as $apprId) {
                    $notifModel->addNotification(
                        $apprId,
                        'Attendance Approval Required',
                        "$employeeName has clocked in and requires your attendance approval.",
                        'attendance_request',
                        'index.php?route=attendance/approvals',
                        'warning',
                        'attendance'
                    );
                }
            }
        }

        $reasons = [];
        if ($isLate)        { $reasons[] = 'LATE'; }
        if ($geoOk === 0)   { $reasons[] = 'OUTSIDE geofence'; }

        return [
            'ok'         => $ok,
            'is_late'    => $isLate,
            'geofence_ok'=> $geoOk,
            'message'    => $ok
                ? ($approval === 'Pending' ? 'Checked in successfully — pending approval.' : 'Checked in successfully.')
                : 'Check-in failed.',
        ];
    }

    /**
     * Evaluate a coordinate against active 'office' geofences.
     * @return int|null  1 inside any fence, 0 outside all, null if not evaluated
     *                   (geofencing disabled, no coords, or no fences defined).
     */
    private function evalGeofence($lat, $lng): ?int {
        $this->query("SELECT setting_value FROM settings WHERE setting_key = 'attendance.geofence_enabled'");
        $row = $this->single();
        $geofenceEnabled = ($row && (string) $row->setting_value === '1');

        if (!$geofenceEnabled) { return null; }

        if ($lat === null || $lng === null || $lat === '' || $lng === '') { return 0; }

        $this->query("SELECT center_lat, center_lng, radius_m FROM geofences WHERE type = 'office' AND active = 1");
        $fences = $this->resultSet();
        if (empty($fences)) { return null; }

        foreach ($fences as $f) {
            $dist = $this->haversineMeters((float) $lat, (float) $lng, (float) $f->center_lat, (float) $f->center_lng);
            if ($dist <= (float) $f->radius_m) { return 1; }
        }
        return 0;
    }

    /** Great-circle distance in metres. */
    private function haversineMeters(float $lat1, float $lng1, float $lat2, float $lng2): float {
        $R = 6371000.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    // ---------------- Check-out ----------------

    public function checkOut($userId, array $d): array {
        $rec = $this->getToday($userId);
        if (!$rec || !$rec->login_at) {
            return ['ok' => false, 'message' => 'You have not checked in today.'];
        }
        if ($rec->logout_at) {
            return ['ok' => false, 'message' => 'You have already checked out today.'];
        }

        // Close any break still open, then re-read the updated break total.
        $this->autoCloseBreak((int) $rec->attendance_id);
        $rec = $this->getToday($userId);

        $cfg   = $this->getShiftConfig();
        $now   = date('Y-m-d H:i:s');
        $date  = $rec->work_date;

        $worked = (int) round((time() - strtotime($rec->login_at)) / 60) - (int) $rec->break_minutes;
        if ($worked < 0) { $worked = 0; }

        $localNow = formatToLocalTime($now, 'Y-m-d H:i:s');
        $localTime = date('H:i:s', strtotime($localNow));
        $isEarly = $localTime < $cfg['shift_end'];
        $status  = ($worked < $cfg['halfday_minutes']) ? 'half_day' : 'present';

        $this->query('UPDATE attendance SET
                        logout_at = :now, logout_lat = :lat, logout_lng = :lng,
                        logout_accuracy_m = :acc, logout_selfie_url = :selfie,
                        logout_device = :device, logout_ip = :ip,
                        worked_minutes = :worked, is_early_logout = :early, status = :status
                      WHERE attendance_id = :aid');
        $this->bind(':now', $now);
        $this->bind(':lat', $d['lat'] !== null ? $d['lat'] : null);
        $this->bind(':lng', $d['lng'] !== null ? $d['lng'] : null);
        $this->bind(':acc', $d['accuracy'] !== null ? (int) $d['accuracy'] : null);
        $this->bind(':selfie', $d['selfie_key']);
        $this->bind(':device', $d['device']);
        $this->bind(':ip', $d['ip']);
        $this->bind(':worked', $worked, PDO::PARAM_INT);
        $this->bind(':early', $isEarly ? 1 : 0, PDO::PARAM_INT);
        $this->bind(':status', $status);
        $this->bind(':aid', (int) $rec->attendance_id);
        $ok = $this->execute();

        return [
            'ok'             => $ok,
            'worked_minutes' => $worked,
            'is_early'       => $isEarly,
            'message'        => $ok ? 'Checked out. Worked ' . floor($worked / 60) . 'h ' . ($worked % 60) . 'm.' : 'Check-out failed.',
        ];
    }

    // ---------------- Breaks ----------------

    public function getOpenBreak($attendanceId) {
        $this->query('SELECT * FROM breaks WHERE attendance_id = :aid AND end_at IS NULL ORDER BY start_at DESC LIMIT 1');
        $this->bind(':aid', (int) $attendanceId);
        return $this->single();
    }

    public function startBreak($userId, $reason = null): array {
        $rec = $this->getToday($userId);
        if (!$rec || !$rec->login_at) { return ['ok' => false, 'message' => 'Check in first.']; }
        if ($rec->logout_at)          { return ['ok' => false, 'message' => 'You have already checked out.']; }
        if ($this->getOpenBreak($rec->attendance_id)) { return ['ok' => false, 'message' => 'A break is already running.']; }

        $this->query('INSERT INTO breaks (attendance_id, start_at, reason) VALUES (:aid, :now, :reason)');
        $this->bind(':aid', (int) $rec->attendance_id);
        $this->bind(':now', date('Y-m-d H:i:s'));
        $this->bind(':reason', $reason);
        return ['ok' => $this->execute(), 'message' => 'Break started.'];
    }

    public function endBreak($userId): array {
        $rec = $this->getToday($userId);
        if (!$rec) { return ['ok' => false, 'message' => 'No attendance today.']; }
        $open = $this->getOpenBreak($rec->attendance_id);
        if (!$open) { return ['ok' => false, 'message' => 'No break is running.']; }

        $minutes = max(0, (int) round((time() - strtotime($open->start_at)) / 60));
        $this->query('UPDATE breaks SET end_at = :now, minutes = :m WHERE break_id = :bid');
        $this->bind(':now', date('Y-m-d H:i:s'));
        $this->bind(':m', $minutes, PDO::PARAM_INT);
        $this->bind(':bid', (int) $open->break_id);
        $this->execute();

        // Roll the total break minutes onto the attendance row.
        $this->query('UPDATE attendance SET break_minutes = break_minutes + :m WHERE attendance_id = :aid');
        $this->bind(':m', $minutes, PDO::PARAM_INT);
        $this->bind(':aid', (int) $rec->attendance_id);
        $this->execute();

        return ['ok' => true, 'message' => 'Break ended (' . $minutes . ' min).', 'minutes' => $minutes];
    }

    /** Close an open break silently (used at checkout). */
    private function autoCloseBreak($attendanceId): void {
        $open = $this->getOpenBreak($attendanceId);
        if (!$open) { return; }
        $minutes = max(0, (int) round((time() - strtotime($open->start_at)) / 60));
        $this->query('UPDATE breaks SET end_at = :now, minutes = :m WHERE break_id = :bid');
        $this->bind(':now', date('Y-m-d H:i:s'));
        $this->bind(':m', $minutes, PDO::PARAM_INT);
        $this->bind(':bid', (int) $open->break_id);
        $this->execute();
        $this->query('UPDATE attendance SET break_minutes = break_minutes + :m WHERE attendance_id = :aid');
        $this->bind(':m', $minutes, PDO::PARAM_INT);
        $this->bind(':aid', (int) $attendanceId);
        $this->execute();
    }

    // ---------------- Approvals ----------------

    /**
     * Pending exceptions scoped to a set of user IDs (null = all, for admin).
     */
    public function getPendingApprovals($userIds) {
        $role = $_SESSION['user_role'] ?? '';
        $uid = (int) ($_SESSION['user_id'] ?? 0);

        // Build base query
        $sql = 'SELECT a.*, u.name AS employee_name, r.role_name
                FROM attendance a 
                JOIN users u ON a.user_id = u.user_id
                JOIN roles r ON u.role_id = r.role_id
                WHERE a.attendance_status = \'Pending\'';

        // Filter based on the approver role and specific workflow rules
        if ($role === 'manager' || $role === 'team_leader') {
            // Manager: sees only employee / sales_person requests for their direct reports
            $sql .= " AND r.role_name IN ('employee', 'sales_person') AND a.user_id IN (
                SELECT user_id FROM employees WHERE reporting_manager_id = $uid
            )";
        } elseif ($role === 'hr') {
            // HR: sees only manager, team_leader, finance, analyst requests
            $sql .= " AND r.role_name IN ('manager', 'team_leader', 'finance', 'analyst')";
        } elseif ($role === 'admin') {
            // Admin: sees only HR requests
            $sql .= " AND r.role_name = 'hr'";
        } else {
            // Other roles cannot approve
            return [];
        }

        $sql .= ' ORDER BY a.work_date DESC, a.login_at DESC';
        $this->query($sql);
        return $this->resultSet();
    }

    public function getById($attendanceId) {
        $this->query('SELECT * FROM attendance WHERE attendance_id = :id');
        $this->bind(':id', (int) $attendanceId);
        return $this->single();
    }

    public function setApproval($attendanceId, $decision, $approverId, $remark = null): bool {
        // decision: 'Approved' | 'Rejected'
        $this->query('UPDATE attendance SET 
                        attendance_status = :st, 
                        approved_by = :by, 
                        approved_at = NOW(), 
                        rejection_reason = :rm,
                        remarks = :rm2
                      WHERE attendance_id = :id AND attendance_status = \'Pending\'');
        $this->bind(':st', $decision);
        $this->bind(':by', (int) $approverId);
        $this->bind(':rm', $remark);
        $this->bind(':rm2', $remark);
        $this->bind(':id', (int) $attendanceId);
        $this->execute();
        $ok = $this->rowCount() > 0;

        if ($ok) {
            // Send in-app notification to the employee who requested it
            $rec = $this->getById($attendanceId);
            if ($rec) {
                $statusText = ($decision === 'Approved') ? 'approved' : 'rejected';
                $severity = ($decision === 'Approved') ? 'info' : 'critical';
                
                $notifModel = new Notification();
                $notifModel->addNotification(
                    (int) $rec->user_id,
                    'Attendance Request Updated',
                    "Your attendance request for " . date('M d, Y', strtotime($rec->work_date)) . " has been $statusText.",
                    'attendance_update',
                    'index.php?route=attendance/index',
                    $severity,
                    'attendance'
                );
            }
        }
        return $ok;
    }

    // ---------------- Report ----------------

    /**
     * @param array $f  ['from','to','user_ids'(array|null),'user_id'(optional)]
     */
    public function getReport(array $f) {
        $sql = 'SELECT a.*, u.name AS employee_name, t.name AS team_name
                FROM attendance a
                JOIN users u ON a.user_id = u.user_id
                LEFT JOIN employees e ON u.user_id = e.user_id
                LEFT JOIN teams t ON e.team_id = t.team_id
                WHERE a.work_date BETWEEN :from AND :to';
        $params = [':from' => $f['from'], ':to' => $f['to']];

        if (is_array($f['user_ids'])) {
            if (empty($f['user_ids'])) { return []; }
            $in = implode(',', array_map('intval', $f['user_ids']));
            $sql .= " AND a.user_id IN ($in)";
        }
        if (!empty($f['user_id'])) {
            $sql .= ' AND a.user_id = :uid';
            $params[':uid'] = (int) $f['user_id'];
        }
        $sql .= ' ORDER BY a.work_date DESC, employee_name ASC';

        $this->query($sql);
        foreach ($params as $k => $v) { $this->bind($k, $v); }
        return $this->resultSet();
    }
}
