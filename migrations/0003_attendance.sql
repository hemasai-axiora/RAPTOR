-- ============================================================================
-- Sprint 2 — Attendance: check-in/out with selfie + GPS, breaks, consent.
-- ============================================================================

-- Daily attendance record: one row per user per day.
CREATE TABLE IF NOT EXISTS attendance (
    attendance_id   BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT NOT NULL,
    work_date       DATE NOT NULL,
    login_at        DATETIME NULL,
    logout_at       DATETIME NULL,
    login_lat       DECIMAL(10,7) NULL,
    login_lng       DECIMAL(10,7) NULL,
    logout_lat      DECIMAL(10,7) NULL,
    logout_lng      DECIMAL(10,7) NULL,
    login_accuracy_m  INT NULL,
    logout_accuracy_m INT NULL,
    login_selfie_url  VARCHAR(255) NULL COMMENT 'Storage key, served via file/show',
    logout_selfie_url VARCHAR(255) NULL,
    login_device    VARCHAR(255) NULL,
    login_ip        VARCHAR(45) NULL,
    logout_device   VARCHAR(255) NULL,
    logout_ip       VARCHAR(45) NULL,
    worked_minutes  INT NOT NULL DEFAULT 0,
    break_minutes   INT NOT NULL DEFAULT 0,
    is_late         BOOLEAN NOT NULL DEFAULT FALSE,
    is_early_logout BOOLEAN NOT NULL DEFAULT FALSE,
    geofence_ok     BOOLEAN NULL COMMENT 'Set in Sprint 3 geofence check',
    integrity_flag  ENUM('ok','suspect') DEFAULT 'ok',
    status          ENUM('present','half_day','absent','leave','holiday','wfh') DEFAULT 'present',
    approval_status ENUM('auto','pending','approved','rejected') DEFAULT 'auto',
    approved_by     INT NULL,
    remarks         VARCHAR(255) NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_day (user_id, work_date),
    INDEX idx_att_date (work_date),
    INDEX idx_att_user_date (user_id, work_date),
    FOREIGN KEY (user_id)     REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Break periods within a day (start/stop). Wired to UI in Sprint 3.
CREATE TABLE IF NOT EXISTS breaks (
    break_id      BIGINT AUTO_INCREMENT PRIMARY KEY,
    attendance_id BIGINT NOT NULL,
    start_at      DATETIME NOT NULL,
    end_at        DATETIME NULL,
    minutes       INT NULL,
    reason        VARCHAR(150) NULL,
    FOREIGN KEY (attendance_id) REFERENCES attendance(attendance_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Location tracking consent (working-hours-only). Latest row per user wins.
CREATE TABLE IF NOT EXISTS location_consents (
    consent_id     BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id        INT NOT NULL,
    consented      BOOLEAN NOT NULL DEFAULT TRUE,
    policy_version VARCHAR(20) NOT NULL DEFAULT 'v1',
    ip             VARCHAR(45) NULL,
    consented_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_consent_user (user_id, consented_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default shift configuration (admin-editable later via Settings).
INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
    ('attendance.shift_start',   '09:30'),
    ('attendance.shift_end',     '18:30'),
    ('attendance.grace_minutes', '15'),
    ('attendance.halfday_minutes','240'),
    ('attendance.policy_version','v1');
