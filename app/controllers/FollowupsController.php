<?php
// Raptor CRM Follow-ups Controller

class FollowupsController extends Controller {
    private $followUpModel;
    private $leadModel;

    public function __construct() {
        $this->requireAuth();

        if ($_SESSION['user_role'] === 'employer') {
            $this->redirect('index.php?route=dashboard/executive');
        }

        $this->followUpModel = $this->model('FollowUp');
        $this->leadModel = $this->model('Lead');
    }

    public function index() {
        $filters = [
            'assigned_to_user_id' => $_GET['assigned_to_user_id'] ?? '',
            'status' => $_GET['status'] ?? 'scheduled',
            'channel' => $_GET['channel'] ?? '',
            'date_from' => $this->normalizeDateBoundary($_GET['date_from'] ?? date('Y-m-d', strtotime('-29 days')), '00:00:00'),
            'date_to' => $this->normalizeDateBoundary($_GET['date_to'] ?? date('Y-m-d'), '23:59:59'),
        ];

        if (Policy::isEmployee()) {
            $filters['assigned_to_user_id'] = $_SESSION['user_id'];
        }

        $data = [
            'title' => 'My Follow-ups | Raptor CRM',
            'active_tab' => 'followups',
            'followups' => $this->followUpModel->getFollowUps($filters, $this->visibleUserIds()),
            'filters' => $filters,
            'channels' => FollowUp::CHANNELS,
            'statuses' => FollowUp::STATUSES,
            'assignees' => $this->getAssignees(),
            'today_count' => count($this->followUpModel->getTodayForUser((int) $_SESSION['user_id'])),
        ];

        $this->viewWithLayout('followups/index', 'main', $data);
    }

    public function schedule() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('index.php?route=followups/index');
        }

        $_POST = filter_input_array(INPUT_POST, FILTER_SANITIZE_SPECIAL_CHARS) ?: [];
        $leadId = (int) ($_POST['lead_id'] ?? 0);
        $lead = $this->leadModel->getLeadById($leadId, $this->visibleUserIds());
        if (!$lead) {
            $this->redirect('index.php?route=leads/index');
        }

        $assignedTo = Policy::isEmployee()
            ? (int) $_SESSION['user_id']
            : (int) ($_POST['assigned_to_user_id'] ?: $lead->assigned_to_user_id ?: $_SESSION['user_id']);

        $dueAt = $this->normalizeDatetime($_POST['due_at'] ?? '');
        if ($dueAt) {
            $id = $this->followUpModel->schedule([
                'lead_id' => $leadId,
                'assigned_to_user_id' => $assignedTo,
                'created_by_user_id' => $_SESSION['user_id'],
                'channel' => $_POST['channel'] ?? 'call',
                'due_at' => $dueAt,
                'note' => trim($_POST['note'] ?? ''),
            ]);
            if ($id) {
                $this->audit('Scheduled follow-up #' . $id, 'follow_up', (int) $id);
            }
        }

        $return = $_POST['return'] ?? 'followups/index';
        if (!preg_match('/^(followups\/index|leads\/view\/\d+)$/', $return)) {
            $return = 'followups/index';
        }
        $this->redirect('index.php?route=' . $return);
    }

    public function complete($id) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $_POST = filter_input_array(INPUT_POST, FILTER_SANITIZE_SPECIAL_CHARS) ?: [];
            $outcome = trim($_POST['outcome'] ?? 'Completed');
            if ($this->followUpModel->complete((int) $id, $outcome, $this->visibleUserIds())) {
                $this->audit('Completed follow-up #' . (int) $id, 'follow_up', (int) $id);
            }
        }
        $this->redirect('index.php?route=followups/index');
    }

    public function cancel($id) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if ($this->followUpModel->cancel((int) $id, $this->visibleUserIds())) {
                $this->audit('Cancelled follow-up #' . (int) $id, 'follow_up', (int) $id);
            }
        }
        $this->redirect('index.php?route=followups/index');
    }

    private function normalizeDatetime(string $value): ?string {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        return str_replace('T', ' ', $value) . (strlen($value) === 16 ? ':00' : '');
    }

    private function normalizeDateBoundary(string $value, string $time): string {
        $date = substr(trim($value), 0, 10);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $date = date('Y-m-d');
        }
        return $date . ' ' . $time;
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
            $stmt = $db->prepare('SELECT u.user_id, u.name
                                  FROM users u ' . $where . '
                                  ORDER BY u.name ASC');
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_OBJ);
        } catch (Exception $e) {
            return [];
        }
    }
}
