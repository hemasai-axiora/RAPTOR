<?php
/**
 * Raptor CRM - Campaign Suppression Model & Service
 * 
 * Enforces the global suppression list:
 * - Checked before EVERY email send attempt.
 * - An email on this list is NEVER emailable again (unsubscribed, hard-bounced, spam-complained).
 * - Thread-safe, indexed lookups by lowercase email.
 */

class CampaignSuppression extends Model {

    public const REASONS = [
        'unsubscribe' => 'User Unsubscribed',
        'hard_bounce' => 'Permanent Delivery Failure (Hard Bounce)',
        'soft_bounce_repeat' => 'Repeated Transient Failure (3+ Soft Bounces)',
        'spam_complaint' => 'Spam / Abuse Complaint',
        'admin_manual' => 'Administrative Manual Block'
    ];

    /**
     * Check if an email is globally suppressed.
     * Guaranteed case-insensitive check.
     */
    public function isSuppressed(string $email): bool {
        $cleanEmail = strtolower(trim($email));
        if (empty($cleanEmail)) {
            return true;
        }

        $this->query('SELECT suppression_id FROM campaign_suppressions WHERE email = :email LIMIT 1');
        $this->bind(':email', $cleanEmail);
        $row = $this->single();
        return !empty($row);
    }

    /**
     * Add an email to the global suppression list.
     * Also updates the contact status in campaign_contacts if present.
     */
    public function suppress(string $email, string $reason = 'unsubscribe', ?string $notes = null, ?int $sourceCampaignId = null): bool {
        $cleanEmail = strtolower(trim($email));
        if (empty($cleanEmail) || !filter_var($cleanEmail, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        if (!array_key_exists($reason, self::REASONS)) {
            $reason = 'admin_manual';
        }

        try {
            $db = Database::getInstance()->getConnection();
            $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);

            // 1. Insert or update suppression table
            if ($driver === 'sqlite') {
                $sql = 'INSERT INTO campaign_suppressions (email, reason, notes, source_campaign_id) 
                        VALUES (:email, :reason, :notes, :camp_id)
                        ON CONFLICT(email) DO UPDATE SET 
                            reason = excluded.reason, 
                            notes = COALESCE(excluded.notes, notes),
                            source_campaign_id = COALESCE(excluded.source_campaign_id, source_campaign_id)';
            } else {
                $sql = 'INSERT INTO campaign_suppressions (email, reason, notes, source_campaign_id) 
                        VALUES (:email, :reason, :notes, :camp_id)
                        ON DUPLICATE KEY UPDATE 
                            reason = VALUES(reason), 
                            notes = COALESCE(VALUES(notes), notes),
                            source_campaign_id = COALESCE(VALUES(source_campaign_id), source_campaign_id)';
            }

            $stmt = $db->prepare($sql);
            $stmt->execute([
                ':email' => $cleanEmail,
                ':reason' => $reason,
                ':notes' => $notes,
                ':camp_id' => $sourceCampaignId
            ]);

            // 2. Cascade status to campaign_contacts table
            $contactStatus = ($reason === 'unsubscribe') ? 'unsubscribed' : (($reason === 'spam_complaint') ? 'complained' : 'bounced');
            $cStmt = $db->prepare('UPDATE campaign_contacts SET status = :status WHERE email = :email');
            $cStmt->execute([':status' => $contactStatus, ':email' => $cleanEmail]);

            // 3. Mark in all lists as unsubscribed if reason is unsubscribe or complaint
            if (in_array($reason, ['unsubscribe', 'spam_complaint'], true)) {
                $now = date('Y-m-d H:i:s');
                if ($driver === 'sqlite') {
                    $lStmt = $db->prepare('UPDATE campaign_list_contacts 
                                           SET status = "unsubscribed", unsubscribed_at = :now
                                           WHERE contact_id IN (SELECT contact_id FROM campaign_contacts WHERE email = :email)');
                } else {
                    $lStmt = $db->prepare('UPDATE campaign_list_contacts lc
                                           JOIN campaign_contacts c ON lc.contact_id = c.contact_id
                                           SET lc.status = "unsubscribed", lc.unsubscribed_at = :now
                                           WHERE c.email = :email');
                }
                try {
                    $lStmt->execute([':now' => $now, ':email' => $cleanEmail]);
                } catch (Throwable $e) {
                    // Safe fallback
                }
            }

            // 4. Cross-bridge to Customer 360 contacts & CRM leads
            try {
                $nowAt = date('Y-m-d H:i:s');
                $c360Stmt = $db->prepare('UPDATE contacts SET is_suppressed = 1, suppression_reason = :reason, suppressed_at = :at WHERE primary_email = :email');
                $c360Stmt->execute([':reason' => $reason, ':at' => $nowAt, ':email' => $cleanEmail]);

                // Update CRM leads to 'unsubscribed' if not already converted
                $leadStmt = $db->prepare("UPDATE leads SET status = 'unsubscribed' WHERE LOWER(email) = :email AND status != 'converted'");
                $leadStmt->execute([':email' => $cleanEmail]);
            } catch (Throwable $e) {
                // Table might not exist during early migration
            }

            return true;
        } catch (Throwable $e) {
            error_log('CampaignSuppression::suppress error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Remove an address from suppression (administrative override).
     */
    public function remove(string $email): bool {
        $cleanEmail = strtolower(trim($email));
        if (empty($cleanEmail)) {
            return false;
        }

        $this->query('DELETE FROM campaign_suppressions WHERE email = :email');
        $this->bind(':email', $cleanEmail);
        return $this->execute();
    }

    /**
     * Paginated suppression list for admin view.
     */
    public function getSuppressions(array $filters = [], int $limit = 50, int $offset = 0): array {
        $sql = 'SELECT s.*, c.name as campaign_name 
                FROM campaign_suppressions s
                LEFT JOIN campaigns_broadcast c ON s.source_campaign_id = c.campaign_id
                WHERE 1=1 ';
        $params = [];

        if (!empty($filters['reason'])) {
            $sql .= 'AND s.reason = :reason ';
            $params[':reason'] = $filters['reason'];
        }

        if (!empty($filters['search'])) {
            $sql .= 'AND s.email LIKE :s ';
            $params[':s'] = '%' . trim($filters['search']) . '%';
        }

        $sql .= 'ORDER BY s.created_at DESC LIMIT ' . (int)$limit . ' OFFSET ' . (int)$offset;

        $this->query($sql);
        foreach ($params as $k => $v) {
            $this->bind($k, $v);
        }
        return $this->resultSet() ?: [];
    }

    public function countSuppressions(array $filters = []): int {
        $sql = 'SELECT COUNT(*) as cnt FROM campaign_suppressions WHERE 1=1 ';
        $params = [];

        if (!empty($filters['reason'])) {
            $sql .= 'AND reason = :reason ';
            $params[':reason'] = $filters['reason'];
        }

        if (!empty($filters['search'])) {
            $sql .= 'AND email LIKE :s ';
            $params[':s'] = '%' . trim($filters['search']) . '%';
        }

        $this->query($sql);
        foreach ($params as $k => $v) {
            $this->bind($k, $v);
        }
        $row = $this->single();
        return (int)($row ? current((array)$row) : 0);
    }
}
