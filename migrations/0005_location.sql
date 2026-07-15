-- ============================================================================
-- Sprint 4 — Location capture & travel summary.
-- Location is only stored while a user is ON DUTY (open attendance session),
-- which enforces the working-hours-only privacy rule at the data layer.
-- ============================================================================

CREATE TABLE IF NOT EXISTS location_logs (
    loc_id      BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NOT NULL,
    captured_at DATETIME NOT NULL,
    lat         DECIMAL(10,7) NOT NULL,
    lng         DECIMAL(10,7) NOT NULL,
    accuracy_m  INT NULL,
    source      ENUM('periodic','checkin','manual','meeting') NOT NULL DEFAULT 'periodic',
    battery_pct TINYINT NULL,
    is_moving   BOOLEAN NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_loc_user_time (user_id, captured_at),
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One rolled-up row per user per day (populated nightly by cron_travel_rollup).
CREATE TABLE IF NOT EXISTS travel_summary (
    summary_id     BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id        INT NOT NULL,
    work_date      DATE NOT NULL,
    distance_km    DECIMAL(8,2) NOT NULL DEFAULT 0.00,
    points_count   INT NOT NULL DEFAULT 0,
    first_at       DATETIME NULL,
    last_at        DATETIME NULL,
    route_polyline MEDIUMTEXT NULL COMMENT 'JSON array of [lat,lng] for the day',
    computed_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_travel_user_day (user_id, work_date),
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
    ('location.tracking_enabled',      '1'),
    ('location.ping_interval_seconds', '120'),
    ('location.max_accuracy_m',        '150'),
    ('location.retention_days',        '90');
