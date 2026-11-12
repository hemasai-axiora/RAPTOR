<?php
// Migration 0066: Ensure track_opens and track_clicks columns exist in campaigns_broadcast

$driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);

if ($driver === 'sqlite') {
    // In SQLite tests, columns are either created in mock or dynamic
    try {
        $db->exec("ALTER TABLE campaigns_broadcast ADD COLUMN track_opens INTEGER DEFAULT 1");
    } catch (Throwable $e) {}
    try {
        $db->exec("ALTER TABLE campaigns_broadcast ADD COLUMN track_clicks INTEGER DEFAULT 1");
    } catch (Throwable $e) {}
} else {
    try {
        $check = $db->query("SHOW COLUMNS FROM campaigns_broadcast LIKE 'track_opens'")->fetch();
        if (!$check) {
            $db->exec("ALTER TABLE campaigns_broadcast ADD COLUMN track_opens TINYINT(1) NOT NULL DEFAULT 1 AFTER plain_text_content");
            echo "    + campaigns_broadcast.track_opens column added\n";
        }
    } catch (Throwable $e) {
        echo "    ! campaigns_broadcast.track_opens notice: " . $e->getMessage() . "\n";
    }

    try {
        $check = $db->query("SHOW COLUMNS FROM campaigns_broadcast LIKE 'track_clicks'")->fetch();
        if (!$check) {
            $db->exec("ALTER TABLE campaigns_broadcast ADD COLUMN track_clicks TINYINT(1) NOT NULL DEFAULT 1 AFTER track_opens");
            echo "    + campaigns_broadcast.track_clicks column added\n";
        }
    } catch (Throwable $e) {
        echo "    ! campaigns_broadcast.track_clicks notice: " . $e->getMessage() . "\n";
    }
}
