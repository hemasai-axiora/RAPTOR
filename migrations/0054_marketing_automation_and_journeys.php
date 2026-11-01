<?php
/**
 * Migration 0054: Marketing Automation Journeys & Behavioral Lead Scoring Schema
 * --------------------------------------------------------------------------
 * Establishes the tables for Sprint 5 (Automation Journeys & Lead Scoring):
 * 1. `marketing_journeys`: Multi-step automation workflows & triggers
 * 2. `journey_steps`: Sequence steps (action, delay, condition, score adjustment)
 * 3. `journey_enrollments`: Contact progression tracker through workflow steps
 * 4. `lead_scoring_rules`: Dynamic behavioral point system
 * 5. `lead_score_logs`: Audit trail for all lead score adjustments
 */

$db = Database::getInstance()->getConnection();
$driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);

echo "Starting Migration 0054: Marketing Automation Journeys & Lead Scoring Schema...\n";

if ($driver === 'sqlite') {
    // 1. Marketing Journeys Table (SQLite)
    $db->exec("
    CREATE TABLE IF NOT EXISTS marketing_journeys (
        journey_id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        description TEXT NULL,
        trigger_type TEXT NOT NULL, -- form_submitted, email_clicked, whatsapp_received, lead_created, tag_added
        trigger_config_json TEXT NULL,
        status TEXT NOT NULL DEFAULT 'draft', -- draft, active, paused, archived
        total_enrolled INTEGER NOT NULL DEFAULT 0,
        total_completed INTEGER NOT NULL DEFAULT 0,
        created_by INTEGER NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );
    CREATE INDEX IF NOT EXISTS idx_journey_trigger ON marketing_journeys (trigger_type, status);
    ");

    // 2. Journey Steps Table (SQLite)
    $db->exec("
    CREATE TABLE IF NOT EXISTS journey_steps (
        step_id INTEGER PRIMARY KEY AUTOINCREMENT,
        journey_id INTEGER NOT NULL,
        step_order INTEGER NOT NULL,
        step_type TEXT NOT NULL, -- action, delay, condition
        action_type TEXT NOT NULL, -- send_email, send_whatsapp, adjust_lead_score, update_stage, assign_rep, wait_delay
        action_config_json TEXT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (journey_id) REFERENCES marketing_journeys (journey_id) ON DELETE CASCADE
    );
    CREATE INDEX IF NOT EXISTS idx_step_journey ON journey_steps (journey_id, step_order);
    ");

    // 3. Journey Enrollments Table (SQLite)
    $db->exec("
    CREATE TABLE IF NOT EXISTS journey_enrollments (
        enrollment_id INTEGER PRIMARY KEY AUTOINCREMENT,
        journey_id INTEGER NOT NULL,
        contact_id INTEGER NOT NULL,
        current_step_order INTEGER NOT NULL DEFAULT 1,
        status TEXT NOT NULL DEFAULT 'active', -- active, completed, cancelled, paused
        next_run_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        enrolled_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        completed_at DATETIME NULL,
        execution_log_json TEXT NULL,
        FOREIGN KEY (journey_id) REFERENCES marketing_journeys (journey_id) ON DELETE CASCADE,
        FOREIGN KEY (contact_id) REFERENCES contacts (contact_id) ON DELETE CASCADE
    );
    CREATE INDEX IF NOT EXISTS idx_enrollment_status ON journey_enrollments (status, next_run_at);
    CREATE INDEX IF NOT EXISTS idx_enrollment_contact ON journey_enrollments (contact_id);
    ");

    // 4. Lead Scoring Rules Table (SQLite)
    $db->exec("
    CREATE TABLE IF NOT EXISTS lead_scoring_rules (
        rule_id INTEGER PRIMARY KEY AUTOINCREMENT,
        rule_name TEXT NOT NULL,
        event_type TEXT NOT NULL UNIQUE, -- form_submit, email_open, email_click, whatsapp_reply, pageview_high_intent, inbound_call
        score_delta INTEGER NOT NULL DEFAULT 5,
        max_daily_occurrences INTEGER NOT NULL DEFAULT 5,
        is_active INTEGER NOT NULL DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );
    CREATE INDEX IF NOT EXISTS idx_rule_event ON lead_scoring_rules (event_type, is_active);
    ");

    // 5. Lead Score Logs Table (SQLite)
    $db->exec("
    CREATE TABLE IF NOT EXISTS lead_score_logs (
        log_id INTEGER PRIMARY KEY AUTOINCREMENT,
        contact_id INTEGER NOT NULL,
        rule_id INTEGER NULL,
        score_delta INTEGER NOT NULL,
        previous_score INTEGER NOT NULL,
        new_score INTEGER NOT NULL,
        reason TEXT NOT NULL,
        occurred_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (contact_id) REFERENCES contacts (contact_id) ON DELETE CASCADE
    );
    CREATE INDEX IF NOT EXISTS idx_score_log_contact ON lead_score_logs (contact_id);
    ");

} else {
    // 1. Marketing Journeys Table (MySQL)
    $db->exec("
    CREATE TABLE IF NOT EXISTS marketing_journeys (
        journey_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(150) NOT NULL,
        description VARCHAR(255) NULL,
        trigger_type ENUM('form_submitted', 'email_clicked', 'whatsapp_received', 'lead_created', 'tag_added') NOT NULL,
        trigger_config_json LONGTEXT NULL,
        status ENUM('draft', 'active', 'paused', 'archived') NOT NULL DEFAULT 'draft',
        total_enrolled INT UNSIGNED NOT NULL DEFAULT 0,
        total_completed INT UNSIGNED NOT NULL DEFAULT 0,
        created_by INT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_journey_trigger (trigger_type, status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // 2. Journey Steps Table (MySQL)
    $db->exec("
    CREATE TABLE IF NOT EXISTS journey_steps (
        step_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        journey_id BIGINT UNSIGNED NOT NULL,
        step_order INT NOT NULL,
        step_type ENUM('action', 'delay', 'condition') NOT NULL,
        action_type ENUM('send_email', 'send_whatsapp', 'adjust_lead_score', 'update_stage', 'assign_rep', 'wait_delay') NOT NULL,
        action_config_json LONGTEXT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_step_journey (journey_id, step_order),
        CONSTRAINT fk_step_journey FOREIGN KEY (journey_id) REFERENCES marketing_journeys (journey_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // 3. Journey Enrollments Table (MySQL)
    $db->exec("
    CREATE TABLE IF NOT EXISTS journey_enrollments (
        enrollment_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        journey_id BIGINT UNSIGNED NOT NULL,
        contact_id BIGINT UNSIGNED NOT NULL,
        current_step_order INT NOT NULL DEFAULT 1,
        status ENUM('active', 'completed', 'cancelled', 'paused') NOT NULL DEFAULT 'active',
        next_run_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        enrolled_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        completed_at DATETIME NULL,
        execution_log_json LONGTEXT NULL,
        INDEX idx_enrollment_status (status, next_run_at),
        INDEX idx_enrollment_contact (contact_id),
        CONSTRAINT fk_enroll_journey FOREIGN KEY (journey_id) REFERENCES marketing_journeys (journey_id) ON DELETE CASCADE,
        CONSTRAINT fk_enroll_contact FOREIGN KEY (contact_id) REFERENCES contacts (contact_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // 4. Lead Scoring Rules Table (MySQL)
    $db->exec("
    CREATE TABLE IF NOT EXISTS lead_scoring_rules (
        rule_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        rule_name VARCHAR(100) NOT NULL,
        event_type VARCHAR(60) NOT NULL UNIQUE,
        score_delta INT NOT NULL DEFAULT 5,
        max_daily_occurrences INT NOT NULL DEFAULT 5,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_rule_event (event_type, is_active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // 5. Lead Score Logs Table (MySQL)
    $db->exec("
    CREATE TABLE IF NOT EXISTS lead_score_logs (
        log_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        contact_id BIGINT UNSIGNED NOT NULL,
        rule_id INT UNSIGNED NULL,
        score_delta INT NOT NULL,
        previous_score INT NOT NULL,
        new_score INT NOT NULL,
        reason VARCHAR(255) NOT NULL,
        occurred_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_score_log_contact (contact_id),
        CONSTRAINT fk_score_log_contact FOREIGN KEY (contact_id) REFERENCES contacts (contact_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
}

echo "  + Automation Journeys & Lead Scoring tables created successfully.\n";

// Seed default lead scoring rules if empty
try {
    $rStmt = $db->query("SELECT COUNT(*) FROM lead_scoring_rules");
    if ((int)$rStmt->fetchColumn() === 0) {
        $db->exec("
            INSERT INTO lead_scoring_rules (rule_name, event_type, score_delta, max_daily_occurrences, is_active) VALUES
            ('Inbound Web Form Submission', 'form_submit', 25, 3, 1),
            ('Marketing Email Open', 'email_open', 5, 5, 1),
            ('Marketing Email Link Click', 'email_click', 15, 5, 1),
            ('WhatsApp Customer Reply', 'whatsapp_reply', 20, 4, 1),
            ('High Intent Page Visit (Pricing/Demo)', 'pageview_high_intent', 10, 5, 1),
            ('Inbound Telephony Call Connected', 'inbound_call', 30, 2, 1)
        ");
        echo "  + Default lead scoring rules seeded successfully.\n";
    }
} catch (Throwable $e) {}

echo "Migration 0054 completed successfully!\n";

