<?php
// Session Status API Endpoint
require_once dirname(dirname(__DIR__)) . '/app/config/config.php';

session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'is_expired' => false,
        'show_popup' => false,
        'remaining_seconds' => 0
    ]);
    exit();
}

if (empty($_SESSION['session_expiry'])) {
    $_SESSION['login_time'] = date('Y-m-d H:i:s');
    $_SESSION['session_expiry'] = date('Y-m-d H:i:s', time() + (10 * 3600));
}

$nowTs = time();
$expiryTs = strtotime($_SESSION['session_expiry']);
$remainingSec = $expiryTs - $nowTs;
$showPopup = ($remainingSec <= 0 && $remainingSec >= -300);

echo json_encode([
    'success' => true,
    'session_expiry' => $_SESSION['session_expiry'],
    'server_now' => date('Y-m-d H:i:s', $nowTs),
    'remaining_seconds' => $remainingSec,
    'popup_grace_seconds' => 300,
    'show_popup' => $showPopup,
    'is_expired' => ($remainingSec < -300)
]);
exit();
