<?php
// Migration 0067: Expand campaigns_broadcast.status ENUM to include 'approved'

$driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);

if ($driver === 'sqlite') {
    // SQLite doesn't enforce strict enum typing
} else {
    try {
        $check = $db->query("SHOW COLUMNS FROM campaigns_broadcast LIKE 'status'")->fetch();
        $typeStr = is_object($check) ? ($check->Type ?? '') : ($check['Type'] ?? '');
        if ($check && strpos($typeStr, "'approved'") === false) {
            $db->exec("ALTER TABLE campaigns_broadcast MODIFY COLUMN status ENUM('draft','approved','scheduled','queued','sending','paused','completed','cancelled') NOT NULL DEFAULT 'draft'");
            echo "    + campaigns_broadcast.status enum expanded to include 'approved'\n";
        }
    } catch (Throwable $e) {
        echo "    ! campaigns_broadcast.status enum notice: " . $e->getMessage() . "\n";
    }
}
