<?php
// Raptor CRM Meetings/Demos Model

class Meeting extends Model {
    public const TYPES = ['meeting', 'demo'];
    public const STATUSES = ['scheduled', 'checked_in', 'completed', 'cancelled'];

    public function getMeetings(array $filters = [], ?array $visibleUserIds = null) {
        [$where, $params] = $this->buildWhere($filters, $visibleUserIds);
        $this->query('SELECT m.*, u.name AS assignee_name, cu.name AS creator_name,
                             l.first_name, l.last_name, l.company_name AS lead_company_name
                      FROM meetings m
                      JOIN users u ON m.assigned_to_user_id = u.user_id
                      LEFT JOIN users cu ON m.created_by_user_id = cu.user_id
                      LEFT JOIN leads l ON m.lead_id = l.lead_id
                      ' . $where . '
                      ORDER BY m.scheduled_start ASC, m.meeting_id ASC');
        $this->bindParams($params);
        return $this->resultSet();
    }

    public function getForLead(int $leadId) {
        return $this->getMeetings(['lead_id' => $leadId], null);
    }

    public function getById(int $id, ?array $visibleUserIds = null) {
        $rows = $this->getMeetings(['meeting_id' => $id], $visibleUserIds);
        return $rows[0] ?? false;
    }

    public function getCheckins(int $meetingId) {
        $this->query('SELECT c.*, u.name AS user_name
                      FROM meeting_checkins c
                      JOIN users u ON c.user_id = u.user_id
                      WHERE c.meeting_id = :id
                      ORDER BY c.checked_at ASC');
        $this->bind(':id', $meetingId);
        return $this->resultSet();
    }

    public function add(array $data) {
        $this->query('INSERT INTO meetings
            (lead_id, assigned_to_user_id, created_by_user_id, type, title, scheduled_start,
             scheduled_end, location, status)
            VALUES
            (:lead_id, :assigned, :creator, :type, :title, :start, :end, :location, "scheduled")');
        $this->bind(':lead_id', $this->nullableInt($data['lead_id'] ?? null));
        $this->bind(':assigned', (int) $data['assigned_to_user_id']);
        $this->bind(':creator', $this->nullableInt($data['created_by_user_id'] ?? null));
        $this->bind(':type', $this->valid($data['type'] ?? 'meeting', self::TYPES, 'meeting'));
        $this->bind(':title', $data['title']);
        $this->bind(':start', $data['scheduled_start']);
        $this->bind(':end', $data['scheduled_end'] ?? null);
        $this->bind(':location', $data['location'] ?? null);

        if ($this->execute()) {
            return (int) $this->lastInsertId();
        }
        return false;
    }

    public function check(int $meetingId, int $userId, string $type, ?float $lat, ?float $lng, ?int $accuracy, ?string $selfieKey, ?array $visibleUserIds = null) {
        $meeting = $this->getById($meetingId, $visibleUserIds);
        if (!$meeting || !in_array($type, ['in', 'out'], true)) {
            return false;
        }

        $this->query('INSERT INTO meeting_checkins
            (meeting_id, user_id, type, lat, lng, accuracy_m, selfie_url, checked_at)
            VALUES (:meeting_id, :user_id, :type, :lat, :lng, :accuracy, :selfie, NOW())');
        $this->bind(':meeting_id', $meetingId);
        $this->bind(':user_id', $userId);
        $this->bind(':type', $type);
        $this->bind(':lat', $lat);
        $this->bind(':lng', $lng);
        $this->bind(':accuracy', $accuracy);
        $this->bind(':selfie', $selfieKey);

        if (!$this->execute()) {
            return false;
        }
        $checkinId = (int) $this->lastInsertId();

        $status = $type === 'in' ? 'checked_in' : $meeting->status;
        $this->query('UPDATE meetings SET status = :status WHERE meeting_id = :id AND status <> "completed"');
        $this->bind(':status', $status);
        $this->bind(':id', $meetingId);
        $this->execute();

        if ($lat !== null && $lng !== null) {
            $this->logMeetingPoint($userId, $lat, $lng, $accuracy);
        }

        return $checkinId;
    }

    public function complete(int $id, array $data, ?array $visibleUserIds = null) {
        if (!$this->getById($id, $visibleUserIds)) {
            return false;
        }
        $this->query('UPDATE meetings
                      SET status = "completed", outcome = :outcome, client_feedback = :feedback,
                          next_follow_up_at = :next_follow_up_at
                      WHERE meeting_id = :id');
        $this->bind(':outcome', $data['outcome'] ?? null);
        $this->bind(':feedback', $data['client_feedback'] ?? null);
        $this->bind(':next_follow_up_at', $data['next_follow_up_at'] ?? null);
        $this->bind(':id', $id);
        return $this->execute();
    }

    public function cancel(int $id, ?array $visibleUserIds = null) {
        if (!$this->getById($id, $visibleUserIds)) {
            return false;
        }
        $this->query('UPDATE meetings SET status = "cancelled" WHERE meeting_id = :id');
        $this->bind(':id', $id);
        return $this->execute();
    }

    private function logMeetingPoint(int $userId, float $lat, float $lng, ?int $accuracy): void {
        try {
            $this->query('INSERT INTO location_logs (user_id, captured_at, lat, lng, accuracy_m, source)
                          VALUES (:uid, NOW(), :lat, :lng, :acc, "meeting")');
            $this->bind(':uid', $userId);
            $this->bind(':lat', $lat);
            $this->bind(':lng', $lng);
            $this->bind(':acc', $accuracy);
            $this->execute();
        } catch (Exception $e) {
            // Meeting check-in remains authoritative even if location rollup is unavailable.
        }
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
                $where[] = 'm.assigned_to_user_id IN (' . implode(',', $keys) . ')';
            }
        }

        foreach (['meeting_id', 'lead_id', 'assigned_to_user_id', 'type', 'status'] as $field) {
            if (isset($filters[$field]) && $filters[$field] !== '') {
                $where[] = 'm.' . $field . ' = :' . $field;
                $params[':' . $field] = $filters[$field];
            }
        }
        if (!empty($filters['date_from'])) {
            $where[] = 'm.scheduled_start >= :date_from';
            $params[':date_from'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[] = 'm.scheduled_start <= :date_to';
            $params[':date_to'] = $filters['date_to'];
        }

        return [$where ? 'WHERE ' . implode(' AND ', $where) : '', $params];
    }

    private function bindParams(array $params): void {
        foreach ($params as $key => $value) {
            $this->bind($key, $value);
        }
    }

    private function nullableInt($value): ?int {
        return ($value === '' || $value === null) ? null : (int) $value;
    }

    private function valid(string $value, array $allowed, string $fallback): string {
        return in_array($value, $allowed, true) ? $value : $fallback;
    }
}
