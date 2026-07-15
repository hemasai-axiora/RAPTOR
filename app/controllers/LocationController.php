<?php
/**
 * Raptor CRM — Location tracking (Sprint 4)
 * Foreground GPS pings (stored only while on duty), plus route map views for
 * the employee (self) and oversight roles (scoped to their team subtree).
 */

class LocationController extends Controller {
    private $loc;

    public function __construct() {
        $this->requireAuth();
        $this->loc = $this->model('LocationLog');
    }

    /**
     * Batch location ping (JSON). Accepts {points:[{lat,lng,accuracy,ts,source}]}.
     * Returns on_duty so the client can stop tracking when the shift ends.
     */
    public function ping() {
        $this->requireAuthApi();
        $body = $this->jsonInput();
        $points = (isset($body['points']) && is_array($body['points'])) ? $body['points'] : [];
        $res = $this->loc->ping((int) $_SESSION['user_id'], $points);
        $this->jsonOk(['on_duty' => $res['on_duty'], 'stored' => $res['stored']]);
    }

    /** Sales person's own route for a date (default today). */
    public function myday() {
        $date = $this->safeDate($_GET['date'] ?? null);
        $this->renderMap((int) $_SESSION['user_id'], $_SESSION['user_name'], $date, true);
    }

    /** Oversight: view a team member's route (scoped). */
    public function member($userId = 0) {
        if (!in_array($_SESSION['user_role'], ['admin', 'manager', 'team_leader'])) {
            $this->redirect('index.php?route=location/myday');
        }
        $userId = (int) $userId;
        $scope = $this->visibleUserIds();
        if ($scope !== null && !in_array($userId, $scope, true)) {
            $this->viewWithLayout('errors/403', 'main', [
                'title' => 'Access Denied',
                'message' => 'This employee is not in your team scope.'
            ]);
            return;
        }
        $name = $this->lookupName($userId);
        $date = $this->safeDate($_GET['date'] ?? null);
        $this->renderMap($userId, $name, $date, false);
    }

    // ---------------- helpers ----------------

    private function renderMap($userId, $name, $date, $isSelf) {
        $points = $this->loc->getDayPoints($userId, $date);
        $data = [
            'title'      => ($isSelf ? 'My Route' : 'Route: ' . $name) . ' | Raptor CRM',
            'active_tab' => $isSelf ? 'myroute' : 'monitoring',
            'is_self'    => $isSelf,
            'emp_name'   => $name,
            'user_id'    => $userId,
            'date'       => $date,
            'points'     => $points,
            'distance'   => $this->loc->distanceKm($points),
            'pins'       => $this->loc->getAttendancePins($userId, $date),
        ];
        $this->viewWithLayout('location/map', 'main', $data);
    }

    private function safeDate($d): string {
        return ($d && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) ? $d : date('Y-m-d');
    }

    private function lookupName($userId): string {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare('SELECT name FROM users WHERE user_id = :id');
        $stmt->execute([':id' => (int) $userId]);
        $row = $stmt->fetch(PDO::FETCH_OBJ);
        return $row ? $row->name : 'Employee';
    }
}
