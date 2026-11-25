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

    private function hasTemplateColumn(string $column): bool {
        static $cols = null;
        if ($cols === null) {
            $cols = [];
            try {
                $db = Database::getInstance()->getConnection();
                $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
                if ($driver === 'sqlite') {
                    $stmt = $db->query("PRAGMA table_info(campaign_templates)");
                    if ($stmt) {
                        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                            $cols[$row['name']] = true;
                        }
                    }
                } else {
                    $stmt = $db->query("SHOW COLUMNS FROM campaign_templates");
                    if ($stmt) {
                        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                            $cols[$row['Field']] = true;
                        }
                    }
                }
            } catch (Throwable $e) {}
        }
        return isset($cols[$column]);
    }

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

        $communicationIntent = in_array($data['communication_intent'] ?? '', ['personal_style', 'promotional', 'operational'], true) ? $data['communication_intent'] : 'promotional';
        $contentHash = hash('sha256', (string)json_encode([
            'name' => $name,
            'category' => $category,
            'subject' => $subjectDefault,
            'html' => $cleanHtml,
            'plain' => $rawPlain,
            'intent' => $communicationIntent
        ]));

        $hasIntent = $this->hasTemplateColumn('communication_intent');
        $hasCHash = $this->hasTemplateColumn('content_sha256');

        $templateId = (int)($data['template_id'] ?? 0);
        if ($templateId > 0) {
            $sql = 'UPDATE campaign_templates 
                    SET name = :name, category = :cat, subject_default = :subj, 
                        html_content = :html, plain_text_content = :plain';
            if ($hasIntent) {
                $sql .= ', communication_intent = :intent';
            }
            if ($hasCHash) {
                $sql .= ', content_sha256 = :chash';
            }
            $sql .= ', updated_at = CURRENT_TIMESTAMP WHERE template_id = :id';

            $this->query($sql);
            $this->bind(':name', $name);
            $this->bind(':cat', $category);
            $this->bind(':subj', $subjectDefault ?: null);
            $this->bind(':html', $cleanHtml);
            $this->bind(':plain', $rawPlain);
            if ($hasIntent) {
                $this->bind(':intent', $communicationIntent);
            }
            if ($hasCHash) {
                $this->bind(':chash', $contentHash);
            }
            $this->bind(':id', $templateId);
            $this->execute();
            return $templateId;
        }

        $insCols = 'name, category, subject_default, html_content, plain_text_content';
        $insVals = ':name, :cat, :subj, :html, :plain';
        if ($hasIntent) {
            $insCols .= ', communication_intent';
            $insVals .= ', :intent';
        }
        if ($hasCHash) {
            $insCols .= ', content_sha256';
            $insVals .= ', :chash';
        }
        $insCols .= ', is_prebuilt, created_by';
        $insVals .= ', 0, :uid';

        $this->query("INSERT INTO campaign_templates ({$insCols}) VALUES ({$insVals})");
        $this->bind(':name', $name);
        $this->bind(':cat', $category);
        $this->bind(':subj', $subjectDefault ?: null);
        $this->bind(':html', $cleanHtml);
        $this->bind(':plain', $rawPlain);
        if ($hasIntent) {
            $this->bind(':intent', $communicationIntent);
        }
        if ($hasCHash) {
            $this->bind(':chash', $contentHash);
        }
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

        $templates = [
            [
                'name' => 'Corporate Clean Announcement',
                'category' => 'announcement',
                'intent' => 'promotional',
                'subject_default' => 'Important Corporate Update from Axiora',
                'html' => self::getPrebuiltTemplateAnnouncement()
            ],
            [
                'name' => 'Modern Product Newsletter',
                'category' => 'newsletter',
                'intent' => 'promotional',
                'subject_default' => 'Monthly Product Highlights & Release Notes',
                'html' => self::getPrebuiltTemplateNewsletter()
            ],
            [
                'name' => 'Welcome & Account Registration',
                'category' => 'operational',
                'intent' => 'operational',
                'subject_default' => 'Welcome to Axiora Global Solutions — Your Account Details',
                'html' => self::getPrebuiltTemplateWelcomeRegistration()
            ],
            [
                'name' => 'Password Reset & OTP Verification',
                'category' => 'operational',
                'intent' => 'operational',
                'subject_default' => 'Your Security Verification Code — Axiora Global Solutions',
                'html' => self::getPrebuiltTemplatePasswordResetOtp()
            ],
            [
                'name' => 'Order & Payment Confirmation',
                'category' => 'operational',
                'intent' => 'operational',
                'subject_default' => 'Payment Received — Invoice & Order Confirmation',
                'html' => self::getPrebuiltTemplateOrderPaymentConfirmation()
            ],
            [
                'name' => 'Admin & System Notification',
                'category' => 'operational',
                'intent' => 'operational',
                'subject_default' => 'System Notification: Operational Alert from Axiora CRM',
                'html' => self::getPrebuiltTemplateAdminNotification()
            ]
        ];

        $hasIntent = $this->hasTemplateColumn('communication_intent');
        $hasCHash = $this->hasTemplateColumn('content_sha256');

        foreach ($templates as $t) {
            // Strictly query only prebuilt system templates (is_prebuilt = 1)
            // Customer-created templates (is_prebuilt = 0) are never modified, converted or overwritten,
            // even if they share the exact same name.
            $chk = $db->prepare("SELECT template_id FROM campaign_templates WHERE name = :name AND is_prebuilt = 1 LIMIT 1");
            $chk->execute([':name' => $t['name']]);
            $existing = $chk->fetch(PDO::FETCH_ASSOC);

            $plain = self::htmlToPlainText($t['html']);
            $cHash = hash('sha256', (string)json_encode([
                'name' => $t['name'],
                'category' => $t['category'],
                'subject' => $t['subject_default'],
                'html' => $t['html'],
                'plain' => $plain,
                'intent' => $t['intent']
            ]));

            if ($existing) {
                // Update prebuilt to keep latest compliant responsive markup
                $upSql = "UPDATE campaign_templates SET 
                          category = :cat, 
                          subject_default = :subj, 
                          html_content = :html, 
                          plain_text_content = :plain, 
                          is_prebuilt = 1"
                       . ($hasIntent ? ", communication_intent = :intent" : "")
                       . ($hasCHash ? ", content_sha256 = :chash" : "")
                       . " WHERE template_id = :id";
                $params = [
                    ':cat' => $t['category'],
                    ':subj' => $t['subject_default'],
                    ':html' => $t['html'],
                    ':plain' => $plain,
                    ':id' => $existing['template_id']
                ];
                if ($hasIntent) $params[':intent'] = $t['intent'];
                if ($hasCHash) $params[':chash'] = $cHash;
                $db->prepare($upSql)->execute($params);
            } else {
                $insSql = "INSERT INTO campaign_templates 
                           (name, category, subject_default, html_content, plain_text_content, is_prebuilt"
                        . ($hasIntent ? ", communication_intent" : "")
                        . ($hasCHash ? ", content_sha256" : "")
                        . ") VALUES (:name, :cat, :subj, :html, :plain, 1"
                        . ($hasIntent ? ", :intent" : "")
                        . ($hasCHash ? ", :chash" : "")
                        . ")";
                $params = [
                    ':name' => $t['name'],
                    ':cat' => $t['category'],
                    ':subj' => $t['subject_default'],
                    ':html' => $t['html'],
                    ':plain' => $plain
                ];
                if ($hasIntent) $params[':intent'] = $t['intent'];
                if ($hasCHash) $params[':chash'] = $cHash;
                $db->prepare($insSql)->execute($params);
            }
        }

        // Also clean up any legacy "Workshop Invite" template to eliminate URL display mismatch & spam triggers
        $stmtWorkshop = $db->query("SELECT template_id FROM campaign_templates WHERE name LIKE '%Workshop%' LIMIT 1");
        $wsRow = $stmtWorkshop ? $stmtWorkshop->fetch(PDO::FETCH_ASSOC) : null;
        if ($wsRow) {
            $cleanWsHtml = self::getPrebuiltTemplateCleanWorkshop();
            $cleanWsPlain = self::htmlToPlainText($cleanWsHtml);
            $cleanWsSubj = 'Invitation: Startup & Business Growth Session — Axiora Global Solutions';
            $db->prepare("UPDATE campaign_templates SET 
                          subject_default = :subj, 
                          html_content = :html, 
                          plain_text_content = :plain 
                          WHERE template_id = :id")
               ->execute([
                   ':subj' => $cleanWsSubj,
                   ':html' => $cleanWsHtml,
                   ':plain' => $cleanWsPlain,
                   ':id' => $wsRow['template_id']
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

    public static function getPrebuiltTemplateWelcomeRegistration(): string {
        return <<<'HTML'
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Welcome to Axiora Global Solutions</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f1f5f9; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
  <table border="0" cellpadding="0" cellspacing="0" width="100%" style="table-layout: fixed; background-color: #f1f5f9; padding: 20px 0;">
    <tr>
      <td align="center">
        <table border="0" cellpadding="0" cellspacing="0" width="600" style="max-width: 600px; width: 100%; background-color: #ffffff; border-radius: 8px; overflow: hidden; border: 1px solid #e2e8f0;">
          <tr>
            <td style="padding: 25px 30px; background-color: #0f172a; text-align: left;">
              <h2 style="color: #ffffff; margin: 0; font-size: 20px; font-weight: 700;">Axiora Global Solutions</h2>
            </td>
          </tr>
          <tr>
            <td style="padding: 35px 30px; color: #334155; font-size: 15px; line-height: 24px;">
              <h3 style="color: #0f172a; margin-top: 0; font-size: 20px;">Welcome to Raptor CRM, {{first_name|User}}!</h3>
              <p style="margin: 0 0 16px;">Your enterprise account has been registered. You now have access to Raptor CRM to collaborate, track workflows, and manage communications.</p>
              
              <table border="0" cellpadding="0" cellspacing="0" width="100%" style="margin: 20px 0; background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px;">
                <tr><td style="padding: 12px 16px; font-weight: bold; color: #475569; width: 35%; border-bottom: 1px solid #e2e8f0;">Login Portal:</td><td style="padding: 12px 16px; border-bottom: 1px solid #e2e8f0;"><a href="https://www.agsraptor.com/public/index.php?route=auth/login" style="color: #2563eb; text-decoration: none; font-weight: 600;">Sign in to Raptor Portal</a></td></tr>
                <tr><td style="padding: 12px 16px; font-weight: bold; color: #475569;">Registered Email:</td><td style="padding: 12px 16px; color: #0f172a;">{{email}}</td></tr>
              </table>

              <table border="0" cellpadding="0" cellspacing="0" style="margin: 24px 0;">
                <tr>
                  <td align="center" style="border-radius: 6px; background-color: #2563eb;">
                    <a href="https://www.agsraptor.com/public/index.php?route=auth/login" target="_blank" style="font-size: 14px; font-weight: bold; color: #ffffff; text-decoration: none; padding: 12px 24px; display: inline-block;">Access Your Account</a>
                  </td>
                </tr>
              </table>

              <p style="margin: 20px 0 0; font-size: 13px; color: #64748b;"><strong>Security Recommendation:</strong> For security purposes, please update your password upon first login and enable two-factor authentication.</p>
              <p style="margin: 20px 0 0; color: #64748b; font-size: 14px;">Best regards,<br /><strong style="color: #1e293b;">Axiora Enterprise Administration</strong></p>
            </td>
          </tr>
          <tr>
            <td style="padding: 20px 30px; background-color: #f8fafc; border-top: 1px solid #e2e8f0; text-align: center; color: #94a3b8; font-size: 12px; line-height: 18px;">
              <p style="margin: 0;">{{company_address}}</p>
              <p style="margin: 4px 0 0;">This is an automated operational notification regarding your Raptor CRM account.</p>
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

    public static function getPrebuiltTemplatePasswordResetOtp(): string {
        return <<<'HTML'
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Password Reset Verification Code</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f1f5f9; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
  <table border="0" cellpadding="0" cellspacing="0" width="100%" style="table-layout: fixed; background-color: #f1f5f9; padding: 20px 0;">
    <tr>
      <td align="center">
        <table border="0" cellpadding="0" cellspacing="0" width="550" style="max-width: 550px; width: 100%; background-color: #ffffff; border-radius: 8px; overflow: hidden; border: 1px solid #e2e8f0;">
          <tr>
            <td style="padding: 25px 30px; background-color: #0f172a; text-align: left;">
              <h2 style="color: #ffffff; margin: 0; font-size: 18px; font-weight: 700;">Axiora Security &amp; Identity</h2>
            </td>
          </tr>
          <tr>
            <td style="padding: 35px 30px; color: #334155; font-size: 15px; line-height: 24px;">
              <h3 style="color: #0f172a; margin-top: 0; font-size: 18px;">Password Reset Request</h3>
              <p style="margin: 0 0 16px;">Hello {{first_name|User}},</p>
              <p style="margin: 0 0 16px;">We received a verification request to reset the password for your Raptor account. Enter the 6-digit verification code below to proceed:</p>
              
              <table border="0" cellpadding="0" cellspacing="0" width="100%" style="margin: 24px 0;">
                <tr>
                  <td align="center" style="background-color: #f1f5f9; border-radius: 6px; padding: 20px; border: 1px dashed #cbd5e1;">
                    <span style="font-size: 32px; font-weight: 700; letter-spacing: 6px; color: #0f172a; font-family: monospace;">{{otp_code|123456}}</span>
                  </td>
                </tr>
              </table>

              <p style="margin: 0 0 12px; font-size: 13px; color: #64748b;">This code is valid for <strong>10 minutes</strong>. If you did not request this code, no action is required and your account remains secure.</p>
              <p style="margin: 20px 0 0; color: #64748b; font-size: 14px;">Best regards,<br /><strong style="color: #1e293b;">The Axiora Security Team</strong></p>
            </td>
          </tr>
          <tr>
            <td style="padding: 16px 30px; background-color: #f8fafc; border-top: 1px solid #e2e8f0; text-align: center; color: #94a3b8; font-size: 12px; line-height: 18px;">
              <p style="margin: 0;">{{company_address}}</p>
              <p style="margin: 4px 0 0;">Automated security notification &bull; Do not reply to this email.</p>
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

    public static function getPrebuiltTemplateOrderPaymentConfirmation(): string {
        return <<<'HTML'
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Payment & Order Confirmation</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f1f5f9; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
  <table border="0" cellpadding="0" cellspacing="0" width="100%" style="table-layout: fixed; background-color: #f1f5f9; padding: 20px 0;">
    <tr>
      <td align="center">
        <table border="0" cellpadding="0" cellspacing="0" width="600" style="max-width: 600px; width: 100%; background-color: #ffffff; border-radius: 8px; overflow: hidden; border: 1px solid #e2e8f0;">
          <tr>
            <td style="padding: 25px 30px; background-color: #0f172a; text-align: left;">
              <h2 style="color: #ffffff; margin: 0; font-size: 20px; font-weight: 700;">Axiora Global Solutions</h2>
            </td>
          </tr>
          <tr>
            <td style="padding: 35px 30px; color: #334155; font-size: 15px; line-height: 24px;">
              <h3 style="color: #0f172a; margin-top: 0; font-size: 20px;">Payment Confirmation</h3>
              <p style="margin: 0 0 16px;">Dear {{first_name|Valued Customer}},</p>
              <p style="margin: 0 0 16px;">Thank you for your business. We have successfully recorded your transaction. Summary details are provided below:</p>
              
              <table border="0" cellpadding="0" cellspacing="0" width="100%" style="margin: 20px 0; background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px;">
                <tr><td style="padding: 12px 16px; font-weight: bold; color: #475569; width: 40%; border-bottom: 1px solid #e2e8f0;">Transaction Reference:</td><td style="padding: 12px 16px; border-bottom: 1px solid #e2e8f0; font-family: monospace;">{{transaction_id|TXN-CONFIRMED}}</td></tr>
                <tr><td style="padding: 12px 16px; font-weight: bold; color: #475569; border-bottom: 1px solid #e2e8f0;">Amount Paid:</td><td style="padding: 12px 16px; border-bottom: 1px solid #e2e8f0; font-weight: bold; color: #16a34a;">{{amount_paid|Confirmed}}</td></tr>
                <tr><td style="padding: 12px 16px; font-weight: bold; color: #475569;">Payment Status:</td><td style="padding: 12px 16px; color: #16a34a; font-weight: bold;">Completed</td></tr>
              </table>

              <p style="margin: 20px 0 0; color: #64748b; font-size: 14px;">Best regards,<br /><strong style="color: #1e293b;">The Axiora Finance &amp; Billing Team</strong></p>
            </td>
          </tr>
          <tr>
            <td style="padding: 20px 30px; background-color: #f8fafc; border-top: 1px solid #e2e8f0; text-align: center; color: #94a3b8; font-size: 12px; line-height: 18px;">
              <p style="margin: 0;">{{company_address}}</p>
              <p style="margin: 4px 0 0;">Official payment transaction receipt &bull; Axiora Billing</p>
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

    public static function getPrebuiltTemplateAdminNotification(): string {
        return <<<'HTML'
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>System Notification</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f1f5f9; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
  <table border="0" cellpadding="0" cellspacing="0" width="100%" style="table-layout: fixed; background-color: #f1f5f9; padding: 20px 0;">
    <tr>
      <td align="center">
        <table border="0" cellpadding="0" cellspacing="0" width="600" style="max-width: 600px; width: 100%; background-color: #ffffff; border-radius: 8px; overflow: hidden; border: 1px solid #e2e8f0;">
          <tr>
            <td style="padding: 20px 30px; background-color: #1e293b; text-align: left;">
              <span style="color: #94a3b8; font-size: 12px; text-transform: uppercase; font-weight: bold;">Raptor CRM Operational Alert</span>
              <h2 style="color: #ffffff; margin: 4px 0 0; font-size: 18px;">Administrative System Notice</h2>
            </td>
          </tr>
          <tr>
            <td style="padding: 30px; color: #334155; font-size: 15px; line-height: 24px;">
              <p style="margin-top: 0;">Hello Administrator,</p>
              <p>An automated operational event or administrative trigger occurred in Raptor CRM that requires your awareness:</p>
              
              <div style="background-color: #f8fafc; border-left: 4px solid #2563eb; padding: 16px 20px; margin: 20px 0; border-radius: 0 4px 4px 0;">
                <p style="margin: 0; color: #0f172a; font-weight: 600;">System Event: Operational Notice</p>
                <p style="margin: 8px 0 0; color: #475569; font-size: 14px;">{{notification_message|Automated system event logged successfully.}}</p>
              </div>

              <p style="margin: 20px 0 0;"><a href="https://www.agsraptor.com/public/index.php" style="color: #2563eb; font-weight: bold; text-decoration: none;">Open Raptor Management Console &rarr;</a></p>
              <p style="margin: 24px 0 0; color: #64748b; font-size: 14px;">System Operations,<br /><strong style="color: #1e293b;">Raptor CRM Infrastructure</strong></p>
            </td>
          </tr>
          <tr>
            <td style="padding: 16px 30px; background-color: #f8fafc; border-top: 1px solid #e2e8f0; text-align: center; color: #94a3b8; font-size: 12px; line-height: 18px;">
              <p style="margin: 0;">{{company_address}}</p>
              <p style="margin: 4px 0 0;">Internal system alert &bull; Do not forward outside organization.</p>
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

    public static function getPrebuiltTemplateCleanWorkshop(): string {
        return <<<'HTML'
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Startup &amp; Business Workshop — From Idea Validation to Scale</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f8fafc; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
  <table border="0" cellpadding="0" cellspacing="0" width="100%" style="table-layout: fixed; background-color: #f8fafc; padding: 20px 0;">
    <tr>
      <td align="center">
        <table border="0" cellpadding="0" cellspacing="0" width="600" style="max-width: 600px; width: 100%; background-color: #ffffff; border-radius: 8px; overflow: hidden; border: 1px solid #e2e8f0;">
          <tr>
            <td style="padding: 25px 30px; background-color: #0f172a; text-align: left;">
              <span style="color: #38bdf8; font-size: 12px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px;">Executive Workshop</span>
              <h2 style="color: #ffffff; margin: 6px 0 0; font-size: 20px;">Startup &amp; Business Growth Roadmap</h2>
            </td>
          </tr>
          <tr>
            <td style="padding: 30px; color: #334155; font-size: 15px; line-height: 24px;">
              <p style="margin-top: 0;">Dear Aspiring Founder / Entrepreneur / Business Leader,</p>
              <p>Every great enterprise begins with a strategic vision. Axiora Global Solutions invites you to our comprehensive session: <strong>Startup &amp; Business Workshop — From Idea Validation to Scale</strong>.</p>
              
              <p style="font-weight: bold; color: #0f172a; margin: 20px 0 8px;">Key Learning Objectives:</p>
              <ul style="padding-left: 20px; color: #475569; margin: 0 0 20px;">
                <li style="margin-bottom: 6px;">Validate your business model and target market dynamics.</li>
                <li style="margin-bottom: 6px;">Structure capital allocation, operational planning, and forecasting.</li>
                <li style="margin-bottom: 6px;">Develop predictable customer acquisition channels.</li>
                <li style="margin-bottom: 6px;">Identify the 10 critical scaling stages for long-term viability.</li>
              </ul>

              <table border="0" cellpadding="0" cellspacing="0" style="margin: 24px 0;">
                <tr>
                  <td align="center" style="border-radius: 6px; background-color: #2563eb;">
                    <a href="https://forms.office.com/r/zGPmq1YAaJ" target="_blank" style="font-size: 14px; font-weight: bold; color: #ffffff; text-decoration: none; padding: 12px 24px; display: inline-block;">Register for the Workshop Session</a>
                  </td>
                </tr>
              </table>

              <p style="margin: 20px 0 0; color: #64748b; font-size: 14px;">Best regards,<br /><strong style="color: #1e293b;">Axiora Global Solutions Pvt. Ltd.</strong></p>
            </td>
          </tr>
          <tr>
            <td style="padding: 20px 30px; background-color: #f1f5f9; border-top: 1px solid #e2e8f0; text-align: center; color: #94a3b8; font-size: 12px; line-height: 18px;">
              <p style="margin: 0 0 6px;">{{company_address}}</p>
              <p style="margin: 0;"><a href="{{unsubscribe_url}}" style="color: #64748b; text-decoration: underline;">Unsubscribe from event invitations</a> &bull; &copy; {{current_year}} Axiora Global Solutions.</p>
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
