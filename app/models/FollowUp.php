<?php
// Raptor CRM Follow-up Model

class FollowUp extends Model {
    public const CHANNELS = ['call', 'whatsapp', 'sms', 'email', 'meeting', 'demo', 'other'];
    public const STATUSES = ['scheduled', 'completed', 'missed', 'cancelled'];

    public function getFollowUps(array $filters = [], ?array $visibleUserIds = null) {
        [$where, $params] = $this->buildWhere($filters, $visibleUserIds);
        $this->query('SELECT f.*, l.first_name, l.last_name, l.company_name AS lead_company_name,
                             l.email AS lead_email, l.phone AS lead_phone, l.status AS lead_status,
                             u.name AS assignee_name, c.name AS creator_name
                      FROM follow_ups f
                      JOIN leads l ON f.lead_id = l.lead_id
                      JOIN users u ON f.assigned_to_user_id = u.user_id
                      LEFT JOIN users c ON f.created_by_user_id = c.user_id
                      ' . $where . '
                      ORDER BY f.due_at ASC, f.follow_up_id ASC');
        $this->bindParams($params);
        return $this->resultSet();
    }

    public function getTodayForUser(int $userId) {
        return $this->getFollowUps([
            'assigned_to_user_id' => $userId,
            'date_from' => date('Y-m-d 00:00:00'),
            'date_to' => date('Y-m-d 23:59:59'),
            'open_only' => true,
        ], [$userId]);
    }

    public function getById(int $id, ?array $visibleUserIds = null) {
        $rows = $this->getFollowUps(['follow_up_id' => $id], $visibleUserIds);
        return $rows[0] ?? false;
    }

    public function schedule(array $data) {
        $this->query('INSERT INTO follow_ups
            (lead_id, assigned_to_user_id, created_by_user_id, channel, due_at, note, status)
            VALUES (:lead_id, :assigned, :creator, :channel, :due_at, :note, "scheduled")');
        $this->bind(':lead_id', (int) $data['lead_id']);
        $this->bind(':assigned', (int) $data['assigned_to_user_id']);
        $this->bind(':creator', $data['created_by_user_id'] ?? null);
        $this->bind(':channel', $this->validChannel($data['channel'] ?? 'call'));
        $this->bind(':due_at', $data['due_at']);
        $this->bind(':note', $data['note'] ?? null);

        if ($this->execute()) {
            return (int) $this->lastInsertId();
        }
        return false;
    }

    public function complete(int $id, string $outcome, ?array $visibleUserIds = null) {
        if (!$this->getById($id, $visibleUserIds)) {
            return false;
        }
        $this->query('UPDATE follow_ups
                      SET status = "completed", completed_at = NOW(), outcome = :outcome
                      WHERE follow_up_id = :id');
        $this->bind(':outcome', $outcome);
        $this->bind(':id', $id);
        return $this->execute();
    }

    public function cancel(int $id, ?array $visibleUserIds = null) {
        if (!$this->getById($id, $visibleUserIds)) {
            return false;
        }
        $this->query('UPDATE follow_ups SET status = "cancelled" WHERE follow_up_id = :id');
        $this->bind(':id', $id);
        return $this->execute();
    }

    public function createAutoForLead(int $leadId, int $assignedToUserId, ?int $actorId, string $reason, string $dueAt) {
        if ($this->hasOpenForLead($leadId)) {
            return false;
        }
        return $this->schedule([
            'lead_id' => $leadId,
            'assigned_to_user_id' => $assignedToUserId,
            'created_by_user_id' => $actorId,
            'channel' => 'call',
            'due_at' => $dueAt,
            'note' => $reason,
        ]);
    }

    public function markOverdueAndNotify(): int {
        $this->query('SELECT f.*, l.first_name, l.last_name
                      FROM follow_ups f
                      JOIN leads l ON f.lead_id = l.lead_id
                      WHERE f.status = "scheduled" AND f.due_at < NOW()');
        $rows = $this->resultSet();

        foreach ($rows as $row) {
            $this->query('UPDATE follow_ups SET status = "missed" WHERE follow_up_id = :id AND status = "scheduled"');
            $this->bind(':id', (int) $row->follow_up_id);
            $this->execute();

            $leadName = trim($row->first_name . ' ' . ($row->last_name ?? ''));
            $this->notify(
                (int) $row->assigned_to_user_id,
                'Missed follow-up',
                'Follow-up for ' . $leadName . ' is overdue.',
                'followup',
                'warning',
                'index.php?route=followups/index',
                'followup-missed-' . (int) $row->follow_up_id
            );

            foreach ($this->getEscalationUsers((int) $row->assigned_to_user_id) as $leaderId) {
                $this->notify(
                    $leaderId,
                    'Team follow-up missed',
                    $leadName . ' has a missed follow-up.',
                    'followup',
                    'warning',
                    'index.php?route=leads/view/' . (int) $row->lead_id,
                    'followup-missed-leader-' . (int) $row->follow_up_id . '-' . $leaderId
                );
            }
        }

        return count($rows);
    }

    public function notifyDueToday(): int {
        $this->query('SELECT f.*, l.first_name, l.last_name
                      FROM follow_ups f
                      JOIN leads l ON f.lead_id = l.lead_id
                      WHERE f.status = "scheduled"
                        AND DATE(f.due_at) = CURDATE()
                        AND f.due_at >= NOW()');
        $rows = $this->resultSet();
        foreach ($rows as $row) {
            $leadName = trim($row->first_name . ' ' . ($row->last_name ?? ''));
            $this->notify(
                (int) $row->assigned_to_user_id,
                'Follow-up due today',
                'Follow-up for ' . $leadName . ' is due today.',
                'followup',
                'info',
                'index.php?route=followups/index',
                'followup-due-today-' . (int) $row->follow_up_id . '-' . date('Ymd')
            );
        }
        return count($rows);
    }

    public function escalateContactSla(): int {
        $slaHours = $this->getSettingInt('lead.contact_sla_hours', 24);
        $this->query('SELECT l.lead_id, l.assigned_to_user_id, l.first_name, l.last_name
                      FROM leads l
                      LEFT JOIN lead_status_history h
                        ON h.lead_id = l.lead_id AND h.to_status IN ("contacted","qualified","proposal","converted")
                      WHERE l.status = "new"
                        AND l.assigned_to_user_id IS NOT NULL
                        AND l.created_at <= DATE_SUB(NOW(), INTERVAL :sla HOUR)
                      GROUP BY l.lead_id
                      HAVING COUNT(h.history_id) = 0');
        $this->bind(':sla', $slaHours, PDO::PARAM_INT);
        $rows = $this->resultSet();
        $created = 0;

        foreach ($rows as $lead) {
            $leadName = trim($lead->first_name . ' ' . ($lead->last_name ?? ''));
            foreach ($this->getEscalationUsers((int) $lead->assigned_to_user_id) as $leaderId) {
                if ($this->recordEscalation((int) $lead->lead_id, (int) $lead->assigned_to_user_id, $leaderId, 'contact_sla_breach')) {
                    $created++;
                    $this->notify(
                        $leaderId,
                        'Lead contact SLA breached',
                        $leadName . ' has not been contacted within ' . $slaHours . ' hours.',
                        'lead_sla',
                        'critical',
                        'index.php?route=leads/view/' . (int) $lead->lead_id,
                        'lead-sla-' . (int) $lead->lead_id . '-' . $leaderId
                    );
                }
            }
        }

        return $created;
    }

    public function reclassifyLeadAgeing(): int {
        $this->query('UPDATE leads
                      SET lead_quality = CASE
                          WHEN status IN ("converted","qualified","proposal") THEN lead_quality
                          WHEN created_at <= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN "cold"
                          WHEN created_at <= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN "warm"
                          ELSE lead_quality
                      END
                      WHERE status IN ("new","contacted","lost")');
        $this->execute();
        return $this->rowCount();
    }

    private function hasOpenForLead(int $leadId): bool {
        $this->query('SELECT COUNT(*) AS c FROM follow_ups WHERE lead_id = :lead_id AND status = "scheduled"');
        $this->bind(':lead_id', $leadId);
        $row = $this->single();
        return $row && (int) $row->c > 0;
    }

    private function recordEscalation(int $leadId, int $assignedTo, int $leaderId, string $reason): bool {
        try {
            $this->query('INSERT INTO lead_sla_escalations (lead_id, assigned_to_user_id, escalated_to_user_id, reason)
                          VALUES (:lead_id, :assigned, :leader, :reason)');
            $this->bind(':lead_id', $leadId);
            $this->bind(':assigned', $assignedTo);
            $this->bind(':leader', $leaderId);
            $this->bind(':reason', $reason);
            return $this->execute();
        } catch (Exception $e) {
            return false;
        }
    }

    private function notify(int $userId, string $title, string $message, string $category, string $severity, string $actionUrl, string $dedupeKey): void {
        try {
            $this->query('INSERT IGNORE INTO notifications
                          (user_id, title, message, type, action_url, severity, category, dedupe_key)
                          VALUES (:user_id, :title, :message, :type, :action_url, :severity, :category, :dedupe_key)');
            $this->bind(':user_id', $userId);
            $this->bind(':title', $title);
            $this->bind(':message', $message);
            $this->bind(':type', $category);
            $this->bind(':action_url', $actionUrl);
            $this->bind(':severity', $severity);
            $this->bind(':category', $category);
            $this->bind(':dedupe_key', $dedupeKey);
            $this->execute();
        } catch (Exception $e) {
            // Notifications are best-effort; the state transition still matters.
        }
    }

    private function getEscalationUsers(int $assignedUserId): array {
        $this->query('SELECT DISTINCT manager_id FROM (
                          SELECT e.reporting_manager_id AS manager_id
                          FROM employees e
                          WHERE e.user_id = :uid AND e.reporting_manager_id IS NOT NULL
                          UNION
                          SELECT t.team_leader_user_id AS manager_id
                          FROM employees e JOIN teams t ON e.team_id = t.team_id
                          WHERE e.user_id = :uid2 AND t.team_leader_user_id IS NOT NULL
                          UNION
                          SELECT t.manager_user_id AS manager_id
                          FROM employees e JOIN teams t ON e.team_id = t.team_id
                          WHERE e.user_id = :uid3 AND t.manager_user_id IS NOT NULL
                      ) x
                      WHERE manager_id IS NOT NULL AND manager_id <> :uid4');
        $this->bind(':uid', $assignedUserId);
        $this->bind(':uid2', $assignedUserId);
        $this->bind(':uid3', $assignedUserId);
        $this->bind(':uid4', $assignedUserId);
        $this->execute();
        return array_map('intval', $this->stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    private function getSettingInt(string $key, int $default): int {
        $this->query('SELECT setting_value FROM settings WHERE setting_key = :k');
        $this->bind(':k', $key);
        $row = $this->single();
        return $row ? (int) $row->setting_value : $default;
    }

    private function buildWhere(array $filters, ?array $visibleUserIds): array {
        $where = [];
        $params = [];

        if ($visibleUserIds !== null) {
            if (!$visibleUserIds) {
                $where[] = '1 = 0';
            } else {
                $keys = [];
                foreach (array_values($visibleUserIds) as $i => $id) {
                    $key = ':visible_' . $i;
                    $keys[] = $key;
                    $params[$key] = (int) $id;
                }
                $where[] = 'f.assigned_to_user_id IN (' . implode(',', $keys) . ')';
            }
        }

        foreach (['follow_up_id', 'lead_id', 'assigned_to_user_id', 'status', 'channel'] as $field) {
            if (isset($filters[$field]) && $filters[$field] !== '') {
                $where[] = 'f.' . $field . ' = :' . $field;
                $params[':' . $field] = $filters[$field];
            }
        }

        if (!empty($filters['date_from'])) {
            $where[] = 'f.due_at >= :date_from';
            $params[':date_from'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[] = 'f.due_at <= :date_to';
            $params[':date_to'] = $filters['date_to'];
        }
        if (!empty($filters['open_only'])) {
            $where[] = 'f.status = "scheduled"';
        }

        return [$where ? 'WHERE ' . implode(' AND ', $where) : '', $params];
    }

    private function bindParams(array $params): void {
        foreach ($params as $key => $value) {
            $this->bind($key, $value);
        }
    }

    private function validChannel(string $channel): string {
        return in_array($channel, self::CHANNELS, true) ? $channel : 'call';
    }
}
