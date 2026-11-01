<?php
/**
 * SocialPublisherController — Social Media Publishing & Meta Lead Ads Gateway
 * -------------------------------------------------------------------------
 * Manages:
 * 1. OAuth connected social accounts (LinkedIn, Facebook, Instagram)
 * 2. Instant and scheduled multi-channel post publishing
 * 3. Inbound Meta Lead Ads webhook capture and synchronizations
 */

class SocialPublisherController extends Controller {

    public function index() {
        $this->requireAuth();
        $db = Database::getInstance()->getConnection();

        // 1. Fetch connected tokens
        $tokens = $db->query("SELECT * FROM social_oauth_tokens ORDER BY platform ASC")->fetchAll(PDO::FETCH_OBJ);

        // 2. Fetch queue items
        $queue = $db->query("SELECT q.*, t.account_name 
                             FROM social_publish_queue q 
                             LEFT JOIN social_oauth_tokens t ON q.token_id = t.token_id 
                             ORDER BY q.created_at DESC LIMIT 50")->fetchAll(PDO::FETCH_OBJ);

        // 3. Fetch Lead Ad campaigns
        $leadAdCamps = $db->query("SELECT * FROM meta_lead_ad_campaigns ORDER BY total_leads_synced DESC")->fetchAll(PDO::FETCH_OBJ);

        $data = [
            'title' => 'Social Media Live Publishing & Ads | Raptor Marketing',
            'active_tab' => 'marketing',
            'tokens' => $tokens ?: [],
            'queue' => $queue ?: [],
            'lead_campaigns' => $leadAdCamps ?: []
        ];

        $this->viewWithLayout('social/publisher', 'main', $data);
    }

    public function schedule() {
        $this->requireAuth();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $platform = trim($_POST['platform'] ?? 'linkedin');
            $text = trim($_POST['message_text'] ?? '');
            $mediaUrl = !empty($_POST['media_url']) ? trim($_POST['media_url']) : null;
            $scheduledAt = !empty($_POST['scheduled_at']) ? trim($_POST['scheduled_at']) : null;
            $publishNow = !empty($_POST['publish_now']);

            if (empty($text)) {
                $_SESSION['flash_error'] = "Post content cannot be empty.";
                $this->redirect('index.php?route=socialPublisher/index');
                return;
            }

            $queueId = SocialMediaService::schedulePost($platform, $text, $mediaUrl, $publishNow ? null : $scheduledAt);

            if ($publishNow && $queueId > 0) {
                SocialMediaService::publishPost($queueId, $platform, $text, $mediaUrl);
                $_SESSION['flash_success'] = "Post published live to " . ucfirst($platform) . "!";
            } else {
                $_SESSION['flash_success'] = "Post scheduled for " . ($scheduledAt ?: 'immediate dispatch') . ".";
            }
        }
        $this->redirect('index.php?route=socialPublisher/index');
    }

    public function leadAds() {
        $this->requireAuth();
        $db = Database::getInstance()->getConnection();

        $campaigns = $db->query("SELECT * FROM meta_lead_ad_campaigns ORDER BY total_leads_synced DESC")->fetchAll(PDO::FETCH_OBJ);
        $recentLeads = $db->query("SELECT c.contact_id, c.first_name, c.last_name, c.company_name, 
                                          c.primary_email, c.primary_phone, c.lead_score, c.created_at,
                                          l.lead_code
                                   FROM contacts c
                                   JOIN leads l ON c.contact_id = l.contact_id
                                   WHERE c.source LIKE 'meta_lead_ad%'
                                   ORDER BY c.created_at DESC LIMIT 50")->fetchAll(PDO::FETCH_OBJ);

        $data = [
            'title' => 'Meta Lead Ads Sync & Capture | Raptor Marketing',
            'active_tab' => 'marketing',
            'campaigns' => $campaigns ?: [],
            'recent_leads' => $recentLeads ?: []
        ];

        $this->viewWithLayout('social/lead_ads', 'main', $data);
    }

    /**
     * Public Webhook endpoint for Meta Lead Ads real-time webhook callbacks.
     */
    public function webhook() {
        header('Content-Type: application/json; charset=utf-8');

        // Meta Webhook Verification Handshake (GET)
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $hubMode = $_GET['hub_mode'] ?? ($_GET['hub.mode'] ?? '');
            $hubChallenge = $_GET['hub_challenge'] ?? ($_GET['hub.challenge'] ?? '');
            $hubVerifyToken = $_GET['hub_verify_token'] ?? ($_GET['hub.verify_token'] ?? '');

            $expectedToken = getenv('META_VERIFY_TOKEN') ?: 'raptor_meta_webhook_secret';

            if ($hubMode === 'subscribe' && $hubVerifyToken === $expectedToken) {
                echo $hubChallenge;
                exit;
            }
            http_response_code(403);
            echo json_encode(['error' => 'Invalid verify token']);
            return;
        }

        // Inbound Lead Generation Event (POST)
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $rawInput = file_get_contents('php://input');
            $data = json_decode($rawInput, true);

            if (empty($data)) {
                $data = $_POST;
            }

            $res = SocialMediaService::ingestMetaLeadAd($data);
            echo json_encode($res);
        }
    }
}

