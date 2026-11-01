<?php
/**
 * Migration 0053: Omnichannel Shared Team Inbox Schema
 * --------------------------------------------------------------------------
 * Establishes the tables for Sprint 4 (Omnichannel Shared Team Inbox):
 * 1. `inbox_threads`: Consolidated threads per contact (WhatsApp, Email, SMS, Calls, Notes)
 * 2. `inbox_messages`: Unified message items (customer messages vs internal private notes)
 * 3. `canned_replies`: Pre-composed snippet shortcuts for fast agent resolution
 */

$db = Database::getInstance()->getConnection();
$driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);

echo "Starting Migration 0053: Omnichannel Shared Team Inbox Schema...\n";

if ($driver === 'sqlite') {
    // 1. Inbox Threads Table (SQLite)
    $db->exec("
    CREATE TABLE IF NOT EXISTS inbox_threads (
        thread_id INTEGER PRIMARY KEY AUTOINCREMENT,
        contact_id INTEGER NOT NULL UNIQUE,
        assigned_user_id INTEGER NULL,
        team_id INTEGER NULL,
        status TEXT NOT NULL DEFAULT 'unassigned', -- unassigned, open, pending, resolved, snoozed
        priority TEXT NOT NULL DEFAULT 'normal', -- low, normal, high, urgent
        channel_primary TEXT NOT NULL DEFAULT 'whatsapp', -- whatsapp, email, sms
        last_channel TEXT NOT NULL DEFAULT 'whatsapp',
        last_message_preview TEXT NULL,
        last_message_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        unread_count INTEGER NOT NULL DEFAULT 0,
        snoozed_until DATETIME NULL,
        resolved_at DATETIME NULL,
        resolved_by INTEGER NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (contact_id) REFERENCES contacts (contact_id) ON DELETE CASCADE
    );
    CREATE INDEX IF NOT EXISTS idx_inbox_contact ON inbox_threads (contact_id);
    CREATE INDEX IF NOT EXISTS idx_inbox_status ON inbox_threads (status);
    CREATE INDEX IF NOT EXISTS idx_inbox_assigned ON inbox_threads (assigned_user_id);
    CREATE INDEX IF NOT EXISTS idx_inbox_last_msg ON inbox_threads (last_message_at);
    ");

    // 2. Inbox Messages Table (SQLite)
    $db->exec("
    CREATE TABLE IF NOT EXISTS inbox_messages (
        message_id INTEGER PRIMARY KEY AUTOINCREMENT,
        thread_id INTEGER NOT NULL,
        contact_id INTEGER NOT NULL,
        channel TEXT NOT NULL, -- whatsapp, email, sms, call, note
        direction TEXT NOT NULL DEFAULT 'outbound', -- inbound, outbound, internal
        is_private_note INTEGER NOT NULL DEFAULT 0, -- 1 = internal agent note (hidden from customer)
        body TEXT NOT NULL,
        media_url TEXT NULL,
        sender_user_id INTEGER NULL, -- Logged in rep for outbound/internal note
        external_id TEXT NULL, -- Meta wamid, SES Message-ID, etc.
        status TEXT NOT NULL DEFAULT 'sent', -- pending, sent, delivered, read, failed
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (thread_id) REFERENCES inbox_threads (thread_id) ON DELETE CASCADE,
        FOREIGN KEY (contact_id) REFERENCES contacts (contact_id) ON DELETE CASCADE
    );
    CREATE INDEX IF NOT EXISTS idx_inbox_msg_thread ON inbox_messages (thread_id);
    CREATE INDEX IF NOT EXISTS idx_inbox_msg_private ON inbox_messages (is_private_note);
    ");

    // 3. Canned Replies Table (SQLite)
    $db->exec("
    CREATE TABLE IF NOT EXISTS canned_replies (
        canned_id INTEGER PRIMARY KEY AUTOINCREMENT,
        shortcut TEXT NOT NULL UNIQUE, -- e.g. /pricing, /demo, /intro
        title TEXT NOT NULL,
        category TEXT NOT NULL DEFAULT 'general', -- general, sales, support
        body_text TEXT NOT NULL,
        created_by INTEGER NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );
    CREATE INDEX IF NOT EXISTS idx_canned_shortcut ON canned_replies (shortcut);
    ");

} else {
    // 1. Inbox Threads Table (MySQL)
    $db->exec("
    CREATE TABLE IF NOT EXISTS inbox_threads (
        thread_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        contact_id BIGINT UNSIGNED NOT NULL UNIQUE,
        assigned_user_id INT NULL,
        team_id INT NULL,
        status ENUM('unassigned', 'open', 'pending', 'resolved', 'snoozed') NOT NULL DEFAULT 'unassigned',
        priority ENUM('low', 'normal', 'high', 'urgent') NOT NULL DEFAULT 'normal',
        channel_primary ENUM('whatsapp', 'email', 'sms') NOT NULL DEFAULT 'whatsapp',
        last_channel ENUM('whatsapp', 'email', 'sms') NOT NULL DEFAULT 'whatsapp',
        last_message_preview VARCHAR(255) NULL,
        last_message_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        unread_count INT UNSIGNED NOT NULL DEFAULT 0,
        snoozed_until DATETIME NULL,
        resolved_at DATETIME NULL,
        resolved_by INT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_inbox_contact (contact_id),
        INDEX idx_inbox_status (status),
        INDEX idx_inbox_assigned (assigned_user_id),
        INDEX idx_inbox_last_msg (last_message_at),
        CONSTRAINT fk_inbox_contact FOREIGN KEY (contact_id) REFERENCES contacts (contact_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // 2. Inbox Messages Table (MySQL)
    $db->exec("
    CREATE TABLE IF NOT EXISTS inbox_messages (
        message_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        thread_id BIGINT UNSIGNED NOT NULL,
        contact_id BIGINT UNSIGNED NOT NULL,
        channel ENUM('whatsapp', 'email', 'sms', 'call', 'note') NOT NULL,
        direction ENUM('inbound', 'outbound', 'internal') NOT NULL DEFAULT 'outbound',
        is_private_note TINYINT(1) NOT NULL DEFAULT 0,
        body TEXT NOT NULL,
        media_url VARCHAR(2048) NULL,
        sender_user_id INT NULL,
        external_id VARCHAR(150) NULL,
        status ENUM('pending', 'sent', 'delivered', 'read', 'failed') NOT NULL DEFAULT 'sent',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_inbox_msg_thread (thread_id),
        INDEX idx_inbox_msg_private (is_private_note),
        CONSTRAINT fk_inbox_msg_thread FOREIGN KEY (thread_id) REFERENCES inbox_threads (thread_id) ON DELETE CASCADE,
        CONSTRAINT fk_inbox_msg_contact FOREIGN KEY (contact_id) REFERENCES contacts (contact_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // 3. Canned Replies Table (MySQL)
    $db->exec("
    CREATE TABLE IF NOT EXISTS canned_replies (
        canned_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        shortcut VARCHAR(50) NOT NULL UNIQUE,
        title VARCHAR(120) NOT NULL,
        category VARCHAR(50) NOT NULL DEFAULT 'general',
        body_text TEXT NOT NULL,
        created_by INT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_canned_shortcut (shortcut)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
}

echo "  + Omnichannel Shared Team Inbox tables ensured successfully.\n";

// Seed default canned replies if empty
try {
    $cStmt = $db->query("SELECT COUNT(*) FROM canned_replies");
    if ((int)$cStmt->fetchColumn() === 0) {
        $db->exec("
            INSERT INTO canned_replies (shortcut, title, category, body_text) VALUES 
            ('/intro', 'Standard Introduction', 'sales', 'Hi {{first_name}}, thanks for reaching out to Raptor! How can our team assist you today?'),
            ('/pricing', 'Enterprise Pricing Overview', 'sales', 'Hello {{first_name}}, our enterprise plan includes unlimited users, custom API integrations, and dedicated SLA support. Would you like to schedule a quick 15-minute walkthrough?'),
            ('/demo', 'Demo Booking Link', 'sales', 'Hi {{first_name}}, you can pick a convenient time on our executive calendar here: https://raptor.io/book-demo'),
            ('/resolved', 'Issue Resolved Closing', 'support', 'Hi {{first_name}}, I am marking this conversation as resolved. Please reply if you need any further assistance! Have a great day.')
        ");
        echo "  + Default canned replies seeded successfully.\n";
    }
} catch (Throwable $e) {}

echo "Migration 0053 completed successfully!\n";

