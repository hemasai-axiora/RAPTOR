<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

set_error_handler(function($errno, $errstr, $errfile, $errline) {
    echo "ERROR [$errno] $errstr in $errfile on line $errline\n";
});

set_exception_handler(function($e) {
    echo "EXCEPTION: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine() . "\n";
    echo $e->getTraceAsString() . "\n";
});

register_shutdown_function(function() {
    $err = error_get_last();
    if ($err) {
        echo "SHUTDOWN ERROR: {$err['message']} in {$err['file']} on line {$err['line']}\n";
    }
});

require_once __DIR__ . '/clean_and_seed.php';
