<?php
/**
 * ContactsController — Unified Customer 360 & Marketing Registry Controller
 * -------------------------------------------------------------------------
 * Manages the consolidated Customer 360 directory:
 * - Omnichannel activity timeline per contact
 * - Multi-channel identity mapping (email, phone, whatsapp, cookie)
 * - Dynamic lifecycle stage & lead score governance
 * - Unified suppression & consent control
 */

class ContactsController extends Controller {
    private $contactModel;

    public function __construct() {
        $this->requireAuth();
        $this->contactModel = $this->model('Contact');
    }

    public function index() {
        $filters = [
            'lifecycle_stage' => $_GET['lifecycle_stage'] ?? '',
            'lead_quality' => $_GET['lead_quality'] ?? '',
            'is_suppressed' => $_GET['is_suppressed'] ?? '',
            'search' => $_GET['search'] ?? '',
        ];

        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = 25;
        $offset = ($page - 1) * $limit;

        $total = $this->contactModel->countContacts($filters);
        $contacts = $this->contactModel->getContacts($filters, $limit, $offset);

        $data = [
            'title' => 'Customer 360 Registry | Raptor Marketing',
            'active_tab' => 'marketing',
            'contacts' => $contacts,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => ceil($total / $limit) ?: 1,
            'filters' => $filters,
            'stages' => Contact::LIFECYCLE_STAGES,
            'qualities' => ['hot', 'warm', 'cold']
        ];

        $this->viewWithLayout('contacts/index', 'main', $data);
    }

    public function view($id = null) {
        $contactId = (int)$id;
        if ($contactId <= 0) {
            $this->redirect('index.php?route=contacts/index');
        }

        $contact = $this->contactModel->getById($contactId);
        if (!$contact) {
            $_SESSION['flash_error'] = "Contact profile not found.";
            $this->redirect('index.php?route=contacts/index');
        }

        $identities = $this->contactModel->getIdentities($contactId);
        $activities = $this->contactModel->getActivities($contactId, 100);

        // Fetch linked CRM lead if any
        $lead = null;
        $db = Database::getInstance()->getConnection();
        $lStmt = $db->prepare("SELECT lead_id, lead_code, status, lead_quality, lead_value, created_at FROM leads WHERE contact_id = ? LIMIT 1");
        $lStmt->execute([$contactId]);
        $lead = $lStmt->fetch(PDO::FETCH_OBJ);

        // Fetch linked Customer if any
        $customer = null;
        $cStmt = $db->prepare("SELECT customer_id, customer_code, customer_type, status, contract_value, renewal_date FROM customers WHERE contact_id = ? LIMIT 1");
        $cStmt->execute([$contactId]);
        $customer = $cStmt->fetch(PDO::FETCH_OBJ);

        $data = [
            'title' => ($contact->first_name ? htmlspecialchars($contact->first_name . ' ' . ($contact->last_name ?? '')) : 'Contact Profile') . ' | Customer 360',
            'active_tab' => 'marketing',
            'contact' => $contact,
            'identities' => $identities,
            'activities' => $activities,
            'lead' => $lead,
            'customer' => $customer,
            'stages' => Contact::LIFECYCLE_STAGES
        ];

        $this->viewWithLayout('contacts/view', 'main', $data);
    }

    public function stage() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $contactId = (int)($_POST['contact_id'] ?? 0);
            $newStage = trim($_POST['lifecycle_stage'] ?? '');
            $userId = (int)($_SESSION['user_id'] ?? 0);

            if ($contactId > 0 && !empty($newStage)) {
                if ($this->contactModel->setLifecycleStage($contactId, $newStage, $userId)) {
                    $_SESSION['flash_success'] = "Lifecycle stage updated to [" . strtoupper($newStage) . "].";
                } else {
                    $_SESSION['flash_error'] = "Failed to update lifecycle stage.";
                }
            }
            $this->redirect('index.php?route=contacts/view/' . $contactId);
        }
        $this->redirect('index.php?route=contacts/index');
    }

    public function score() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $contactId = (int)($_POST['contact_id'] ?? 0);
            $delta = (int)($_POST['delta'] ?? 0);
            $reason = trim($_POST['reason'] ?? 'Manual Score Adjustment');
            $userId = (int)($_SESSION['user_id'] ?? 0);

            if ($contactId > 0 && $delta !== 0) {
                $newScore = $this->contactModel->updateLeadScore($contactId, $delta, $reason, $userId);
                $_SESSION['flash_success'] = "Lead score updated to [{$newScore}].";
            }
            $this->redirect('index.php?route=contacts/view/' . $contactId);
        }
        $this->redirect('index.php?route=contacts/index');
    }

    public function suppress() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $contactId = (int)($_POST['contact_id'] ?? 0);
            $action = trim($_POST['action'] ?? 'suppress');
            $reason = trim($_POST['reason'] ?? 'manual_optout');
            $userId = (int)($_SESSION['user_id'] ?? 0);

            if ($contactId > 0) {
                if ($action === 'suppress') {
                    $this->contactModel->suppressContact($contactId, $reason, $userId);
                    $_SESSION['flash_warning'] = "Contact suppressed across all marketing channels and CRM outreach.";
                } else {
                    $this->contactModel->unsuppressContact($contactId, $userId);
                    $_SESSION['flash_success'] = "Suppression lifted for this contact.";
                }
            }
            $this->redirect('index.php?route=contacts/view/' . $contactId);
        }
        $this->redirect('index.php?route=contacts/index');
    }

    public function addNote() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $contactId = (int)($_POST['contact_id'] ?? 0);
            $title = trim($_POST['title'] ?? 'CRM Activity Note');
            $note = trim($_POST['description'] ?? '');
            $channel = trim($_POST['channel'] ?? 'crm');
            $userId = (int)($_SESSION['user_id'] ?? 0);

            if ($contactId > 0 && !empty($note)) {
                $this->contactModel->recordActivity(
                    $contactId,
                    $channel,
                    'manual_note',
                    $title,
                    $note,
                    [],
                    $userId
                );
                $_SESSION['flash_success'] = "Activity note recorded on Customer 360 timeline.";
            }
            $this->redirect('index.php?route=contacts/view/' . $contactId);
        }
        $this->redirect('index.php?route=contacts/index');
    }
}

