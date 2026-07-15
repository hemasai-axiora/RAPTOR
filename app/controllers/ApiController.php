<?php
// Raptor CRM Rest API Controller for Dashboard Metrics

class ApiController extends Controller {
    private $db;

    public function __construct() {
        $this->requireAuth();
        $this->db = Database::getInstance()->getConnection();
        header('Content-Type: application/json');
    }

    // Helper to extract client and date filters from request
    private function getFilters() {
        $clientId = isset($_GET['client_id']) && $_GET['client_id'] !== 'all' ? (int)$_GET['client_id'] : null;
        
        $startDate = !empty($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
        $endDate = !empty($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
        
        return [
            'client_id' => $clientId,
            'start_date' => $startDate,
            'end_date' => $endDate
        ];
    }

    // JSON endpoint for Executive overview dashboard metrics
    public function executive() {
        $filters = $this->getFilters();
        
        $cid = $filters['client_id'];
        $start = $filters['start_date'];
        $end = $filters['end_date'];

        // 1. Fetch Aggregated Metrics
        $metricQuery = 'SELECT 
                            SUM(reach) as total_reach,
                            SUM(impressions) as total_impressions,
                            SUM(engagements) as total_engagements,
                            SUM(clicks) as total_clicks,
                            SUM(leads_generated) as total_leads,
                            SUM(spend) as total_spend,
                            SUM(revenue_influenced) as total_rev
                        FROM channel_daily_metrics 
                        WHERE metric_date BETWEEN :start AND :end';
        if ($cid) {
            $metricQuery .= ' AND client_id = :cid';
        }
        
        $stmt = $this->db->prepare($metricQuery);
        $stmt->bindValue(':start', $start);
        $stmt->bindValue(':end', $end);
        if ($cid) {
            $stmt->bindValue(':cid', $cid, PDO::PARAM_INT);
        }
        $stmt->execute();
        $totals = $stmt->fetch(PDO::FETCH_OBJ);

        // Fallbacks if empty
        $spend = $totals->total_spend ?: 0;
        $rev = $totals->total_rev ?: 0;
        $leads = $totals->total_leads ?: 0;
        $clicks = $totals->total_clicks ?: 0;
        $impressions = $totals->total_impressions ?: 0;

        // 2. Qualified Leads Count
        $leadQuery = 'SELECT COUNT(lead_id) as q_leads FROM leads WHERE status = "qualified" AND created_at BETWEEN :start AND :end';
        if ($cid) $leadQuery .= ' AND client_id = :cid';
        $leadStmt = $this->db->prepare($leadQuery);
        $leadStmt->bindValue(':start', $start . ' 00:00:00');
        $leadStmt->bindValue(':end', $end . ' 23:59:59');
        if ($cid) $leadStmt->bindValue(':cid', $cid, PDO::PARAM_INT);
        $leadStmt->execute();
        $qLeadsObj = $leadStmt->fetch(PDO::FETCH_OBJ);
        $qLeads = $qLeadsObj->q_leads ?: 0;

        // 3. Health Score Calculations
        $roi = $spend > 0 ? ($rev / $spend) : 0;
        $cac = $qLeads > 0 ? ($spend / ($qLeads * 0.4)) : 0;
        $convRate = $clicks > 0 ? ($leads / ($clicks * 1.5)) * 100 : 0;
        $healthScore = min(100, max(50, 75 + ($roi * 3) - ($cac / 80) + ($convRate / 2)));

        // 4. Sparkline Trends (Daily Revenue list)
        $trendQuery = 'SELECT metric_date, SUM(revenue_influenced) as daily_rev, SUM(spend) as daily_spend 
                       FROM channel_daily_metrics 
                       WHERE metric_date BETWEEN :start AND :end';
        if ($cid) $trendQuery .= ' AND client_id = :cid';
        $trendQuery .= ' GROUP BY metric_date ORDER BY metric_date ASC';
        
        $trendStmt = $this->db->prepare($trendQuery);
        $trendStmt->bindValue(':start', $start);
        $trendStmt->bindValue(':end', $end);
        if ($cid) $trendStmt->bindValue(':cid', $cid, PDO::PARAM_INT);
        $trendStmt->execute();
        $trends = $trendStmt->fetchAll(PDO::FETCH_OBJ);

        // 5. Campaigns List
        $campQuery = 'SELECT c.*, cl.company_name FROM campaigns c JOIN clients cl ON c.client_id = cl.client_id';
        if ($cid) $campQuery .= ' WHERE c.client_id = :cid';
        $campQuery .= ' ORDER BY c.roi DESC LIMIT 5';
        $campStmt = $this->db->prepare($campQuery);
        if ($cid) $campStmt->bindValue(':cid', $cid, PDO::PARAM_INT);
        $campStmt->execute();
        $campaignsList = $campStmt->fetchAll(PDO::FETCH_OBJ);

        // 6. Smart Alerts
        $alertQuery = 'SELECT * FROM smart_alerts WHERE status = "active"';
        if ($cid) $alertQuery .= ' AND client_id = :cid';
        $alertQuery .= ' ORDER BY created_at DESC LIMIT 3';
        $alertStmt = $this->db->prepare($alertQuery);
        if ($cid) $alertStmt->bindValue(':cid', $cid, PDO::PARAM_INT);
        $alertStmt->execute();
        $alerts = $alertStmt->fetchAll(PDO::FETCH_OBJ);

        // 7. AI Summary Analysis (Gemini Integration)
        require_once dirname(dirname(__FILE__)) . '/core/GeminiService.php';
        $gemini = new GeminiService();
        $aiSummary = $gemini->generateSummary($cid, $start, $end);

        // 8. Social Media Analytics
        require_once dirname(dirname(__FILE__)) . '/models/AnalyticsEntry.php';
        $analyticsModel = new AnalyticsEntry();
        $socialMetrics = $analyticsModel->getDashboardMetrics();
        $platformAnalytics = $analyticsModel->getPlatformAnalytics();
        $accountAnalytics = $analyticsModel->getAccountAnalytics();

        // Output consolidated package
        echo json_encode([
            'kpis' => [
                'health_score' => round($healthScore),
                'roi' => round($roi, 2),
                'revenue' => $rev,
                'q_leads' => $qLeads,
                'cac' => round($cac, 2),
                'conv_rate' => round($convRate, 2),
                'spend' => $spend
            ],
            'trends' => $trends,
            'campaigns' => $campaignsList,
            'alerts' => $alerts,
            'ai_summary' => $aiSummary,
            'social_metrics' => $socialMetrics,
            'platform_analytics' => $platformAnalytics,
            'account_analytics' => $accountAnalytics
        ]);
    }

    // JSON endpoint for Campaign & Platform performance
    public function channels() {
        $filters = $this->getFilters();
        $cid = $filters['client_id'];
        $start = $filters['start_date'];
        $end = $filters['end_date'];

        // 1. Platform Performance Summary Grid
        $platformQuery = 'SELECT 
                            platform, 
                            SUM(reach) as total_reach,
                            SUM(engagements) as total_engagements,
                            SUM(clicks) as total_clicks,
                            SUM(leads_generated) as total_leads,
                            SUM(spend) as total_spend,
                            SUM(revenue_influenced) as total_rev
                          FROM channel_daily_metrics 
                          WHERE metric_date BETWEEN :start AND :end';
        if ($cid) $platformQuery .= ' AND client_id = :cid';
        $platformQuery .= ' GROUP BY platform ORDER BY total_rev DESC';

        $pStmt = $this->db->prepare($platformQuery);
        $pStmt->bindValue(':start', $start);
        $pStmt->bindValue(':end', $end);
        if ($cid) $pStmt->bindValue(':cid', $cid, PDO::PARAM_INT);
        $pStmt->execute();
        $platformStats = $pStmt->fetchAll(PDO::FETCH_OBJ);

        // 2. Best Posting Time Heatmap
        $heatmapQuery = 'SELECT day_of_week, hour_of_day, AVG(avg_engagement_rate) as engagement 
                         FROM best_posting_time_metrics';
        if ($cid) $heatmapQuery .= ' WHERE client_id = :cid';
        $heatmapQuery .= ' GROUP BY day_of_week, hour_of_day ORDER BY day_of_week, hour_of_day';
        $hStmt = $this->db->prepare($heatmapQuery);
        if ($cid) $hStmt->bindValue(':cid', $cid, PDO::PARAM_INT);
        $hStmt->execute();
        $heatmap = $hStmt->fetchAll(PDO::FETCH_OBJ);

        // 3. Traffic sources percentages
        $trafficQuery = 'SELECT platform, SUM(clicks) as clicks FROM channel_daily_metrics WHERE metric_date BETWEEN :start AND :end';
        if ($cid) $trafficQuery .= ' AND client_id = :cid';
        $trafficQuery .= ' GROUP BY platform';
        $tStmt = $this->db->prepare($trafficQuery);
        $tStmt->bindValue(':start', $start);
        $tStmt->bindValue(':end', $end);
        if ($cid) $tStmt->bindValue(':cid', $cid, PDO::PARAM_INT);
        $tStmt->execute();
        $traffic = $tStmt->fetchAll(PDO::FETCH_OBJ);

        echo json_encode([
            'platforms' => $platformStats,
            'heatmap' => $heatmap,
            'traffic' => $traffic
        ]);
    }

    // JSON endpoint for Customer Intelligence
    public function customer() {
        $filters = $this->getFilters();
        $cid = $filters['client_id'];
        $start = $filters['start_date'];
        $end = $filters['end_date'];

        // 1. Demographics Snaps
        $demoQuery = 'SELECT dimension_type, dimension_label, percentage 
                      FROM audience_demographics_snapshots 
                      WHERE recorded_date = (SELECT MAX(recorded_date) FROM audience_demographics_snapshots)';
        if ($cid) $demoQuery .= ' AND client_id = :cid';
        $dStmt = $this->db->prepare($demoQuery);
        if ($cid) $dStmt->bindValue(':cid', $cid, PDO::PARAM_INT);
        $dStmt->execute();
        $demos = $dStmt->fetchAll(PDO::FETCH_OBJ);

        // 2. Sentiment Logs
        $sentimentQuery = 'SELECT recorded_date, positive_score, neutral_score, negative_score 
                           FROM brand_sentiment_logs 
                           WHERE recorded_date BETWEEN :start AND :end';
        if ($cid) $sentimentQuery .= ' AND client_id = :cid';
        $sentimentQuery .= ' ORDER BY recorded_date ASC';
        $sStmt = $this->db->prepare($sentimentQuery);
        $sStmt->bindValue(':start', $start);
        $sStmt->bindValue(':end', $end);
        if ($cid) $sStmt->bindValue(':cid', $cid, PDO::PARAM_INT);
        $sStmt->execute();
        $sentiments = $sStmt->fetchAll(PDO::FETCH_OBJ);

        // 3. Competitor Benchmarks
        $compQuery = 'SELECT metric_name, our_metric_value, competitor_avg_value, vs_competitor_percentage 
                      FROM competitor_benchmarks 
                      WHERE recorded_date = (SELECT MAX(recorded_date) FROM competitor_benchmarks)';
        if ($cid) $compQuery .= ' AND client_id = :cid';
        $cStmt = $this->db->prepare($compQuery);
        if ($cid) $cStmt->bindValue(':cid', $cid, PDO::PARAM_INT);
        $cStmt->execute();
        $benchmarks = $cStmt->fetchAll(PDO::FETCH_OBJ);

        // 4. Lead Quality Breakdown
        $leadQualQuery = 'SELECT lead_quality, COUNT(lead_id) as count FROM leads WHERE created_at BETWEEN :start AND :end';
        if ($cid) $leadQualQuery .= ' AND client_id = :cid';
        $leadQualQuery .= ' GROUP BY lead_quality';
        $lqStmt = $this->db->prepare($leadQualQuery);
        $lqStmt->bindValue(':start', $start . ' 00:00:00');
        $lqStmt->bindValue(':end', $end . ' 23:59:59');
        if ($cid) $lqStmt->bindValue(':cid', $cid, PDO::PARAM_INT);
        $lqStmt->execute();
        $quality = $lqStmt->fetchAll(PDO::FETCH_OBJ);

        echo json_encode([
            'demographics' => $demos,
            'sentiments' => $sentiments,
            'benchmarks' => $benchmarks,
            'quality' => $quality
        ]);
    }
}
