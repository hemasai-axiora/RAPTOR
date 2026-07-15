<?php
/**
 * Sprint 13 cron: deliver pending notifications to saved Web Push subscriptions.
 *
 * If Minishlink\WebPush is installed through Composer, this sends real Web Push
 * messages. Without that library it marks pending pushes as skipped so the cron
 * remains safe on the current dependency-free install.
 *
 * Usage:
 *   php bin/cron_push_notifications.php
 */

require_once dirname(__DIR__) . '/app/config/config.php';
require_once APPROOT . '/core/Database.php';

$vendor = dirname(__DIR__) . '/vendor/autoload.php';
if (is_file($vendor)) {
    require_once $vendor;
}

$db = Database::getInstance()->getConnection();
$enabled = $db->query("SELECT setting_value FROM settings WHERE setting_key = 'alerts.web_push_enabled'")->fetchColumn();
if ((string) $enabled !== '1') {
    echo "[SKIP] alerts.web_push_enabled is off\n";
    exit(0);
}

if (!class_exists('\\Minishlink\\WebPush\\WebPush') || !class_exists('\\Minishlink\\WebPush\\Subscription')) {
    $db->exec("UPDATE notifications SET push_status = 'skipped' WHERE push_status = 'pending'");
    echo "[SKIP] Web Push library not installed; pending notifications marked skipped\n";
    exit(0);
}

$settings = [];
$stmt = $db->query("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'alerts.%'");
foreach ($stmt->fetchAll(PDO::FETCH_OBJ) as $row) {
    $settings[$row->setting_key] = $row->setting_value;
}

$auth = [
    'VAPID' => [
        'subject' => !empty($settings['alerts.email_from']) ? 'mailto:' . $settings['alerts.email_from'] : 'mailto:admin@example.com',
        'publicKey' => $settings['alerts.vapid_public_key'] ?? '',
        'privateKey' => $settings['alerts.vapid_private_key'] ?? '',
    ],
];

$webPush = new \Minishlink\WebPush\WebPush($auth);
$stmt = $db->query("SELECT n.*, ps.endpoint, ps.p256dh_key, ps.auth_key
                    FROM notifications n
                    JOIN push_subscriptions ps ON n.user_id = ps.user_id AND ps.active = 1
                    WHERE n.push_status = 'pending'
                    ORDER BY n.created_at ASC
                    LIMIT 100");
$rows = $stmt->fetchAll(PDO::FETCH_OBJ);

foreach ($rows as $row) {
    $subscription = \Minishlink\WebPush\Subscription::create([
        'endpoint' => $row->endpoint,
        'publicKey' => $row->p256dh_key,
        'authToken' => $row->auth_key,
    ]);
    $payload = json_encode([
        'title' => $row->title,
        'body' => $row->message,
        'url' => $row->action_url ?: 'index.php?route=notifications/index',
    ]);
    $webPush->queueNotification($subscription, $payload);
}

$sent = 0;
$failed = 0;
foreach ($webPush->flush() as $report) {
    $ok = $report->isSuccess();
    $endpoint = $report->getRequest()->getUri()->__toString();
    $update = $db->prepare("UPDATE notifications n
                            JOIN push_subscriptions ps ON n.user_id = ps.user_id
                            SET n.push_status = :status, ps.active = IF(:active = 1, ps.active, 0)
                            WHERE ps.endpoint = :endpoint AND n.push_status = 'pending'");
    $update->execute([
        ':status' => $ok ? 'sent' : 'failed',
        ':active' => $ok ? 1 : 0,
        ':endpoint' => $endpoint,
    ]);
    $ok ? $sent++ : $failed++;
}

echo "[OK] push sent={$sent} failed={$failed}\n";
