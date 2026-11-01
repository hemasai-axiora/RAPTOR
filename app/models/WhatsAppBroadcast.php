<?php
/**
 * WhatsAppBroadcast Model — Bulk WhatsApp Marketing Campaigns
 * -------------------------------------------------------------------------
 * Manages bulk broadcast campaigns using approved Meta Message Templates:
 * - List-based and segment-based recipient scheduling
 * - Automatic pre-send suppression checks
 * - Execution counters (queued, sent, delivered, read, failed)
 */

class WhatsAppBroadcast extends Model {

    public function createBroadcast(string $name, int $templateId, ?int $listId = null, ?string $scheduledAt = null, ?int $createdBy = null): int {
        $this->query("INSERT INTO whatsapp_broadcasts (
            name, template_id, list_id, status, scheduled_at, created_by, created_at
        ) VALUES (
            :name, :tmpl_id, :list_id, 'draft', :sched, :created_by, :now
        )");

        $this->bind(':name', trim($name));
        $this->bind(':tmpl_id', $templateId);
        $this->bind(':list_id', $listId);
        $this->bind(':sched', $scheduledAt);
        $this->bind(':created_by', $createdBy);
        $this->bind(':now', date('Y-m-d H:i:s'));

        if ($this->execute()) {
            return (int)$this->lastInsertId();
        }
        return 0;
    }

    public function getById(int $broadcastId): ?object {
        $this->query("SELECT b.*, t.name AS template_name, t.category AS template_category,
                             t.body_text AS template_body
                      FROM whatsapp_broadcasts b
                      JOIN whatsapp_templates t ON b.template_id = t.template_id
                      WHERE b.broadcast_id = :id LIMIT 1");
        $this->bind(':id', $broadcastId);
        $row = $this->single();
        return $row ?: null;
    }

    public function addRecipients(int $broadcastId, array $recipients): int {
        $inserted = 0;
        foreach ($recipients as $r) {
            $contactId = (int)$r['contact_id'];
            $phone = WhatsAppService::formatPhoneNumber($r['phone_number']);
            $params = !empty($r['parameters']) ? (is_array($r['parameters']) ? json_encode($r['parameters']) : $r['parameters']) : null;

            if (empty($phone) || empty($contactId)) continue;

            $this->query("INSERT INTO whatsapp_broadcast_recipients (
                broadcast_id, contact_id, phone_number, parameters_json, status, created_at
            ) VALUES (
                :bid, :cid, :phone, :params, 'queued', :now
            )");
            $this->bind(':bid', $broadcastId);
            $this->bind(':cid', $contactId);
            $this->bind(':phone', $phone);
            $this->bind(':params', $params);
            $this->bind(':now', date('Y-m-d H:i:s'));

            if ($this->execute()) {
                $inserted++;
            }
        }

        // Update total recipient count
        $this->query("UPDATE whatsapp_broadcasts SET total_recipients = total_recipients + :cnt WHERE broadcast_id = :id");
        $this->bind(':cnt', $inserted);
        $this->bind(':id', $broadcastId);
        $this->execute();

        return $inserted;
    }

    public function getQueuedRecipients(int $broadcastId, int $limit = 50): array {
        $this->query("SELECT r.*, c.is_suppressed 
                      FROM whatsapp_broadcast_recipients r
                      JOIN contacts c ON r.contact_id = c.contact_id
                      WHERE r.broadcast_id = :bid AND r.status = 'queued'
                      LIMIT :limit");
        $this->bind(':bid', $broadcastId);
        $this->bind(':limit', max(1, $limit));
        return $this->resultSet() ?: [];
    }

    public function markRecipientSent(int $recipientId, string $metaMessageId): void {
        $this->query("UPDATE whatsapp_broadcast_recipients 
                      SET status = 'sent', meta_message_id = :mid, sent_at = :now 
                      WHERE recipient_id = :id");
        $this->bind(':mid', $metaMessageId);
        $this->bind(':now', date('Y-m-d H:i:s'));
        $this->bind(':id', $recipientId);
        $this->execute();
    }

    public function markRecipientFailed(int $recipientId, string $errorCode, string $errorMessage): void {
        $this->query("UPDATE whatsapp_broadcast_recipients 
                      SET status = 'failed', error_code = :code, error_message = :msg 
                      WHERE recipient_id = :id");
        $this->bind(':code', $errorCode);
        $this->bind(':msg', substr($errorMessage, 0, 255));
        $this->bind(':id', $recipientId);
        $this->execute();
    }

    public function updateBroadcastStatus(int $broadcastId, string $status): bool {
        $this->query("UPDATE whatsapp_broadcasts SET status = :status, updated_at = :now WHERE broadcast_id = :id");
        $this->bind(':status', $status);
        $this->bind(':now', date('Y-m-d H:i:s'));
        $this->bind(':id', $broadcastId);
        return $this->execute();
    }

    public function getAllBroadcasts(): array {
        $this->query("SELECT b.*, t.name AS template_name, u.name AS creator_name
                      FROM whatsapp_broadcasts b
                      JOIN whatsapp_templates t ON b.template_id = t.template_id
                      LEFT JOIN users u ON b.created_by = u.user_id
                      ORDER BY b.created_at DESC");
        return $this->resultSet() ?: [];
    }
}
