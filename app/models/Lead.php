<?php
// Raptor CRM Lead Model

class Lead extends Model {
    public const STATUSES = ['new', 'contacted', 'qualified', 'proposal', 'converted', 'lost'];
    public const QUALITIES = ['hot', 'warm', 'cold'];
    public const PRIORITIES = ['low', 'medium', 'high', 'urgent'];

    public function getLeads(array $filters = [], ?array $visibleUserIds = null) {
        [$where, $params] = $this->buildLeadWhere($filters, $visibleUserIds);

        $sql = 'SELECT l.*, l.company_name AS lead_company_name, c.company_name AS client_company_name,
                       u.name AS assignee_name, t.name AS team_name, p.name AS product_name,
                       TIMESTAMPDIFF(DAY, l.created_at, NOW()) AS ageing_days,
                       (SELECT created_at FROM follow_ups WHERE lead_id = l.lead_id AND status = \'scheduled\' ORDER BY due_at ASC LIMIT 1) AS next_follow_up_created_at
                FROM leads l
                LEFT JOIN clients c ON l.client_id = c.client_id
                LEFT JOIN users u ON l.assigned_to_user_id = u.user_id
                LEFT JOIN teams t ON l.team_id = t.team_id
                LEFT JOIN products p ON l.product_id = p.product_id
                ' . $where . '
                ORDER BY l.created_at DESC';

        $this->query($sql);
        $this->bindParams($params);
        return $this->resultSet();
    }

    public function getPipeline(?array $visibleUserIds = null) {
        $leads = $this->getLeads([], $visibleUserIds);
        $pipeline = [];
        foreach (self::STATUSES as $status) {
            $pipeline[$status] = [];
        }
        foreach ($leads as $lead) {
            $pipeline[$lead->status][] = $lead;
        }
        return $pipeline;
    }

    public function getLeadById($id, ?array $visibleUserIds = null) {
        $filters = ['lead_id' => (int) $id];
        $rows = $this->getLeads($filters, $visibleUserIds);
        return $rows[0] ?? false;
    }

    public function getProducts() {
        $this->query('SELECT * FROM products WHERE status = "active" ORDER BY name ASC');
        return $this->resultSet();
    }

    public function getSources() {
        $this->query('SELECT * FROM lead_sources WHERE status = "active" ORDER BY name ASC');
        return $this->resultSet();
    }

    public function getStatusHistory($leadId) {
        $this->query('SELECT h.*, u.name AS changed_by_name
                      FROM lead_status_history h
                      LEFT JOIN users u ON h.changed_by_user_id = u.user_id
                      WHERE h.lead_id = :lead_id
                      ORDER BY h.changed_at DESC');
        $this->bind(':lead_id', (int) $leadId);
        return $this->resultSet();
    }

    public function getAssignmentHistory($leadId) {
        $this->query('SELECT a.*, fu.name AS from_user_name, tu.name AS to_user_name, byu.name AS assigned_by_name
                      FROM lead_assignments a
                      LEFT JOIN users fu ON a.from_user_id = fu.user_id
                      LEFT JOIN users tu ON a.to_user_id = tu.user_id
                      LEFT JOIN users byu ON a.assigned_by_user_id = byu.user_id
                      WHERE a.lead_id = :lead_id
                      ORDER BY a.assigned_at DESC');
        $this->bind(':lead_id', (int) $leadId);
        return $this->resultSet();
    }

    public function findDuplicates(?string $phone, ?string $email, ?int $excludeId = null) {
        $conditions = [];
        $params = [];
        if ($phone !== null && trim($phone) !== '') {
            $conditions[] = 'phone = :phone';
            $params[':phone'] = trim($phone);
        }
        if ($email !== null && trim($email) !== '') {
            $conditions[] = 'email = :email';
            $params[':email'] = trim($email);
        }
        if (!$conditions) {
            return [];
        }

        $sql = 'SELECT lead_id, first_name, last_name, email, phone, status, created_at
                FROM leads
                WHERE (' . implode(' OR ', $conditions) . ')';
        if ($excludeId) {
            $sql .= ' AND lead_id <> :exclude_id';
            $params[':exclude_id'] = (int) $excludeId;
        }
        $sql .= ' ORDER BY created_at DESC LIMIT 5';

        $this->query($sql);
        $this->bindParams($params);
        return $this->resultSet();
    }

    public function addLead($data) {
        $this->query('INSERT INTO leads
            (client_id, assigned_to_user_id, team_id, first_name, last_name, company_name, email, phone,
             status, lead_quality, conversion_probability, probability, lead_value, lead_source,
             campaign_source, product_id, location, priority, next_follow_up_at, lost_reason, converted_at)
            VALUES
            (:client_id, :assigned_to_user_id, :team_id, :first_name, :last_name, :company_name, :email, :phone,
             :status, :lead_quality, :conversion_probability, :probability, :lead_value, :lead_source,
             :campaign_source, :product_id, :location, :priority, :next_follow_up_at, :lost_reason, :converted_at)');

        $this->bindLeadFields($data);

        if ($this->execute()) {
            $leadId = (int) $this->lastInsertId();
            $this->logStatus($leadId, null, $data['status'], $data['changed_by_user_id'] ?? null, 'Lead created');
            if (!empty($data['assigned_to_user_id'])) {
                $this->logAssignment($leadId, null, (int) $data['assigned_to_user_id'], $data['changed_by_user_id'] ?? null, 'Initial assignment');
            }
            $this->logJourneyForStatus($leadId, null, $data['status']);
            return $leadId;
        }
        return false;
    }

    public function updateLead($data) {
        $currentLead = $this->getLeadById($data['lead_id']);
        if (!$currentLead) {
            return false;
        }

        $this->query('UPDATE leads
            SET client_id = :client_id, assigned_to_user_id = :assigned_to_user_id, team_id = :team_id,
                first_name = :first_name, last_name = :last_name, company_name = :company_name,
                email = :email, phone = :phone, status = :status, lead_quality = :lead_quality,
                conversion_probability = :conversion_probability, probability = :probability,
                lead_value = :lead_value, lead_source = :lead_source, campaign_source = :campaign_source,
                product_id = :product_id, location = :location, priority = :priority,
                next_follow_up_at = :next_follow_up_at, lost_reason = :lost_reason, converted_at = :converted_at
            WHERE lead_id = :lead_id');

        $this->bind(':lead_id', (int) $data['lead_id']);
        $this->bindLeadFields($data);

        if ($this->execute()) {
            $actorId = $data['changed_by_user_id'] ?? null;
            if ($currentLead->status !== $data['status']) {
                $this->logStatus((int) $data['lead_id'], $currentLead->status, $data['status'], $actorId, $data['history_note'] ?? null);
                $this->logJourneyForStatus((int) $data['lead_id'], $currentLead->status, $data['status']);
            }
            if ((string) $currentLead->assigned_to_user_id !== (string) ($data['assigned_to_user_id'] ?? '')) {
                $this->logAssignment(
                    (int) $data['lead_id'],
                    $currentLead->assigned_to_user_id ? (int) $currentLead->assigned_to_user_id : null,
                    !empty($data['assigned_to_user_id']) ? (int) $data['assigned_to_user_id'] : null,
                    $actorId,
                    $data['assignment_note'] ?? null
                );
            }
            return true;
        }
        return false;
    }

    public function moveStatus($leadId, $status, $actorId = null, ?array $visibleUserIds = null) {
        if (!in_array($status, self::STATUSES, true)) {
            return false;
        }

        $lead = $this->getLeadById($leadId, $visibleUserIds);
        if (!$lead || $lead->status === $status) {
            return $lead ? true : false;
        }

        $convertedAt = $status === 'converted' ? date('Y-m-d H:i:s') : $lead->converted_at;
        $this->query('UPDATE leads SET status = :status, converted_at = :converted_at WHERE lead_id = :lead_id');
        $this->bind(':status', $status);
        $this->bind(':converted_at', $convertedAt);
        $this->bind(':lead_id', (int) $leadId);

        if ($this->execute()) {
            $this->logStatus((int) $leadId, $lead->status, $status, $actorId, 'Pipeline move');
            $this->logJourneyForStatus((int) $leadId, $lead->status, $status);
            return true;
        }
        return false;
    }

    public function deleteLead($id, ?array $visibleUserIds = null) {
        return false;
    }

    private function buildLeadWhere(array $filters, ?array $visibleUserIds): array {
        $where = [];
        $params = [];

        if ($visibleUserIds !== null) {
            if (!$visibleUserIds) {
                $where[] = '1 = 0';
            } else {
                $placeholders = [];
                foreach (array_values($visibleUserIds) as $i => $id) {
                    $key = ':visible_' . $i;
                    $placeholders[] = $key;
                    $params[$key] = (int) $id;
                }
                $where[] = 'l.assigned_to_user_id IN (' . implode(',', $placeholders) . ')';
            }
        }

        foreach (['status', 'lead_quality', 'lead_source', 'assigned_to_user_id', 'team_id', 'lead_id'] as $field) {
            if (isset($filters[$field]) && $filters[$field] !== '') {
                $where[] = 'l.' . $field . ' = :' . $field;
                $params[':' . $field] = $filters[$field];
            }
        }

        if (!empty($filters['ageing'])) {
            if ($filters['ageing'] === '7') {
                $where[] = 'l.created_at <= DATE_SUB(NOW(), INTERVAL 7 DAY)';
            } elseif ($filters['ageing'] === '30') {
                $where[] = 'l.created_at <= DATE_SUB(NOW(), INTERVAL 30 DAY)';
            }
        }

        return [$where ? 'WHERE ' . implode(' AND ', $where) : '', $params];
    }

    private function bindLeadFields(array $data): void {
        $probability = $this->decimal($data['probability'] ?? $data['conversion_probability'] ?? 0);
        $convertedAt = $data['status'] === 'converted'
            ? ($data['converted_at'] ?: date('Y-m-d H:i:s'))
            : ($data['converted_at'] ?: null);

        $this->bind(':client_id', $this->nullableInt($data['client_id'] ?? null));
        $this->bind(':assigned_to_user_id', $this->nullableInt($data['assigned_to_user_id'] ?? null));
        $this->bind(':team_id', $this->nullableInt($data['team_id'] ?? null));
        $this->bind(':first_name', $data['first_name']);
        $this->bind(':last_name', $data['last_name'] ?? null);
        $this->bind(':company_name', $data['company_name'] ?? null);
        $this->bind(':email', (!isset($data['email']) || trim($data['email']) === '') ? null : trim($data['email']));
        $this->bind(':phone', (!isset($data['phone']) || trim($data['phone']) === '') ? null : trim($data['phone']));
        $this->bind(':status', $data['status']);
        $this->bind(':lead_quality', $data['lead_quality']);
        $this->bind(':conversion_probability', $probability);
        $this->bind(':probability', $probability);
        $this->bind(':lead_value', $this->decimal($data['lead_value'] ?? 0));
        $this->bind(':lead_source', $data['lead_source']);
        $this->bind(':campaign_source', $data['campaign_source'] ?? null);
        $this->bind(':product_id', $this->nullableInt($data['product_id'] ?? null));
        $this->bind(':location', $data['location'] ?? null);
        $this->bind(':priority', $data['priority'] ?? 'medium');
        $this->bind(':next_follow_up_at', $data['next_follow_up_at'] ?: null);
        $this->bind(':lost_reason', $data['lost_reason'] ?? null);
        $this->bind(':converted_at', $convertedAt);
    }

    private function bindParams(array $params): void {
        foreach ($params as $key => $value) {
            $this->bind($key, $value);
        }
    }

    private function nullableInt($value): ?int {
        return ($value === '' || $value === null) ? null : (int) $value;
    }

    private function decimal($value): string {
        return number_format(max(0, (float) $value), 2, '.', '');
    }

    private function logStatus(int $leadId, ?string $from, string $to, $actorId = null, ?string $note = null): void {
        $this->query('INSERT INTO lead_status_history (lead_id, from_status, to_status, changed_by_user_id, note)
                      VALUES (:lead_id, :from_status, :to_status, :actor, :note)');
        $this->bind(':lead_id', $leadId);
        $this->bind(':from_status', $from);
        $this->bind(':to_status', $to);
        $this->bind(':actor', $this->nullableInt($actorId));
        $this->bind(':note', $note);
        $this->execute();
    }

    private function logAssignment(int $leadId, ?int $from, ?int $to, $actorId = null, ?string $note = null): void {
        $this->query('INSERT INTO lead_assignments (lead_id, from_user_id, to_user_id, assigned_by_user_id, note)
                      VALUES (:lead_id, :from_user_id, :to_user_id, :actor, :note)');
        $this->bind(':lead_id', $leadId);
        $this->bind(':from_user_id', $from);
        $this->bind(':to_user_id', $to);
        $this->bind(':actor', $this->nullableInt($actorId));
        $this->bind(':note', $note);
        $this->execute();
    }

    private function getStageIdByName($name) {
        $this->query('SELECT stage_id FROM customer_journey_stages WHERE stage_name = :name');
        $this->bind(':name', $name);
        $row = $this->single();
        return $row ? $row->stage_id : null;
    }

    private function logJourneyForStatus(int $leadId, ?string $fromStatus, string $toStatus): void {
        $map = [
            'new' => 'Leads',
            'contacted' => 'Leads',
            'qualified' => 'Qualified',
            'proposal' => 'Qualified',
            'converted' => 'Customers',
            'lost' => 'Leads',
        ];
        $fromStageId = $fromStatus && isset($map[$fromStatus]) ? $this->getStageIdByName($map[$fromStatus]) : null;
        $toStageId = $this->getStageIdByName($map[$toStatus] ?? 'Leads');
        if (!$toStageId || ($fromStageId && $fromStageId === $toStageId)) {
            return;
        }
        $this->query('INSERT INTO customer_journey_log (lead_id, from_stage_id, to_stage_id)
                      VALUES (:lead_id, :from_stage_id, :to_stage_id)');
        $this->bind(':lead_id', $leadId);
        $this->bind(':from_stage_id', $fromStageId);
        $this->bind(':to_stage_id', $toStageId);
        $this->execute();
    }
}
