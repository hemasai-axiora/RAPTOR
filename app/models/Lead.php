<?php
// Raptor CRM Lead Model

class Lead extends Model {
    public const STATUSES = ['new', 'contacted', 'qualified', 'proposal', 'converted', 'lost'];
    public const STAGE_ORDER = [
        'new' => 0,
        'contacted' => 1,
        'qualified' => 2,
        'proposal' => 3,
        'converted' => 4,
        'lost' => 5
    ];
    public const QUALITIES = ['hot', 'warm', 'cold'];
    public const PRIORITIES = ['low', 'medium', 'high', 'urgent'];

    public function generateLeadCode(): string {
        $year = date('Y');
        $this->query('SELECT MAX(lead_id) AS max_id FROM leads');
        $row = $this->single();
        $nextId = ($row && $row->max_id) ? ((int) $row->max_id + 1) : 1;
        return sprintf('LD-%s-%05d', $year, $nextId);
    }

    public function getLeads(array $filters = [], ?array $visibleUserIds = null) {
        [$where, $params] = $this->buildLeadWhere($filters, $visibleUserIds);

        $sql = 'SELECT l.*, l.company_name AS lead_company_name, c.company_name AS client_company_name,
                       u.name AS assignee_name, emp_u.name AS owner_employee_name, emp.job_title AS owner_job_title,
                       t.name AS team_name, p.name AS product_name,
                       TIMESTAMPDIFF(DAY, l.created_at, NOW()) AS ageing_days,
                       (SELECT created_at FROM follow_ups WHERE lead_id = l.lead_id AND status = \'scheduled\' ORDER BY due_at ASC LIMIT 1) AS next_follow_up_created_at
                FROM leads l
                LEFT JOIN clients c ON l.client_id = c.client_id
                LEFT JOIN users u ON l.assigned_to_user_id = u.user_id
                LEFT JOIN employees emp ON l.owner_employee_id = emp.employee_id
                LEFT JOIN users emp_u ON emp.user_id = emp_u.user_id
                LEFT JOIN teams t ON l.team_id = t.team_id
                LEFT JOIN products p ON l.product_id = p.product_id
                ' . $where . '
                ORDER BY l.created_at DESC';

        $this->query($sql);
        $this->bindParams($params);
        return $this->resultSet();
    }

    public function getAllLeadsForSelect(?array $visibleUserIds = null): array {
        $sql = 'SELECT l.lead_id, l.title, l.contact_name, l.first_name, l.last_name, l.company_name AS lead_company_name, c.company_name AS client_company_name
                FROM leads l
                LEFT JOIN clients c ON l.client_id = c.client_id';
        if (!empty($visibleUserIds)) {
            $ids = implode(',', array_map('intval', $visibleUserIds));
            $sql .= " WHERE l.assigned_to_user_id IN ({$ids}) OR l.user_id IN ({$ids})";
        }
        $sql .= ' ORDER BY l.created_at DESC LIMIT 200';
        $this->query($sql);
        $res = $this->resultSet() ?: [];
        if (empty($res)) {
            $this->query('SELECT l.lead_id, l.title, l.contact_name, l.first_name, l.last_name, l.company_name AS lead_company_name, c.company_name AS client_company_name FROM leads l LEFT JOIN clients c ON l.client_id = c.client_id ORDER BY l.created_at DESC LIMIT 200');
            $res = $this->resultSet() ?: [];
        }
        return $res;
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
        if (empty($data['lead_code'])) {
            $data['lead_code'] = $this->generateLeadCode();
        }

        // Sync owner_employee_id and assigned_to_user_id
        if (!empty($data['owner_employee_id']) && empty($data['assigned_to_user_id'])) {
            $this->query('SELECT user_id FROM employees WHERE employee_id = :emp_id');
            $this->bind(':emp_id', (int) $data['owner_employee_id']);
            $empRow = $this->single();
            if ($empRow && $empRow->user_id) {
                $data['assigned_to_user_id'] = (int) $empRow->user_id;
            }
        } elseif (!empty($data['assigned_to_user_id']) && empty($data['owner_employee_id'])) {
            $this->query('SELECT employee_id FROM employees WHERE user_id = :u_id');
            $this->bind(':u_id', (int) $data['assigned_to_user_id']);
            $empRow = $this->single();
            if ($empRow && $empRow->employee_id) {
                $data['owner_employee_id'] = (int) $empRow->employee_id;
            }
        }

        $this->query('INSERT INTO leads
            (lead_code, client_id, owner_employee_id, assigned_to_user_id, team_id, first_name, last_name, company_name, email, phone,
             status, lead_quality, conversion_probability, probability, lead_value, lead_source,
             campaign_source, product_id, location, priority, next_follow_up_at, lost_reason, converted_at)
            VALUES
            (:lead_code, :client_id, :owner_employee_id, :assigned_to_user_id, :team_id, :first_name, :last_name, :company_name, :email, :phone,
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

        if (empty($data['lead_code'])) {
            $data['lead_code'] = $currentLead->lead_code ?: $this->generateLeadCode();
        }

        // Sync owner_employee_id and assigned_to_user_id
        if (!empty($data['owner_employee_id']) && empty($data['assigned_to_user_id'])) {
            $this->query('SELECT user_id FROM employees WHERE employee_id = :emp_id');
            $this->bind(':emp_id', (int) $data['owner_employee_id']);
            $empRow = $this->single();
            if ($empRow && $empRow->user_id) {
                $data['assigned_to_user_id'] = (int) $empRow->user_id;
            }
        } elseif (!empty($data['assigned_to_user_id']) && empty($data['owner_employee_id'])) {
            $this->query('SELECT employee_id FROM employees WHERE user_id = :u_id');
            $this->bind(':u_id', (int) $data['assigned_to_user_id']);
            $empRow = $this->single();
            if ($empRow && $empRow->employee_id) {
                $data['owner_employee_id'] = (int) $empRow->employee_id;
            }
        }

        $this->query('UPDATE leads
            SET lead_code = :lead_code, client_id = :client_id, owner_employee_id = :owner_employee_id, assigned_to_user_id = :assigned_to_user_id, team_id = :team_id,
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

    public static function getValidNextStages(string $currentStage): array {
        $currentStage = strtolower(trim($currentStage));
        if ($currentStage === 'converted' || $currentStage === 'lost') {
            return [];
        }

        $valid = [];
        $order = self::STAGE_ORDER;

        if (isset($order[$currentStage])) {
            $currentIndex = $order[$currentStage];
            $stagesInSeq = ['new', 'contacted', 'qualified', 'proposal', 'converted'];
            if ($currentIndex < 4 && isset($stagesInSeq[$currentIndex + 1])) {
                $valid[] = $stagesInSeq[$currentIndex + 1];
            }
            if ($currentStage !== 'lost') {
                $valid[] = 'lost';
            }
        }

        return array_values(array_unique($valid));
    }

    public function moveStatus($leadId, $status, $actorId = null, ?array $visibleUserIds = null) {
        if (!in_array($status, self::STATUSES, true)) {
            return false;
        }

        $lead = $this->getLeadById($leadId, $visibleUserIds);
        if (!$lead || $lead->status === $status) {
            return $lead ? true : false;
        }

        $validNextStages = self::getValidNextStages($lead->status);
        if (!in_array($status, $validNextStages, true)) {
            return false;
        }

        $convertedAt = $status === 'converted' ? date('Y-m-d H:i:s') : $lead->converted_at;
        $this->query('UPDATE leads SET status = :status, converted_at = :converted_at WHERE lead_id = :lead_id AND status = :old_status');
        $this->bind(':status', $status);
        $this->bind(':converted_at', $convertedAt);
        $this->bind(':lead_id', (int) $leadId);
        $this->bind(':old_status', $lead->status);

        if ($this->execute() && $this->rowCount() > 0) {
            $this->logStatus((int) $leadId, $lead->status, $status, $actorId, 'Pipeline move');
            $this->logJourneyForStatus((int) $leadId, $lead->status, $status);
            return true;
        }
        return false;
    }

    public function moveStatusWithRemarks($leadId, string $targetStatus, string $remarks, $actorId = null, ?array $visibleUserIds = null, ?string $customDate = null): array {
        $lead = $this->getLeadById($leadId, $visibleUserIds);
        if (!$lead) {
            return ['success' => false, 'message' => 'Lead not found or access denied.'];
        }

        if ($lead->status === $targetStatus) {
            return ['success' => false, 'message' => 'Lead is already in the selected stage.'];
        }

        $validNextStages = self::getValidNextStages($lead->status);
        if (!in_array($targetStatus, $validNextStages, true)) {
            return [
                'success' => false,
                'message' => sprintf('Invalid stage transition from "%s" to "%s". Only forward transitions are allowed.', strtoupper($lead->status), strtoupper($targetStatus))
            ];
        }

        if (trim($remarks) === '') {
            return ['success' => false, 'message' => 'Remarks/Notes are required for stage transitions.'];
        }

        $convertedAt = $targetStatus === 'converted' ? date('Y-m-d H:i:s') : $lead->converted_at;
        $this->query('UPDATE leads SET status = :status, converted_at = :converted_at WHERE lead_id = :lead_id AND status = :old_status');
        $this->bind(':status', $targetStatus);
        $this->bind(':converted_at', $convertedAt);
        $this->bind(':lead_id', (int) $leadId);
        $this->bind(':old_status', $lead->status);

        if ($this->execute() && $this->rowCount() > 0) {
            $this->logStatus((int) $leadId, $lead->status, $targetStatus, $actorId, $remarks, $customDate);
            $this->logJourneyForStatus((int) $leadId, $lead->status, $targetStatus);
            return [
                'success' => true,
                'message' => sprintf('Lead successfully moved from %s to %s.', strtoupper($lead->status), strtoupper($targetStatus)),
                'lead_id' => (int) $leadId,
                'from_status' => $lead->status,
                'to_status' => $targetStatus
            ];
        }

        return ['success' => false, 'message' => 'Failed to update stage. It may have been updated concurrently by another user.'];
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

        foreach (['status', 'lead_quality', 'lead_source', 'assigned_to_user_id', 'owner_employee_id', 'team_id', 'lead_id', 'lead_code'] as $field) {
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
        $convertedAt = ($data['status'] ?? '') === 'converted'
            ? (!empty($data['converted_at']) ? $data['converted_at'] : date('Y-m-d H:i:s'))
            : (!empty($data['converted_at']) ? $data['converted_at'] : null);

        $this->bind(':lead_code', !empty($data['lead_code']) ? $data['lead_code'] : null);
        $this->bind(':client_id', $this->nullableInt($data['client_id'] ?? null));
        $this->bind(':owner_employee_id', $this->nullableInt($data['owner_employee_id'] ?? null));
        $this->bind(':assigned_to_user_id', $this->nullableInt($data['assigned_to_user_id'] ?? null));
        $this->bind(':team_id', $this->nullableInt($data['team_id'] ?? null));
        $this->bind(':first_name', $data['first_name']);
        $this->bind(':last_name', (!isset($data['last_name']) || trim($data['last_name']) === '') ? null : trim($data['last_name']));
        $this->bind(':company_name', $data['company_name'] ?? null);
        $this->bind(':email', (!isset($data['email']) || trim($data['email']) === '') ? null : trim($data['email']));
        $this->bind(':phone', (!isset($data['phone']) || trim($data['phone']) === '') ? null : trim($data['phone']));
        $this->bind(':status', $data['status'] ?? 'new');
        $this->bind(':lead_quality', $data['lead_quality'] ?? 'warm');
        $this->bind(':conversion_probability', $probability);
        $this->bind(':probability', $probability);
        $this->bind(':lead_value', $this->decimal($data['lead_value'] ?? 0));
        $this->bind(':lead_source', $data['lead_source'] ?? 'Direct');
        $this->bind(':campaign_source', $data['campaign_source'] ?? null);
        $this->bind(':product_id', $this->nullableInt($data['product_id'] ?? null));
        $this->bind(':location', $data['location'] ?? null);
        $this->bind(':priority', $data['priority'] ?? 'medium');
        $this->bind(':next_follow_up_at', !empty($data['next_follow_up_at']) ? $data['next_follow_up_at'] : null);
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

    private function logStatus(int $leadId, ?string $from, string $to, $actorId = null, ?string $note = null, ?string $customDate = null): void {
        if ($customDate && strtotime($customDate) !== false) {
            $this->query('INSERT INTO lead_status_history (lead_id, from_status, to_status, changed_by_user_id, note, changed_at)
                          VALUES (:lead_id, :from_status, :to_status, :actor, :note, :changed_at)');
            $this->bind(':changed_at', date('Y-m-d H:i:s', strtotime($customDate)));
        } else {
            $this->query('INSERT INTO lead_status_history (lead_id, from_status, to_status, changed_by_user_id, note)
                          VALUES (:lead_id, :from_status, :to_status, :actor, :note)');
        }
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
