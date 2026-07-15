<?php
// Raptor CRM API Connectors & Simulation Engine

abstract class ApiConnector {
    protected $client_id;
    protected $platform;
    protected $db;

    public function __construct($clientId, $platform) {
        $this->client_id = $clientId;
        $this->platform = $platform;
        $this->db = Database::getInstance()->getConnection();
    }

    // Abstract method to fetch metrics for a given date
    abstract public function fetchDailyMetrics($date);

    // Abstract method to fetch post metrics
    abstract public function fetchPostPerformance($date);
}

// Meta Graph Connector (Facebook & Instagram)
class MetaConnector extends ApiConnector {
    public function fetchDailyMetrics($date) {
        // In production: Guzzle HTTP call to Graph API /{page-id}/insights
        // For development: Generate realistic fluctuations based on client profile
        $baseReach = rand(15000, 25000);
        $impressions = $baseReach * rand(12, 18) / 10;
        $engagements = $baseReach * rand(5, 8) / 100;
        $clicks = $engagements * rand(25, 35) / 100;
        $leads = $clicks * rand(8, 15) / 100;
        $spend = rand(150, 250);
        $revenue = $leads * rand(120, 200);

        return [
            'reach' => (int)$baseReach,
            'impressions' => (int)$impressions,
            'engagements' => (int)$engagements,
            'clicks' => (int)$clicks,
            'leads_generated' => (int)$leads,
            'spend' => (float)$spend,
            'revenue_influenced' => (float)$revenue
        ];
    }

    public function fetchPostPerformance($date) {
        return [
            'reach' => rand(1200, 3000),
            'impressions' => rand(1500, 4500),
            'engagements' => rand(80, 250),
            'clicks' => rand(15, 60)
        ];
    }
}

// LinkedIn API Connector
class LinkedInConnector extends ApiConnector {
    public function fetchDailyMetrics($date) {
        $baseReach = rand(5000, 10000);
        $impressions = $baseReach * rand(11, 14) / 10;
        $engagements = $baseReach * rand(3, 6) / 100;
        $clicks = $engagements * rand(20, 30) / 100;
        $leads = $clicks * rand(12, 22) / 100; // Higher B2B lead rate
        $spend = rand(200, 350); // Higher B2B cost
        $revenue = $leads * rand(300, 600); // Higher deal value

        return [
            'reach' => (int)$baseReach,
            'impressions' => (int)$impressions,
            'engagements' => (int)$engagements,
            'clicks' => (int)$clicks,
            'leads_generated' => (int)$leads,
            'spend' => (float)$spend,
            'revenue_influenced' => (float)$revenue
        ];
    }

    public function fetchPostPerformance($date) {
        return [
            'reach' => rand(800, 2000),
            'impressions' => rand(1000, 2800),
            'engagements' => rand(40, 120),
            'clicks' => rand(10, 45)
        ];
    }
}

// Google Ads & Analytics Connector
class GoogleAdsConnector extends ApiConnector {
    public function fetchDailyMetrics($date) {
        // Represents Google Search Ads + GA4 website metrics
        $clicks = rand(400, 800);
        $impressions = $clicks * rand(15, 25);
        $reach = $impressions * 0.9; // Reach is close to impressions for search
        $engagements = $clicks * rand(70, 85) / 100; // website session engagement
        $leads = $clicks * rand(3, 7) / 100;
        $spend = $clicks * rand(12, 22) / 10; // CPC cost
        $revenue = $leads * rand(150, 250);

        return [
            'reach' => (int)$reach,
            'impressions' => (int)$impressions,
            'engagements' => (int)$engagements,
            'clicks' => (int)$clicks,
            'leads_generated' => (int)$leads,
            'spend' => (float)$spend,
            'revenue_influenced' => (float)$revenue
        ];
    }

    public function fetchPostPerformance($date) {
        // Ads don't have standard "social posts", but we can return ad performance
        return [
            'reach' => rand(5000, 10000),
            'impressions' => rand(6000, 12000),
            'engagements' => rand(200, 500),
            'clicks' => rand(100, 300)
        ];
    }
}

// YouTube Connector
class YouTubeConnector extends ApiConnector {
    public function fetchDailyMetrics($date) {
        $views = rand(8000, 20000); // reach = views
        $impressions = $views * rand(5, 8);
        $engagements = $views * rand(8, 14) / 100; // Likes, comments, shares
        $clicks = $views * rand(2, 5) / 100; // link clicks in description
        $leads = $clicks * rand(10, 20) / 100;
        $spend = rand(50, 150);
        $revenue = $leads * rand(200, 400);

        return [
            'reach' => (int)$views,
            'impressions' => (int)$impressions,
            'engagements' => (int)$engagements,
            'clicks' => (int)$clicks,
            'leads_generated' => (int)$leads,
            'spend' => (float)$spend,
            'revenue_influenced' => (float)$revenue
        ];
    }

    public function fetchPostPerformance($date) {
        return [
            'reach' => rand(4000, 10000), // Video Views
            'impressions' => rand(20000, 50000),
            'engagements' => rand(300, 900),
            'clicks' => rand(100, 250)
        ];
    }
}

// X (Twitter) Connector
class XConnector extends ApiConnector {
    public function fetchDailyMetrics($date) {
        $impressions = rand(25000, 60000);
        $reach = $impressions * 0.7;
        $engagements = $impressions * rand(15, 30) / 1000;
        $clicks = $engagements * rand(10, 20) / 100;
        $leads = $clicks * rand(4, 10) / 100;
        $spend = rand(80, 180);
        $revenue = $leads * rand(100, 150);

        return [
            'reach' => (int)$reach,
            'impressions' => (int)$impressions,
            'engagements' => (int)$engagements,
            'clicks' => (int)$clicks,
            'leads_generated' => (int)$leads,
            'spend' => (float)$spend,
            'revenue_influenced' => (float)$revenue
        ];
    }

    public function fetchPostPerformance($date) {
        return [
            'reach' => rand(1000, 5000),
            'impressions' => rand(1500, 7500),
            'engagements' => rand(15, 60),
            'clicks' => rand(2, 12)
        ];
    }
}
