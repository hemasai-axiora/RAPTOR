<?php
/**
 * Migration 0055: Cloud Telephony, Call Management & TRAI DND Compliance Schema
 * --------------------------------------------------------------------------
 * Establishes the tables for Sprint 6 (Telephony & Call Management):
 * 1. `telephony_calls`: Inbound/outbound calls, recordings, dispositions, durations
 * 2. `dnd_registry`: TRAI / National DND registry scrubbing & compliance records
 * 3. `call_dispositions`: Standardized call outcome codes & follow-up rules
 */

$db = Database::getInstance()->getConnection();
$driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);

echo "Starting Migration 0055: Cloud Telephony & Call Management Schema...\n";

if ($driver === 'sqlite') {
    // 1. Telephony Calls Table (SQLite)
    $db->exec("
    CREATE TABLE IF NOT EXISTS telephony_calls (
        call_id INTEGER PRIMARY KEY AUTOINCREMENT,
        call_uuid TEXT NOT NULL UNIQUE,
        contact_id INTEGER NULL,
        lead_id INTEGER NULL,
        user_id INTEGER NULL, -- Calling sales agent
        provider TEXT NOT NULL DEFAULT 'mock', -- twilio, exotel, ozonetel, mock
        call_sid TEXT NULL, -- External telephony provider call SID
        direction TEXT NOT NULL DEFAULT 'outbound', -- inbound, outbound
        from_number TEXT NOT NULL,
        to_number TEXT NOT NULL,
        status TEXT NOT NULL DEFAULT 'initiated', -- initiated, ringing, in_progress, completed, busy, no_answer, failed
        duration_seconds INTEGER NOT NULL DEFAULT 0,
        recording_url TEXT NULL,
        disposition_code TEXT NULL, -- interested, callback_requested, not_interested, invalid_number, voicemail, wrong_contact
        disposition_notes TEXT NULL,
        dnd_status TEXT NOT NULL DEFAULT 'scrubbed_clean', -- scrubbed_clean, dnd_restricted
        started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        ended_at DATETIME NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (contact_id) REFERENCES contacts (contact_id) ON DELETE SET NULL,
        FOREIGN KEY (lead_id) REFERENCES leads (lead_id) ON DELETE SET NULL,
        FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE SET NULL
    );
    CREATE INDEX IF NOT EXISTS idx_call_contact ON telephony_calls (contact_id);
    CREATE INDEX IF NOT EXISTS idx_call_status ON telephony_calls (status);
    CREATE INDEX IF NOT EXISTS idx_call_sid ON telephony_calls (call_sid);
    CREATE INDEX IF NOT EXISTS idx_call_agent ON telephony_calls (user_id);
    ");

    // 2. DND Registry Table (SQLite)
    $db->exec("
    CREATE TABLE IF NOT EXISTS dnd_registry (
        dnd_id INTEGER PRIMARY KEY AUTOINCREMENT,
        phone_number TEXT NOT NULL UNIQUE,
        dnd_type TEXT NOT NULL DEFAULT 'full', -- full, promotional_only
        source TEXT NOT NULL DEFAULT 'trai_scrubbed', -- trai_scrubbed, customer_optout, manual
        registered_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        expires_at DATETIME NULL
    );
    CREATE INDEX IF NOT EXISTS idx_dnd_phone ON dnd_registry (phone_number);
    ");

    // 3. Call Dispositions Lookup Table (SQLite)
    $db->exec("
    CREATE TABLE IF NOT EXISTS call_dispositions (
        disposition_id INTEGER PRIMARY KEY AUTOINCREMENT,
        code TEXT NOT NULL UNIQUE,
        name TEXT NOT NULL,
        category TEXT NOT NULL DEFAULT 'positive', -- positive, neutral, negative
        requires_callback INTEGER NOT NULL DEFAULT 0,
        is_active INTEGER NOT NULL DEFAULT 1
    );
    ");

} else {
    // 1. Telephony Calls Table (MySQL)
    $db->exec("
    CREATE TABLE IF NOT EXISTS telephony_calls (
        call_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        call_uuid CHAR(36) NOT NULL UNIQUE,
        contact_id BIGINT UNSIGNED NULL,
        lead_id INT NULL,
        user_id INT NULL,
        provider ENUM('twilio', 'exotel', 'ozonetel', 'mock') NOT NULL DEFAULT 'mock',
        call_sid VARCHAR(100) NULL,
        direction ENUM('inbound', 'outbound') NOT NULL DEFAULT 'outbound',
        from_number VARCHAR(50) NOT NULL,
        to_number VARCHAR(50) NOT NULL,
        status ENUM('initiated', 'ringing', 'in_progress', 'completed', 'busy', 'no_answer', 'failed') NOT NULL DEFAULT 'initiated',
        duration_seconds INT UNSIGNED NOT NULL DEFAULT 0,
        recording_url VARCHAR(2048) NULL,
        disposition_code VARCHAR(50) NULL,
        disposition_notes TEXT NULL,
        dnd_status ENUM('scrubbed_clean', 'dnd_restricted') NOT NULL DEFAULT 'scrubbed_clean',
        started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        ended_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_call_contact (contact_id),
        INDEX idx_call_status (status),
        INDEX idx_call_sid (call_sid),
        INDEX idx_call_agent (user_id),
        CONSTRAINT fk_call_contact FOREIGN KEY (contact_id) REFERENCES contacts (contact_id) ON DELETE SET NULL,
        CONSTRAINT fk_call_lead FOREIGN KEY (lead_id) REFERENCES leads (lead_id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // 2. DND Registry Table (MySQL)
    $db->exec("
    CREATE TABLE IF NOT EXISTS dnd_registry (
        dnd_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        phone_number VARCHAR(50) NOT NULL UNIQUE,
        dnd_type ENUM('full', 'promotional_only') NOT NULL DEFAULT 'full',
        source ENUM('trai_scrubbed', 'customer_optout', 'manual') NOT NULL DEFAULT 'trai_scrubbed',
        registered_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        expires_at DATETIME NULL,
        INDEX idx_dnd_phone (phone_number)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // 3. Call Dispositions Lookup Table (MySQL)
    $db->exec("
    CREATE TABLE IF NOT EXISTS call_dispositions (
        disposition_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        code VARCHAR(50) NOT NULL UNIQUE,
        name VARCHAR(100) NOT NULL,
        category ENUM('positive', 'neutral', 'negative') NOT NULL DEFAULT 'positive',
        requires_callback TINYINT(1) NOT NULL DEFAULT 0,
        is_active TINYINT(1) NOT NULL DEFAULT 1
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
}

echo "  + Telephony, Call Management & DND tables created successfully.\n";

// Seed standard call dispositions if empty
try {
    $dStmt = $db->query("SELECT COUNT(*) FROM call_dispositions");
    if ((int)$dStmt->fetchColumn() === 0) {
        $db->exec("
            INSERT INTO call_dispositions (code, name, category, requires_callback, is_active) VALUES
            ('interested', 'Interested - Follow-up Demo', 'positive', 1, 1),
            ('callback_requested', 'Callback Requested by Prospect', 'neutral', 1, 1),
            ('not_interested', 'Not Interested / No Requirement', 'negative', 0, 1),
            ('invalid_number', 'Invalid or Disconnected Number', 'negative', 0, 1),
            ('left_voicemail', 'Left Voicemail / Answering Machine', 'neutral', 0, 1),
            ('wrong_contact', 'Wrong Person / Changed Company', 'negative', 0, 1),
            ('connected_closed', 'Deal Closed / Sale Agreed', 'positive', 0, 1)
        ");
        echo "  + Standard call dispositions seeded successfully.\n";
    }
} catch (Throwable $e) {}

echo "Migration 0055 completed successfully!\n";

