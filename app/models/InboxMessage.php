<?php
/**
 * InboxMessage Model — Multi-Channel Unified Message Stream
 * -------------------------------------------------------------------------
 * Stores individual message items in an omnichannel thread:
 * - Public customer communications (WhatsApp, Email, SMS)
 * - Internal private team notes (Yellow notes, visible only to agents)
 * - Telephony summaries and CRM system notices
 */

class InboxMessage extends Model {

    public const CHANNELS = ['whatsapp', 'email', 'sms', 'call', 'note'];
    public const DIRECTIONS = ['inbound', 'outbound', 'internal'];

    public function recordMessage(array $data): int {
        $threadId = (int)$data['thread_id'];
        $contactId = (int)$data['contact_id'];
        $channel = in_array($data['channel'] ?? '', self::CHANNELS, true) ? $data['channel'] : 'whatsapp';
        $direction = in_array($data['direction'] ?? '', self::DIRECTIONS, true) ? $data['direction'] : 'outbound';
        $isPrivateNote = !empty($data['is_private_note']) ? 1 : 0;
        $body = trim($data['body'] ?? '');
        $userId = !empty($data['sender_user_id']) ? (int)$data['sender_user_id'] : null;
        $status = $data['status'] ?? 'sent';
        $externalId = $data['external_id'] ?? null;
        $mediaUrl = $data['media_url'] ?? null;
        $now = date('Y-m-d H:i:s');

        $this->query("INSERT INTO inbox_messages (
            thread_id, contact_id, channel, direction, is_private_note,
            body, media_url, sender_user_id, external_id, status, created_at
        ) VALUES (
            :tid, :cid, :chan, :dir, :pnote,
            :body, :media, :uid, :ext_id, :status, :now
        )");

        $this->bind(':tid', $threadId);
        $this->bind(':cid', $contactId);
        $this->bind(':chan', $channel);
        $this->bind(':dir', $direction);
        $this->bind(':pnote', $isPrivateNote);
        $this->bind(':body', $body);
        $this->bind(':media', $mediaUrl);
        $this->bind(':uid', $userId);
        $this->bind(':ext_id', $externalId);
        $this->bind(':status', $status);
        $this->bind(':now', $now);

        if ($this->execute()) {
            $msgId = (int)$this->lastInsertId();

            // Update thread metadata
            $threadModel = new InboxThread();
            $isInbound = ($direction === 'inbound');
            $preview = $isPrivateNote ? "[Internal Note] {$body}" : $body;
            $threadModel->recordNewMessage($threadId, $preview, $channel, $isInbound);

            // If public touchpoint, log to Customer 360 timeline
            if (!$isPrivateNote) {
                try {
                    $contactModel = new Contact();
                    $activityType = $isInbound ? "{$channel}_received" : "{$channel}_sent";
                    $title = $isInbound ? "Received " . ucfirst($channel) : "Sent " . ucfirst($channel);
                    $contactModel->recordActivity(
                        $contactId,
                        $channel === 'whatsapp' ? 'whatsapp' : ($channel === 'email' ? 'email' : 'crm'),
                        $activityType,
                        $title,
                        $body,
                        ['external_id' => $externalId, 'thread_id' => $threadId],
                        $userId
                    );
                } catch (Throwable $e) {}
            }

            return $msgId;
        }
        return 0;
    }

    public function getMessagesForThread(int $threadId, int $limit = 100): array {
        $this->query("SELECT m.*, u.name AS sender_user_name, u.role AS sender_role
                      FROM inbox_messages m
                      LEFT JOIN users u ON m.sender_user_id = u.user_id
                      WHERE m.thread_id = :tid
                      ORDER BY m.created_at ASC
                      LIMIT :limit");
        $this->bind(':tid', $threadId);
        $this->bind(':limit', max(1, $limit));
        return $this->resultSet() ?: [];
    }
}

