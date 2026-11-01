<?php
/**
 * TelephonyController — Cloud Calling, Dispositions & DND Compliance
 * -------------------------------------------------------------------------
 * Manages:
 * 1. Click-to-Call dialing bridge for sales reps
 * 2. Real-time call status and audio recording playback
 * 3. Call disposition tagging and follow-up scheduling
 * 4. National TRAI DND registry scrubbing tool
 */

class TelephonyController extends Controller {

    private $callModel;
    private $dndModel;
    private $contactModel;

    public function __construct() {
        $this->requireAuth();
        $this->callModel = $this->model('TelephonyCall');
        $this->dndModel = $this->model('DndRegistry');
        $this->contactModel = $this->model('Contact');
    }

    public function index() {
        $filters = [
            'status' => $_GET['status'] ?? '',
            'search' => $_GET['search'] ?? '',
            'user_id' => !empty($_GET['user_id']) ? (int)$_GET['user_id'] : null
        ];

        $calls = $this->callModel->getCalls($filters, 50);
        $analytics = $this->callModel->getAnalytics();

        $data = [
            'title' => 'Cloud Telephony & Call Logs | Raptor CRM',
            'active_tab' => 'marketing',
            'calls' => $calls,
            'analytics' => $analytics,
            'filters' => $filters,
            'is_calling_hours' => TelephonyService::isWithinCallingHours()
        ];

        $this->viewWithLayout('telephony/index', 'main', $data);
    }

    public function initiateCall() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $customerPhone = trim($_POST['customer_phone'] ?? '');
            $agentPhone = trim($_POST['agent_phone'] ?? '+18005550199');
            $contactId = !empty($_POST['contact_id']) ? (int)$_POST['contact_id'] : null;
            $leadId = !empty($_POST['lead_id']) ? (int)$_POST['lead_id'] : null;
            $currentUserId = (int)($_SESSION['user_id'] ?? 0);

            $res = TelephonyService::initiateCall($agentPhone, $customerPhone, [
                'contact_id' => $contactId,
                'lead_id' => $leadId,
                'user_id' => $currentUserId,
                'bypass_calling_hours' => !empty($_POST['bypass_hours'])
            ]);

            if ($res['success']) {
                $_SESSION['flash_success'] = "Outbound call initiated! Bridging {$agentPhone} to {$customerPhone}.";
            } else {
                $_SESSION['flash_error'] = "Call failed: " . ($res['error'] ?? 'Unknown error');
            }

            if (!empty($_POST['return_to'])) {
                $this->redirect($_POST['return_to']);
                return;
            }
        }
        $this->redirect('index.php?route=telephony/index');
    }

    public function saveDisposition() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $callId = (int)($_POST['call_id'] ?? 0);
            $code = trim($_POST['disposition_code'] ?? '');
            $notes = trim($_POST['disposition_notes'] ?? '');
            $callbackDue = !empty($_POST['callback_due_at']) ? trim($_POST['callback_due_at']) : null;

            if ($callId > 0 && !empty($code)) {
                $this->callModel->updateDisposition($callId, $code, $notes, $callbackDue);
                $_SESSION['flash_success'] = "Call outcome tagged as [" . strtoupper($code) . "].";
            }
        }
        $this->redirect('index.php?route=telephony/index');
    }

    public function dnd() {
        $numbers = $this->dndModel->getAll(100);

        $data = [
            'title' => 'TRAI DND Compliance Registry | Raptor Marketing',
            'active_tab' => 'marketing',
            'numbers' => $numbers
        ];

        $this->viewWithLayout('telephony/dnd', 'main', $data);
    }

    public function addDnd() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $phone = trim($_POST['phone_number'] ?? '');
            $type = trim($_POST['dnd_type'] ?? 'full');

            if (!empty($phone)) {
                $this->dndModel->addNumber($phone, $type, 'manual');
                $_SESSION['flash_success'] = "Phone number {$phone} registered on Do-Not-Disturb registry.";
            } else {
                $_SESSION['flash_error'] = "Phone number cannot be empty.";
            }
        }
        $this->redirect('index.php?route=telephony/dnd');
    }

    public function scrubList() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $rawText = trim($_POST['numbers_text'] ?? '');
            $lines = preg_split('/[\r\n,]+/', $rawText);
            $results = $this->dndModel->scrubBatch($lines);

            $data = [
                'title' => 'DND Batch Scrubbing Results | Raptor Marketing',
                'active_tab' => 'marketing',
                'results' => $results,
                'numbers' => $this->dndModel->getAll(50)
            ];

            $this->viewWithLayout('telephony/dnd', 'main', $data);
            return;
        }
        $this->redirect('index.php?route=telephony/dnd');
    }
}

