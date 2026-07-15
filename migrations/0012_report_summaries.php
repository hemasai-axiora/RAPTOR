<?php
/**
 * Sprint 12 - Generated report summaries and digest metadata.
 */

$tableExists = function (PDO $db, string $table): bool {
    $stmt = $db->prepare(
        "SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t"
    );
    $stmt->execute([':t' => $table]);
    return (int) $stmt->fetchColumn() > 0;
};

if (!$tableExists($db, 'report_summaries')) {
    $db->exec(
        "CREATE TABLE report_summaries (
            summary_id BIGINT AUTO_INCREMENT PRIMARY KEY,
            report_key VARCHAR(60) NOT NULL,
            period ENUM('daily','weekly','monthly') NOT NULL DEFAULT 'daily',
            start_date DATE NOT NULL,
            end_date DATE NOT NULL,
            generated_by_user_id INT NULL,
            storage_key VARCHAR(255) NULL,
            summary_json JSON NULL,
            emailed_at DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_report_summary_period (report_key, period, start_date, end_date),
            INDEX idx_report_summary_period (period, start_date, end_date),
            FOREIGN KEY (generated_by_user_id) REFERENCES users(user_id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    echo "    + report_summaries table added\n";
}

$db->exec(
    "INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
        ('reports.email_enabled', '0'),
        ('reports.digest_recipients', '')"
);

echo "  [OK] report summaries schema ensured.\n";
