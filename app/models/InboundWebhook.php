<?php
/**
 * InboundWebhook Model — External API Ingestion Endpoints
 * -------------------------------------------------------------------------
 * Manages authenticated webhook ingestion endpoints for WordPress, Webflow,
 * Meta Lead Ads, Zapier, and custom external lead generation sources.
 */

class InboundWebhook extends Model {

    public function createEndpoint(string $name, string $provider, ?int $assignedUserId = null, string $leadSource = 'webhook', ?array $fieldMapping = null): array {
        $endpointKey = 'wh_' . bin2hex(random_bytes(16));
        $secret = 'whsec_' . bin2hex(random_bytes(24));
        $secretHash = hash('sha256', $secret);

        $this->query("INSERT INTO inbound_webhook_endpoints (
            name, provider, endpoint_key, secret_hash, assigned_user_id,
            default_lead_source, field_mapping_json, is_active, total_received, created_at
        ) VALUES (
            :name, :provider, :key, :hash, :assigned,
            :source, :mapping, 1, 0, :now
        )");

        $this->bind(':name', trim($name));
        $this->bind(':provider', $provider);
        $this->bind(':key', $endpointKey);
        $this->bind(':hash', $secretHash);
        $this->bind(':assigned', $assignedUserId);
        $this->bind(':source', $leadSource);
        $this->bind(':mapping', $fieldMapping ? json_encode($fieldMapping) : null);
        $this->bind(':now', date('Y-m-d H:i:s'));

        if ($this->execute()) {
            return [
                'endpoint_id' => (int)$this->lastInsertId(),
                'endpoint_key' => $endpointKey,
                'secret' => $secret
            ];
        }
        return [];
    }

    public function getByKey(string $endpointKey): ?object {
        $this->query("SELECT * FROM inbound_webhook_endpoints WHERE endpoint_key = :key AND is_active = 1 LIMIT 1");
        $this->bind(':key', trim($endpointKey));
        $row = $this->single();
        return $row ?: null;
    }

    public function verifySignature(object $endpoint, string $payload, string $signature): bool {
        // Compute expected HMAC-SHA256 signature
        $expected = hash_hmac('sha256', $payload, $endpoint->secret_hash);
        // Also support raw match against signature if bearer token used
        return hash_equals($expected, $signature) || hash_equals($endpoint->secret_hash, hash('sha256', $signature));
    }

    public function recordHit(int $endpointId): void {
        try {
            $this->query("UPDATE inbound_webhook_endpoints 
                          SET total_received = total_received + 1, last_received_at = :now 
                          WHERE endpoint_id = :id");
            $this->bind(':now', date('Y-m-d H:i:s'));
            $this->bind(':id', $endpointId);
            $this->execute();
        } catch (Throwable $e) {}
    }

    public function getAllEndpoints(): array {
        $this->query("SELECT w.*, u.name AS assigned_user_name 
                      FROM inbound_webhook_endpoints w
                      LEFT JOIN users u ON w.assigned_user_id = u.user_id
                      ORDER BY w.created_at DESC");
        return $this->resultSet() ?: [];
    }
}

