<?php
// Raptor CRM Tasks Controller

class TasksController extends Controller {
    private $taskModel;

    public function __construct() {
        $this->requireAuth();
        $this->requirePermission('tasks', 'view');

        $this->taskModel = $this->model('Task');
    }

    public function index() {
        $filters = [
            'assigned_to_user_id' => $_GET['assigned_to_user_id'] ?? '',
            'team_id' => $_GET['team_id'] ?? '',
            'review_status' => $_GET['review_status'] ?? '',
        ];
        if (Policy::isEmployee() && empty($_GET['assigned_to_user_id'])) {
            // Employee sees tasks assigned to them or their team via $visibleUserIds scoping
        }

        $teamModel = $this->model('Team');
        $visible = $this->visibleUserIds();
        $data = [
            'title' => 'Task Board | Raptor CRM',
            'active_tab' => 'operations',
            'tasks' => $this->taskModel->getTasks($visible, $filters),
            'metrics' => $this->taskModel->completionMetrics($visible),
            'can_assign' => in_array($_SESSION['user_role'], ['admin', 'manager', 'team_leader'], true),
            'can_review' => in_array($_SESSION['user_role'], ['admin', 'manager', 'team_leader'], true),
            'assignees' => $this->getAssignees(),
            'teams' => $teamModel->getTeams(),
            'filters' => $filters,
            'review_statuses' => Task::REVIEW_STATUSES,
        ];

        $this->viewWithLayout('tasks/index', 'main', $data);
    }

    public function add() {
        $this->requirePermission('tasks', 'create');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $_POST = filter_input_array(INPUT_POST, FILTER_SANITIZE_SPECIAL_CHARS) ?: [];
            $title = strip_tags(trim($_POST['title'] ?? ''));
            $assignedTo = trim($_POST['assigned_to_user_id'] ?? '');
            $teamId = trim($_POST['team_id'] ?? '');
            $startDate = $this->normalizeDatetime($_POST['start_date'] ?? '');
            $deadline = $this->normalizeDatetime($_POST['deadline'] ?? '');

            if (empty($title) || !preg_match('/[a-zA-Z]/', $title)) {
                $_SESSION['task_error'] = 'Task Title must contain at least one letter.';
                $this->redirect('index.php?route=tasks/index');
                return;
            }

            if (empty($assignedTo) && empty($teamId)) {
                $_SESSION['task_error'] = 'Please assign the task to an employee or a team.';
                $this->redirect('index.php?route=tasks/index');
                return;
            }

            if (empty($deadline)) {
                $_SESSION['task_error'] = 'Deadline is required.';
                $this->redirect('index.php?route=tasks/index');
                return;
            }

            if (!empty($startDate) && strtotime($startDate) < strtotime(date('Y-m-d 00:00:00'))) {
                $_SESSION['task_error'] = 'Start date & time cannot be in the past.';
                $this->redirect('index.php?route=tasks/index');
                return;
            }

            if (strtotime($deadline) < strtotime(date('Y-m-d 00:00:00'))) {
                $_SESSION['task_error'] = 'Deadline date & time cannot be in the past.';
                $this->redirect('index.php?route=tasks/index');
                return;
            }

            if (!empty($startDate) && strtotime($deadline) < strtotime($startDate)) {
                $_SESSION['task_error'] = 'Deadline cannot be earlier than start date.';
                $this->redirect('index.php?route=tasks/index');
                return;
            }

            $data = [
                'assigned_to_user_id' => $assignedTo ?: null,
                'team_id' => $teamId ?: null,
                'created_by_user_id' => $_SESSION['user_id'],
                'title' => $title,
                'description' => strip_tags(trim($_POST['description'] ?? '')),
                'start_date' => $startDate,
                'priority' => trim($_POST['priority'] ?? 'medium'),
                'deadline' => $deadline,
                'estimated_hours' => $_POST['estimated_hours'] ?? 0,
                'remarks' => strip_tags(trim($_POST['remarks'] ?? '')),
                'status' => 'pending',
            ];

            $taskId = $this->taskModel->addTask($data);
            if ($taskId) {
                $this->audit('Created task: ' . $data['title'], 'task', (int) $taskId, null, $data);
                $_SESSION['task_success'] = 'Task created successfully.';
            } else {
                $_SESSION['task_error'] = 'Something went wrong while saving the task.';
            }
        }
        $this->redirect('index.php?route=tasks/index');
    }

    public function updateStatus() {
        $this->requireAuthApi();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $taskId = (int) ($_POST['task_id'] ?? 0);
            $status = trim($_POST['status'] ?? '');
            if (in_array($status, Task::STATUSES, true) && $this->taskModel->updateStatus($taskId, $status, $this->visibleUserIds())) {
                $this->audit('Updated task #' . $taskId . ' status to ' . $status, 'task', $taskId);
                $this->jsonOk(['task_id' => $taskId, 'status' => $status]);
            }
        }

        $this->jsonError('Failed to update task status.');
    }

    public function progress($id) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $_POST = filter_input_array(INPUT_POST, FILTER_SANITIZE_SPECIAL_CHARS) ?: [];
            $ok = $this->taskModel->updateProgress((int) $id, [
                'progress_percent' => $_POST['progress_percent'] ?? 0,
                'actual_hours' => $_POST['actual_hours'] ?? 0,
                'remarks' => strip_tags(trim($_POST['remarks'] ?? '')),
            ], $this->visibleUserIds());
            if ($ok) {
                $this->audit('Updated task #' . (int) $id . ' progress', 'task', (int) $id);
            }
        }
        $this->redirect('index.php?route=tasks/index');
    }

    public function complete($id) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $_POST = filter_input_array(INPUT_POST, FILTER_SANITIZE_SPECIAL_CHARS) ?: [];
            $task = $this->taskModel->getTaskById((int) $id, $this->visibleUserIds());
            if (!$task) {
                $this->redirect('index.php?route=tasks/index');
            }

            $proofKey = null;
            try {
                if (!empty($_FILES['proof']['name'])) {
                    $proofKey = Storage::put($_FILES['proof'], 'task-proof');
                } elseif (empty($task->proof_url)) {
                    throw new RuntimeException('Proof file is required to complete this task.');
                }
            } catch (RuntimeException $e) {
                $_SESSION['task_error'] = $e->getMessage();
                $this->redirect('index.php?route=tasks/index');
            }

            $ok = $this->taskModel->completeWithProof(
                (int) $id,
                $proofKey,
                strip_tags(trim($_POST['remarks'] ?? '')),
                (float) ($_POST['actual_hours'] ?? 0),
                $this->visibleUserIds()
            );
            if ($ok) {
                $this->audit('Completed task #' . (int) $id . ' with proof', 'task', (int) $id);
            }
        }
        $this->redirect('index.php?route=tasks/index');
    }

    public function review($id) {
        $this->requirePermission('tasks', 'assign');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $_POST = filter_input_array(INPUT_POST, FILTER_SANITIZE_SPECIAL_CHARS) ?: [];
            $decision = $_POST['decision'] ?? 'rejected';
            $remark = strip_tags(trim($_POST['review_remark'] ?? ''));
            if ($this->taskModel->review((int) $id, $decision, (int) $_SESSION['user_id'], $remark, $this->visibleUserIds())) {
                $this->audit('Reviewed task #' . (int) $id . ' as ' . $decision, 'task', (int) $id);
            }
        }
        $this->redirect('index.php?route=tasks/index');
    }

    public function delete($id) {
        $this->requirePermission('tasks', 'assign');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if ($this->taskModel->deleteTask((int) $id, $this->visibleUserIds())) {
                $this->audit('Deleted task #' . (int) $id, 'task', (int) $id);
                $_SESSION['task_success'] = 'Task deleted successfully.';
            } else {
                $_SESSION['task_error'] = 'Failed to delete task.';
            }
        }
        $this->redirect('index.php?route=tasks/index');
    }

    public function deleteMultiple() {
        $this->requirePermission('tasks', 'assign');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $rawIds = $_POST['task_ids'] ?? [];
            if (is_string($rawIds)) {
                $rawIds = explode(',', $rawIds);
            }
            $taskIds = array_filter(array_map('intval', (array)$rawIds), function($id) { return $id > 0; });

            if (empty($taskIds)) {
                $_SESSION['task_error'] = 'Please select at least one task to delete.';
            } else {
                $count = $this->taskModel->deleteMultipleTasks($taskIds, $this->visibleUserIds());
                if ($count > 0) {
                    $this->audit("Bulk deleted {$count} task(s)", 'task');
                    $_SESSION['task_success'] = "Successfully deleted {$count} task(s).";
                } else {
                    $_SESSION['task_error'] = 'Failed to delete selected tasks.';
                }
            }
        }
        $this->redirect('index.php?route=tasks/index');
    }

    public function deleteByEmployee() {
        $this->requirePermission('tasks', 'assign');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $employeeUserId = (int)($_POST['employee_user_id'] ?? 0);
            if ($employeeUserId <= 0) {
                $_SESSION['task_error'] = 'Please select a valid employee.';
            } else {
                $count = $this->taskModel->deleteTasksByEmployee($employeeUserId, $this->visibleUserIds());
                if ($count > 0) {
                    $this->audit("Deleted all {$count} task(s) for employee #{$employeeUserId}", 'task');
                    $_SESSION['task_success'] = "Successfully deleted {$count} task(s) assigned to the selected employee.";
                } else {
                    $_SESSION['task_error'] = 'No tasks found for the selected employee or deletion failed.';
                }
            }
        }
        $this->redirect('index.php?route=tasks/index');
    }

    private function normalizeDatetime(string $value): ?string {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        return str_replace('T', ' ', $value) . (strlen($value) === 16 ? ':00' : '');
    }

    private function getAssignees() {
        try {
            $db = Database::getInstance()->getConnection();
            $visible = $this->visibleUserIds();
            $params = [];
            $where = 'WHERE u.status = "active"';
            if ($visible !== null) {
                if (!$visible) {
                    return [];
                }
                $keys = [];
                foreach ($visible as $i => $id) {
                    $key = ':uid' . $i;
                    $keys[] = $key;
                    $params[$key] = (int) $id;
                }
                $where .= ' AND u.user_id IN (' . implode(',', $keys) . ')';
            }
            $stmt = $db->prepare('SELECT u.user_id, u.name FROM users u ' . $where . ' ORDER BY u.name ASC');
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_OBJ);
        } catch (Exception $e) {
            return [];
        }
    }
}
