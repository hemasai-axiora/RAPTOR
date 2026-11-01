<?php
/**
 * Raptor CRM - Webhook Processor Service
 * 
 * Ingests, authenticates, and routes bounce, complaint, unsubscribe, and delivery
 * webhooks from Amazon SES, Postmark, SendGrid, and Mailgun.
 * 
 * Deliverability Safeguards:
 * - Hard Bounce -> Immediately suppress & cascade status
 * - Soft Bounce 3 times -> Auto-suppress with soft_bounce_repeat
 * - Spam Complaint -> Immediately suppress & cascade status
 * - Delivered -> Updates recipient delivery timestamp and campaign stats
 */

class WebhookProcessorService {

    /**
     * Process an incoming bounce notification.
     */
    public static function processBounce(string $email, string $bounceType, ?string $reason = null, ?int $campaignId = null, array $rawPayload = []): array {
        $cleanEmail = strtolower(trim($email));
        if (empty($cleanEmail)) {
            return ['success' => false, 'error' => 'Missing email address'];
        }

        $db = Database::getInstance()->getConnection();
        $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
        $nowSql = ($driver === 'sqlite') ? "datetime('now')" : "NOW()";

        $suppressionModel = new CampaignSuppression();
        $isHard = (strtolower($bounceType) === 'hard' || strtolower($bounceType) === 'permanent');

        if ($isHard) {
            // Immediate global suppression for hard bounces
            $suppressionModel->suppress($cleanEmail, 'hard_bounce', $reason ?: 'Permanent Delivery Failure', $campaignId);

            // Update contact status
            $db->prepare("UPDATE campaign_contacts SET status = 'bounced', updated_at = {$nowSql} WHERE email = :email")
               ->execute([':email' => $cleanEmail]);

            // Update campaign counter and recipient bounce status
            if ($campaignId > 0) {
                $db->prepare("UPDATE campaigns_broadcast SET bounced_count = bounced_count + 1 WHERE campaign_id = :cid")
                   ->execute([':cid' => $campaignId]);
                $db->prepare("UPDATE campaign_recipients SET status = 'bounced', bounce_type = 'hard', bounce_reason = :r WHERE campaign_id = :cid AND email = :email")
                   ->execute([':r' => $reason, ':cid' => $campaignId, ':email' => $cleanEmail]);
            }

            self::logEvent($campaignId, $cleanEmail, 'hard_bounce', [
                'reason' => $reason,
                'raw' => $rawPayload
            ]);

            return ['success' => true, 'action' => 'hard_bounced_and_suppressed', 'email' => $cleanEmail];
        }

        // Soft Bounce Handling
        self::logEvent($campaignId, $cleanEmail, 'soft_bounce', [
            'reason' => $reason,
            'raw' => $rawPayload
        ]);

        if ($campaignId > 0) {
            $db->prepare("UPDATE campaign_recipients SET bounce_type = 'soft', bounce_reason = :r WHERE campaign_id = :cid AND email = :email")
               ->execute([':r' => $reason, ':cid' => $campaignId, ':email' => $cleanEmail]);
        }

        // Count previous soft bounces
        $cntStmt = $db->prepare('SELECT COUNT(*) FROM campaign_email_events e 
                                 JOIN campaign_recipients r ON e.recipient_id = r.recipient_id 
                                 WHERE r.email = :email AND e.event_type = "soft_bounce"');
        $cntStmt->execute([':email' => $cleanEmail]);
        $softCount = (int)$cntStmt->fetchColumn();

        if ($softCount >= 3) {
            // 3rd soft bounce -> auto-suppress
            $suppressionModel->suppress($cleanEmail, 'soft_bounce_repeat', "Auto-suppressed after {$softCount} repeated soft bounces", $campaignId);
            return ['success' => true, 'action' => 'soft_bounce_threshold_suppressed', 'email' => $cleanEmail];
        }

        return ['success' => true, 'action' => 'soft_bounce_logged', 'email' => $cleanEmail, 'total_soft' => $softCount];
    }

    /**
     * Process an incoming spam complaint.
     */
    public static function processComplaint(string $email, ?string $feedbackType = null, ?int $campaignId = null, array $rawPayload = []): array {
        $cleanEmail = strtolower(trim($email));
        if (empty($cleanEmail)) {
            return ['success' => false, 'error' => 'Missing email address'];
        }

        $db = Database::getInstance()->getConnection();
        $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
        $nowSql = ($driver === 'sqlite') ? "datetime('now')" : "NOW()";

        $suppressionModel = new CampaignSuppression();
        $suppressionModel->suppress($cleanEmail, 'spam_complaint', 'Spam complaint via provider webhook: ' . ($feedbackType ?: 'abuse'), $campaignId);

        // Update contact status
        $db->prepare("UPDATE campaign_contacts SET status = 'complained', updated_at = {$nowSql} WHERE email = :email")
           ->execute([':email' => $cleanEmail]);

        // Update campaign counter
        if ($campaignId > 0) {
            $db->prepare("UPDATE campaigns_broadcast SET complained_count = complained_count + 1 WHERE campaign_id = :cid")
               ->execute([':cid' => $campaignId]);
            $db->prepare("UPDATE campaign_recipients SET status = 'complained' WHERE campaign_id = :cid AND email = :email")
               ->execute([':cid' => $campaignId, ':email' => $cleanEmail]);
        }

        self::logEvent($campaignId, $cleanEmail, 'complaint', [
            'feedback_type' => $feedbackType,
            'raw' => $rawPayload
        ]);

        return ['success' => true, 'action' => 'complaint_suppressed', 'email' => $cleanEmail];
    }

    /**
     * Process successful delivery event.
     */
    public static function processDelivery(string $email, ?string $providerMessageId = null, ?int $campaignId = null): array {
        $cleanEmail = strtolower(trim($email));
        if (empty($cleanEmail)) return ['success' => false];

        $db = Database::getInstance()->getConnection();
        $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
        $nowSql = ($driver === 'sqlite') ? "datetime('now')" : "NOW()";

        if ($campaignId > 0) {
            $db->prepare("UPDATE campaigns_broadcast SET delivered_count = delivered_count + 1 WHERE campaign_id = :cid")
               ->execute([':cid' => $campaignId]);

            $db->prepare("UPDATE campaign_recipients SET status = 'delivered', delivered_at = {$nowSql} WHERE campaign_id = :cid AND email = :email")
               ->execute([':cid' => $campaignId, ':email' => $cleanEmail]);
        }

        self::logEvent($campaignId, $cleanEmail, 'delivered', [
            'provider_message_id' => $providerMessageId
        ]);

        return ['success' => true, 'action' => 'delivered', 'email' => $cleanEmail];
    }

    private static function logEvent(?int $campId, string $email, string $eventType, array $payload): void {
        try {
            $db = Database::getInstance()->getConnection();
            $rStmt = $db->prepare('SELECT recipient_id, contact_id FROM campaign_recipients WHERE email = :email AND campaign_id = :cid ORDER BY recipient_id DESC LIMIT 1');
            $rStmt->execute([':email' => $email, ':cid' => $campId ?: 0]);
            $recip = $rStmt->fetch(PDO::FETCH_OBJ);

            $stmt = $db->prepare('INSERT INTO campaign_email_events (campaign_id, recipient_id, contact_id, event_type, payload_json) 
                                  VALUES (:cid, :rid, :cntid, :type, :payload)');
            $stmt->execute([
                ':cid' => $campId ?: null,
                ':rid' => $recip ? (int)$recip->recipient_id : null,
                ':cntid' => $recip ? (int)$recip->contact_id : null,
                ':type' => $eventType,
                ':payload' => json_encode($payload, JSON_UNESCAPED_UNICODE)
            ]);
        } catch (Throwable $e) {}
    }

    /**
     * Mailgun Webhook Signature Verifier.
     */
    public static function verifyMailgunSignature(string $token, string $timestamp, string $signature): bool {
        $key = $_ENV['MAILGUN_WEBHOOK_KEY'] ?? ($_ENV['MAILGUN_API_KEY'] ?? '');
        if (empty($key)) return true; // dev fallback if not configured
        if (abs(time() - (int)$timestamp) > 900) return false; // 15-minute tolerance
        $expected = hash_hmac('sha256', $timestamp . $token, $key);
        return hash_equals($expected, $signature);
    }
}

