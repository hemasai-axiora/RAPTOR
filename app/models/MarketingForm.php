<?php
/**
 * MarketingForm Model — Dynamic Web Forms & Embed Generator
 * -------------------------------------------------------------------------
 * Manages embeddable lead capture forms with field definitions,
 * conversion metrics, automated lead routing, and anti-spam controls.
 */

class MarketingForm extends Model {

    public const DEFAULT_FIELDS = [
        ['name' => 'first_name', 'label' => 'First Name', 'type' => 'text', 'required' => true],
        ['name' => 'last_name', 'label' => 'Last Name', 'type' => 'text', 'required' => false],
        ['name' => 'email', 'label' => 'Email Address', 'type' => 'email', 'required' => true],
        ['name' => 'phone', 'label' => 'Phone Number', 'type' => 'tel', 'required' => false],
        ['name' => 'company_name', 'label' => 'Company Name', 'type' => 'text', 'required' => false],
        ['name' => 'notes', 'label' => 'Message / Notes', 'type' => 'textarea', 'required' => false]
    ];

    public function createForm(array $data): int {
        $uuid = $this->generateUuid();
        $fields = !empty($data['fields']) ? (is_array($data['fields']) ? json_encode($data['fields']) : $data['fields']) : json_encode(self::DEFAULT_FIELDS);

        $this->query("INSERT INTO marketing_forms (
            form_uuid, name, description, form_type, fields_json, submit_button_text,
            success_message, redirect_url, assigned_user_id, default_lead_source,
            default_lead_quality, enable_honeypot, enable_captcha, captcha_secret_key,
            notification_emails, is_active, created_by, created_at
        ) VALUES (
            :uuid, :name, :desc, :type, :fields, :btn,
            :msg, :redir, :assigned, :source,
            :quality, :honey, :captcha, :csec,
            :emails, 1, :created_by, :now
        )");

        $this->bind(':uuid', $uuid);
        $this->bind(':name', trim($data['name'] ?? 'Untitled Web Form'));
        $this->bind(':desc', $data['description'] ?? null);
        $this->bind(':type', $data['form_type'] ?? 'embedded');
        $this->bind(':fields', $fields);
        $this->bind(':btn', trim($data['submit_button_text'] ?? 'Submit'));
        $this->bind(':msg', trim($data['success_message'] ?? 'Thank you! We have received your submission.'));
        $this->bind(':redir', !empty($data['redirect_url']) ? trim($data['redirect_url']) : null);
        $this->bind(':assigned', !empty($data['assigned_user_id']) ? (int)$data['assigned_user_id'] : null);
        $this->bind(':source', trim($data['default_lead_source'] ?? 'web_form'));
        $this->bind(':quality', trim($data['default_lead_quality'] ?? 'warm'));
        $this->bind(':honey', isset($data['enable_honeypot']) ? (int)$data['enable_honeypot'] : 1);
        $this->bind(':captcha', isset($data['enable_captcha']) ? (int)$data['enable_captcha'] : 0);
        $this->bind(':csec', $data['captcha_secret_key'] ?? null);
        $this->bind(':emails', $data['notification_emails'] ?? null);
        $this->bind(':created_by', !empty($data['created_by']) ? (int)$data['created_by'] : null);
        $this->bind(':now', date('Y-m-d H:i:s'));

        if ($this->execute()) {
            return (int)$this->lastInsertId();
        }
        return 0;
    }

    public function getById(int $formId): ?object {
        $this->query("SELECT f.*, u.name AS assigned_user_name 
                      FROM marketing_forms f
                      LEFT JOIN users u ON f.assigned_user_id = u.user_id
                      WHERE f.form_id = :id LIMIT 1");
        $this->bind(':id', $formId);
        $row = $this->single();
        return $row ?: null;
    }

    public function getByUuid(string $uuid): ?object {
        $this->query("SELECT * FROM marketing_forms WHERE form_uuid = :uuid LIMIT 1");
        $this->bind(':uuid', trim($uuid));
        $row = $this->single();
        return $row ?: null;
    }

    public function getAllForms(int $limit = 50, int $offset = 0): array {
        $this->query("SELECT f.*, u.name AS assigned_user_name,
                             ROUND(CASE WHEN f.views_count > 0 THEN (f.submissions_count * 100.0 / f.views_count) ELSE 0 END, 1) AS conversion_rate
                      FROM marketing_forms f
                      LEFT JOIN users u ON f.assigned_user_id = u.user_id
                      ORDER BY f.created_at DESC
                      LIMIT :limit OFFSET :offset");
        $this->bind(':limit', max(1, $limit));
        $this->bind(':offset', max(0, $offset));
        return $this->resultSet() ?: [];
    }

    public function countForms(): int {
        $this->query("SELECT COUNT(*) AS total FROM marketing_forms");
        $row = $this->single();
        return $row ? (int)$row->total : 0;
    }

    public function incrementViews(int $formId): void {
        try {
            $this->query("UPDATE marketing_forms SET views_count = views_count + 1 WHERE form_id = :id");
            $this->bind(':id', $formId);
            $this->execute();
        } catch (Throwable $e) {}
    }

    public function incrementSubmissions(int $formId): void {
        try {
            $this->query("UPDATE marketing_forms SET submissions_count = submissions_count + 1 WHERE form_id = :id");
            $this->bind(':id', $formId);
            $this->execute();
        } catch (Throwable $e) {}
    }

    public function generateEmbedSnippet(object $form, string $format = 'iframe'): string {
        $urlRoot = defined('URLROOT') ? URLROOT : '';
        $formUuid = htmlspecialchars($form->form_uuid);

        if ($format === 'iframe') {
            return '<iframe src="' . $urlRoot . '/index.php?route=forms/render&uuid=' . $formUuid . '" ' .
                   'width="100%" height="480" frameborder="0" style="border:none;border-radius:8px;max-width:600px;" ' .
                   'title="' . htmlspecialchars($form->name) . '"></iframe>';
        }

        // JavaScript Widget Embed Snippet
        return '<div id="raptor-form-' . $formUuid . '"></div>' . "\n" .
               '<script src="' . $urlRoot . '/raptor.js" data-form-uuid="' . $formUuid . '" async></script>';
    }

    private function generateUuid(): string {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
}
