<?php
/**
 * Raptor CRM — Migration 0034: Session Timeout & Confirmation Tracking
 * Creates the `sessions` table to store login checkpoints, rolling 2-hour extensions,
 * and user activity confirmation timestamps.
 */

class Migration0034SessionTimeout {
    public function up(PDO $db): void {
        $db->exec("CREATE TABLE IF NOT EXISTS `sessions` (
            `session_id` VARCHAR(128) NOT NULL,
            `user_id` INT(11) NOT NULL,
            `login_time` DATETIME NOT NULL,
            `session_expiry` DATETIME NOT NULL,
            `last_confirmed_at` DATETIME NULL DEFAULT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`session_id`),
            KEY `idx_user_id` (`user_id`),
            KEY `idx_session_expiry` (`session_expiry`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    }

    public function down(PDO $db): void {
        $db->exec("DROP TABLE IF EXISTS `sessions`;");
    }
}

return new Migration0034SessionTimeout();
