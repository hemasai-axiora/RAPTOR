<?php
/**
 * Migration 0049: Email Campaigns & Mass Mailing Architecture Schema
 * --------------------------------------------------------------------------
 * Creates comprehensive, production-grade schema for:
 * 1. Contacts, lists, list memberships, tags, contact tags, dynamic segments
 * 2. Templates (HTML + mandatory plain text alternative)
 * 3. Campaigns (with A/B subject testing, pre-send checklist, recipient metrics)
 * 4. Campaign recipients & DB-backed queue with row locking for CLI workers
 * 5. Event logs (deliveries, opens, clicks, bounces, complaints, unsubs)
 * 6. Global suppression list (never emailed once suppressed)
 * 7. Sending domains (SPF, DKIM, DMARC, MX verification status)
 * 8. Domain warm-up schedule with daily ramp-up caps
 * 9. Audit trail for compliance & governance (GDPR / CAN-SPAM / DPDP)
 * 10. Role permissions for 'email_campaigns' module
 */

$db = Database::getInstance()->getConnection();

echo "Starting Migration 0049: Email Campaigns & Mass Mailing Schema...\n";

// 1. Contacts Table
$db->exec("
CREATE TABLE IF NOT EXISTS campaign_contacts (
    contact_id BIGINT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    first_name VARCHAR(100) NULL,
    last_name VARCHAR(100) NULL,
    company_name VARCHAR(150) NULL,
    custom_fields JSON NULL,
    status ENUM('subscribed', 'unsubscribed', 'bounced', 'complained', 'suppressed') NOT NULL DEFAULT 'subscribed',
    source VARCHAR(50) NOT NULL DEFAULT 'manual',
    consent_type VARCHAR(50) NOT NULL DEFAULT 'explicit_optin',
    consent_timestamp DATETIME NULL,
    consent_ip VARCHAR(45) NULL,
    consent_notes TEXT NULL,
    is_role_account BOOLEAN NOT NULL DEFAULT FALSE,
    is_disposable BOOLEAN NOT NULL DEFAULT FALSE,
    last_engagement_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_contact_email (email),
    INDEX idx_contact_status (status),
    INDEX idx_contact_last_eng (last_engagement_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");
echo "  + campaign_contacts table ensured\n";

// 2. Lists Table
$db->exec("
CREATE TABLE IF NOT EXISTS campaign_lists (
    list_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    description TEXT NULL,
    is_internal BOOLEAN NOT NULL DEFAULT FALSE,
    optin_mode ENUM('single', 'double') NOT NULL DEFAULT 'single',
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");
echo "  + campaign_lists table ensured\n";

// 3. List Memberships (Many-to-Many)
$db->exec("
CREATE TABLE IF NOT EXISTS campaign_list_contacts (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    list_id INT NOT NULL,
    contact_id BIGINT NOT NULL,
    status ENUM('active', 'unsubscribed') NOT NULL DEFAULT 'active',
    unsubscribed_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_list_contact (list_id, contact_id),
    INDEX idx_lc_status (status),
    FOREIGN KEY (list_id) REFERENCES campaign_lists(list_id) ON DELETE CASCADE,
    FOREIGN KEY (contact_id) REFERENCES campaign_contacts(contact_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");
echo "  + campaign_list_contacts table ensured\n";

// 4. Tags Table
$db->exec("
CREATE TABLE IF NOT EXISTS campaign_tags (
    tag_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    color VARCHAR(20) NOT NULL DEFAULT '#3b82f6',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");
echo "  + campaign_tags table ensured\n";

// 5. Contact Tags
$db->exec("
CREATE TABLE IF NOT EXISTS campaign_contact_tags (
    contact_id BIGINT NOT NULL,
    tag_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (contact_id, tag_id),
    FOREIGN KEY (contact_id) REFERENCES campaign_contacts(contact_id) ON DELETE CASCADE,
    FOREIGN KEY (tag_id) REFERENCES campaign_tags(tag_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");
echo "  + campaign_contact_tags table ensured\n";

// 6. Dynamic Segments Table
$db->exec("
CREATE TABLE IF NOT EXISTS campaign_segments (
    segment_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    description TEXT NULL,
    match_type ENUM('all', 'any') NOT NULL DEFAULT 'all',
    rules_json JSON NOT NULL,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");
echo "  + campaign_segments table ensured\n";

// 7. Email Templates Table
$db->exec("
CREATE TABLE IF NOT EXISTS campaign_templates (
    template_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    category VARCHAR(50) NOT NULL DEFAULT 'general',
    subject_default VARCHAR(255) NULL,
    html_content MEDIUMTEXT NOT NULL,
    plain_text_content MEDIUMTEXT NOT NULL,
    is_prebuilt BOOLEAN NOT NULL DEFAULT FALSE,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");
echo "  + campaign_templates table ensured\n";

// 8. Campaigns Table
$db->exec("
CREATE TABLE IF NOT EXISTS campaigns_broadcast (
    campaign_id INT AUTO_INCREMENT PRIMARY KEY,
    campaign_code VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(150) NOT NULL,
    subject_a VARCHAR(255) NOT NULL,
    subject_b VARCHAR(255) NULL,
    ab_split_percent INT NOT NULL DEFAULT 0,
    ab_winner_metric ENUM('opens', 'clicks') NOT NULL DEFAULT 'opens',
    ab_winner_subject VARCHAR(255) NULL,
    preheader VARCHAR(255) NULL,
    from_name VARCHAR(100) NOT NULL,
    from_email VARCHAR(150) NOT NULL,
    reply_to VARCHAR(150) NULL,
    list_id INT NULL,
    segment_id INT NULL,
    template_id INT NULL,
    html_content MEDIUMTEXT NOT NULL,
    plain_text_content MEDIUMTEXT NOT NULL,
    status ENUM('draft', 'scheduled', 'queued', 'sending', 'paused', 'completed', 'cancelled') NOT NULL DEFAULT 'draft',
    scheduled_at DATETIME NULL,
    started_at DATETIME NULL,
    completed_at DATETIME NULL,
    paused_reason VARCHAR(255) NULL,
    requires_approval BOOLEAN NOT NULL DEFAULT FALSE,
    approved_by INT NULL,
    approved_at DATETIME NULL,
    pre_send_checklist_passed BOOLEAN NOT NULL DEFAULT FALSE,
    pre_send_checklist_json JSON NULL,
    total_recipients INT NOT NULL DEFAULT 0,
    sent_count INT NOT NULL DEFAULT 0,
    delivered_count INT NOT NULL DEFAULT 0,
    bounced_count INT NOT NULL DEFAULT 0,
    complained_count INT NOT NULL DEFAULT 0,
    opened_count INT NOT NULL DEFAULT 0,
    clicked_count INT NOT NULL DEFAULT 0,
    unsubscribed_count INT NOT NULL DEFAULT 0,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_camp_status (status),
    INDEX idx_camp_sched (scheduled_at),
    FOREIGN KEY (list_id) REFERENCES campaign_lists(list_id) ON DELETE SET NULL,
    FOREIGN KEY (segment_id) REFERENCES campaign_segments(segment_id) ON DELETE SET NULL,
    FOREIGN KEY (template_id) REFERENCES campaign_templates(template_id) ON DELETE SET NULL,
    FOREIGN KEY (approved_by) REFERENCES users(user_id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");
echo "  + campaigns_broadcast table ensured\n";

// 9. Campaign Recipients Table
$db->exec("
CREATE TABLE IF NOT EXISTS campaign_recipients (
    recipient_id BIGINT AUTO_INCREMENT PRIMARY KEY,
    campaign_id INT NOT NULL,
    contact_id BIGINT NOT NULL,
    email VARCHAR(255) NOT NULL,
    subject_variant ENUM('A', 'B') NOT NULL DEFAULT 'A',
    status ENUM('queued', 'sent', 'delivered', 'bounced', 'complained', 'failed') NOT NULL DEFAULT 'queued',
    sent_at DATETIME NULL,
    delivered_at DATETIME NULL,
    opened_at DATETIME NULL,
    open_count INT NOT NULL DEFAULT 0,
    clicked_at DATETIME NULL,
    click_count INT NOT NULL DEFAULT 0,
    bounce_type ENUM('none', 'soft', 'hard') NOT NULL DEFAULT 'none',
    bounce_reason VARCHAR(255) NULL,
    unsubscribe_token VARCHAR(64) NOT NULL UNIQUE,
    provider_message_id VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_camp_recipient (campaign_id, contact_id),
    INDEX idx_recip_email (email),
    INDEX idx_recip_status (status),
    INDEX idx_recip_msg_id (provider_message_id),
    INDEX idx_recip_unsub (unsubscribe_token),
    FOREIGN KEY (campaign_id) REFERENCES campaigns_broadcast(campaign_id) ON DELETE CASCADE,
    FOREIGN KEY (contact_id) REFERENCES campaign_contacts(contact_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");
echo "  + campaign_recipients table ensured\n";

// 10. Queue Table (DB-backed FIFO with safe row locking)
$db->exec("
CREATE TABLE IF NOT EXISTS campaign_email_queue (
    queue_id BIGINT AUTO_INCREMENT PRIMARY KEY,
    campaign_id INT NOT NULL,
    recipient_id BIGINT NOT NULL,
    status ENUM('pending', 'processing', 'sent', 'failed', 'cancelled') NOT NULL DEFAULT 'pending',
    priority TINYINT NOT NULL DEFAULT 10,
    attempts TINYINT NOT NULL DEFAULT 0,
    max_attempts TINYINT NOT NULL DEFAULT 3,
    scheduled_at DATETIME NOT NULL,
    locked_at DATETIME NULL,
    locked_by VARCHAR(100) NULL,
    last_error TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_q_status_sched (status, scheduled_at),
    INDEX idx_q_locked (locked_at),
    FOREIGN KEY (campaign_id) REFERENCES campaigns_broadcast(campaign_id) ON DELETE CASCADE,
    FOREIGN KEY (recipient_id) REFERENCES campaign_recipients(recipient_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");
echo "  + campaign_email_queue table ensured\n";

// 11. Raw Event Log (Audit & Webhooks)
$db->exec("
CREATE TABLE IF NOT EXISTS campaign_email_events (
    event_id BIGINT AUTO_INCREMENT PRIMARY KEY,
    campaign_id INT NULL,
    recipient_id BIGINT NULL,
    contact_id BIGINT NULL,
    event_type ENUM('sent', 'delivered', 'open', 'click', 'soft_bounce', 'hard_bounce', 'complaint', 'unsubscribe') NOT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    target_url TEXT NULL,
    provider_message_id VARCHAR(255) NULL,
    payload_json JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ev_camp (campaign_id, event_type),
    INDEX idx_ev_contact (contact_id),
    INDEX idx_ev_created (created_at),
    FOREIGN KEY (campaign_id) REFERENCES campaigns_broadcast(campaign_id) ON DELETE SET NULL,
    FOREIGN KEY (recipient_id) REFERENCES campaign_recipients(recipient_id) ON DELETE SET NULL,
    FOREIGN KEY (contact_id) REFERENCES campaign_contacts(contact_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");
echo "  + campaign_email_events table ensured\n";

// 12. Global Suppression List
$db->exec("
CREATE TABLE IF NOT EXISTS campaign_suppressions (
    suppression_id BIGINT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    reason ENUM('unsubscribe', 'hard_bounce', 'soft_bounce_repeat', 'spam_complaint', 'admin_manual') NOT NULL,
    notes VARCHAR(255) NULL,
    source_campaign_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_supp_email (email),
    FOREIGN KEY (source_campaign_id) REFERENCES campaigns_broadcast(campaign_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");
echo "  + campaign_suppressions table ensured\n";

// 13. Sending Domains & DNS Authentication Verification
$db->exec("
CREATE TABLE IF NOT EXISTS campaign_domains (
    domain_id INT AUTO_INCREMENT PRIMARY KEY,
    domain_name VARCHAR(150) NOT NULL UNIQUE,
    is_default BOOLEAN NOT NULL DEFAULT FALSE,
    spf_status ENUM('unverified', 'valid', 'invalid') NOT NULL DEFAULT 'unverified',
    spf_record_expected VARCHAR(255) NULL,
    dkim_status ENUM('unverified', 'valid', 'invalid') NOT NULL DEFAULT 'unverified',
    dkim_selector VARCHAR(50) NOT NULL DEFAULT 'raptor',
    dkim_public_key TEXT NULL,
    dkim_private_key_encrypted TEXT NULL,
    dmarc_status ENUM('unverified', 'valid', 'invalid') NOT NULL DEFAULT 'unverified',
    dmarc_policy ENUM('none', 'quarantine', 'reject') NOT NULL DEFAULT 'none',
    mx_status ENUM('unverified', 'valid', 'invalid') NOT NULL DEFAULT 'unverified',
    last_checked_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");
echo "  + campaign_domains table ensured\n";

// 14. Domain Warmup Schedules & Daily Caps
$db->exec("
CREATE TABLE IF NOT EXISTS campaign_warmup_schedules (
    warmup_id INT AUTO_INCREMENT PRIMARY KEY,
    domain_id INT NOT NULL,
    current_stage INT NOT NULL DEFAULT 1,
    daily_cap INT NOT NULL DEFAULT 100,
    sent_today INT NOT NULL DEFAULT 0,
    date_stamp DATE NOT NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_domain_date (domain_id, date_stamp),
    FOREIGN KEY (domain_id) REFERENCES campaign_domains(domain_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");
echo "  + campaign_warmup_schedules table ensured\n";

// 15. Compliance Audit Logs (CAN-SPAM / GDPR / DPDP)
$db->exec("
CREATE TABLE IF NOT EXISTS campaign_audit_logs (
    audit_id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50) NOT NULL,
    entity_id BIGINT NOT NULL,
    details_json JSON NULL,
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_audit_action (action),
    INDEX idx_audit_entity (entity_type, entity_id),
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");
echo "  + campaign_audit_logs table ensured\n";

// 16. Grant Module Permissions for 'email_campaigns'
try {
    $permCols = ['can_view', 'can_create', 'can_edit', 'can_delete'];
    $roleIds = $db->query("SELECT role_id, role_name FROM roles")->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // Admin, HR, Manager roles get access
    $allowedRoles = ['admin' => [1,1,1,1], 'manager' => [1,1,1,0], 'hr' => [1,1,1,0]];
    foreach ($allowedRoles as $roleName => $perms) {
        if (isset($roleIds[$roleName])) {
            $rid = $roleIds[$roleName];
            $stmt = $db->prepare("INSERT INTO role_permissions (role_id, module, can_view, can_create, can_edit, can_delete)
                                  VALUES (:rid, 'email_campaigns', :v, :c, :e, :d)
                                  ON DUPLICATE KEY UPDATE can_view=VALUES(can_view), can_create=VALUES(can_create), can_edit=VALUES(can_edit), can_delete=VALUES(can_delete)");
            $stmt->execute([
                ':rid' => $rid,
                ':v' => $perms[0],
                ':c' => $perms[1],
                ':e' => $perms[2],
                ':d' => $perms[3]
            ]);
        }
    }
    echo "  + role_permissions for email_campaigns granted to admin/manager/hr\n";
} catch (Exception $e) {}

echo "Migration 0049 completed successfully.\n";

