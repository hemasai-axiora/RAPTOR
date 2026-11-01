<?php
/**
 * Raptor CRM - Public Unsubscribe Controller
 * 
 * Handles RFC 8058 One-Click Unsubscribe and browser preference center.
 * Public endpoint: does NOT require authentication or session login.
 */

class UnsubscribeController extends Controller {

    /**
     * Browser view for unsubscribe confirmation and preference center.
     */
    public function view() {
        $token = trim($_GET['token'] ?? '');
        $campaignId = (int)($_GET['c'] ?? 0);
        $listId = (int)($_GET['l'] ?? 0);

        if (empty($token)) {
            die('Invalid or missing unsubscribe token.');
        }

        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare('SELECT r.email, c.name as campaign_name, l.name as list_name, l.list_id
                              FROM campaign_recipients r
                              LEFT JOIN campaigns_broadcast c ON r.campaign_id = c.campaign_id
                              LEFT JOIN campaign_lists l ON c.list_id = l.list_id
                              WHERE r.unsubscribe_token = :token LIMIT 1');
        $stmt->execute([':token' => $token]);
        $recip = $stmt->fetch(PDO::FETCH_OBJ);

        if (!$recip) {
            die('Unsubscribe link is invalid or has expired.');
        }

        $data = [
            'token' => $token,
            'email' => $recip->email,
            'campaign_name' => $recip->campaign_name ?? 'Email Broadcast',
            'list_name' => $recip->list_name ?? 'General Newsletter',
            'list_id' => (int)($recip->list_id ?? $listId)
        ];

        // Standalone clean view without main admin sidebar layout
        $this->view('email_campaigns/public/unsubscribe', $data);
    }

    /**
     * Handle browser unsubscribe submission.
     */
    public function action() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $token = trim($_POST['token'] ?? '');
            $scope = in_array($_POST['scope'] ?? '', ['global', 'list'], true) ? $_POST['scope'] : 'global';
            $listId = (int)($_POST['list_id'] ?? 0);

            $res = UnsubscribeService::processUnsubscribe($token, $scope, $listId);

            $data = [
                'success' => $res['success'],
                'message' => $res['message'] ?? ($res['error'] ?? 'An error occurred.'),
                'email' => $res['email'] ?? ''
            ];

            $this->view('email_campaigns/public/unsubscribe_success', $data);
            return;
        }

        $this->redirect('index.php');
    }

    /**
     * RFC 8058 One-Click HTTP POST Endpoint.
     * Standard mail user agents (e.g. Gmail / Yahoo / Apple Mail) send POST request with:
     * Body: List-Unsubscribe=One-Click
     */
    public function rfc8058() {
        $token = trim($_GET['token'] ?? ($_POST['token'] ?? ''));

        if (empty($token)) {
            http_response_code(400);
            header('Content-Type: text/plain');
            echo 'Missing unsubscribe token';
            exit();
        }

        $res = UnsubscribeService::processUnsubscribe($token, 'global');

        if ($res['success']) {
            http_response_code(200);
            header('Content-Type: text/plain');
            echo 'Successfully unsubscribed';
        } else {
            http_response_code(400);
            header('Content-Type: text/plain');
            echo 'Unsubscribe error: ' . ($res['error'] ?? 'Invalid token');
        }
        exit();
    }
}

