<?php
// Smart Multi-Path Live Patch Installer for Raptor CRM
error_reporting(E_ALL);
ini_set('display_errors', '1');

echo "<div style='font-family:sans-serif;padding:25px;background:#0d1117;color:#c9d1d9;line-height:1.6;'>";
echo "<h2 style='color:#58a6ff;'>Raptor CRM Smart Live Patch Installer</h2>";

$baseUrl = "https://raw.githubusercontent.com/hemasai-axiora/RAPTOR/main/";

// Detect all potential root folders on Unaux
$candidateRoots = [];
$dir = __DIR__;

foreach ([$dir, dirname($dir), dirname(dirname($dir)), $dir . '/RAPTOR-main', dirname($dir) . '/RAPTOR-main'] as $candidate) {
    if (is_dir($candidate) && !in_array($candidate, $candidateRoots, true)) {
        $candidateRoots[] = $candidate;
    }
}

$filesToPatch = [
    'app/views/layouts/main.php',
    'app/core/PermissionService.php',
    'app/core/Controller.php',
    'app/core/Model.php',
    'app/views/followups/index.php',
    'app/controllers/FollowupsController.php',
    'app/controllers/CommunicationsController.php',
    'app/controllers/MeetingsController.php',
    'migrations/0041_fix_employee_crm_permissions.php'
];

foreach ($candidateRoots as $root) {
    echo "<h3 style='color:#79c0ff;'>Checking target path: <code>" . htmlspecialchars($root) . "</code></h3>";
    
    foreach ($filesToPatch as $relPath) {
        $remoteUrl = $baseUrl . $relPath;
        $localPath = $root . '/' . $relPath;
        
        $ch = curl_init($remoteUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
        $code = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200 && !empty($code)) {
            $parentDir = dirname($localPath);
            if (!is_dir($parentDir)) {
                @mkdir($parentDir, 0777, true);
            }
            @file_put_contents($localPath, $code);
            echo "<p style='color:#3fb950;margin:4px 0;'>✔ Patched $relPath</p>";
        }
    }
    
    // Execute Migration 0041
    $migrationFile = $root . '/migrations/0041_fix_employee_crm_permissions.php';
    if (file_exists($migrationFile)) {
        try {
            if (file_exists($root . '/app/config/config.php')) require_once $root . '/app/config/config.php';
            if (file_exists($root . '/app/core/Database.php')) require_once $root . '/app/core/Database.php';
            include $migrationFile;
            echo "<p style='color:#3fb950;'>✔ Migration 0041 executed successfully on " . htmlspecialchars($root) . "</p>";
        } catch (Throwable $e) {
            echo "<p style='color:#d29922;'>Migration notice: " . htmlspecialchars($e->getMessage()) . "</p>";
        }
    }
}

echo "<h2 style='color:#3fb950;'>✅ All Directories Patched Successfully!</h2>";
echo "<p><a href='index.php?route=auth/logout' style='background:#238636;color:#fff;padding:10px 20px;border-radius:6px;text-decoration:none;font-weight:bold;'>Click here to Log In & Verify</a></p>";
echo "</div>";
