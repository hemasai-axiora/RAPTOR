<?php
// Session Extend API Endpoint
require_once dirname(dirname(__DIR__)) . '/app/config/config.php';

session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$nowTs = time();
$nowStr = date('Y-m-d H:i:s', $nowTs);
$newExpiryStr = date('Y-m-d H:i:s', $nowTs + (2 * 3600));

$_SESSION['session_expiry'] = $newExpiryStr;
$_SESSION['last_activity']  = $nowTs;

try {
    $sid = session_id();
    $db  = Database::getInstance()->getConnection();
    $stmt = $db->prepare('UPDATE sessions SET session_expiry = :expiry, last_confirmed_at = :now, updated_at = NOW() WHERE session_id = :sid AND user_id = :uid');
    $stmt->execute([
        ':expiry' => $newExpiryStr,
        ':now'    => $nowStr,
        ':sid'    => $sid,
        ':uid'    => (int) $_SESSION['user_id']
    ]);

    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $auditStmt = $db->prepare('INSERT INTO activity_logs (user_id, action, ip_address, user_agent) VALUES (:uid, "SESSION_EXTENDED", :ip, :ua)');
    $auditStmt->execute([':uid' => (int) $_SESSION['user_id'], ':ip' => $ip, ':ua' => $ua]);
} catch (Exception $e) {
    // Fail safe
}

echo json_encode([
    'success' => true,
    'message' => 'Session extended by 2 hours.',
    'session_expiry' => $newExpiryStr,
    'remaining_seconds' => 7200
]);
exit();
