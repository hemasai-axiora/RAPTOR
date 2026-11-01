<?php
/**
 * FormsController — Marketing Forms & Inbound Ingestion Manager
 * -------------------------------------------------------------------------
 * Handles:
 * 1. Web form builder & embed snippet generator
 * 2. Public standalone form renderer (`forms/render&uuid=...`)
 * 3. Submission audit logs & conversion analytics
 * 4. Inbound webhook endpoint keys (WordPress, Webflow, Meta Lead Ads)
 */

class FormsController extends Controller {

    private $formModel;
    private $submissionModel;
    private $webhookModel;
    private $userModel;

    public function __construct() {
        $this->formModel = $this->model('MarketingForm');
        $this->submissionModel = $this->model('FormSubmission');
        $this->webhookModel = $this->model('InboundWebhook');
        $this->userModel = $this->model('User');
    }

    public function index() {
        $this->requireAuth();
        $forms = $this->formModel->getAllForms(100);
        $total = $this->formModel->countForms();

        $data = [
            'title' => 'Lead Capture Forms | Raptor Marketing',
            'active_tab' => 'marketing',
            'forms' => $forms,
            'total' => $total
        ];

        $this->viewWithLayout('forms/index', 'main', $data);
    }

    public function add() {
        $this->requireAuth();
        $users = $this->userModel->getUsers() ?: [];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $name = trim($_POST['name'] ?? '');
            if (empty($name)) {
                $_SESSION['flash_error'] = "Form name is required.";
                $this->redirect('index.php?route=forms/add');
                return;
            }

            $fields = [
                ['name' => 'first_name', 'label' => 'First Name', 'type' => 'text', 'required' => true],
                ['name' => 'last_name', 'label' => 'Last Name', 'type' => 'text', 'required' => false],
                ['name' => 'email', 'label' => 'Email Address', 'type' => 'email', 'required' => true],
                ['name' => 'phone', 'label' => 'Phone Number', 'type' => 'tel', 'required' => !empty($_POST['require_phone'])],
                ['name' => 'company_name', 'label' => 'Company Name', 'type' => 'text', 'required' => false],
                ['name' => 'notes', 'label' => 'Message / Notes', 'type' => 'textarea', 'required' => false]
            ];

            $formId = $this->formModel->createForm([
                'name' => $name,
                'description' => trim($_POST['description'] ?? ''),
                'form_type' => $_POST['form_type'] ?? 'embedded',
                'fields' => $fields,
                'submit_button_text' => trim($_POST['submit_button_text'] ?? 'Submit Inquiry'),
                'success_message' => trim($_POST['success_message'] ?? 'Thank you! We have received your submission.'),
                'redirect_url' => trim($_POST['redirect_url'] ?? ''),
                'assigned_user_id' => !empty($_POST['assigned_user_id']) ? (int)$_POST['assigned_user_id'] : null,
                'default_lead_source' => trim($_POST['default_lead_source'] ?? 'web_form'),
                'default_lead_quality' => trim($_POST['default_lead_quality'] ?? 'warm'),
                'enable_honeypot' => 1,
                'notification_emails' => trim($_POST['notification_emails'] ?? ''),
                'created_by' => (int)($_SESSION['user_id'] ?? 0)
            ]);

            if ($formId > 0) {
                $_SESSION['flash_success'] = "Lead capture form created successfully.";
                $this->redirect('index.php?route=forms/view/' . $formId);
                return;
            } else {
                $_SESSION['flash_error'] = "Failed to create form.";
            }
        }

        $data = [
            'title' => 'Create Lead Form | Raptor Marketing',
            'active_tab' => 'marketing',
            'users' => $users
        ];

        $this->viewWithLayout('forms/add', 'main', $data);
    }

    public function view($id = null) {
        $this->requireAuth();
        $formId = (int)$id;
        $form = $this->formModel->getById($formId);

        if (!$form) {
            $_SESSION['flash_error'] = "Form not found.";
            $this->redirect('index.php?route=forms/index');
            return;
        }

        $submissions = $this->submissionModel->getSubmissionsForForm($formId, 50);
        $iframeSnippet = $this->formModel->generateEmbedSnippet($form, 'iframe');
        $jsSnippet = $this->formModel->generateEmbedSnippet($form, 'js');

        $data = [
            'title' => htmlspecialchars($form->name) . ' | Form Details',
            'active_tab' => 'marketing',
            'form' => $form,
            'submissions' => $submissions,
            'iframe_snippet' => $iframeSnippet,
            'js_snippet' => $jsSnippet
        ];

        $this->viewWithLayout('forms/view', 'main', $data);
    }

    /**
     * Public standalone or iframe form renderer.
     * Accessible without login for embed purposes.
     */
    public function render() {
        $uuid = trim($_GET['uuid'] ?? '');
        $form = $this->formModel->getByUuid($uuid);

        if (!$form || (int)$form->is_active !== 1) {
            die('Form not found or has been disabled.');
        }

        // Increment view count
        $this->formModel->incrementViews((int)$form->form_id);

        $fields = json_decode($form->fields_json, true) ?: MarketingForm::DEFAULT_FIELDS;

        $data = [
            'form' => $form,
            'fields' => $fields
        ];

        // Standalone clean view without main admin layout
        $this->view('forms/public_render', $data);
    }

    public function webhooks() {
        $this->requireAuth();
        $endpoints = $this->webhookModel->getAllEndpoints();
        $users = $this->userModel->getUsers() ?: [];

        $data = [
            'title' => 'Inbound Webhook Endpoints | Raptor Marketing',
            'active_tab' => 'marketing',
            'endpoints' => $endpoints,
            'users' => $users
        ];

        $this->viewWithLayout('forms/webhooks', 'main', $data);
    }

    public function createWebhook() {
        $this->requireAuth();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $name = trim($_POST['name'] ?? '');
            $provider = trim($_POST['provider'] ?? 'custom');
            $assignedUserId = !empty($_POST['assigned_user_id']) ? (int)$_POST['assigned_user_id'] : null;
            $leadSource = trim($_POST['default_lead_source'] ?? 'webhook');

            if (!empty($name)) {
                $res = $this->webhookModel->createEndpoint($name, $provider, $assignedUserId, $leadSource);
                if (!empty($res)) {
                    $_SESSION['flash_success'] = "Webhook endpoint generated! Secret: " . $res['secret'];
                    $_SESSION['new_webhook_secret'] = $res['secret'];
                } else {
                    $_SESSION['flash_error'] = "Failed to create webhook endpoint.";
                }
            }
        }
        $this->redirect('index.php?route=forms/webhooks');
    }
}
