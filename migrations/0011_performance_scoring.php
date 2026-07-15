<?php
/**
 * Sprint 10 - Performance scoring, manager reviews, and configurable weights.
 */

$tableExists = function (PDO $db, string $table): bool {
    $stmt = $db->prepare(
        "SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t"
    );
    $stmt->execute([':t' => $table]);
    return (int) $stmt->fetchColumn() > 0;
};

if (!$tableExists($db, 'scoring_weights')) {
    $db->exec(
        "CREATE TABLE scoring_weights (
            weight_key VARCHAR(50) PRIMARY KEY,
            label VARCHAR(100) NOT NULL,
            weight_percent DECIMAL(5,2) NOT NULL DEFAULT 0.00,
            active BOOLEAN NOT NULL DEFAULT TRUE,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    echo "    + scoring_weights table added\n";
}

if (!$tableExists($db, 'performance_scores')) {
    $db->exec(
        "CREATE TABLE performance_scores (
            score_id BIGINT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            period ENUM('weekly','monthly') NOT NULL DEFAULT 'weekly',
            start_date DATE NOT NULL,
            end_date DATE NOT NULL,
            attendance_score DECIMAL(6,2) NOT NULL DEFAULT 0.00,
            punctuality_score DECIMAL(6,2) NOT NULL DEFAULT 0.00,
            activity_score DECIMAL(6,2) NOT NULL DEFAULT 0.00,
            target_score DECIMAL(6,2) NOT NULL DEFAULT 0.00,
            lead_score DECIMAL(6,2) NOT NULL DEFAULT 0.00,
            followup_score DECIMAL(6,2) NOT NULL DEFAULT 0.00,
            conversion_score DECIMAL(6,2) NOT NULL DEFAULT 0.00,
            revenue_score DECIMAL(6,2) NOT NULL DEFAULT 0.00,
            meeting_score DECIMAL(6,2) NOT NULL DEFAULT 0.00,
            demo_score DECIMAL(6,2) NOT NULL DEFAULT 0.00,
            overall_score DECIMAL(6,2) NOT NULL DEFAULT 0.00,
            performance_band ENUM('excellent','good','average','needs_attention') NOT NULL DEFAULT 'average',
            team_rank INT NULL,
            computed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_perf_user_period (user_id, period, start_date, end_date),
            INDEX idx_perf_period_score (period, start_date, end_date, overall_score),
            FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    echo "    + performance_scores table added\n";
}

if (!$tableExists($db, 'manager_reviews')) {
    $db->exec(
        "CREATE TABLE manager_reviews (
            review_id BIGINT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            reviewer_user_id INT NOT NULL,
            period ENUM('weekly','monthly') NOT NULL DEFAULT 'weekly',
            start_date DATE NOT NULL,
            end_date DATE NOT NULL,
            rating TINYINT NULL,
            remarks TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_mgr_reviews_user_period (user_id, period, start_date, end_date),
            FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
            FOREIGN KEY (reviewer_user_id) REFERENCES users(user_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    echo "    + manager_reviews table added\n";
}

$db->exec(
    "INSERT INTO scoring_weights (weight_key, label, weight_percent) VALUES
        ('attendance', 'Attendance', 10.00),
        ('punctuality', 'Punctuality', 8.00),
        ('activity', 'Activity Volume', 12.00),
        ('target', 'Target Achievement', 18.00),
        ('lead', 'Lead Generation', 10.00),
        ('followup', 'Follow-up Discipline', 10.00),
        ('conversion', 'Conversions', 10.00),
        ('revenue', 'Revenue', 10.00),
        ('meeting', 'Meetings', 6.00),
        ('demo', 'Demos', 6.00)
     ON DUPLICATE KEY UPDATE label = VALUES(label)"
);

echo "  [OK] performance scoring schema ensured.\n";
