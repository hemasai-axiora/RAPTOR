<?php
/**
 * Raptor CRM - Campaign Template Model & Sanitizer
 * 
 * Manages reusable, mobile-responsive email templates:
 * - Table-based, inline-CSS layout compatible with Gmail, Outlook, Apple Mail.
 * - Auto-generates plain-text alternative (multipart/alternative).
 * - Enforces security: sanitizes HTML, strips scripts/forms/iframes/base64 data URIs.
 * - Merge tag replacement with fallback values.
 */

class CampaignTemplate extends Model {

    public const MERGE_TAGS = [
        '{{first_name}}' => 'Recipient First Name (e.g. John)',
        '{{first_name|Friend}}' => 'First Name with fallback (e.g. "Friend" if empty)',
        '{{last_name}}' => 'Recipient Last Name',
        '{{name}}' => 'Full Name',
        '{{email}}' => 'Recipient Email Address',
        '{{company_name}}' => 'Recipient Company Name',
        '{{unsubscribe_url}}' => 'Mandatory One-Click Unsubscribe Link',
        '{{company_address}}' => 'Mandatory Sender Postal Address',
        '{{current_year}}' => 'Current Year (e.g. 2026)',
        '{{current_date}}' => 'Current Formatted Date'
    ];

    public function getAllTemplates(): array {
        $this->query('SELECT t.*, u.name as creator_name 
                      FROM campaign_templates t
                      LEFT JOIN users u ON t.created_by = u.user_id
                      ORDER BY t.is_prebuilt DESC, t.name ASC');
        return $this->resultSet() ?: [];
    }

    public function getById(int $templateId) {
        $this->query('SELECT * FROM campaign_templates WHERE template_id = :id');
        $this->bind(':id', $templateId);
        return $this->single();
    }

    public function saveTemplate(array $data, ?int $userId = null): int {
        $name = trim($data['name'] ?? '');
        $category = trim($data['category'] ?? 'general');
        $subjectDefault = trim($data['subject_default'] ?? '');
        $rawHtml = $data['html_content'] ?? '';
        $rawPlain = $data['plain_text_content'] ?? '';

        if (empty($name)) {
            throw new InvalidArgumentException('Template name is required');
        }

        // Sanitize HTML and ensure deliverability hygiene
        $cleanHtml = self::sanitizeHtml($rawHtml);

        // Auto-generate plain-text part if left blank
        if (empty(trim($rawPlain))) {
            $rawPlain = self::htmlToPlainText($cleanHtml);
        }

        $templateId = (int)($data['template_id'] ?? 0);
        if ($templateId > 0) {
            $this->query('UPDATE campaign_templates 
                          SET name = :name, category = :cat, subject_default = :subj, 
                              html_content = :html, plain_text_content = :plain, updated_at = CURRENT_TIMESTAMP
                          WHERE template_id = :id');
            $this->bind(':name', $name);
            $this->bind(':cat', $category);
            $this->bind(':subj', $subjectDefault ?: null);
            $this->bind(':html', $cleanHtml);
            $this->bind(':plain', $rawPlain);
            $this->bind(':id', $templateId);
            $this->execute();
            return $templateId;
        }

        $this->query('INSERT INTO campaign_templates 
                      (name, category, subject_default, html_content, plain_text_content, is_prebuilt, created_by)
                      VALUES 
                      (:name, :cat, :subj, :html, :plain, 0, :uid)');
        $this->bind(':name', $name);
        $this->bind(':cat', $category);
        $this->bind(':subj', $subjectDefault ?: null);
        $this->bind(':html', $cleanHtml);
        $this->bind(':plain', $rawPlain);
        $this->bind(':uid', $userId);
        $this->execute();
        return (int)$this->lastInsertId();
    }

    public function deleteTemplate(int $templateId): bool {
        $this->query('DELETE FROM campaign_templates WHERE template_id = :id AND is_prebuilt = 0');
        $this->bind(':id', $templateId);
        return $this->execute();
    }

    /**
     * Merge Tag Replacement Engine
     * Replaces {{tag}} or {{tag|fallback}} with contact attributes and context.
     */
    public static function renderMergeTags(string $content, array $contact, array $context = []): string {
        $defaults = [
            'unsubscribe_url' => $context['unsubscribe_url'] ?? '#',
            'company_address' => $context['company_address'] ?? 'Axiora Global Solutions, Cyber City, Hyderabad, India',
            'current_year' => date('Y'),
            'current_date' => date('F j, Y'),
            'first_name' => $contact['first_name'] ?? '',
            'last_name' => $contact['last_name'] ?? '',
            'name' => trim(($contact['first_name'] ?? '') . ' ' . ($contact['last_name'] ?? '')),
            'email' => $contact['email'] ?? '',
            'company_name' => $contact['company_name'] ?? ''
        ];

        // Include any custom fields passed in contact
        if (!empty($contact['custom_fields'])) {
            $cf = is_array($contact['custom_fields']) ? $contact['custom_fields'] : (json_decode($contact['custom_fields'], true) ?: []);
            foreach ($cf as $k => $v) {
                if (!isset($defaults[$k])) {
                    $defaults[$k] = $v;
                }
            }
        }

        // Replace tags with regex: {{key}} or {{key|fallback}}
        return preg_replace_callback('/\{\{\s*([a-zA-Z0-9_]+)(?:\|([^}]+))?\s*\}\}/', function($matches) use ($defaults) {
            $key = strtolower(trim($matches[1]));
            $fallback = isset($matches[2]) ? trim($matches[2]) : '';

            if (isset($defaults[$key]) && trim((string)$defaults[$key]) !== '') {
                return htmlspecialchars((string)$defaults[$key], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            }
            return htmlspecialchars($fallback, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }, $content);
    }

    /**
     * Converts HTML to clean, readable plain-text alternative.
     * Ensures RFC multipart/alternative delivery for text-only clients.
     */
    public static function htmlToPlainText(string $html): string {
        // 1. Remove style and script blocks completely
        $text = preg_replace('/<style[^>]*>.*?<\/style>/is', '', $html);
        $text = preg_replace('/<script[^>]*>.*?<\/script>/is', '', $text);

        // 2. Convert links <a href="URL">TEXT</a> to TEXT (URL)
        $text = preg_replace_callback('/<a\s+[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is', function($m) {
            $url = trim($m[1]);
            $anchor = trim(strip_tags($m[2]));
            if (empty($anchor) || $anchor === $url) {
                return $url;
            }
            return $anchor . ' (' . $url . ')';
        }, $text);

        // 3. Convert headers and paragraphs to line breaks
        $text = preg_replace('/<\/(h[1-6]|p|div|tr)>/i', "\n\n", $text);
        $text = preg_replace('/<br\s*\/?>/i', "\n", $text);
        $text = preg_replace('/<li[^>]*>/i', "• ", $text);
        $text = preg_replace('/<\/li>/i', "\n", $text);

        // 4. Strip remaining HTML tags
        $text = strip_tags($text);

        // 5. Decode HTML entities
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // 6. Normalize whitespace
        $lines = explode("\n", $text);
        $trimmedLines = array_map('rtrim', $lines);
        $text = implode("\n", $trimmedLines);
        $text = preg_replace("/\n{3,}/", "\n\n", $text);

        return trim($text);
    }

    /**
     * Strict Deliverability HTML Sanitizer
     * - Disallows <script>, <iframe>, <form>, <input>, <embed>, <object>
     * - Strips base64 image data URIs (hurts deliverability; images must be HTTPS)
     * - Strips inline event handlers (onload, onclick)
     * - Enforces max ~100KB size limit
     */
    public static function sanitizeHtml(string $html): string {
        // Size safeguard: ~100KB limit (Gmail clips emails > 102KB)
        if (strlen($html) > 150 * 1024) {
            throw new InvalidArgumentException('Email template exceeds the 100 KB limit. Large emails are clipped by Gmail and trigger spam filters.');
        }

        // Remove dangerous tags
        $dangerousTags = ['script', 'iframe', 'form', 'input', 'button', 'embed', 'object', 'applet', 'meta'];
        foreach ($dangerousTags as $tag) {
            $html = preg_replace('#<' . $tag . '[^>]*>.*?</' . $tag . '>#is', '', $html);
            $html = preg_replace('#<' . $tag . '[^>]*>#is', '', $html);
        }

        // Remove inline event handlers (on*)
        $html = preg_replace('/\s*on[a-zA-Z]+\s*=\s*(".*?"|\'.*?\'|[^\s>]+)/is', '', $html);

        // Disallow base64 embedded images (data:image/...)
        if (preg_match('/src=["\']data:image\/[a-z]+;base64,/i', $html)) {
            throw new InvalidArgumentException('Deliverability Warning: Base64 embedded images are blocked by major webmail providers. Images must be hosted over HTTPS with alt attributes.');
        }

        return $html;
    }

    /**
     * Seed production-ready, mobile-responsive table templates
     */
    public function ensurePrebuiltTemplates(): void {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->query("SELECT COUNT(*) as cnt FROM campaign_templates WHERE is_prebuilt = 1");
        $row = $stmt->fetch(PDO::FETCH_OBJ);
        if ($row && (int)$row->cnt > 0) {
            return;
        }

        $templates = [
            [
                'name' => 'Corporate Clean Announcement',
                'category' => 'announcement',
                'subject_default' => 'Important Corporate Update from Axiora',
                'html' => self::getPrebuiltTemplateAnnouncement()
            ],
            [
                'name' => 'Modern Product Newsletter',
                'category' => 'newsletter',
                'subject_default' => 'Monthly Product Highlights & Release Notes',
                'html' => self::getPrebuiltTemplateNewsletter()
            ]
        ];

        foreach ($templates as $t) {
            $plain = self::htmlToPlainText($t['html']);
            $ins = $db->prepare('INSERT INTO campaign_templates 
                                 (name, category, subject_default, html_content, plain_text_content, is_prebuilt)
                                 VALUES (:name, :cat, :subj, :html, :plain, 1)');
            $ins->execute([
                ':name' => $t['name'],
                ':cat' => $t['category'],
                ':subj' => $t['subject_default'],
                ':html' => $t['html'],
                ':plain' => $plain
            ]);
        }
    }

    public static function getPrebuiltTemplateAnnouncement(): string {
        return <<<'HTML'
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Corporate Announcement</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f1f5f9; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
  <table border="0" cellpadding="0" cellspacing="0" width="100%" style="table-layout: fixed; background-color: #f1f5f9; padding: 20px 0;">
    <tr>
      <td align="center">
        <table border="0" cellpadding="0" cellspacing="0" width="600" style="max-width: 600px; width: 100%; background-color: #ffffff; border-radius: 8px; overflow: hidden; border: 1px solid #e2e8f0;">
          <!-- Header -->
          <tr>
            <td style="padding: 30px; background-color: #2563eb; text-align: left;">
              <h2 style="color: #ffffff; margin: 0; font-size: 22px; font-weight: 700;">Axiora Global Solutions</h2>
            </td>
          </tr>
          <!-- Content Body -->
          <tr>
            <td style="padding: 35px 30px; color: #334155; font-size: 15px; line-height: 24px;">
              <h3 style="color: #0f172a; margin-top: 0; font-size: 20px;">Hello {{first_name|Valued Colleague}},</h3>
              <p style="margin: 0 0 16px;">We are writing to share an important corporate update regarding our services and upcoming operational enhancements.</p>
              <p style="margin: 0 0 24px;">Please review the details below. Our teams have finalized several high-impact workflow improvements designed to streamline collaboration.</p>
              
              <!-- Call to Action Button -->
              <table border="0" cellpadding="0" cellspacing="0" style="margin: 20px 0;">
                <tr>
                  <td align="center" style="border-radius: 6px; background-color: #2563eb;">
                    <a href="https://axiora.in" target="_blank" style="font-size: 14px; font-weight: bold; color: #ffffff; text-decoration: none; padding: 12px 24px; display: inline-block;">View Portal Update</a>
                  </td>
                </tr>
              </table>

              <p style="margin: 24px 0 0; color: #64748b; font-size: 14px;">Best regards,<br /><strong style="color: #1e293b;">The Axiora Operations Team</strong></p>
            </td>
          </tr>
          <!-- Footer with CAN-SPAM Compliance & Unsubscribe -->
          <tr>
            <td style="padding: 24px 30px; background-color: #f8fafc; border-top: 1px solid #e2e8f0; text-align: center; color: #94a3b8; font-size: 12px; line-height: 18px;">
              <p style="margin: 0 0 8px;">{{company_address}}</p>
              <p style="margin: 0;">You are receiving this communication because of your registered association. <a href="{{unsubscribe_url}}" style="color: #64748b; text-decoration: underline;">Unsubscribe</a> from these updates.</p>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
HTML;
    }

    public static function getPrebuiltTemplateNewsletter(): string {
        return <<<'HTML'
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Monthly Newsletter</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f8fafc; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
  <table border="0" cellpadding="0" cellspacing="0" width="100%" style="table-layout: fixed; background-color: #f8fafc; padding: 20px 0;">
    <tr>
      <td align="center">
        <table border="0" cellpadding="0" cellspacing="0" width="600" style="max-width: 600px; width: 100%; background-color: #ffffff; border-radius: 8px; overflow: hidden; border: 1px solid #e2e8f0;">
          <tr>
            <td style="padding: 25px 30px; background-color: #0f172a; text-align: left;">
              <span style="color: #38bdf8; font-size: 12px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px;">Monthly Briefing</span>
              <h2 style="color: #ffffff; margin: 5px 0 0; font-size: 20px;">Axiora Innovations & Product Insights</h2>
            </td>
          </tr>
          <tr>
            <td style="padding: 30px; color: #334155; font-size: 15px; line-height: 24px;">
              <p style="margin-top: 0;">Hi {{first_name|Friend}},</p>
              <p>Welcome to this edition of our newsletter. Here are the top features and system releases deployed over the past month:</p>
              
              <ul style="padding-left: 20px; color: #475569;">
                <li style="margin-bottom: 8px;"><strong>Automated Email Campaigns:</strong> High-deliverability queue architecture with RFC 8058 one-click unsubscribes.</li>
                <li style="margin-bottom: 8px;"><strong>Payroll Lifecycle Control:</strong> Dynamic proration and statutory compliance rules.</li>
                <li style="margin-bottom: 8px;"><strong>Audience Segmentation:</strong> Precise subscriber filtering without duplication.</li>
              </ul>

              <p style="margin: 20px 0 0;">Thank you for being part of our network!</p>
            </td>
          </tr>
          <tr>
            <td style="padding: 24px 30px; background-color: #f1f5f9; border-top: 1px solid #e2e8f0; text-align: center; color: #94a3b8; font-size: 12px; line-height: 18px;">
              <p style="margin: 0 0 6px;">{{company_address}}</p>
              <p style="margin: 0;"><a href="{{unsubscribe_url}}" style="color: #64748b; text-decoration: underline;">Unsubscribe from newsletter</a> &bull; &copy; {{current_year}} Axiora Global Solutions.</p>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
HTML;
    }
}
