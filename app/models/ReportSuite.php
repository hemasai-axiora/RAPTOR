<?php
// Sprint 12 - Shared report framework and exports.

class ReportSuite extends Model {
    public const REPORTS = [
        'attendance' => 'Attendance',
        'login_logout' => 'Login / Logout',
        'location_travel' => 'Location Travel',
        'distance' => 'Distance',
        'task_completion' => 'Task Completion',
        'target_progress' => 'Target Planned vs Achieved',
        'lead_generation' => 'Lead Generation',
        'lead_status' => 'Lead Status',
        'lead_conversion' => 'Lead Conversion',
        'follow_up' => 'Follow-up',
        'missed_follow_up' => 'Missed Follow-up',
        'communication_activity' => 'Call / Email / Message Activity',
        'meeting' => 'Meeting',
        'demo' => 'Demo',
        'revenue' => 'Revenue',
        'performance' => 'Employee / Manager / Team Performance',
        'daily_summary' => 'Daily Summary',
        'monthly_summary' => 'Monthly Summary',
    ];

    public function definitions(): array {
        return self::REPORTS;
    }

    public function run(string $key, array $filters, ?array $visibleUserIds = null): array {
        if (!isset(self::REPORTS[$key])) {
            $key = 'daily_summary';
        }

        $filters = $this->normalizeFilters($filters);
        [$userSql, $params] = $this->userScopeSql($filters, $visibleUserIds);

        switch ($key) {
            case 'attendance':
                return $this->simpleReport($key, $filters, [
                    'Date' => 'work_date', 'Employee' => 'employee_name', 'Team' => 'team_name',
                    'Status' => 'status', 'Login' => 'login_at', 'Logout' => 'logout_at',
                    'Worked Min' => 'worked_minutes', 'Break Min' => 'break_minutes',
                    'Late' => 'is_late', 'Early Logout' => 'is_early_logout',
                    'Approval' => 'attendance_status',
                ], "SELECT a.work_date, u.name AS employee_name, COALESCE(t.name,'-') AS team_name,
                           a.status, a.login_at, a.logout_at, a.worked_minutes, a.break_minutes,
                           IF(a.is_late,'Yes','No') AS is_late, IF(a.is_early_logout,'Yes','No') AS is_early_logout,
                           a.attendance_status
                    FROM attendance a
                    JOIN users u ON a.user_id = u.user_id
                    LEFT JOIN employees e ON u.user_id = e.user_id
                    LEFT JOIN teams t ON e.team_id = t.team_id
                    WHERE a.work_date BETWEEN :from AND :to $userSql
                    ORDER BY a.work_date DESC, u.name ASC", $params);

            case 'login_logout':
                return $this->simpleReport($key, $filters, [
                    'Date' => 'work_date', 'Employee' => 'employee_name', 'Login' => 'login_at',
                    'Login IP' => 'login_ip', 'Logout' => 'logout_at', 'Logout IP' => 'logout_ip',
                    'Integrity' => 'integrity_flag',
                ], "SELECT a.work_date, u.name AS employee_name, a.login_at, a.login_ip,
                           a.logout_at, a.logout_ip, a.integrity_flag
                    FROM attendance a JOIN users u ON a.user_id = u.user_id
                    LEFT JOIN employees e ON u.user_id = e.user_id
                    WHERE a.work_date BETWEEN :from AND :to $userSql
                    ORDER BY a.work_date DESC, u.name ASC", $params);

            case 'location_travel':
            case 'distance':
                return $this->simpleReport($key, $filters, [
                    'Date' => 'work_date', 'Employee' => 'employee_name', 'Distance KM' => 'distance_km',
                    'Points' => 'points_count', 'First Point' => 'first_at', 'Last Point' => 'last_at',
                ], "SELECT ts.work_date, u.name AS employee_name, ts.distance_km, ts.points_count,
                           ts.first_at, ts.last_at
                    FROM travel_summary ts JOIN users u ON ts.user_id = u.user_id
                    LEFT JOIN employees e ON u.user_id = e.user_id
                    WHERE ts.work_date BETWEEN :from AND :to $userSql
                    ORDER BY ts.work_date DESC, u.name ASC", $params);

            case 'task_completion':
                return $this->simpleReport($key, $filters, [
                    'Employee' => 'employee_name', 'Task' => 'title', 'Priority' => 'priority',
                    'Deadline' => 'deadline', 'Status' => 'status', 'Progress %' => 'progress_percent',
                    'Review' => 'review_status', 'Completed At' => 'completed_at',
                ], "SELECT u.name AS employee_name, t.title, t.priority, t.deadline, t.status,
                           t.progress_percent, t.review_status, t.completed_at
                    FROM tasks t JOIN users u ON t.assigned_to_user_id = u.user_id
                    LEFT JOIN employees e ON u.user_id = e.user_id
                    WHERE DATE(t.deadline) BETWEEN :from AND :to $userSql
                    ORDER BY t.deadline DESC", $params);

            case 'target_progress':
                [$targetUserSql, $targetParams] = $this->targetScopeSql($filters, $visibleUserIds);
                return $this->simpleReport($key, $filters, [
                    'Owner' => 'owner_name', 'Owner Type' => 'owner_type', 'Period' => 'period',
                    'Category' => 'category_name', 'Start' => 'start_date', 'End' => 'end_date',
                    'Planned' => 'planned_value', 'Achieved' => 'achieved_value',
                    'Completion %' => 'completion_percent', 'Status' => 'status',
                ], "SELECT COALESCE(u.name, tm.name, '-') AS owner_name, tg.owner_type, tg.period,
                           tc.name AS category_name, tg.start_date, tg.end_date, tg.status,
                           ti.planned_value, COALESCE(tp.achieved_value,0) AS achieved_value,
                           COALESCE(tp.completion_percent,0) AS completion_percent
                    FROM targets tg
                    JOIN target_items ti ON tg.target_id = ti.target_id
                    JOIN target_categories tc ON ti.category_id = tc.category_id
                    LEFT JOIN target_progress tp ON ti.target_item_id = tp.target_item_id
                    LEFT JOIN users u ON tg.owner_user_id = u.user_id
                    LEFT JOIN teams tm ON tg.team_id = tm.team_id
                    LEFT JOIN employees e ON u.user_id = e.user_id
                    WHERE tg.start_date <= :to AND tg.end_date >= :from
                      AND (:team_filter = 0 OR tg.team_id = :team_filter2 OR e.team_id = :team_filter3)
                      $targetUserSql
                    ORDER BY tg.start_date DESC, owner_name ASC", $targetParams);

            case 'lead_generation':
            case 'lead_status':
                return $this->simpleReport($key, $filters, [
                    'Created' => 'created_at', 'Lead' => 'lead_name', 'Company' => 'company_name',
                    'Owner' => 'employee_name', 'Source' => 'lead_source', 'Status' => 'status',
                    'Quality' => 'lead_quality', 'Value' => 'lead_value',
                ], "SELECT l.created_at, CONCAT(l.first_name, ' ', COALESCE(l.last_name,'')) AS lead_name,
                           l.company_name, COALESCE(u.name,'Unassigned') AS employee_name,
                           l.lead_source, l.status, l.lead_quality, l.lead_value
                    FROM leads l LEFT JOIN users u ON l.assigned_to_user_id = u.user_id
                    LEFT JOIN employees e ON u.user_id = e.user_id
                    WHERE DATE(l.created_at) BETWEEN :from AND :to $userSql
                    ORDER BY l.created_at DESC", $params);

            case 'lead_conversion':
            case 'revenue':
                return $this->simpleReport($key, $filters, [
                    'Converted' => 'converted_at', 'Lead' => 'lead_name', 'Owner' => 'employee_name',
                    'Source' => 'lead_source', 'Probability %' => 'probability', 'Revenue' => 'lead_value',
                ], "SELECT l.converted_at, CONCAT(l.first_name, ' ', COALESCE(l.last_name,'')) AS lead_name,
                           COALESCE(u.name,'Unassigned') AS employee_name, l.lead_source,
                           COALESCE(l.probability, l.conversion_probability, 0) AS probability, l.lead_value
                    FROM leads l LEFT JOIN users u ON l.assigned_to_user_id = u.user_id
                    LEFT JOIN employees e ON u.user_id = e.user_id
                    WHERE l.status = 'converted' AND DATE(COALESCE(l.converted_at, l.updated_at)) BETWEEN :from AND :to $userSql
                    ORDER BY COALESCE(l.converted_at, l.updated_at) DESC", $params);

            case 'follow_up':
            case 'missed_follow_up':
                $statusClause = $key === 'missed_follow_up' ? "AND f.status = 'missed'" : '';
                return $this->simpleReport($key, $filters, [
                    'Due' => 'due_at', 'Owner' => 'employee_name', 'Lead' => 'lead_name',
                    'Channel' => 'channel', 'Status' => 'status', 'Completed' => 'completed_at',
                    'Outcome' => 'outcome',
                ], "SELECT f.due_at, u.name AS employee_name,
                           CONCAT(l.first_name, ' ', COALESCE(l.last_name,'')) AS lead_name,
                           f.channel, f.status, f.completed_at, f.outcome
                    FROM follow_ups f
                    JOIN users u ON f.assigned_to_user_id = u.user_id
                    JOIN leads l ON f.lead_id = l.lead_id
                    LEFT JOIN employees e ON u.user_id = e.user_id
                    WHERE DATE(f.due_at) BETWEEN :from AND :to $statusClause $userSql
                    ORDER BY f.due_at DESC", $params);

            case 'communication_activity':
                return $this->simpleReport($key, $filters, [
                    'When' => 'happened_at', 'Employee' => 'employee_name', 'Channel' => 'channel',
                    'Direction' => 'direction', 'Duration Sec' => 'duration_seconds',
                    'Lead' => 'lead_name', 'Outcome' => 'outcome',
                ], "SELECT c.happened_at, u.name AS employee_name, c.channel, c.direction,
                           c.duration_seconds, CONCAT(l.first_name, ' ', COALESCE(l.last_name,'')) AS lead_name,
                           c.outcome
                    FROM communications c JOIN users u ON c.user_id = u.user_id
                    LEFT JOIN leads l ON c.lead_id = l.lead_id
                    LEFT JOIN employees e ON u.user_id = e.user_id
                    WHERE DATE(c.happened_at) BETWEEN :from AND :to $userSql
                    ORDER BY c.happened_at DESC", $params);

            case 'meeting':
            case 'demo':
                return $this->simpleReport($key, $filters, [
                    'Scheduled' => 'scheduled_start', 'Employee' => 'employee_name', 'Title' => 'title',
                    'Location' => 'location', 'Status' => 'status', 'Outcome' => 'outcome',
                    'Feedback' => 'client_feedback',
                ], "SELECT m.scheduled_start, u.name AS employee_name, m.title, m.location,
                           m.status, m.outcome, m.client_feedback
                    FROM meetings m JOIN users u ON m.assigned_to_user_id = u.user_id
                    LEFT JOIN employees e ON u.user_id = e.user_id
                    WHERE m.type = :meeting_type AND DATE(m.scheduled_start) BETWEEN :from AND :to $userSql
                    ORDER BY m.scheduled_start DESC", array_merge($params, [':meeting_type' => $key]));

            case 'performance':
                return $this->simpleReport($key, $filters, [
                    'Employee' => 'employee_name', 'Period' => 'period', 'Start' => 'start_date',
                    'End' => 'end_date', 'Overall' => 'overall_score', 'Band' => 'performance_band',
                    'Team Rank' => 'team_rank',
                ], "SELECT u.name AS employee_name, ps.period, ps.start_date, ps.end_date,
                           ps.overall_score, ps.performance_band, ps.team_rank
                    FROM performance_scores ps JOIN users u ON ps.user_id = u.user_id
                    LEFT JOIN employees e ON u.user_id = e.user_id
                    WHERE ps.start_date <= :to AND ps.end_date >= :from $userSql
                    ORDER BY ps.period, ps.overall_score DESC", $params);

            case 'monthly_summary':
            case 'daily_summary':
            default:
                return $this->summaryReport($key, $filters, $visibleUserIds);
        }
    }

    public function getUsersForScope(?array $visibleUserIds = null): array {
        $where = "WHERE u.status = 'active'";
        $params = [];
        if (is_array($visibleUserIds)) {
            if (!$visibleUserIds) {
                return [];
            }
            $keys = [];
            foreach (array_values($visibleUserIds) as $i => $id) {
                $k = ':u' . $i;
                $keys[] = $k;
                $params[$k] = (int) $id;
            }
            $where .= ' AND u.user_id IN (' . implode(',', $keys) . ')';
        }
        $this->query("SELECT u.user_id, u.name, e.team_id FROM users u LEFT JOIN employees e ON u.user_id = e.user_id $where ORDER BY u.name ASC");
        foreach ($params as $k => $v) {
            $this->bind($k, $v);
        }
        return $this->resultSet();
    }

    public function getTeamsForScope(?array $visibleUserIds = null): array {
        if ($visibleUserIds === null) {
            $this->query("SELECT team_id, name FROM teams WHERE status = 'active' ORDER BY name ASC");
            return $this->resultSet();
        }
        if (!$visibleUserIds) {
            return [];
        }
        $keys = [];
        $params = [];
        foreach (array_values($visibleUserIds) as $i => $id) {
            $k = ':u' . $i;
            $keys[] = $k;
            $params[$k] = (int) $id;
        }
        $this->query("SELECT DISTINCT t.team_id, t.name
                      FROM teams t JOIN employees e ON t.team_id = e.team_id
                      WHERE e.user_id IN (" . implode(',', $keys) . ")
                      ORDER BY t.name ASC");
        foreach ($params as $k => $v) {
            $this->bind($k, $v);
        }
        return $this->resultSet();
    }

    public function saveSummary(string $reportKey, string $period, string $from, string $to, array $summary, ?int $generatedBy = null): bool {
        $this->query("INSERT INTO report_summaries
                        (report_key, period, start_date, end_date, generated_by_user_id, summary_json)
                      VALUES (:report_key, :period, :from_date, :to_date, :uid, :summary_json)
                      ON DUPLICATE KEY UPDATE
                        generated_by_user_id = VALUES(generated_by_user_id),
                        summary_json = VALUES(summary_json),
                        created_at = CURRENT_TIMESTAMP");
        $this->bind(':report_key', $reportKey);
        $this->bind(':period', $period);
        $this->bind(':from_date', $from);
        $this->bind(':to_date', $to);
        $this->bind(':uid', $generatedBy);
        $this->bind(':summary_json', json_encode($summary));
        return $this->execute();
    }

    public function markEmailed(string $reportKey, string $period, string $from, string $to): bool {
        $this->query("UPDATE report_summaries SET emailed_at = NOW()
                      WHERE report_key = :report_key AND period = :period AND start_date = :from_date AND end_date = :to_date");
        $this->bind(':report_key', $reportKey);
        $this->bind(':period', $period);
        $this->bind(':from_date', $from);
        $this->bind(':to_date', $to);
        return $this->execute();
    }

    public function digestRecipients(): array {
        $this->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('reports.email_enabled','reports.digest_recipients')");
        $rows = $this->resultSet();
        $settings = [];
        foreach ($rows as $row) {
            $settings[$row->setting_key] = (string) $row->setting_value;
        }
        if (($settings['reports.email_enabled'] ?? '0') !== '1') {
            return [];
        }
        $parts = preg_split('/[,;\s]+/', $settings['reports.digest_recipients'] ?? '');
        return array_values(array_filter(array_map('trim', $parts)));
    }

    private function simpleReport(string $key, array $filters, array $columns, string $sql, array $params): array {
        $params[':from'] = $filters['from'];
        $params[':to'] = $filters['to'];
        $params[':team_filter'] = $filters['team_id'];
        $params[':team_filter2'] = $filters['team_id'];
        $params[':team_filter3'] = $filters['team_id'];
        $rows = $this->fetchRows($sql, $params);

        return [
            'key' => $key,
            'title' => self::REPORTS[$key],
            'filters' => $filters,
            'columns' => $columns,
            'rows' => $rows,
            'totals' => $this->totals($rows),
        ];
    }

    private function summaryReport(string $key, array $filters, ?array $visibleUserIds): array {
        [$userSql, $params] = $this->userScopeSql($filters, $visibleUserIds);
        $params[':from'] = $filters['from'];
        $params[':to'] = $filters['to'];

        $metrics = [
            ['Attendance Days', "SELECT COUNT(*) FROM attendance a JOIN users u ON a.user_id = u.user_id LEFT JOIN employees e ON u.user_id = e.user_id WHERE a.work_date BETWEEN :from AND :to $userSql"],
            ['Worked Minutes', "SELECT COALESCE(SUM(a.worked_minutes),0) FROM attendance a JOIN users u ON a.user_id = u.user_id LEFT JOIN employees e ON u.user_id = e.user_id WHERE a.work_date BETWEEN :from AND :to $userSql"],
            ['Distance KM', "SELECT COALESCE(SUM(ts.distance_km),0) FROM travel_summary ts JOIN users u ON ts.user_id = u.user_id LEFT JOIN employees e ON u.user_id = e.user_id WHERE ts.work_date BETWEEN :from AND :to $userSql"],
            ['Tasks Completed', "SELECT COUNT(*) FROM tasks t JOIN users u ON t.assigned_to_user_id = u.user_id LEFT JOIN employees e ON u.user_id = e.user_id WHERE t.status = 'completed' AND DATE(t.completed_at) BETWEEN :from AND :to $userSql"],
            ['Leads Generated', "SELECT COUNT(*) FROM leads l LEFT JOIN users u ON l.assigned_to_user_id = u.user_id LEFT JOIN employees e ON u.user_id = e.user_id WHERE DATE(l.created_at) BETWEEN :from AND :to $userSql"],
            ['Leads Converted', "SELECT COUNT(*) FROM leads l LEFT JOIN users u ON l.assigned_to_user_id = u.user_id LEFT JOIN employees e ON u.user_id = e.user_id WHERE l.status = 'converted' AND DATE(COALESCE(l.converted_at, l.updated_at)) BETWEEN :from AND :to $userSql"],
            ['Revenue', "SELECT COALESCE(SUM(l.lead_value),0) FROM leads l LEFT JOIN users u ON l.assigned_to_user_id = u.user_id LEFT JOIN employees e ON u.user_id = e.user_id WHERE l.status = 'converted' AND DATE(COALESCE(l.converted_at, l.updated_at)) BETWEEN :from AND :to $userSql"],
            ['Follow-ups Due', "SELECT COUNT(*) FROM follow_ups f JOIN users u ON f.assigned_to_user_id = u.user_id LEFT JOIN employees e ON u.user_id = e.user_id WHERE DATE(f.due_at) BETWEEN :from AND :to $userSql"],
            ['Missed Follow-ups', "SELECT COUNT(*) FROM follow_ups f JOIN users u ON f.assigned_to_user_id = u.user_id LEFT JOIN employees e ON u.user_id = e.user_id WHERE f.status = 'missed' AND DATE(f.due_at) BETWEEN :from AND :to $userSql"],
            ['Communications', "SELECT COUNT(*) FROM communications c JOIN users u ON c.user_id = u.user_id LEFT JOIN employees e ON u.user_id = e.user_id WHERE DATE(c.happened_at) BETWEEN :from AND :to $userSql"],
            ['Meetings Completed', "SELECT COUNT(*) FROM meetings m JOIN users u ON m.assigned_to_user_id = u.user_id LEFT JOIN employees e ON u.user_id = e.user_id WHERE m.type = 'meeting' AND m.status = 'completed' AND DATE(m.scheduled_start) BETWEEN :from AND :to $userSql"],
            ['Demos Completed', "SELECT COUNT(*) FROM meetings m JOIN users u ON m.assigned_to_user_id = u.user_id LEFT JOIN employees e ON u.user_id = e.user_id WHERE m.type = 'demo' AND m.status = 'completed' AND DATE(m.scheduled_start) BETWEEN :from AND :to $userSql"],
        ];

        $rows = [];
        foreach ($metrics as [$label, $sql]) {
            $rows[] = ['metric' => $label, 'value' => $this->scalar($sql, $params)];
        }

        return [
            'key' => $key,
            'title' => self::REPORTS[$key],
            'filters' => $filters,
            'columns' => ['Metric' => 'metric', 'Value' => 'value'],
            'rows' => $rows,
            'totals' => [],
        ];
    }

    private function normalizeFilters(array $filters): array {
        $to = $this->validDate($filters['to'] ?? '') ?: date('Y-m-d');
        $from = $this->validDate($filters['from'] ?? '') ?: date('Y-m-d', strtotime($to . ' -30 days'));
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }
        return [
            'from' => $from,
            'to' => $to,
            'user_id' => max(0, (int)($filters['user_id'] ?? 0)),
            'team_id' => max(0, (int)($filters['team_id'] ?? 0)),
        ];
    }

    private function validDate(string $date): ?string {
        $dt = DateTime::createFromFormat('Y-m-d', $date);
        return $dt && $dt->format('Y-m-d') === $date ? $date : null;
    }

    private function userScopeSql(array $filters, ?array $visibleUserIds): array {
        $ids = $visibleUserIds === null ? null : array_values(array_unique(array_map('intval', $visibleUserIds)));
        if ($filters['user_id'] > 0) {
            if ($ids === null || in_array($filters['user_id'], $ids, true)) {
                $ids = [$filters['user_id']];
            } else {
                $ids = [];
            }
        }

        $where = '';
        $params = [];
        if (is_array($ids)) {
            if (!$ids) {
                return [' AND 1 = 0', []];
            }
            $keys = [];
            foreach ($ids as $i => $id) {
                $k = ':scope_user_' . $i;
                $keys[] = $k;
                $params[$k] = $id;
            }
            $where .= ' AND u.user_id IN (' . implode(',', $keys) . ')';
        }
        if ($filters['team_id'] > 0) {
            $where .= ' AND e.team_id = :filter_team_id';
            $params[':filter_team_id'] = $filters['team_id'];
        }
        return [$where, $params];
    }

    private function targetScopeSql(array $filters, ?array $visibleUserIds): array {
        $where = '';
        $params = [];

        if ($filters['user_id'] > 0) {
            if (is_array($visibleUserIds) && !in_array($filters['user_id'], array_map('intval', $visibleUserIds), true)) {
                return [' AND 1 = 0', []];
            }
            $where .= ' AND u.user_id = :target_user_id';
            $params[':target_user_id'] = $filters['user_id'];
        } elseif (is_array($visibleUserIds)) {
            if (!$visibleUserIds) {
                return [' AND 1 = 0', []];
            }
            $keys = [];
            $teamKeys = [];
            foreach (array_values(array_unique(array_map('intval', $visibleUserIds))) as $i => $id) {
                $k = ':target_scope_user_' . $i;
                $tk = ':target_scope_team_user_' . $i;
                $keys[] = $k;
                $teamKeys[] = $tk;
                $params[$k] = $id;
                $params[$tk] = $id;
            }
            $in = implode(',', $keys);
            $teamIn = implode(',', $teamKeys);
            $where .= " AND (u.user_id IN ($in)
                         OR tg.team_id IN (
                            SELECT DISTINCT team_id FROM employees
                            WHERE user_id IN ($teamIn) AND team_id IS NOT NULL
                         ))";
        }

        if ($filters['team_id'] > 0) {
            $where .= ' AND (tg.team_id = :target_team_id OR e.team_id = :target_team_id2)';
            $params[':target_team_id'] = $filters['team_id'];
            $params[':target_team_id2'] = $filters['team_id'];
        }

        return [$where, $params];
    }

    private function fetchRows(string $sql, array $params): array {
        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            if (strpos($sql, $key) === false) {
                continue;
            }
            $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function scalar(string $sql, array $params) {
        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            if (strpos($sql, $key) === false) {
                continue;
            }
            $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();
        return $stmt->fetchColumn();
    }

    private function totals(array $rows): array {
        $totals = ['rows' => count($rows)];
        foreach ($rows as $row) {
            foreach ($row as $key => $value) {
                if (is_numeric($value)) {
                    $totals[$key] = ($totals[$key] ?? 0) + (float) $value;
                }
            }
        }
        return $totals;
    }
}
