<?php
// Sprint 13 - Notification center.

class NotificationsController extends Controller {
    private $notificationModel;

    public function __construct() {
        $this->requireAuth();
        $this->notificationModel = $this->model('Notification');
    }

    public function index() {
        $data = [
            'title' => 'Notifications | Raptor CRM',
            'active_tab' => 'notifications',
            'notifications' => $this->notificationModel->forUser((int) $_SESSION['user_id'], false, 100),
            'unread_count' => $this->notificationModel->unreadCount((int) $_SESSION['user_id']),
            'web_push_enabled' => $this->setting('alerts.web_push_enabled') === '1',
            'vapid_public_key' => $this->setting('alerts.vapid_public_key'),
        ];
        $this->viewWithLayout('notifications/index', 'main', $data);
    }

    public function read($id = 0) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->notificationModel->markRead((int) $_SESSION['user_id'], (int) $id);
        }
        $this->redirect('index.php?route=notifications/index');
    }

    public function readAll() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->notificationModel->markAllRead((int) $_SESSION['user_id']);
        }
        $this->redirect('index.php?route=notifications/index');
    }

    public function subscribe() {
        $this->requireAuthApi();
        $input = $this->jsonInput();
        $ok = $this->notificationModel->storeSubscription(
            (int) $_SESSION['user_id'],
            $input,
            $_SERVER['HTTP_USER_AGENT'] ?? ''
        );
        $ok ? $this->jsonOk(null, 'Push subscription saved.') : $this->jsonError('Invalid push subscription.');
    }

    private function setting(string $key): string {
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare('SELECT setting_value FROM settings WHERE setting_key = :key');
            $stmt->execute([':key' => $key]);
            return (string) ($stmt->fetchColumn() ?: '');
        } catch (Exception $e) {
            return '';
        }
    }
}
