<?php
/**
 * InboxController — Omnichannel Shared Team Inbox
 * -------------------------------------------------------------------------
 * Manages:
 * 1. Multi-channel customer conversations (WhatsApp, Email, SMS, CRM Calls)
 * 2. Internal private team notes (Yellow notes hidden from customers)
 * 3. Thread assignments, status state transitions, and priority tagging
 * 4. Canned reply shortcuts (/pricing, /demo, /intro)
 * 5. Customer 360 live context integration
 */

class InboxController extends Controller {

    private $threadModel;
    private $messageModel;
    private $cannedModel;
    private $contactModel;
    private $userModel;

    public function __construct() {
        $this->requireAuth();
        $this->threadModel = $this->model('InboxThread');
        $this->messageModel = $this->model('InboxMessage');
        $this->cannedModel = $this->model('CannedReply');
        $this->contactModel = $this->model('Contact');
        $this->userModel = $this->model('User');
    }

    public function index() {
        $currentUserId = (int)($_SESSION['user_id'] ?? 0);
        $tab = $_GET['tab'] ?? 'all'; // all, mine, unassigned, resolved

        $filters = [
            'tab' => $tab,
            'current_user_id' => $currentUserId,
            'search' => $_GET['search'] ?? '',
            'priority' => $_GET['priority'] ?? ''
        ];

        $threads = $this->threadModel->getThreads($filters, 50);

        // Selected active thread
        $activeThreadId = !empty($_GET['thread_id']) ? (int)$_GET['thread_id'] : (!empty($threads[0]) ? (int)$threads[0]->thread_id : 0);
        $activeThread = $activeThreadId > 0 ? $this->threadModel->getById($activeThreadId) : null;

        $messages = [];
        $contact = null;
        if ($activeThread) {
            $this->threadModel->markRead($activeThreadId);
            $messages = $this->messageModel->getMessagesForThread($activeThreadId, 150);
            $contact = $this->contactModel->getById((int)$activeThread->contact_id);
        }

        $cannedReplies = $this->cannedModel->getAll();
        $users = $this->userModel->getUsers() ?: [];

        $data = [
            'title' => 'Omnichannel Shared Inbox | Raptor Marketing',
            'active_tab' => 'marketing',
            'threads' => $threads,
            'active_thread' => $activeThread,
            'messages' => $messages,
            'contact' => $contact,
            'canned_replies' => $cannedReplies,
            'users' => $users,
            'current_tab' => $tab,
            'filters' => $filters,
            'statuses' => InboxThread::STATUSES,
            'priorities' => InboxThread::PRIORITIES
        ];

        $this->viewWithLayout('inbox/index', 'main', $data);
    }

    public function send() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $threadId = (int)($_POST['thread_id'] ?? 0);
            $thread = $this->threadModel->getById($threadId);

            if (!$thread) {
                $_SESSION['flash_error'] = "Conversation thread not found.";
                $this->redirect('index.php?route=inbox/index');
                return;
            }

            $body = trim($_POST['body'] ?? '');
            if (empty($body)) {
                $_SESSION['flash_error'] = "Message content cannot be empty.";
                $this->redirect('index.php?route=inbox/index&thread_id=' . $threadId);
                return;
            }

            $currentUserId = (int)($_SESSION['user_id'] ?? 0);
            $isPrivateNote = !empty($_POST['is_private_note']) ? 1 : 0;
            $channel = trim($_POST['channel'] ?? 'whatsapp');

            // 1. Internal Private Team Note
            if ($isPrivateNote) {
                $this->messageModel->recordMessage([
                    'thread_id' => $threadId,
                    'contact_id' => (int)$thread->contact_id,
                    'channel' => 'note',
                    'direction' => 'internal',
                    'is_private_note' => 1,
                    'body' => $body,
                    'sender_user_id' => $currentUserId,
                    'status' => 'sent'
                ]);
                $_SESSION['flash_success'] = "Internal note posted to team thread.";
                $this->redirect('index.php?route=inbox/index&thread_id=' . $threadId);
                return;
            }

            // 2. Outbound Customer Communication (Pre-send suppression check)
            $contact = $this->contactModel->getById((int)$thread->contact_id);
            if ($contact && (int)$contact->is_suppressed === 1) {
                $_SESSION['flash_error'] = "Blocked: Contact is globally suppressed/opted-out. Outbound customer messaging is prohibited.";
                $this->redirect('index.php?route=inbox/index&thread_id=' . $threadId);
                return;
            }

            $externalId = null;
            if ($channel === 'whatsapp' && !empty($thread->primary_phone)) {
                $res = WhatsAppService::sendTextMessage($thread->primary_phone, $body);
                if (!$res['success']) {
                    $_SESSION['flash_error'] = "WhatsApp dispatch failed: " . ($res['error'] ?? 'Unknown error');
                    $this->redirect('index.php?route=inbox/index&thread_id=' . $threadId);
                    return;
                }
                $externalId = $res['meta_message_id'] ?? null;
            }

            // Record outbound message in unified stream
            $this->messageModel->recordMessage([
                'thread_id' => $threadId,
                'contact_id' => (int)$thread->contact_id,
                'channel' => $channel,
                'direction' => 'outbound',
                'is_private_note' => 0,
                'body' => $body,
                'sender_user_id' => $currentUserId,
                'external_id' => $externalId,
                'status' => 'sent'
            ]);

            $_SESSION['flash_success'] = "Message dispatched via " . ucfirst($channel) . ".";
            $this->redirect('index.php?route=inbox/index&thread_id=' . $threadId);
        }
    }

    public function assign() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $threadId = (int)($_POST['thread_id'] ?? 0);
            $userId = !empty($_POST['assigned_user_id']) ? (int)$_POST['assigned_user_id'] : null;

            if ($threadId > 0) {
                $this->threadModel->assignRep($threadId, $userId);
                $_SESSION['flash_success'] = "Agent assignment updated.";
            }
            $this->redirect('index.php?route=inbox/index&thread_id=' . $threadId);
        }
    }

    public function status() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $threadId = (int)($_POST['thread_id'] ?? 0);
            $status = trim($_POST['status'] ?? '');
            $userId = (int)($_SESSION['user_id'] ?? 0);

            if ($threadId > 0 && !empty($status)) {
                $this->threadModel->updateStatus($threadId, $status, $userId);
                $_SESSION['flash_success'] = "Thread status updated to [" . strtoupper($status) . "].";
            }
            $this->redirect('index.php?route=inbox/index&thread_id=' . $threadId);
        }
    }

    public function priority() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $threadId = (int)($_POST['thread_id'] ?? 0);
            $priority = trim($_POST['priority'] ?? '');

            if ($threadId > 0 && !empty($priority)) {
                $this->threadModel->updatePriority($threadId, $priority);
                $_SESSION['flash_success'] = "Priority updated to [" . strtoupper($priority) . "].";
            }
            $this->redirect('index.php?route=inbox/index&thread_id=' . $threadId);
        }
    }

    public function cannedReplies() {
        $canned = $this->cannedModel->getAll();
        $data = [
            'title' => 'Canned Quick Replies | Raptor Marketing',
            'active_tab' => 'marketing',
            'canned' => $canned
        ];
        $this->viewWithLayout('inbox/canned_replies', 'main', $data);
    }

    public function saveCanned() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $shortcut = trim($_POST['shortcut'] ?? '');
            $title = trim($_POST['title'] ?? '');
            $category = trim($_POST['category'] ?? 'general');
            $body = trim($_POST['body_text'] ?? '');
            $userId = (int)($_SESSION['user_id'] ?? 0);

            if (!empty($shortcut) && !empty($title) && !empty($body)) {
                $this->cannedModel->create($shortcut, $title, $category, $body, $userId);
                $_SESSION['flash_success'] = "Canned quick reply saved.";
            } else {
                $_SESSION['flash_error'] = "Shortcut, Title, and Response text are required.";
            }
        }
        $this->redirect('index.php?route=inbox/cannedReplies');
    }
}

