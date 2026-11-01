<?php
/**
 * WhatsAppTemplate Model — Meta Approved Message Templates
 * -------------------------------------------------------------------------
 * Manages WhatsApp Business Message Templates:
 * - Categories: MARKETING, UTILITY, AUTHENTICATION
 * - Components: Header, Body with {{1}}, {{2}} variables, Footer, Buttons
 * - Meta review states: APPROVED, PENDING, REJECTED, PAUSED
 */

class WhatsAppTemplate extends Model {

    public const CATEGORIES = ['MARKETING', 'UTILITY', 'AUTHENTICATION'];
    public const STATUSES = ['APPROVED', 'PENDING', 'REJECTED', 'PAUSED'];
    public const HEADER_TYPES = ['NONE', 'TEXT', 'IMAGE', 'VIDEO', 'DOCUMENT'];

    public function saveTemplate(array $data): int {
        $name = strtolower(preg_replace('/[^a-z0-9_]/', '_', trim($data['name'] ?? '')));
        $category = in_array($data['category'] ?? '', self::CATEGORIES, true) ? $data['category'] : 'MARKETING';
        $language = trim($data['language'] ?? 'en_US');
        $headerType = in_array($data['header_type'] ?? '', self::HEADER_TYPES, true) ? $data['header_type'] : 'NONE';
        $status = in_array($data['status'] ?? '', self::STATUSES, true) ? $data['status'] : 'APPROVED';

        $buttons = !empty($data['buttons']) ? (is_array($data['buttons']) ? json_encode($data['buttons']) : $data['buttons']) : null;
        $variables = !empty($data['variables_sample']) ? (is_array($data['variables_sample']) ? json_encode($data['variables_sample']) : $data['variables_sample']) : null;

        $this->query("INSERT INTO whatsapp_templates (
            meta_template_id, name, category, language, status, header_type,
            header_content, body_text, footer_text, buttons_json, variables_sample_json,
            created_by, created_at
        ) VALUES (
            :meta_id, :name, :cat, :lang, :status, :htype,
            :hcontent, :body, :footer, :buttons, :vars,
            :created_by, :now
        )");

        $this->bind(':meta_id', $data['meta_template_id'] ?? null);
        $this->bind(':name', $name);
        $this->bind(':cat', $category);
        $this->bind(':lang', $language);
        $this->bind(':status', $status);
        $this->bind(':htype', $headerType);
        $this->bind(':hcontent', $data['header_content'] ?? null);
        $this->bind(':body', trim($data['body_text'] ?? ''));
        $this->bind(':footer', $data['footer_text'] ?? null);
        $this->bind(':buttons', $buttons);
        $this->bind(':vars', $variables);
        $this->bind(':created_by', !empty($data['created_by']) ? (int)$data['created_by'] : null);
        $this->bind(':now', date('Y-m-d H:i:s'));

        if ($this->execute()) {
            return (int)$this->lastInsertId();
        }
        return 0;
    }

    public function getById(int $templateId): ?object {
        $this->query("SELECT * FROM whatsapp_templates WHERE template_id = :id LIMIT 1");
        $this->bind(':id', $templateId);
        $row = $this->single();
        return $row ?: null;
    }

    public function getByName(string $name): ?object {
        $clean = strtolower(trim($name));
        $this->query("SELECT * FROM whatsapp_templates WHERE name = :name LIMIT 1");
        $this->bind(':name', $clean);
        $row = $this->single();
        return $row ?: null;
    }

    public function getApprovedTemplates(string $category = ''): array {
        $sql = "SELECT * FROM whatsapp_templates WHERE status = 'APPROVED'";
        if (!empty($category)) {
            $sql .= " AND category = :cat";
        }
        $sql .= " ORDER BY name ASC";

        $this->query($sql);
        if (!empty($category)) {
            $this->bind(':cat', $category);
        }
        return $this->resultSet() ?: [];
    }

    public function getAllTemplates(): array {
        $this->query("SELECT t.*, u.name AS creator_name 
                      FROM whatsapp_templates t
                      LEFT JOIN users u ON t.created_by = u.user_id
                      ORDER BY t.created_at DESC");
        return $this->resultSet() ?: [];
    }

    public function updateStatus(int $templateId, string $status, ?string $metaTemplateId = null, ?string $rejectionReason = null): bool {
        $this->query("UPDATE whatsapp_templates 
                      SET status = :status, meta_template_id = COALESCE(:meta_id, meta_template_id),
                          rejection_reason = :reason, updated_at = :now 
                      WHERE template_id = :id");
        $this->bind(':status', in_array($status, self::STATUSES, true) ? $status : 'PENDING');
        $this->bind(':meta_id', $metaTemplateId);
        $this->bind(':reason', $rejectionReason);
        $this->bind(':now', date('Y-m-d H:i:s'));
        $this->bind(':id', $templateId);
        return $this->execute();
    }

    public static function renderBody(string $templateBody, array $variables = []): string {
        $rendered = $templateBody;
        foreach ($variables as $index => $val) {
            $placeholder = '{{' . ($index + 1) . '}}';
            $rendered = str_replace($placeholder, (string)$val, $rendered);
        }
        return $rendered;
    }
}
