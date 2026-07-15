<?php
// Raptor CRM Target Planning Model

class Target extends Model {
    public const PERIODS = ['daily', 'weekly', 'monthly'];
    public const STATUSES = ['draft', 'pending_approval', 'approved', 'rejected'];

    public function getCategories() {
        $this->query('SELECT * FROM target_categories WHERE active = TRUE ORDER BY name ASC');
        return $this->resultSet();
    }

    public function getTargets(?array $visibleUserIds = null, array $filters = []) {
        [$where, $params] = $this->buildWhere($visibleUserIds, $filters);
        $this->query('SELECT t.*, ou.name AS owner_name, tm.name AS team_name, cu.name AS creator_name, au.name AS approver_name,
                             COUNT(ti.target_item_id) AS item_count,
                             COALESCE(SUM(ti.planned_value), 0) AS planned_total,
                             COALESCE(AVG(tp.completion_percent), 0) AS avg_completion
                      FROM targets t
                      LEFT JOIN users ou ON t.owner_user_id = ou.user_id
                      LEFT JOIN teams tm ON t.team_id = tm.team_id
                      LEFT JOIN users cu ON t.created_by_user_id = cu.user_id
                      LEFT JOIN users au ON t.approved_by_user_id = au.user_id
                      LEFT JOIN target_items ti ON t.target_id = ti.target_id
                      LEFT JOIN target_progress tp ON ti.target_item_id = tp.target_item_id
                      ' . $where . '
                      GROUP BY t.target_id
                      ORDER BY t.start_date DESC, t.target_id DESC');
        $this->bindParams($params);
        return $this->resultSet();
    }

    public function getTargetById(int $id, ?array $visibleUserIds = null) {
        $rows = $this->getTargets($visibleUserIds, ['target_id' => $id]);
        return $rows[0] ?? false;
    }

    public function getItems(int $targetId) {
        $this->query('SELECT ti.*, c.category_key, c.name AS category_name, c.unit,
                             p.name AS product_name, tr.name AS territory_name,
                             COALESCE(tp.achieved_value, 0) AS achieved_value,
                             COALESCE(tp.completion_percent, 0) AS completion_percent
                      FROM target_items ti
                      JOIN target_categories c ON ti.category_id = c.category_id
                      LEFT JOIN products p ON ti.product_id = p.product_id
                      LEFT JOIN territories tr ON ti.territory_id = tr.territory_id
                      LEFT JOIN target_progress tp ON ti.target_item_id = tp.target_item_id
                      WHERE ti.target_id = :id
                      ORDER BY c.name ASC');
        $this->bind(':id', $targetId);
        return $this->resultSet();
    }

    public function createTarget(array $data, array $items) {
        $this->query('INSERT INTO targets
            (owner_type, owner_user_id, team_id, period, start_date, end_date, status, created_by_user_id)
            VALUES (:owner_type, :owner_user_id, :team_id, :period, :start_date, :end_date, :status, :creator)');
        $ownerType = $data['owner_type'] === 'team' ? 'team' : 'employee';
        $this->bind(':owner_type', $ownerType);
        $this->bind(':owner_user_id', $ownerType === 'employee' ? (int) $data['owner_user_id'] : null);
        $this->bind(':team_id', $ownerType === 'team' ? (int) $data['team_id'] : null);
        $this->bind(':period', in_array($data['period'], self::PERIODS, true) ? $data['period'] : 'monthly');
        $this->bind(':start_date', $data['start_date']);
        $this->bind(':end_date', $data['end_date']);
        $this->bind(':status', $data['status'] ?? 'pending_approval');
        $this->bind(':creator', (int) $data['created_by_user_id']);

        if (!$this->execute()) {
            return false;
        }
        $targetId = (int) $this->lastInsertId();

        foreach ($items as $item) {
            if ((float) ($item['planned_value'] ?? 0) <= 0 || empty($item['category_id'])) {
                continue;
            }
            $this->query('INSERT INTO target_items (target_id, category_id, product_id, territory_id, planned_value)
                          VALUES (:target_id, :category_id, :product_id, :territory_id, :planned)');
            $this->bind(':target_id', $targetId);
            $this->bind(':category_id', (int) $item['category_id']);
            $this->bind(':product_id', $this->nullableInt($item['product_id'] ?? null));
            $this->bind(':territory_id', $this->nullableInt($item['territory_id'] ?? null));
            $this->bind(':planned', $this->decimal($item['planned_value']));
            $this->execute();
        }

        $this->recomputeTarget($targetId);
        return $targetId;
    }

    public function review(int $targetId, string $status, int $actorId, string $remark = '', ?array $visibleUserIds = null): bool {
        if (!$this->getTargetById($targetId, $visibleUserIds)) {
            return false;
        }
        $status = $status === 'approved' ? 'approved' : 'rejected';
        $this->query('UPDATE targets
                      SET status = :status, approved_by_user_id = :actor, approved_at = NOW(), approval_remark = :remark
                      WHERE target_id = :id');
        $this->bind(':status', $status);
        $this->bind(':actor', $actorId);
        $this->bind(':remark', $remark);
        $this->bind(':id', $targetId);
        return $this->execute();
    }

    public function recomputeAll(): int {
        $this->query('SELECT target_id FROM targets WHERE status = "approved"');
        $rows = $this->resultSet();
        $count = 0;
        foreach ($rows as $row) {
            $this->recomputeTarget((int) $row->target_id);
            $count++;
        }
        return $count;
    }

    public function recomputeTarget(int $targetId): bool {
        $target = $this->getTargetById($targetId, null);
        if (!$target) {
            return false;
        }
        $items = $this->getItems($targetId);
        $userIds = $this->targetUserIds($target);

        foreach ($items as $item) {
            $achieved = $this->metricValue($item->category_key, $userIds, $target->start_date, $target->end_date);
            $planned = (float) $item->planned_value;
            $pct = $planned > 0 ? min(999.99, round(($achieved / $planned) * 100, 2)) : 0;
            $this->query('INSERT INTO target_progress (target_item_id, achieved_value, completion_percent)
                          VALUES (:item, :achieved, :pct)
                          ON DUPLICATE KEY UPDATE achieved_value = :achieved2, completion_percent = :pct2');
            $this->bind(':item', (int) $item->target_item_id);
            $this->bind(':achieved', $this->decimal($achieved));
            $this->bind(':pct', $pct);
            $this->bind(':achieved2', $this->decimal($achieved));
            $this->bind(':pct2', $pct);
            $this->execute();
        }
        return true;
    }

    public function getUsers(?array $visibleUserIds = null) {
        $where = 'WHERE status = "active"';
        $params = [];
        if ($visibleUserIds !== null) {
            if (!$visibleUserIds) {
                return [];
            }
            $in = $this->placeholders($visibleUserIds, 'user_scope');
            $where .= ' AND user_id IN (' . $in['sql'] . ')';
            $params = $in['params'];
        }
        $this->query('SELECT user_id, name FROM users ' . $where . ' ORDER BY name ASC');
        $this->bindParams($params);
        return $this->resultSet();
    }

    public function getTeams(?array $visibleUserIds = null) {
        $where = 'WHERE t.status = "active"';
        $params = [];
        if ($visibleUserIds !== null) {
            if (!$visibleUserIds) {
                return [];
            }
            $idList = implode(',', array_map('intval', $visibleUserIds));
            $where .= ' AND (
                t.team_leader_user_id IN (' . $idList . ')
                OR t.manager_user_id IN (' . $idList . ')
                OR EXISTS (SELECT 1 FROM employees e WHERE e.team_id = t.team_id AND e.user_id IN (' . $idList . '))
            )';
        }
        $this->query('SELECT DISTINCT t.team_id, t.name FROM teams t ' . $where . ' ORDER BY t.name ASC');
        $this->bindParams($params);
        return $this->resultSet();
    }

    public function getProducts() {
        $this->query('SELECT product_id, name FROM products WHERE status = "active" ORDER BY name ASC');
        return $this->resultSet();
    }

    public function getTerritories() {
        $this->query('SELECT territory_id, name FROM territories WHERE status = "active" ORDER BY name ASC');
        return $this->resultSet();
    }

    private function metricValue(string $category, array $userIds, string $start, string $end): float {
        if (!$userIds) {
            return 0.0;
        }
        $in = $this->placeholders($userIds, 'u');
        $params = $in['params'];
        $startAt = $start . ' 00:00:00';
        $endAt = $end . ' 23:59:59';

        $sql = null;
        if ($category === 'calls') {
            $sql = 'SELECT COUNT(*) FROM communications WHERE user_id IN (' . $in['sql'] . ') AND channel = "call" AND happened_at BETWEEN :start AND :end';
        } elseif ($category === 'emails') {
            $sql = 'SELECT COUNT(*) FROM communications WHERE user_id IN (' . $in['sql'] . ') AND channel = "email" AND happened_at BETWEEN :start AND :end';
        } elseif ($category === 'messages') {
            $sql = 'SELECT COUNT(*) FROM communications WHERE user_id IN (' . $in['sql'] . ') AND channel IN ("whatsapp","sms","social") AND happened_at BETWEEN :start AND :end';
        } elseif ($category === 'meetings') {
            $sql = 'SELECT COUNT(*) FROM meetings WHERE assigned_to_user_id IN (' . $in['sql'] . ') AND type = "meeting" AND status = "completed" AND scheduled_start BETWEEN :start AND :end';
        } elseif ($category === 'demos') {
            $sql = 'SELECT COUNT(*) FROM meetings WHERE assigned_to_user_id IN (' . $in['sql'] . ') AND type = "demo" AND status = "completed" AND scheduled_start BETWEEN :start AND :end';
        } elseif ($category === 'leads') {
            $sql = 'SELECT COUNT(*) FROM leads WHERE assigned_to_user_id IN (' . $in['sql'] . ') AND created_at BETWEEN :start AND :end';
        } elseif ($category === 'conversions') {
            $sql = 'SELECT COUNT(*) FROM leads WHERE assigned_to_user_id IN (' . $in['sql'] . ') AND status = "converted" AND COALESCE(converted_at, updated_at) BETWEEN :start AND :end';
        } elseif ($category === 'revenue') {
            $sql = 'SELECT COALESCE(SUM(lead_value), 0) FROM leads WHERE assigned_to_user_id IN (' . $in['sql'] . ') AND status = "converted" AND COALESCE(converted_at, updated_at) BETWEEN :start AND :end';
        } elseif ($category === 'tasks') {
            $sql = 'SELECT COUNT(*) FROM tasks WHERE assigned_to_user_id IN (' . $in['sql'] . ') AND review_status = "approved" AND COALESCE(reviewed_at, completed_at, updated_at) BETWEEN :start AND :end';
        }

        if (!$sql) {
            return 0.0;
        }
        $this->query($sql);
        foreach ($params as $k => $v) {
            $this->bind($k, $v);
        }
        $this->bind(':start', $startAt);
        $this->bind(':end', $endAt);
        $this->execute();
        return (float) $this->stmt->fetchColumn();
    }

    private function targetUserIds($target): array {
        if ($target->owner_type === 'employee') {
            return [(int) $target->owner_user_id];
        }
        $this->query('SELECT user_id FROM employees WHERE team_id = :team');
        $this->bind(':team', (int) $target->team_id);
        $this->execute();
        return array_map('intval', $this->stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    private function buildWhere(?array $visibleUserIds, array $filters): array {
        $where = [];
        $params = [];
        if ($visibleUserIds !== null) {
            if (!$visibleUserIds) {
                $where[] = '1 = 0';
            } else {
                $idList = implode(',', array_map('intval', $visibleUserIds));
                $where[] = '(
                    t.owner_user_id IN (' . $idList . ')
                    OR (
                        t.owner_type = "team"
                        AND EXISTS (
                            SELECT 1 FROM teams ts
                            LEFT JOIN employees e ON e.team_id = ts.team_id
                            WHERE ts.team_id = t.team_id
                              AND (
                                  ts.team_leader_user_id IN (' . $idList . ')
                                  OR ts.manager_user_id IN (' . $idList . ')
                                  OR e.user_id IN (' . $idList . ')
                              )
                        )
                    )
                )';
            }
        }
        foreach (['target_id', 'status', 'period', 'owner_type'] as $field) {
            if (isset($filters[$field]) && $filters[$field] !== '') {
                $where[] = 't.' . $field . ' = :' . $field;
                $params[':' . $field] = $filters[$field];
            }
        }
        return [$where ? 'WHERE ' . implode(' AND ', $where) : '', $params];
    }

    private function placeholders(array $ids, string $prefix): array {
        $keys = [];
        $params = [];
        foreach (array_values($ids) as $i => $id) {
            $key = ':' . $prefix . $i;
            $keys[] = $key;
            $params[$key] = (int) $id;
        }
        return ['sql' => implode(',', $keys), 'params' => $params];
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
}
