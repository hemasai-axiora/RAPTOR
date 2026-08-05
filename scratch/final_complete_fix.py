import ftplib
import io
import urllib.request

FTP_HOST = "ftpupload.net"
FTP_USER = "ezyro_42571719"
FTP_PASS = "b441442"

# CONFIRMED working credentials:
DB_HOST = "sql212.ezyro.com"
DB_USER = "ezyro_42571719"
DB_PASS = "b441442"
DB_NAME = "ezyro_42571719_raptor"

print("=== RAPTOR CRM - FINAL FIX ===")
print(f"DB: {DB_USER}:{DB_PASS}@{DB_HOST}/{DB_NAME}")

# Step 1: Upload correct config.php
config_php = f"""<?php
// Raptor CRM - ProFreeHost Production Configuration
if (!function_exists('env')) {{
    function env(string $key, $default = null) {{
        $val = getenv($key);
        return ($val === false || $val === '') ? $default : $val;
    }}
}}
define('DB_HOST', env('DB_HOST', '{DB_HOST}'));
define('DB_USER', env('DB_USER') && env('DB_USER') !== 'root' ? env('DB_USER') : '{DB_USER}');
define('DB_PASS', env('DB_PASS') && env('DB_PASS') !== 'rootpassword' ? env('DB_PASS') : '{DB_PASS}');
define('DB_NAME', env('DB_NAME', '{DB_NAME}'));
define('APPROOT', dirname(dirname(__FILE__)));
$host = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? null;
if ($host) {{
    if (strpos($host, ',') !== false) {{ $host = trim(explode(',', $host)[0]); }}
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {{ $protocol = 'https'; }}
    $script_name = $_SERVER['SCRIPT_NAME'] ?? '';
    $dir = dirname($script_name);
    $dir = ($dir === '\\\\' || $dir === '/') ? '' : $dir;
    define('URLROOT', $protocol . '://' . $host . $dir);
}} else {{
    define('URLROOT', env('URLROOT', 'http://raptor.unaux.com/public'));
}}
define('SITENAME', env('SITENAME', 'RAPTOR'));
define('APP_ENV', env('APP_ENV', 'production'));
define('SESSION_TIMEOUT', (int) env('SESSION_TIMEOUT', 1800));
define('STORAGE_PATH', env('STORAGE_PATH', APPROOT . '/../storage'));
define('MAX_UPLOAD_BYTES', (int) env('MAX_UPLOAD_BYTES', 5 * 1024 * 1024));
define('STORAGE_PROVIDER', env('STORAGE_PROVIDER', 'local'));
define('S3_BUCKET', env('S3_BUCKET', ''));
define('S3_REGION', env('S3_REGION', 'us-east-1'));
error_reporting(E_ALL);
ini_set('display_errors', '1');
if (!function_exists('getUserTimezone')) {{
    function getUserTimezone(): string {{
        $tz = $_COOKIE['user_timezone'] ?? env('APP_TIMEZONE', 'Asia/Kolkata');
        return !empty($tz) ? $tz : 'Asia/Kolkata';
    }}
}}
date_default_timezone_set(getUserTimezone());
if (!function_exists('formatToLocalTime')) {{
    function formatToLocalTime($datetime, $format = 'Y-m-d H:i:s'): string {{
        if (empty($datetime)) return '';
        try {{
            $userTzStr = getUserTimezone(); $targetTz = new DateTimeZone($userTzStr);
            if (strpos($datetime, 'Z') !== false || preg_match('/[+-]00:?00$/', $datetime)) {{
                $dt = new DateTime($datetime, new DateTimeZone('UTC'));
            }} else {{ $dt = new DateTime($datetime, $targetTz); }}
            $dt->setTimezone($targetTz); return $dt->format($format);
        }} catch (Exception $e) {{ return $datetime; }}
    }}
}}
if (!function_exists('parseLocalToUtc')) {{
    function parseLocalToUtc($localDatetimeString): string {{
        if (empty($localDatetimeString)) return '';
        try {{
            $localTz = getUserTimezone();
            $dt = new DateTime($localDatetimeString, new DateTimeZone($localTz));
            $dt->setTimezone(new DateTimeZone('UTC')); return $dt->format('Y-m-d H:i:s');
        }} catch (Exception $e) {{ return $localDatetimeString; }}
    }}
}}
"""

ftp = ftplib.FTP(FTP_HOST, timeout=15)
ftp.login(FTP_USER, FTP_PASS)
print("\n[1/3] Uploading config.php...")
ftp.cwd("/htdocs/app/config")
ftp.storbinary("STOR config.php", io.BytesIO(config_php.encode('utf-8')))
print("    [OK] config.php with correct DB credentials uploaded!")

# Step 2: Upload final_setup.php
print("[2/3] Uploading final_setup.php...")
setup_php = r"""<?php
header('Content-Type: text/plain; charset=utf-8');
set_time_limit(300);
ini_set('display_errors','1');
error_reporting(E_ALL);

echo "=== RAPTOR CRM FINAL SETUP ===\n\n";
require_once dirname(__DIR__) . '/app/config/config.php';

try {
    $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4", DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT=>10
    ]);
    echo "DB: CONNECTED to " . DB_HOST . "/" . DB_NAME . "\n\n";
} catch (PDOException $e) {
    die("DB FAILED: " . $e->getMessage() . "\n");
}

function runSql($pdo, $name, $sql) {
    try { $pdo->exec($sql); echo "[OK] $name\n"; return true; }
    catch (PDOException $e) { echo "[ERR] $name: " . $e->getMessage() . "\n"; return false; }
}

echo "--- Creating Tables ---\n";
runSql($pdo,'roles',"CREATE TABLE IF NOT EXISTS roles(role_id INT AUTO_INCREMENT PRIMARY KEY,role_name VARCHAR(50) NOT NULL UNIQUE,description VARCHAR(255),created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB CHARSET=utf8mb4");
runSql($pdo,'permissions',"CREATE TABLE IF NOT EXISTS permissions(permission_id INT AUTO_INCREMENT PRIMARY KEY,permission_name VARCHAR(100) NOT NULL UNIQUE,module VARCHAR(50) NOT NULL,action VARCHAR(50) NOT NULL,description VARCHAR(255)) ENGINE=InnoDB CHARSET=utf8mb4");
runSql($pdo,'users',"CREATE TABLE IF NOT EXISTS users(user_id INT AUTO_INCREMENT PRIMARY KEY,name VARCHAR(100) NOT NULL,email VARCHAR(100) NOT NULL UNIQUE,password_hash VARCHAR(255) NOT NULL,role_id INT NOT NULL,status ENUM('active','inactive') DEFAULT 'active',created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB CHARSET=utf8mb4");
runSql($pdo,'role_permissions',"CREATE TABLE IF NOT EXISTS role_permissions(id INT AUTO_INCREMENT PRIMARY KEY,role_id INT NOT NULL,permission_id INT NOT NULL,scope ENUM('own','team','all') DEFAULT 'all',UNIQUE KEY uniq_rp(role_id,permission_id)) ENGINE=InnoDB CHARSET=utf8mb4");
runSql($pdo,'employees',"CREATE TABLE IF NOT EXISTS employees(employee_id INT AUTO_INCREMENT PRIMARY KEY,user_id INT NOT NULL UNIQUE,employee_code VARCHAR(50) UNIQUE,job_title VARCHAR(100),department VARCHAR(100),reporting_manager_id INT,date_of_joining DATE,employment_type ENUM('full_time','part_time','contract','intern') DEFAULT 'full_time',status ENUM('active','inactive','terminated') DEFAULT 'active',created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB CHARSET=utf8mb4");
runSql($pdo,'leads',"CREATE TABLE IF NOT EXISTS leads(lead_id INT AUTO_INCREMENT PRIMARY KEY,first_name VARCHAR(100) NOT NULL,last_name VARCHAR(100),company_name VARCHAR(150),email VARCHAR(100),phone VARCHAR(50),lead_source VARCHAR(100) DEFAULT 'Website',lead_value DECIMAL(12,2) DEFAULT 0.00,lead_notes TEXT,status ENUM('new','contacted','qualified','proposal','negotiation','won','lost') DEFAULT 'new',lead_quality ENUM('cold','warm','hot') DEFAULT 'warm',assigned_to_user_id INT,created_by_user_id INT,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB CHARSET=utf8mb4");
runSql($pdo,'follow_ups',"CREATE TABLE IF NOT EXISTS follow_ups(follow_up_id INT AUTO_INCREMENT PRIMARY KEY,lead_id INT NOT NULL,assigned_to_user_id INT,created_by_user_id INT,channel ENUM('call','whatsapp','sms','email','meeting','demo','other') DEFAULT 'call',due_at DATETIME NOT NULL,completed_at DATETIME,note TEXT,outcome TEXT,status ENUM('scheduled','completed','missed','cancelled') DEFAULT 'scheduled',created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB CHARSET=utf8mb4");
runSql($pdo,'customers',"CREATE TABLE IF NOT EXISTS customers(customer_id INT AUTO_INCREMENT PRIMARY KEY,lead_id INT,company_name VARCHAR(150) NOT NULL,contact_name VARCHAR(100),contact_email VARCHAR(100),contact_phone VARCHAR(50),address TEXT,assigned_to_user_id INT,status ENUM('active','inactive','churned') DEFAULT 'active',created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB CHARSET=utf8mb4");
runSql($pdo,'communications',"CREATE TABLE IF NOT EXISTS communications(communication_id INT AUTO_INCREMENT PRIMARY KEY,lead_id INT,customer_id INT,user_id INT NOT NULL,channel ENUM('call','whatsapp','sms','email','meeting','demo','other') DEFAULT 'call',summary TEXT,logged_at DATETIME DEFAULT CURRENT_TIMESTAMP,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB CHARSET=utf8mb4");
runSql($pdo,'meetings',"CREATE TABLE IF NOT EXISTS meetings(meeting_id INT AUTO_INCREMENT PRIMARY KEY,lead_id INT,customer_id INT,organizer_user_id INT NOT NULL,title VARCHAR(200) NOT NULL,scheduled_at DATETIME NOT NULL,duration_minutes INT DEFAULT 30,location VARCHAR(255),notes TEXT,status ENUM('scheduled','completed','cancelled') DEFAULT 'scheduled',created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB CHARSET=utf8mb4");
runSql($pdo,'tasks',"CREATE TABLE IF NOT EXISTS tasks(task_id INT AUTO_INCREMENT PRIMARY KEY,title VARCHAR(200) NOT NULL,description TEXT,assigned_to_user_id INT,created_by_user_id INT,lead_id INT,customer_id INT,due_date DATETIME,priority ENUM('low','medium','high','urgent') DEFAULT 'medium',status ENUM('pending','in_progress','completed','cancelled') DEFAULT 'pending',created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB CHARSET=utf8mb4");
runSql($pdo,'activity_logs',"CREATE TABLE IF NOT EXISTS activity_logs(log_id INT AUTO_INCREMENT PRIMARY KEY,user_id INT,action VARCHAR(200) NOT NULL,entity_type VARCHAR(50),entity_id INT,ip_address VARCHAR(45),created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB CHARSET=utf8mb4");
runSql($pdo,'notifications',"CREATE TABLE IF NOT EXISTS notifications(notification_id INT AUTO_INCREMENT PRIMARY KEY,user_id INT NOT NULL,message TEXT NOT NULL,is_read TINYINT(1) DEFAULT 0,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB CHARSET=utf8mb4");
runSql($pdo,'sessions',"CREATE TABLE IF NOT EXISTS sessions(id INT AUTO_INCREMENT PRIMARY KEY,session_id VARCHAR(128) NOT NULL UNIQUE,user_id INT,payload TEXT,last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB CHARSET=utf8mb4");
runSql($pdo,'migration_log',"CREATE TABLE IF NOT EXISTS migration_log(id INT AUTO_INCREMENT PRIMARY KEY,migration_name VARCHAR(255) NOT NULL UNIQUE,applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB CHARSET=utf8mb4");

echo "\n--- Seeding Roles ---\n";
$roleData=[['admin','System Administrator'],['hr','HR Manager'],['manager','Department Manager'],['team_leader','Team Leader'],['employee','Employee'],['sales_person','Sales Person'],['finance','Finance Officer'],['analyst','Business Analyst'],['ceo','Chief Executive Officer'],['employer','Employer']];
foreach($roleData as[$n,$d]){$pdo->prepare("INSERT INTO roles(role_name,description)VALUES(?,?)ON DUPLICATE KEY UPDATE description=VALUES(description)")->execute([$n,$d]);echo "[OK] $n\n";}

echo "\n--- Seeding Permissions ---\n";
$permData=[['dashboard.view','dashboard','view'],['leads.view','leads','view'],['leads.create','leads','create'],['leads.edit','leads','edit'],['leads.delete','leads','delete'],['crm_leads.view','crm_leads','view'],['crm_leads.create','crm_leads','create'],['crm_leads.edit','crm_leads','edit'],['followups.view','followups','view'],['followups.create','followups','create'],['followups.edit','followups','edit'],['customers.view','customers','view'],['customers.create','customers','create'],['customers.edit','customers','edit'],['communications.view','communications','view'],['communications.create','communications','create'],['meetings.view','meetings','view'],['meetings.create','meetings','create'],['meetings.edit','meetings','edit'],['tasks.view','tasks','view'],['tasks.create','tasks','create'],['employees.view','employees','view'],['reports.view','reports','view'],['analytics.view','analytics','view']];
foreach($permData as[$pn,$mod,$act]){$pdo->prepare("INSERT INTO permissions(permission_name,module,action,description)VALUES(?,?,?,?)ON DUPLICATE KEY UPDATE module=VALUES(module)")->execute([$pn,$mod,$act,"$act $mod"]);}
echo "[OK] ".count($permData)." permissions\n";

echo "\n--- Granting Permissions ---\n";
$allRoles=$pdo->query("SELECT role_id,role_name FROM roles")->fetchAll(PDO::FETCH_ASSOC);
$allPerms=$pdo->query("SELECT permission_id FROM permissions")->fetchAll(PDO::FETCH_COLUMN);
foreach($allRoles as$r){
    $scope=in_array($r['role_name'],['admin','ceo','manager','hr'])?'all':(in_array($r['role_name'],['team_leader'])?'team':'own');
    foreach($allPerms as$pid){$pdo->prepare("INSERT INTO role_permissions(role_id,permission_id,scope)VALUES(?,?,?)ON DUPLICATE KEY UPDATE scope=VALUES(scope)")->execute([$r['role_id'],$pid,$scope]);}
    echo "[OK] {$r['role_name']} -> $scope\n";
}

echo "\n--- Creating Users ---\n";
$usersData=[['Admin','admin@raptor.local','Password123!','admin'],['Employee','employee@raptor.local','Password123!','employee'],['Manager','manager@raptor.local','Password123!','manager'],['Sales','sales@raptor.local','Password123!','sales_person'],['CEO','ceo@raptor.local','Password123!','ceo']];
foreach($usersData as[$name,$email,$pass,$rname]){
    $rid=$pdo->query("SELECT role_id FROM roles WHERE role_name='$rname'")->fetchColumn();
    $exists=$pdo->query("SELECT COUNT(*) FROM users WHERE email='$email'")->fetchColumn();
    if(!$exists&&$rid){$pdo->prepare("INSERT INTO users(name,email,password_hash,role_id,status)VALUES(?,?,?,?,'active')")->execute([$name,$email,password_hash($pass,PASSWORD_BCRYPT),$rid]);echo "[OK] $email\n";}
    else{echo "[SKIP] $email (exists)\n";}
}

// Employee record
$empId=$pdo->query("SELECT user_id FROM users WHERE email='employee@raptor.local'")->fetchColumn();
if($empId){$pdo->prepare("INSERT INTO employees(user_id,employee_code,job_title,department,status)VALUES(?,?,?,?,'active')ON DUPLICATE KEY UPDATE status='active'")->execute([$empId,'EMP001','Sales Executive','Sales']);echo "[OK] Employee: EMP001\n";}

// Mark migration as done
$pdo->exec("INSERT INTO migration_log(migration_name) VALUES('full_setup_2026') ON DUPLICATE KEY UPDATE applied_at=NOW()");

echo "\n=== SETUP COMPLETE! ===\n";
$t=$pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
echo "Tables created: ".count($t)."\n";
echo implode(', ',$t)."\n\n";
echo "LOGIN: http://raptor.unaux.com/public/index.php?route=auth/login\n";
echo "admin@raptor.local / Password123!\n";
echo "employee@raptor.local / Password123!\n\n";
echo "MODULES:\n";
echo "- Follow-ups:     ?route=followups/index\n";
echo "- Leads:          ?route=leads/index\n";  
echo "- Customers:      ?route=customers/index\n";
echo "- Communications: ?route=communications/index\n";
echo "- Meetings:       ?route=meetings/index\n";
"""

ftp.cwd("/htdocs/bin")
ftp.storbinary("STOR final_setup.php", io.BytesIO(setup_php.encode('utf-8')))
print("    [OK] final_setup.php uploaded!")
ftp.quit()

# Step 3: Run it
print("[3/3] Running final_setup.php on live server...")
cookie = "ecdd9de927b44aceffae5934f5990024"
req = urllib.request.Request(
    "http://raptor.unaux.com/bin/final_setup.php",
    headers={"User-Agent": "Mozilla/5.0", "Cookie": f"__test={cookie}; path=/"}
)
with urllib.request.urlopen(req, timeout=120) as resp:
    body = resp.read().decode('utf-8', errors='ignore')
    print(f"\nHTTP {resp.status} ({len(body)} bytes)")
    print(body)
