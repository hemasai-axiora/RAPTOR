<?php
// CustomDashboard Model - Handles Power BI-style Custom Dashboard Builder data persistence and dynamic widget aggregation

class CustomDashboard extends Model {

    public function getDashboardsForUser(int $userId, string $userRole): array {
        $sql = "SELECT DISTINCT d.*, u.name AS owner_name,
                    (SELECT COUNT(*) FROM custom_dashboard_widgets w WHERE w.dashboard_id = d.id) AS widget_count
                FROM custom_dashboards d
                JOIN users u ON d.owner_id = u.user_id
                LEFT JOIN custom_dashboard_roles r ON d.id = r.dashboard_id
                WHERE d.owner_id = :uid
                   OR d.visibility_type = 'everyone'
                   OR d.is_template = 1
                   OR (d.visibility_type = 'role' AND r.role = :urole)
                ORDER BY d.is_default DESC, d.updated_at DESC";

        $this->query($sql);
        $this->bind(':uid', $userId);
        $this->bind(':urole', $userRole);
        $dashboards = $this->resultSet();

        foreach ($dashboards as &$dash) {
            $dash->roles = $this->getDashboardRoles($dash->id);
            $dash->is_owner = ((int)$dash->owner_id === $userId);
        }

        return $dashboards;
    }

    public function getDashboardRoles(int $dashboardId): array {
        $this->query("SELECT role FROM custom_dashboard_roles WHERE dashboard_id = :did");
        $this->bind(':did', $dashboardId);
        $rows = $this->resultSet();
        return array_column($rows, 'role');
    }

    public function getDashboardById(int $id): ?object {
        $this->query("SELECT d.*, u.name AS owner_name FROM custom_dashboards d JOIN users u ON d.owner_id = u.user_id WHERE d.id = :id");
        $this->bind(':id', $id);
        $dashboard = $this->single();

        if (!$dashboard) return null;

        $dashboard->roles = $this->getDashboardRoles($id);
        $dashboard->widgets = $this->getWidgetsForDashboard($id);

        return $dashboard;
    }

    public function getWidgetsForDashboard(int $dashboardId): array {
        $this->query("SELECT * FROM custom_dashboard_widgets WHERE dashboard_id = :did ORDER BY sort_order ASC, pos_y ASC, pos_x ASC");
        $this->bind(':did', $dashboardId);
        $widgets = $this->resultSet();

        foreach ($widgets as &$w) {
            $w->config = json_decode($w->config_json ?: '{}', true) ?: [];
        }

        return $widgets;
    }

    public function saveDashboard(array $data, int $userId): int {
        $id = !empty($data['id']) ? (int)$data['id'] : 0;
        $name = trim($data['name'] ?? 'Untitled Dashboard');
        $desc = trim($data['description'] ?? '');
        $isTemplate = !empty($data['is_template']) ? 1 : 0;
        $isDefault = !empty($data['is_default']) ? 1 : 0;
        $visibility = in_array($data['visibility_type'] ?? '', ['private', 'role', 'everyone'], true) ? $data['visibility_type'] : 'private';

        if ($id > 0) {
            $this->query("UPDATE custom_dashboards 
                SET name = :name, description = :desc, is_template = :is_template, visibility_type = :visibility
                WHERE id = :id AND (owner_id = :uid OR :is_admin = 1)");
            $this->bind(':name', $name);
            $this->bind(':desc', $desc);
            $this->bind(':is_template', $isTemplate);
            $this->bind(':visibility', $visibility);
            $this->bind(':id', $id);
            $this->bind(':uid', $userId);
            $this->bind(':is_admin', (($_SESSION['user_role'] ?? '') === 'admin') ? 1 : 0);
            $this->execute();
        } else {
            $this->query("INSERT INTO custom_dashboards (name, description, owner_id, is_template, is_default, visibility_type)
                VALUES (:name, :desc, :uid, :is_template, :is_default, :visibility)");
            $this->bind(':name', $name);
            $this->bind(':desc', $desc);
            $this->bind(':uid', $userId);
            $this->bind(':is_template', $isTemplate);
            $this->bind(':is_default', $isDefault);
            $this->bind(':visibility', $visibility);
            $this->execute();
            $id = (int)$this->lastInsertId();
        }

        // Sync Roles
        if ($id > 0) {
            $this->query("DELETE FROM custom_dashboard_roles WHERE dashboard_id = :did");
            $this->bind(':did', $id);
            $this->execute();

            if ($visibility === 'role' && !empty($data['roles']) && is_array($data['roles'])) {
                foreach ($data['roles'] as $role) {
                    $this->query("INSERT INTO custom_dashboard_roles (dashboard_id, role) VALUES (:did, :role)");
                    $this->bind(':did', $id);
                    $this->bind(':role', $role);
                    $this->execute();
                }
            }

            if ($isDefault) {
                $this->setDefaultDashboard($id, $userId);
            }

            // Save Widgets
            if (isset($data['widgets']) && is_array($data['widgets'])) {
                $this->saveWidgets($id, $data['widgets']);
            }
        }

        return $id;
    }

    public function saveWidgets(int $dashboardId, array $widgets): void {
        $this->query("DELETE FROM custom_dashboard_widgets WHERE dashboard_id = :did");
        $this->bind(':did', $dashboardId);
        $this->execute();

        foreach ($widgets as $idx => $w) {
            $title = trim($w['title'] ?? 'Widget');
            $type = $w['widget_type'] ?? 'kpi';
            $dataSource = $w['data_source'] ?? 'leads';
            $posX = (int)($w['pos_x'] ?? 0);
            $posY = (int)($w['pos_y'] ?? 0);
            $width = (int)($w['width'] ?? 6);
            $height = (int)($w['height'] ?? 4);
            $configJson = is_array($w['config'] ?? null) ? json_encode($w['config']) : ($w['config_json'] ?? '{}');

            $this->query("INSERT INTO custom_dashboard_widgets 
                (dashboard_id, title, widget_type, data_source, config_json, pos_x, pos_y, width, height, sort_order)
                VALUES (:did, :title, :type, :ds, :cfg, :px, :py, :w, :h, :order)");
            $this->bind(':did', $dashboardId);
            $this->bind(':title', $title);
            $this->bind(':type', $type);
            $this->bind(':ds', $dataSource);
            $this->bind(':cfg', $configJson);
            $this->bind(':px', $posX);
            $this->bind(':py', $posY);
            $this->bind(':w', $width);
            $this->bind(':h', $height);
            $this->bind(':order', $idx);
            $this->execute();
        }
    }

    public function duplicateDashboard(int $id, int $userId): ?int {
        $source = $this->getDashboardById($id);
        if (!$source) return null;

        $newDashboardData = [
            'name' => $source->name . ' (Copy)',
            'description' => $source->description,
            'is_template' => 0,
            'is_default' => 0,
            'visibility_type' => 'private',
            'roles' => $source->roles,
            'widgets' => []
        ];

        foreach ($source->widgets as $w) {
            $newDashboardData['widgets'][] = [
                'title' => $w->title,
                'widget_type' => $w->widget_type,
                'data_source' => $w->data_source,
                'pos_x' => $w->pos_x,
                'pos_y' => $w->pos_y,
                'width' => $w->width,
                'height' => $w->height,
                'config' => $w->config
            ];
        }

        return $this->saveDashboard($newDashboardData, $userId);
    }

    public function deleteDashboard(int $id, int $userId, bool $isAdmin): bool {
        $this->query("DELETE FROM custom_dashboards WHERE id = :id AND (owner_id = :uid OR :is_admin = 1)");
        $this->bind(':id', $id);
        $this->bind(':uid', $userId);
        $this->bind(':is_admin', $isAdmin ? 1 : 0);
        return $this->execute();
    }

    public function setDefaultDashboard(int $id, int $userId): bool {
        $this->query("UPDATE custom_dashboards SET is_default = 0 WHERE owner_id = :uid");
        $this->bind(':uid', $userId);
        $this->execute();

        $this->query("UPDATE custom_dashboards SET is_default = 1 WHERE id = :id AND owner_id = :uid");
        $this->bind(':id', $id);
        $this->bind(':uid', $userId);
        return $this->execute();
    }

    /**
     * Dynamic Widget Data Aggregation Engine
     */
    public function getWidgetData(array $widget, string $userRole, int $userId, array $params = []): array {
        $dataSource = $widget['data_source'] ?? 'leads';
        $type = $widget['widget_type'] ?? 'kpi';
        $config = $widget['config'] ?? [];

        $metric = $config['metric'] ?? 'count';
        $agg = strtoupper($config['aggregation'] ?? 'COUNT');
        $groupBy = $config['group_by'] ?? '';

        // RBAC Check
        if (in_array($userRole, ['employee', 'sales_person']) && in_array($dataSource, ['invoices', 'payroll'])) {
            return ['error' => 'Access Restricted', 'value' => 0, 'data' => []];
        }

        try {
            switch ($dataSource) {
                case 'leads':
                    return $this->aggregateLeadsData($type, $metric, $agg, $groupBy);
                case 'campaigns':
                    return $this->aggregateCampaignsData($type, $metric, $agg, $groupBy);
                case 'invoices':
                    return $this->aggregateInvoicesData($type, $metric, $agg, $groupBy);
                case 'attendance':
                    return $this->aggregateAttendanceData($type, $metric, $agg, $groupBy);
                case 'targets':
                    return $this->aggregateTargetsData($type, $metric, $agg, $groupBy);
                case 'tasks':
                    return $this->aggregateTasksData($type, $metric, $agg, $groupBy);
                case 'customers':
                    return $this->aggregateCustomersData($type, $metric, $agg, $groupBy);
                case 'website_analytics':
                    return $this->aggregateWebsiteAnalyticsData($type, $metric, $agg, $groupBy);
                case 'text':
                    return ['text' => $config['text_content'] ?? ($widget['title'] ?? 'Custom Note')];
                default:
                    return ['value' => 0, 'labels' => [], 'series' => []];
            }
        } catch (Throwable $e) {
            return ['error' => $e->getMessage(), 'value' => 0, 'data' => []];
        }
    }

    private function aggregateLeadsData(string $type, string $metric, string $agg, string $groupBy): array {
        if ($type === 'kpi') {
            $col = ($metric === 'value') ? 'lead_value' : 'lead_id';
            $fn = ($metric === 'value') ? ($agg === 'AVG' ? 'AVG' : 'SUM') : 'COUNT';
            $this->query("SELECT COALESCE({$fn}({$col}), 0) AS val FROM leads");
            $val = (float)($this->single()->val ?? 0);
            return ['value' => round($val, 2), 'label' => ($metric === 'value' ? '$' . number_format($val, 2) : number_format($val))];
        }

        if (in_array($type, ['bar', 'pie', 'line', 'funnel', 'gauge'])) {
            $groupCol = ($groupBy === 'source') ? 'lead_source' : 'status';
            $this->query("SELECT {$groupCol} AS label, COUNT(*) AS val FROM leads GROUP BY {$groupCol} ORDER BY val DESC LIMIT 10");
            $rows = $this->resultSet();
            return [
                'labels' => array_column($rows, 'label'),
                'series' => array_map('intval', array_column($rows, 'val'))
            ];
        }

        // Table
        $this->query("SELECT lead_id, lead_code, CONCAT(first_name, ' ', COALESCE(last_name,'')) AS lead_name, status, lead_value FROM leads ORDER BY lead_id DESC LIMIT 10");
        $rows = $this->resultSet();
        return ['headers' => ['ID', 'Code', 'Lead Name', 'Status', 'Value ($)'], 'rows' => $rows];
    }

    private function aggregateCampaignsData(string $type, string $metric, string $agg, string $groupBy): array {
        if ($type === 'kpi') {
            $col = ($metric === 'budget') ? 'budget' : (($metric === 'spend') ? 'spend' : 'campaign_id');
            $fn = ($metric === 'count') ? 'COUNT' : 'SUM';
            $this->query("SELECT COALESCE({$fn}({$col}), 0) AS val FROM campaigns");
            $val = (float)($this->single()->val ?? 0);
            return ['value' => round($val, 2), 'label' => ($metric === 'count' ? number_format($val) : '$' . number_format($val, 2))];
        }

        $this->query("SELECT channel AS label, COUNT(*) AS val FROM campaigns GROUP BY channel LIMIT 10");
        $rows = $this->resultSet();
        return [
            'labels' => array_column($rows, 'label'),
            'series' => array_map('intval', array_column($rows, 'val'))
        ];
    }

    private function aggregateInvoicesData(string $type, string $metric, string $agg, string $groupBy): array {
        if ($type === 'kpi') {
            $col = ($metric === 'amount') ? 'amount' : 'invoice_id';
            $fn = ($metric === 'amount') ? ($agg === 'AVG' ? 'AVG' : 'SUM') : 'COUNT';
            $this->query("SELECT COALESCE({$fn}({$col}), 0) AS val FROM invoices");
            $val = (float)($this->single()->val ?? 0);
            return ['value' => round($val, 2), 'label' => ($metric === 'amount' ? '$' . number_format($val, 2) : number_format($val))];
        }

        $this->query("SELECT status AS label, COUNT(*) AS val FROM invoices GROUP BY status");
        $rows = $this->resultSet();
        return [
            'labels' => array_column($rows, 'label'),
            'series' => array_map('intval', array_column($rows, 'val'))
        ];
    }

    private function aggregateAttendanceData(string $type, string $metric, string $agg, string $groupBy): array {
        if ($type === 'kpi') {
            $col = ($metric === 'worked_minutes') ? 'worked_minutes' : 'attendance_id';
            $fn = ($metric === 'worked_minutes') ? 'SUM' : 'COUNT';
            $this->query("SELECT COALESCE({$fn}({$col}), 0) AS val FROM attendance");
            $val = (float)($this->single()->val ?? 0);
            return ['value' => round($val, 1), 'label' => ($metric === 'worked_minutes' ? number_format($val) . ' Mins' : number_format($val) . ' Days')];
        }

        $this->query("SELECT status AS label, COUNT(*) AS val FROM attendance GROUP BY status");
        $rows = $this->resultSet();
        return [
            'labels' => array_column($rows, 'label'),
            'series' => array_map('intval', array_column($rows, 'val'))
        ];
    }

    private function aggregateTargetsData(string $type, string $metric, string $agg, string $groupBy): array {
        if ($type === 'kpi' || $type === 'gauge') {
            $this->query("SELECT COALESCE(SUM(ti.planned_value), 0) AS target_val, COALESCE(SUM(tp.achieved_value), 0) AS ach_val FROM target_items ti LEFT JOIN target_progress tp ON ti.target_item_id = tp.target_item_id");
            $row = $this->single();
            $targetVal = (float)($row->target_val ?? 0);
            $achVal = (float)($row->ach_val ?? 0);
            $pct = ($targetVal > 0) ? round(($achVal / $targetVal) * 100, 1) : 100.0;
            return ['value' => $pct, 'label' => $pct . '% Target Completion', 'achieved' => $achVal, 'target' => $targetVal];
        }

        $this->query("SELECT tc.name AS label, SUM(ti.planned_value) AS val FROM target_items ti JOIN target_categories tc ON ti.category_id = tc.category_id GROUP BY tc.name");
        $rows = $this->resultSet();
        return [
            'labels' => array_column($rows, 'label'),
            'series' => array_map('floatval', array_column($rows, 'val'))
        ];
    }

    private function aggregateTasksData(string $type, string $metric, string $agg, string $groupBy): array {
        if ($type === 'kpi') {
            $this->query("SELECT COUNT(*) AS val FROM tasks WHERE status != 'completed'");
            $val = (int)($this->single()->val ?? 0);
            return ['value' => $val, 'label' => number_format($val) . ' Pending Tasks'];
        }

        $this->query("SELECT status AS label, COUNT(*) AS val FROM tasks GROUP BY status");
        $rows = $this->resultSet();
        return [
            'labels' => array_column($rows, 'label'),
            'series' => array_map('intval', array_column($rows, 'val'))
        ];
    }

    private function aggregateCustomersData(string $type, string $metric, string $agg, string $groupBy): array {
        if ($type === 'kpi') {
            $col = ($metric === 'contract_value') ? 'contract_value' : 'customer_id';
            $fn = ($metric === 'contract_value') ? 'SUM' : 'COUNT';
            $this->query("SELECT COALESCE({$fn}({$col}), 0) AS val FROM customers");
            $val = (float)($this->single()->val ?? 0);
            return ['value' => round($val, 2), 'label' => ($metric === 'contract_value' ? '$' . number_format($val, 2) : number_format($val))];
        }

        $this->query("SELECT status AS label, COUNT(*) AS val FROM customers GROUP BY status");
        $rows = $this->resultSet();
        return [
            'labels' => array_column($rows, 'label'),
            'series' => array_map('intval', array_column($rows, 'val'))
        ];
    }

    private function aggregateWebsiteAnalyticsData(string $type, string $metric, string $agg, string $groupBy): array {
        if ($type === 'kpi') {
            $this->query("SELECT COALESCE(SUM(pageviews), 0) AS val FROM website_analytics_snapshots");
            $val = (int)($this->single()->val ?? 0);
            return ['value' => $val, 'label' => number_format($val) . ' Pageviews'];
        }

        $this->query("SELECT snapshot_date AS label, pageviews AS val FROM website_analytics_snapshots ORDER BY snapshot_date DESC LIMIT 7");
        $rows = array_reverse($this->resultSet());
        return [
            'labels' => array_column($rows, 'label'),
            'series' => array_map('intval', array_column($rows, 'val'))
        ];
    }
}
