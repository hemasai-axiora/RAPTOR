<?php
/**
 * Migration 0051: Marketing Forms, Inbound Webhooks & Web Visitor Tracking Schema
 * -------------------------------------------------------------------------------
 * Establishes tables for Sprint 2 (Inbound Lead Ingestion & Tracking Engine):
 * 1. `marketing_forms`: Dynamic embeddable forms with field configurations, assignment rules, and spam controls.
 * 2. `form_submissions`: Raw submission log linking submitted payload, client IP, UTMs, and resolved contact.
 * 3. `inbound_webhook_endpoints`: Registered external API webhook keys (WordPress, Webflow, Meta Lead Ads).
 * 4. `web_sessions`: Anonymous and stitched website visitor sessions with UTM attribution and device data.
 */

$db = Database::getInstance()->getConnection();
$driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);

echo "Starting Migration 0051: Marketing Forms & Inbound Lead Capture Schema...\n";

if ($driver === 'sqlite') {
    // 1. Marketing Forms Table (SQLite)
    $db->exec("
    CREATE TABLE IF NOT EXISTS marketing_forms (
        form_id INTEGER PRIMARY KEY AUTOINCREMENT,
        form_uuid TEXT NOT NULL UNIQUE,
        name TEXT NOT NULL,
        description TEXT NULL,
        form_type TEXT NOT NULL DEFAULT 'embedded', -- embedded, popup, standalone
        fields_json TEXT NOT NULL, -- JSON array of fields (label, type, name, required)
        submit_button_text TEXT NOT NULL DEFAULT 'Submit',
        success_message TEXT NOT NULL DEFAULT 'Thank you! We have received your submission.',
        redirect_url TEXT NULL,
        assigned_user_id INTEGER NULL,
        default_lead_source TEXT NOT NULL DEFAULT 'web_form',
        default_lead_quality TEXT NOT NULL DEFAULT 'warm',
        enable_honeypot INTEGER NOT NULL DEFAULT 1,
        enable_captcha INTEGER NOT NULL DEFAULT 0,
        captcha_secret_key TEXT NULL,
        notification_emails TEXT NULL, -- comma-separated
        is_active INTEGER NOT NULL DEFAULT 1,
        submissions_count INTEGER NOT NULL DEFAULT 0,
        views_count INTEGER NOT NULL DEFAULT 0,
        created_by INTEGER NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );
    CREATE INDEX IF NOT EXISTS idx_forms_uuid ON marketing_forms (form_uuid);
    CREATE INDEX IF NOT EXISTS idx_forms_active ON marketing_forms (is_active);
    ");

    // 2. Form Submissions Table (SQLite)
    $db->exec("
    CREATE TABLE IF NOT EXISTS form_submissions (
        submission_id INTEGER PRIMARY KEY AUTOINCREMENT,
        form_id INTEGER NULL,
        contact_id INTEGER NULL,
        lead_id INTEGER NULL,
        visitor_cookie TEXT NULL,
        ip_address TEXT NULL,
        user_agent TEXT NULL,
        page_url TEXT NULL,
        referrer TEXT NULL,
        utm_source TEXT NULL,
        utm_medium TEXT NULL,
        utm_campaign TEXT NULL,
        utm_content TEXT NULL,
        utm_term TEXT NULL,
        submitted_data_json TEXT NOT NULL,
        is_spam INTEGER NOT NULL DEFAULT 0,
        spam_reason TEXT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (form_id) REFERENCES marketing_forms (form_id) ON DELETE SET NULL,
        FOREIGN KEY (contact_id) REFERENCES contacts (contact_id) ON DELETE SET NULL,
        FOREIGN KEY (lead_id) REFERENCES leads (lead_id) ON DELETE SET NULL
    );
    CREATE INDEX IF NOT EXISTS idx_sub_form ON form_submissions (form_id);
    CREATE INDEX IF NOT EXISTS idx_sub_contact ON form_submissions (contact_id);
    CREATE INDEX IF NOT EXISTS idx_sub_cookie ON form_submissions (visitor_cookie);
    ");

    // 3. Inbound Webhook Endpoints Table (SQLite)
    $db->exec("
    CREATE TABLE IF NOT EXISTS inbound_webhook_endpoints (
        endpoint_id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        provider TEXT NOT NULL, -- wordpress, webflow, meta_lead_ads, zapier, custom
        endpoint_key TEXT NOT NULL UNIQUE,
        secret_hash TEXT NOT NULL, -- HMAC shared secret
        assigned_user_id INTEGER NULL,
        default_lead_source TEXT NOT NULL DEFAULT 'webhook',
        field_mapping_json TEXT NULL, -- JSON mapping external fields to Raptor contact fields
        is_active INTEGER NOT NULL DEFAULT 1,
        total_received INTEGER NOT NULL DEFAULT 0,
        last_received_at DATETIME NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );
    CREATE INDEX IF NOT EXISTS idx_webhook_key ON inbound_webhook_endpoints (endpoint_key);
    ");

    // 4. Web Sessions Table (SQLite)
    $db->exec("
    CREATE TABLE IF NOT EXISTS web_sessions (
        session_id INTEGER PRIMARY KEY AUTOINCREMENT,
        visitor_cookie TEXT NOT NULL,
        contact_id INTEGER NULL,
        ip_address TEXT NULL,
        user_agent TEXT NULL,
        first_page_url TEXT NULL,
        referrer TEXT NULL,
        utm_source TEXT NULL,
        utm_medium TEXT NULL,
        utm_campaign TEXT NULL,
        pageviews_count INTEGER NOT NULL DEFAULT 1,
        first_seen_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        last_seen_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );
    CREATE INDEX IF NOT EXISTS idx_session_cookie ON web_sessions (visitor_cookie);
    CREATE INDEX IF NOT EXISTS idx_session_contact ON web_sessions (contact_id);
    ");

} else {
    // 1. Marketing Forms Table (MySQL)
    $db->exec("
    CREATE TABLE IF NOT EXISTS marketing_forms (
        form_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        form_uuid CHAR(36) NOT NULL UNIQUE,
        name VARCHAR(150) NOT NULL,
        description TEXT NULL,
        form_type ENUM('embedded', 'popup', 'standalone') NOT NULL DEFAULT 'embedded',
        fields_json LONGTEXT NOT NULL,
        submit_button_text VARCHAR(100) NOT NULL DEFAULT 'Submit',
        success_message TEXT NOT NULL,
        redirect_url VARCHAR(2048) NULL,
        assigned_user_id INT NULL,
        default_lead_source VARCHAR(100) NOT NULL DEFAULT 'web_form',
        default_lead_quality ENUM('hot', 'warm', 'cold') NOT NULL DEFAULT 'warm',
        enable_honeypot TINYINT(1) NOT NULL DEFAULT 1,
        enable_captcha TINYINT(1) NOT NULL DEFAULT 0,
        captcha_secret_key VARCHAR(255) NULL,
        notification_emails TEXT NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        submissions_count INT UNSIGNED NOT NULL DEFAULT 0,
        views_count INT UNSIGNED NOT NULL DEFAULT 0,
        created_by INT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_forms_uuid (form_uuid),
        INDEX idx_forms_active (is_active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // 2. Form Submissions Table (MySQL)
    $db->exec("
    CREATE TABLE IF NOT EXISTS form_submissions (
        submission_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        form_id BIGINT UNSIGNED NULL,
        contact_id BIGINT UNSIGNED NULL,
        lead_id INT NULL,
        visitor_cookie VARCHAR(64) NULL,
        ip_address VARCHAR(45) NULL,
        user_agent VARCHAR(255) NULL,
        page_url VARCHAR(2048) NULL,
        referrer VARCHAR(2048) NULL,
        utm_source VARCHAR(100) NULL,
        utm_medium VARCHAR(100) NULL,
        utm_campaign VARCHAR(100) NULL,
        utm_content VARCHAR(100) NULL,
        utm_term VARCHAR(100) NULL,
        submitted_data_json LONGTEXT NOT NULL,
        is_spam TINYINT(1) NOT NULL DEFAULT 0,
        spam_reason VARCHAR(150) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_sub_form (form_id),
        INDEX idx_sub_contact (contact_id),
        INDEX idx_sub_cookie (visitor_cookie),
        CONSTRAINT fk_sub_form FOREIGN KEY (form_id) REFERENCES marketing_forms (form_id) ON DELETE SET NULL,
        CONSTRAINT fk_sub_contact FOREIGN KEY (contact_id) REFERENCES contacts (contact_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // 3. Inbound Webhook Endpoints Table (MySQL)
    $db->exec("
    CREATE TABLE IF NOT EXISTS inbound_webhook_endpoints (
        endpoint_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(150) NOT NULL,
        provider ENUM('wordpress', 'webflow', 'meta_lead_ads', 'zapier', 'custom') NOT NULL DEFAULT 'custom',
        endpoint_key VARCHAR(64) NOT NULL UNIQUE,
        secret_hash VARCHAR(128) NOT NULL,
        assigned_user_id INT NULL,
        default_lead_source VARCHAR(100) NOT NULL DEFAULT 'webhook',
        field_mapping_json LONGTEXT NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        total_received INT UNSIGNED NOT NULL DEFAULT 0,
        last_received_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_webhook_key (endpoint_key)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // 4. Web Sessions Table (MySQL)
    $db->exec("
    CREATE TABLE IF NOT EXISTS web_sessions (
        session_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        visitor_cookie VARCHAR(64) NOT NULL,
        contact_id BIGINT UNSIGNED NULL,
        ip_address VARCHAR(45) NULL,
        user_agent VARCHAR(255) NULL,
        first_page_url VARCHAR(2048) NULL,
        referrer VARCHAR(2048) NULL,
        utm_source VARCHAR(100) NULL,
        utm_medium VARCHAR(100) NULL,
        utm_campaign VARCHAR(100) NULL,
        pageviews_count INT UNSIGNED NOT NULL DEFAULT 1,
        first_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        last_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_session_cookie (visitor_cookie),
        INDEX idx_session_contact (contact_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
}

echo "  + Marketing Forms & Inbound Lead Capture tables ensured successfully.\n";
