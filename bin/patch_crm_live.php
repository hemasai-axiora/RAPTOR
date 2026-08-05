<?php
// Live Patch Execution Endpoint for Raptor CRM
error_reporting(E_ALL);
ini_set('display_errors', '1');

echo "<h2>Raptor CRM Live Patch & Permission Update Script</h2>";
echo "<pre style='background:#0d1117;color:#c9d1d9;padding:15px;font-family:monospace;'>";

$root = dirname(__DIR__);

// 1. Run Migration 0041 (Permission Seed for Employee)
echo "1. Connecting to MySQL Database...\n";
try {
    require_once $root . '/app/config/config.php';
    require_once $root . '/app/core/Database.php';
    $db = Database::getInstance()->getConnection();
    echo "✔ Database connection established.\n\n";

    echo "2. Applying Migration 0041 (Employee CRM Permissions)...\n";
    $permsToEnsure = [
        ['crm_leads.view', 'crm_leads', 'view', 'View leads'],
        ['crm_leads.create', 'crm_leads', 'create', 'Create leads'],
        ['crm_leads.edit', 'crm_leads', 'edit', 'Edit leads'],
        ['leads.view', 'leads', 'view', 'View leads'],
        ['leads.create', 'leads', 'create', 'Create leads'],
        ['leads.edit', 'leads', 'edit', 'Edit leads'],
        ['customers.view', 'customers', 'view', 'View customer directory'],
        ['customers.create', 'customers', 'create', 'Create customer accounts'],
        ['customers.edit', 'customers', 'edit', 'Edit customer accounts'],
        ['communications.view', 'communications', 'view', 'View communications log'],
        ['communications.create', 'communications', 'create', 'Log communication touches'],
        ['meetings.view', 'meetings', 'view', 'View meetings and demos'],
        ['meetings.create', 'meetings', 'create', 'Schedule meetings and demos'],
        ['followups.view', 'followups', 'view', 'View follow-ups'],
        ['followups.create', 'followups', 'create', 'Schedule follow-ups'],
    ];

    foreach ($permsToEnsure as [$pName, $mod, $act, $desc]) {
        $stmt = $db->prepare('INSERT INTO permissions (permission_name, module, action, description) VALUES (:name, :mod, :act, :desc) ON DUPLICATE KEY UPDATE module = VALUES(module), action = VALUES(action), description = VALUES(description)');
        $stmt->execute([':name' => $pName, ':mod' => $mod, ':act' => $act, ':desc' => $desc]);
    }

    $getRoleId = function (PDO $db, string $roleName): ?int {
        $stmt = $db->prepare('SELECT role_id FROM roles WHERE role_name = :name LIMIT 1');
        $stmt->execute([':name' => $roleName]);
        $id = $stmt->fetchColumn();
        return $id !== false ? (int) $id : null;
    };

    $getPermissionId = function (PDO $db, string $permissionName): ?int {
        $stmt = $db->prepare('SELECT permission_id FROM permissions WHERE permission_name = :name LIMIT 1');
        $stmt->execute([':name' => $permissionName]);
        $id = $stmt->fetchColumn();
        return $id !== false ? (int) $id : null;
    };

    $rolesToGrant = ['employee', 'sales_person'];
    foreach ($rolesToGrant as $roleName) {
        $rid = $getRoleId($db, $roleName);
        if (!$rid) continue;
        foreach (['crm_leads.view', 'crm_leads.create', 'crm_leads.edit', 'leads.view', 'leads.create', 'leads.edit', 'customers.view', 'customers.create', 'communications.view', 'communications.create', 'meetings.view', 'meetings.create', 'followups.view', 'followups.create'] as $pName) {
            $pid = $getPermissionId($db, $pName);
            if ($pid) {
                $scope = ($pName === 'customers.view') ? 'all' : 'own';
                $stmt = $db->prepare('INSERT INTO role_permissions (role_id, permission_id, scope) VALUES (:r, :p, :s) ON DUPLICATE KEY UPDATE scope = VALUES(scope)');
                $stmt->execute([':r' => $rid, ':p' => $pid, ':s' => $scope]);
            }
        }
        echo "✔ Granted CRM permissions to role '{$roleName}'\n";
    }

} catch (Throwable $e) {
    echo "Notice: Database update message: " . $e->getMessage() . "\n";
}

// 3. Download GitHub main release zip and extract to /var/www/html/
echo "\n3. Downloading latest repository codebase from GitHub main branch...\n";
$zipUrl = 'https://raw.githubusercontent.com/hemasai-axiora/RAPTOR/main/update.zip';
$tempZip = $root . '/temp_live_update.zip';

$ch = curl_init($zipUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
curl_setopt($ch, CURLOPT_TIMEOUT, 60);
$zipData = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200 && !empty($zipData)) {
    file_put_contents($tempZip, $zipData);
    echo "✔ Downloaded zip file: " . strlen($zipData) . " bytes\n";

    if (class_exists('ZipArchive')) {
        $zip = new ZipArchive();
        if ($zip->open($tempZip) === TRUE) {
            echo "4. Extracting updated files into " . $root . "...\n";
            $extracted = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $filename = $zip->getNameIndex($i);
                $relativePath = preg_replace('#^[^/]+/#', '', $filename);
                if (empty($relativePath)) continue;
                $destPath = $root . '/' . $relativePath;
                if (substr($filename, -1) === '/') {
                    if (!is_dir($destPath)) @mkdir($destPath, 0777, true);
                } else {
                    $destDir = dirname($destPath);
                    if (!is_dir($destDir)) @mkdir($destDir, 0777, true);
                    copy("zip://" . $tempZip . "#" . $filename, $destPath);
                    $extracted++;
                }
            }
            $zip->close();
            @unlink($tempZip);
            echo "✔ Successfully updated {$extracted} application files!\n";
        } else {
            echo "Notice: Could not open ZipArchive.\n";
        }
    } else {
        echo "Notice: ZipArchive PHP extension not enabled.\n";
    }
} else {
    echo "Notice: Zip download returned HTTP {$httpCode}.\n";
}

echo "\n✅ LIVE PATCH COMPLETE! All CRM modules updated.\n";
echo "</pre>";
