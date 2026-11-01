<?php
/**
 * Raptor CRM - Campaign List Model
 */

class CampaignList extends Model {

    public function getAllLists(): array {
        $this->query('SELECT l.*, 
                             COUNT(DISTINCT lc.contact_id) AS total_contacts,
                             SUM(CASE WHEN lc.status = "active" AND c.status = "subscribed" THEN 1 ELSE 0 END) AS active_subscribers,
                             u.name AS creator_name
                      FROM campaign_lists l
                      LEFT JOIN campaign_list_contacts lc ON l.list_id = lc.list_id
                      LEFT JOIN campaign_contacts c ON lc.contact_id = c.contact_id
                      LEFT JOIN users u ON l.created_by = u.user_id
                      GROUP BY l.list_id
                      ORDER BY l.is_internal DESC, l.name ASC');
        return $this->resultSet() ?: [];
    }

    public function getById(int $listId) {
        $this->query('SELECT l.*, 
                             COUNT(DISTINCT lc.contact_id) AS total_contacts,
                             SUM(CASE WHEN lc.status = "active" AND c.status = "subscribed" THEN 1 ELSE 0 END) AS active_subscribers
                      FROM campaign_lists l
                      LEFT JOIN campaign_list_contacts lc ON l.list_id = lc.list_id
                      LEFT JOIN campaign_contacts c ON lc.contact_id = c.contact_id
                      WHERE l.list_id = :id
                      GROUP BY l.list_id');
        $this->bind(':id', $listId);
        return $this->single();
    }

    public function createList(string $name, ?string $description = null, bool $isInternal = false, string $optinMode = 'single', ?int $userId = null): int {
        $cleanName = trim($name);
        if (empty($cleanName)) {
            throw new InvalidArgumentException('List name cannot be empty');
        }

        $this->query('INSERT INTO campaign_lists (name, description, is_internal, optin_mode, created_by)
                      VALUES (:name, :desc, :internal, :optin, :uid)');
        $this->bind(':name', $cleanName);
        $this->bind(':desc', $description);
        $this->bind(':internal', $isInternal ? 1 : 0);
        $this->bind(':optin', in_array($optinMode, ['single', 'double'], true) ? $optinMode : 'single');
        $this->bind(':uid', $userId);
        $this->execute();
        return (int)$this->lastInsertId();
    }

    public function updateList(int $listId, string $name, ?string $description = null, string $optinMode = 'single'): bool {
        $cleanName = trim($name);
        if (empty($cleanName)) {
            return false;
        }

        $this->query('UPDATE campaign_lists 
                      SET name = :name, description = :desc, optin_mode = :optin, updated_at = NOW() 
                      WHERE list_id = :id');
        $this->bind(':name', $cleanName);
        $this->bind(':desc', $description);
        $this->bind(':optin', in_array($optinMode, ['single', 'double'], true) ? $optinMode : 'single');
        $this->bind(':id', $listId);
        return $this->execute();
    }

    public function deleteList(int $listId): bool {
        $this->query('DELETE FROM campaign_lists WHERE list_id = :id');
        $this->bind(':id', $listId);
        return $this->execute();
    }
}
