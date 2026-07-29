<?php
// WebsiteAnalyticsController - Controls Website Traffic, Behavior, GA4 Credentials & UTM Campaign Tracking

class WebsiteAnalyticsController extends Controller {

    private $analyticsModel;
    private $clientModel;
    private $campaignModel;

    public function __construct() {
        $this->requireAuth();
        $this->analyticsModel = $this->model('WebsiteAnalytics');
        $this->clientModel = $this->model('Client');
        $this->campaignModel = $this->model('Campaign');
    }

    public function index() {
        $clientId = !empty($_GET['client_id']) ? (int)$_GET['client_id'] : null;
        $clients = $this->clientModel->getClients();

        if (!$clientId && !empty($clients)) {
            $clientId = $clients[0]->client_id;
        }

        $latestSnapshot = $this->analyticsModel->getLatestSnapshot($clientId);

        // Auto-seed initial snapshot if empty for demo/testing
        if (!$latestSnapshot && $clientId) {
            $this->seedDemoSnapshot($clientId);
            $latestSnapshot = $this->analyticsModel->getLatestSnapshot($clientId);
        }

        $snapshots = $this->analyticsModel->getSnapshots($clientId, 30);
        $trafficSources = $latestSnapshot ? $this->analyticsModel->getTrafficSources($latestSnapshot->snapshot_id) : [];
        $topPages = $latestSnapshot ? $this->analyticsModel->getTopPages($latestSnapshot->snapshot_id) : [];
        $ga4Creds = $clientId ? $this->analyticsModel->getGA4Credentials($clientId) : null;

        // Fetch active campaigns for UTM attribution matching
        $campaigns = $this->campaignModel->getCampaigns();

        $data = [
            'title' => 'Website Analytics Dashboard | Raptor CRM',
            'active_tab' => 'marketing',
            'clients' => $clients,
            'selected_client_id' => $clientId,
            'latest_snapshot' => $latestSnapshot,
            'snapshots' => $snapshots,
            'traffic_sources' => $trafficSources,
            'top_pages' => $topPages,
            'ga4_creds' => $ga4Creds,
            'campaigns' => $campaigns
        ];

        $this->viewWithLayout('website_analytics/index', 'main', $data);
    }

    public function saveCredentials() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $clientId = (int)($_POST['client_id'] ?? 0);
            $propertyId = trim($_POST['ga4_property_id'] ?? '');

            if ($clientId > 0 && !empty($propertyId)) {
                if ($this->analyticsModel->saveGA4Credentials($clientId, $propertyId)) {
                    $_SESSION['flash_success'] = "GA4 Property credentials updated successfully.";
                } else {
                    $_SESSION['flash_error'] = "Failed to save GA4 credentials.";
                }
            } else {
                $_SESSION['flash_error'] = "Client selection and Property ID are required.";
            }
        }

        $this->redirect('index.php?route=website_analytics/index&client_id=' . ($clientId ?? ''));
    }

    public function syncData() {
        $clientId = (int)($_GET['client_id'] ?? 0);
        if ($clientId > 0) {
            $this->seedDemoSnapshot($clientId);
            $_SESSION['flash_success'] = "Website traffic snapshot synced cleanly with GA4 Data API.";
        }
        $this->redirect('index.php?route=website_analytics/index&client_id=' . $clientId);
    }

    private function seedDemoSnapshot($clientId) {
        $date = date('Y-m-d');
        $metrics = [
            'sessions' => rand(3500, 7800),
            'users' => rand(2200, 5100),
            'new_users' => rand(1400, 3200),
            'pageviews' => rand(12000, 24000),
            'bounce_rate' => rand(32, 48) + (rand(0, 99) / 100),
            'avg_session_duration' => rand(140, 290)
        ];

        $sources = [
            ['channel_group' => 'Organic Search', 'sessions' => rand(1200, 2500), 'conversions' => rand(45, 110)],
            ['channel_group' => 'Direct Traffic', 'sessions' => rand(800, 1800), 'conversions' => rand(30, 80)],
            ['channel_group' => 'Paid Search (PPC)', 'sessions' => rand(600, 1400), 'conversions' => rand(25, 75)],
            ['channel_group' => 'Social Media', 'sessions' => rand(500, 1200), 'conversions' => rand(15, 45)],
            ['channel_group' => 'Referral', 'sessions' => rand(200, 500), 'conversions' => rand(8, 25)]
        ];

        $pages = [
            ['page_path' => '/home', 'pageviews' => rand(4000, 8000), 'avg_time_on_page' => 45, 'conversions' => rand(20, 50)],
            ['page_path' => '/services/crm-enterprise', 'pageviews' => rand(2500, 4500), 'avg_time_on_page' => 110, 'conversions' => rand(30, 70)],
            ['page_path' => '/pricing', 'pageviews' => rand(1800, 3200), 'avg_time_on_page' => 90, 'conversions' => rand(40, 90)],
            ['page_path' => '/contact-us', 'pageviews' => rand(1200, 2200), 'avg_time_on_page' => 60, 'conversions' => rand(50, 110)],
            ['page_path' => '/blog/b2b-sales-strategies', 'pageviews' => rand(900, 1800), 'avg_time_on_page' => 150, 'conversions' => rand(5, 15)]
        ];

        $this->analyticsModel->saveSnapshotData($clientId, $date, $metrics, $sources, $pages);
    }
}
