<?php
// Session Logout API Endpoint
require_once dirname(dirname(__DIR__)) . '/app/config/config.php';

$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => $https,
    'httponly' => true,
    'samesite' => 'Lax',
]);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

$rawTrigger = $_POST['trigger'] ?? $_GET['trigger'] ?? 'user_logout';
$isTimeout  = ($rawTrigger === 'timeout' || $rawTrigger === 'session_timeout');
$reason     = $isTimeout ? 'session_timeout' : 'user_logout';
$auditType  = $isTimeout ? 'SESSION_LOGOUT_TIMEOUT' : 'SESSION_LOGOUT_USER';

// If user is initiating manual logout via POST, verify CSRF token
if (!$isTimeout && isset($_SESSION['user_id']) && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '';
    if (empty($_SESSION['csrf_token']) || empty($csrf) || !hash_equals((string)$_SESSION['csrf_token'], (string)$csrf)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Security Error: CSRF token validation failed.']);
        exit();
    }
}

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
