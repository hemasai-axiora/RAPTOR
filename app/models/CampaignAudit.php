<?php
/**
 * Raptor CRM - Campaign Compliance Audit Logger
 * 
 * Logs critical campaign and contact actions for GDPR / DPDP / CAN-SPAM governance.
 */

class CampaignAudit extends Model {

    public static function log(?int $userId, string $action, string $entityType, int $entityId, array $details = []): void {
        try {
            $db = Database::getInstance()->getConnection();
            $ip = $_SERVER['REMOTE_ADDR'] ?? (php_sapi_name() === 'cli' ? '127.0.0.1' : null);
            $stmt = $db->prepare('INSERT INTO campaign_audit_logs (user_id, action, entity_type, entity_id, details_json, ip_address)
                                  VALUES (:uid, :act, :etype, :eid, :details, :ip)');
            $stmt->execute([
                ':uid' => $userId,
                ':act' => $action,
                ':etype' => $entityType,
                ':eid' => $entityId,
                ':details' => json_encode($details, JSON_UNESCAPED_UNICODE),
                ':ip' => $ip
            ]);
        } catch (Throwable $t) {
            error_log('CampaignAudit log failed: ' . $t->getMessage());
        }
    }
}
