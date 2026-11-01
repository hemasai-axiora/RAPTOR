<?php
/**
 * WhatsAppController — Meta WhatsApp Business Cloud Admin & Shared Inbox
 * -------------------------------------------------------------------------
 * Manages:
 * 1. 24-Hour session-aware messaging & shared inbox
 * 2. Meta Message Templates management
 * 3. Bulk template broadcast scheduling
 * 4. API credentials & Webhook settings
 */

class WhatsAppController extends Controller {

    private $conversationModel;
    private $messageModel;
    private $templateModel;
    private $broadcastModel;
    private $contactModel;
    private $userModel;

    public function __construct() {
        $this->requireAuth();
        $this->conversationModel = $this->model('WhatsAppConversation');
        $this->messageModel = $this->model('WhatsAppMessage');
        $this->templateModel = $this->model('WhatsAppTemplate');
        $this->broadcastModel = $this->model('WhatsAppBroadcast');
        $this->contactModel = $this->model('Contact');
        $this->userModel = $this->model('User');
    }

    public function index() {
        $this->redirect('index.php?route=whatsapp/conversations');
    }

    public function conversations() {
        $filters = [
            'status' => $_GET['status'] ?? '',
            'assigned_user_id' => $_GET['assigned_user_id'] ?? '',
            'search' => $_GET['search'] ?? ''
        ];

        $conversations = $this->conversationModel->getConversations($filters, 50);

        $data = [
            'title' => 'WhatsApp Inbox | Raptor Marketing',
            'active_tab' => 'marketing',
            'conversations' => $conversations,
            'filters' => $filters
        ];

        $this->viewWithLayout('whatsapp/conversations', 'main', $data);
    }

    public function chat($id = null) {
        $convId = (int)$id;
        $conv = $this->conversationModel->getById($convId);

        if (!$conv) {
            $_SESSION['flash_error'] = "Conversation not found.";
            $this->redirect('index.php?route=whatsapp/conversations');
            return;
        }

        // Mark unread messages as read
        $this->conversationModel->markAsRead($convId);

        $messages = $this->messageModel->getMessagesForConversation($convId, 150);
        $isSessionActive = $this->conversationModel->isSessionActive($conv);
        $approvedTemplates = $this->templateModel->getApprovedTemplates();

        $contact = $conv->contact_id ? $this->contactModel->getById((int)$conv->contact_id) : null;

        $data = [
            'title' => 'Chat with ' . htmlspecialchars($conv->first_name ?: $conv->phone_number) . ' | WhatsApp',
            'active_tab' => 'marketing',
            'conversation' => $conv,
            'messages' => $messages,
            'is_session_active' => $isSessionActive,
            'templates' => $approvedTemplates,
            'contact' => $contact
        ];

        $this->viewWithLayout('whatsapp/chat', 'main', $data);
    }

    public function send() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $convId = (int)($_POST['conversation_id'] ?? 0);
            $conv = $this->conversationModel->getById($convId);

            if (!$conv) {
                $_SESSION['flash_error'] = "Conversation not found.";
                $this->redirect('index.php?route=whatsapp/conversations');
                return;
            }

            // Check if recipient contact is suppressed
            if ($conv->contact_id) {
                $contact = $this->contactModel->getById((int)$conv->contact_id);
                if ($contact && (int)$contact->is_suppressed === 1) {
                    $_SESSION['flash_error'] = "Cannot send message: Contact is globally suppressed/opted-out.";
                    $this->redirect('index.php?route=whatsapp/chat/' . $convId);
                    return;
                }
            }

            $isSessionActive = $this->conversationModel->isSessionActive($conv);
            $userId = (int)($_SESSION['user_id'] ?? 0);

            if ($isSessionActive) {
                // Free-form text message allowed inside 24-hr session
                $body = trim($_POST['body'] ?? '');
                if (empty($body)) {
                    $_SESSION['flash_error'] = "Message body cannot be empty.";
                    $this->redirect('index.php?route=whatsapp/chat/' . $convId);
                    return;
                }

                $res = WhatsAppService::sendTextMessage($conv->phone_number, $body);
                if ($res['success']) {
                    $this->messageModel->recordMessage([
                        'conversation_id' => $convId,
                        'contact_id' => $conv->contact_id,
                        'direction' => 'outbound',
                        'message_type' => 'text',
                        'meta_message_id' => $res['meta_message_id'],
                        'body' => $body,
                        'status' => 'sent',
                        'sent_by_user_id' => $userId
                    ]);
                    $this->conversationModel->recordOutbound($convId);

                    if ($conv->contact_id) {
                        $this->contactModel->recordActivity(
                            (int)$conv->contact_id,
                            'whatsapp',
                            'whatsapp_sent',
                            'Sent WhatsApp Message',
                            $body,
                            ['meta_id' => $res['meta_message_id']],
                            $userId
                        );
                    }
                    $_SESSION['flash_success'] = "Message sent.";
                } else {
                    $_SESSION['flash_error'] = "Failed to dispatch message: " . ($res['error'] ?? 'Unknown error');
                }
            } else {
                // Session expired: Must use pre-approved Template
                $templateId = (int)($_POST['template_id'] ?? 0);
                $tmpl = $this->templateModel->getById($templateId);

                if (!$tmpl || $tmpl->status !== 'APPROVED') {
                    $_SESSION['flash_error'] = "24-Hour window expired. You must select an approved Message Template.";
                    $this->redirect('index.php?route=whatsapp/chat/' . $convId);
                    return;
                }

                $res = WhatsAppService::sendTemplateMessage($conv->phone_number, $tmpl->name, $tmpl->language);
                if ($res['success']) {
                    $this->messageModel->recordMessage([
                        'conversation_id' => $convId,
                        'contact_id' => $conv->contact_id,
                        'direction' => 'outbound',
                        'message_type' => 'template',
                        'template_id' => $tmpl->template_id,
                        'meta_message_id' => $res['meta_message_id'],
                        'body' => $tmpl->body_text,
                        'status' => 'sent',
                        'sent_by_user_id' => $userId
                    ]);
                    $this->conversationModel->recordOutbound($convId);
                    $_SESSION['flash_success'] = "Template message dispatched.";
                } else {
                    $_SESSION['flash_error'] = "Failed to send template: " . ($res['error'] ?? 'Unknown error');
                }
            }

            $this->redirect('index.php?route=whatsapp/chat/' . $convId);
        }
    }

    public function templates() {
        $templates = $this->templateModel->getAllTemplates();

        $data = [
            'title' => 'WhatsApp Message Templates | Raptor Marketing',
            'active_tab' => 'marketing',
            'templates' => $templates,
            'categories' => WhatsAppTemplate::CATEGORIES,
            'header_types' => WhatsAppTemplate::HEADER_TYPES
        ];

        $this->viewWithLayout('whatsapp/templates', 'main', $data);
    }

    public function saveTemplate() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $name = trim($_POST['name'] ?? '');
            $category = trim($_POST['category'] ?? 'MARKETING');
            $body = trim($_POST['body_text'] ?? '');

            if (!empty($name) && !empty($body)) {
                $tmplId = $this->templateModel->saveTemplate([
                    'name' => $name,
                    'category' => $category,
                    'language' => $_POST['language'] ?? 'en_US',
                    'header_type' => $_POST['header_type'] ?? 'NONE',
                    'header_content' => trim($_POST['header_content'] ?? ''),
                    'body_text' => $body,
                    'footer_text' => trim($_POST['footer_text'] ?? ''),
                    'status' => 'APPROVED', // Pre-approved in local system
                    'created_by' => (int)($_SESSION['user_id'] ?? 0)
                ]);

                if ($tmplId > 0) {
                    $_SESSION['flash_success'] = "Template saved successfully.";
                } else {
                    $_SESSION['flash_error'] = "Failed to save template. Name might already exist.";
                }
            } else {
                $_SESSION['flash_error'] = "Template name and body text are required.";
            }
        }
        $this->redirect('index.php?route=whatsapp/templates');
    }

    public function broadcasts() {
        $broadcasts = $this->broadcastModel->getAllBroadcasts();
        $templates = $this->templateModel->getApprovedTemplates('MARKETING');

        $data = [
            'title' => 'WhatsApp Broadcast Campaigns | Raptor Marketing',
            'active_tab' => 'marketing',
            'broadcasts' => $broadcasts,
            'templates' => $templates
        ];

        $this->viewWithLayout('whatsapp/broadcasts', 'main', $data);
    }

    public function settings() {
        $data = [
            'title' => 'Meta WhatsApp Cloud API Settings | Raptor Marketing',
            'active_tab' => 'marketing',
            'webhook_url' => (defined('URLROOT') ? URLROOT : '') . '/index.php?route=whatsapp_webhook/index',
            'verify_token' => WhatsAppService::getWebhookVerifyToken()
        ];

        $this->viewWithLayout('whatsapp/settings', 'main', $data);
    }
}
