<?php
/**
 * Migration 0052: Meta WhatsApp Business Cloud API Architecture Schema
 * --------------------------------------------------------------------------
 * Establishes the enterprise-grade schema for official WhatsApp messaging:
 * 1. `whatsapp_templates`: Meta approved message templates (Marketing, Utility, Auth)
 * 2. `whatsapp_conversations`: 24-hour customer service session tracker
 * 3. `whatsapp_messages`: Omnichannel message ledger (inbound/outbound with status)
 * 4. `whatsapp_broadcasts`: Bulk template broadcast campaigns
 * 5. `whatsapp_broadcast_recipients`: Recipient queue & delivery status
 */

$db = Database::getInstance()->getConnection();
$driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);

echo "Starting Migration 0052: Meta WhatsApp Business Cloud API Schema...\n";

if ($driver === 'sqlite') {
    // 1. Templates Table (SQLite)
    $db->exec("
    CREATE TABLE IF NOT EXISTS whatsapp_templates (
        template_id INTEGER PRIMARY KEY AUTOINCREMENT,
        meta_template_id TEXT NULL,
        name TEXT NOT NULL UNIQUE,
        category TEXT NOT NULL DEFAULT 'MARKETING', -- MARKETING, UTILITY, AUTHENTICATION
        language TEXT NOT NULL DEFAULT 'en_US',
        status TEXT NOT NULL DEFAULT 'PENDING', -- APPROVED, PENDING, REJECTED, PAUSED
        header_type TEXT NOT NULL DEFAULT 'NONE', -- NONE, TEXT, IMAGE, VIDEO, DOCUMENT
        header_content TEXT NULL,
        body_text TEXT NOT NULL,
        footer_text TEXT NULL,
        buttons_json TEXT NULL, -- Quick replies / Call-to-actions
        variables_sample_json TEXT NULL,
        rejection_reason TEXT NULL,
        created_by INTEGER NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );
    CREATE INDEX IF NOT EXISTS idx_wa_tmpl_name ON whatsapp_templates (name);
    CREATE INDEX IF NOT EXISTS idx_wa_tmpl_status ON whatsapp_templates (status);
    ");

    // 2. Conversations Table (SQLite) - 24-hour session window tracker
    $db->exec("
    CREATE TABLE IF NOT EXISTS whatsapp_conversations (
        conversation_id INTEGER PRIMARY KEY AUTOINCREMENT,
        contact_id INTEGER NULL,
        phone_number TEXT NOT NULL UNIQUE,
        assigned_user_id INTEGER NULL,
        status TEXT NOT NULL DEFAULT 'open', -- open, closed, pending
        session_type TEXT NOT NULL DEFAULT 'user_initiated', -- user_initiated, business_initiated
        session_category TEXT NOT NULL DEFAULT 'service', -- service, marketing, utility
        session_expires_at DATETIME NULL, -- Active 24-hour free-form messaging window
        last_inbound_at DATETIME NULL,
        last_outbound_at DATETIME NULL,
        unread_count INTEGER NOT NULL DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (contact_id) REFERENCES contacts (contact_id) ON DELETE SET NULL
    );
    CREATE INDEX IF NOT EXISTS idx_wa_conv_phone ON whatsapp_conversations (phone_number);
    CREATE INDEX IF NOT EXISTS idx_wa_conv_expires ON whatsapp_conversations (session_expires_at);
    ");

    // 3. Messages Table (SQLite)
    $db->exec("
    CREATE TABLE IF NOT EXISTS whatsapp_messages (
        message_id INTEGER PRIMARY KEY AUTOINCREMENT,
        conversation_id INTEGER NOT NULL,
        contact_id INTEGER NULL,
        direction TEXT NOT NULL DEFAULT 'outbound', -- inbound, outbound
        message_type TEXT NOT NULL DEFAULT 'text', -- text, template, image, document, audio, video, interactive
        meta_message_id TEXT NULL UNIQUE, -- wamid.HBg...
        template_id INTEGER NULL,
        body TEXT NULL,
        media_url TEXT NULL,
        payload_json TEXT NULL,
        status TEXT NOT NULL DEFAULT 'pending', -- pending, sent, delivered, read, failed
        error_code TEXT NULL,
        error_message TEXT NULL,
        cost_estimated REAL DEFAULT 0.0,
        sent_by_user_id INTEGER NULL,
        sent_at DATETIME NULL,
        delivered_at DATETIME NULL,
        read_at DATETIME NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (conversation_id) REFERENCES whatsapp_conversations (conversation_id) ON DELETE CASCADE,
        FOREIGN KEY (contact_id) REFERENCES contacts (contact_id) ON DELETE SET NULL,
        FOREIGN KEY (template_id) REFERENCES whatsapp_templates (template_id) ON DELETE SET NULL
    );
    CREATE INDEX IF NOT EXISTS idx_wa_msg_conv ON whatsapp_messages (conversation_id);
    CREATE INDEX IF NOT EXISTS idx_wa_msg_meta ON whatsapp_messages (meta_message_id);
    CREATE INDEX IF NOT EXISTS idx_wa_msg_status ON whatsapp_messages (status);
    ");

    // 4. Broadcasts Table (SQLite)
    $db->exec("
    CREATE TABLE IF NOT EXISTS whatsapp_broadcasts (
        broadcast_id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        template_id INTEGER NOT NULL,
        list_id INTEGER NULL,
        status TEXT NOT NULL DEFAULT 'draft', -- draft, scheduled, processing, completed, paused, failed
        total_recipients INTEGER NOT NULL DEFAULT 0,
        sent_count INTEGER NOT NULL DEFAULT 0,
        delivered_count INTEGER NOT NULL DEFAULT 0,
        read_count INTEGER NOT NULL DEFAULT 0,
        failed_count INTEGER NOT NULL DEFAULT 0,
        scheduled_at DATETIME NULL,
        started_at DATETIME NULL,
        completed_at DATETIME NULL,
        created_by INTEGER NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (template_id) REFERENCES whatsapp_templates (template_id) ON DELETE RESTRICT
    );
    CREATE INDEX IF NOT EXISTS idx_wa_bcast_status ON whatsapp_broadcasts (status);
    ");

    // 5. Broadcast Recipients Table (SQLite)
    $db->exec("
    CREATE TABLE IF NOT EXISTS whatsapp_broadcast_recipients (
        recipient_id INTEGER PRIMARY KEY AUTOINCREMENT,
        broadcast_id INTEGER NOT NULL,
        contact_id INTEGER NOT NULL,
        phone_number TEXT NOT NULL,
        parameters_json TEXT NULL, -- Variables mapping: {{1}}, {{2}}
        status TEXT NOT NULL DEFAULT 'queued', -- queued, processing, sent, delivered, read, failed
        meta_message_id TEXT NULL,
        error_code TEXT NULL,
        error_message TEXT NULL,
        sent_at DATETIME NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (broadcast_id) REFERENCES whatsapp_broadcasts (broadcast_id) ON DELETE CASCADE,
        FOREIGN KEY (contact_id) REFERENCES contacts (contact_id) ON DELETE CASCADE
    );
    CREATE INDEX IF NOT EXISTS idx_wa_rcp_bcast ON whatsapp_broadcast_recipients (broadcast_id);
    CREATE INDEX IF NOT EXISTS idx_wa_rcp_status ON whatsapp_broadcast_recipients (status);
    ");

} else {
    // 1. Templates Table (MySQL)
    $db->exec("
    CREATE TABLE IF NOT EXISTS whatsapp_templates (
        template_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        meta_template_id VARCHAR(100) NULL,
        name VARCHAR(120) NOT NULL UNIQUE,
        category ENUM('MARKETING', 'UTILITY', 'AUTHENTICATION') NOT NULL DEFAULT 'MARKETING',
        language VARCHAR(10) NOT NULL DEFAULT 'en_US',
        status ENUM('APPROVED', 'PENDING', 'REJECTED', 'PAUSED') NOT NULL DEFAULT 'PENDING',
        header_type ENUM('NONE', 'TEXT', 'IMAGE', 'VIDEO', 'DOCUMENT') NOT NULL DEFAULT 'NONE',
        header_content VARCHAR(1024) NULL,
        body_text TEXT NOT NULL,
        footer_text VARCHAR(120) NULL,
        buttons_json LONGTEXT NULL,
        variables_sample_json LONGTEXT NULL,
        rejection_reason VARCHAR(255) NULL,
        created_by INT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_wa_tmpl_name (name),
        INDEX idx_wa_tmpl_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // 2. Conversations Table (MySQL) - 24-hour session window tracker
    $db->exec("
    CREATE TABLE IF NOT EXISTS whatsapp_conversations (
        conversation_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        contact_id BIGINT UNSIGNED NULL,
        phone_number VARCHAR(50) NOT NULL UNIQUE,
        assigned_user_id INT NULL,
        status ENUM('open', 'closed', 'pending') NOT NULL DEFAULT 'open',
        session_type ENUM('user_initiated', 'business_initiated') NOT NULL DEFAULT 'user_initiated',
        session_category ENUM('service', 'marketing', 'utility') NOT NULL DEFAULT 'service',
        session_expires_at DATETIME NULL,
        last_inbound_at DATETIME NULL,
        last_outbound_at DATETIME NULL,
        unread_count INT UNSIGNED NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_wa_conv_phone (phone_number),
        INDEX idx_wa_conv_expires (session_expires_at),
        INDEX idx_wa_conv_contact (contact_id),
        CONSTRAINT fk_wa_conv_contact FOREIGN KEY (contact_id) REFERENCES contacts (contact_id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // 3. Messages Table (MySQL)
    $db->exec("
    CREATE TABLE IF NOT EXISTS whatsapp_messages (
        message_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        conversation_id BIGINT UNSIGNED NOT NULL,
        contact_id BIGINT UNSIGNED NULL,
        direction ENUM('inbound', 'outbound') NOT NULL DEFAULT 'outbound',
        message_type ENUM('text', 'template', 'image', 'document', 'audio', 'video', 'interactive') NOT NULL DEFAULT 'text',
        meta_message_id VARCHAR(120) NULL UNIQUE,
        template_id INT UNSIGNED NULL,
        body TEXT NULL,
        media_url VARCHAR(2048) NULL,
        payload_json LONGTEXT NULL,
        status ENUM('pending', 'sent', 'delivered', 'read', 'failed') NOT NULL DEFAULT 'pending',
        error_code VARCHAR(50) NULL,
        error_message VARCHAR(255) NULL,
        cost_estimated DECIMAL(8,4) NOT NULL DEFAULT 0.0000,
        sent_by_user_id INT NULL,
        sent_at DATETIME NULL,
        delivered_at DATETIME NULL,
        read_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_wa_msg_conv (conversation_id),
        INDEX idx_wa_msg_meta (meta_message_id),
        INDEX idx_wa_msg_status (status),
        CONSTRAINT fk_wa_msg_conv FOREIGN KEY (conversation_id) REFERENCES whatsapp_conversations (conversation_id) ON DELETE CASCADE,
        CONSTRAINT fk_wa_msg_contact FOREIGN KEY (contact_id) REFERENCES contacts (contact_id) ON DELETE SET NULL,
        CONSTRAINT fk_wa_msg_tmpl FOREIGN KEY (template_id) REFERENCES whatsapp_templates (template_id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // 4. Broadcasts Table (MySQL)
    $db->exec("
    CREATE TABLE IF NOT EXISTS whatsapp_broadcasts (
        broadcast_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(150) NOT NULL,
        template_id INT UNSIGNED NOT NULL,
        list_id INT NULL,
        status ENUM('draft', 'scheduled', 'processing', 'completed', 'paused', 'failed') NOT NULL DEFAULT 'draft',
        total_recipients INT UNSIGNED NOT NULL DEFAULT 0,
        sent_count INT UNSIGNED NOT NULL DEFAULT 0,
        delivered_count INT UNSIGNED NOT NULL DEFAULT 0,
        read_count INT UNSIGNED NOT NULL DEFAULT 0,
        failed_count INT UNSIGNED NOT NULL DEFAULT 0,
        scheduled_at DATETIME NULL,
        started_at DATETIME NULL,
        completed_at DATETIME NULL,
        created_by INT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_wa_bcast_status (status),
        CONSTRAINT fk_wa_bcast_tmpl FOREIGN KEY (template_id) REFERENCES whatsapp_templates (template_id) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // 5. Broadcast Recipients Table (MySQL)
    $db->exec("
    CREATE TABLE IF NOT EXISTS whatsapp_broadcast_recipients (
        recipient_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        broadcast_id BIGINT UNSIGNED NOT NULL,
        contact_id BIGINT UNSIGNED NOT NULL,
        phone_number VARCHAR(50) NOT NULL,
        parameters_json LONGTEXT NULL,
        status ENUM('queued', 'processing', 'sent', 'delivered', 'read', 'failed') NOT NULL DEFAULT 'queued',
        meta_message_id VARCHAR(120) NULL,
        error_code VARCHAR(50) NULL,
        error_message VARCHAR(255) NULL,
        sent_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_wa_rcp_bcast (broadcast_id),
        INDEX idx_wa_rcp_status (status),
        CONSTRAINT fk_wa_rcp_bcast FOREIGN KEY (broadcast_id) REFERENCES whatsapp_broadcasts (broadcast_id) ON DELETE CASCADE,
        CONSTRAINT fk_wa_rcp_contact FOREIGN KEY (contact_id) REFERENCES contacts (contact_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
}

echo "  + Meta WhatsApp Business Cloud API tables ensured successfully.\n";
