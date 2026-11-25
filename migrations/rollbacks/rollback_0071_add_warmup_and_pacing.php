<?php
/**
 * Rollback 0071: Revert Warmup Option & 120s Dispatch Pacing
 */

$driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);

if ($driver !== 'sqlite') {
    try {
        $check = $db->query("SHOW COLUMNS FROM campaigns_broadcast LIKE 'send_interval_seconds'")->fetch();
        if ($check) {
            $db->exec("ALTER TABLE campaigns_broadcast DROP COLUMN send_interval_seconds");
            echo "    - campaigns_broadcast.send_interval_seconds dropped\n";
        }
    } catch (Throwable $e) {}

    try {
        $check = $db->query("SHOW COLUMNS FROM campaigns_broadcast LIKE 'is_warmup_enabled'")->fetch();
        if ($check) {
            $db->exec("ALTER TABLE campaigns_broadcast DROP COLUMN is_warmup_enabled");
            echo "    - campaigns_broadcast.is_warmup_enabled dropped\n";
        }
    } catch (Throwable $e) {}
}

try {
    $db->exec("DELETE FROM settings WHERE setting_key IN ('mail.marketing.pacing_seconds', 'mail.warmup_enabled')");
} catch (Throwable $e) {}
