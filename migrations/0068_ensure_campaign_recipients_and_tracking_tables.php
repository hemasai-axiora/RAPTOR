<?php
/**
 * Migration 0068: Ensure campaign_recipients and dependent tracking tables exist
 * -------------------------------------------------------------------------------
 * Re-applies the campaign_recipients, campaign_email_queue, and campaign_email_events
 * table definitions using CREATE TABLE IF NOT EXISTS so it is safe to run on databases
 * where these tables already exist (the IF NOT EXISTS guard prevents duplication).
 *
 * This migration resolves:
 *   - SQLSTATE[42S02] Table 'raptor_crm_db.campaign_recipients' doesn't exist
 *     (seen in CampaignTrackingController when tracking links from live emails are clicked)
 *
 * Root cause: Migration 0049 defines these tables but on some environments the migration
 * runner may have encountered a FK constraint failure mid-run, leaving the tables absent.
 * This migration re-declares them defensively.
 */

$db     = Database::getInstance()->getConnection();
$driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);

echo "Starting Migration 0068: Ensure campaign_recipients & tracking tables...\n";

// ─── 1. campaign_recipients ───────────────────────────────────────────────────
if ($driver === 'sqlite') {
    $db->exec("
    CREATE TABLE IF NOT EXISTS campaign_recipients (
        recipient_id   INTEGER PRIMARY KEY AUTOINCREMENT,
        campaign_id    INT NOT NULL,
        contact_id     BIGINT NOT NULL,
        email          VARCHAR(255) NOT NULL,
        subject_variant VARCHAR(1) NOT NULL DEFAULT 'A',
        status         VARCHAR(32)  NOT NULL DEFAULT 'queued',
        sent_at        DATETIME NULL,
        delivered_at   DATETIME NULL,
        opened_at      DATETIME NULL,
        open_count     INT NOT NULL DEFAULT 0,
        clicked_at     DATETIME NULL,
        click_count    INT NOT NULL DEFAULT 0,
        bounce_type    VARCHAR(16) NOT NULL DEFAULT 'none',
        bounce_reason  VARCHAR(255) NULL,
        unsubscribe_token VARCHAR(500) NOT NULL DEFAULT '',
        provider_message_id VARCHAR(255) NULL,
        created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );
    ");
} else {
    $db->exec("
    CREATE TABLE IF NOT EXISTS campaign_recipients (
        recipient_id        BIGINT AUTO_INCREMENT PRIMARY KEY,
        campaign_id         INT NOT NULL,
        contact_id          BIGINT NOT NULL,
        email               VARCHAR(255) NOT NULL,
        subject_variant     ENUM('A','B') NOT NULL DEFAULT 'A',
        status              ENUM('queued','sent','delivered','bounced','complained','failed') NOT NULL DEFAULT 'queued',
        sent_at             DATETIME NULL,
        delivered_at        DATETIME NULL,
        opened_at           DATETIME NULL,
        open_count          INT NOT NULL DEFAULT 0,
        clicked_at          DATETIME NULL,
        click_count         INT NOT NULL DEFAULT 0,
        bounce_type         ENUM('none','soft','hard') NOT NULL DEFAULT 'none',
        bounce_reason       VARCHAR(255) NULL,
        unsubscribe_token   VARCHAR(500) NOT NULL DEFAULT '',
        provider_message_id VARCHAR(255) NULL,
        created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_camp_recipient (campaign_id, contact_id),
        INDEX idx_recip_email  (email),
        INDEX idx_recip_status (status),
        INDEX idx_recip_msg_id (provider_message_id),
        INDEX idx_recip_unsub  (unsubscribe_token(255)),
        CONSTRAINT fk_recip_campaign FOREIGN KEY (campaign_id) REFERENCES campaigns_broadcast(campaign_id) ON DELETE CASCADE,
        CONSTRAINT fk_recip_contact  FOREIGN KEY (contact_id)  REFERENCES campaign_contacts(contact_id)   ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
}
echo "  + campaign_recipients table ensured\n";

// ─── 2. campaign_email_queue ──────────────────────────────────────────────────
if ($driver === 'sqlite') {
    $db->exec("
    CREATE TABLE IF NOT EXISTS campaign_email_queue (
        queue_id      INTEGER PRIMARY KEY AUTOINCREMENT,
        campaign_id   INT NOT NULL,
        recipient_id  BIGINT NOT NULL,
        status        VARCHAR(32)  NOT NULL DEFAULT 'pending',
        priority      TINYINT NOT NULL DEFAULT 10,
        attempts      TINYINT NOT NULL DEFAULT 0,
        max_attempts  TINYINT NOT NULL DEFAULT 3,
        scheduled_at  DATETIME NOT NULL,
        locked_at     DATETIME NULL,
        locked_by     VARCHAR(100) NULL,
        last_error    TEXT NULL,
        created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );
    ");
} else {
    $db->exec("
    CREATE TABLE IF NOT EXISTS campaign_email_queue (
        queue_id     BIGINT AUTO_INCREMENT PRIMARY KEY,
        campaign_id  INT NOT NULL,
        recipient_id BIGINT NOT NULL,
        status       ENUM('pending','processing','sent','failed','cancelled') NOT NULL DEFAULT 'pending',
        priority     TINYINT NOT NULL DEFAULT 10,
        attempts     TINYINT NOT NULL DEFAULT 0,
        max_attempts TINYINT NOT NULL DEFAULT 3,
        scheduled_at DATETIME NOT NULL,
        locked_at    DATETIME NULL,
        locked_by    VARCHAR(100) NULL,
        last_error   TEXT NULL,
        created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_q_status_sched (status, scheduled_at),
        INDEX idx_q_locked       (locked_at),
        CONSTRAINT fk_queue_campaign   FOREIGN KEY (campaign_id)  REFERENCES campaigns_broadcast(campaign_id)  ON DELETE CASCADE,
        CONSTRAINT fk_queue_recipient  FOREIGN KEY (recipient_id) REFERENCES campaign_recipients(recipient_id)  ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
}
echo "  + campaign_email_queue table ensured\n";

// ─── 3. campaign_email_events ─────────────────────────────────────────────────
if ($driver === 'sqlite') {
    $db->exec("
    CREATE TABLE IF NOT EXISTS campaign_email_events (
        event_id            INTEGER PRIMARY KEY AUTOINCREMENT,
        campaign_id         INT NULL,
        recipient_id        BIGINT NULL,
        contact_id          BIGINT NULL,
        event_type          VARCHAR(32) NOT NULL,
        ip_address          VARCHAR(45) NULL,
        user_agent          VARCHAR(255) NULL,
        target_url          TEXT NULL,
        provider_message_id VARCHAR(255) NULL,
        payload_json        TEXT NULL,
        created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );
    ");
} else {
    $db->exec("
    CREATE TABLE IF NOT EXISTS campaign_email_events (
        event_id            BIGINT AUTO_INCREMENT PRIMARY KEY,
        campaign_id         INT NULL,
        recipient_id        BIGINT NULL,
        contact_id          BIGINT NULL,
        event_type          ENUM('sent','delivered','open','click','soft_bounce','hard_bounce','complaint','unsubscribe') NOT NULL,
        ip_address          VARCHAR(45) NULL,
        user_agent          VARCHAR(255) NULL,
        target_url          TEXT NULL,
        provider_message_id VARCHAR(255) NULL,
        payload_json        JSON NULL,
        created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_ev_camp    (campaign_id, event_type),
        INDEX idx_ev_contact (contact_id),
        INDEX idx_ev_created (created_at),
        CONSTRAINT fk_ev_campaign  FOREIGN KEY (campaign_id)  REFERENCES campaigns_broadcast(campaign_id)  ON DELETE SET NULL,
        CONSTRAINT fk_ev_recipient FOREIGN KEY (recipient_id) REFERENCES campaign_recipients(recipient_id) ON DELETE SET NULL,
        CONSTRAINT fk_ev_contact   FOREIGN KEY (contact_id)   REFERENCES campaign_contacts(contact_id)    ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
}
echo "  + campaign_email_events table ensured\n";

// ─── 4. Ensure unsubscribe_token is NOT NULL (safe backfill for existing rows) ─
if ($driver !== 'sqlite') {
    try {
        // Backfill any rows that may have empty tokens (defensive)
        $db->exec("
            UPDATE campaign_recipients
            SET unsubscribe_token = CONCAT('legacy-', campaign_id, '-', contact_id, '-', UNIX_TIMESTAMP())
            WHERE unsubscribe_token = '' OR unsubscribe_token IS NULL
        ");
    } catch (Throwable $e) {
        echo "  ~ Token backfill notice: " . $e->getMessage() . "\n";
    }
}

echo "Migration 0068 completed successfully.\n";

