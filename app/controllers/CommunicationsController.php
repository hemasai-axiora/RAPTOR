<?php
// Raptor CRM Communications Controller

class CommunicationsController extends Controller {
    private $communicationModel;
    private $leadModel;

    public function __construct() {
        $this->requireAuth();

        if (($_SESSION['user_role'] ?? '') === 'employer') {
            $this->redirect('index.php?route=dashboard/executive');
        }

        $this->communicationModel = $this->model('Communication');
        $this->leadModel = $this->model('Lead');
    }

    public function index() {
        try {
            $dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-29 days'));
            $dateTo = $_GET['date_to'] ?? date('Y-m-d');
            if ($dateTo < $dateFrom) {
                $_SESSION['communication_error'] = 'To Date cannot be earlier than From Date.';
                $dateFrom = date('Y-m-d', strtotime('-29 days'));
                $dateTo = date('Y-m-d');
            }

            $filters = [
                'user_id' => $_GET['user_id'] ?? '',
                'channel' => $_GET['channel'] ?? '',
                'direction' => $_GET['direction'] ?? '',
                'outcome' => $_GET['outcome'] ?? '',
                'search' => $_GET['search'] ?? '',
                'date_from' => $this->dateBoundary($dateFrom, '00:00:00'),
                'date_to' => $this->dateBoundary($dateTo, '23:59:59'),
            ];
            if (Policy::isEmployee()) {
                $filters['user_id'] = $_SESSION['user_id'];
            }

            $data = [
                'title' => 'Communications Log | Raptor CRM',
                'active_tab' => 'communications',
                'communications' => ($this->communicationModel && method_exists($this->communicationModel, 'getCommunications')) ? ($this->communicationModel->getCommunications($filters, $this->visibleUserIds()) ?: []) : [],
                'filters' => $filters,
                'channels' => defined('Communication::CHANNELS') ? Communication::CHANNELS : ['call', 'whatsapp', 'sms', 'email', 'social', 'other'],
                'directions' => defined('Communication::DIRECTIONS') ? Communication::DIRECTIONS : ['made', 'received', 'missed', 'sent'],
                'users' => method_exists($this, 'getUsers') ? ($this->getUsers() ?: []) : [],
                'leads' => ($this->leadModel && method_exists($this->leadModel, 'getAllLeadsForSelect')) ? ($this->leadModel->getAllLeadsForSelect($this->visibleUserIds()) ?: []) : [],
            ];

            $this->viewWithLayout('communications/index', 'main', $data);
        } catch (\Throwable $e) {
            error_log("Communications index 500 prevention catch: " . $e->getMessage());
            $data = [
                'title' => 'Communications Log | Raptor CRM',
                'active_tab' => 'communications',
                'communications' => [],
                'filters' => [],
                'channels' => ['call', 'whatsapp', 'sms', 'email', 'social', 'other'],
                'directions' => ['made', 'received', 'missed', 'sent'],
                'users' => [],
                'leads' => [],
                'error_msg' => 'Communications log initialized with default state.'
            ];
            $this->viewWithLayout('communications/index', 'main', $data);
        }
    }

    public function bulkUpload() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('index.php?route=communications/index');
        }

        $records = [];
        $userId = $_SESSION['user_id'];

        // Option 1: File Upload
        if (!empty($_FILES['csv_file']['tmp_name']) && is_uploaded_file($_FILES['csv_file']['tmp_name'])) {
            $handle = fopen($_FILES['csv_file']['tmp_name'], 'r');
            if ($handle !== false) {
                $header = fgetcsv($handle);
                while (($row = fgetcsv($handle)) !== false) {
                    if (count($row) >= 1 && !empty(trim($row[0]))) {
                        $records[] = [
                            'identifier' => trim($row[0] ?? ''),
                            'channel'    => strtolower(trim($row[1] ?? 'whatsapp')),
                            'direction'  => strtolower(trim($row[2] ?? 'made')),
                            'outcome'    => trim($row[3] ?? 'Sent Message'),
                            'note'       => trim($row[4] ?? ''),
                            'happened_at'=> !empty($row[5]) ? $this->normalizeDatetime($row[5]) : date('Y-m-d H:i:s'),
                        ];
                    }
                }
                fclose($handle);
            }
        } 
        // Option 2: Paste Raw Lines
        elseif (!empty($_POST['bulk_text'])) {
            $lines = explode("\n", trim($_POST['bulk_text']));
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '') continue;
                $parts = array_map('trim', explode(',', $line));
                if (!empty($parts[0])) {
                    $records[] = [
                        'identifier' => $parts[0],
                        'channel'    => strtolower($parts[1] ?? 'whatsapp'),
                        'direction'  => strtolower($parts[2] ?? 'made'),
                        'outcome'    => $parts[3] ?? 'Sent Message',
                        'note'       => $parts[4] ?? '',
                        'happened_at'=> !empty($parts[5]) ? $this->normalizeDatetime($parts[5]) : date('Y-m-d H:i:s'),
                    ];
                }
            }
        }

        if (empty($records)) {
            $_SESSION['communication_error'] = 'No valid communication records found to upload. Please check your CSV file or pasted text.';
            $this->redirect('index.php?route=communications/index');
            return;
        }

        $inserted = 0;
        foreach ($records as $rec) {
            $leadId = $this->communicationModel->findLeadByPhoneOrEmail($rec['identifier']);
            
            $validChannels = ['call', 'whatsapp', 'sms', 'email', 'social', 'other'];
            $channel = in_array($rec['channel'], $validChannels) ? $rec['channel'] : 'whatsapp';

            $validDirections = ['made', 'received', 'missed', 'sent', 'inbound', 'outbound'];
            $direction = in_array($rec['direction'], $validDirections) ? $rec['direction'] : 'made';
            if ($direction === 'outbound') $direction = 'made';
            if ($direction === 'inbound') $direction = 'received';

            $happenedAt = $rec['happened_at'] ?: date('Y-m-d H:i:s');

            $id = $this->communicationModel->add([
                'lead_id' => $leadId,
                'phone_number' => $rec['identifier'],
                'user_id' => $userId,
                'channel' => $channel,
                'direction' => $direction,
                'duration_seconds' => 0,
                'outcome' => strip_tags($rec['outcome'] ?: 'Bulk Touch'),
                'note' => strip_tags($rec['note'] ?: ('Bulk logged ' . strtoupper($channel) . ' touch for ' . $rec['identifier'])),
                'proof_url' => null,
                'happened_at' => $happenedAt,
            ]);

            if ($id) $inserted++;
        }

        $_SESSION['communication_success'] = "Successfully bulk logged {$inserted} communication touch points!";
        $this->redirect('index.php?route=communications/index');
    }

    public function sampleCsv() {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=communications_bulk_upload_sample.csv');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['phone_or_email_or_lead_id', 'channel', 'direction', 'outcome', 'notes', 'happened_at']);
        fputcsv($output, ['+919876543210', 'whatsapp', 'sent', 'Template Sent', 'Followed up regarding quote proposal', date('Y-m-d H:i:s')]);
        fputcsv($output, ['john@example.com', 'email', 'sent', 'Email Sent', 'Sent monthly marketing brochure', date('Y-m-d H:i:s')]);
        fputcsv($output, ['9876543211', 'call', 'made', 'Connected', 'Discussed requirements & pricing', date('Y-m-d H:i:s')]);
        fclose($output);
        exit();
    }

    public function exportCsv() {
        $dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-29 days'));
        $dateTo = $_GET['date_to'] ?? date('Y-m-d');
        $filters = [
            'user_id' => $_GET['user_id'] ?? '',
            'channel' => $_GET['channel'] ?? '',
            'direction' => $_GET['direction'] ?? '',
            'outcome' => $_GET['outcome'] ?? '',
            'search' => $_GET['search'] ?? '',
            'date_from' => $this->dateBoundary($dateFrom, '00:00:00'),
            'date_to' => $this->dateBoundary($dateTo, '23:59:59'),
        ];
        if (Policy::isEmployee()) {
            $filters['user_id'] = $_SESSION['user_id'];
        }

        $communications = $this->communicationModel->getCommunications($filters, $this->visibleUserIds()) ?: [];

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=communications_export_' . date('Y-m-d') . '.csv');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['ID', 'Lead Name', 'Company', 'Team Member', 'Channel', 'Direction', 'Outcome', 'Notes', 'Happened At']);

        foreach ($communications as $item) {
            fputcsv($output, [
                $item->communication_id,
                trim(($item->first_name ?? '') . ' ' . ($item->last_name ?? '')),
                $item->lead_company_name ?? '',
                $item->user_name ?? '',
                strtoupper($item->channel ?? ''),
                strtoupper($item->direction ?? ''),
                $item->outcome ?? '',
                $item->note ?? '',
                $item->happened_at ?? ''
            ]);
        }
        fclose($output);
        exit();
    }

    public function update($id) {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('index.php?route=communications/index');
        }

        $_POST = filter_input_array(INPUT_POST, FILTER_SANITIZE_SPECIAL_CHARS) ?: [];
        $happenedAt = $this->normalizeDatetime($_POST['happened_at'] ?? '') ?: date('Y-m-d H:i:s');
        $outcome = strip_tags(trim($_POST['outcome'] ?? ''));
        $note = strip_tags(trim($_POST['note'] ?? ''));

        $updated = $this->communicationModel->update((int)$id, [
            'phone_number' => $_POST['phone_number'] ?? null,
            'channel' => $_POST['channel'] ?? 'call',
            'direction' => $_POST['direction'] ?? 'made',
            'outcome' => $outcome,
            'note' => $note,
            'happened_at' => $happenedAt,
        ]);

        if ($updated) {
            $_SESSION['communication_success'] = 'Communication record updated successfully.';
            $this->audit('Updated communication #' . (int)$id, 'communication', (int)$id);
        } else {
            $_SESSION['communication_error'] = 'Failed to update communication record.';
        }

        $this->redirect('index.php?route=communications/index');
    }

    public function bulkDelete() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['ids']) && is_array($_POST['ids'])) {
            $deleted = $this->communicationModel->deleteBulk($_POST['ids'], $this->visibleUserIds());
            if ($deleted > 0) {
                $_SESSION['communication_success'] = "Successfully deleted {$deleted} communication log entries.";
            }
        }
        $this->redirect('index.php?route=communications/index');
    }

    public function add() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('index.php?route=communications/index');
        }

        $_POST = filter_input_array(INPUT_POST, FILTER_SANITIZE_SPECIAL_CHARS) ?: [];
        $proofKey = null;
        try {
            if (!empty($_FILES['proof']['name'])) {
                $ext = strtolower(pathinfo($_FILES['proof']['name'], PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
                if (!in_array($ext, $allowed, true)) {
                    $_SESSION['communication_error'] = 'Invalid file type. Only image files (JPG, PNG, WEBP, GIF) are allowed for communication proof.';
                    $this->redirect('index.php?route=communications/index');
                    return;
                }
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

        $happenedAt = $this->normalizeDatetime($_POST['happened_at'] ?? '') ?: date('Y-m-d H:i:s');
        if (strtotime($happenedAt) > time()) {
            $_SESSION['communication_error'] = 'Happened At date/time cannot be in the future.';
            $this->redirect('index.php?route=communications/index');
            return;
        }

        $outcome = strip_tags(trim($_POST['outcome'] ?? ''));
        $note = strip_tags(trim($_POST['note'] ?? ''));

        if ($outcome !== '' && !preg_match('/[a-zA-Z0-9]/', $outcome)) {
            $_SESSION['communication_error'] = 'Outcome must contain alphanumeric characters.';
            $this->redirect('index.php?route=communications/index');
            return;
        }

        if ($note !== '' && !preg_match('/[a-zA-Z0-9]/', $note)) {
            $_SESSION['communication_error'] = 'Note must contain alphanumeric characters.';
            $this->redirect('index.php?route=communications/index');
            return;
        }

        $id = $this->communicationModel->add([
            'lead_id' => $leadId,
            'user_id' => $_SESSION['user_id'],
            'channel' => $_POST['channel'] ?? 'call',
            'direction' => $_POST['direction'] ?? 'made',
            'duration_seconds' => (int) ($_POST['duration_minutes'] ?? 0) * 60,
            'outcome' => $outcome,
            'note' => $note,
            'proof_url' => $proofKey,
            'happened_at' => $happenedAt,
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
