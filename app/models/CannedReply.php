<?php
/**
 * CannedReply Model — Fast Response Shortcuts & Snippets
 * -------------------------------------------------------------------------
 * Pre-composed response templates with variable merge tags for
 * instant team member resolution (/pricing, /demo, /intro, /resolved).
 */

class CannedReply extends Model {

    public function getAll(string $category = ''): array {
        $sql = "SELECT c.*, u.name AS creator_name 
                FROM canned_replies c
                LEFT JOIN users u ON c.created_by = u.user_id";
        if (!empty($category)) {
            $sql .= " WHERE c.category = :cat";
        }
        $sql .= " ORDER BY c.shortcut ASC";

        $this->query($sql);
        if (!empty($category)) {
            $this->bind(':cat', $category);
        }
        return $this->resultSet() ?: [];
    }

    public function getByShortcut(string $shortcut): ?object {
        $clean = trim($shortcut);
        if (!str_starts_with($clean, '/')) {
            $clean = '/' . $clean;
        }

        $this->query("SELECT * FROM canned_replies WHERE shortcut = :sc LIMIT 1");
        $this->bind(':sc', $clean);
        $row = $this->single();
        return $row ?: null;
    }

    public function create(string $shortcut, string $title, string $category, string $bodyText, ?int $createdBy = null): int {
        $clean = trim($shortcut);
        if (!str_starts_with($clean, '/')) {
            $clean = '/' . $clean;
        }

        $this->query("INSERT INTO canned_replies (shortcut, title, category, body_text, created_by, created_at)
                      VALUES (:sc, :title, :cat, :body, :uid, :now)");
        $this->bind(':sc', $clean);
        $this->bind(':title', trim($title));
        $this->bind(':cat', trim($category));
        $this->bind(':body', trim($bodyText));
        $this->bind(':uid', $createdBy);
        $this->bind(':now', date('Y-m-d H:i:s'));

        if ($this->execute()) {
            return (int)$this->lastInsertId();
        }
        return 0;
    }

    public static function interpolate(string $text, ?object $contact): string {
        if (!$contact) return $text;

        $replacements = [
            '{{first_name}}' => $contact->first_name ?: 'there',
            '{{last_name}}' => $contact->last_name ?: '',
            '{{company}}' => $contact->company_name ?: 'your company',
            '{{email}}' => $contact->primary_email ?: '',
            '{{phone}}' => $contact->primary_phone ?: ''
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $text);
    }
}

