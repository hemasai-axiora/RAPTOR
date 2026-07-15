<?php
/**
 * Sprint 8 - Communications log, meetings/demos, check-ins, and attachments.
 */

$tableExists = function (PDO $db, string $table): bool {
    $stmt = $db->prepare(
        "SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t"
    );
    $stmt->execute([':t' => $table]);
    return (int) $stmt->fetchColumn() > 0;
};

if (!$tableExists($db, 'communications')) {
    $db->exec(
        "CREATE TABLE communications (
            communication_id BIGINT AUTO_INCREMENT PRIMARY KEY,
            lead_id INT NULL,
            user_id INT NOT NULL,
            channel ENUM('call','whatsapp','sms','email','social','other') NOT NULL DEFAULT 'call',
            direction ENUM('made','received','missed','sent') NOT NULL DEFAULT 'made',
            duration_seconds INT NOT NULL DEFAULT 0,
            outcome VARCHAR(150) NULL,
            note TEXT NULL,
            proof_url VARCHAR(255) NULL,
            happened_at DATETIME NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_comms_user_time (user_id, happened_at),
            INDEX idx_comms_lead_time (lead_id, happened_at),
            FOREIGN KEY (lead_id) REFERENCES leads(lead_id) ON DELETE SET NULL,
            FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    echo "    + communications table added\n";
}

if (!$tableExists($db, 'meetings')) {
    $db->exec(
        "CREATE TABLE meetings (
            meeting_id BIGINT AUTO_INCREMENT PRIMARY KEY,
            lead_id INT NULL,
            assigned_to_user_id INT NOT NULL,
            created_by_user_id INT NULL,
            type ENUM('meeting','demo') NOT NULL DEFAULT 'meeting',
            title VARCHAR(150) NOT NULL,
            scheduled_start DATETIME NOT NULL,
            scheduled_end DATETIME NULL,
            location VARCHAR(255) NULL,
            status ENUM('scheduled','checked_in','completed','cancelled') NOT NULL DEFAULT 'scheduled',
            outcome VARCHAR(150) NULL,
            client_feedback TEXT NULL,
            next_follow_up_at DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_meetings_owner_time (assigned_to_user_id, scheduled_start),
            INDEX idx_meetings_lead_time (lead_id, scheduled_start),
            FOREIGN KEY (lead_id) REFERENCES leads(lead_id) ON DELETE SET NULL,
            FOREIGN KEY (assigned_to_user_id) REFERENCES users(user_id) ON DELETE CASCADE,
            FOREIGN KEY (created_by_user_id) REFERENCES users(user_id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    echo "    + meetings table added\n";
}

if (!$tableExists($db, 'meeting_checkins')) {
    $db->exec(
        "CREATE TABLE meeting_checkins (
            checkin_id BIGINT AUTO_INCREMENT PRIMARY KEY,
            meeting_id BIGINT NOT NULL,
            user_id INT NOT NULL,
            type ENUM('in','out') NOT NULL DEFAULT 'in',
            lat DECIMAL(10,7) NULL,
            lng DECIMAL(10,7) NULL,
            accuracy_m INT NULL,
            selfie_url VARCHAR(255) NULL,
            checked_at DATETIME NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_checkins_meeting (meeting_id, type),
            FOREIGN KEY (meeting_id) REFERENCES meetings(meeting_id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    echo "    + meeting_checkins table added\n";
}

if (!$tableExists($db, 'attachments')) {
    $db->exec(
        "CREATE TABLE attachments (
            attachment_id BIGINT AUTO_INCREMENT PRIMARY KEY,
            entity_type VARCHAR(40) NOT NULL,
            entity_id BIGINT NOT NULL,
            uploaded_by_user_id INT NULL,
            storage_key VARCHAR(255) NOT NULL,
            original_name VARCHAR(255) NULL,
            mime_type VARCHAR(100) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_attachments_entity (entity_type, entity_id),
            FOREIGN KEY (uploaded_by_user_id) REFERENCES users(user_id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    echo "    + attachments table added\n";
}

echo "  [OK] communications and meetings schema ensured.\n";
