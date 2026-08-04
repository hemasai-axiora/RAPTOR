<?php
// Session Logout API Endpoint
require_once dirname(dirname(__DIR__)) . '/app/config/config.php';

session_start();

header('Content-Type: application/json');

$rawTrigger = $_POST['trigger'] ?? $_GET['trigger'] ?? 'user_logout';
$isTimeout  = ($rawTrigger === 'timeout' || $rawTrigger === 'session_timeout');
$reason     = $isTimeout ? 'session_timeout' : 'user_logout';
$auditType  = $isTimeout ? 'SESSION_LOGOUT_TIMEOUT' : 'SESSION_LOGOUT_USER';

if (isset($_SESSION['user_id'])) {
    try {
        $sid = session_id();
        $db  = Database::getInstance()->getConnection();
        $stmt = $db->prepare('DELETE FROM sessions WHERE session_id = :sid OR user_id = :uid');
        $stmt->execute([':sid' => $sid, ':uid' => (int) $_SESSION['user_id']]);

        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $auditStmt = $db->prepare('INSERT INTO activity_logs (user_id, action, ip_address, user_agent) VALUES (:uid, :act, :ip, :ua)');
        $auditStmt->execute([':uid' => (int) $_SESSION['user_id'], ':act' => $auditType, ':ip' => $ip, ':ua' => $ua]);
    } catch (Exception $e) {
        // Fail safe
    }
}

$_SESSION = array();
session_destroy();

echo json_encode([
    'success'  => true,
    'redirect' => 'index.php?route=auth/login&reason=' . urlencode($reason)
]);
exit();
