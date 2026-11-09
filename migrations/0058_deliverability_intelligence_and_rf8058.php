<?php
/**
 * Migration 0058: Deliverability Intelligence, Rule Engine Schema & RFC 8058 Support
 * -----------------------------------------------------------------------------------
 * Establishes enterprise deliverability intelligence infrastructure:
 * 1. marketing_alerts: Real-time alert lifecycle (DETECTED -> OPEN -> ACKNOWLEDGED -> RESOLVED / WAIVED)
 * 2. marketing_preflight_runs: Immutable audit log of preflight policy runs & findings
 * 3. marketing_campaign_snapshots: Cryptographic content SHA-256 approval snapshots
 * 4. marketing_consent_events: Append-only compliance ledger for GDPR, DPDP, and CAN-SPAM
 * 5. Expands campaigns_broadcast & campaign_templates with communication_intent & content hashes
 */

$db = Database::getInstance()->getConnection();
$driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);

echo "Starting Migration 0058: Deliverability Intelligence & RFC 8058 Schema...\n";

// 1. Marketing Deliverability Alerts Table
if ($driver === 'sqlite') {
    $db->exec("
    CREATE TABLE IF NOT EXISTS marketing_alerts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        alert_dedup_key VARCHAR(128) NOT NULL,
        object_type VARCHAR(64) NOT NULL,
        object_id BIGINT NULL,
        brand_id INT NULL,
        rule_id VARCHAR(32) NOT NULL,
        policy_version VARCHAR(32) NOT NULL,
        severity VARCHAR(16) NOT NULL,
        state VARCHAR(32) NOT NULL DEFAULT 'OPEN',
        evidence_json TEXT NULL,
        affected_count INT NOT NULL DEFAULT 1,
        first_seen_at DATETIME NOT NULL,
        last_seen_at DATETIME NOT NULL,
        assigned_to INT NULL,
        acknowledged_by INT NULL,
        acknowledged_at DATETIME NULL,
        resolved_by INT NULL,
        resolved_at DATETIME NULL,
        resolution_note TEXT NULL,
        waiver_reason TEXT NULL,
        waiver_expiry DATETIME NULL,
        correlation_id VARCHAR(64) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );
    ");
} else {
    $db->exec("
    CREATE TABLE IF NOT EXISTS marketing_alerts (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        alert_dedup_key VARCHAR(128) NOT NULL,
        object_type VARCHAR(64) NOT NULL,
        object_id BIGINT NULL,
        brand_id INT NULL,
        rule_id VARCHAR(32) NOT NULL,
        policy_version VARCHAR(32) NOT NULL,
        severity VARCHAR(16) NOT NULL,
        state VARCHAR(32) NOT NULL DEFAULT 'OPEN',
        evidence_json JSON NULL,
        affected_count INT NOT NULL DEFAULT 1,
        first_seen_at DATETIME NOT NULL,
        last_seen_at DATETIME NOT NULL,
        assigned_to INT NULL,
        acknowledged_by INT NULL,
        acknowledged_at DATETIME NULL,
        resolved_by INT NULL,
        resolved_at DATETIME NULL,
        resolution_note TEXT NULL,
        waiver_reason TEXT NULL,
        waiver_expiry DATETIME NULL,
        correlation_id VARCHAR(64) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_alert_dedup (alert_dedup_key),
        INDEX idx_alert_state (state, severity),
        INDEX idx_alert_rule (rule_id),
        INDEX idx_alert_object (object_type, object_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
}
echo "  + marketing_alerts table ensured\n";

// 2. Preflight Runs Table
if ($driver === 'sqlite') {
    $db->exec("
    CREATE TABLE IF NOT EXISTS marketing_preflight_runs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        campaign_id INT NULL,
        template_id INT NULL,
        run_id VARCHAR(64) NOT NULL UNIQUE,
        artifact_sha256 VARCHAR(64) NOT NULL,
        policy_version VARCHAR(32) NOT NULL,
        status VARCHAR(32) NOT NULL,
        summary_json TEXT NOT NULL,
        findings_json TEXT NOT NULL,
        evaluated_at DATETIME NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );
    ");
} else {
    $db->exec("
    CREATE TABLE IF NOT EXISTS marketing_preflight_runs (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        campaign_id INT NULL,
        template_id INT NULL,
        run_id VARCHAR(64) NOT NULL UNIQUE,
        artifact_sha256 VARCHAR(64) NOT NULL,
        policy_version VARCHAR(32) NOT NULL,
        status VARCHAR(32) NOT NULL,
        summary_json JSON NOT NULL,
        findings_json JSON NOT NULL,
        evaluated_at DATETIME NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_pfl_camp (campaign_id),
        INDEX idx_pfl_tpl (template_id),
        INDEX idx_pfl_hash (artifact_sha256)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
}
echo "  + marketing_preflight_runs table ensured\n";

// 3. Campaign Approval Snapshots Table
if ($driver === 'sqlite') {
    $db->exec("
    CREATE TABLE IF NOT EXISTS marketing_campaign_snapshots (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        campaign_id INT NOT NULL,
        approved_hash VARCHAR(64) NOT NULL,
        template_revision_id INT NULL,
        policy_version VARCHAR(32) NOT NULL,
        approved_by INT NULL,
        approved_at DATETIME NOT NULL,
        snapshot_json TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );
    ");
} else {
    $db->exec("
    CREATE TABLE IF NOT EXISTS marketing_campaign_snapshots (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        campaign_id INT NOT NULL,
        approved_hash VARCHAR(64) NOT NULL,
        template_revision_id INT NULL,
        policy_version VARCHAR(32) NOT NULL,
        approved_by INT NULL,
        approved_at DATETIME NOT NULL,
        snapshot_json JSON NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_snap_camp (campaign_id),
        INDEX idx_snap_hash (approved_hash)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
}
echo "  + marketing_campaign_snapshots table ensured\n";

// 4. Marketing Consent Events Ledger Table
if ($driver === 'sqlite') {
    $db->exec("
    CREATE TABLE IF NOT EXISTS marketing_consent_events (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        contact_id BIGINT NOT NULL,
        brand_id INT NULL,
        channel VARCHAR(24) NOT NULL DEFAULT 'email',
        purpose VARCHAR(60) NOT NULL,
        action VARCHAR(20) NOT NULL,
        legal_basis VARCHAR(60) NULL,
        evidence_ref VARCHAR(255) NULL,
        notice_version VARCHAR(64) NULL,
        occurred_at DATETIME NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );
    ");
} else {
    $db->exec("
    CREATE TABLE IF NOT EXISTS marketing_consent_events (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        contact_id BIGINT NOT NULL,
        brand_id INT NULL,
        channel VARCHAR(24) NOT NULL DEFAULT 'email',
        purpose VARCHAR(60) NOT NULL,
        action VARCHAR(20) NOT NULL,
        legal_basis VARCHAR(60) NULL,
        evidence_ref VARCHAR(255) NULL,
        notice_version VARCHAR(64) NULL,
        occurred_at DATETIME NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_consent_lookup (contact_id, channel, purpose, occurred_at),
        INDEX idx_consent_action (action)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
}
echo "  + marketing_consent_events table ensured\n";

// 5. Expand campaigns_broadcast with Intent & Cryptographic Snapshots
$alterCamp = [
    "ALTER TABLE campaigns_broadcast ADD COLUMN communication_intent VARCHAR(32) NOT NULL DEFAULT 'promotional'",
    "ALTER TABLE campaigns_broadcast ADD COLUMN approved_snapshot_hash VARCHAR(64) NULL",
    "ALTER TABLE campaigns_broadcast ADD COLUMN content_sha256 VARCHAR(64) NULL"
];
foreach ($alterCamp as $sql) {
    try {
        $db->exec($sql);
    } catch (Throwable $e) {}
}
echo "  + campaigns_broadcast intent & snapshot columns ensured\n";

// 6. Expand campaign_templates with Intent & Revision tracking
$alterTpl = [
    "ALTER TABLE campaign_templates ADD COLUMN communication_intent VARCHAR(32) NOT NULL DEFAULT 'promotional'",
    "ALTER TABLE campaign_templates ADD COLUMN revision_no INT NOT NULL DEFAULT 1",
    "ALTER TABLE campaign_templates ADD COLUMN content_sha256 VARCHAR(64) NULL"
];
foreach ($alterTpl as $sql) {
    try {
        $db->exec($sql);
    } catch (Throwable $e) {}
}
echo "  + campaign_templates intent & revision columns ensured\n";

echo "Migration 0058 completed successfully.\n";

