<?php
/**
 * Raptor CRM - Email Campaigns Controller
 * 
 * Orchestrates Contacts, Lists, Segments, Import Validation,
 * Internal Payroll Employee Synchronization, and Global Suppression.
 */

class EmailCampaignsController extends Controller {

    private CampaignContact $contactModel;
    private CampaignList $listModel;
    private CampaignTag $tagModel;
    private CampaignSegment $segmentModel;
    private CampaignSuppression $suppressionModel;

    public function __construct() {
        $this->requireAuth();

        // RBAC check: admin, ceo, manager, hr, or users with email_campaigns permission
        $role = $_SESSION['user_role'] ?? '';
        $allowedRoles = ['admin', 'ceo', 'manager', 'hr'];
        if (!in_array($role, $allowedRoles, true)) {
            if (class_exists('Policy') && !Policy::can('email_campaigns', 'view')) {
                $_SESSION['flash_error'] = 'Access denied. You do not have permission to access Email Campaigns.';
                $this->redirect('index.php?route=home');
                exit();
            }
        }

        $this->contactModel = new CampaignContact();
        $this->listModel = new CampaignList();
        $this->tagModel = new CampaignTag();
        $this->segmentModel = new CampaignSegment();
        $this->suppressionModel = new CampaignSuppression();
    }

    /**
     * Default index: redirects to contacts
     */
    public function index() {
        $this->contacts();
    }

    // =========================================================================
    // 1. CONTACTS MANAGEMENT
    // =========================================================================

    public function contacts() {
        $filters = [
            'search' => trim($_GET['search'] ?? ''),
            'status' => trim($_GET['status'] ?? ''),
            'list_id' => !empty($_GET['list_id']) ? (int)$_GET['list_id'] : null,
            'tag_id' => !empty($_GET['tag_id']) ? (int)$_GET['tag_id'] : null,
            'source' => trim($_GET['source'] ?? '')
        ];

        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = 25;
        $offset = ($page - 1) * $limit;

        $totalCount = $this->contactModel->countContacts($filters);
        $contacts = $this->contactModel->getContacts($filters, $limit, $offset);
        $totalPages = (int)ceil($totalCount / $limit);

        $lists = $this->listModel->getAllLists();
        $tags = $this->tagModel->getAllTags();

        $data = [
            'title' => 'Email Contacts & Audiences | Raptor CRM',
            'active_tab' => 'email_campaigns_contacts',
            'contacts' => $contacts,
            'total_count' => $totalCount,
            'current_page' => $page,
            'total_pages' => $totalPages,
            'filters' => $filters,
            'lists' => $lists,
            'tags' => $tags
        ];

        $this->viewWithLayout('email_campaigns/contacts/index', 'main', $data);
    }

    public function contactCreate() {
        $lists = $this->listModel->getAllLists();
        $tags = $this->tagModel->getAllTags();

        $data = [
            'title' => 'Add Contact | Raptor CRM',
            'active_tab' => 'email_campaigns_contacts',
            'lists' => $lists,
            'tags' => $tags,
            'contact' => null
        ];

        $this->viewWithLayout('email_campaigns/contacts/create', 'main', $data);
    }

    public function contactEdit() {
        $contactId = (int)($_GET['id'] ?? 0);
        $contact = $this->contactModel->getById($contactId);

        if (!$contact) {
            $_SESSION['flash_error'] = 'Contact not found.';
            $this->redirect('index.php?route=email_campaigns/contacts');
            return;
        }

        $lists = $this->listModel->getAllLists();
        $tags = $this->tagModel->getAllTags();

        $data = [
            'title' => 'Edit Contact | Raptor CRM',
            'active_tab' => 'email_campaigns_contacts',
            'lists' => $lists,
            'tags' => $tags,
            'contact' => $contact
        ];

        $this->viewWithLayout('email_campaigns/contacts/create', 'main', $data);
    }

    public function contactSave() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('index.php?route=email_campaigns/contacts');
            return;
        }

        $email = strtolower(trim($_POST['email'] ?? ''));
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['flash_error'] = 'Please provide a valid email address.';
            $this->redirect('index.php?route=email_campaigns/contactCreate');
            return;
        }

        $listIds = isset($_POST['list_ids']) && is_array($_POST['list_ids']) ? array_map('intval', $_POST['list_ids']) : [];
        $tagIds = isset($_POST['tag_ids']) && is_array($_POST['tag_ids']) ? array_map('intval', $_POST['tag_ids']) : [];

        // Custom fields from form
        $customFields = [];
        if (!empty($_POST['cf_key']) && is_array($_POST['cf_key'])) {
            foreach ($_POST['cf_key'] as $idx => $key) {
                $k = trim($key);
                $v = trim($_POST['cf_val'][$idx] ?? '');
                if ($k !== '') {
                    $customFields[$k] = $v;
                }
            }
        }

        try {
            $contactId = $this->contactModel->upsertContact([
                'email' => $email,
                'first_name' => trim($_POST['first_name'] ?? ''),
                'last_name' => trim($_POST['last_name'] ?? ''),
                'company_name' => trim($_POST['company_name'] ?? ''),
                'status' => in_array($_POST['status'] ?? '', ['subscribed', 'unsubscribed'], true) ? $_POST['status'] : 'subscribed',
                'source' => trim($_POST['source'] ?? 'manual_entry'),
                'consent_type' => trim($_POST['consent_type'] ?? 'explicit_optin'),
                'consent_notes' => trim($_POST['consent_notes'] ?? ''),
                'consent_ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                'custom_fields' => $customFields,
                'list_ids' => $listIds,
                'tag_ids' => $tagIds
            ]);

            CampaignAudit::log((int)($_SESSION['user_id'] ?? 0), 'contact.saved', 'contact', $contactId, [
                'email' => $email
            ]);

            $_SESSION['flash_success'] = 'Contact saved successfully.';
            $this->redirect('index.php?route=email_campaigns/contacts');
        } catch (Throwable $e) {
            $_SESSION['flash_error'] = 'Failed to save contact: ' . $e->getMessage();
            $this->redirect('index.php?route=email_campaigns/contactCreate');
        }
    }

    public function contactDelete() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('index.php?route=email_campaigns/contacts');
            return;
        }

        $contactId = (int)($_POST['contact_id'] ?? 0);
        if ($contactId > 0) {
            $this->contactModel->deleteContact($contactId, (int)($_SESSION['user_id'] ?? 0));
            $_SESSION['flash_success'] = 'Contact and all associated data permanently deleted.';
        }

        $this->redirect('index.php?route=email_campaigns/contacts');
    }

    public function contactExport() {
        $contactId = (int)($_GET['id'] ?? 0);
        $exportData = $this->contactModel->exportGdprData($contactId);

        if (!$exportData) {
            $_SESSION['flash_error'] = 'Contact not found for export.';
            $this->redirect('index.php?route=email_campaigns/contacts');
            return;
        }

        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="gdpr_export_contact_' . $contactId . '.json"');
        echo json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit();
    }

    // =========================================================================
    // 2. CSV IMPORT & DELIVERABILITY VALIDATION
    // =========================================================================

    public function import() {
        $lists = $this->listModel->getAllLists();
        $tags = $this->tagModel->getAllTags();

        $data = [
            'title' => 'Import Contacts via CSV | Raptor CRM',
            'active_tab' => 'email_campaigns_contacts',
            'lists' => $lists,
            'tags' => $tags,
            'consent_types' => CampaignContact::CONSENT_TYPES
        ];

        $this->viewWithLayout('email_campaigns/contacts/import', 'main', $data);
    }

    public function importProcess() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('index.php?route=email_campaigns/import');
            return;
        }

        if (empty($_FILES['csv_file']['tmp_name']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
            $_SESSION['flash_error'] = 'Please upload a valid CSV file.';
            $this->redirect('index.php?route=email_campaigns/import');
            return;
        }

        $uploadedFile = $_FILES['csv_file']['tmp_name'];
        $origName = $_FILES['csv_file']['name'];
        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

        if ($ext !== 'csv') {
            $_SESSION['flash_error'] = 'Invalid file format. Only .csv files are supported.';
            $this->redirect('index.php?route=email_campaigns/import');
            return;
        }

        // Require consent acknowledgement checkbox
        if (empty($_POST['confirm_consent'])) {
            $_SESSION['flash_error'] = 'Compliance Error: You must confirm that you have valid, lawful consent to send emails to these contacts.';
            $this->redirect('index.php?route=email_campaigns/import');
            return;
        }

        $listIds = isset($_POST['list_ids']) && is_array($_POST['list_ids']) ? array_map('intval', $_POST['list_ids']) : [];
        $tagIds = isset($_POST['tag_ids']) && is_array($_POST['tag_ids']) ? array_map('intval', $_POST['tag_ids']) : [];
        $checkMx = !empty($_POST['check_mx']);
        $consentType = trim($_POST['consent_type'] ?? 'explicit_optin');

        $options = [
            'list_ids' => $listIds,
            'tag_ids' => $tagIds,
            'check_mx' => $checkMx,
            'consent_type' => $consentType,
            'consent_notes' => 'Imported via CSV: ' . htmlspecialchars($origName)
        ];

        $res = ContactImportService::processCsv($uploadedFile, $options, (int)($_SESSION['user_id'] ?? 0));

        if (!$res['success']) {
            $_SESSION['flash_error'] = 'CSV Import Failed: ' . ($res['error'] ?? 'Unknown error');
            $this->redirect('index.php?route=email_campaigns/import');
            return;
        }

        $msg = sprintf(
            'Import complete! Successfully imported: %d, Skipped/Duplicates: %d, Rejected (invalid/role/disposable): %d.',
            $res['imported'],
            $res['skipped'],
            $res['rejected']
        );

        if (!empty($res['reject_report_url'])) {
            $msg .= ' <a href="' . htmlspecialchars($res['reject_report_url']) . '" class="btn btn-sm btn-outline-danger ms-2" download><i class="fa-solid fa-download me-1"></i>Download Error Report</a>';
        }

        $_SESSION['flash_success'] = $msg;
        $this->redirect('index.php?route=email_campaigns/contacts');
    }

    // =========================================================================
    // 3. INTERNAL PAYROLL EMPLOYEE SYNCHRONIZATION
    // =========================================================================

    public function syncInternal() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('index.php?route=email_campaigns/lists');
            return;
        }

        $listId = (int)($_POST['list_id'] ?? 0);
        if ($listId <= 0) {
            $_SESSION['flash_error'] = 'Invalid list ID for employee sync.';
            $this->redirect('index.php?route=email_campaigns/lists');
            return;
        }

        $res = $this->contactModel->syncInternalEmployees($listId, (int)($_SESSION['user_id'] ?? 0));

        if ($res['success']) {
            $_SESSION['flash_success'] = sprintf(
                'Successfully synced %d internal employees to the list! (Strictly name and email projected; zero payroll/financial data exposed).',
                $res['synced_count']
            );
        } else {
            $_SESSION['flash_error'] = 'Failed to sync internal employees: ' . ($res['error'] ?? 'Unknown error');
        }

        $this->redirect('index.php?route=email_campaigns/lists');
    }

    // =========================================================================
    // 4. LISTS MANAGEMENT
    // =========================================================================

    public function lists() {
        $lists = $this->listModel->getAllLists();

        $data = [
            'title' => 'Email Lists & Subscriber Audiences | Raptor CRM',
            'active_tab' => 'email_campaigns_lists',
            'lists' => $lists
        ];

        $this->viewWithLayout('email_campaigns/lists/index', 'main', $data);
    }

    public function listSave() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('index.php?route=email_campaigns/lists');
            return;
        }

        $listId = (int)($_POST['list_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $isInternal = !empty($_POST['is_internal']);
        $optinMode = in_array($_POST['optin_mode'] ?? '', ['single', 'double'], true) ? $_POST['optin_mode'] : 'single';

        if (empty($name)) {
            $_SESSION['flash_error'] = 'List name cannot be empty.';
            $this->redirect('index.php?route=email_campaigns/lists');
            return;
        }

        try {
            if ($listId > 0) {
                $this->listModel->updateList($listId, $name, $desc, $optinMode);
                $_SESSION['flash_success'] = 'List updated successfully.';
            } else {
                $newListId = $this->listModel->createList($name, $desc, $isInternal, $optinMode, (int)($_SESSION['user_id'] ?? 0));
                $_SESSION['flash_success'] = 'List created successfully.';
            }
        } catch (Throwable $e) {
            $_SESSION['flash_error'] = 'Error saving list: ' . $e->getMessage();
        }

        $this->redirect('index.php?route=email_campaigns/lists');
    }

    public function listDelete() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('index.php?route=email_campaigns/lists');
            return;
        }

        $listId = (int)($_POST['list_id'] ?? 0);
        if ($listId > 0) {
            $this->listModel->deleteList($listId);
            $_SESSION['flash_success'] = 'List deleted successfully.';
        }

        $this->redirect('index.php?route=email_campaigns/lists');
    }

    // =========================================================================
    // 5. DYNAMIC SEGMENTS & FILTER BUILDER
    // =========================================================================

    public function segments() {
        $segments = $this->segmentModel->getAllSegments();
        $lists = $this->listModel->getAllLists();
        $tags = $this->tagModel->getAllTags();

        $data = [
            'title' => 'Audience Segments & Filter Builder | Raptor CRM',
            'active_tab' => 'email_campaigns_segments',
            'segments' => $segments,
            'lists' => $lists,
            'tags' => $tags,
            'operators' => CampaignSegment::OPERATORS
        ];

        $this->viewWithLayout('email_campaigns/segments/index', 'main', $data);
    }

    public function segmentSave() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('index.php?route=email_campaigns/segments');
            return;
        }

        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $matchType = in_array($_POST['match_type'] ?? '', ['all', 'any'], true) ? $_POST['match_type'] : 'all';

        // Parse dynamic rules
        $rules = [];
        if (!empty($_POST['rule_field']) && is_array($_POST['rule_field'])) {
            foreach ($_POST['rule_field'] as $idx => $field) {
                $f = trim($field);
                $op = trim($_POST['rule_operator'][$idx] ?? 'equals');
                $val = trim($_POST['rule_value'][$idx] ?? '');
                if ($f !== '') {
                    $rules[] = [
                        'field' => $f,
                        'operator' => $op,
                        'value' => $val
                    ];
                }
            }
        }

        try {
            $segmentId = (int)($_POST['segment_id'] ?? 0);
            $this->segmentModel->saveSegment([
                'segment_id' => $segmentId,
                'name' => $name,
                'description' => $description,
                'match_type' => $matchType,
                'rules' => $rules
            ], (int)($_SESSION['user_id'] ?? 0));

            $_SESSION['flash_success'] = 'Audience segment saved successfully.';
        } catch (Throwable $e) {
            $_SESSION['flash_error'] = 'Failed to save segment: ' . $e->getMessage();
        }

        $this->redirect('index.php?route=email_campaigns/segments');
    }

    public function segmentDelete() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('index.php?route=email_campaigns/segments');
            return;
        }

        $segmentId = (int)($_POST['segment_id'] ?? 0);
        if ($segmentId > 0) {
            $this->segmentModel->deleteSegment($segmentId);
            $_SESSION['flash_success'] = 'Segment deleted successfully.';
        }

        $this->redirect('index.php?route=email_campaigns/segments');
    }

    public function segmentPreview() {
        $segmentId = (int)($_GET['id'] ?? 0);
        $count = $this->segmentModel->countMatchingContacts($segmentId);

        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'count' => $count]);
        exit();
    }

    // =========================================================================
    // 6. GLOBAL SUPPRESSION LIST
    // =========================================================================

    public function suppressions() {
        $filters = [
            'search' => trim($_GET['search'] ?? ''),
            'reason' => trim($_GET['reason'] ?? '')
        ];

        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = 50;
        $offset = ($page - 1) * $limit;

        $totalCount = $this->suppressionModel->countSuppressions($filters);
        $suppressions = $this->suppressionModel->getSuppressions($filters, $limit, $offset);
        $totalPages = (int)ceil($totalCount / $limit);

        $data = [
            'title' => 'Global Suppression List | Raptor CRM',
            'active_tab' => 'email_campaigns_suppressions',
            'suppressions' => $suppressions,
            'total_count' => $totalCount,
            'current_page' => $page,
            'total_pages' => $totalPages,
            'filters' => $filters,
            'reasons' => CampaignSuppression::REASONS
        ];

        $this->viewWithLayout('email_campaigns/suppressions/index', 'main', $data);
    }

    public function suppressionAdd() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('index.php?route=email_campaigns/suppressions');
            return;
        }

        $email = strtolower(trim($_POST['email'] ?? ''));
        $reason = trim($_POST['reason'] ?? 'admin_manual');
        $notes = trim($_POST['notes'] ?? 'Manually added by administrator');

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['flash_error'] = 'Invalid email address provided.';
            $this->redirect('index.php?route=email_campaigns/suppressions');
            return;
        }

        $res = $this->suppressionModel->suppress($email, $reason, $notes);
        if ($res) {
            CampaignAudit::log((int)($_SESSION['user_id'] ?? 0), 'suppression.manual_added', 'suppression', null, [
                'email' => $email,
                'reason' => $reason
            ]);
            $_SESSION['flash_success'] = 'Email successfully added to the Global Suppression List. It will never be emailed again.';
        } else {
            $_SESSION['flash_error'] = 'Failed to add email to suppression list.';
        }

        $this->redirect('index.php?route=email_campaigns/suppressions');
    }

    public function suppressionRemove() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('index.php?route=email_campaigns/suppressions');
            return;
        }

        // Only Admin or CEO can remove an email from suppression
        $role = $_SESSION['user_role'] ?? '';
        if (!in_array($role, ['admin', 'ceo'], true)) {
            $_SESSION['flash_error'] = 'Deliverability Protection: Only Super Administrators can un-suppress an address.';
            $this->redirect('index.php?route=email_campaigns/suppressions');
            return;
        }

        $email = strtolower(trim($_POST['email'] ?? ''));
        if (!empty($email)) {
            $this->suppressionModel->remove($email);
            CampaignAudit::log((int)($_SESSION['user_id'] ?? 0), 'suppression.removed', 'suppression', null, [
                'email' => $email
            ]);
            $_SESSION['flash_success'] = 'Address removed from suppression list.';
        }

        $this->redirect('index.php?route=email_campaigns/suppressions');
    }
}
