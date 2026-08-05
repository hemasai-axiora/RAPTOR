<?php
// Live Database Diagnostic & Patch via alter_employees.php
require_once dirname(dirname(__FILE__)) . '/app/config/config.php';
require_once dirname(dirname(__FILE__)) . '/app/core/Database.php';

header('Content-Type: text/plain; charset=utf-8');

// Web-based Auto-Updater Hook to pull latest GitHub code
if (isset($_GET['update']) || (php_sapi_name() !== 'cli' && !isset($_GET['noupdate']))) {
    $root = dirname(__DIR__);
    $zipUrl = 'https://github.com/hemasai-axiora/RAPTOR/archive/refs/heads/main.zip';
    $tempZip = $root . '/public/temp_update.zip';
    $ch = curl_init($zipUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
    curl_setopt($ch, CURLOPT_TIMEOUT, 120);
    $zipData = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($httpCode === 200 && !empty($zipData)) {
        file_put_contents($tempZip, $zipData);
        $zip = new ZipArchive();
        if ($zip->open($tempZip) === TRUE) {
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
                }
            }
            $zip->close();
            @unlink($tempZip);
            echo "Auto-updated codebase from GitHub main branch.\n";
        }
    }
}

try {
    $db = Database::getInstance()->getConnection();
    echo "=== RAPTOR CRM LIVE DB DIAGNOSTIC & PERMISSION SEED ===\n\n";

    // 1. Check Table Existence
    $tables = ['follow_ups', 'leads', 'customers', 'communications', 'meetings', 'users', 'roles', 'permissions', 'role_permissions'];
    foreach ($tables as $t) {
        try {
            $count = $db->query("SELECT COUNT(*) FROM {$t}")->fetchColumn();
            echo "[OK] Table '{$t}' exists with {$count} rows.\n";
        } catch (Exception $e) {
            echo "[ERROR] Table '{$t}' missing or query failed: " . $e->getMessage() . "\n";
            // Create table if missing
            if ($t === 'follow_ups') {
                $db->exec("CREATE TABLE IF NOT EXISTS follow_ups (
                    follow_up_id INT AUTO_INCREMENT PRIMARY KEY,
                    lead_id INT NOT NULL,
                    assigned_to_user_id INT NULL,
                    created_by_user_id INT NULL,
                    channel ENUM('call', 'whatsapp', 'sms', 'email', 'meeting', 'demo', 'other') DEFAULT 'call',
                    due_at DATETIME NOT NULL,
                    completed_at DATETIME NULL,
                    note TEXT NULL,
                    outcome TEXT NULL,
                    status ENUM('scheduled', 'completed', 'missed', 'cancelled') DEFAULT 'scheduled',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
                echo "   -> Auto-created 'follow_ups' table.\n";
            } elseif ($t === 'customers') {
                $db->exec("CREATE TABLE IF NOT EXISTS customers (
                    customer_id INT AUTO_INCREMENT PRIMARY KEY,
                    lead_id INT NULL,
                    company_name VARCHAR(150) NOT NULL,
                    contact_name VARCHAR(100) NULL,
                    contact_email VARCHAR(100) NULL,
                    contact_phone VARCHAR(50) NULL,
                    assigned_to_user_id INT NULL,
                    status ENUM('active', 'inactive', 'churned') DEFAULT 'active',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
                echo "   -> Auto-created 'customers' table.\n";
            } elseif ($t === 'communications') {
                $db->exec("CREATE TABLE IF NOT EXISTS communications (
                    communication_id INT AUTO_INCREMENT PRIMARY KEY,
                    lead_id INT NULL,
                    customer_id INT NULL,
                    user_id INT NOT NULL,
                    channel ENUM('call', 'whatsapp', 'sms', 'email', 'meeting', 'demo', 'other') DEFAULT 'call',
                    summary TEXT NULL,
                    logged_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
                echo "   -> Auto-created 'communications' table.\n";
            } elseif ($t === 'meetings') {
                $db->exec("CREATE TABLE IF NOT EXISTS meetings (
                    meeting_id INT AUTO_INCREMENT PRIMARY KEY,
                    lead_id INT NULL,
                    customer_id INT NULL,
                    organizer_user_id INT NOT NULL,
                    title VARCHAR(200) NOT NULL,
                    scheduled_at DATETIME NOT NULL,
                    duration_minutes INT DEFAULT 30,
                    location VARCHAR(255) NULL,
                    notes TEXT NULL,
                    status ENUM('scheduled', 'completed', 'cancelled') DEFAULT 'scheduled',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
                echo "   -> Auto-created 'meetings' table.\n";
            }
        }
    }

    // 2. Ensure Permissions for Employee & Sales Person & Admin
    echo "\n=== Seeding Permissions ===\n";
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

    $roles = ['employee', 'sales_person', 'admin', 'manager', 'ceo'];
    foreach ($roles as $rName) {
        $rStmt = $db->prepare('SELECT role_id FROM roles WHERE role_name = :r LIMIT 1');
        $rStmt->execute([':r' => $rName]);
        $rid = $rStmt->fetchColumn();
        if (!$rid) continue;

        foreach ($permsToEnsure as [$pName]) {
            $pStmt = $db->prepare('SELECT permission_id FROM permissions WHERE permission_name = :p LIMIT 1');
            $pStmt->execute([':p' => $pName]);
            $pid = $pStmt->fetchColumn();
            if ($pid) {
                $scope = in_array($rName, ['admin', 'ceo', 'manager'], true) ? 'all' : (($pName === 'customers.view') ? 'all' : 'own');
                $stmt = $db->prepare('INSERT INTO role_permissions (role_id, permission_id, scope) VALUES (:r, :p, :s) ON DUPLICATE KEY UPDATE scope = VALUES(scope)');
                $stmt->execute([':r' => $rid, ':p' => $pid, ':s' => $scope]);
            }
        }
        echo "[OK] Permissions granted for role '{$rName}'.\n";
    }

    // 3. Test queries for Followups, Leads, Customers, Communications, Meetings
    echo "\n=== Testing Model Queries ===\n";
    
    // Followups
    try {
        $q = $db->query("SELECT f.*, l.first_name, l.last_name FROM follow_ups f LEFT JOIN leads l ON f.lead_id = l.lead_id LIMIT 1");
        echo "[OK] Followups query executed. Count: " . $q->rowCount() . "\n";
    } catch (Exception $e) { echo "[ERROR] Followups query failed: " . $e->getMessage() . "\n"; }

    // Leads
    try {
        $q = $db->query("SELECT * FROM leads LIMIT 1");
        echo "[OK] Leads query executed. Count: " . $q->rowCount() . "\n";
    } catch (Exception $e) { echo "[ERROR] Leads query failed: " . $e->getMessage() . "\n"; }

    // Customers
    try {
        $q = $db->query("SELECT * FROM customers LIMIT 1");
        echo "[OK] Customers query executed. Count: " . $q->rowCount() . "\n";
    } catch (Exception $e) { echo "[ERROR] Customers query failed: " . $e->getMessage() . "\n"; }

    // Communications
    try {
        $q = $db->query("SELECT * FROM communications LIMIT 1");
        echo "[OK] Communications query executed. Count: " . $q->rowCount() . "\n";
    } catch (Exception $e) { echo "[ERROR] Communications query failed: " . $e->getMessage() . "\n"; }

    // Meetings
    try {
        $q = $db->query("SELECT * FROM meetings LIMIT 1");
        echo "[OK] Meetings query executed. Count: " . $q->rowCount() . "\n";
    } catch (Exception $e) { echo "[ERROR] Meetings query failed: " . $e->getMessage() . "\n"; }

    echo "\n=== DIAGNOSTIC & SEED COMPLETE ===\n";
} catch (Exception $e) {
    echo "[FATAL ERROR] " . $e->getMessage() . "\n";
}
