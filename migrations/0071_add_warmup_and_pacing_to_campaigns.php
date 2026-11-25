<?php
/**
 * Migration 0071: Add Email Warmup Option & 120s Dispatch Pacing Controls
 * 
 * 1. campaigns_broadcast:
 *    - is_warmup_enabled: TINYINT(1) DEFAULT 1 (enforces daily domain ramp-up caps)
 *    - send_interval_seconds: INT DEFAULT 120 (enforces minimum 120s gap between consecutive emails)
 * 2. settings table:
 *    - mail.marketing.pacing_seconds: 120
 *    - mail.warmup_enabled: 1
 */

$driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);

if ($driver === 'sqlite') {
    try {
        $db->exec("ALTER TABLE campaigns_broadcast ADD COLUMN is_warmup_enabled INTEGER DEFAULT 1");
    } catch (Throwable $e) {}
    try {
        $db->exec("ALTER TABLE campaigns_broadcast ADD COLUMN send_interval_seconds INTEGER DEFAULT 120");
    } catch (Throwable $e) {}
} else {
    try {
        $check = $db->query("SHOW COLUMNS FROM campaigns_broadcast LIKE 'is_warmup_enabled'")->fetch();
        if (!$check) {
            $db->exec("ALTER TABLE campaigns_broadcast ADD COLUMN is_warmup_enabled TINYINT(1) NOT NULL DEFAULT 1 AFTER track_clicks");
            echo "    + campaigns_broadcast.is_warmup_enabled column added\n";
        }
    } catch (Throwable $e) {
        echo "    ! campaigns_broadcast.is_warmup_enabled notice: " . $e->getMessage() . "\n";
    }

    try {
        $check = $db->query("SHOW COLUMNS FROM campaigns_broadcast LIKE 'send_interval_seconds'")->fetch();
        if (!$check) {
            $db->exec("ALTER TABLE campaigns_broadcast ADD COLUMN send_interval_seconds INT NOT NULL DEFAULT 120 AFTER is_warmup_enabled");
            echo "    + campaigns_broadcast.send_interval_seconds column added\n";
        }
    } catch (Throwable $e) {
        echo "    ! campaigns_broadcast.send_interval_seconds notice: " . $e->getMessage() . "\n";
    }
}

// Seed or update default settings
try {
    $ins = $db->prepare("INSERT INTO settings (setting_key, setting_value, created_at, updated_at) 
                         VALUES (:k, :v, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
                         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    $ins->execute([':k' => 'mail.marketing.pacing_seconds', ':v' => '120']);
    $ins->execute([':k' => 'mail.warmup_enabled', ':v' => '1']);
} catch (Throwable $e) {
    // SQLite or alternative fallback
    try {
        $db->exec("INSERT OR IGNORE INTO settings (setting_key, setting_value) VALUES ('mail.marketing.pacing_seconds', '120')");
        $db->exec("INSERT OR IGNORE INTO settings (setting_key, setting_value) VALUES ('mail.warmup_enabled', '1')");
    } catch (Throwable $e2) {}
}
