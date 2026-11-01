<?php
/**
 * Raptor CRM - Deliverability Health Report Service
 * 
 * Aggregates 24-hour delivery health metrics, bounce/complaint tripwire alerts,
 * DNS authentication readiness, DNSBL blacklist checks, and queue throughput.
 * Formats responsive HTML & plain-text reports to alert domain administrators.
 */

class DeliverabilityHealthReportService {

    /**
     * Gather deliverability health data for the past 24 hours.
     */
    public static function collectHealthData(): array {
        $db = Database::getInstance()->getConnection();
        $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);

        // 1. Past 24-hour campaign dispatches
        if ($driver === 'sqlite') {
            $since = date('Y-m-d H:i:s', strtotime('-24 hours'));
            $eventsSql = "SELECT event_type, COUNT(*) as cnt 
                          FROM campaign_email_events 
                          WHERE created_at >= '{$since}' 
                          GROUP BY event_type";
        } else {
            $eventsSql = "SELECT event_type, COUNT(*) as cnt 
                          FROM campaign_email_events 
                          WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) 
                          GROUP BY event_type";
        }

        $stmt = $db->query($eventsSql);
        $events = $stmt ? $stmt->fetchAll(PDO::FETCH_KEY_PAIR) : [];

        $sent = (int)($events['sent'] ?? 0);
        $delivered = (int)($events['delivered'] ?? 0);
        $hardBounces = (int)($events['hard_bounce'] ?? 0);
        $softBounces = (int)($events['soft_bounce'] ?? 0);
        $totalBounces = $hardBounces + $softBounces;
        $complaints = (int)($events['complaint'] ?? 0);
        $opens = (int)($events['open'] ?? 0);
        $clicks = (int)($events['click'] ?? 0);

        // If no events in the last 24h, fallback to totals from campaigns table for overview
        if ($sent === 0) {
            $sumStmt = $db->query("SELECT SUM(sent_count) as s, SUM(delivered_count) as d, SUM(bounced_count) as b, SUM(complained_count) as c FROM campaigns_broadcast");
            $sum = $sumStmt ? $sumStmt->fetch(PDO::FETCH_ASSOC) : [];
            $sent = (int)($sum['s'] ?? 0);
            $delivered = (int)($sum['d'] ?? 0);
            $totalBounces = (int)($sum['b'] ?? 0);
            $complaints = (int)($sum['c'] ?? 0);
        }

        $bounceRate = $sent > 0 ? round(($totalBounces / $sent) * 100, 2) : 0.0;
        $complaintRate = $sent > 0 ? round(($complaints / $sent) * 100, 3) : 0.0;

        // Health evaluation
        $bounceStatus = 'healthy';
        if ($bounceRate >= 2.0) {
            $bounceStatus = 'critical'; // Breaches provider pause limit
        } elseif ($bounceRate >= 1.0) {
            $bounceStatus = 'warning';
        }

        $complaintStatus = 'healthy';
        if ($complaintRate >= 0.1) {
            $complaintStatus = 'critical'; // Breaches Google/Yahoo 0.1% spam threshold
        } elseif ($complaintRate >= 0.05) {
            $complaintStatus = 'warning';
        }

        // 2. Queue Backlog Status
        $queueSql = "SELECT status, COUNT(*) as cnt FROM campaign_email_queue GROUP BY status";
        $qStmt = $db->query($queueSql);
        $queueCounts = $qStmt ? $qStmt->fetchAll(PDO::FETCH_KEY_PAIR) : [];

        $queuePending = (int)($queueCounts['pending'] ?? 0);
        $queueProcessing = (int)($queueCounts['processing'] ?? 0);
        $queueFailed = (int)($queueCounts['failed'] ?? 0);
        $queueCancelled = (int)($queueCounts['cancelled'] ?? 0);

        // 3. Domain Authentication States
        $domainModel = new CampaignDomain();
        $domains = $domainModel->getAllDomains();

        $domainAuthSummary = [];
        foreach ($domains as $d) {
            $domainAuthSummary[] = [
                'name' => $d->domain_name,
                'spf' => $d->spf_status,
                'dkim' => $d->dkim_status,
                'dmarc' => $d->dmarc_status,
                'mx' => $d->mx_status,
                'is_ready' => ($d->spf_status === 'valid' && $d->dkim_status === 'valid' && $d->dmarc_status === 'valid')
            ];
        }

        // 4. Global Suppression Count
        $suppStmt = $db->query("SELECT COUNT(*) FROM campaign_suppressions");
        $suppressionCount = (int)($suppStmt ? $suppStmt->fetchColumn() : 0);

        // 5. Default Domain Blacklist Check
        $defaultDomain = $domainModel->getDefaultDomain();
        $blacklistResults = null;
        if ($defaultDomain) {
            $blacklistResults = BlacklistMonitorService::checkDomain($defaultDomain->domain_name);
        }

        return [
            'timestamp' => date('Y-m-d H:i:s'),
            'metrics_24h' => [
                'sent' => $sent,
                'delivered' => $delivered,
                'bounces' => $totalBounces,
                'hard_bounces' => $hardBounces,
                'soft_bounces' => $softBounces,
                'complaints' => $complaints,
                'bounce_rate' => $bounceRate,
                'complaint_rate' => $complaintRate,
                'bounce_status' => $bounceStatus,
                'complaint_status' => $complaintStatus
            ],
            'queue' => [
                'pending' => $queuePending,
                'processing' => $queueProcessing,
                'failed' => $queueFailed,
                'cancelled' => $queueCancelled
            ],
            'domains' => $domainAuthSummary,
            'suppressions_total' => $suppressionCount,
            'blacklist' => $blacklistResults
        ];
    }

    /**
     * Build responsive HTML email report.
     */
    public static function buildHtmlReport(array $data): string {
        $m = $data['metrics_24h'];
        $q = $data['queue'];

        $bColor = match($m['bounce_status']) {
            'critical' => '#dc2626',
            'warning' => '#f59e0b',
            default => '#10b981'
        };

        $cColor = match($m['complaint_status']) {
            'critical' => '#dc2626',
            'warning' => '#f59e0b',
            default => '#10b981'
        };

        $domainRows = '';
        foreach ($data['domains'] as $d) {
            $badge = $d['is_ready'] 
                ? '<span style="color:#10b981;font-weight:bold;">VALIDATED</span>' 
                : '<span style="color:#f59e0b;font-weight:bold;">PENDING / ACTION NEEDED</span>';
            $domainRows .= "<tr>
                <td style=\"padding:8px;border-bottom:1px solid #e5e7eb;\">{$d['name']}</td>
                <td style=\"padding:8px;border-bottom:1px solid #e5e7eb;\">{$d['spf']}</td>
                <td style=\"padding:8px;border-bottom:1px solid #e5e7eb;\">{$d['dkim']}</td>
                <td style=\"padding:8px;border-bottom:1px solid #e5e7eb;\">{$d['dmarc']}</td>
                <td style=\"padding:8px;border-bottom:1px solid #e5e7eb;\">{$badge}</td>
            </tr>";
        }

        $blacklistSummary = 'Domain reputation clean across queried public blacklists.';
        if (!empty($data['blacklist']) && !$data['blacklist']['is_clean']) {
            $blacklistSummary = "<strong style=\"color:#dc2626;\">WARNING: Domain listed on {$data['blacklist']['listed_count']} blacklist(s)!</strong>";
        }

        return "
        <!DOCTYPE html>
        <html>
        <head><meta charset=\"UTF-8\"></head>
        <body style=\"font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;margin:0;padding:20px;background:#f3f4f6;color:#1f2937;\">
            <div style=\"max-width:640px;margin:0 auto;background:#ffffff;border-radius:8px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,0.1);\">
                <div style=\"background:#1e293b;padding:20px;color:#ffffff;\">
                    <h2 style=\"margin:0;font-size:20px;\">Raptor CRM - Daily Deliverability Health Report</h2>
                    <p style=\"margin:4px 0 0 0;font-size:13px;color:#94a3b8;\">Report Generated: {$data['timestamp']} UTC</p>
                </div>
                <div style=\"padding:20px;\">
                    <h3 style=\"font-size:15px;color:#374151;margin-top:0;\">1. Deliverability Ratios & Reputation Safeguards</h3>
                    <table style=\"width:100%;border-collapse:collapse;margin-bottom:20px;\">
                        <tr>
                            <td style=\"width:50%;padding:10px;background:#f9fafb;border-radius:6px;border-left:4px solid {$bColor};\">
                                <div style=\"font-size:12px;color:#6b7280;text-transform:uppercase;\">Bounce Rate (Threshold &lt; 2%)</div>
                                <div style=\"font-size:22px;font-weight:bold;color:{$bColor};\">{$m['bounce_rate']}%</div>
                                <div style=\"font-size:11px;color:#9ca3af;\">{$m['bounces']} bounces / {$m['sent']} sent</div>
                            </td>
                            <td style=\"width:10px;\"></td>
                            <td style=\"width:50%;padding:10px;background:#f9fafb;border-radius:6px;border-left:4px solid {$cColor};\">
                                <div style=\"font-size:12px;color:#6b7280;text-transform:uppercase;\">Complaint Rate (Threshold &lt; 0.1%)</div>
                                <div style=\"font-size:22px;font-weight:bold;color:{$cColor};\">{$m['complaint_rate']}%</div>
                                <div style=\"font-size:11px;color:#9ca3af;\">{$m['complaints']} spam complaints</div>
                            </td>
                        </tr>
                    </table>

                    <h3 style=\"font-size:15px;color:#374151;\">2. Queue Throughput</h3>
                    <p style=\"font-size:13px;color:#4b5563;margin-bottom:15px;\">
                        Pending: <strong>{$q['pending']}</strong> | Processing: <strong>{$q['processing']}</strong> | Failed: <strong>{$q['failed']}</strong> | Total Suppressions: <strong>{$data['suppressions_total']}</strong>
                    </p>

                    <h3 style=\"font-size:15px;color:#374151;\">3. Sending Domain DNS Authentication</h3>
                    <table style=\"width:100%;border-collapse:collapse;font-size:13px;margin-bottom:20px;\">
                        <tr style=\"background:#f9fafb;text-align:left;\">
                            <th style=\"padding:8px;border-bottom:2px solid #e5e7eb;\">Domain</th>
                            <th style=\"padding:8px;border-bottom:2px solid #e5e7eb;\">SPF</th>
                            <th style=\"padding:8px;border-bottom:2px solid #e5e7eb;\">DKIM</th>
                            <th style=\"padding:8px;border-bottom:2px solid #e5e7eb;\">DMARC</th>
                            <th style=\"padding:8px;border-bottom:2px solid #e5e7eb;\">Status</th>
                        </tr>
                        {$domainRows}
                    </table>

                    <h3 style=\"font-size:15px;color:#374151;\">4. Blacklist (DNSBL) Monitor</h3>
                    <p style=\"font-size:13px;color:#4b5563;\">{$blacklistSummary}</p>
                </div>
                <div style=\"background:#f9fafb;padding:12px 20px;font-size:11px;color:#6b7280;text-align:center;border-top:1px solid #e5e7eb;\">
                    Axiora Global Solutions | Raptor CRM Email Deliverability Engine
                </div>
            </div>
        </body>
        </html>";
    }

    /**
     * Build plain-text report alternative.
     */
    public static function buildPlainTextReport(array $data): string {
        $m = $data['metrics_24h'];
        $q = $data['queue'];

        return "RAPTOR CRM: DAILY DELIVERABILITY HEALTH REPORT\n"
             . "Generated: {$data['timestamp']} UTC\n"
             . "------------------------------------------------------------\n"
             . "1. DELIVERABILITY METRICS (Past 24 Hours)\n"
             . "- Sent: {$m['sent']}\n"
             . "- Delivered: {$m['delivered']}\n"
             . "- Bounce Rate: {$m['bounce_rate']}% (Status: {$m['bounce_status']})\n"
             . "- Complaint Rate: {$m['complaint_rate']}% (Status: {$m['complaint_status']})\n"
             . "\n"
             . "2. QUEUE & SUPPRESSION\n"
             . "- Pending: {$q['pending']}\n"
             . "- Processing: {$q['processing']}\n"
             . "- Failed: {$q['failed']}\n"
             . "- Suppressions Total: {$data['suppressions_total']}\n"
             . "\n"
             . "3. REPUTATION SAFEGUARDS\n"
             . "- Max allowed bounce threshold: 2.0%\n"
             . "- Max allowed spam complaint threshold: 0.1%\n";
    }

    /**
     * Dispatch daily report email to admin recipients.
     */
    public static function dispatchReport(?string $recipientEmail = null): bool {
        $to = $recipientEmail ?: ($_ENV['ADMIN_ALERT_EMAIL'] ?? 'admin@raptor.internal');
        $data = self::collectHealthData();

        $html = self::buildHtmlReport($data);
        $text = self::buildPlainTextReport($data);
        $subject = sprintf('Raptor Deliverability Report [%s] - Bounce: %s%% | Complaint: %s%%',
            strtoupper($data['metrics_24h']['bounce_status']),
            $data['metrics_24h']['bounce_rate'],
            $data['metrics_24h']['complaint_rate']
        );

        $mailer = MailerFactory::getAdapter();
        $result = $mailer->send([
            'to' => $to,
            'to_name' => 'Raptor Deliverability Admin',
            'from_email' => $_ENV['MAIL_FROM_ADDRESS'] ?? 'no-reply@raptor.internal',
            'from_name' => 'Raptor Health Engine',
            'subject' => $subject,
            'html' => $html,
            'text' => $text
        ]);

        return $result->isSuccess();
    }
}
