<?php
// Raptor CRM Manager Monitoring Dashboard Model

class Monitoring extends Model {
    public function employees(?array $visibleUserIds = null): array {
        [$where, $params] = $this->userScopeWhere($visibleUserIds, 'u.user_id');
        $this->query('SELECT u.user_id, u.name, u.email, r.role_name, t.name AS team_name
                      FROM users u
                      JOIN roles r ON u.role_id = r.role_id
                      LEFT JOIN employees e ON u.user_id = e.user_id
                      LEFT JOIN teams t ON e.team_id = t.team_id
                      WHERE u.status = "active"
                        AND r.role_name IN ("employee","sales_person","team_leader")
                        ' . ($where ? 'AND ' . implode(' AND ', $where) : '') . '
                      ORDER BY t.name ASC, u.name ASC');
        $this->bindParams($params);
        return $this->resultSet();
    }

    public function liveBoard(?array $visibleUserIds = null): array {
        $employees = $this->employees($visibleUserIds);
        if (!$employees) {
            return [];
        }
        $ids = array_map(fn($e) => (int) $e->user_id, $employees);
        $att = $this->todayAttendance($ids);
        $loc = $this->latestLocations($ids);

        $rows = [];
        foreach ($employees as $emp) {
            $uid = (int) $emp->user_id;
            $a = $att[$uid] ?? null;
            $l = $loc[$uid] ?? null;
            $state = 'no_login';
            if ($a && !empty($a->login_at) && empty($a->logout_at)) {
                $state = 'working';
            } elseif ($a && !empty($a->logout_at)) {
                $state = 'checked_out';
            }
            if ($a && (int) ($a->is_late ?? 0) === 1 && $state === 'working') {
                $state = 'late';
            }

            $locationOff = !$l || strtotime($l->captured_at) < strtotime('-30 minutes');
            $rows[] = (object) [
                'user_id' => $uid,
                'name' => $emp->name,
                'email' => $emp->email,
                'team_name' => $emp->team_name,
                'state' => $state,
                'login_at' => $a->login_at ?? null,
                'logout_at' => $a->logout_at ?? null,
                'worked_minutes' => (int) ($a->worked_minutes ?? 0),
                'is_late' => (int) ($a->is_late ?? 0),
                'location_off' => $locationOff,
                'lat' => $l->lat ?? null,
                'lng' => $l->lng ?? null,
                'last_location_at' => $l->captured_at ?? null,
            ];
        }
        return $rows;
    }

    public function todayRollup(?array $visibleUserIds = null): array {
        $ids = $this->scopedIds($visibleUserIds);
        if ($ids !== null && !$ids) {
            return $this->emptyRollup();
        }
        $userSql = $ids === null ? '1=1' : 'user_id IN (' . implode(',', array_map('intval', $ids)) . ')';
        $assignedSql = $ids === null ? '1=1' : 'assigned_to_user_id IN (' . implode(',', array_map('intval', $ids)) . ')';
        $todayStart = date('Y-m-d 00:00:00');
        $todayEnd = date('Y-m-d 23:59:59');
        $today = date('Y-m-d');

        return [
            'attendance' => [
                'working' => $this->countSql('SELECT COUNT(*) FROM attendance WHERE ' . $userSql . ' AND work_date = CURDATE() AND login_at IS NOT NULL AND logout_at IS NULL'),
                'late' => $this->countSql('SELECT COUNT(*) FROM attendance WHERE ' . $userSql . ' AND work_date = CURDATE() AND is_late = 1'),
                'no_login' => max(0, count($this->employees($visibleUserIds)) - $this->countSql('SELECT COUNT(DISTINCT user_id) FROM attendance WHERE ' . $userSql . ' AND work_date = CURDATE() AND login_at IS NOT NULL')),
            ],
            'targets' => $this->targetRollup($visibleUserIds),
            'tasks' => [
                'pending' => $this->countSql('SELECT COUNT(*) FROM tasks WHERE ' . $assignedSql . ' AND status = "pending"'),
                'in_progress' => $this->countSql('SELECT COUNT(*) FROM tasks WHERE ' . $assignedSql . ' AND status = "in_progress"'),
                'completed_today' => $this->countSql('SELECT COUNT(*) FROM tasks WHERE ' . $assignedSql . ' AND status = "completed" AND completed_at BETWEEN "' . $todayStart . '" AND "' . $todayEnd . '"'),
                'pending_review' => $this->countSql('SELECT COUNT(*) FROM tasks WHERE ' . $assignedSql . ' AND review_status = "pending_review"'),
            ],
            'activity' => [
                'communications' => $this->countSql('SELECT COUNT(*) FROM communications WHERE ' . $userSql . ' AND happened_at BETWEEN "' . $todayStart . '" AND "' . $todayEnd . '"'),
                'meetings' => $this->countSql('SELECT COUNT(*) FROM meetings WHERE ' . $assignedSql . ' AND type = "meeting" AND scheduled_start BETWEEN "' . $todayStart . '" AND "' . $todayEnd . '"'),
                'demos' => $this->countSql('SELECT COUNT(*) FROM meetings WHERE ' . $assignedSql . ' AND type = "demo" AND scheduled_start BETWEEN "' . $todayStart . '" AND "' . $todayEnd . '"'),
            ],
            'leads' => [
                'generated' => $this->countSql('SELECT COUNT(*) FROM leads WHERE ' . $assignedSql . ' AND created_at BETWEEN "' . $todayStart . '" AND "' . $todayEnd . '"'),
                'converted' => $this->countSql('SELECT COUNT(*) FROM leads WHERE ' . $assignedSql . ' AND status = "converted" AND COALESCE(converted_at, updated_at) BETWEEN "' . $todayStart . '" AND "' . $todayEnd . '"'),
                'followed' => $this->countSql('SELECT COUNT(DISTINCT lead_id) FROM follow_ups WHERE ' . $assignedSql . ' AND status = "completed" AND completed_at BETWEEN "' . $todayStart . '" AND "' . $todayEnd . '"'),
            ],
            'followups' => [
                'pending' => $this->countSql('SELECT COUNT(*) FROM follow_ups WHERE ' . $assignedSql . ' AND status = "scheduled" AND DATE(due_at) = "' . $today . '"'),
                'missed' => $this->countSql('SELECT COUNT(*) FROM follow_ups WHERE ' . $assignedSql . ' AND status = "missed" AND DATE(due_at) = "' . $today . '"'),
            ],
        ];
    }

    public function pipelineForecast(?array $visibleUserIds = null): array {
        $ids = $this->scopedIds($visibleUserIds);
        if ($ids !== null && !$ids) {
            return ['by_status' => [], 'forecast' => 0.0];
        }
        $where = $ids === null ? '1=1' : 'assigned_to_user_id IN (' . implode(',', array_map('intval', $ids)) . ')';
        $this->query('SELECT status, COUNT(*) AS count, COALESCE(SUM(lead_value),0) AS value_sum,
                             COALESCE(SUM(lead_value * COALESCE(probability, conversion_probability, 0) / 100),0) AS forecast_sum
                      FROM leads
                      WHERE ' . $where . '
                        AND status NOT IN ("converted","lost")
                      GROUP BY status
                      ORDER BY FIELD(status,"new","contacted","qualified","proposal")');
        $rows = $this->resultSet();
        $forecast = 0.0;
        foreach ($rows as $row) {
            $forecast += (float) $row->forecast_sum;
        }
        return ['by_status' => $rows, 'forecast' => $forecast];
    }

    public function employeeDay(int $userId, string $date): array {
        $start = parseLocalToUtc($date . ' 00:00:00');
        $end = parseLocalToUtc($date . ' 23:59:59');
        return [
            'employee' => $this->employee($userId),
            'attendance' => $this->singleSql('SELECT * FROM attendance WHERE user_id = :uid AND work_date = :d', [':uid' => $userId, ':d' => $date]),
            'locations' => $this->rowsSql('SELECT * FROM location_logs WHERE user_id = :uid AND captured_at BETWEEN :start AND :end ORDER BY captured_at DESC LIMIT 20', [':uid' => $userId, ':start' => $start, ':end' => $end]),
            'tasks' => $this->rowsSql('SELECT * FROM tasks WHERE assigned_to_user_id = :uid AND ((deadline BETWEEN :start AND :end) OR (completed_at BETWEEN :start AND :end)) ORDER BY deadline ASC', [':uid' => $userId, ':start' => $start, ':end' => $end]),
            'communications' => $this->rowsSql('SELECT c.*, l.first_name, l.last_name FROM communications c LEFT JOIN leads l ON c.lead_id = l.lead_id WHERE c.user_id = :uid AND c.happened_at BETWEEN :start AND :end ORDER BY c.happened_at DESC', [':uid' => $userId, ':start' => $start, ':end' => $end]),
            'meetings' => $this->rowsSql('SELECT m.*, l.first_name, l.last_name FROM meetings m LEFT JOIN leads l ON m.lead_id = l.lead_id WHERE m.assigned_to_user_id = :uid AND m.scheduled_start BETWEEN :start AND :end ORDER BY m.scheduled_start ASC', [':uid' => $userId, ':start' => $start, ':end' => $end]),
            'leads' => $this->rowsSql('SELECT * FROM leads WHERE assigned_to_user_id = :uid AND created_at BETWEEN :start AND :end ORDER BY created_at DESC', [':uid' => $userId, ':start' => $start, ':end' => $end]),
            'followups' => $this->rowsSql('SELECT f.*, l.first_name, l.last_name FROM follow_ups f JOIN leads l ON f.lead_id = l.lead_id WHERE f.assigned_to_user_id = :uid AND f.due_at BETWEEN :start AND :end ORDER BY f.due_at ASC', [':uid' => $userId, ':start' => $start, ':end' => $end]),
        ];
    }

    private function employee(int $userId) {
        return $this->singleSql('SELECT u.*, t.name AS team_name FROM users u LEFT JOIN employees e ON u.user_id = e.user_id LEFT JOIN teams t ON e.team_id = t.team_id WHERE u.user_id = :uid', [':uid' => $userId]);
    }

    private function todayAttendance(array $ids): array {
        $rows = $this->rowsSql('SELECT * FROM attendance WHERE user_id IN (' . implode(',', array_map('intval', $ids)) . ') AND work_date = CURDATE()', []);
        $out = [];
        foreach ($rows as $row) { $out[(int) $row->user_id] = $row; }
        return $out;
    }

    private function latestLocations(array $ids): array {
        $rows = $this->rowsSql('SELECT ll.*
                                FROM location_logs ll
                                JOIN (
                                    SELECT user_id, MAX(captured_at) AS max_at
                                    FROM location_logs
                                    WHERE user_id IN (' . implode(',', array_map('intval', $ids)) . ')
                                    GROUP BY user_id
                                ) x ON ll.user_id = x.user_id AND ll.captured_at = x.max_at', []);
        $out = [];
        foreach ($rows as $row) { $out[(int) $row->user_id] = $row; }
        return $out;
    }

    private function targetRollup(?array $visibleUserIds): array {
        $ids = $this->scopedIds($visibleUserIds);
        if ($ids !== null && !$ids) {
            return ['planned' => 0, 'achieved' => 0, 'completion' => 0];
        }
        if ($ids === null) {
            $scope = '1=1';
        } else {
            $idList = implode(',', array_map('intval', $ids));
            $scope = '(t.owner_user_id IN (' . $idList . ')
                       OR (
                           t.owner_type = "team"
                           AND EXISTS (
                               SELECT 1 FROM teams ts
                               LEFT JOIN employees e ON e.team_id = ts.team_id
                               WHERE ts.team_id = t.team_id
                                 AND (
                                     ts.team_leader_user_id IN (' . $idList . ')
                                     OR ts.manager_user_id IN (' . $idList . ')
                                     OR e.user_id IN (' . $idList . ')
                                 )
                           )
                       ))';
        }
        $row = $this->singleSql('SELECT COALESCE(SUM(ti.planned_value),0) AS planned,
                                        COALESCE(SUM(tp.achieved_value),0) AS achieved,
                                        COALESCE(AVG(tp.completion_percent),0) AS completion
                                 FROM targets t
                                 JOIN target_items ti ON t.target_id = ti.target_id
                                 LEFT JOIN target_progress tp ON ti.target_item_id = tp.target_item_id
                                 WHERE t.status = "approved"
                                   AND CURDATE() BETWEEN t.start_date AND t.end_date
                                   AND ' . $scope, []);
        return [
            'planned' => (float) ($row->planned ?? 0),
            'achieved' => (float) ($row->achieved ?? 0),
            'completion' => (float) ($row->completion ?? 0),
        ];
    }

    private function scopedIds(?array $visibleUserIds): ?array {
        return $visibleUserIds === null ? null : array_values(array_unique(array_map('intval', $visibleUserIds)));
    }

    private function userScopeWhere(?array $visibleUserIds, string $column): array {
        if ($visibleUserIds === null) { return [[], []]; }
        if (!$visibleUserIds) { return [['1 = 0'], []]; }
        return [[$column . ' IN (' . implode(',', array_map('intval', $visibleUserIds)) . ')'], []];
    }

    private function countSql(string $sql): int {
        $this->query($sql);
        $this->execute();
        return (int) $this->stmt->fetchColumn();
    }

    private function rowsSql(string $sql, array $params): array {
        $this->query($sql);
        $this->bindParams($params);
        return $this->resultSet();
    }

    private function singleSql(string $sql, array $params) {
        $this->query($sql);
        $this->bindParams($params);
        return $this->single();
    }

    private function bindParams(array $params): void {
        foreach ($params as $key => $value) {
            $this->bind($key, $value);
        }
    }

    private function emptyRollup(): array {
        return [
            'attendance' => ['working' => 0, 'late' => 0, 'no_login' => 0],
            'targets' => ['planned' => 0, 'achieved' => 0, 'completion' => 0],
            'tasks' => ['pending' => 0, 'in_progress' => 0, 'completed_today' => 0, 'pending_review' => 0],
            'activity' => ['communications' => 0, 'meetings' => 0, 'demos' => 0],
            'leads' => ['generated' => 0, 'followed' => 0, 'converted' => 0],
            'followups' => ['pending' => 0, 'missed' => 0],
        ];
    }
}
