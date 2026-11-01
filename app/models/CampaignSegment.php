<?php
/**
 * Raptor CRM - Campaign Segment Model
 * 
 * Provides dynamic segment definition and SQL query generator
 * for rule-based contact filtering (e.g., status, tag, source, custom fields).
 */

class CampaignSegment extends Model {

    public const OPERATORS = [
        'equals' => 'Equals',
        'not_equals' => 'Does not equal',
        'contains' => 'Contains',
        'not_contains' => 'Does not contain',
        'starts_with' => 'Starts with',
        'ends_with' => 'Ends with',
        'is_empty' => 'Is empty',
        'is_not_empty' => 'Is not empty',
        'in_list' => 'Belongs to List',
        'has_tag' => 'Tagged with'
    ];

    public function getAllSegments(): array {
        $this->query('SELECT s.*, u.name as creator_name FROM campaign_segments s
                      LEFT JOIN users u ON s.created_by = u.user_id
                      ORDER BY s.name ASC');
        return $this->resultSet() ?: [];
    }

    public function getById(int $segmentId) {
        $this->query('SELECT * FROM campaign_segments WHERE segment_id = :id');
        $this->bind(':id', $segmentId);
        $seg = $this->single();
        if ($seg && !empty($seg->rules_json)) {
            $seg->rules = json_decode($seg->rules_json, true) ?: [];
        } elseif ($seg) {
            $seg->rules = [];
        }
        return $seg;
    }

    public function saveSegment(array $data, ?int $userId = null): int {
        $name = trim($data['name'] ?? '');
        $description = trim($data['description'] ?? '');
        $matchType = in_array($data['match_type'] ?? '', ['all', 'any'], true) ? $data['match_type'] : 'all';
        $rules = isset($data['rules']) && is_array($data['rules']) ? $data['rules'] : [];
        $rulesJson = json_encode($rules, JSON_UNESCAPED_UNICODE);

        if (empty($name)) {
            throw new InvalidArgumentException('Segment name cannot be empty');
        }

        $segmentId = (int)($data['segment_id'] ?? 0);
        if ($segmentId > 0) {
            $this->query('UPDATE campaign_segments 
                          SET name = :name, description = :desc, match_type = :mt, rules_json = :rules, updated_at = NOW()
                          WHERE segment_id = :id');
            $this->bind(':name', $name);
            $this->bind(':desc', $description);
            $this->bind(':mt', $matchType);
            $this->bind(':rules', $rulesJson);
            $this->bind(':id', $segmentId);
            $this->execute();
            return $segmentId;
        }

        $this->query('INSERT INTO campaign_segments (name, description, match_type, rules_json, created_by)
                      VALUES (:name, :desc, :mt, :rules, :uid)');
        $this->bind(':name', $name);
        $this->bind(':desc', $description);
        $this->bind(':mt', $matchType);
        $this->bind(':rules', $rulesJson);
        $this->bind(':uid', $userId);
        $this->execute();
        return (int)$this->lastInsertId();
    }

    public function deleteSegment(int $segmentId): bool {
        $this->query('DELETE FROM campaign_segments WHERE segment_id = :id');
        $this->bind(':id', $segmentId);
        return $this->execute();
    }

    /**
     * Build SQL WHERE clause and parameters from segment rules.
     */
    public function compileSegmentSql(array $segmentRules, string $matchType = 'all'): array {
        if (empty($segmentRules)) {
            return ['where' => '1=1', 'params' => []];
        }

        $conditions = [];
        $params = [];
        $idx = 0;

        foreach ($segmentRules as $r) {
            $field = trim($r['field'] ?? '');
            $operator = trim($r['operator'] ?? '');
            $value = trim($r['value'] ?? '');
            $idx++;
            $pName = ':p_seg_' . $idx;

            switch ($operator) {
                case 'equals':
                    $conditions[] = "c.{$field} = {$pName}";
                    $params[$pName] = $value;
                    break;
                case 'not_equals':
                    $conditions[] = "(c.{$field} != {$pName} OR c.{$field} IS NULL)";
                    $params[$pName] = $value;
                    break;
                case 'contains':
                    $conditions[] = "c.{$field} LIKE {$pName}";
                    $params[$pName] = '%' . $value . '%';
                    break;
                case 'not_contains':
                    $conditions[] = "(c.{$field} NOT LIKE {$pName} OR c.{$field} IS NULL)";
                    $params[$pName] = '%' . $value . '%';
                    break;
                case 'starts_with':
                    $conditions[] = "c.{$field} LIKE {$pName}";
                    $params[$pName] = $value . '%';
                    break;
                case 'ends_with':
                    $conditions[] = "c.{$field} LIKE {$pName}";
                    $params[$pName] = '%' . $value;
                    break;
                case 'is_empty':
                    $conditions[] = "(c.{$field} IS NULL OR c.{$field} = '')";
                    break;
                case 'is_not_empty':
                    $conditions[] = "(c.{$field} IS NOT NULL AND c.{$field} != '')";
                    break;
                case 'in_list':
                    $conditions[] = "c.contact_id IN (SELECT lc.contact_id FROM campaign_list_contacts lc WHERE lc.list_id = {$pName} AND lc.status = 'active')";
                    $params[$pName] = (int)$value;
                    break;
                case 'has_tag':
                    $conditions[] = "c.contact_id IN (SELECT ct.contact_id FROM campaign_contact_tags ct JOIN campaign_tags t ON ct.tag_id = t.tag_id WHERE t.name = {$pName} OR t.tag_id = {$pName})";
                    $params[$pName] = $value;
                    break;
            }
        }

        if (empty($conditions)) {
            return ['where' => '1=1', 'params' => []];
        }

        $glue = ($matchType === 'any') ? ' OR ' : ' AND ';
        return [
            'where' => '(' . implode($glue, $conditions) . ')',
            'params' => $params
        ];
    }

    /**
     * Count contacts currently matching this segment.
     */
    public function countMatchingContacts(int $segmentId): int {
        $seg = $this->getById($segmentId);
        if (!$seg) {
            return 0;
        }

        $compiled = $this->compileSegmentSql($seg->rules, $seg->match_type ?? 'all');
        $sql = "SELECT COUNT(DISTINCT c.contact_id) FROM campaign_contacts c WHERE c.status = 'subscribed' AND " . $compiled['where'];

        $this->query($sql);
        foreach ($compiled['params'] as $k => $v) {
            $this->bind($k, $v);
        }
        $row = $this->single();
        return (int)($row ? current((array)$row) : 0);
    }

    /**
     * Retrieve matching contact IDs for campaign sending.
     */
    public function getMatchingContactIds(int $segmentId): array {
        $seg = $this->getById($segmentId);
        if (!$seg) {
            return [];
        }

        $compiled = $this->compileSegmentSql($seg->rules, $seg->match_type ?? 'all');
        $sql = "SELECT DISTINCT c.contact_id FROM campaign_contacts c WHERE c.status = 'subscribed' AND " . $compiled['where'];

        $this->query($sql);
        foreach ($compiled['params'] as $k => $v) {
            $this->bind($k, $v);
        }
        $rows = $this->resultSet() ?: [];
        $ids = [];
        foreach ($rows as $r) {
            $ids[] = (int)$r->contact_id;
        }
        return $ids;
    }
}
