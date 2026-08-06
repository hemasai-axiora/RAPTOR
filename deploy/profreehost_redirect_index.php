<?php
// Auto-Redirect from raptor.unaux.com to RAPTOR Production Stack on AWS EC2
$request_uri = $_SERVER['REQUEST_URI'] ?? '/public/index.php';
if ($request_uri === '/' || $request_uri === '') {
    $request_uri = '/public/index.php';
}
header("Location: https://98.94.227.211" . $request_uri, true, 301);
exit();
