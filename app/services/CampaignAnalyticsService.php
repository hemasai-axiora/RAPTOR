<?php
/**
 * Raptor CRM - Campaign Analytics & Reporting Engine
 * 
 * Provides:
 * 1. Global & Per-Campaign Key Performance Indicators (Sent, Delivered, Opens, Clicks, Bounces, Complaints)
 * 2. Deliverability health ratios (Delivery %, Open %, CTR %, CTOR %, Bounce %, Complaint %)
 * 3. Domain-level performance breakdown (Gmail, Outlook/Hotmail, Yahoo, Corporate)
 * 4. Top clicked URLs table
 * 5. Inactive subscriber re-engagement engine (flags 90-180 day inactive contacts)
 * 6. Export to CSV functionality
 */

class CampaignAnalyticsService {

    public static function getSummaryMetrics(?int $campaignId = null): array {
        $db = Database::getInstance()->getConnection();
        $where = ($campaignId > 0) ? 'WHERE campaign_id = ' . (int)$campaignId : '';

        $sql = "SELECT 
                    SUM(sent_count) as total_sent,
                    SUM(delivered_count) as total_delivered,
                    SUM(opened_count) as total_opened,
                    SUM(clicked_count) as total_clicked,
                    SUM(bounced_count) as total_bounced,
                    SUM(complained_count) as total_complained,
                    SUM(unsubscribed_count) as total_unsubscribed
                FROM campaigns_broadcast {$where}";

        $stmt = $db->query($sql);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $sent = (int)($row['total_sent'] ?? 0);
        $delivered = (int)($row['total_delivered'] ?? 0);
        $opened = (int)($row['total_opened'] ?? 0);
        $clicked = (int)($row['total_clicked'] ?? 0);
        $bounced = (int)($row['total_bounced'] ?? 0);
        $complained = (int)($row['total_complained'] ?? 0);
        $unsubscribed = (int)($row['total_unsubscribed'] ?? 0);

        // Calculate ratios
        $deliveryRate = $sent > 0 ? round(($delivered / $sent) * 100, 2) : 0.0;
        $openRate = $delivered > 0 ? round(($opened / $delivered) * 100, 2) : 0.0;
        $clickRate = $delivered > 0 ? round(($clicked / $delivered) * 100, 2) : 0.0;
        $ctor = $opened > 0 ? round(($clicked / $opened) * 100, 2) : 0.0;
        $bounceRate = $sent > 0 ? round(($bounced / $sent) * 100, 2) : 0.0;
        $complaintRate = $sent > 0 ? round(($complained / $sent) * 100, 3) : 0.0;

        // Apple Mail Privacy Protection (MPP) proxy opens count
        $mppSql = "SELECT COUNT(*) as mpp_count FROM campaign_email_events WHERE event_type = 'open' AND payload_json LIKE '%\"is_mpp\":true%'";
        if ($campaignId > 0) {
            $mppSql .= ' AND campaign_id = ' . (int)$campaignId;
        }
        $mppStmt = $db->query($mppSql);
        $mppRow = $mppStmt ? $mppStmt->fetch(PDO::FETCH_ASSOC) : [];
        $mppOpens = (int)($mppRow['mpp_count'] ?? 0);

        return [
            'sent' => $sent,
            'delivered' => $delivered,
            'opened' => $opened,
            'clicked' => $clicked,
            'bounced' => $bounced,
            'complained' => $complained,
            'unsubscribed' => $unsubscribed,
            'mpp_opens' => $mppOpens,
            'rates' => [
                'delivery_rate' => $deliveryRate,
                'open_rate' => $openRate,
                'click_rate' => $clickRate,
                'ctor' => $ctor,
                'bounce_rate' => $bounceRate,
                'complaint_rate' => $complaintRate
            ]
        ];
    }

    /**
     * Domain Family Breakdown (Gmail, Outlook, Yahoo, Corporate)
     */
    public static function getDomainBreakdown(?int $campaignId = null): array {
        $db = Database::getInstance()->getConnection();
        $where = ($campaignId > 0) ? 'WHERE r.campaign_id = ' . (int)$campaignId : '';

        $sql = "SELECT r.email, r.status, r.open_count, r.click_count 
                FROM campaign_recipients r {$where}";
        $stmt = $db->query($sql);
        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

        $families = [
            'Gmail / Google' => ['sent' => 0, 'delivered' => 0, 'opened' => 0, 'clicked' => 0, 'bounced' => 0],
            'Microsoft / Outlook' => ['sent' => 0, 'delivered' => 0, 'opened' => 0, 'clicked' => 0, 'bounced' => 0],
            'Yahoo / AOL' => ['sent' => 0, 'delivered' => 0, 'opened' => 0, 'clicked' => 0, 'bounced' => 0],
            'Corporate / Other' => ['sent' => 0, 'delivered' => 0, 'opened' => 0, 'clicked' => 0, 'bounced' => 0]
        ];

        foreach ($rows as $r) {
            $email = strtolower($r['email']);
            $domain = substr(strrchr($email, "@"), 1);

            $family = 'Corporate / Other';
            if (in_array($domain, ['gmail.com', 'googlemail.com'], true)) {
                $family = 'Gmail / Google';
            } elseif (in_array($domain, ['outlook.com', 'hotmail.com', 'live.com', 'msn.com'], true)) {
                $family = 'Microsoft / Outlook';
            } elseif (in_array($domain, ['yahoo.com', 'ymail.com', 'myyahoo.com', 'aol.com'], true)) {
                $family = 'Yahoo / AOL';
            }

            $families[$family]['sent']++;
            if ($r['status'] === 'delivered' || $r['status'] === 'sent') {
                $families[$family]['delivered']++;
            }
            if ((int)$r['open_count'] > 0) {
                $families[$family]['opened']++;
            }
            if ((int)$r['click_count'] > 0) {
                $families[$family]['clicked']++;
            }
            if ($r['status'] === 'bounced') {
                $families[$family]['bounced']++;
            }
        }

        return $families;
    }

    /**
     * Top Clicked URLs Table
     */
    public static function getTopClickedLinks(?int $campaignId = null, int $limit = 10): array {
        $db = Database::getInstance()->getConnection();
        $where = ($campaignId > 0) ? 'AND campaign_id = ' . (int)$campaignId : '';

        $sql = "SELECT target_url, COUNT(*) as click_count, COUNT(DISTINCT recipient_id) as unique_clicks 
                FROM campaign_email_events 
                WHERE event_type = 'click' AND target_url IS NOT NULL {$where}
                GROUP BY target_url 
                ORDER BY click_count DESC 
                LIMIT " . (int)$limit;

        $stmt = $db->query($sql);
        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    /**
     * Inactive Subscriber Re-Engagement Engine
     * Auto-flags contacts with no opens/clicks in X days (default 90 days).
     */
    public static function flagInactiveContacts(int $days = 90): int {
        $db = Database::getInstance()->getConnection();
        $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $dateThreshold = date('Y-m-d H:i:s', strtotime("-{$days} days"));
            $sql = "UPDATE campaign_contacts 
                    SET status = 'unsubscribed' 
                    WHERE status = 'subscribed' 
                      AND ((last_engagement_at IS NOT NULL AND last_engagement_at < '{$dateThreshold}')
                           OR (last_engagement_at IS NULL AND created_at < '{$dateThreshold}'))";
        } else {
            $sql = "UPDATE campaign_contacts 
                    SET status = 'unsubscribed' 
                    WHERE status = 'subscribed' 
                      AND ((last_engagement_at IS NOT NULL AND last_engagement_at < DATE_SUB(NOW(), INTERVAL {$days} DAY))
                           OR (last_engagement_at IS NULL AND created_at < DATE_SUB(NOW(), INTERVAL {$days} DAY)))";
        }

        return (int)$db->exec($sql);
    }

    /**
     * Generate Downloadable Analytics CSV Export
     */
    public static function exportAnalyticsCsv(?int $campaignId = null): string {
        $db = Database::getInstance()->getConnection();
        $where = ($campaignId > 0) ? 'WHERE r.campaign_id = ' . (int)$campaignId : '';

        $sql = "SELECT r.email, r.subject_variant, r.status, r.sent_at, r.delivered_at, 
                       r.opened_at, r.open_count, r.clicked_at, r.click_count, r.bounce_type, r.bounce_reason
                FROM campaign_recipients r {$where} 
                ORDER BY r.recipient_id ASC";

        $stmt = $db->query($sql);
        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

        $output = fopen('php://temp', 'r+');
        fputcsv($output, ['Email', 'Variant', 'Status', 'Sent At', 'Delivered At', 'First Opened', 'Open Count', 'First Clicked', 'Click Count', 'Bounce Type', 'Bounce Reason']);

        foreach ($rows as $r) {
            fputcsv($output, [
                $r['email'],
                $r['subject_variant'],
                $r['status'],
                $r['sent_at'] ?? 'N/A',
                $r['delivered_at'] ?? 'N/A',
                $r['opened_at'] ?? 'N/A',
                $r['open_count'],
                $r['clicked_at'] ?? 'N/A',
                $r['click_count'],
                $r['bounce_type'],
                $r['bounce_reason'] ?? ''
            ]);
        }

        rewind($output);
        $csvContent = stream_get_contents($output);
        fclose($output);

        return $csvContent;
    }
}

