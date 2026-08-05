<?php
// ProFreeHost / Unaux environment configuration override
// This file is loaded FIRST before config.php
// It sets the correct MySQL credentials for the raptor.unaux.com hosting environment

// Common ProFreeHost MySQL hostnames
$possibleHosts = [
    'sql200.ezyro.com',
    'sql201.ezyro.com',
    'sql202.ezyro.com',
    'sql203.ezyro.com',
    'sql204.ezyro.com',
    'sql205.ezyro.com',
    'sql206.ezyro.com',
    'sql207.ezyro.com',
    'sql208.ezyro.com',
    'sql209.ezyro.com',
    'sql210.ezyro.com',
    'sql211.ezyro.com',
    'sql212.ezyro.com',
    'sql213.ezyro.com',
    'sql214.ezyro.com',
    'sql215.ezyro.com',
    'sql216.ezyro.com',
    'localhost',
    '127.0.0.1'
];

// Try to find the real MySQL host by testing connections
$foundHost = null;
foreach ($possibleHosts as $h) {
    $conn = @fsockopen($h, 3306, $errno, $errstr, 1);
    if ($conn) {
        fclose($conn);
        $foundHost = $h;
        break;
    }
}

// Write results to a temp file for diagnosis
$result = "DB Host Scan Results:\n";
foreach ($possibleHosts as $h) {
    $conn = @fsockopen($h, 3306, $errno, $errstr, 1);
    if ($conn) {
        fclose($conn);
        $result .= "[OPEN] $h\n";
    } else {
        $result .= "[CLOSED] $h\n";
    }
}

header('Content-Type: text/plain');
echo $result;
echo "\nBest Host: " . ($foundHost ?? 'none found');
echo "\n\nPHP Version: " . phpversion();
echo "\nServer: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'unknown');
echo "\nDocument Root: " . ($_SERVER['DOCUMENT_ROOT'] ?? 'unknown');
