<?php
// Raptor CRM Meetings/Demos Controller

class MeetingsController extends Controller {
    private $meetingModel;
    private $leadModel;

    public function __construct() {
        $this->requireAuth();

        if ($_SESSION['user_role'] === 'employer') {
            $this->redirect('index.php?route=dashboard/executive');
        }

        $this->meetingModel = $this->model('Meeting');
        $this->leadModel = $this->model('Lead');
        $this->userModel = $this->model('User');
    }

    public function index() {
        $filters = [
            'assigned_to_user_id' => $_GET['assigned_to_user_id'] ?? '',
            'type' => $_GET['type'] ?? '',
            'status' => $_GET['status'] ?? '',
            'date_from' => $this->dateBoundary($_GET['date_from'] ?? date('Y-m-d', strtotime('-29 days')), '00:00:00'),
            'date_to' => $this->dateBoundary($_GET['date_to'] ?? date('Y-m-d'), '23:59:59'),
        ];
        if (Policy::isEmployee()) {
            $filters['assigned_to_user_id'] = $_SESSION['user_id'];
        }

        $data = [
            'title' => 'Meetings & Demos | Raptor CRM',
            'active_tab' => 'meetings',
            'meetings' => $this->meetingModel->getMeetings($filters, $this->visibleUserIds()),
            'filters' => $filters,
            'types' => Meeting::TYPES,
            'statuses' => Meeting::STATUSES,
            'users' => $this->getUsers(),
            'leads' => $this->leadModel->getLeads([], $this->visibleUserIds()),
        ];

        $this->viewWithLayout('meetings/index', 'main', $data);
    }

    public function add() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('index.php?route=meetings/index');
        }

        $_POST = filter_input_array(INPUT_POST, FILTER_SANITIZE_SPECIAL_CHARS) ?: [];
        
        $scheduledStart = $this->normalizeDatetime($_POST['scheduled_start'] ?? '');
        if (!$scheduledStart || strtotime($scheduledStart) < time()) {
            $_SESSION['meeting_error'] = 'Meeting start time must be in the future.';
            $this->redirect('index.php?route=meetings/index');
            return;
        }

        $assigned = Policy::isEmployee()
            ? $_SESSION['user_id']
            : ($_POST['assigned_to_user_id'] ?? $_SESSION['user_id']);
        
        $leadId = $_POST['lead_id'] ?? null;
        if ($leadId !== null && $leadId !== '' && !$this->leadModel->getLeadById((int) $leadId, $this->visibleUserIds())) {
            $leadId = null;
        }

        $title = strip_tags(trim($_POST['title'] ?? ''));
        $location = strip_tags(trim($_POST['location'] ?? ''));

        $id = $this->meetingModel->add([
            'lead_id' => $leadId,
            'assigned_to_user_id' => $assigned,
            'created_by_user_id' => $_SESSION['user_id'],
            'type' => $_POST['type'] ?? 'meeting',
            'title' => $title,
            'scheduled_start' => $scheduledStart,
            'scheduled_end' => null,
            'location' => $location,
        ]);

        if ($id) {
            $this->audit('Scheduled meeting/demo #' . (int) $id, 'meeting', (int) $id);

            // Fetch recipient emails
            $recipients = [];

            // 1. The Owner/Assignee
            $owner = $this->userModel->getUserById($assigned);
            if ($owner && !empty($owner->email)) {
                $recipients[] = $owner->email;
            }

            // 2. The Lead
            if ($leadId) {
                $lead = $this->leadModel->getLeadById((int) $leadId, $this->visibleUserIds());
                if ($lead && !empty($lead->email)) {
                    $recipients[] = $lead->email;
                }
            }

            // 3. Additional Attendees / Participants
            if (!empty($_POST['attendees_list'])) {
                $emails = explode(',', $_POST['attendees_list']);
                foreach ($emails as $email) {
                    $email = trim($email);
                    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $recipients[] = $email;
                    }
                }
            }

            $recipients = array_unique($recipients);

            if ($recipients) {
                $meetingUrl = URLROOT . '/index.php?route=meetings/index';
                $subject = "Meeting Scheduled: " . $title;
                $body = "Hello,\n\nA new meeting has been scheduled in Raptor CRM.\n\n" .
                        "Details:\n" .
                        "- Title: " . $title . "\n" .
                        "- Date & Time: " . $scheduledStart . "\n" .
                        "- Location: " . ($location ?: 'N/A') . "\n\n" .
                        "Please click the link below to view or check-in to this meeting:\n" .
                        $meetingUrl . "\n\n" .
                        "Best regards,\nRaptor CRM System";

                foreach ($recipients as $email) {
                    @mail($email, $subject, $body);
                }
            }
        }

        $return = $_POST['return'] ?? 'meetings/index';
        if (!preg_match('/^(meetings\/index|leads\/view\/\d+)$/', $return)) {
            $return = 'meetings/index';
        }
        $this->redirect('index.php?route=' . $return);
    }

    public function check($id) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $_POST = filter_input_array(INPUT_POST, FILTER_SANITIZE_SPECIAL_CHARS) ?: [];
            $meeting = $this->meetingModel->getById((int) $id, $this->visibleUserIds());
            if (!$meeting || (int) $meeting->assigned_to_user_id !== (int) $_SESSION['user_id']) {
                $this->redirect('index.php?route=meetings/index');
            }
            $selfieKey = null;
            try {
                if (!empty($_FILES['selfie']['name'])) {
                    $selfieKey = Storage::put($_FILES['selfie'], 'meeting-selfie');
                } elseif (!empty($_POST['selfie_data'])) {
                    $selfieKey = Storage::putDataUrl($_POST['selfie_data'], 'meeting-selfie');
                }
            } catch (RuntimeException $e) {
                $_SESSION['meeting_error'] = $e->getMessage();
                $this->redirect('index.php?route=meetings/index');
            }

            $type = $_POST['type'] ?? 'in';
            $lat = is_numeric($_POST['lat'] ?? null) ? (float) $_POST['lat'] : null;
            $lng = is_numeric($_POST['lng'] ?? null) ? (float) $_POST['lng'] : null;
            $accuracy = is_numeric($_POST['accuracy_m'] ?? null) ? (int) $_POST['accuracy_m'] : null;

            $checkinId = $this->meetingModel->check((int) $id, (int) $_SESSION['user_id'], $type, $lat, $lng, $accuracy, $selfieKey, $this->visibleUserIds());
            if ($checkinId) {
                $this->audit('Meeting check-' . $type . ' #' . (int) $checkinId, 'meeting', (int) $id);
            }
        }
        $this->redirect('index.php?route=meetings/index');
    }

    public function complete($id) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $_POST = filter_input_array(INPUT_POST, FILTER_SANITIZE_SPECIAL_CHARS) ?: [];
            if ($this->meetingModel->complete((int) $id, [
                'outcome' => strip_tags(trim($_POST['outcome'] ?? '')),
                'client_feedback' => strip_tags(trim($_POST['client_feedback'] ?? '')),
                'next_follow_up_at' => $this->normalizeDatetime($_POST['next_follow_up_at'] ?? ''),
            ], $this->visibleUserIds())) {
                $this->audit('Completed meeting/demo #' . (int) $id, 'meeting', (int) $id);
            }
        }
        $this->redirect('index.php?route=meetings/index');
    }

    public function cancel($id) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if ($this->meetingModel->cancel((int) $id, $this->visibleUserIds())) {
                $this->audit('Cancelled meeting/demo #' . (int) $id, 'meeting', (int) $id);
            }
        }
        $this->redirect('index.php?route=meetings/index');
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
