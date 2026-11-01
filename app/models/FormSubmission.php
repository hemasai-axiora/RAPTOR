<?php
/**
 * FormSubmission Model — Audit Ledger for Inbound Submissions
 * -------------------------------------------------------------------------
 * Captures all raw web form and webhook submissions, links them to resolved
 * Customer 360 contacts, stores UTM attribution, and tracks spam flags.
 */

class FormSubmission extends Model {

    public function recordSubmission(array $data): int {
        $this->query("INSERT INTO form_submissions (
            form_id, contact_id, lead_id, visitor_cookie, ip_address, user_agent,
            page_url, referrer, utm_source, utm_medium, utm_campaign, utm_content,
            utm_term, submitted_data_json, is_spam, spam_reason, created_at
        ) VALUES (
            :form_id, :contact_id, :lead_id, :cookie, :ip, :ua,
            :url, :ref, :usrc, :umed, :ucmp, :ucont,
            :uterm, :data_json, :spam, :sreason, :now
        )");

        $this->bind(':form_id', !empty($data['form_id']) ? (int)$data['form_id'] : null);
        $this->bind(':contact_id', !empty($data['contact_id']) ? (int)$data['contact_id'] : null);
        $this->bind(':lead_id', !empty($data['lead_id']) ? (int)$data['lead_id'] : null);
        $this->bind(':cookie', !empty($data['visitor_cookie']) ? substr(trim($data['visitor_cookie']), 0, 64) : null);
        $this->bind(':ip', !empty($data['ip_address']) ? substr(trim($data['ip_address']), 0, 45) : null);
        $this->bind(':ua', !empty($data['user_agent']) ? substr(trim($data['user_agent']), 0, 255) : null);
        $this->bind(':url', !empty($data['page_url']) ? substr(trim($data['page_url']), 0, 2048) : null);
        $this->bind(':ref', !empty($data['referrer']) ? substr(trim($data['referrer']), 0, 2048) : null);
        $this->bind(':usrc', !empty($data['utm_source']) ? substr(trim($data['utm_source']), 0, 100) : null);
        $this->bind(':umed', !empty($data['utm_medium']) ? substr(trim($data['utm_medium']), 0, 100) : null);
        $this->bind(':ucmp', !empty($data['utm_campaign']) ? substr(trim($data['utm_campaign']), 0, 100) : null);
        $this->bind(':ucont', !empty($data['utm_content']) ? substr(trim($data['utm_content']), 0, 100) : null);
        $this->bind(':uterm', !empty($data['utm_term']) ? substr(trim($data['utm_term']), 0, 100) : null);
        $this->bind(':data_json', is_array($data['submitted_data'] ?? null) ? json_encode($data['submitted_data']) : ($data['submitted_data_json'] ?? '{}'));
        $this->bind(':spam', !empty($data['is_spam']) ? 1 : 0);
        $this->bind(':sreason', $data['spam_reason'] ?? null);
        $this->bind(':now', date('Y-m-d H:i:s'));

        if ($this->execute()) {
            return (int)$this->lastInsertId();
        }
        return 0;
    }

    public function getSubmissionsForForm(int $formId, int $limit = 50, int $offset = 0): array {
        $this->query("SELECT s.*, c.first_name AS contact_first_name, c.company_name AS contact_company,
                             c.primary_email, c.primary_phone, l.lead_code
                      FROM form_submissions s
                      LEFT JOIN contacts c ON s.contact_id = c.contact_id
                      LEFT JOIN leads l ON s.lead_id = l.lead_id
                      WHERE s.form_id = :fid
                      ORDER BY s.created_at DESC
                      LIMIT :limit OFFSET :offset");
        $this->bind(':fid', $formId);
        $this->bind(':limit', max(1, $limit));
        $this->bind(':offset', max(0, $offset));
        return $this->resultSet() ?: [];
    }

    public function getRecentSubmissions(int $limit = 50): array {
        $this->query("SELECT s.*, f.name AS form_name, c.primary_email, c.first_name, c.company_name, l.lead_code
                      FROM form_submissions s
                      LEFT JOIN marketing_forms f ON s.form_id = f.form_id
                      LEFT JOIN contacts c ON s.contact_id = c.contact_id
                      LEFT JOIN leads l ON s.lead_id = l.lead_id
                      ORDER BY s.created_at DESC
                      LIMIT :limit");
        $this->bind(':limit', max(1, $limit));
        return $this->resultSet() ?: [];
    }
}
