<?php
/**
 * Raptor CRM - Campaign Approval Snapshot Service
 * -------------------------------------------------------------------------
 * Generates cryptographic approval snapshots (SHA-256) of campaign content,
 * template revisions, sender identities, communication intent, and audience criteria.
 * Any subsequent edit invalidates the approval snapshot.
 */

declare(strict_types=1);

class CampaignSnapshotService {

    public const CURRENT_POLICY_VERSION = '2026-11-09.1';

    /**
     * Compute canonical SHA-256 hash of campaign content.
     */
    public static function computeContentHash(array $campaignData): string {
        $canonical = [
            'name' => trim((string)($campaignData['name'] ?? '')),
            'subject_a' => trim((string)($campaignData['subject_a'] ?? '')),
            'subject_b' => trim((string)($campaignData['subject_b'] ?? '')),
            'preheader' => trim((string)($campaignData['preheader'] ?? '')),
            'from_name' => trim((string)($campaignData['from_name'] ?? '')),
            'from_email' => strtolower(trim((string)($campaignData['from_email'] ?? ''))),
            'reply_to' => strtolower(trim((string)($campaignData['reply_to'] ?? ''))),
            'communication_intent' => trim((string)($campaignData['communication_intent'] ?? 'promotional')),
            'html_hash' => hash('sha256', (string)($campaignData['html_content'] ?? '')),
            'plain_hash' => hash('sha256', (string)($campaignData['plain_text_content'] ?? '')),
            'list_id' => (int)($campaignData['list_id'] ?? 0),
            'segment_id' => (int)($campaignData['segment_id'] ?? 0),
            'policy_version' => self::CURRENT_POLICY_VERSION
        ];

        return hash('sha256', (string)json_encode($canonical, JSON_UNESCAPED_UNICODE));
    }

    /**
     * Create immutable campaign approval snapshot.
     */
    public static function createSnapshot(int $campaignId, int $userId, array $campaignData): string {
        $hash = self::computeContentHash($campaignData);
        $db = Database::getInstance()->getConnection();
        $now = date('Y-m-d H:i:s');

        $stmt = $db->prepare('INSERT INTO marketing_campaign_snapshots 
                              (campaign_id, approved_hash, template_revision_id, policy_version, approved_by, approved_at, snapshot_json)
                              VALUES 
                              (:cid, :hash, :trid, :pver, :uid, :now, :json)');
        $stmt->execute([
            ':cid' => $campaignId,
            ':hash' => $hash,
            ':trid' => !empty($campaignData['template_id']) ? (int)$campaignData['template_id'] : null,
            ':pver' => self::CURRENT_POLICY_VERSION,
            ':uid' => $userId,
            ':now' => $now,
            ':json' => json_encode($campaignData, JSON_UNESCAPED_UNICODE)
        ]);

        // Update campaign record with approved hash
        $uStmt = $db->prepare('UPDATE campaigns_broadcast 
                               SET approved_snapshot_hash = :hash, content_sha256 = :hash, approved_by = :uid, approved_at = :now 
                               WHERE campaign_id = :cid');
        $uStmt->execute([
            ':hash' => $hash,
            ':uid' => $userId,
            ':now' => $now,
            ':cid' => $campaignId
        ]);

        return $hash;
    }

    /**
     * Verify that current campaign content strictly matches approved snapshot hash.
     */
    public static function verifySnapshot(int $campaignId): bool {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare('SELECT * FROM campaigns_broadcast WHERE campaign_id = :cid LIMIT 1');
        $stmt->execute([':cid' => $campaignId]);
        $camp = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$camp || empty($camp['approved_snapshot_hash'])) {
            return false;
        }

        $currentHash = self::computeContentHash($camp);
        $approvedHash = $camp['approved_snapshot_hash'];

        if (!hash_equals($approvedHash, $currentHash)) {
            // Invalidate approval
            $db->prepare('UPDATE campaigns_broadcast 
                          SET approved_snapshot_hash = NULL, status = "draft" 
                          WHERE campaign_id = :cid')
               ->execute([':cid' => $campaignId]);
            return false;
        }

        return true;
    }
}

