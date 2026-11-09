<?php
/**
 * Raptor CRM - RFC 8058 One-Click & Web Unsubscribe Service
 * 
 * Provides:
 * 1. HMAC-SHA256 tamper-proof token generation and verification
 * 2. RFC 8058 List-Unsubscribe / List-Unsubscribe-Post header generator
 * 3. Immediate global suppression list execution
 * 4. List-specific preference center option
 */

class UnsubscribeService {

    private static function getSecret(): string {
        $secret = $_ENV['APP_KEY'] ?? ($_ENV['UNSUB_SECRET'] ?? 'raptor-secure-campaign-key-928174');
        if (empty($secret)) {
            $secret = 'default-deliverability-secret-salt';
        }
        return $secret;
    }

    /**
     * Generate an HMAC-signed unsubscribe token for a specific campaign recipient.
     */
    public static function generateToken(int $campaignId, int $contactId, string $email): string {
        $cleanEmail = strtolower(trim($email));
        $payload = $campaignId . ':' . $contactId . ':' . $cleanEmail;
        $hash = hash_hmac('sha256', $payload, self::getSecret());
        return bin2hex($payload) . '.' . $hash;
    }

    /**
     * Verify HMAC signature of an unsubscribe token.
     */
    public static function verifyToken(string $token): ?array {
        $parts = explode('.', trim($token));
        if (count($parts) !== 2) {
            return null;
        }
        $hexPayload = $parts[0];
        $signature = $parts[1];
        
        $payload = @hex2bin($hexPayload);
        if ($payload === false) {
            return null;
        }

        $expectedSig = hash_hmac('sha256', $payload, self::getSecret());
        if (!hash_equals($expectedSig, $signature)) {
            return null;
        }

        $fields = explode(':', $payload, 3);
        if (count($fields) !== 3) {
            return null;
        }

        return [
            'campaign_id' => (int)$fields[0],
            'contact_id' => (int)$fields[1],
            'email' => $fields[2]
        ];
    }

    /**
     * Generate complete unsubscribe URL.
     */
    public static function buildUnsubscribeUrl(string $token, int $campaignId = 0, int $listId = 0): string {
        $base = defined('URLROOT') ? URLROOT : '';
        $params = [
            'route' => 'unsubscribe/view',
            'token' => $token
        ];
        if ($campaignId > 0) {
            $params['c'] = $campaignId;
        }
        if ($listId > 0) {
            $params['l'] = $listId;
        }
        return $base . '/index.php?' . http_build_query($params);
    }

    /**
     * Generate RFC 8058 One-Click POST URL.
     */
    public static function buildOneClickUrl(string $token): string {
        $base = defined('URLROOT') ? URLROOT : '';
        return $base . '/index.php?route=unsubscribe/rfc8058&token=' . urlencode($token);
    }

    /**
     * Process an unsubscribe action by token.
     * 
     * @param string $token Unsubscribe token
     * @param string $scope 'global' (all future emails) or 'list' (current list only)
     * @param int|null $listId Specific list ID if scope is 'list'
     * @return array [success, email, message]
     */
    public static function processUnsubscribe(string $token, string $scope = 'global', ?int $listId = null): array {
        $cleanToken = trim($token);
        if (empty($cleanToken)) {
            return ['success' => false, 'error' => 'Invalid or missing unsubscribe token'];
        }

        $tokenData = self::verifyToken($cleanToken);
        $db = Database::getInstance()->getConnection();

        // Check if matching recipient row exists in campaign_recipients
        $stmt = $db->prepare('SELECT r.recipient_id, r.campaign_id, r.contact_id, r.email, c.list_id
                              FROM campaign_recipients r
                              LEFT JOIN campaigns_broadcast c ON r.campaign_id = c.campaign_id
                              WHERE r.unsubscribe_token = :token LIMIT 1');
        $stmt->execute([':token' => $cleanToken]);
        $recip = $stmt->fetch(PDO::FETCH_OBJ);

        if (!$recip && !$tokenData) {
            return ['success' => false, 'error' => 'Unsubscribe link is invalid or expired'];
        }

        $email = $recip ? strtolower(trim($recip->email)) : ($tokenData['email'] ?? '');
        $contactId = $recip ? (int)$recip->contact_id : (int)($tokenData['contact_id'] ?? 0);
        $campaignId = $recip ? (int)$recip->campaign_id : (int)($tokenData['campaign_id'] ?? 0);
        $recipientId = $recip ? (int)$recip->recipient_id : 0;
        $targetListId = $listId ?: (int)($recip->list_id ?? 0);

        $suppressionModel = new CampaignSuppression();

        if ($scope === 'global') {
            // Globally suppress
            $suppressionModel->suppress($email, 'unsubscribe', 'One-Click Unsubscribe from Campaign #' . $campaignId, $campaignId);

            // Record in Consent Ledger if contact exists
            if ($contactId > 0 && class_exists('ConsentService')) {
                ConsentService::revoke($contactId, ConsentService::PURPOSE_MARKETING, 'RFC 8058 One-Click / Web Unsubscribe');
            }

            // Log event
            self::logEvent($campaignId, $recipientId, $contactId, 'unsubscribe', [
                'scope' => 'global',
                'email' => $email
            ]);

            return [
                'success' => true,
                'email' => $email,
                'scope' => 'global',
                'message' => 'You have been successfully unsubscribed from all email communications.'
            ];
        } else {
            // Unsubscribe from this specific list only
            if ($targetListId > 0) {
                $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
                $nowSql = ($driver === 'sqlite') ? 'datetime("now")' : 'NOW()';
                $db->prepare("UPDATE campaign_list_contacts 
                              SET status = 'unsubscribed', unsubscribed_at = {$nowSql} 
                              WHERE list_id = :lid AND contact_id = :cid")
                   ->execute([':lid' => $targetListId, ':cid' => $contactId]);
            }

            self::logEvent($campaignId, $recipientId, $contactId, 'unsubscribe', [
                'scope' => 'list',
                'list_id' => $targetListId,
                'email' => $email
            ]);

            return [
                'success' => true,
                'email' => $email,
                'scope' => 'list',
                'message' => 'You have been unsubscribed from this specific mailing list.'
            ];
        }
    }

    private static function logEvent(int $campId, int $recipId, int $contactId, string $eventType, array $payload): void {
        try {
            $db = Database::getInstance()->getConnection();
            $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
            $stmt = $db->prepare('INSERT INTO campaign_email_events (campaign_id, recipient_id, contact_id, event_type, ip_address, user_agent, payload_json)
                                  VALUES (:cid, :rid, :cntid, :type, :ip, :ua, :payload)');
            $stmt->execute([
                ':cid' => $campId ?: null,
                ':rid' => $recipId ?: null,
                ':cntid' => $contactId ?: null,
                ':type' => $eventType,
                ':ip' => $ip,
                ':ua' => $ua,
                ':payload' => json_encode($payload, JSON_UNESCAPED_UNICODE)
            ]);
        } catch (Throwable $t) {}
    }
}
