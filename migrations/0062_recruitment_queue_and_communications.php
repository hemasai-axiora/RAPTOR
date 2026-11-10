<?php
/**
 * Migration 0062: Recruitment Queue & Communications Schema (Raptor v2.3 ATS)
 * -----------------------------------------------------------------------------------
 * Provisions background processing, notifications, and AI telemetry infrastructure:
 * 21. recruitment_communications (Email/WhatsApp candidate delivery logs)
 * 22. recruitment_queue (Transactional DB FIFO queue with SKIP LOCKED)
 * 23. recruitment_ai_logs (Telemetry, prompt tokens, latency for AI copilot operations)
 */

$driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
echo "Starting Migration 0062: Recruitment Queue & Communications ({$driver})...\n";

if ($driver === 'sqlite') {
    // 21. Communications
    $db->exec("
    CREATE TABLE IF NOT EXISTS recruitment_communications (
        comm_id INTEGER PRIMARY KEY AUTOINCREMENT,
        candidate_id INT NOT NULL,
        application_id INT NULL,
        channel VARCHAR(50) NOT NULL,
        template_key VARCHAR(100) NOT NULL,
        recipient_address VARCHAR(150) NOT NULL,
        subject VARCHAR(255) NULL,
        body_excerpt TEXT NULL,
        status VARCHAR(50) NOT NULL DEFAULT 'queued',
        provider_message_id VARCHAR(255) NULL,
        error_message TEXT NULL,
        sent_at DATETIME NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );
    ");

    // 22. Queue
    $db->exec("
    CREATE TABLE IF NOT EXISTS recruitment_queue (
        queue_id INTEGER PRIMARY KEY AUTOINCREMENT,
        job_type VARCHAR(100) NOT NULL,
        payload_json TEXT NOT NULL,
        status VARCHAR(50) NOT NULL DEFAULT 'pending',
        attempts TINYINT NOT NULL DEFAULT 0,
        max_attempts TINYINT NOT NULL DEFAULT 3,
        scheduled_at DATETIME NOT NULL,
        locked_at DATETIME NULL,
        locked_by VARCHAR(100) NULL,
        last_error TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );
    ");

    // 23. AI Telemetry Logs
    $db->exec("
    CREATE TABLE IF NOT EXISTS recruitment_ai_logs (
        log_id INTEGER PRIMARY KEY AUTOINCREMENT,
        provider VARCHAR(50) NOT NULL,
        model_name VARCHAR(100) NOT NULL,
        task_type VARCHAR(100) NOT NULL,
        prompt_tokens INT NOT NULL DEFAULT 0,
        completion_tokens INT NOT NULL DEFAULT 0,
        latency_ms INT NOT NULL DEFAULT 0,
        is_success TINYINT(1) NOT NULL DEFAULT 1,
        error_message TEXT NULL,
        created_by_user_id INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );
    ");

} else {
    // MySQL 8.0+ / MariaDB Engine
    // 21. Communications
    $db->exec("
    CREATE TABLE IF NOT EXISTS recruitment_communications (
        comm_id INT AUTO_INCREMENT PRIMARY KEY,
        candidate_id INT NOT NULL,
        application_id INT NULL,
        channel ENUM('email', 'whatsapp', 'sms') NOT NULL,
        template_key VARCHAR(100) NOT NULL,
        recipient_address VARCHAR(150) NOT NULL,
        subject VARCHAR(255) NULL,
        body_excerpt TEXT NULL,
        status ENUM('queued', 'sent', 'delivered', 'failed') NOT NULL DEFAULT 'queued',
        provider_message_id VARCHAR(255) NULL,
        error_message TEXT NULL,
        sent_at DATETIME NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_comm_cand (candidate_id),
        FOREIGN KEY (candidate_id) REFERENCES recruitment_candidates(candidate_id) ON DELETE CASCADE,
        FOREIGN KEY (application_id) REFERENCES recruitment_applications(application_id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // 22. Queue
    $db->exec("
    CREATE TABLE IF NOT EXISTS recruitment_queue (
        queue_id BIGINT AUTO_INCREMENT PRIMARY KEY,
        job_type VARCHAR(100) NOT NULL,
        payload_json JSON NOT NULL,
        status ENUM('pending', 'processing', 'completed', 'failed', 'dlq') NOT NULL DEFAULT 'pending',
        attempts TINYINT NOT NULL DEFAULT 0,
        max_attempts TINYINT NOT NULL DEFAULT 3,
        scheduled_at DATETIME NOT NULL,
        locked_at DATETIME NULL,
        locked_by VARCHAR(100) NULL,
        last_error TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_rec_q_status (status, scheduled_at),
        INDEX idx_rec_q_lock (locked_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // 23. AI Telemetry Logs
    $db->exec("
    CREATE TABLE IF NOT EXISTS recruitment_ai_logs (
        log_id INT AUTO_INCREMENT PRIMARY KEY,
        provider ENUM('gemini', 'openai', 'anthropic') NOT NULL,
        model_name VARCHAR(100) NOT NULL,
        task_type VARCHAR(100) NOT NULL,
        prompt_tokens INT NOT NULL DEFAULT 0,
        completion_tokens INT NOT NULL DEFAULT 0,
        latency_ms INT NOT NULL DEFAULT 0,
        is_success TINYINT(1) NOT NULL DEFAULT 1,
        error_message TEXT NULL,
        created_by_user_id INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (created_by_user_id) REFERENCES users(user_id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
}

echo "  [OK] Migration 0062: 3 Communications, Queue & Telemetry Tables ensured successfully.\n";

