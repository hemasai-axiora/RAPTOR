<?php
/**
 * WhatsAppConversation Model — 24-Hour Customer Service Session Tracker
 * -------------------------------------------------------------------------
 * Tracks active WhatsApp conversations and enforces Meta's 24-hour rule:
 * - Inbound user messages open a 24-hour free-form session window
 * - Determines whether free-form text or paid templates are required
 * - Links conversations to Customer 360 contacts & assigned sales reps
 */

class WhatsAppConversation extends Model {

    public function findOrCreate(string $phoneNumber, ?int $contactId = null): object {
        $cleanPhone = WhatsAppService::formatPhoneNumber($phoneNumber);

        $this->query("SELECT * FROM whatsapp_conversations WHERE phone_number = :phone LIMIT 1");
        $this->bind(':phone', $cleanPhone);
        $existing = $this->single();

        if ($existing) {
            if (!$existing->contact_id && $contactId) {
                $this->query("UPDATE whatsapp_conversations SET contact_id = :cid WHERE conversation_id = :id");
                $this->bind(':cid', $contactId);
                $this->bind(':id', $existing->conversation_id);
                $this->execute();
                $existing->contact_id = $contactId;
            }
            return $existing;
        }

        // Create new conversation
        $this->query("INSERT INTO whatsapp_conversations (
            contact_id, phone_number, status, session_type, session_category,
            unread_count, created_at, updated_at
        ) VALUES (
            :cid, :phone, 'open', 'user_initiated', 'service',
            0, :now, :now
        )");

        $this->bind(':cid', $contactId);
        $this->bind(':phone', $cleanPhone);
        $this->bind(':now', date('Y-m-d H:i:s'));
        $this->execute();

        $convId = (int)$this->lastInsertId();
        return $this->getById($convId);
    }

    public function getById(int $conversationId): ?object {
        $this->query("SELECT c.*, ct.first_name, ct.last_name, ct.company_name, ct.is_suppressed,
                             u.name AS assigned_user_name
                      FROM whatsapp_conversations c
                      LEFT JOIN contacts ct ON c.contact_id = ct.contact_id
                      LEFT JOIN users u ON c.assigned_user_id = u.user_id
                      WHERE c.conversation_id = :id LIMIT 1");
        $this->bind(':id', $conversationId);
        $row = $this->single();
        return $row ?: null;
    }

    public function getByPhone(string $phoneNumber): ?object {
        $cleanPhone = WhatsAppService::formatPhoneNumber($phoneNumber);
        $this->query("SELECT * FROM whatsapp_conversations WHERE phone_number = :phone LIMIT 1");
        $this->bind(':phone', $cleanPhone);
        $row = $this->single();
        return $row ?: null;
    }

    /**
     * Check if the 24-hour customer service window is currently open.
     */
    public function isSessionActive(int|object $conversation): bool {
        $conv = is_object($conversation) ? $conversation : $this->getById($conversation);
        if (!$conv || empty($conv->session_expires_at)) {
            return false;
        }
        return strtotime($conv->session_expires_at) > time();
    }

    /**
     * Records an incoming user message, opening/refreshing the 24-hour session.
     */
    public function recordInbound(int $conversationId): void {
        $now = date('Y-m-d H:i:s');
        $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));

        $this->query("UPDATE whatsapp_conversations 
                      SET last_inbound_at = :now, 
                          session_expires_at = :expires, 
                          session_type = 'user_initiated',
                          unread_count = unread_count + 1,
                          status = 'open',
                          updated_at = :now 
                      WHERE conversation_id = :id");
        $this->bind(':now', $now);
        $this->bind(':expires', $expiresAt);
        $this->bind(':id', $conversationId);
        $this->execute();
    }

    /**
     * Records an outbound business message.
     */
    public function recordOutbound(int $conversationId): void {
        $now = date('Y-m-d H:i:s');
        $this->query("UPDATE whatsapp_conversations 
                      SET last_outbound_at = :now, updated_at = :now 
                      WHERE conversation_id = :id");
        $this->bind(':now', $now);
        $this->bind(':id', $conversationId);
        $this->execute();
    }

    public function markAsRead(int $conversationId): void {
        $this->query("UPDATE whatsapp_conversations SET unread_count = 0 WHERE conversation_id = :id");
        $this->bind(':id', $conversationId);
        $this->execute();
    }

    public function assignUser(int $conversationId, ?int $userId): bool {
        $this->query("UPDATE whatsapp_conversations SET assigned_user_id = :uid WHERE conversation_id = :id");
        $this->bind(':uid', $userId);
        $this->bind(':id', $conversationId);
        return $this->execute();
    }

    public function getConversations(array $filters = [], int $limit = 50, int $offset = 0): array {
        $sql = "SELECT c.*, ct.first_name, ct.last_name, ct.company_name, ct.is_suppressed,
                       u.name AS assigned_user_name,
                       (SELECT body FROM whatsapp_messages WHERE conversation_id = c.conversation_id ORDER BY created_at DESC LIMIT 1) AS last_message_body,
                       (SELECT direction FROM whatsapp_messages WHERE conversation_id = c.conversation_id ORDER BY created_at DESC LIMIT 1) AS last_message_direction
                FROM whatsapp_conversations c
                LEFT JOIN contacts ct ON c.contact_id = ct.contact_id
                LEFT JOIN users u ON c.assigned_user_id = u.user_id
                WHERE 1=1";

        if (!empty($filters['status'])) {
            $sql .= " AND c.status = :status";
        }
        if (!empty($filters['assigned_user_id'])) {
            $sql .= " AND c.assigned_user_id = :uid";
        }
        if (!empty($filters['search'])) {
            $sql .= " AND (c.phone_number LIKE :srch OR ct.first_name LIKE :srch OR ct.last_name LIKE :srch OR ct.company_name LIKE :srch)";
        }

        $sql .= " ORDER BY c.updated_at DESC LIMIT :limit OFFSET :offset";

        $this->query($sql);
        if (!empty($filters['status'])) $this->bind(':status', $filters['status']);
        if (!empty($filters['assigned_user_id'])) $this->bind(':uid', (int)$filters['assigned_user_id']);
        if (!empty($filters['search'])) $this->bind(':srch', '%' . trim($filters['search']) . '%');
        $this->bind(':limit', max(1, $limit));
        $this->bind(':offset', max(0, $offset));

        return $this->resultSet() ?: [];
    }
}
