<?php
/**
 * WhatsAppMessage Model — Message Ledger & Delivery Receipts
 * -------------------------------------------------------------------------
 * Tracks every WhatsApp message transmitted and received:
 * - Links meta_message_id to local records
 * - Updates real-time statuses (sent, delivered, read, failed) from webhooks
 * - Calculates estimated conversation-level messaging costs
 */

class WhatsAppMessage extends Model {

    public function recordMessage(array $data): int {
        $this->query("INSERT INTO whatsapp_messages (
            conversation_id, contact_id, direction, message_type, meta_message_id,
            template_id, body, media_url, payload_json, status, error_code,
            error_message, cost_estimated, sent_by_user_id, sent_at, created_at
        ) VALUES (
            :cid, :contact_id, :dir, :mtype, :meta_id,
            :tmpl_id, :body, :media, :payload, :status, :err_code,
            :err_msg, :cost, :user_id, :sent_at, :now
        )");

        $now = date('Y-m-d H:i:s');
        $this->bind(':cid', (int)$data['conversation_id']);
        $this->bind(':contact_id', !empty($data['contact_id']) ? (int)$data['contact_id'] : null);
        $this->bind(':dir', $data['direction'] ?? 'outbound');
        $this->bind(':mtype', $data['message_type'] ?? 'text');
        $this->bind(':meta_id', $data['meta_message_id'] ?? null);
        $this->bind(':tmpl_id', !empty($data['template_id']) ? (int)$data['template_id'] : null);
        $this->bind(':body', $data['body'] ?? null);
        $this->bind(':media', $data['media_url'] ?? null);
        $this->bind(':payload', is_array($data['payload'] ?? null) ? json_encode($data['payload']) : ($data['payload_json'] ?? null));
        $this->bind(':status', $data['status'] ?? 'pending');
        $this->bind(':err_code', $data['error_code'] ?? null);
        $this->bind(':err_msg', $data['error_message'] ?? null);
        $this->bind(':cost', (float)($data['cost_estimated'] ?? 0.0));
        $this->bind(':user_id', !empty($data['sent_by_user_id']) ? (int)$data['sent_by_user_id'] : null);
        $this->bind(':sent_at', ($data['direction'] ?? '') === 'outbound' ? $now : null);
        $this->bind(':now', $now);

        if ($this->execute()) {
            return (int)$this->lastInsertId();
        }
        return 0;
    }

    public function getByMetaId(string $metaMessageId): ?object {
        $this->query("SELECT * FROM whatsapp_messages WHERE meta_message_id = :mid LIMIT 1");
        $this->bind(':mid', trim($metaMessageId));
        $row = $this->single();
        return $row ?: null;
    }

    public function updateDeliveryStatus(string $metaMessageId, string $status, ?string $timestamp = null, ?string $errorCode = null, ?string $errorMsg = null): bool {
        $msg = $this->getByMetaId($metaMessageId);
        if (!$msg) {
            return false;
        }

        $now = $timestamp ? date('Y-m-d H:i:s', (int)$timestamp) : date('Y-m-d H:i:s');
        $updates = ["status = :status"];
        $params = [':status' => $status, ':mid' => $metaMessageId];

        if ($status === 'delivered') {
            $updates[] = "delivered_at = COALESCE(delivered_at, :now)";
            $params[':now'] = $now;
        } elseif ($status === 'read') {
            $updates[] = "read_at = COALESCE(read_at, :now)";
            $params[':now'] = $now;
        } elseif ($status === 'failed') {
            $updates[] = "error_code = :err_code, error_message = :err_msg";
            $params[':err_code'] = $errorCode;
            $params[':err_msg'] = $errorMsg;
        }

        $sql = "UPDATE whatsapp_messages SET " . implode(', ', $updates) . " WHERE meta_message_id = :mid";
        $this->query($sql);
        foreach ($params as $k => $v) {
            $this->bind($k, $v);
        }
        return $this->execute();
    }

    public function getMessagesForConversation(int $conversationId, int $limit = 100): array {
        $this->query("SELECT m.*, u.name AS sent_by_name, t.name AS template_name
                      FROM whatsapp_messages m
                      LEFT JOIN users u ON m.sent_by_user_id = u.user_id
                      LEFT JOIN whatsapp_templates t ON m.template_id = t.template_id
                      WHERE m.conversation_id = :cid
                      ORDER BY m.created_at ASC
                      LIMIT :limit");
        $this->bind(':cid', $conversationId);
        $this->bind(':limit', max(1, $limit));
        return $this->resultSet() ?: [];
    }
}
