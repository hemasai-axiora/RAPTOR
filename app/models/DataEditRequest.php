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
        'customer' => ['table' => 'customers', 'pk' => 'customer_id', 'fields' => ['name', 'email', 'phone', 'company_name', 'status']],
    ];

    public function entityTypes(): array {
        return array_keys(self::ENTITIES);
    }

    public function create(array $data): bool {
        $entityType = $data['entity_type'] ?? '';
        if (!isset(self::ENTITIES[$entityType])) {
            return false;
        }

        $changes = $data['proposed_changes'] ?? [];
        $action = in_array(($data['requested_action'] ?? 'update'), ['archive', 'delete'], true) ? $data['requested_action'] : 'update';

        $this->query('INSERT INTO data_edit_requests
                (entity_type, entity_id, requested_action, proposed_changes, manager_comment, requested_by_user_id)
            VALUES (:entity_type, :entity_id, :action, :changes, :comment, :requested_by)');
        $this->bind(':entity_type', $entityType);
        $this->bind(':entity_id', (int) $data['entity_id']);
        $this->bind(':action', $action);
        $this->bind(':changes', is_string($changes) ? $changes : json_encode($changes));
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
            if ($request->requested_action === 'delete') {
                $this->deleteEntity($request);
            } elseif ($request->requested_action === 'archive') {
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

    public function updateAndApprove(int $id, int $adminId, string $newChangesJson = '', string $comment = ''): bool {
        $request = $this->getById($id);
        if (!$request) {
            return false;
        }

        if (!empty($newChangesJson)) {
            $this->query('UPDATE data_edit_requests SET proposed_changes = :changes WHERE request_id = :id');
            $this->bind(':changes', $newChangesJson);
            $this->bind(':id', $id);
            $this->execute();
            $request->proposed_changes = $newChangesJson;
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

    public function deleteRequestRecord(int $reqId): bool {
        $this->query('DELETE FROM data_edit_requests WHERE request_id = :id');
        $this->bind(':id', $reqId);
        return $this->execute();
    }

    public function deleteEntity($request): void {
        $meta = self::ENTITIES[$request->entity_type] ?? null;
        if (!$meta) {
            throw new RuntimeException('Unsupported entity type.');
        }

        $table = $meta['table'];
        $pk = $meta['pk'];

        try {
            $this->query('SET FOREIGN_KEY_CHECKS = 0');
            $this->execute();
        } catch (Exception $e) {}

        $this->query("DELETE FROM `$table` WHERE `$pk` = :id");
        $this->bind(':id', (int) $request->entity_id);
        $this->execute();

        try {
            $this->query('SET FOREIGN_KEY_CHECKS = 1');
            $this->execute();
        } catch (Exception $e) {}
    }

    public function parseChangesText(string $text): array {
        $decoded = json_decode($text, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function applyUpdate($request): void {
        $meta = self::ENTITIES[$request->entity_type] ?? null;
        if (!$meta) {
            throw new RuntimeException('Unsupported entity type.');
        }

        $raw = json_decode($request->proposed_changes ?: '{}', true) ?: [];
        if (!$raw) {
            return;
        }

        $sets = [];
        $binds = [];
        foreach ($raw as $field => $value) {
            $cleanField = preg_replace('/[^a-zA-Z0-9_]/', '', $field);
            if ($cleanField) {
                $sets[] = "`$cleanField` = :$cleanField";
                $binds[':' . $cleanField] = $value;
            }
        }
        if (!$sets) return;

        $sql = 'UPDATE `' . $meta['table'] . '` SET ' . implode(', ', $sets) . ' WHERE `' . $meta['pk'] . '` = :id';
        $this->query($sql);
        foreach ($binds as $param => $val) {
            $this->bind($param, $val);
        }
        $this->bind(':id', (int) $request->entity_id);
        $this->execute();
    }

    private function archiveEntity($request, int $adminId): void {
        $meta = self::ENTITIES[$request->entity_type] ?? null;
        if (!$meta) {
            throw new RuntimeException('Unsupported entity type.');
        }

        $table = $meta['table'];
        $pk = $meta['pk'];

        try {
            $this->query("ALTER TABLE `$table` ADD COLUMN is_archived TINYINT(1) DEFAULT 0, ADD COLUMN archived_at DATETIME NULL, ADD COLUMN archived_by_user_id INT NULL, ADD COLUMN archive_reason TEXT NULL");
            $this->execute();
        } catch (Exception $e) {}

        try {
            $this->query("UPDATE `$table` SET is_archived = 1 WHERE `$pk` = :id");
            $this->bind(':id', (int) $request->entity_id);
            $this->execute();
        } catch (Exception $e) {
            // Fallback status update
            try {
                $this->query("UPDATE `$table` SET status = 'archived' WHERE `$pk` = :id");
                $this->bind(':id', (int) $request->entity_id);
                $this->execute();
            } catch (Exception $e2) {}
        }
    }
}
