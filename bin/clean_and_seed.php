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

// Check role mapping
$requiredRoles = ['admin', 'manager', 'hr', 'finance', 'analyst', 'employee'];
foreach ($requiredRoles as $role) {
    if (!isset($rolesMap[$role])) {
        die("Error: Role '$role' is missing from the database.\n");
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

// Ensure Analyst role has full view & management permissions for HR, Payroll, Employees, and Reports
$analystRoleId = $rolesMap['analyst'];
$analystModules = ['employees', 'attendance', 'leave', 'payroll', 'hrms', 'social_media', 'reports', 'analytics', 'customers'];
foreach ($analystModules as $mod) {
    $stmt = $db->prepare("SELECT permission_id FROM permissions WHERE module = :mod");
    $stmt->execute([':mod' => $mod]);
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $pId) {
        $db->prepare("INSERT IGNORE INTO role_permissions (role_id, permission_id, scope) VALUES (:rid, :pid, 'all')")
           ->execute([':rid' => $analystRoleId, ':pid' => $pId]);
    }
}
echo "Granted HR & Payroll, Employees, and Reports permissions to Analyst role.\n";
// Seed New Users
// Password is 'Password123!'
$hash = password_hash('Password123!', PASSWORD_BCRYPT, ['cost' => 10]);

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
}
echo "Seeded 13 social media platforms.\n";

// Enable foreign key checks back
$db->exec("SET FOREIGN_KEY_CHECKS = 1;");
echo "Database seeding completed successfully!\n";
