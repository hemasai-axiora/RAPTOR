<?php
// Raptor CRM Communications Model

class Communication extends Model {
    public const CHANNELS = ['call', 'whatsapp', 'sms', 'email', 'social', 'other'];
    public const DIRECTIONS = ['made', 'received', 'missed', 'sent'];

    public function __construct() {
        parent::__construct();
        $this->ensureSchema();
    }

    private function ensureSchema() {
        try {
            $this->query("ALTER TABLE communications ADD COLUMN phone_number VARCHAR(100) NULL AFTER lead_id");
            $this->execute();
        } catch (Exception $e) {
            // Column exists
        }
        try {
            $this->query("UPDATE communications SET phone_number = TRIM(SUBSTRING_INDEX(note, 'for ', -1)) WHERE (phone_number IS NULL OR phone_number = '') AND note LIKE '%for %'");
            $this->execute();
        } catch (Exception $e) {
            // Backfill ignore
        }
    }

    public function getCommunications(array $filters = [], ?array $visibleUserIds = null) {
        [$where, $params] = $this->buildWhere($filters, $visibleUserIds);
        $this->query('SELECT c.*, u.name AS user_name, l.first_name, l.last_name, l.phone AS lead_phone, l.email AS lead_email, l.company_name AS lead_company_name
                      FROM communications c
                      LEFT JOIN users u ON c.user_id = u.user_id
                      LEFT JOIN leads l ON c.lead_id = l.lead_id
                      ' . $where . '
                      ORDER BY c.happened_at DESC, c.communication_id DESC');
        $this->bindParams($params);
        return $this->resultSet();
    }

    public function getForLead(int $leadId) {
        return $this->getCommunications(['lead_id' => $leadId], null);
    }

    public function update(int $id, array $data): bool {
        $this->query('UPDATE communications 
                      SET phone_number = :phone_number,
                          outcome = :outcome, 
                          note = :note, 
                          channel = :channel, 
                          direction = :direction, 
                          happened_at = :happened_at 
                      WHERE communication_id = :id');
        $this->bind(':id', $id);
        $this->bind(':phone_number', !empty($data['phone_number']) ? trim($data['phone_number']) : null);
        $this->bind(':outcome', $data['outcome'] ?? null);
        $this->bind(':note', $data['note'] ?? null);
        $this->bind(':channel', $this->valid($data['channel'] ?? 'call', self::CHANNELS, 'call'));
        $this->bind(':direction', $this->valid($data['direction'] ?? 'made', self::DIRECTIONS, 'made'));
        $this->bind(':happened_at', $data['happened_at'] ?? date('Y-m-d H:i:s'));
        return $this->execute();
    }

    public function add(array $data) {
        $this->query('INSERT INTO communications
            (lead_id, phone_number, user_id, channel, direction, duration_seconds, outcome, note, proof_url, happened_at)
            VALUES (:lead_id, :phone_number, :user_id, :channel, :direction, :duration, :outcome, :note, :proof_url, :happened_at)');
        $this->bind(':lead_id', $this->nullableInt($data['lead_id'] ?? null));
        $this->bind(':phone_number', !empty($data['phone_number']) ? trim($data['phone_number']) : null);
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
        [$where, $params] = $this->buildWhere(['communication_id' => $id], $visibleUserIds);
        $this->query('DELETE c FROM communications c ' . $where);
        $this->bindParams($params);
        return $this->execute();
    }

    public function deleteBulk(array $ids, ?array $visibleUserIds = null): int {
        if (empty($ids)) return 0;
        $deleted = 0;
        foreach ($ids as $id) {
            if ($this->delete((int)$id, $visibleUserIds)) {
                $deleted++;
            }
        }
        return $deleted;
    }

    public function findLeadByPhoneOrEmail(string $identifier) {
        $identifier = trim($identifier);
        if (empty($identifier)) return null;

        $db = Database::getInstance()->getConnection();
        if (is_numeric($identifier) && (int)$identifier > 0) {
            $stmt = $db->prepare('SELECT lead_id FROM leads WHERE lead_id = :id LIMIT 1');
            $stmt->execute([':id' => (int)$identifier]);
            $lead = $stmt->fetch(PDO::FETCH_OBJ);
            if ($lead) return (int)$lead->lead_id;
        }

        $cleanPhone = preg_replace('/[^0-9]/', '', $identifier);
        if (strlen($cleanPhone) >= 7) {
            $stmt = $db->prepare('SELECT lead_id FROM leads WHERE REPLACE(REPLACE(REPLACE(phone, "-", ""), " ", ""), "+", "") LIKE :p LIMIT 1');
            $stmt->execute([':p' => '%' . $cleanPhone . '%']);
            $lead = $stmt->fetch(PDO::FETCH_OBJ);
            if ($lead) return (int)$lead->lead_id;
        }

        if (strpos($identifier, '@') !== false) {
            $stmt = $db->prepare('SELECT lead_id FROM leads WHERE LOWER(email) = LOWER(:e) LIMIT 1');
            $stmt->execute([':e' => $identifier]);
            $lead = $stmt->fetch(PDO::FETCH_OBJ);
            if ($lead) return (int)$lead->lead_id;
        }

        return null;
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
                $params[':' . $field] = strtolower($filters[$field]);
            }
        }

        if (!empty($filters['outcome'])) {
            $where[] = 'c.outcome LIKE :outcome';
            $params[':outcome'] = '%' . $filters['outcome'] . '%';
        }

        if (!empty($filters['search'])) {
            $where[] = '(l.first_name LIKE :search OR l.last_name LIKE :search OR l.phone LIKE :search OR l.email LIKE :search OR c.outcome LIKE :search OR c.note LIKE :search)';
            $params[':search'] = '%' . $filters['search'] . '%';
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
