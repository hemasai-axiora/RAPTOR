<?php
require_once dirname(dirname(__FILE__)) . '/app/config/config.php';
require_once dirname(dirname(__FILE__)) . '/app/core/Database.php';
require_once dirname(dirname(__FILE__)) . '/app/core/Controller.php';
require_once dirname(dirname(__FILE__)) . '/app/controllers/ApiController.php';

// Mock session
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['user_role'] = 'admin';

// Instantiate ApiController and mock GET parameters
$_GET['client_id'] = 'all';
$_GET['start_date'] = '';
$_GET['end_date'] = '';

try {
    $api = new ApiController();
    
    // We want to capture output from executive()
    ob_start();
    $api->executive();
    $output = ob_get_clean();
    
    echo "API Executive Output:\n";
    echo $output . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
