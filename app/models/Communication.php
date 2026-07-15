<?php
// Raptor CRM Communications Model

class Communication extends Model {
    public const CHANNELS = ['call', 'whatsapp', 'sms', 'email', 'social', 'other'];
    public const DIRECTIONS = ['made', 'received', 'missed', 'sent'];

    public function getCommunications(array $filters = [], ?array $visibleUserIds = null) {
        [$where, $params] = $this->buildWhere($filters, $visibleUserIds);
        $this->query('SELECT c.*, u.name AS user_name, l.first_name, l.last_name, l.company_name AS lead_company_name
                      FROM communications c
                      JOIN users u ON c.user_id = u.user_id
                      LEFT JOIN leads l ON c.lead_id = l.lead_id
                      ' . $where . '
                      ORDER BY c.happened_at DESC, c.communication_id DESC');
        $this->bindParams($params);
        return $this->resultSet();
    }

    public function getForLead(int $leadId) {
        return $this->getCommunications(['lead_id' => $leadId], null);
    }

    public function add(array $data) {
        $this->query('INSERT INTO communications
            (lead_id, user_id, channel, direction, duration_seconds, outcome, note, proof_url, happened_at)
            VALUES (:lead_id, :user_id, :channel, :direction, :duration, :outcome, :note, :proof_url, :happened_at)');
        $this->bind(':lead_id', $this->nullableInt($data['lead_id'] ?? null));
        $this->bind(':user_id', (int) $data['user_id']);
        $this->bind(':channel', $this->valid($data['channel'] ?? 'call', self::CHANNELS, 'call'));
        $this->bind(':direction', $this->valid($data['direction'] ?? 'made', self::DIRECTIONS, 'made'));
        $this->bind(':duration', max(0, (int) ($data['duration_seconds'] ?? 0)));
        $this->bind(':outcome', $data['outcome'] ?? null);
        $this->bind(':note', $data['note'] ?? null);
        $this->bind(':proof_url', $data['proof_url'] ?? null);
        $this->bind(':happened_at', $data['happened_at']);

        if ($this->execute()) {
            return (int) $this->lastInsertId();
        }
        return false;
    }

    public function delete(int $id, ?array $visibleUserIds = null): bool {
        return false;
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
                $where[] = 'c.user_id IN (' . implode(',', $keys) . ')';
            }
        }

        foreach (['communication_id', 'lead_id', 'user_id', 'channel', 'direction'] as $field) {
            if (isset($filters[$field]) && $filters[$field] !== '') {
                $where[] = 'c.' . $field . ' = :' . $field;
                $params[':' . $field] = $filters[$field];
            }
        }

        if (!empty($filters['date_from'])) {
            $where[] = 'c.happened_at >= :date_from';
            $params[':date_from'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[] = 'c.happened_at <= :date_to';
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
