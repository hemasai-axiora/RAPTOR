<?php
// One-click Auto-Updater for Raptor CRM on Unaux / Remote Hosting
error_reporting(E_ALL);
ini_set('display_errors', '1');

echo "<h2>Raptor CRM Live Auto-Updater</h2>";

$zipUrl = "https://github.com/hemasai-axiora/RAPTOR/archive/refs/heads/main.zip";
$tempZip = __DIR__ . '/temp_update.zip';

echo "<p>1. Downloading latest release from GitHub main branch...</p>";

$ch = curl_init($zipUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
$zipData = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200 || empty($zipData)) {
    die("<p style='color:red;'>Failed to download update zip from GitHub (HTTP $httpCode).</p>");
}

file_put_contents($tempZip, $zipData);
echo "<p>2. Downloaded zip size: " . strlen($zipData) . " bytes.</p>";

$zip = new ZipArchive();
if ($zip->open($tempZip) === TRUE) {
    $targetDir = dirname(__DIR__); // Root folder
    echo "<p>3. Extracting files to $targetDir...</p>";
    
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $filename = $zip->getNameIndex($i);
        // Strip the leading folder name "RAPTOR-main/"
        $relativePath = preg_replace('#^[^/]+/#', '', $filename);
        if (empty($relativePath)) continue;
        
        $destPath = $targetDir . '/' . $relativePath;
        if (substr($filename, -1) === '/') {
            if (!is_dir($destPath)) {
                mkdir($destPath, 0777, true);
            }
        } else {
            $destDir = dirname($destPath);
            if (!is_dir($destDir)) {
                mkdir($destDir, 0777, true);
            }
            copy("zip://" . $tempZip . "#" . $filename, $destPath);
        }
    }
    $zip->close();
    @unlink($tempZip);
    echo "<p style='color:green;font-weight:bold;'>4. Files extracted successfully!</p>";
} else {
    die("<p style='color:red;'>Failed to open zip archive. Make sure PHP ZipArchive extension is enabled.</p>");
}

// 5. Run Migration 0041
echo "<p>5. Running database migration 0041...</p>";
$migrationFile = __DIR__ . '/../migrations/0041_fix_employee_crm_permissions.php';
if (file_exists($migrationFile)) {
    require_once __DIR__ . '/../app/config/config.php';
    require_once __DIR__ . '/../app/core/Database.php';
    include $migrationFile;
    echo "<p style='color:green;'>Migration 0041 executed successfully!</p>";
}

echo "<h3>✅ Auto-Update Complete! You can now access your application.</h3>";
echo "<a href='index.php?route=auth/logout' style='font-size:1.2rem;font-weight:bold;'>Click here to Log In to Raptor CRM</a>";
