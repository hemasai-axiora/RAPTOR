<?php
// Raptor CRM Task Model

class Task extends Model {
    public const STATUSES = ['pending', 'in_progress', 'completed'];
    public const PRIORITIES = ['low', 'medium', 'high'];
    public const REVIEW_STATUSES = ['not_submitted', 'pending_review', 'approved', 'rejected'];

    public function getTasks(?array $visibleUserIds = null, array $filters = []) {
        [$where, $params] = $this->buildWhere($visibleUserIds, $filters);
        $this->query('SELECT t.*, u_assignee.name AS assignee_name, u_creator.name AS creator_name,
                             u_reviewer.name AS reviewer_name
                      FROM tasks t
                      JOIN users u_assignee ON t.assigned_to_user_id = u_assignee.user_id
                      JOIN users u_creator ON t.created_by_user_id = u_creator.user_id
                      LEFT JOIN users u_reviewer ON t.reviewed_by = u_reviewer.user_id
                      ' . $where . '
                      ORDER BY t.deadline ASC, t.task_id ASC');
        $this->bindParams($params);
        return $this->resultSet();
    }

    public function getTaskById($id, ?array $visibleUserIds = null) {
        $rows = $this->getTasks($visibleUserIds, ['task_id' => (int) $id]);
        return $rows[0] ?? false;
    }

    public function addTask($data) {
        $this->query('INSERT INTO tasks
            (assigned_to_user_id, created_by_user_id, title, description, start_date, priority, deadline,
             status, progress_percent, estimated_hours, remarks)
            VALUES
            (:assigned, :creator, :title, :desc, :start_date, :priority, :deadline,
             :status, :progress, :estimated, :remarks)');

        $this->bind(':assigned', (int) $data['assigned_to_user_id']);
        $this->bind(':creator', (int) $data['created_by_user_id']);
        $this->bind(':title', $data['title']);
        $this->bind(':desc', $data['description'] ?? null);
        $this->bind(':start_date', $data['start_date'] ?? null);
        $this->bind(':priority', $this->validPriority($data['priority'] ?? 'medium'));
        $this->bind(':deadline', $data['deadline']);
        $this->bind(':status', $this->validStatus($data['status'] ?? 'pending'));
        $this->bind(':progress', $this->clampPercent($data['progress_percent'] ?? 0));
        $this->bind(':estimated', $this->decimal($data['estimated_hours'] ?? 0));
        $this->bind(':remarks', $data['remarks'] ?? null);

        if ($this->execute()) {
            return (int) $this->lastInsertId();
        }
        return false;
    }

    public function updateStatus($id, $status, ?array $visibleUserIds = null) {
        if (!$this->getTaskById((int) $id, $visibleUserIds)) {
            return false;
        }
        $status = $this->validStatus($status);
        $this->query('UPDATE tasks
                      SET status = :status,
                          progress_percent = CASE WHEN :status2 = "completed" THEN 100 ELSE progress_percent END,
                          completed_at = CASE WHEN :status3 = "completed" THEN COALESCE(completed_at, NOW()) ELSE NULL END,
                          review_status = CASE WHEN :status4 = "completed" THEN "pending_review" ELSE "not_submitted" END
                      WHERE task_id = :id');
        $this->bind(':status', $status);
        $this->bind(':status2', $status);
        $this->bind(':status3', $status);
        $this->bind(':status4', $status);
        $this->bind(':id', (int) $id);
        return $this->execute();
    }

    public function updateProgress(int $id, array $data, ?array $visibleUserIds = null) {
        if (!$this->getTaskById($id, $visibleUserIds)) {
            return false;
        }
        $progress = $this->clampPercent($data['progress_percent'] ?? 0);
        $status = $progress >= 100 ? 'completed' : ($progress > 0 ? 'in_progress' : 'pending');

        $this->query('UPDATE tasks
                      SET progress_percent = :progress,
                          actual_hours = :actual_hours,
                          remarks = :remarks,
                          status = :status,
                          completed_at = CASE WHEN :status2 = "completed" THEN COALESCE(completed_at, NOW()) ELSE completed_at END,
                          review_status = CASE WHEN :status3 = "completed" THEN "pending_review" ELSE review_status END
                      WHERE task_id = :id');
        $this->bind(':progress', $progress);
        $this->bind(':actual_hours', $this->decimal($data['actual_hours'] ?? 0));
        $this->bind(':remarks', $data['remarks'] ?? null);
        $this->bind(':status', $status);
        $this->bind(':status2', $status);
        $this->bind(':status3', $status);
        $this->bind(':id', $id);
        return $this->execute();
    }

    public function completeWithProof(int $id, ?string $proofKey, string $remarks, float $actualHours, ?array $visibleUserIds = null) {
        if (!$this->getTaskById($id, $visibleUserIds)) {
            return false;
        }
        $this->query('UPDATE tasks
                      SET status = "completed", progress_percent = 100, completed_at = NOW(),
                          proof_url = COALESCE(:proof_url, proof_url),
                          remarks = :remarks, actual_hours = :actual_hours,
                          review_status = "pending_review", reviewed_by = NULL, reviewed_at = NULL, review_remark = NULL
                      WHERE task_id = :id');
        $this->bind(':proof_url', $proofKey);
        $this->bind(':remarks', $remarks);
        $this->bind(':actual_hours', $this->decimal($actualHours));
        $this->bind(':id', $id);
        return $this->execute();
    }

    public function review(int $id, string $decision, int $reviewerId, string $remark = '', ?array $visibleUserIds = null) {
        if (!$this->getTaskById($id, $visibleUserIds)) {
            return false;
        }
        $reviewStatus = $decision === 'approved' ? 'approved' : 'rejected';
        $this->query('UPDATE tasks
                      SET review_status = :review_status,
                          reviewed_by = :reviewer,
                          reviewed_at = NOW(),
                          review_remark = :remark,
                          status = CASE WHEN :review_status2 = "rejected" THEN "in_progress" ELSE status END,
                          progress_percent = CASE WHEN :review_status3 = "rejected" THEN 90 ELSE progress_percent END
                      WHERE task_id = :id');
        $this->bind(':review_status', $reviewStatus);
        $this->bind(':review_status2', $reviewStatus);
        $this->bind(':review_status3', $reviewStatus);
        $this->bind(':reviewer', $reviewerId);
        $this->bind(':remark', $remark);
        $this->bind(':id', $id);
        return $this->execute();
    }

    public function carryForwardIncomplete(?string $date = null): int {
        $date = $date ?: date('Y-m-d');
        $this->query('SELECT t.*
                      FROM tasks t
                      WHERE t.status <> "completed"
                        AND DATE(t.deadline) < :date
                        AND NOT EXISTS (
                            SELECT 1 FROM tasks c
                            WHERE c.source_task_id = t.task_id
                              AND DATE(c.deadline) >= :date
                        )');
        $this->bind(':date', $date);
        $tasks = $this->resultSet();
        $count = 0;

        foreach ($tasks as $task) {
            $newDeadline = date('Y-m-d H:i:s', strtotime($date . ' ' . date('H:i:s', strtotime($task->deadline))));
            $this->query('INSERT INTO tasks
                (assigned_to_user_id, created_by_user_id, title, description, start_date, priority, deadline,
                 status, progress_percent, estimated_hours, actual_hours, remarks, is_carry_forward, source_task_id, review_status)
                VALUES
                (:assigned, :creator, :title, :description, :start_date, :priority, :deadline,
                 "pending", :progress, :estimated, 0.00, :remarks, TRUE, :source_task_id, "not_submitted")');
            $this->bind(':assigned', (int) $task->assigned_to_user_id);
            $this->bind(':creator', (int) $task->created_by_user_id);
            $this->bind(':title', $task->title);
            $this->bind(':description', $task->description);
            $this->bind(':start_date', $date . ' 00:00:00');
            $this->bind(':priority', $task->priority);
            $this->bind(':deadline', $newDeadline);
            $this->bind(':progress', (int) $task->progress_percent);
            $this->bind(':estimated', $this->decimal($task->estimated_hours ?? 0));
            $this->bind(':remarks', trim(($task->remarks ?? '') . "\nCarried forward from task #" . $task->task_id));
            $this->bind(':source_task_id', (int) $task->task_id);
            if ($this->execute()) {
                $count++;
            }
        }

        return $count;
    }

    public function completionMetrics(?array $visibleUserIds = null): array {
        [$where, $params] = $this->buildWhere($visibleUserIds, []);
        $this->query('SELECT
                         COUNT(*) AS total_tasks,
                         SUM(status = "completed") AS completed_tasks,
                         SUM(review_status = "approved") AS approved_tasks,
                         SUM(is_carry_forward = 1) AS carried_tasks
                      FROM tasks t ' . $where);
        $this->bindParams($params);
        $row = $this->single();
        return [
            'total' => (int) ($row->total_tasks ?? 0),
            'completed' => (int) ($row->completed_tasks ?? 0),
            'approved' => (int) ($row->approved_tasks ?? 0),
            'carried' => (int) ($row->carried_tasks ?? 0),
        ];
    }

    public function deleteTask($id, ?array $visibleUserIds = null) {
        return false;
    }

    private function buildWhere(?array $visibleUserIds, array $filters): array {
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
                $where[] = 't.assigned_to_user_id IN (' . implode(',', $keys) . ')';
            }
        }

        foreach (['task_id', 'assigned_to_user_id', 'status', 'review_status'] as $field) {
            if (isset($filters[$field]) && $filters[$field] !== '') {
                $where[] = 't.' . $field . ' = :' . $field;
                $params[':' . $field] = $filters[$field];
            }
        }

        return [$where ? 'WHERE ' . implode(' AND ', $where) : '', $params];
    }

    private function bindParams(array $params): void {
        foreach ($params as $key => $value) {
            $this->bind($key, $value);
        }
    }

    private function validStatus(string $status): string {
        return in_array($status, self::STATUSES, true) ? $status : 'pending';
    }

    private function validPriority(string $priority): string {
        return in_array($priority, self::PRIORITIES, true) ? $priority : 'medium';
    }

    private function clampPercent($value): int {
        return min(100, max(0, (int) $value));
    }

    private function decimal($value): string {
        return number_format(max(0, (float) $value), 2, '.', '');
    }
}
