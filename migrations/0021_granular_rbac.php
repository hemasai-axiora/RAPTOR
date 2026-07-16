<?php
/**
 * Migration 0021 — Granular RBAC
 *
 * Adds:
 *  - module + action columns to permissions (keeps permission_name for backward compat)
 *  - is_system column to roles (prevents admin deletion)
 *  - scope column to role_permissions
 *  - user_permission_overrides table (per-user grant/revoke)
 *  - Seeds all 6 canonical roles + full granular permission matrix
 *
 * Runs via bin/migrate.php with $db (PDO) in scope.
 * All operations are additive (no DROP, no TRUNCATE).
 */

$columnExists = function (PDO $db, string $table, string $column): bool {
    $stmt = $db->prepare(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c"
    );
    $stmt->execute([':t' => $table, ':c' => $column]);
    return (int) $stmt->fetchColumn() > 0;
};

$tableExists = function (PDO $db, string $table): bool {
    $stmt = $db->prepare(
        "SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t"
    );
    $stmt->execute([':t' => $table]);
    return (int) $stmt->fetchColumn() > 0;
};

// ─── 1. Extend permissions table ───────────────────────────────────────────
if (!$columnExists($db, 'permissions', 'module')) {
    $db->exec("ALTER TABLE permissions ADD COLUMN module VARCHAR(60) NULL AFTER permission_id");
    echo "    + permissions.module added\n";
}
if (!$columnExists($db, 'permissions', 'action')) {
    $db->exec("ALTER TABLE permissions ADD COLUMN action VARCHAR(60) NULL AFTER module");
    echo "    + permissions.action added\n";
}
if (!$columnExists($db, 'permissions', 'description')) {
    $db->exec("ALTER TABLE permissions ADD COLUMN description TEXT NULL");
    echo "    + permissions.description added\n";
}

// ─── 2. Extend roles table ──────────────────────────────────────────────────
if (!$columnExists($db, 'roles', 'is_system')) {
    $db->exec("ALTER TABLE roles ADD COLUMN is_system TINYINT(1) NOT NULL DEFAULT 0 AFTER description");
    echo "    + roles.is_system added\n";
}

// ─── 3. Extend role_permissions table ──────────────────────────────────────
if (!$columnExists($db, 'role_permissions', 'scope')) {
    $db->exec("ALTER TABLE role_permissions ADD COLUMN scope ENUM('own','team','all') NULL AFTER permission_id");
    echo "    + role_permissions.scope added\n";
}

// ─── 4. user_permission_overrides table ────────────────────────────────────
if (!$tableExists($db, 'user_permission_overrides')) {
    $db->exec("
        CREATE TABLE user_permission_overrides (
            override_id   INT AUTO_INCREMENT PRIMARY KEY,
            user_id       INT NOT NULL,
            permission_id INT NOT NULL,
            scope         ENUM('own','team','all') NULL,
            type          ENUM('grant','revoke') NOT NULL DEFAULT 'grant',
            created_by    INT NULL,
            created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_user_perm (user_id, permission_id, type),
            FOREIGN KEY (user_id)   REFERENCES users(user_id) ON DELETE CASCADE,
            FOREIGN KEY (permission_id) REFERENCES permissions(permission_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "    + user_permission_overrides table created\n";
}

// ─── 5. Ensure all 6 canonical roles exist ─────────────────────────────────
$ensureRole = function (PDO $db, string $name, string $description, bool $isSystem = false): int {
    $stmt = $db->prepare('SELECT role_id FROM roles WHERE role_name = :name LIMIT 1');
    $stmt->execute([':name' => $name]);
    $id = $stmt->fetchColumn();
    if ($id === false) {
        $stmt = $db->prepare('INSERT INTO roles (role_name, description, is_system) VALUES (:n, :d, :s)');
        $stmt->execute([':n' => $name, ':d' => $description, ':s' => (int) $isSystem]);
        $id = (int) $db->lastInsertId();
        echo "    + role '{$name}' added\n";
    } else {
        // Ensure is_system flag is correct for admin
        if ($isSystem) {
            $db->prepare('UPDATE roles SET is_system = 1 WHERE role_id = :id')
               ->execute([':id' => $id]);
        }
        echo "    = role '{$name}' already exists\n";
    }
    return (int) $id;
};

$roleIds = [];
$roleIds['admin']       = $ensureRole($db, 'admin',       'Full system administrator with unrestricted access.', true);
$roleIds['hr']          = $ensureRole($db, 'hr',          'HR management: employees, attendance, leave, payroll prep.');
$roleIds['finance']     = $ensureRole($db, 'finance',     'Finance management: invoices, payments, payroll approval, expenses.');
$roleIds['analyst']     = $ensureRole($db, 'analyst',     'Read-only access across all modules with analytics and reporting.');
$roleIds['manager']     = $ensureRole($db, 'manager',     'Team lead: oversees CRM, projects, team attendance and performance.');
$roleIds['employee']    = $ensureRole($db, 'employee',    'Employee self-service: own attendance, payslips, tasks, expenses.');

// Also keep team_leader if present (legacy)
$ensureRole($db, 'team_leader', 'Legacy team leader role. Use manager for new assignments.');

// ─── 6. Seed granular permissions ──────────────────────────────────────────
// Format: [module, action, description]
$permissionMatrix = [
    // Dashboard
    ['dashboard',   'view',    'View dashboard pages'],
    ['dashboard',   'manage',  'Manage dashboard templates'],
    // Employees / HR
    ['employees',   'view',    'View employee directory'],
    ['employees',   'create',  'Add new employee accounts'],
    ['employees',   'edit',    'Edit employee profiles'],
    ['employees',   'delete',  'Archive/deactivate employees'],
    ['employees',   'export',  'Export employee data'],
    // Attendance
    ['attendance',  'view',    'View attendance records'],
    ['attendance',  'mark',    'Mark own check-in/check-out'],
    ['attendance',  'approve', 'Approve attendance exceptions'],
    ['attendance',  'export',  'Export attendance report'],
    // Leave
    ['leave',       'view',    'View leave records'],
    ['leave',       'apply',   'Apply for leave'],
    ['leave',       'approve', 'Approve leave requests'],
    ['leave',       'delete',  'Delete leave records'],
    // Payroll
    ['payroll',     'view',    'View payroll runs and payslips'],
    ['payroll',     'create',  'Create payroll runs'],
    ['payroll',     'edit',    'Edit salary structures'],
    ['payroll',     'approve', 'Approve and disburse payroll'],
    ['payroll',     'export',  'Export payroll reports'],
    // Expenses / Reimbursements
    ['expenses',    'view',    'View expenses and reimbursements'],
    ['expenses',    'create',  'Submit expense claims'],
    ['expenses',    'approve', 'Approve expense reimbursements'],
    ['expenses',    'export',  'Export expense reports'],
    // Invoices
    ['invoices',    'view',    'View billing ledger and invoices'],
    ['invoices',    'create',  'Generate new invoices'],
    ['invoices',    'edit',    'Edit invoice details (UTR, cancel)'],
    ['invoices',    'export',  'Export invoices'],
    // CRM — Leads
    ['crm_leads',   'view',    'View leads'],
    ['crm_leads',   'create',  'Create leads'],
    ['crm_leads',   'edit',    'Edit lead details'],
    ['crm_leads',   'assign',  'Assign leads to team members'],
    ['crm_leads',   'manage',  'Full lead management'],
    ['crm_leads',   'export',  'Export leads'],
    // Customers / Clients
    ['customers',   'view',    'View client accounts'],
    ['customers',   'create',  'Add client accounts'],
    ['customers',   'edit',    'Edit client accounts'],
    ['customers',   'manage',  'Full client management'],
    // Projects
    ['projects',    'view',    'View projects'],
    ['projects',    'create',  'Create projects'],
    ['projects',    'edit',    'Edit projects'],
    ['projects',    'manage',  'Full project management'],
    // Tasks
    ['tasks',       'view',    'View task board'],
    ['tasks',       'create',  'Create tasks'],
    ['tasks',       'edit',    'Edit tasks'],
    ['tasks',       'assign',  'Assign tasks to team members'],
    // Social Media
    ['social_media','view',    'View social media stats and content'],
    ['social_media','create',  'Post social media content'],
    ['social_media','edit',    'Edit social media content'],
    ['social_media','manage',  'Full social media management'],
    // Reports
    ['reports',     'view',    'View reports'],
    ['reports',     'export',  'Export/download reports'],
    // Analytics
    ['analytics',   'view',    'View analytics dashboards'],
    ['analytics',   'export',  'Export analytics data'],
    // Settings
    ['settings',    'view',    'View system settings'],
    ['settings',    'manage',  'Modify system settings'],
    // Roles & Permissions
    ['roles',       'view',    'View roles and permission matrix'],
    ['roles',       'manage',  'Create, edit, delete roles and permissions'],
    // Users (access management)
    ['users',       'view',    'View user accounts'],
    ['users',       'manage',  'Create, edit, suspend users and override permissions'],
    // Audit logs
    ['audit_logs',  'view',    'View system audit logs'],
    // Notifications
    ['notifications','view',   'View notifications'],
    // Documents
    ['documents',   'view',    'View documents'],
    ['documents',   'create',  'Upload documents'],
    ['documents',   'edit',    'Edit documents'],
    ['documents',   'delete',  'Delete documents'],
];

$permIds = [];
foreach ($permissionMatrix as [$module, $action, $desc]) {
    $permName = $module . '.' . $action;
    // Upsert: update module/action/description if row exists by permission_name
    $stmt = $db->prepare('SELECT permission_id FROM permissions WHERE permission_name = :name LIMIT 1');
    $stmt->execute([':name' => $permName]);
    $existingId = $stmt->fetchColumn();
    if ($existingId === false) {
        $stmt = $db->prepare(
            'INSERT INTO permissions (permission_name, module, action, description) VALUES (:name, :mod, :act, :desc)'
        );
        $stmt->execute([':name' => $permName, ':mod' => $module, ':act' => $action, ':desc' => $desc]);
        $permIds[$permName] = (int) $db->lastInsertId();
    } else {
        // Update module/action if NULL (old rows)
        $db->prepare('UPDATE permissions SET module = :mod, action = :act, description = :desc WHERE permission_id = :id')
           ->execute([':mod' => $module, ':act' => $action, ':desc' => $desc, ':id' => $existingId]);
        $permIds[$permName] = (int) $existingId;
    }
}
echo "    + " . count($permIds) . " granular permissions seeded/updated\n";

// ─── 7. Seed role_permissions with scopes ──────────────────────────────────
// Format: [role, module, action, scope|null]
$rolePermMatrix = [
    // ── ADMIN: all permissions, scope=all ──────────────────────────────────
    // (We'll grant all in a loop below)

    // ── HR ─────────────────────────────────────────────────────────────────
    ['hr', 'dashboard',    'view',    null],
    ['hr', 'employees',    'view',    'all'],
    ['hr', 'employees',    'create',  'all'],
    ['hr', 'employees',    'edit',    'all'],
    ['hr', 'employees',    'export',  'all'],
    ['hr', 'attendance',   'view',    'all'],
    ['hr', 'attendance',   'mark',    'all'],
    ['hr', 'attendance',   'approve', 'all'],
    ['hr', 'attendance',   'export',  'all'],
    ['hr', 'leave',        'view',    'all'],
    ['hr', 'leave',        'apply',   'own'],
    ['hr', 'leave',        'approve', 'all'],
    ['hr', 'payroll',      'view',    'all'],
    ['hr', 'payroll',      'create',  'all'],
    ['hr', 'payroll',      'edit',    'all'],
    ['hr', 'expenses',     'view',    'all'],
    ['hr', 'users',        'view',    'all'],
    ['hr', 'reports',      'view',    'all'],
    ['hr', 'reports',      'export',  'all'],
    ['hr', 'analytics',    'view',    'all'],
    ['hr', 'documents',    'view',    'all'],
    ['hr', 'documents',    'create',  'all'],
    ['hr', 'documents',    'edit',    'all'],
    ['hr', 'notifications','view',    null],

    // ── FINANCE ────────────────────────────────────────────────────────────
    ['finance', 'dashboard',    'view',    null],
    ['finance', 'payroll',      'view',    'all'],
    ['finance', 'payroll',      'create',  'all'],
    ['finance', 'payroll',      'edit',    'all'],
    ['finance', 'payroll',      'approve', 'all'],
    ['finance', 'payroll',      'export',  'all'],
    ['finance', 'expenses',     'view',    'all'],
    ['finance', 'expenses',     'approve', 'all'],
    ['finance', 'expenses',     'export',  'all'],
    ['finance', 'invoices',     'view',    'all'],
    ['finance', 'invoices',     'create',  'all'],
    ['finance', 'invoices',     'edit',    'all'],
    ['finance', 'invoices',     'export',  'all'],
    ['finance', 'projects',     'view',    'all'],
    ['finance', 'reports',      'view',    'all'],
    ['finance', 'reports',      'export',  'all'],
    ['finance', 'analytics',    'view',    'all'],
    ['finance', 'notifications','view',    null],

    // ── ANALYST (read-only everywhere) ─────────────────────────────────────
    ['analyst', 'dashboard',    'view',    null],
    ['analyst', 'employees',    'view',    'all'],
    ['analyst', 'attendance',   'view',    'all'],
    ['analyst', 'leave',        'view',    'all'],
    ['analyst', 'payroll',      'view',    'all'],
    ['analyst', 'expenses',     'view',    'all'],
    ['analyst', 'invoices',     'view',    'all'],
    ['analyst', 'crm_leads',    'view',    'all'],
    ['analyst', 'customers',    'view',    'all'],
    ['analyst', 'projects',     'view',    'all'],
    ['analyst', 'tasks',        'view',    'all'],
    ['analyst', 'social_media', 'view',    'all'],
    ['analyst', 'reports',      'view',    'all'],
    ['analyst', 'reports',      'export',  'all'],
    ['analyst', 'analytics',    'view',    'all'],
    ['analyst', 'analytics',    'export',  'all'],
    ['analyst', 'documents',    'view',    'all'],
    ['analyst', 'notifications','view',    null],
    ['analyst', 'dashboard',    'manage',  null],

    // ── MANAGER ────────────────────────────────────────────────────────────
    ['manager', 'dashboard',    'view',    null],
    ['manager', 'dashboard',    'manage',  null],
    ['manager', 'employees',    'view',    'team'],
    ['manager', 'attendance',   'view',    'team'],
    ['manager', 'attendance',   'approve', 'team'],
    ['manager', 'leave',        'view',    'team'],
    ['manager', 'leave',        'approve', 'team'],
    ['manager', 'expenses',     'view',    'team'],
    ['manager', 'expenses',     'approve', 'team'],
    ['manager', 'payroll',      'view',    'team'],
    ['manager', 'crm_leads',    'view',    'team'],
    ['manager', 'crm_leads',    'create',  'all'],
    ['manager', 'crm_leads',    'edit',    'team'],
    ['manager', 'crm_leads',    'assign',  'team'],
    ['manager', 'crm_leads',    'manage',  'team'],
    ['manager', 'customers',    'view',    'all'],
    ['manager', 'customers',    'manage',  'all'],
    ['manager', 'projects',     'view',    'all'],
    ['manager', 'projects',     'manage',  'all'],
    ['manager', 'tasks',        'view',    'team'],
    ['manager', 'tasks',        'create',  'all'],
    ['manager', 'tasks',        'assign',  'team'],
    ['manager', 'social_media', 'view',    'all'],
    ['manager', 'social_media', 'manage',  'all'],
    ['manager', 'reports',      'view',    'team'],
    ['manager', 'analytics',    'view',    'team'],
    ['manager', 'invoices',     'view',    'all'],
    ['manager', 'notifications','view',    null],

    // ── EMPLOYEE ───────────────────────────────────────────────────────────
    ['employee', 'dashboard',    'view',    null],
    ['employee', 'employees',    'view',    'own'],
    ['employee', 'attendance',   'view',    'own'],
    ['employee', 'attendance',   'mark',    'own'],
    ['employee', 'leave',        'view',    'own'],
    ['employee', 'leave',        'apply',   'own'],
    ['employee', 'payroll',      'view',    'own'],
    ['employee', 'expenses',     'view',    'own'],
    ['employee', 'expenses',     'create',  'own'],
    ['employee', 'crm_leads',    'view',    'own'],
    ['employee', 'tasks',        'view',    'own'],
    ['employee', 'social_media', 'view',    'own'],
    ['employee', 'reports',      'view',    'own'],
    ['employee', 'notifications','view',    null],
];

// Grant all permissions to admin
foreach ($permIds as $permName => $permId) {
    $rid = $roleIds['admin'];
    $db->prepare('INSERT IGNORE INTO role_permissions (role_id, permission_id, scope) VALUES (:rid, :pid, :scope)')
       ->execute([':rid' => $rid, ':pid' => $permId, ':scope' => 'all']);
}
echo "    + admin: all permissions granted (scope=all)\n";

// Grant role-specific permissions
$grantedCount = 0;
foreach ($rolePermMatrix as [$roleName, $module, $action, $scope]) {
    $rid = $roleIds[$roleName] ?? null;
    if (!$rid) continue;
    $permName = $module . '.' . $action;
    $pid = $permIds[$permName] ?? null;
    if (!$pid) continue;

    // INSERT or update scope (composite PK: role_id, permission_id)
    $db->prepare(
        'INSERT INTO role_permissions (role_id, permission_id, scope) VALUES (:r, :p, :s)
         ON DUPLICATE KEY UPDATE scope = VALUES(scope)'
    )->execute([':r' => $rid, ':p' => $pid, ':s' => $scope]);
    $grantedCount++;
}
echo "    + {$grantedCount} role-permission assignments seeded\n";

// ─── 8. Mark admin role as system-protected ────────────────────────────────
$db->prepare('UPDATE roles SET is_system = 1 WHERE role_name = :name')
   ->execute([':name' => 'admin']);

echo "  [OK] Granular RBAC migration complete.\n";
