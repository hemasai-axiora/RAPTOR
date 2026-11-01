<?php
/**
 * Raptor CRM - Campaign Tag Model
 */

class CampaignTag extends Model {

    public function getAllTags(): array {
        $this->query('SELECT t.*, COUNT(ct.contact_id) AS contact_count 
                      FROM campaign_tags t
                      LEFT JOIN campaign_contact_tags ct ON t.tag_id = ct.tag_id
                      GROUP BY t.tag_id
                      ORDER BY t.name ASC');
        return $this->resultSet() ?: [];
    }

    public function getOrCreateTag(string $name, string $color = '#3b82f6'): int {
        $cleanName = trim($name);
        if (empty($cleanName)) {
            return 0;
        }

        $this->query('SELECT tag_id FROM campaign_tags WHERE name = :name LIMIT 1');
        $this->bind(':name', $cleanName);
        $row = $this->single();
        if ($row) {
            return (int)$row->tag_id;
        }

        $this->query('INSERT INTO campaign_tags (name, color) VALUES (:name, :color)');
        $this->bind(':name', $cleanName);
        $this->bind(':color', $color);
        $this->execute();
        return (int)$this->lastInsertId();
    }

    public function deleteTag(int $tagId): bool {
        $this->query('DELETE FROM campaign_tags WHERE tag_id = :id');
        $this->bind(':id', $tagId);
        return $this->execute();
    }
}

