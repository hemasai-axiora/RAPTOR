<?php
// Migration 0065: Expand unsubscribe_token column in campaign_recipients to VARCHAR(500)

$driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);

if ($driver === 'sqlite') {
    // In SQLite, columns are dynamically typed and ALTER TABLE MODIFY is not supported.
    echo "    ~ SQLite detected; skipping MODIFY COLUMN for campaign_recipients.unsubscribe_token\n";
} else {
    try {
        $db->exec("ALTER TABLE campaign_recipients MODIFY COLUMN unsubscribe_token VARCHAR(500) NOT NULL");
        echo "    + campaign_recipients.unsubscribe_token expanded to VARCHAR(500)\n";
    } catch (Throwable $e) {
        echo "    ! campaign_recipients.unsubscribe_token modification notice: " . $e->getMessage() . "\n";
    }
}
