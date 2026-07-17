<?php
// Dedicated dashboard module with five customizable dashboards.

class DashboardModule extends Model {
    public const DASHBOARDS = [
        'executive' => [
            'label' => 'Executive & Analytics Overview',
            'description' => 'Leadership and analyst KPIs across revenue, pipeline, activity, and risk.',
            'roles' => ['admin', 'manager', 'employer', 'analyst'],
            'widgets' => ['revenue', 'pipeline', 'lead_funnel', 'activity_mix', 'risk_alerts', 'top_performers'],
        ],
        'sales_command' => [
            'label' => 'Sales Command Center',
            'description' => 'Live sales team status, today rollup, follow-ups, and blockers.',
            'roles' => ['admin', 'manager', 'team_leader'],
            'widgets' => ['live_board', 'attendance', 'today_targets', 'followups', 'tasks', 'activity_mix'],
        ],
        'field_activity' => [
            'label' => 'Field Activity',
            'description' => 'Routes, distance, meetings, demos, calls, and field productivity.',
            'roles' => ['admin', 'manager', 'team_leader', 'employee', 'sales_person'],
            'widgets' => ['distance', 'meetings', 'communications', 'route_health', 'proofs', 'daily_summary'],
        ],
        'pipeline_revenue' => [
            'label' => 'Pipeline & Revenue',
            'description' => 'Lead pipeline, forecast value, conversions, and revenue movement.',
            'roles' => ['admin', 'manager', 'team_leader', 'employee', 'sales_person'],
            'widgets' => ['pipeline', 'revenue', 'lead_funnel', 'conversion_rate', 'high_value_leads', 'source_mix'],
        ],
        'performance_targets' => [
            'label' => 'Performance & Targets',
            'description' => 'Targets, scores, rankings, task discipline, and coaching signals.',
            'roles' => ['admin', 'manager', 'team_leader', 'employee', 'sales_person'],
            'widgets' => ['target_completion', 'performance_rank', 'task_completion', 'followup_discipline', 'low_performers', 'review_queue'],
        ],
    ];

    public function dashboardsForRole(string $role): array {
        $out = [];
        foreach (self::DASHBOARDS as $key => $dashboard) {
            if (in_array($role, $dashboard['roles'], true)) {
                $out[$key] = $dashboard;
            }
        }
        return $out;
    }

    public function getDashboard(string $key, string $role): ?array {
        $dashboards = $this->dashboardsForRole($role);
        if (!isset($dashboards[$key])) {
            return null;
        }
        return $dashboards[$key] + ['key' => $key];
    }

    public function preferences(int $userId, string $dashboardKey): array {
        $this->query('SELECT * FROM dashboard_preferences WHERE user_id = :uid AND dashboard_key = :dashboard_key');
        $this->bind(':uid', $userId);
        $this->bind(':dashboard_key', $dashboardKey);
        $row = $this->single();

        return [
            'widget_order' => $row && $row->widget_order ? (json_decode($row->widget_order, true) ?: []) : [],
            'hidden_widgets' => $row && $row->hidden_widgets ? (json_decode($row->hidden_widgets, true) ?: []) : [],
            'theme_accent' => $row->theme_accent ?? 'indigo',
            'date_range_days' => (int) ($row->date_range_days ?? $this->settingInt('dashboards.default_range_days', 30)),
        ];
    }

    public function savePreferences(int $userId, string $dashboardKey, array $input, array $allowedWidgets): bool {
        $hidden = [];
        foreach (($input['hidden_widgets'] ?? []) as $widget) {
            if (in_array($widget, $allowedWidgets, true)) {
                $hidden[] = $widget;
            }
        }

        $order = [];
        $submittedOrder = $input['widget_order'] ?? [];
        asort($submittedOrder, SORT_NUMERIC);
        foreach ($submittedOrder as $widget => $position) {
            if (in_array($widget, $allowedWidgets, true)) {
                $order[] = $widget;
            }
        }
        foreach ($allowedWidgets as $widget) {
            if (!in_array($widget, $order, true)) {
                $order[] = $widget;
            }
        }

        $accent = in_array($input['theme_accent'] ?? '', ['indigo', 'emerald', 'amber', 'rose', 'cyan'], true)
            ? $input['theme_accent']
            : 'indigo';
        $days = max(1, min(365, (int) ($input['date_range_days'] ?? 30)));

        $this->query('INSERT INTO dashboard_preferences
                (user_id, dashboard_key, widget_order, hidden_widgets, theme_accent, date_range_days)
            VALUES (:uid, :dashboard_key, :widget_order, :hidden_widgets, :accent, :days)
            ON DUPLICATE KEY UPDATE
                widget_order = VALUES(widget_order),
                hidden_widgets = VALUES(hidden_widgets),
                theme_accent = VALUES(theme_accent),
                date_range_days = VALUES(date_range_days)');
        $this->bind(':uid', $userId);
        $this->bind(':dashboard_key', $dashboardKey);
        $this->bind(':widget_order', json_encode($order));
        $this->bind(':hidden_widgets', json_encode($hidden));
        $this->bind(':accent', $accent);
        $this->bind(':days', $days, PDO::PARAM_INT);
        return $this->execute();
    }

    public function visibleWidgets(array $dashboard, array $prefs): array {
        $widgets = $prefs['widget_order'] ?: $dashboard['widgets'];
        $widgets = array_values(array_filter($widgets, function ($widget) use ($dashboard, $prefs) {
            return in_array($widget, $dashboard['widgets'], true)
                && !in_array($widget, $prefs['hidden_widgets'], true);
        }));
        foreach ($dashboard['widgets'] as $widget) {
            if (!in_array($widget, $widgets, true) && !in_array($widget, $prefs['hidden_widgets'], true)) {
                $widgets[] = $widget;
            }
        }
        return $widgets;
    }

    public function data(string $dashboardKey, array $prefs, ?array $visibleUserIds): array {
        $days = (int) $prefs['date_range_days'];
        $from = date('Y-m-d 00:00:00', strtotime('-' . ($days - 1) . ' days'));
        $to = date('Y-m-d 23:59:59');
        $dateFrom = substr($from, 0, 10);
        $dateTo = substr($to, 0, 10);
        [$userSql, $assignedSql, $targetSql, $notificationSql] = $this->scopeSql($visibleUserIds);
        $performanceSql = $this->columnScopeSql($visibleUserIds, 'ps.user_id');
        $monitoring = $this->monitoring();

        return [
            'range_label' => $dateFrom . ' to ' . $dateTo,
            'kpis' => [
                'revenue' => $this->scalar("SELECT COALESCE(SUM(lead_value),0) FROM leads WHERE $assignedSql AND status = 'converted' AND COALESCE(converted_at, updated_at) BETWEEN :from AND :to", $from, $to),
                'pipeline_value' => $this->scalar("SELECT COALESCE(SUM(lead_value),0) FROM leads WHERE $assignedSql AND status NOT IN ('converted','lost')", $from, $to),
                'forecast' => $this->scalar("SELECT COALESCE(SUM(lead_value * COALESCE(probability, conversion_probability, 0) / 100),0) FROM leads WHERE $assignedSql AND status NOT IN ('converted','lost')", $from, $to),
                'leads' => $this->scalar("SELECT COUNT(*) FROM leads WHERE $assignedSql AND created_at BETWEEN :from AND :to", $from, $to),
                'conversions' => $this->scalar("SELECT COUNT(*) FROM leads WHERE $assignedSql AND status = 'converted' AND COALESCE(converted_at, updated_at) BETWEEN :from AND :to", $from, $to),
                'communications' => $this->scalar("SELECT COUNT(*) FROM communications WHERE $userSql AND happened_at BETWEEN :from AND :to", $from, $to),
                'meetings' => $this->scalar("SELECT COUNT(*) FROM meetings WHERE $assignedSql AND scheduled_start BETWEEN :from AND :to", $from, $to),
                'distance' => $this->scalar("SELECT COALESCE(SUM(distance_km),0) FROM travel_summary WHERE $userSql AND work_date BETWEEN :date_from AND :date_to", $from, $to, $dateFrom, $dateTo),
                'target_completion' => $this->scalar("SELECT COALESCE(AVG(tp.completion_percent),0)
                    FROM target_progress tp JOIN target_items ti ON tp.target_item_id = ti.target_item_id
                    JOIN targets t ON ti.target_id = t.target_id
                    WHERE $targetSql AND t.status = 'approved' AND t.start_date <= :date_to AND t.end_date >= :date_from", $from, $to, $dateFrom, $dateTo),
                'performance' => $this->scalar("SELECT COALESCE(AVG(overall_score),0) FROM performance_scores WHERE $userSql AND end_date >= :date_from", $from, $to, $dateFrom, $dateTo),
            ],
            'pipeline' => $this->rows("SELECT status, COUNT(*) AS count, COALESCE(SUM(lead_value),0) AS value
                FROM leads WHERE $assignedSql GROUP BY status ORDER BY FIELD(status,'new','contacted','qualified','proposal','converted','lost')", $from, $to),
            'activity_mix' => [
                ['label' => 'Calls', 'value' => $this->scalar("SELECT COUNT(*) FROM communications WHERE $userSql AND channel = 'call' AND happened_at BETWEEN :from AND :to", $from, $to)],
                ['label' => 'Messages', 'value' => $this->scalar("SELECT COUNT(*) FROM communications WHERE $userSql AND channel IN ('whatsapp','sms','social') AND happened_at BETWEEN :from AND :to", $from, $to)],
                ['label' => 'Emails', 'value' => $this->scalar("SELECT COUNT(*) FROM communications WHERE $userSql AND channel = 'email' AND happened_at BETWEEN :from AND :to", $from, $to)],
                ['label' => 'Meetings', 'value' => $this->scalar("SELECT COUNT(*) FROM meetings WHERE $assignedSql AND type = 'meeting' AND scheduled_start BETWEEN :from AND :to", $from, $to)],
                ['label' => 'Demos', 'value' => $this->scalar("SELECT COUNT(*) FROM meetings WHERE $assignedSql AND type = 'demo' AND scheduled_start BETWEEN :from AND :to", $from, $to)],
            ],
            'today_rollup' => $monitoring->todayRollup($visibleUserIds),
            'live_board' => array_slice($monitoring->liveBoard($visibleUserIds), 0, 8),
            'top_performers' => $this->rows("SELECT u.name, ps.overall_score, ps.performance_band
                FROM performance_scores ps JOIN users u ON ps.user_id = u.user_id
                WHERE $performanceSql AND ps.end_date >= :date_from ORDER BY ps.overall_score DESC LIMIT 5", $from, $to, $dateFrom, $dateTo),
            'low_performers' => $this->rows("SELECT u.name, ps.overall_score, ps.performance_band
                FROM performance_scores ps JOIN users u ON ps.user_id = u.user_id
                WHERE $performanceSql AND ps.end_date >= :date_from ORDER BY ps.overall_score ASC LIMIT 5", $from, $to, $dateFrom, $dateTo),
            'risk_alerts' => $this->rows("SELECT title, severity, created_at FROM notifications
                WHERE $notificationSql AND created_at BETWEEN :from AND :to ORDER BY created_at DESC LIMIT 5", $from, $to),
            'review_queue' => [
                'tasks' => $this->scalar("SELECT COUNT(*) FROM tasks WHERE $assignedSql AND review_status = 'pending_review'", $from, $to),
                'attendance' => $this->scalar("SELECT COUNT(*) FROM attendance WHERE $userSql AND attendance_status = 'Pending'", $from, $to),
            ],
            'tasks' => [
                'pending' => $this->scalar("SELECT COUNT(*) FROM tasks WHERE $assignedSql AND status = 'pending'", $from, $to),
                'in_progress' => $this->scalar("SELECT COUNT(*) FROM tasks WHERE $assignedSql AND status = 'in_progress'", $from, $to),
                'completed' => $this->scalar("SELECT COUNT(*) FROM tasks WHERE $assignedSql AND status = 'completed' AND COALESCE(completed_at, updated_at) BETWEEN :from AND :to", $from, $to),
                'overdue' => $this->scalar("SELECT COUNT(*) FROM tasks WHERE $assignedSql AND status <> 'completed' AND deadline < NOW()", $from, $to),
            ],
            'followups' => [
                'scheduled' => $this->scalar("SELECT COUNT(*) FROM follow_ups WHERE $assignedSql AND status = 'scheduled' AND due_at BETWEEN :from AND :to", $from, $to),
                'completed' => $this->scalar("SELECT COUNT(*) FROM follow_ups WHERE $assignedSql AND status = 'completed' AND COALESCE(completed_at, due_at) BETWEEN :from AND :to", $from, $to),
                'missed' => $this->scalar("SELECT COUNT(*) FROM follow_ups WHERE $assignedSql AND status = 'missed' AND due_at BETWEEN :from AND :to", $from, $to),
            ],
            'high_value_leads' => $this->rows("SELECT first_name, last_name, lead_value, status
                FROM leads WHERE $assignedSql AND status NOT IN ('converted','lost')
                ORDER BY lead_value DESC LIMIT 5", $from, $to),
            'source_mix' => $this->rows("SELECT COALESCE(lead_source, 'Unknown') AS source, COUNT(*) AS count
                FROM leads WHERE $assignedSql AND created_at BETWEEN :from AND :to
                GROUP BY COALESCE(lead_source, 'Unknown') ORDER BY count DESC LIMIT 6", $from, $to),
            'proofs' => [
                'task_proofs' => $this->scalar("SELECT COUNT(*) FROM tasks WHERE $assignedSql AND proof_url IS NOT NULL AND COALESCE(completed_at, updated_at) BETWEEN :from AND :to", $from, $to),
                'meeting_selfies' => $this->scalar("SELECT COUNT(*) FROM meeting_checkins mc JOIN meetings m ON mc.meeting_id = m.meeting_id WHERE $assignedSql AND mc.selfie_url IS NOT NULL AND mc.checked_at BETWEEN :from AND :to", $from, $to),
            ],
        ];
    }

    public function widgetMeta(): array {
        return [
            'revenue' => ['label' => 'Revenue', 'icon' => 'fa-indian-rupee-sign'],
            'pipeline' => ['label' => 'Pipeline', 'icon' => 'fa-diagram-project'],
            'lead_funnel' => ['label' => 'Lead Funnel', 'icon' => 'fa-filter'],
            'activity_mix' => ['label' => 'Activity Mix', 'icon' => 'fa-chart-pie'],
            'risk_alerts' => ['label' => 'Risk Alerts', 'icon' => 'fa-triangle-exclamation'],
            'top_performers' => ['label' => 'Top Performers', 'icon' => 'fa-ranking-star'],
            'live_board' => ['label' => 'Live Board', 'icon' => 'fa-tower-observation'],
            'attendance' => ['label' => 'Attendance', 'icon' => 'fa-fingerprint'],
            'today_targets' => ['label' => 'Today Targets', 'icon' => 'fa-bullseye'],
            'followups' => ['label' => 'Follow-ups', 'icon' => 'fa-bell'],
            'tasks' => ['label' => 'Tasks', 'icon' => 'fa-list-check'],
            'distance' => ['label' => 'Distance', 'icon' => 'fa-route'],
            'meetings' => ['label' => 'Meetings', 'icon' => 'fa-handshake'],
            'communications' => ['label' => 'Communications', 'icon' => 'fa-comments'],
            'route_health' => ['label' => 'Route Health', 'icon' => 'fa-location-dot'],
            'proofs' => ['label' => 'Proofs', 'icon' => 'fa-camera'],
            'daily_summary' => ['label' => 'Daily Summary', 'icon' => 'fa-calendar-day'],
            'conversion_rate' => ['label' => 'Conversion Rate', 'icon' => 'fa-percent'],
            'high_value_leads' => ['label' => 'High Value Leads', 'icon' => 'fa-gem'],
            'source_mix' => ['label' => 'Source Mix', 'icon' => 'fa-share-nodes'],
            'target_completion' => ['label' => 'Target Completion', 'icon' => 'fa-bullseye'],
            'performance_rank' => ['label' => 'Performance Rank', 'icon' => 'fa-ranking-star'],
            'task_completion' => ['label' => 'Task Completion', 'icon' => 'fa-square-check'],
            'followup_discipline' => ['label' => 'Follow-up Discipline', 'icon' => 'fa-clock'],
            'low_performers' => ['label' => 'Low Performers', 'icon' => 'fa-arrow-trend-down'],
        ];
    }

    private function monitoring() {
        require_once APPROOT . '/models/Monitoring.php';
        return new Monitoring();
    }

    private function columnScopeSql(?array $visibleUserIds, string $column): string {
        if ($visibleUserIds === null) {
            return '1=1';
        }
        if (!$visibleUserIds) {
            return '1=0';
        }
        return $column . ' IN (' . implode(',', array_map('intval', $visibleUserIds)) . ')';
    }

    private function scopeSql(?array $visibleUserIds): array {
        if ($visibleUserIds === null) {
            return ['1=1', '1=1', '1=1', '1=1'];
        }
        if (!$visibleUserIds) {
            return ['1=0', '1=0', '1=0', '1=0'];
        }
        $ids = implode(',', array_map('intval', $visibleUserIds));
        return [
            'user_id IN (' . $ids . ')',
            'assigned_to_user_id IN (' . $ids . ')',
            '(t.owner_user_id IN (' . $ids . ') OR EXISTS (SELECT 1 FROM employees e WHERE e.team_id = t.team_id AND e.user_id IN (' . $ids . ')))',
            'user_id IN (' . $ids . ')',
        ];
    }

    private function scalar(string $sql, string $from, string $to, ?string $dateFrom = null, ?string $dateTo = null): float {
        $this->query($sql);
        if (strpos($sql, ':from') !== false) {
            $this->bind(':from', $from);
        }
        if (strpos($sql, ':to') !== false) {
            $this->bind(':to', $to);
        }
        if (strpos($sql, ':date_from') !== false) {
            $this->bind(':date_from', $dateFrom ?: substr($from, 0, 10));
        }
        if (strpos($sql, ':date_to') !== false) {
            $this->bind(':date_to', $dateTo ?: substr($to, 0, 10));
        }
        $this->execute();
        return (float) $this->stmt->fetchColumn();
    }

    private function rows(string $sql, string $from, string $to, ?string $dateFrom = null, ?string $dateTo = null): array {
        $this->query($sql);
        if (strpos($sql, ':from') !== false) {
            $this->bind(':from', $from);
        }
        if (strpos($sql, ':to') !== false) {
            $this->bind(':to', $to);
        }
        if (strpos($sql, ':date_from') !== false) {
            $this->bind(':date_from', $dateFrom ?: substr($from, 0, 10));
        }
        if (strpos($sql, ':date_to') !== false) {
            $this->bind(':date_to', $dateTo ?: substr($to, 0, 10));
        }
        return $this->resultSet();
    }

    public function templatesForUser(int $userId, string $role): array {
        $this->query('SELECT dt.*, u.name AS created_by_name
            FROM dashboard_templates dt
            JOIN users u ON dt.created_by_user_id = u.user_id
            WHERE dt.status = "active"
              AND (
                dt.visibility = "global"
                OR dt.created_by_user_id = :uid
                OR (dt.visibility = "role" AND JSON_CONTAINS(COALESCE(dt.allowed_roles, JSON_ARRAY()), JSON_QUOTE(:role)))
              )
            ORDER BY dt.updated_at DESC, dt.name ASC');
        $this->bind(':uid', $userId);
        $this->bind(':role', $role);
        return $this->resultSet();
    }

    public function createTemplate(array $data, int $userId, string $role): bool {
        $dashboard = $this->getDashboard($data['base_dashboard_key'] ?? '', $role);
        if (!$dashboard) {
            return false;
        }

        $widgets = [];
        foreach (($data['widgets'] ?? []) as $widget) {
            if (in_array($widget, $dashboard['widgets'], true)) {
                $widgets[] = $widget;
            }
        }
        if (!$widgets) {
            $widgets = $dashboard['widgets'];
        }

        $allowedRoles = array_values(array_intersect(
            $data['allowed_roles'] ?? [$role],
            ['admin', 'manager', 'team_leader', 'employee', 'sales_person', 'analyst', 'employer', 'hr']
        ));

        $this->query('INSERT INTO dashboard_templates
                (name, description, base_dashboard_key, widgets, visibility, allowed_roles, created_by_user_id)
            VALUES (:name, :description, :base_key, :widgets, :visibility, :allowed_roles, :uid)');
        $this->bind(':name', trim($data['name'] ?? ''));
        $this->bind(':description', trim($data['description'] ?? ''));
        $this->bind(':base_key', $dashboard['key']);
        $this->bind(':widgets', json_encode($widgets));
        $this->bind(':visibility', in_array($data['visibility'] ?? 'role', ['private', 'role', 'global'], true) ? $data['visibility'] : 'role');
        $this->bind(':allowed_roles', json_encode($allowedRoles));
        $this->bind(':uid', $userId);
        return trim($data['name'] ?? '') !== '' && $this->execute();
    }

    private function settingInt(string $key, int $default): int {
        $this->query('SELECT setting_value FROM settings WHERE setting_key = :key');
        $this->bind(':key', $key);
        $row = $this->single();
        return $row ? (int) $row->setting_value : $default;
    }
}
