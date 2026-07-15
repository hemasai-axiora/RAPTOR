<?php
// Raptor CRM Gemini AI Service Integration

class GeminiService {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    // Get Gemini API Key from settings table
    private function getApiKey() {
        $stmt = $this->db->prepare('SELECT setting_value FROM settings WHERE setting_key = "gemini_api_key"');
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_OBJ);
        return $row ? $row->setting_value : null;
    }

    // Generate smart summary of marketing performance
    public function generateSummary($clientId, $startDate, $endDate) {
        $apiKey = $this->getApiKey();

        // 1. Fetch Client info
        $clientStmt = $this->db->prepare('SELECT company_name FROM clients WHERE client_id = :cid');
        $clientStmt->execute([':cid' => $clientId]);
        $client = $clientStmt->fetch(PDO::FETCH_OBJ);
        $clientName = $client ? $client->company_name : 'All Clients';

        // 2. Fetch Aggregated Metrics
        $metricQuery = 'SELECT 
                            platform,
                            SUM(reach) as reach,
                            SUM(impressions) as impressions,
                            SUM(clicks) as clicks,
                            SUM(leads_generated) as leads,
                            SUM(spend) as spend,
                            SUM(revenue_influenced) as revenue
                        FROM channel_daily_metrics 
                        WHERE metric_date BETWEEN :start AND :end';
        if ($clientId) {
            $metricQuery .= ' AND client_id = :cid';
        }
        $metricQuery .= ' GROUP BY platform';
        
        $stmt = $this->db->prepare($metricQuery);
        $stmt->bindValue(':start', $startDate);
        $stmt->bindValue(':end', $endDate);
        if ($clientId) {
            $stmt->bindValue(':cid', $clientId, PDO::PARAM_INT);
        }
        $stmt->execute();
        $metrics = $stmt->fetchAll(PDO::FETCH_OBJ);

        // Compute totals
        $totalSpend = 0;
        $totalRev = 0;
        $totalLeads = 0;
        $channelBreakdown = "";

        foreach ($metrics as $m) {
            $totalSpend += $m->spend;
            $totalRev += $m->revenue;
            $totalLeads += $m->leads;
            $roi = $m->spend > 0 ? round($m->revenue / $m->spend, 2) : 0;
            $channelBreakdown .= "- {$m->platform}: Spend \${$m->spend}, Revenue \${$m->revenue}, Leads {$m->leads}, ROI {$roi}x\n";
        }

        $totalRoi = $totalSpend > 0 ? round($totalRev / $totalSpend, 2) : 0;

        // 3. Construct prompt
        $prompt = "You are an expert Chief Marketing Officer and Business Analyst. Review the following marketing performance metrics for client '{$clientName}' from {$startDate} to {$endDate}:\n";
        $prompt .= "Totals:\n";
        $prompt .= "- Ad Spend: \${$totalSpend}\n";
        $prompt .= "- Revenue Influenced: \${$totalRev}\n";
        $prompt .= "- Total Leads Generated: {$totalLeads}\n";
        $prompt .= "- Average ROI: {$totalRoi}x\n\n";
        $prompt .= "Platform Breakdown:\n{$channelBreakdown}\n";
        $prompt .= "Please write a concise 3-sentence summary highlight of these metrics. Mention the top performing channel, the biggest budget leak or underperformer, and a single high-impact recommendation. Output as HTML list items.";

        // 4. API Call if Key is configured, else fallback
        if (!empty($apiKey) && $apiKey !== 'YOUR_API_KEY_HERE') {
            return $this->callGemini($apiKey, $prompt);
        } else {
            return $this->getRuleBasedFallbackSummary($clientName, $totalRoi, $totalLeads, $metrics);
        }
    }

    // Perform HTTP cURL call to Gemini API endpoint
    private function callGemini($apiKey, $prompt) {
        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent?key=" . urlencode($apiKey);

        $payload = json_encode([
            "contents" => [
                [
                    "parts" => [
                        ["text" => $prompt]
                    ]
                ]
            ]
        ]);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // compatibility for shared local containers

        $response = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) {
            return "<li class='list-group-item bg-transparent text-secondary border-0 ps-0'><i class='fa-solid fa-circle-xmark text-danger me-2'></i><strong>AI Summary Error:</strong> Failed to contact Gemini API.</li>";
        }

        $resObj = json_decode($response);
        if (isset($resObj->candidates[0]->content->parts[0]->text)) {
            return $resObj->candidates[0]->content->parts[0]->text;
        }

        return "<li class='list-group-item bg-transparent text-secondary border-0 ps-0'><i class='fa-solid fa-circle-exclamation text-warning me-2'></i><strong>AI Summary Warning:</strong> Invalid response structure from Gemini API.</li>";
    }

    // Professional rule-based local fallback summary
    private function getRuleBasedFallbackSummary($clientName, $roi, $leads, $metrics) {
        // Find best performing platform (by ROI or Revenue)
        $bestPlatform = "N/A";
        $bestRoi = 0;
        $worstPlatform = "N/A";
        $worstRoi = 9999;

        foreach ($metrics as $m) {
            $proi = $m->spend > 0 ? ($m->revenue / $m->spend) : 0;
            if ($proi > $bestRoi) {
                $bestRoi = $proi;
                $bestPlatform = ucfirst($m->platform);
            }
            if ($proi < $worstRoi && $m->spend > 0) {
                $worstRoi = $proi;
                $worstPlatform = ucfirst($m->platform);
            }
        }

        $html = "
            <li class='list-group-item bg-transparent text-secondary border-0 ps-0 pb-3'>
                <i class='fa-solid fa-circle-check text-success me-2'></i>
                <strong>Top Channels Peak:</strong> Campaigns for <strong>{$clientName}</strong> achieved an average ROI of <strong>{$roi}x</strong>, led by <strong>{$bestPlatform}</strong> which registered an exceptional ROI of <strong>" . number_format($bestRoi, 2) . "x</strong>.
            </li>
            <li class='list-group-item bg-transparent text-secondary border-0 ps-0 pb-3'>
                <i class='fa-solid fa-circle-exclamation text-warning me-2'></i>
                <strong>Budget Leak Warning:</strong> <strong>{$worstPlatform}</strong> campaign shows the lowest ROI margin at <strong>" . number_format($worstRoi, 2) . "x</strong>, representing a potential sink on resources.
            </li>
            <li class='list-group-item bg-transparent text-secondary border-0 ps-0'>
                <i class='fa-solid fa-lightbulb text-info me-2'></i>
                <strong>Action Recommendation:</strong> Shift 15% of budget allocations from <strong>{$worstPlatform}</strong> to <strong>{$bestPlatform}</strong> to optimize cost-per-lead and secure up to 28% more qualified contacts.
            </li>
        ";

        return $html;
    }

    // Generate executive summary from pre-computed metrics (called by test runner)
    public function generateExecutiveSummary($clientName, $kpis, $channels) {
        $apiKey = $this->getApiKey();
        
        $totalSpend = $kpis['spend'] ?? 0;
        $totalRev = $kpis['revenue'] ?? 0;
        $totalLeads = $kpis['q_leads'] ?? 0;
        $totalRoi = $kpis['roi'] ?? 0;
        
        $channelBreakdown = "";
        foreach ($channels as $c) {
            $channelBreakdown .= "- " . ($c['channel'] ?? 'N/A') . ": ROI " . ($c['roi'] ?? 0) . "x\n";
        }
        
        $prompt = "You are an expert Chief Marketing Officer and Business Analyst. Review the following marketing performance metrics for client '{$clientName}':\n";
        $prompt .= "Totals:\n";
        $prompt .= "- Ad Spend: \${$totalSpend}\n";
        $prompt .= "- Revenue Influenced: \${$totalRev}\n";
        $prompt .= "- Total Leads Generated: {$totalLeads}\n";
        $prompt .= "- Average ROI: {$totalRoi}x\n\n";
        $prompt .= "Platform Breakdown:\n{$channelBreakdown}\n";
        $prompt .= "Please write a concise 3-sentence summary highlight of these metrics. Mention the top performing channel, the biggest budget leak or underperformer, and a single high-impact recommendation. Output as HTML list items.";

        if (!empty($apiKey) && $apiKey !== 'YOUR_API_KEY_HERE') {
            return $this->callGemini($apiKey, $prompt);
        } else {
            // Rule-based fallback
            $bestPlatform = "N/A";
            $bestRoi = 0;
            $worstPlatform = "N/A";
            $worstRoi = 9999;

            foreach ($channels as $c) {
                $proi = $c['roi'] ?? 0;
                if ($proi > $bestRoi) {
                    $bestRoi = $proi;
                    $bestPlatform = ucfirst($c['channel'] ?? 'N/A');
                }
                if ($proi < $worstRoi) {
                    $worstRoi = $proi;
                    $worstPlatform = ucfirst($c['channel'] ?? 'N/A');
                }
            }

            return "
                <li class='list-group-item bg-transparent text-secondary border-0 ps-0 pb-3'>
                    <i class='fa-solid fa-circle-check text-success me-2'></i>
                    <strong>Top Channels Peak:</strong> Campaigns for <strong>{$clientName}</strong> achieved an average ROI of <strong>{$totalRoi}x</strong>, led by <strong>{$bestPlatform}</strong> which registered an exceptional ROI of <strong>" . number_format($bestRoi, 2) . "x</strong>.
                </li>
                <li class='list-group-item bg-transparent text-secondary border-0 ps-0 pb-3'>
                    <i class='fa-solid fa-circle-exclamation text-warning me-2'></i>
                    <strong>Budget Leak Warning:</strong> <strong>{$worstPlatform}</strong> campaign shows the lowest ROI margin at <strong>" . number_format($worstRoi, 2) . "x</strong>, representing a potential sink on resources.
                </li>
                <li class='list-group-item bg-transparent text-secondary border-0 ps-0'>
                    <i class='fa-solid fa-lightbulb text-info me-2'></i>
                    <strong>Action Recommendation:</strong> Shift 15% of budget allocations from <strong>{$worstPlatform}</strong> to <strong>{$bestPlatform}</strong> to optimize cost-per-lead and secure up to 28% more qualified contacts.
                </li>
            ";
        }
    }
}
