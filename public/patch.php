<?php
// Standalone Live Patch Script for Raptor CRM on Unaux
error_reporting(E_ALL);
ini_set('display_errors', '1');

echo "<div style='font-family:sans-serif;padding:20px;background:#111;color:#fff;'>";
echo "<h2>Raptor CRM Live Patch Installer</h2>";

$baseUrl = "https://raw.githubusercontent.com/hemasai-axiora/RAPTOR/main/";
$targetRoot = dirname(__DIR__); // Root folder (/htdocs)

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

$successCount = 0;

foreach ($filesToPatch as $relPath) {
    $remoteUrl = $baseUrl . $relPath;
    $localPath = $targetRoot . '/' . $relPath;
    
    echo "<p>Patching <code>$relPath</code>...</p>";
    
    $ch = curl_init($remoteUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
    $code = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200 && !empty($code)) {
        $dir = dirname($localPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        file_put_contents($localPath, $code);
        echo "<p style='color:#2ec4b6;'>✔ Successfully updated $relPath</p>";
        $successCount++;
    } else {
        echo "<p style='color:#e63946;'>❌ Failed to fetch $relPath (HTTP $httpCode)</p>";
    }
}

// Execute Migration 0041
echo "<p>Executing Migration 0041...</p>";
$migrationFile = $targetRoot . '/migrations/0041_fix_employee_crm_permissions.php';
if (file_exists($migrationFile)) {
    try {
        require_once $targetRoot . '/app/config/config.php';
        require_once $targetRoot . '/app/core/Database.php';
        include $migrationFile;
        echo "<p style='color:#2ec4b6;'>✔ Migration 0041 executed successfully!</p>";
    } catch (Throwable $e) {
        echo "<p style='color:orange;'>Migration notice: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
}

echo "<h3>✅ Patched $successCount files successfully!</h3>";
echo "<p><a href='index.php?route=auth/logout' style='color:#0d6efd;font-weight:bold;font-size:1.2rem;'>Click here to Log In to Raptor CRM</a></p>";
echo "</div>";
