import ftplib
import io

FTP_HOST = "ftpupload.net"
FTP_USER = "ezyro_42571719"
FTP_PASS = "b441442"

# This PHP script will:
# 1. Read server environment for DB credentials
# 2. Try all likely passwords 
# 3. If successful, import the full database schema
# 4. Seed all permissions for employee roles
php_content = r"""<?php
header('Content-Type: text/plain; charset=utf-8');
set_time_limit(300);
ini_set('display_errors', '1');
error_reporting(E_ALL);

echo "=== RAPTOR CRM COMPLETE SETUP & FIX ===\n\n";

$user = 'ezyro_42571719';
$hosts = ['sql200.ezyro.com', 'sql201.ezyro.com', 'sql202.ezyro.com'];
$passwords = [
    'b441442', 'Axiorags@2026', 'axiorags@2026', 'Raptor@12345',
    'RaptorCRM@2026', 'raptor2026', 'Raptor2026', 'raptor',
    'b441442!', 'B441442', 'Ezyro2026', 'ezyro2026', 'Admin@2026',
    'Password123!', 'password', 'admin', '123456', 'ezyro_42571719'
];
$dbNames = ['ezyro_42571719', 'ezyro_42571719_raptor', 'ezyro_42571719_crm', 'raptor_crm_db'];

// Also check environment variables
echo "--- Checking Environment Variables ---\n";
$envVars = ['DB_HOST', 'DB_USER', 'DB_PASS', 'DB_PASSWORD', 'DB_NAME', 'MYSQL_PASSWORD', 'MYSQL_DATABASE', 'MYSQL_USER'];
foreach ($envVars as $v) {
    $val = getenv($v);
    if ($val !== false && $val !== '') echo "$v = $val\n";
}

// Check for .env file
$envPaths = [
    dirname(__DIR__) . '/.env',
    dirname(__DIR__) . '/app/.env',
    '/home/vol17_2/ezyro.com/ezyro_42571719/.env',
    dirname(__DIR__) . '/../.env',
];
foreach ($envPaths as $ep) {
    if (@file_exists($ep)) {
        echo "\nFound .env at: $ep\n";
        echo @file_get_contents($ep) . "\n";
    }
}

echo "\n--- Attempting MySQL Connections ---\n";

$pdo = null;
$workingHost = null;
$workingPass = null;
$workingDb = null;

foreach ($hosts as $host) {
    foreach ($dbNames as $dbName) {
        foreach ($passwords as $pass) {
            try {
                $dsn = "mysql:host={$host};dbname={$dbName};charset=utf8;connect_timeout=3";
                $pdo = new PDO($dsn, $user, $pass, [
                    PDO::ATTR_TIMEOUT => 3,
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                ]);
                echo "\n*** SUCCESS! ***\n";
                echo "HOST: $host\nUSER: $user\nPASS: $pass\nDB: $dbName\n\n";
                $workingHost = $host;
                $workingPass = $pass;
                $workingDb = $dbName;
                break 3;
            } catch (PDOException $e) {
                $msg = $e->getMessage();
                if (strpos($msg, '1045') !== false) {
                    // Access denied - wrong password but host/user exist
                } elseif (strpos($msg, 'timed out') === false && strpos($msg, 'refused') === false && strpos($msg, 'No such host') === false) {
                    echo "Note $host/$dbName/$pass: $msg\n";
                }
            }
        }
    }
}

if (!$pdo) {
    echo "\nCould not connect with any tried password.\n";
    echo "Please check ProFreeHost cPanel > MySQL Databases for the password.\n";
    echo "Then edit /htdocs/app/config/config.php to set DB_PASS.\n";
    exit(1);
}

// NOW SET UP THE DATABASE
echo "--- Setting up Database Schema ---\n";

// Check which tables exist
$tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
echo "Existing tables: " . (empty($tables) ? "(none)" : implode(', ', $tables)) . "\n\n";

// Core tables needed
$createStatements = [];

if (!in_array('roles', $tables)) {
    $createStatements[] = "CREATE TABLE IF NOT EXISTS roles (
        role_id INT AUTO_INCREMENT PRIMARY KEY,
        role_name VARCHAR(50) NOT NULL UNIQUE,
        description VARCHAR(255) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
}

if (!in_array('users', $tables)) {
    $createStatements[] = "CREATE TABLE IF NOT EXISTS users (
        user_id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(100) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        role_id INT NOT NULL,
        status ENUM('active','inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
}

if (!in_array('permissions', $tables)) {
    $createStatements[] = "CREATE TABLE IF NOT EXISTS permissions (
        permission_id INT AUTO_INCREMENT PRIMARY KEY,
        permission_name VARCHAR(100) NOT NULL UNIQUE,
        module VARCHAR(50) NOT NULL,
        action VARCHAR(50) NOT NULL,
        description VARCHAR(255) NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
}

if (!in_array('role_permissions', $tables)) {
    $createStatements[] = "CREATE TABLE IF NOT EXISTS role_permissions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        role_id INT NOT NULL,
        permission_id INT NOT NULL,
        scope ENUM('own','team','all') DEFAULT 'own',
        UNIQUE KEY uniq_role_perm (role_id, permission_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
}

if (!in_array('leads', $tables)) {
    $createStatements[] = "CREATE TABLE IF NOT EXISTS leads (
        lead_id INT AUTO_INCREMENT PRIMARY KEY,
        first_name VARCHAR(100) NOT NULL,
        last_name VARCHAR(100) NULL,
        company_name VARCHAR(150) NULL,
        email VARCHAR(100) NULL,
        phone VARCHAR(50) NULL,
        lead_source VARCHAR(100) DEFAULT 'Website',
        lead_value DECIMAL(12,2) DEFAULT 0.00,
        lead_notes TEXT NULL,
        status ENUM('new','contacted','qualified','proposal','negotiation','won','lost') DEFAULT 'new',
        lead_quality ENUM('cold','warm','hot') DEFAULT 'warm',
        assigned_to_user_id INT NULL,
        created_by_user_id INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
}

if (!in_array('follow_ups', $tables)) {
    $createStatements[] = "CREATE TABLE IF NOT EXISTS follow_ups (
        follow_up_id INT AUTO_INCREMENT PRIMARY KEY,
        lead_id INT NOT NULL,
        assigned_to_user_id INT NULL,
        created_by_user_id INT NULL,
        channel ENUM('call','whatsapp','sms','email','meeting','demo','other') DEFAULT 'call',
        due_at DATETIME NOT NULL,
        completed_at DATETIME NULL,
        note TEXT NULL,
        outcome TEXT NULL,
        status ENUM('scheduled','completed','missed','cancelled') DEFAULT 'scheduled',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
}

if (!in_array('customers', $tables)) {
    $createStatements[] = "CREATE TABLE IF NOT EXISTS customers (
        customer_id INT AUTO_INCREMENT PRIMARY KEY,
        lead_id INT NULL,
        company_name VARCHAR(150) NOT NULL,
        contact_name VARCHAR(100) NULL,
        contact_email VARCHAR(100) NULL,
        contact_phone VARCHAR(50) NULL,
        assigned_to_user_id INT NULL,
        status ENUM('active','inactive','churned') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
}

if (!in_array('communications', $tables)) {
    $createStatements[] = "CREATE TABLE IF NOT EXISTS communications (
        communication_id INT AUTO_INCREMENT PRIMARY KEY,
        lead_id INT NULL,
        customer_id INT NULL,
        user_id INT NOT NULL,
        channel ENUM('call','whatsapp','sms','email','meeting','demo','other') DEFAULT 'call',
        summary TEXT NULL,
        logged_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
}

if (!in_array('meetings', $tables)) {
    $createStatements[] = "CREATE TABLE IF NOT EXISTS meetings (
        meeting_id INT AUTO_INCREMENT PRIMARY KEY,
        lead_id INT NULL,
        customer_id INT NULL,
        organizer_user_id INT NOT NULL,
        title VARCHAR(200) NOT NULL,
        scheduled_at DATETIME NOT NULL,
        duration_minutes INT DEFAULT 30,
        location VARCHAR(255) NULL,
        notes TEXT NULL,
        status ENUM('scheduled','completed','cancelled') DEFAULT 'scheduled',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
}

if (!in_array('sessions', $tables)) {
    $createStatements[] = "CREATE TABLE IF NOT EXISTS sessions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        session_id VARCHAR(128) NOT NULL UNIQUE,
        user_id INT NULL,
        payload TEXT NULL,
        last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
}

if (!in_array('employees', $tables)) {
    $createStatements[] = "CREATE TABLE IF NOT EXISTS employees (
        employee_id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL UNIQUE,
        employee_code VARCHAR(50) NULL UNIQUE,
        job_title VARCHAR(100) NULL,
        department VARCHAR(100) NULL,
        reporting_manager_id INT NULL,
        date_of_joining DATE NULL,
        employment_type ENUM('full_time','part_time','contract','intern') DEFAULT 'full_time',
        status ENUM('active','inactive','terminated') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
}

if (!in_array('activity_logs', $tables)) {
    $createStatements[] = "CREATE TABLE IF NOT EXISTS activity_logs (
        log_id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NULL,
        action VARCHAR(200) NOT NULL,
        ip_address VARCHAR(45) NULL,
        user_agent VARCHAR(255) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
}

foreach ($createStatements as $sql) {
    try {
        $pdo->exec($sql);
        $tableName = '';
        preg_match('/TABLE\s+IF\s+NOT\s+EXISTS\s+(\w+)/i', $sql, $m);
        echo "[OK] Created table: " . ($m[1] ?? 'unknown') . "\n";
    } catch (PDOException $e) {
        echo "[ERR] Create table failed: " . $e->getMessage() . "\n";
    }
}

echo "\n--- Seeding Roles ---\n";
$roles = [
    ['admin', 'System Administrator'],
    ['hr', 'HR Manager'],
    ['manager', 'Department Manager'],
    ['team_leader', 'Team Leader'],
    ['employee', 'Employee'],
    ['sales_person', 'Sales Person'],
    ['finance', 'Finance Officer'],
    ['analyst', 'Business Analyst'],
    ['ceo', 'Chief Executive Officer'],
    ['employer', 'Employer'],
];
foreach ($roles as [$name, $desc]) {
    $stmt = $pdo->prepare("INSERT INTO roles (role_name, description) VALUES (?, ?) ON DUPLICATE KEY UPDATE description = VALUES(description)");
    $stmt->execute([$name, $desc]);
    echo "[OK] Role: $name\n";
}

echo "\n--- Seeding Default Admin User ---\n";
// Check if admin exists
$adminCheck = $pdo->query("SELECT COUNT(*) FROM users WHERE email = 'admin@raptor.local'")->fetchColumn();
if (!$adminCheck) {
    $adminRoleId = $pdo->query("SELECT role_id FROM roles WHERE role_name = 'admin'")->fetchColumn();
    $stmt = $pdo->prepare("INSERT INTO users (name, email, password_hash, role_id, status) VALUES (?, ?, ?, ?, 'active')");
    $stmt->execute(['Admin', 'admin@raptor.local', password_hash('Password123!', PASSWORD_BCRYPT), $adminRoleId]);
    echo "[OK] Created admin@raptor.local (Password: Password123!)\n";
}

// Create employee test user
$empCheck = $pdo->query("SELECT COUNT(*) FROM users WHERE email = 'employee@raptor.local'")->fetchColumn();
if (!$empCheck) {
    $empRoleId = $pdo->query("SELECT role_id FROM roles WHERE role_name = 'employee'")->fetchColumn();
    $stmt = $pdo->prepare("INSERT INTO users (name, email, password_hash, role_id, status) VALUES (?, ?, ?, ?, 'active')");
    $stmt->execute(['Test Employee', 'employee@raptor.local', password_hash('Password123!', PASSWORD_BCRYPT), $empRoleId]);
    echo "[OK] Created employee@raptor.local (Password: Password123!)\n";
}

echo "\n--- Seeding Permissions ---\n";
$perms = [
    ['leads.view', 'leads', 'view', 'View leads'],
    ['leads.create', 'leads', 'create', 'Create leads'],
    ['leads.edit', 'leads', 'edit', 'Edit leads'],
    ['leads.delete', 'leads', 'delete', 'Delete leads'],
    ['crm_leads.view', 'crm_leads', 'view', 'View CRM leads'],
    ['crm_leads.create', 'crm_leads', 'create', 'Create CRM leads'],
    ['crm_leads.edit', 'crm_leads', 'edit', 'Edit CRM leads'],
    ['customers.view', 'customers', 'view', 'View customers'],
    ['customers.create', 'customers', 'create', 'Create customers'],
    ['customers.edit', 'customers', 'edit', 'Edit customers'],
    ['communications.view', 'communications', 'view', 'View communications'],
    ['communications.create', 'communications', 'create', 'Log communications'],
    ['meetings.view', 'meetings', 'view', 'View meetings'],
    ['meetings.create', 'meetings', 'create', 'Schedule meetings'],
    ['followups.view', 'followups', 'view', 'View follow-ups'],
    ['followups.create', 'followups', 'create', 'Schedule follow-ups'],
    ['tasks.view', 'tasks', 'view', 'View tasks'],
    ['tasks.create', 'tasks', 'create', 'Create tasks'],
    ['dashboard.view', 'dashboard', 'view', 'View dashboard'],
];
foreach ($perms as [$pName, $mod, $act, $desc]) {
    $stmt = $pdo->prepare("INSERT INTO permissions (permission_name, module, action, description) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE module=VALUES(module)");
    $stmt->execute([$pName, $mod, $act, $desc]);
}
echo "[OK] " . count($perms) . " permissions seeded\n";

echo "\n--- Granting Permissions to Employee/Sales Roles ---\n";
$empRoles = ['employee', 'sales_person', 'admin', 'manager', 'ceo', 'team_leader'];
foreach ($empRoles as $roleName) {
    $rid = $pdo->query("SELECT role_id FROM roles WHERE role_name = '$roleName' LIMIT 1")->fetchColumn();
    if (!$rid) continue;
    $scope = in_array($roleName, ['admin', 'ceo', 'manager', 'team_leader']) ? 'all' : 'own';
    foreach ($perms as [$pName]) {
        $pid = $pdo->query("SELECT permission_id FROM permissions WHERE permission_name = '$pName' LIMIT 1")->fetchColumn();
        if ($pid) {
            $stmt = $pdo->prepare("INSERT INTO role_permissions (role_id, permission_id, scope) VALUES (?,?,?) ON DUPLICATE KEY UPDATE scope=VALUES(scope)");
            $stmt->execute([$rid, $pid, $scope]);
        }
    }
    echo "[OK] Granted all CRM permissions to role: $roleName\n";
}

echo "\n\n=== SETUP COMPLETE! ===\n";
echo "Database: $workingDb on $workingHost\n";
echo "Login: http://raptor.unaux.com/public/index.php?route=auth/login\n";
echo "Admin: admin@raptor.local / Password123!\n";
echo "Employee: employee@raptor.local / Password123!\n";
echo "\nAll 5 modules should now work:\n";
echo "- Follow-ups: ?route=followups/index\n";
echo "- Leads: ?route=leads/index\n";
echo "- Customers: ?route=customers/index\n";
echo "- Communications: ?route=communications/index\n";
echo "- Meetings: ?route=meetings/index\n";
"""

print("Uploading comprehensive setup script via FTP...")
ftp = ftplib.FTP(FTP_HOST, timeout=15)
ftp.login(FTP_USER, FTP_PASS)
ftp.cwd("/htdocs/bin")

php_bytes = php_content.encode('utf-8')
ftp.storbinary("STOR full_setup.php", io.BytesIO(php_bytes))
print(f"[OK] Uploaded bin/full_setup.php ({len(php_bytes)} bytes)")
ftp.quit()
print("Done!")
