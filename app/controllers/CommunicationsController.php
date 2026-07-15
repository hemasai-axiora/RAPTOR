<?php
// Raptor CRM Communications Controller

class CommunicationsController extends Controller {
    private $communicationModel;
    private $leadModel;

    public function __construct() {
        $this->requireAuth();

        if ($_SESSION['user_role'] === 'employer') {
            $this->redirect('index.php?route=dashboard/executive');
        }

        $this->communicationModel = $this->model('Communication');
        $this->leadModel = $this->model('Lead');
    }

    public function index() {
        $filters = [
            'user_id' => $_GET['user_id'] ?? '',
            'channel' => $_GET['channel'] ?? '',
            'direction' => $_GET['direction'] ?? '',
            'date_from' => $this->dateBoundary($_GET['date_from'] ?? date('Y-m-d', strtotime('-29 days')), '00:00:00'),
            'date_to' => $this->dateBoundary($_GET['date_to'] ?? date('Y-m-d'), '23:59:59'),
        ];
        if (Policy::isEmployee()) {
            $filters['user_id'] = $_SESSION['user_id'];
        }

        $data = [
            'title' => 'Communications Log | Raptor CRM',
            'active_tab' => 'communications',
            'communications' => $this->communicationModel->getCommunications($filters, $this->visibleUserIds()),
            'filters' => $filters,
            'channels' => Communication::CHANNELS,
            'directions' => Communication::DIRECTIONS,
            'users' => $this->getUsers(),
            'leads' => $this->leadModel->getLeads([], $this->visibleUserIds()),
        ];

        $this->viewWithLayout('communications/index', 'main', $data);
    }

    public function add() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('index.php?route=communications/index');
        }

        $_POST = filter_input_array(INPUT_POST, FILTER_SANITIZE_SPECIAL_CHARS) ?: [];
        $proofKey = null;
        try {
            if (!empty($_FILES['proof']['name'])) {
                $proofKey = Storage::put($_FILES['proof'], 'communication-proof');
            }
        } catch (RuntimeException $e) {
            $_SESSION['communication_error'] = $e->getMessage();
            $this->redirect('index.php?route=communications/index');
        }

        $leadId = $_POST['lead_id'] ?? null;
        if ($leadId !== null && $leadId !== '' && !$this->leadModel->getLeadById((int) $leadId, $this->visibleUserIds())) {
            $leadId = null;
        }

        $id = $this->communicationModel->add([
            'lead_id' => $leadId,
            'user_id' => $_SESSION['user_id'],
            'channel' => $_POST['channel'] ?? 'call',
            'direction' => $_POST['direction'] ?? 'made',
            'duration_seconds' => (int) ($_POST['duration_minutes'] ?? 0) * 60,
            'outcome' => strip_tags(trim($_POST['outcome'] ?? '')),
            'note' => strip_tags(trim($_POST['note'] ?? '')),
            'proof_url' => $proofKey,
            'happened_at' => $this->normalizeDatetime($_POST['happened_at'] ?? '') ?: date('Y-m-d H:i:s'),
        ]);

        if ($id) {
            $this->audit('Logged communication #' . (int) $id, 'communication', (int) $id);
        }

        $return = $_POST['return'] ?? 'communications/index';
        if (!preg_match('/^(communications\/index|leads\/view\/\d+)$/', $return)) {
            $return = 'communications/index';
        }
        $this->redirect('index.php?route=' . $return);
    }

    public function delete($id) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if ($this->communicationModel->delete((int) $id, $this->visibleUserIds())) {
                $this->audit('Deleted communication #' . (int) $id, 'communication', (int) $id);
            }
        }
        $this->redirect('index.php?route=communications/index');
    }

    private function normalizeDatetime(string $value): ?string {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        return str_replace('T', ' ', $value) . (strlen($value) === 16 ? ':00' : '');
    }

    private function dateBoundary(string $value, string $time): string {
        $date = substr(trim($value), 0, 10);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $date = date('Y-m-d');
        }
        return $date . ' ' . $time;
    }

    private function getUsers() {
        try {
            $db = Database::getInstance()->getConnection();
            $visible = $this->visibleUserIds();
            $params = [];
            $where = 'WHERE status = "active"';
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
                $where .= ' AND user_id IN (' . implode(',', $keys) . ')';
            }
            $stmt = $db->prepare('SELECT user_id, name FROM users ' . $where . ' ORDER BY name ASC');
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_OBJ);
        } catch (Exception $e) {
            return [];
        }
    }
}
