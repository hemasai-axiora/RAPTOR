<?php
/**
 * InboxThread Model — Consolidated Customer Omnichannel Threads
 * -------------------------------------------------------------------------
 * Unifies all messages, calls, WhatsApp chats, and private internal notes
 * for a single contact into an actionable shared inbox thread.
 */

class InboxThread extends Model {

    public const STATUSES = ['unassigned', 'open', 'pending', 'resolved', 'snoozed'];
    public const PRIORITIES = ['low', 'normal', 'high', 'urgent'];
    public const CHANNELS = ['whatsapp', 'email', 'sms'];

    public function getOrCreateForContact(int $contactId, string $initialChannel = 'whatsapp'): object {
        $this->query("SELECT * FROM inbox_threads WHERE contact_id = :cid LIMIT 1");
        $this->bind(':cid', $contactId);
        $existing = $this->single();

        if ($existing) {
            return $existing;
        }

        $channel = in_array($initialChannel, self::CHANNELS, true) ? $initialChannel : 'whatsapp';
        $now = date('Y-m-d H:i:s');

        $this->query("INSERT INTO inbox_threads (
            contact_id, status, priority, channel_primary, last_channel,
            last_message_preview, last_message_at, unread_count, created_at, updated_at
        ) VALUES (
            :cid, 'unassigned', 'normal', :chan, :chan,
            'New conversation started', :now, 0, :now, :now
        )");

        $this->bind(':cid', $contactId);
        $this->bind(':chan', $channel);
        $this->bind(':now', $now);
        $this->execute();

        $threadId = (int)$this->lastInsertId();
        return $this->getById($threadId);
    }

    public function getById(int $threadId): ?object {
        $this->query("SELECT t.*, c.first_name, c.last_name, c.company_name, c.primary_email,
                             c.primary_phone, c.lifecycle_stage, c.lead_score, c.lead_quality,
                             c.is_suppressed, c.suppression_reason, u.name AS assigned_user_name
                      FROM inbox_threads t
                      JOIN contacts c ON t.contact_id = c.contact_id
                      LEFT JOIN users u ON t.assigned_user_id = u.user_id
                      WHERE t.thread_id = :id LIMIT 1");
        $this->bind(':id', $threadId);
        $row = $this->single();
        return $row ?: null;
    }

    public function getByContactId(int $contactId): ?object {
        $this->query("SELECT * FROM inbox_threads WHERE contact_id = :cid LIMIT 1");
        $this->bind(':cid', $contactId);
        $row = $this->single();
        return $row ?: null;
    }

    public function assignRep(int $threadId, ?int $userId): bool {
        $statusUpdate = $userId ? ", status = CASE WHEN status = 'unassigned' THEN 'open' ELSE status END" : ", status = 'unassigned'";
        $this->query("UPDATE inbox_threads 
                      SET assigned_user_id = :uid, updated_at = :now {$statusUpdate} 
                      WHERE thread_id = :id");
        $this->bind(':uid', $userId);
        $this->bind(':now', date('Y-m-d H:i:s'));
        $this->bind(':id', $threadId);
        return $this->execute();
    }

    public function updateStatus(int $threadId, string $status, ?int $resolvedByUserId = null): bool {
        if (!in_array($status, self::STATUSES, true)) {
            return false;
        }

        $now = date('Y-m-d H:i:s');
        $sql = "UPDATE inbox_threads SET status = :status, updated_at = :now";
        $params = [':status' => $status, ':now' => $now, ':id' => $threadId];

        if ($status === 'resolved') {
            $sql .= ", resolved_at = :now, resolved_by = :res_by";
            $params[':res_by'] = $resolvedByUserId;
        } elseif ($status === 'open' || $status === 'unassigned') {
            $sql .= ", resolved_at = NULL, resolved_by = NULL";
        }

        $sql .= " WHERE thread_id = :id";
        $this->query($sql);
        foreach ($params as $k => $v) {
            $this->bind($k, $v);
        }
        return $this->execute();
    }

    public function updatePriority(int $threadId, string $priority): bool {
        if (!in_array($priority, self::PRIORITIES, true)) {
            return false;
        }
        $this->query("UPDATE inbox_threads SET priority = :pri, updated_at = :now WHERE thread_id = :id");
        $this->bind(':pri', $priority);
        $this->bind(':now', date('Y-m-d H:i:s'));
        $this->bind(':id', $threadId);
        return $this->execute();
    }

    public function recordNewMessage(int $threadId, string $preview, string $channel, bool $isInbound): void {
        $now = date('Y-m-d H:i:s');
        $cleanPreview = substr(trim($preview), 0, 250);

        $sql = "UPDATE inbox_threads 
                SET last_message_preview = :prev, 
                    last_channel = :chan, 
                    last_message_at = :now, 
                    updated_at = :now";

        if ($isInbound) {
            $sql .= ", unread_count = unread_count + 1, status = CASE WHEN status = 'resolved' THEN 'open' ELSE status END";
        }

        $sql .= " WHERE thread_id = :id";

        $this->query($sql);
        $this->bind(':prev', $cleanPreview);
        $this->bind(':chan', in_array($channel, self::CHANNELS, true) ? $channel : 'whatsapp');
        $this->bind(':now', $now);
        $this->bind(':id', $threadId);
        $this->execute();
    }

    public function markRead(int $threadId): void {
        $this->query("UPDATE inbox_threads SET unread_count = 0 WHERE thread_id = :id");
        $this->bind(':id', $threadId);
        $this->execute();
    }

    public function getThreads(array $filters = [], int $limit = 50, int $offset = 0): array {
        $conds = [];
        $params = [];

        if (!empty($filters['tab'])) {
            if ($filters['tab'] === 'mine' && !empty($filters['current_user_id'])) {
                $conds[] = "t.assigned_user_id = :cuid AND t.status != 'resolved'";
                $params[':cuid'] = (int)$filters['current_user_id'];
            } elseif ($filters['tab'] === 'unassigned') {
                $conds[] = "t.assigned_user_id IS NULL AND t.status != 'resolved'";
            } elseif ($filters['tab'] === 'resolved') {
                $conds[] = "t.status = 'resolved'";
            } else {
                // Default 'all' active
                $conds[] = "t.status != 'resolved'";
            }
        } elseif (!empty($filters['status'])) {
            $conds[] = "t.status = :status";
            $params[':status'] = $filters['status'];
        }

        if (!empty($filters['priority'])) {
            $conds[] = "t.priority = :pri";
            $params[':pri'] = $filters['priority'];
        }

        if (!empty($filters['search'])) {
            $conds[] = "(c.first_name LIKE :srch OR c.last_name LIKE :srch OR c.company_name LIKE :srch OR c.primary_email LIKE :srch OR c.primary_phone LIKE :srch OR t.last_message_preview LIKE :srch)";
            $params[':srch'] = '%' . trim($filters['search']) . '%';
        }

        $whereSql = !empty($conds) ? 'WHERE ' . implode(' AND ', $conds) : '';

        $sql = "SELECT t.*, c.first_name, c.last_name, c.company_name, c.primary_email,
                       c.primary_phone, c.lifecycle_stage, c.lead_score, c.lead_quality,
                       c.is_suppressed, u.name AS assigned_user_name
                FROM inbox_threads t
                JOIN contacts c ON t.contact_id = c.contact_id
                LEFT JOIN users u ON t.assigned_user_id = u.user_id
                {$whereSql}
                ORDER BY t.last_message_at DESC
                LIMIT :limit OFFSET :offset";

        $this->query($sql);
        foreach ($params as $k => $v) {
            $this->bind($k, $v);
        }
        $this->bind(':limit', max(1, $limit));
        $this->bind(':offset', max(0, $offset));

        return $this->resultSet() ?: [];
    }
}

