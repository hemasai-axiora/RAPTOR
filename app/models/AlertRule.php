<?php
// Sprint 13 - Rule-driven alert engine.

class AlertRule extends Model {
    public function getRules(): array {
        $this->query('SELECT * FROM alert_rules ORDER BY category ASC, name ASC');
        return $this->resultSet();
    }

    public function updateRules(array $rules): bool {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare('UPDATE alert_rules
                SET enabled = :enabled, severity = :severity, threshold_value = :threshold_value,
                    recipient_scope = :recipient_scope
                WHERE rule_key = :rule_key');
            foreach ($rules as $key => $rule) {
                $severity = in_array($rule['severity'] ?? '', ['info', 'warning', 'critical'], true) ? $rule['severity'] : 'warning';
                $scope = in_array($rule['recipient_scope'] ?? '', ['owner', 'manager', 'both', 'admin'], true) ? $rule['recipient_scope'] : 'owner';
                $stmt->execute([
                    ':enabled' => !empty($rule['enabled']) ? 1 : 0,
                    ':severity' => $severity,
                    ':threshold_value' => is_numeric($rule['threshold_value'] ?? null) ? $rule['threshold_value'] : null,
                    ':recipient_scope' => $scope,
                    ':rule_key' => $key,
                ]);
            }
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return false;
        }
    }

    public function runEnabledRules(): array {
        $this->query('SELECT * FROM alert_rules WHERE enabled = 1 ORDER BY rule_id ASC');
        $rules = $this->resultSet();
        $results = [];
        foreach ($rules as $rule) {
            $created = 0;
            foreach ($this->matchesForRule($rule) as $match) {
                foreach ($this->recipientsFor($rule->recipient_scope, (int) $match['owner_user_id']) as $recipientId) {
                    $created += $this->notify(
                        $recipientId,
                        $match['title'],
                        $match['message'],
                        $rule->category,
                        $rule->severity,
                        $match['action_url'],
                        $rule->rule_key . '-' . $match['dedupe_id'] . '-' . $recipientId
                    ) ? 1 : 0;
                }
            }
            $this->markRuleRun($rule->rule_key);
            $results[$rule->rule_key] = $created;
        }
        return $results;
    }

    private function matchesForRule($rule): array {
        $threshold = max(0, (float) $rule->threshold_value);
        switch ($rule->rule_key) {
            case 'late_login':
                return $this->rows("SELECT a.attendance_id AS dedupe_id, a.user_id AS owner_user_id,
                                           'Late login' AS title,
                                           CONCAT(u.name, ' checked in late today.') AS message,
                                           'index.php?route=attendance/index' AS action_url
                                    FROM attendance a JOIN users u ON a.user_id = u.user_id
                                    WHERE a.work_date = CURDATE() AND a.is_late = 1");

            case 'no_login':
                return $this->rows("SELECT u.user_id AS dedupe_id, u.user_id AS owner_user_id,
                                           'No login recorded' AS title,
                                           CONCAT(u.name, ' has not checked in today.') AS message,
                                           'index.php?route=dashboard/monitoring' AS action_url
                                    FROM users u
                                    JOIN roles r ON u.role_id = r.role_id
                                    LEFT JOIN attendance a ON a.user_id = u.user_id AND a.work_date = CURDATE()
                                    WHERE r.role_name IN ('employee','sales_person') AND u.status = 'active'
                                      AND a.attendance_id IS NULL
                                      AND TIME(NOW()) >= ADDTIME((SELECT COALESCE(MAX(setting_value),'09:30') FROM settings WHERE setting_key = 'attendance.shift_start'), SEC_TO_TIME(:seconds))",
                    [':seconds' => (int) ($threshold * 60)]);

            case 'missing_logout':
                return $this->rows("SELECT a.attendance_id AS dedupe_id, a.user_id AS owner_user_id,
                                           'Missing logout' AS title,
                                           CONCAT(u.name, ' has an open attendance session.') AS message,
                                           'index.php?route=attendance/approvals' AS action_url
                                    FROM attendance a JOIN users u ON a.user_id = u.user_id
                                    WHERE a.login_at IS NOT NULL AND a.logout_at IS NULL
                                      AND a.login_at <= DATE_SUB(NOW(), INTERVAL :hours HOUR)",
                    [':hours' => (int) max(1, $threshold)]);

            case 'location_disabled':
                return $this->rows("SELECT a.attendance_id AS dedupe_id, a.user_id AS owner_user_id,
                                           'Location not updating' AS title,
                                           CONCAT(u.name, ' is checked in but has no location ping today.') AS message,
                                           'index.php?route=dashboard/monitoring' AS action_url
                                    FROM attendance a JOIN users u ON a.user_id = u.user_id
                                    LEFT JOIN location_logs ll ON ll.user_id = a.user_id AND DATE(ll.captured_at) = a.work_date
                                    WHERE a.work_date = CURDATE() AND a.login_at IS NOT NULL AND a.logout_at IS NULL
                                    GROUP BY a.attendance_id HAVING COUNT(ll.loc_id) = 0");

            case 'target_not_updated':
                return $this->rows("SELECT tg.target_id AS dedupe_id, COALESCE(tg.owner_user_id, t.team_leader_user_id, t.manager_user_id) AS owner_user_id,
                                           'Target progress stale' AS title,
                                           CONCAT('Target #', tg.target_id, ' has stale or missing progress.') AS message,
                                           'index.php?route=targets/index' AS action_url
                                    FROM targets tg
                                    JOIN target_items ti ON tg.target_id = ti.target_id
                                    LEFT JOIN target_progress tp ON ti.target_item_id = tp.target_item_id
                                    LEFT JOIN teams t ON tg.team_id = t.team_id
                                    WHERE tg.status = 'approved'
                                      AND tg.start_date <= CURDATE() AND tg.end_date >= CURDATE()
                                      AND (tp.progress_id IS NULL OR tp.computed_at <= DATE_SUB(NOW(), INTERVAL :hours HOUR))
                                    GROUP BY tg.target_id",
                    [':hours' => (int) max(1, $threshold)]);

            case 'task_overdue':
                return $this->rows("SELECT t.task_id AS dedupe_id, t.assigned_to_user_id AS owner_user_id,
                                           'Task overdue' AS title,
                                           CONCAT(t.title, ' is past deadline.') AS message,
                                           'index.php?route=tasks/index' AS action_url
                                    FROM tasks t
                                    WHERE t.status <> 'completed' AND t.deadline < NOW()");

            case 'followup_due':
                return $this->rows("SELECT f.follow_up_id AS dedupe_id, f.assigned_to_user_id AS owner_user_id,
                                           'Follow-up due soon' AS title,
                                           CONCAT('Follow-up for ', l.first_name, ' ', COALESCE(l.last_name,''), ' is due soon.') AS message,
                                           'index.php?route=followups/index' AS action_url
                                    FROM follow_ups f JOIN leads l ON f.lead_id = l.lead_id
                                    WHERE f.status = 'scheduled'
                                      AND f.due_at BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL :minutes MINUTE)",
                    [':minutes' => (int) max(1, $threshold)]);

            case 'missed_followup':
                return $this->rows("SELECT f.follow_up_id AS dedupe_id, f.assigned_to_user_id AS owner_user_id,
                                           'Missed follow-up' AS title,
                                           CONCAT('Missed follow-up for ', l.first_name, ' ', COALESCE(l.last_name,''), '.') AS message,
                                           CONCAT('index.php?route=leads/view/', l.lead_id) AS action_url
                                    FROM follow_ups f JOIN leads l ON f.lead_id = l.lead_id
                                    WHERE f.status = 'missed'");

            case 'lead_unattended':
                return $this->rows("SELECT l.lead_id AS dedupe_id, l.assigned_to_user_id AS owner_user_id,
                                           'Lead unattended' AS title,
                                           CONCAT(l.first_name, ' ', COALESCE(l.last_name,''), ' has not moved from New.') AS message,
                                           CONCAT('index.php?route=leads/view/', l.lead_id) AS action_url
                                    FROM leads l
                                    WHERE l.status = 'new' AND l.assigned_to_user_id IS NOT NULL
                                      AND l.created_at <= DATE_SUB(NOW(), INTERVAL :hours HOUR)",
                    [':hours' => (int) max(1, $threshold)]);

            case 'meeting_reminder':
                return $this->rows("SELECT m.meeting_id AS dedupe_id, m.assigned_to_user_id AS owner_user_id,
                                           'Meeting/demo reminder' AS title,
                                           CONCAT(m.title, ' starts soon.') AS message,
                                           'index.php?route=meetings/index' AS action_url
                                    FROM meetings m
                                    WHERE m.status = 'scheduled'
                                      AND m.scheduled_start BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL :minutes MINUTE)",
                    [':minutes' => (int) max(1, $threshold)]);

            case 'low_performance':
                return $this->rows("SELECT ps.score_id AS dedupe_id, ps.user_id AS owner_user_id,
                                           'Low performance score' AS title,
                                           CONCAT(u.name, ' is at ', ps.overall_score, ' overall score.') AS message,
                                           CONCAT('index.php?route=performance/profile/', ps.user_id) AS action_url
                                    FROM performance_scores ps JOIN users u ON ps.user_id = u.user_id
                                    WHERE ps.period = 'weekly' AND ps.end_date >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
                                      AND ps.overall_score < :score",
                    [':score' => $threshold]);

            case 'approval_pending':
                return $this->rows("SELECT CONCAT('att-', a.attendance_id) AS dedupe_id, a.user_id AS owner_user_id,
                                           'Attendance approval pending' AS title,
                                           CONCAT(u.name, ' has a pending attendance exception.') AS message,
                                           'index.php?route=attendance/approvals' AS action_url
                                    FROM attendance a JOIN users u ON a.user_id = u.user_id
                                    WHERE a.attendance_status = 'Pending'
                                    UNION ALL
                                    SELECT CONCAT('task-', t.task_id) AS dedupe_id, t.assigned_to_user_id AS owner_user_id,
                                           'Task review pending' AS title,
                                           CONCAT(t.title, ' is waiting for review.') AS message,
                                           'index.php?route=tasks/index' AS action_url
                                    FROM tasks t WHERE t.review_status = 'pending_review'");

            case 'high_value_lead':
                return $this->rows("SELECT l.lead_id AS dedupe_id, l.assigned_to_user_id AS owner_user_id,
                                           'High-value lead pending' AS title,
                                           CONCAT(l.first_name, ' ', COALESCE(l.last_name,''), ' is worth ', l.lead_value, '.') AS message,
                                           CONCAT('index.php?route=leads/view/', l.lead_id) AS action_url
                                    FROM leads l
                                    WHERE l.status NOT IN ('converted','lost')
                                      AND l.assigned_to_user_id IS NOT NULL
                                      AND l.lead_value >= :value",
                    [':value' => $threshold]);

            case 'contact_sla_breach':
                return $this->rows("SELECT l.lead_id AS dedupe_id, l.assigned_to_user_id AS owner_user_id,
                                           'Contact SLA breached' AS title,
                                           CONCAT(l.first_name, ' ', COALESCE(l.last_name,''), ' has not been contacted in time.') AS message,
                                           CONCAT('index.php?route=leads/view/', l.lead_id) AS action_url
                                    FROM leads l
                                    WHERE l.status = 'new' AND l.assigned_to_user_id IS NOT NULL
                                      AND l.created_at <= DATE_SUB(NOW(), INTERVAL :hours HOUR)",
                    [':hours' => (int) max(1, $threshold)]);
        }

        return [];
    }

    private function recipientsFor(string $scope, int $ownerUserId): array {
        if ($ownerUserId <= 0) {
            return [];
        }
        if ($scope === 'admin') {
            return $this->adminIds();
        }

        $ids = [];
        if ($scope === 'owner' || $scope === 'both') {
            $ids[] = $ownerUserId;
        }
        if ($scope === 'manager' || $scope === 'both') {
            $ids = array_merge($ids, $this->managerIdsFor($ownerUserId));
        }
        return array_values(array_unique(array_filter(array_map('intval', $ids))));
    }

    private function notify(int $userId, string $title, string $message, string $category, string $severity, string $actionUrl, string $dedupeKey): bool {
        try {
            $this->query('INSERT IGNORE INTO notifications
                            (user_id, title, message, type, action_url, severity, category, dedupe_key)
                          VALUES (:uid, :title, :message, :type, :action_url, :severity, :category, :dedupe_key)');
            $this->bind(':uid', $userId);
            $this->bind(':title', $title);
            $this->bind(':message', $message);
            $this->bind(':type', $category);
            $this->bind(':action_url', $actionUrl);
            $this->bind(':severity', $severity);
            $this->bind(':category', $category);
            $this->bind(':dedupe_key', substr($dedupeKey, 0, 150));
            $this->execute();
            return $this->rowCount() > 0;
        } catch (Exception $e) {
            return false;
        }
    }

    private function rows(string $sql, array $params = []): array {
        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function managerIdsFor(int $ownerUserId): array {
        $rows = $this->rows('SELECT DISTINCT manager_id FROM (
                                SELECT e.reporting_manager_id AS manager_id
                                FROM employees e WHERE e.user_id = :uid AND e.reporting_manager_id IS NOT NULL
                                UNION
                                SELECT t.team_leader_user_id AS manager_id
                                FROM employees e JOIN teams t ON e.team_id = t.team_id
                                WHERE e.user_id = :uid2 AND t.team_leader_user_id IS NOT NULL
                                UNION
                                SELECT t.manager_user_id AS manager_id
                                FROM employees e JOIN teams t ON e.team_id = t.team_id
                                WHERE e.user_id = :uid3 AND t.manager_user_id IS NOT NULL
                            ) x WHERE manager_id IS NOT NULL AND manager_id <> :uid4', [
            ':uid' => $ownerUserId,
            ':uid2' => $ownerUserId,
            ':uid3' => $ownerUserId,
            ':uid4' => $ownerUserId,
        ]);
        return array_map('intval', array_column($rows, 'manager_id'));
    }

    private function adminIds(): array {
        $rows = $this->rows("SELECT u.user_id FROM users u JOIN roles r ON u.role_id = r.role_id WHERE r.role_name = 'admin' AND u.status = 'active'");
        return array_map('intval', array_column($rows, 'user_id'));
    }

    private function markRuleRun(string $ruleKey): void {
        $this->query('UPDATE alert_rules SET last_run_at = NOW() WHERE rule_key = :key');
        $this->bind(':key', $ruleKey);
        $this->execute();
    }
}
