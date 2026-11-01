<?php
/**
 * Migration 0056: Social Media Live Publishing, OAuth & Meta Lead Ads Schema
 * --------------------------------------------------------------------------
 * Establishes tables for Sprint 8 (Social Media Publishing & Lead Ads):
 * 1. `social_oauth_tokens`: Multi-platform OAuth credentials & expiry tracking
 * 2. `social_publish_queue`: Asynchronous post publishing queue & status tracker
 * 3. `meta_lead_ad_campaigns`: Meta Lead Ads form mappings & synchronization metrics
 */

$db = Database::getInstance()->getConnection();
$driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);

echo "Starting Migration 0056: Social Media Publishing & Lead Ads Schema...\n";

if ($driver === 'sqlite') {
    // 1. Social OAuth Tokens Table (SQLite)
    $db->exec("
    CREATE TABLE IF NOT EXISTS social_oauth_tokens (
        token_id INTEGER PRIMARY KEY AUTOINCREMENT,
        platform TEXT NOT NULL, -- linkedin, facebook, instagram, twitter_x, mock
        account_name TEXT NOT NULL,
        account_id_external TEXT NULL,
        access_token TEXT NOT NULL,
        refresh_token TEXT NULL,
        token_expires_at DATETIME NULL,
        scopes_json TEXT NULL,
        is_active INTEGER NOT NULL DEFAULT 1,
        last_refreshed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );
    CREATE INDEX IF NOT EXISTS idx_social_plat ON social_oauth_tokens (platform, is_active);
    ");

    // 2. Social Publish Queue Table (SQLite)
    $db->exec("
    CREATE TABLE IF NOT EXISTS social_publish_queue (
        queue_id INTEGER PRIMARY KEY AUTOINCREMENT,
        post_id INTEGER NULL,
        token_id INTEGER NULL,
        platform TEXT NOT NULL, -- linkedin, facebook, instagram, twitter_x, mock
        message_text TEXT NOT NULL,
        media_url TEXT NULL,
        scheduled_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        status TEXT NOT NULL DEFAULT 'pending', -- pending, publishing, published, failed
        external_post_id TEXT NULL,
        error_message TEXT NULL,
        published_at DATETIME NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (token_id) REFERENCES social_oauth_tokens (token_id) ON DELETE SET NULL
    );
    CREATE INDEX IF NOT EXISTS idx_social_queue_status ON social_publish_queue (status, scheduled_at);
    ");

    // 3. Meta Lead Ad Campaigns Table (SQLite)
    $db->exec("
    CREATE TABLE IF NOT EXISTS meta_lead_ad_campaigns (
        ad_id INTEGER PRIMARY KEY AUTOINCREMENT,
        platform TEXT NOT NULL DEFAULT 'facebook', -- facebook, instagram
        form_id_external TEXT NOT NULL UNIQUE,
        campaign_name TEXT NOT NULL,
        total_leads_synced INTEGER NOT NULL DEFAULT 0,
        last_synced_at DATETIME NULL,
        is_active INTEGER NOT NULL DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );
    CREATE INDEX IF NOT EXISTS idx_meta_form ON meta_lead_ad_campaigns (form_id_external);
    ");

} else {
    // 1. Social OAuth Tokens Table (MySQL)
    $db->exec("
    CREATE TABLE IF NOT EXISTS social_oauth_tokens (
        token_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        platform ENUM('linkedin', 'facebook', 'instagram', 'twitter_x', 'mock') NOT NULL,
        account_name VARCHAR(150) NOT NULL,
        account_id_external VARCHAR(100) NULL,
        access_token TEXT NOT NULL,
        refresh_token TEXT NULL,
        token_expires_at DATETIME NULL,
        scopes_json LONGTEXT NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        last_refreshed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_social_plat (platform, is_active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // 2. Social Publish Queue Table (MySQL)
    $db->exec("
    CREATE TABLE IF NOT EXISTS social_publish_queue (
        queue_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        post_id INT NULL,
        token_id BIGINT UNSIGNED NULL,
        platform ENUM('linkedin', 'facebook', 'instagram', 'twitter_x', 'mock') NOT NULL,
        message_text TEXT NOT NULL,
        media_url VARCHAR(2048) NULL,
        scheduled_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        status ENUM('pending', 'publishing', 'published', 'failed') NOT NULL DEFAULT 'pending',
        external_post_id VARCHAR(255) NULL,
        error_message TEXT NULL,
        published_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_social_queue_status (status, scheduled_at),
        CONSTRAINT fk_social_queue_token FOREIGN KEY (token_id) REFERENCES social_oauth_tokens (token_id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // 3. Meta Lead Ad Campaigns Table (MySQL)
    $db->exec("
    CREATE TABLE IF NOT EXISTS meta_lead_ad_campaigns (
        ad_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        platform ENUM('facebook', 'instagram') NOT NULL DEFAULT 'facebook',
        form_id_external VARCHAR(100) NOT NULL UNIQUE,
        campaign_name VARCHAR(150) NOT NULL,
        total_leads_synced INT UNSIGNED NOT NULL DEFAULT 0,
        last_synced_at DATETIME NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_meta_form (form_id_external)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
}

echo "  + Social Media Publishing, OAuth & Meta Lead Ads tables created successfully.\n";

// Seed mock OAuth accounts if empty
try {
    $tStmt = $db->query("SELECT COUNT(*) FROM social_oauth_tokens");
    if ((int)$tStmt->fetchColumn() === 0) {
        $db->exec("
            INSERT INTO social_oauth_tokens (platform, account_name, account_id_external, access_token, token_expires_at, is_active) VALUES
            ('linkedin', 'Raptor Corporate (LinkedIn)', 'urn:li:organization:98765432', 'mock_li_token_valid_998877', datetime('now', '+60 days'), 1),
            ('facebook', 'Raptor Official Page (Facebook)', '100234567890123', 'mock_fb_token_valid_443322', datetime('now', '+60 days'), 1),
            ('instagram', 'Raptor Product Visuals (Instagram)', '17841400123456789', 'mock_ig_token_valid_112233', datetime('now', '+60 days'), 1)
        ");
        echo "  + Default social OAuth tokens initialized.\n";
    }
} catch (Throwable $e) {}

echo "Migration 0056 completed successfully!\n";

