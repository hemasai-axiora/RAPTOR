-- ============================================================================
-- Sprint 3 — Geofences for attendance/location verification.
-- ============================================================================

CREATE TABLE IF NOT EXISTS geofences (
    geofence_id INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(150) NOT NULL,
    type        ENUM('office','client','territory') NOT NULL DEFAULT 'office',
    ref_id      INT NULL COMMENT 'Optional link to branch/client/territory id',
    center_lat  DECIMAL(10,7) NOT NULL,
    center_lng  DECIMAL(10,7) NOT NULL,
    radius_m    INT NOT NULL DEFAULT 200,
    active      BOOLEAN NOT NULL DEFAULT TRUE,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_geofence_type_active (type, active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Geofencing is OPT-IN: disabled until an admin enables it and defines fences,
-- so early check-ins are never blocked by an empty geofence set.
INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
    ('attendance.geofence_enabled', '0'),
    ('attendance.default_radius_m', '200');
