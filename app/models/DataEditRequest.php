<?php
// Governed manager edit requests. Admins approve or reject; records are never deleted.

class DataEditRequest extends Model {
    private const ENTITIES = [
        'client' => ['table' => 'clients', 'pk' => 'client_id', 'fields' => ['company_name', 'email', 'phone', 'status', 'contract_start', 'contract_end', 'package_details', 'billing_address']],
        'campaign' => ['table' => 'campaigns', 'pk' => 'campaign_id', 'fields' => ['name', 'client_id', 'channel', 'budget', 'spend', 'revenue_influenced', 'start_date', 'end_date', 'status']],
        'lead' => ['table' => 'leads', 'pk' => 'lead_id', 'fields' => ['first_name', 'last_name', 'email', 'phone', 'status', 'lead_value', 'assigned_to_user_id']],
        'task' => ['table' => 'tasks', 'pk' => 'task_id', 'fields' => ['title', 'description', 'assigned_to_user_id', 'status', 'priority', 'deadline']],
        'invoice' => ['table' => 'invoices', 'pk' => 'invoice_id', 'fields' => ['status', 'amount', 'due_date']],
        'team' => ['table' => 'teams', 'pk' => 'team_id', 'fields' => ['name', 'manager_user_id', 'team_leader_user_id', 'status']],
        'employee' => ['table' => 'users', 'pk' => 'user_id', 'fields' => ['name', 'email', 'status']],
    ];

    public function entityTypes(): array {
        return array_keys(self::ENTITIES);
    }

    public function create(array $data): bool {
        $entityType = $data['entity_type'] ?? '';
        if (!isset(self::ENTITIES[$entityType])) {
            return false;
        }

        $changes = $this->sanitizeChanges($entityType, $data['proposed_changes'] ?? []);
        $action = ($data['requested_action'] ?? 'update') === 'archive' ? 'archive' : 'update';

        $this->query('INSERT INTO data_edit_requests
                (entity_type, entity_id, requested_action, proposed_changes, manager_comment, requested_by_user_id)
            VALUES (:entity_type, :entity_id, :action, :changes, :comment, :requested_by)');
        $this->bind(':entity_type', $entityType);
        $this->bind(':entity_id', (int) $data['entity_id']);
        $this->bind(':action', $action);
        $this->bind(':changes', json_encode($changes));
        $this->bind(':comment', trim($data['manager_comment'] ?? ''));
        $this->bind(':requested_by', (int) $data['requested_by_user_id']);
        return $this->execute();
    }

    public function all(string $status = 'pending'): array {
        $where = $status === 'all' ? '1=1' : 'der.status = :status';
        $this->query("SELECT der.*, requester.name AS requester_name, reviewer.name AS reviewer_name
            FROM data_edit_requests der
            JOIN users requester ON der.requested_by_user_id = requester.user_id
            LEFT JOIN users reviewer ON der.reviewed_by_user_id = reviewer.user_id
            WHERE $where
            ORDER BY der.requested_at DESC");
        if ($status !== 'all') {
            $this->bind(':status', $status);
        }
        return $this->resultSet();
    }

    public function pendingForManager(int $userId): array {
        $this->query('SELECT der.*, requester.name AS requester_name, reviewer.name AS reviewer_name
            FROM data_edit_requests der
            JOIN users requester ON der.requested_by_user_id = requester.user_id
            LEFT JOIN users reviewer ON der.reviewed_by_user_id = reviewer.user_id
            WHERE der.requested_by_user_id = :uid
            ORDER BY der.requested_at DESC');
        $this->bind(':uid', $userId);
        return $this->resultSet();
    }

    public function getById(int $id) {
        $this->query('SELECT * FROM data_edit_requests WHERE request_id = :id');
        $this->bind(':id', $id);
        return $this->single();
    }

    public function approve(int $id, int $adminId, string $comment = ''): bool {
        $request = $this->getById($id);
        if (!$request || $request->status !== 'pending') {
            return false;
        }

        $this->db->beginTransaction();
        try {
            if ($request->requested_action === 'archive') {
                $this->archiveEntity($request, $adminId);
            } else {
                $this->applyUpdate($request);
            }

            $this->query('UPDATE data_edit_requests
                SET status = "approved", reviewed_by_user_id = :admin_id, reviewed_comment = :comment, reviewed_at = NOW()
                WHERE request_id = :id');
            $this->bind(':admin_id', $adminId);
            $this->bind(':comment', trim($comment));
            $this->bind(':id', $id);
            $ok = $this->execute();
            $this->db->commit();
            return $ok;
        } catch (Exception $e) {
            $this->db->rollBack();
            return false;
        }
    }

    public function reject(int $id, int $adminId, string $comment = ''): bool {
        $this->query('UPDATE data_edit_requests
            SET status = "rejected", reviewed_by_user_id = :admin_id, reviewed_comment = :comment, reviewed_at = NOW()
            WHERE request_id = :id AND status = "pending"');
        $this->bind(':admin_id', $adminId);
        $this->bind(':comment', trim($comment));
        $this->bind(':id', $id);
        return $this->execute();
    }

    public function parseChangesText(string $text): array {
        $decoded = json_decode($text, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function sanitizeChanges(string $entityType, array $changes): array {
        $allowed = self::ENTITIES[$entityType]['fields'] ?? [];
        return array_intersect_key($changes, array_flip($allowed));
    }

    private function applyUpdate($request): void {
        $meta = self::ENTITIES[$request->entity_type] ?? null;
        if (!$meta) {
            throw new RuntimeException('Unsupported entity type.');
        }

        $changes = $this->sanitizeChanges($request->entity_type, json_decode($request->proposed_changes ?: '{}', true) ?: []);
        if (!$changes) {
            return;
        }

        $sets = [];
        foreach ($changes as $field => $value) {
            $sets[] = "`$field` = :$field";
        }
        $sql = 'UPDATE `' . $meta['table'] . '` SET ' . implode(', ', $sets) . ' WHERE `' . $meta['pk'] . '` = :id';
        $this->query($sql);
        foreach ($changes as $field => $value) {
            $this->bind(':' . $field, $value);
        }
        $this->bind(':id', (int) $request->entity_id);
        $this->execute();
    }

    private function archiveEntity($request, int $adminId): void {
        $meta = self::ENTITIES[$request->entity_type] ?? null;
        if (!$meta) {
            throw new RuntimeException('Unsupported entity type.');
        }

        $this->query('UPDATE `' . $meta['table'] . '` SET is_archived = 1, archived_at = NOW(), archived_by_user_id = :admin_id, archive_reason = :reason WHERE `' . $meta['pk'] . '` = :id');
        $this->bind(':admin_id', $adminId);
        $this->bind(':reason', $request->manager_comment);
        $this->bind(':id', (int) $request->entity_id);
        $this->execute();
    }
}
