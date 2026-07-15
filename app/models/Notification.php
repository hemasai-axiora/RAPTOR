<?php
// Sprint 13 - Notification center and push subscription storage.

class Notification extends Model {
    public function forUser(int $userId, bool $unreadOnly = false, int $limit = 50): array {
        $sql = 'SELECT * FROM notifications WHERE user_id = :uid';
        if ($unreadOnly) {
            $sql .= ' AND is_read = 0';
        }
        $sql .= ' ORDER BY created_at DESC LIMIT :limit';
        $this->query($sql);
        $this->bind(':uid', $userId);
        $this->bind(':limit', $limit, PDO::PARAM_INT);
        return $this->resultSet();
    }

    public function unreadCount(int $userId): int {
        $this->query('SELECT COUNT(*) AS c FROM notifications WHERE user_id = :uid AND is_read = 0');
        $this->bind(':uid', $userId);
        $row = $this->single();
        return $row ? (int) $row->c : 0;
    }

    public function markRead(int $userId, int $notificationId): bool {
        $this->query('UPDATE notifications
                      SET is_read = 1, read_at = COALESCE(read_at, NOW())
                      WHERE user_id = :uid AND notification_id = :id');
        $this->bind(':uid', $userId);
        $this->bind(':id', $notificationId);
        return $this->execute();
    }

    public function markAllRead(int $userId): bool {
        $this->query('UPDATE notifications SET is_read = 1, read_at = COALESCE(read_at, NOW())
                      WHERE user_id = :uid AND is_read = 0');
        $this->bind(':uid', $userId);
        return $this->execute();
    }

    public function storeSubscription(int $userId, array $subscription, string $userAgent = ''): bool {
        $endpoint = trim($subscription['endpoint'] ?? '');
        $keys = $subscription['keys'] ?? [];
        if ($endpoint === '' || empty($keys['p256dh']) || empty($keys['auth'])) {
            return false;
        }

        $this->query('SELECT subscription_id FROM push_subscriptions WHERE user_id = :uid AND endpoint = :endpoint LIMIT 1');
        $this->bind(':uid', $userId);
        $this->bind(':endpoint', $endpoint);
        $existing = $this->single();

        if ($existing) {
            $this->query('UPDATE push_subscriptions
                          SET p256dh_key = :p256dh, auth_key = :auth, user_agent = :ua,
                              active = 1, last_seen_at = NOW()
                          WHERE subscription_id = :id');
            $this->bind(':id', (int) $existing->subscription_id);
        } else {
            $this->query('INSERT INTO push_subscriptions
                            (user_id, endpoint, p256dh_key, auth_key, user_agent, active, last_seen_at)
                          VALUES (:uid, :endpoint, :p256dh, :auth, :ua, 1, NOW())');
            $this->bind(':uid', $userId);
            $this->bind(':endpoint', $endpoint);
        }

        $this->bind(':p256dh', $keys['p256dh']);
        $this->bind(':auth', $keys['auth']);
        $this->bind(':ua', substr($userAgent, 0, 255));
        return $this->execute();
    }
}
