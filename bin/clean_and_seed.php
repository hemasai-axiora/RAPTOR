<?php
require_once dirname(__DIR__) . '/app/config/config.php';
require_once dirname(__DIR__) . '/app/core/Database.php';

$db = Database::getInstance()->getConnection();

echo "Starting database cleanup...\n";

// Disable foreign key checks to allow clearing tables
$db->exec("SET FOREIGN_KEY_CHECKS = 0;");

// List of transactional and data tables to clear
$tablesToClear = [
    'attendance_approvals',
    'breaks',
    'attendance',
    'leave_approvals',
    'leave_requests',
    'leave_balances',
    'payroll_details',
    'payroll_runs',
    'payroll_payslips',
    'user_permission_overrides',
    'activity_logs',
    'notifications',
    'employees',
    'targets',
    'target_items',
    'target_progress',
    'performance_scores',
    'manager_reviews',
    'follow_ups',
    'lead_sla_escalations',
    'lead_assignments',
    'lead_status_history',
    'leads',
    'travel_summary',
    'location_logs',
    'geofences',
    'product_pricing',
    'products',
    'client_contacts',
    'clients',
    'bank_accounts',
    'salary_structures',
    'bonuses',
    'reimbursements',
    'analytics_history',
    'analytics_entries',
    'assignments',
    'social_accounts',
    'platforms',
    'meeting_checkins',
    'meetings',
    'attachments',
    'communications',
    'dashboard_preferences',
    'data_edit_requests',
    'users'
];

foreach ($tablesToClear as $table) {
    try {
        $db->exec("DELETE FROM `$table`");
        echo "  - Cleared table $table\n";
    } catch (Exception $ex) {
        echo "  x Failed to clear $table: " . $ex->getMessage() . "\n";
    }
}

echo "Dummy data cleared.\n";

// Self-healing schema checks
try { $db->exec("ALTER TABLE roles ADD COLUMN is_system TINYINT(1) NOT NULL DEFAULT 0"); } catch (Exception $e) {}
try { $db->exec("ALTER TABLE roles ADD COLUMN description VARCHAR(255) NULL"); } catch (Exception $e) {}
try { $db->exec("ALTER TABLE permissions ADD COLUMN module VARCHAR(60) NULL"); } catch (Exception $e) {}
try { $db->exec("ALTER TABLE permissions ADD COLUMN action VARCHAR(60) NULL"); } catch (Exception $e) {}
try { $db->exec("ALTER TABLE role_permissions ADD COLUMN scope VARCHAR(20) NOT NULL DEFAULT 'all'"); } catch (Exception $e) {}
try { $db->exec("ALTER TABLE users ADD COLUMN force_password_reset TINYINT(1) NOT NULL DEFAULT 0"); } catch (Exception $e) {}
try { $db->exec("ALTER TABLE employees ADD COLUMN employee_code VARCHAR(50) NULL"); } catch (Exception $e) {}
try { $db->exec("ALTER TABLE employees ADD COLUMN job_title VARCHAR(100) NULL"); } catch (Exception $e) {}
try { $db->exec("ALTER TABLE employees ADD COLUMN reporting_manager_id INT NULL"); } catch (Exception $e) {}
try { $db->exec("ALTER TABLE employees ADD COLUMN department VARCHAR(100) NULL"); } catch (Exception $e) {}

// Ensure CEO role exists
$stmt = $db->prepare("SELECT role_id FROM roles WHERE role_name = 'ceo'");
$stmt->execute();
$ceoRoleId = $stmt->fetchColumn();

if (!$ceoRoleId) {
    $db->exec("INSERT INTO roles (role_name, description, is_system) VALUES ('ceo', 'Chief Executive Officer (CEO)', 1)");
    $ceoRoleId = (int) $db->lastInsertId();
    echo "Created CEO role.\n";
} else {
    $ceoRoleId = (int) $ceoRoleId;
    echo "CEO role already exists.\n";
}

// Grant all permissions to CEO role
$db->exec("DELETE FROM role_permissions WHERE role_id = $ceoRoleId");
$db->exec("INSERT INTO role_permissions (role_id, permission_id) SELECT $ceoRoleId, permission_id FROM permissions");
echo "Granted all permissions to CEO role.\n";

// Fetch other role IDs
$rolesMap = [];
$stmt = $db->query("SELECT role_id, role_name FROM roles");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $rolesMap[$row['role_name']] = (int) $row['role_id'];
}

// Check role mapping and auto-create missing roles
$requiredRoles = [
    'admin' => 'Administrator',
    'manager' => 'Sales Manager',
    'hr' => 'HR Manager',
    'finance' => 'Finance Manager',
    'analyst' => 'Data Analyst',
    'employee' => 'Sales Associate'
];
foreach ($requiredRoles as $role => $desc) {
    if (!isset($rolesMap[$role])) {
        $db->prepare("INSERT INTO roles (role_name, description, is_system) VALUES (:r, :d, 1)")
           ->execute([':r' => $role, ':d' => $desc]);
        $rolesMap[$role] = (int) $db->lastInsertId();
        echo "Auto-created missing role: $role\n";
    }
}
// Ensure Employee, Manager, HR, Finance, Analyst roles have social_media view, create, edit permissions
$updateAccessRoles = [$rolesMap['employee'], $rolesMap['manager'], $rolesMap['hr'], $rolesMap['finance'], $rolesMap['analyst']];
$socialPerms = ['view', 'create', 'edit'];
foreach ($updateAccessRoles as $rId) {
    foreach ($socialPerms as $action) {
        $stmt = $db->prepare("SELECT permission_id FROM permissions WHERE module = 'social_media' AND action = :act");
        $stmt->execute([':act' => $action]);
        $permId = $stmt->fetchColumn();
        if ($permId) {
            $db->prepare("INSERT IGNORE INTO role_permissions (role_id, permission_id, scope) VALUES (:rid, :pid, 'all')")
               ->execute([':rid' => $rId, ':pid' => $permId]);
        }
    }
}
echo "Ensured Manager & Employee roles have social media view/create/edit permissions.\n";

// Ensure Manager and Analyst roles have customers view, create, edit, delete permissions
$customerRoles = [$rolesMap['manager'], $rolesMap['analyst']];
$customerActions = ['view', 'create', 'edit', 'delete'];
foreach ($customerRoles as $rId) {
    foreach ($customerActions as $action) {
        $stmt = $db->prepare("SELECT permission_id FROM permissions WHERE module = 'customers' AND action = :act");
        $stmt->execute([':act' => $action]);
        $permId = $stmt->fetchColumn();
        if ($permId) {
            $db->prepare("INSERT IGNORE INTO role_permissions (role_id, permission_id, scope) VALUES (:rid, :pid, 'all')")
               ->execute([':rid' => $rId, ':pid' => $permId]);
        }
    }
}
echo "Ensured Manager & Analyst roles have customers view/create/edit permissions.\n";

// Ensure all roles have proper module permissions
$allModules = ['dashboard', 'attendance', 'leaves', 'calendar', 'targets', 'performance', 'crm_leads', 'leads', 'customers', 'communications', 'meetings', 'followups', 'tasks', 'social_media', 'payroll', 'reports', 'notifications', 'hrms', 'campaigns'];
foreach ($allModules as $modName) {
    // Add permission if not exists
    $pCheck = $db->prepare("SELECT permission_id FROM permissions WHERE module = :mod AND permission_name = :pname");
    $pCheck->execute([':mod' => $modName, ':pname' => $modName . '.view']);
    $pId = $pCheck->fetchColumn();
    if (!$pId) {
        $db->prepare("INSERT INTO permissions (module, permission_name, description) VALUES (:mod, :pname, :desc)")
           ->execute([':mod' => $modName, ':pname' => $modName . '.view', ':desc' => 'View ' . $modName]);
        $pId = $db->lastInsertId();
    }
    
    // Grant to employee, manager, analyst, hr, finance
    foreach ([$rolesMap['employee'], $rolesMap['manager'], $rolesMap['analyst'], $rolesMap['hr'], $rolesMap['finance']] as $rId) {
        $db->prepare("INSERT IGNORE INTO role_permissions (role_id, permission_id, scope) VALUES (:rid, :pid, 'own')")
           ->execute([':rid' => $rId, ':pid' => $pId]);
    }
}
echo "Ensured all operational permissions are granted for all roles.\n";
// Seed New Users
// Password is 'Raptor@12345'
$hash = password_hash('Raptor@12345', PASSWORD_BCRYPT, ['cost' => 10]);

$usersToSeed = [
    'ceo' => [
        'email' => 'ceo@raptor.local',
        'name' => 'Prem',
        'role_id' => $ceoRoleId,
        'code' => 'EMP001',
        'title' => 'CEO',
        'dept' => 'Executive Office',
        'manager' => null
    ],
    'admin' => [
        'email' => 'admin@raptor.local',
        'name' => 'Naveen',
        'role_id' => $rolesMap['admin'],
        'code' => 'EMP002',
        'title' => 'System Administrator',
        'dept' => 'IT Operations',
        'manager' => 'ceo'
    ],
    'manager' => [
        'email' => 'manager@raptor.local',
        'name' => 'Janardhan Tanakala',
        'role_id' => $rolesMap['manager'],
        'code' => 'EMP003',
        'title' => 'Sales Manager',
        'dept' => 'Sales',
        'manager' => 'ceo'
    ],
    'hr' => [
        'email' => 'hr@raptor.local',
        'name' => 'Priya',
        'role_id' => $rolesMap['hr'],
        'code' => 'EMP004',
        'title' => 'HR Manager',
        'dept' => 'Human Resources',
        'manager' => 'ceo'
    ],
    'finance' => [
        'email' => 'finance@raptor.local',
        'name' => 'Sharath',
        'role_id' => $rolesMap['finance'],
        'code' => 'EMP005',
        'title' => 'Finance Manager',
        'dept' => 'Finance',
        'manager' => 'ceo'
    ],
    'analyst' => [
        'email' => 'analyst@raptor.local',
        'name' => 'Mundlamuri Mrudula',
        'role_id' => $rolesMap['analyst'],
        'code' => 'EMP006',
        'title' => 'Data Analyst',
        'dept' => 'Analytics',
        'manager' => 'ceo'
    ],
    'employee' => [
        'email' => 'employee@raptor.local',
        'name' => 'Hema Sai',
        'role_id' => $rolesMap['employee'],
        'code' => 'EMP007',
        'title' => 'Sales Associate',
        'dept' => 'Sales',
        'manager' => 'manager'
    ]
];

// Insert Users
$insertedUserIds = [];
foreach ($usersToSeed as $key => $u) {
    $stmt = $db->prepare("INSERT INTO users (email, name, password, role_id, status, force_password_reset) 
                          VALUES (:email, :name, :pass, :role, 'active', 0)");
    $stmt->execute([
        ':email' => $u['email'],
        ':name' => $u['name'],
        ':pass' => $hash,
        ':role' => $u['role_id']
    ]);
    $insertedUserIds[$key] = (int) $db->lastInsertId();
    echo "Seeded user: {$u['name']} ({$u['email']})\n";
}

// Insert Employee profiles and link reporting managers
foreach ($usersToSeed as $key => $u) {
    $userId = $insertedUserIds[$key];
    $mgrKey = $u['manager'];
    $mgrUserId = $mgrKey ? $insertedUserIds[$mgrKey] : null;
    
    $stmt = $db->prepare("INSERT INTO employees (user_id, employee_code, job_title, reporting_manager_id, department, status, hire_date) 
                          VALUES (:uid, :code, :title, :mgr, :dept, 'active', :hire_date)");
    $stmt->execute([
        ':uid' => $userId,
        ':code' => $u['code'],
        ':title' => $u['title'],
        ':mgr' => $mgrUserId,
        ':dept' => $u['dept'],
        ':hire_date' => date('Y-m-d')
    ]);
    echo "Linked reporting hierarchy for employee: {$u['name']} -> Manager: " . ($mgrKey ? $usersToSeed[$mgrKey]['name'] : 'None') . "\n";
}

// Seed location consent for all users
foreach ($insertedUserIds as $uId) {
    $db->prepare("INSERT INTO location_consents (user_id, consented, policy_version, ip) VALUES (:uid, 1, 'v1', '127.0.0.1')")
       ->execute([':uid' => $uId]);
}
echo "Seeded location consents for all users.\n";

// Seed All Social Media Platforms & Default Social Accounts
$platformsToSeed = [
    ['Facebook', 'fa-brands fa-facebook', 'Raptor Official Facebook Page', 'https://facebook.com/raptorofficial'],
    ['Instagram', 'fa-brands fa-instagram', '@raptor_official', 'https://instagram.com/raptor_official'],
    ['LinkedIn', 'fa-brands fa-linkedin', 'Raptor Technologies Corp', 'https://linkedin.com/company/raptortech'],
    ['Twitter/X', 'fa-brands fa-x-twitter', '@RaptorCRM', 'https://x.com/RaptorCRM'],
    ['YouTube', 'fa-brands fa-youtube', 'Raptor Digital Hub', 'https://youtube.com/c/raptordigital'],
    ['TikTok', 'fa-brands fa-tiktok', '@raptortok', 'https://tiktok.com/@raptortok'],
    ['Pinterest', 'fa-brands fa-pinterest', 'Raptor Pins', 'https://pinterest.com/raptorpins'],
    ['Snapchat', 'fa-brands fa-snapchat', '@raptorsnap', 'https://snapchat.com/add/raptorsnap'],
    ['WhatsApp Business', 'fa-brands fa-whatsapp', 'Raptor WA Support', 'https://wa.me/15550199'],
    ['Google Business Profile', 'fa-brands fa-google', 'Raptor Digital Agency', 'https://business.google.com'],
    ['Reddit', 'fa-brands fa-reddit', 'u/RaptorOfficial', 'https://reddit.com/u/RaptorOfficial'],
    ['Threads', 'fa-brands fa-at', '@raptor_threads', 'https://threads.net/@raptor_threads'],
    ['Telegram', 'fa-brands fa-telegram', '@RaptorOfficialChannel', 'https://t.me/RaptorOfficialChannel']
];

// Seed or fetch default client for social accounts
$clientStmt = $db->query("SELECT client_id FROM clients LIMIT 1");
$clientId = $clientStmt->fetchColumn();
if (!$clientId) {
    $db->exec("INSERT INTO clients (company_name, email, phone, status) VALUES ('Raptor Enterprise', 'contact@raptor.local', '1-800-RAPTOR', 'active')");
    $clientId = (int) $db->lastInsertId();
}

// Ensure credentials columns exist on social_accounts
$colsToEnsure = [
    'username' => 'VARCHAR(100) NULL',
    'account_password' => 'VARCHAR(255) NULL',
    'account_notes' => 'TEXT NULL',
    'manager_remarks' => 'TEXT NULL'
];
foreach ($colsToEnsure as $colName => $colDef) {
    try {
        $db->exec("ALTER TABLE social_accounts ADD COLUMN $colName $colDef");
    } catch (Exception $e) {
        // Column already exists
    }
}

foreach ($platformsToSeed as [$pName, $pIcon, $profileName, $profileUrl]) {
    // Insert platform
    $pStmt = $db->prepare("INSERT INTO platforms (name, icon) VALUES (:n, :i) ON DUPLICATE KEY UPDATE icon = VALUES(icon)");
    $pStmt->execute([':n' => $pName, ':i' => $pIcon]);
    
    // Fetch platform_id
    $pId = $db->query("SELECT platform_id FROM platforms WHERE name = '$pName'")->fetchColumn();
    
    if ($pId && $clientId) {
        $username = strtolower(str_replace([' ', '/', '@'], ['_', '', ''], $profileName));
        $accStmt = $db->prepare("INSERT INTO social_accounts (client_id, platform_id, platform, profile_name, profile_url, username, account_password, status, account_notes, manager_remarks) 
                                 VALUES (:cid, :pid, :pname, :pname_str, :purl, :uname, :pass, 'active', 'Official company handle for marketing.', 'Approved by management.')");
        $accStmt->execute([
            ':cid' => $clientId,
            ':pid' => $pId,
            ':pname' => $pName,
            ':pname_str' => $profileName,
            ':purl' => $profileUrl,
            ':uname' => $username,
            ':pass' => 'RaptorPass@2026'
        ]);
        $accId = (int)$db->lastInsertId();
        
        // Assign to Employee (Hema Sai / user_id)
        if ($accId && !empty($insertedUserIds['employee'])) {
            $db->exec("INSERT INTO assignments (user_id, account_id, is_shared) VALUES ({$insertedUserIds['employee']}, {$accId}, 1)");
        }
    }
}
echo "Seeded 13 social media platforms with default accounts & employee assignments.\n";

// Seed sample lead
$leadCheck = $db->query("SELECT lead_id FROM leads LIMIT 1")->fetchColumn();
if (!$leadCheck) {
    try {
        $adminUserId = $db->query("SELECT user_id FROM users WHERE email = 'admin@raptor.local' LIMIT 1")->fetchColumn() ?: 1;
        $db->exec("INSERT INTO leads (client_id, assigned_to_user_id, first_name, last_name, company_name, email, phone, status, lead_quality, priority, lead_value, lead_source)
                   VALUES ($clientId, $adminUserId, 'Acme', 'Corporation', 'Acme Corp', 'info@acme.test', '555-0199', 'new', 'warm', 'medium', 10000.00, 'Direct')");
    } catch (Exception $e) {
        // Ignore lead insert error if columns differ
    }
}

// Enable foreign key checks back
$db->exec("SET FOREIGN_KEY_CHECKS = 1;");
echo "Database seeding completed successfully!\n";
