<?php
/**
 * Six-month demo data loader for Raptor Sales Monitoring CRM.
 *
 * Creates:
 * - 2 Admins
 * - 2 HR users
 * - 2 Analysts
 * - 10 Managers
 * - 90 Employees
 * - 10 teams with 9 employees each
 * - Six months of attendance, leads, tasks, follow-ups, communications,
 *   meetings, targets, notifications, reports/dashboard preferences, and
 *   manager edit requests where those tables exist.
 *
 * Usage:
 *   php bin/seed_6_month_demo_data.php --fresh
 *
 * Default password for all demo users:
 *   Raptor@12345
 */

require_once dirname(__DIR__) . '/app/config/config.php';
require_once dirname(__DIR__) . '/app/core/Database.php';

$fresh = in_array('--fresh', $argv, true);
$db = Database::getInstance()->getConnection();
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
srand(20260706);

echo "=== Raptor CRM six-month demo data loader ===\n";

function tableExists(PDO $db, string $table): bool {
    $stmt = $db->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t");
    $stmt->execute([':t' => $table]);
    return (int) $stmt->fetchColumn() > 0;
}

function columns(PDO $db, string $table): array {
    static $cache = [];
    if (isset($cache[$table])) {
        return $cache[$table];
    }
    $stmt = $db->prepare("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t");
    $stmt->execute([':t' => $table]);
    $cache[$table] = array_flip($stmt->fetchAll(PDO::FETCH_COLUMN));
    return $cache[$table];
}

function hasCol(PDO $db, string $table, string $column): bool {
    $cols = columns($db, $table);
    return isset($cols[$column]);
}

function insertRow(PDO $db, string $table, array $data) {
    if (!tableExists($db, $table)) {
        return null;
    }
    $cols = columns($db, $table);
    $data = array_intersect_key($data, $cols);
    if (!$data) {
        return null;
    }
    $names = array_keys($data);
    $placeholders = array_map(fn($name) => ':' . $name, $names);
    $sql = 'INSERT INTO `' . $table . '` (`' . implode('`,`', $names) . '`) VALUES (' . implode(',', $placeholders) . ')';
    $stmt = $db->prepare($sql);
    foreach ($data as $key => $value) {
        $stmt->bindValue(':' . $key, $value);
    }
    $stmt->execute();
    return $db->lastInsertId();
}

function updateRow(PDO $db, string $table, array $data, string $pk, int $id): void {
    if (!tableExists($db, $table)) {
        return;
    }
    $cols = columns($db, $table);
    $data = array_intersect_key($data, $cols);
    unset($data[$pk]);
    if (!$data || !isset($cols[$pk])) {
        return;
    }
    $sets = [];
    foreach (array_keys($data) as $field) {
        $sets[] = "`$field` = :$field";
    }
    $stmt = $db->prepare('UPDATE `' . $table . '` SET ' . implode(', ', $sets) . ' WHERE `' . $pk . '` = :id');
    foreach ($data as $key => $value) {
        $stmt->bindValue(':' . $key, $value);
    }
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
}

function ensureRole(PDO $db, string $name, string $description): int {
    $stmt = $db->prepare('SELECT role_id FROM roles WHERE role_name = :name');
    $stmt->execute([':name' => $name]);
    $id = $stmt->fetchColumn();
    if ($id !== false) {
        return (int) $id;
    }
    return (int) insertRow($db, 'roles', ['role_name' => $name, 'description' => $description]);
}

function ensurePermission(PDO $db, string $name, string $description): int {
    $stmt = $db->prepare('SELECT permission_id FROM permissions WHERE permission_name = :name');
    $stmt->execute([':name' => $name]);
    $id = $stmt->fetchColumn();
    if ($id !== false) {
        return (int) $id;
    }
    return (int) insertRow($db, 'permissions', ['permission_name' => $name, 'description' => $description]);
}

function grant(PDO $db, int $roleId, int $permissionId): void {
    if (!tableExists($db, 'role_permissions')) {
        return;
    }
    $stmt = $db->prepare('INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (:rid, :pid)');
    $stmt->execute([':rid' => $roleId, ':pid' => $permissionId]);
}

function dateBetween(string $start, string $end): string {
    $from = strtotime($start);
    $to = strtotime($end);
    return date('Y-m-d', rand($from, $to));
}

function dt(string $date, int $hour, int $minute = 0): string {
    return $date . ' ' . sprintf('%02d:%02d:00', $hour, $minute);
}

function pick(array $items) {
    return $items[array_rand($items)];
}

try {
    if ($fresh) {
        echo "[INFO] Fresh mode: clearing demo-capable operational tables...\n";
        $tables = [
            'dashboard_templates', 'dashboard_preferences', 'data_edit_requests',
            'notifications', 'report_summaries', 'security_events', 'rate_limits', 'login_attempts',
            'performance_scores', 'manager_reviews', 'target_progress', 'target_items', 'targets',
            'meeting_checkins', 'meetings', 'communications', 'follow_ups', 'tasks',
            'lead_assignments', 'lead_status_history', 'customer_journey_log', 'attribution_touchpoints',
            'web_behavior_sessions', 'leads', 'payments', 'invoices', 'campaigns', 'clients',
            'travel_summary', 'location_logs', 'attendance_breaks', 'attendance',
            'employees', 'teams', 'branches', 'territories', 'geofences',
            'activity_logs', 'users'
        ];
        $db->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach ($tables as $table) {
            if (tableExists($db, $table)) {
                $db->exec('TRUNCATE TABLE `' . $table . '`');
            }
        }
        $db->exec('SET FOREIGN_KEY_CHECKS = 1');
    }

    echo "[INFO] Ensuring roles and permissions...\n";
    $roleIds = [
        'admin' => ensureRole($db, 'admin', 'Full system administrator'),
        'manager' => ensureRole($db, 'manager', 'Manager with subordinate task and dashboard access'),
        'employee' => ensureRole($db, 'employee', 'Employee self-service role'),
        'hr' => ensureRole($db, 'hr', 'HR employee management role'),
        'analyst' => ensureRole($db, 'analyst', 'Analyst dashboard and reports role'),
        'employer' => ensureRole($db, 'employer', 'Read-only executive viewer'),
        'team_leader' => ensureRole($db, 'team_leader', 'Legacy team oversight role'),
    ];

    $permissions = [
        'manage_employees', 'assign_employee_roles', 'view_employee_directory',
        'request_data_edit', 'approve_data_edit', 'create_dashboard_templates',
        'manage_dashboard_templates', 'archive_records', 'view_team_monitoring',
        'approve_attendance', 'assign_leads', 'manage_targets', 'manage_tasks',
        'manage_reports', 'manage_settings'
    ];
    $permissionIds = [];
    foreach ($permissions as $permission) {
        $permissionIds[$permission] = ensurePermission($db, $permission, ucwords(str_replace('_', ' ', $permission)));
    }
    foreach ($permissionIds as $pid) {
        grant($db, $roleIds['admin'], $pid);
    }
    foreach (['manage_employees', 'assign_employee_roles', 'view_employee_directory'] as $permission) {
        grant($db, $roleIds['hr'], $permissionIds[$permission]);
    }
    foreach (['request_data_edit', 'view_team_monitoring', 'assign_leads', 'manage_targets', 'manage_tasks'] as $permission) {
        grant($db, $roleIds['manager'], $permissionIds[$permission]);
    }
    foreach (['create_dashboard_templates', 'manage_reports'] as $permission) {
        grant($db, $roleIds['analyst'], $permissionIds[$permission]);
    }

    $passwordHash = password_hash('Raptor@12345', PASSWORD_BCRYPT, ['cost' => 10]);
    $users = [];
    $employees = [];
    $managers = [];
    $admins = [];
    $hrs = [];
    $analysts = [];

    $createUser = function (string $role, string $name, string $email, ?int $managerId = null, ?int $teamId = null, string $department = 'Sales', string $title = '') use ($db, $roleIds, $passwordHash, &$users, &$employees) {
        $id = insertRow($db, 'users', [
            'role_id' => $roleIds[$role],
            'name' => $name,
            'email' => $email,
            'password' => $passwordHash,
            'status' => 'active',
        ]);
        $id = (int) $id;
        $users[$email] = $id;
        insertRow($db, 'employees', [
            'user_id' => $id,
            'employee_code' => 'RPT-' . str_pad((string) $id, 4, '0', STR_PAD_LEFT),
            'department' => $department,
            'job_title' => $title ?: ucwords(str_replace('_', ' ', $role)),
            'reporting_manager_id' => $managerId,
            'team_id' => $teamId,
            'branch_id' => null,
            'hire_date' => dateBetween('2024-01-01', '2026-01-15'),
            'status' => 'active',
        ]);
        if ($role === 'employee') {
            $employees[] = $id;
        }
        return $id;
    };

    echo "[INFO] Creating users: 2 admin, 2 HR, 2 analyst, 10 managers, 90 employees...\n";
    for ($i = 1; $i <= 2; $i++) {
        $admins[] = $createUser('admin', "Raptor Admin $i", "admin$i@raptor.test", null, null, 'Administration', 'System Administrator');
        $hrs[] = $createUser('hr', "Raptor HR $i", "hr$i@raptor.test", null, null, 'Human Resources', 'HR Manager');
        $analysts[] = $createUser('analyst', "Raptor Analyst $i", "analyst$i@raptor.test", null, null, 'Analytics', 'Business Analyst');
    }

    $branchIds = [];
    $territoryIds = [];
    for ($i = 1; $i <= 5; $i++) {
        $branchIds[] = (int) insertRow($db, 'branches', [
            'name' => "Raptor Branch $i",
            'address' => "{$i}00 Market Street, Demo City",
            'lat' => 12.9000000 + ($i / 100),
            'lng' => 77.5000000 + ($i / 100),
            'status' => 'active',
        ]);
        $territoryIds[] = (int) insertRow($db, 'territories', [
            'name' => "Territory $i",
            'description' => "Demo sales territory $i",
            'status' => 'active',
        ]);
    }

    $teamIds = [];
    for ($i = 1; $i <= 10; $i++) {
        $managerId = $createUser('manager', "Sales Manager $i", "manager$i@raptor.test", null, null, 'Sales', 'Regional Sales Manager');
        $managers[] = $managerId;
        $teamId = (int) insertRow($db, 'teams', [
            'name' => "Team " . chr(64 + $i),
            'team_leader_user_id' => null,
            'manager_user_id' => $managerId,
            'branch_id' => $branchIds[($i - 1) % count($branchIds)] ?: null,
            'territory_id' => $territoryIds[($i - 1) % count($territoryIds)] ?: null,
            'status' => 'active',
        ]);
        $teamIds[] = $teamId;
        updateRow($db, 'employees', ['team_id' => $teamId, 'branch_id' => $branchIds[($i - 1) % count($branchIds)] ?: null], 'user_id', $managerId);
    }

    $first = ['Aarav','Vivaan','Aditya','Kabir','Reyansh','Arjun','Sai','Ishaan','Vihaan','Anaya','Diya','Myra','Sara','Ira','Aanya','Riya','Nora','Tara'];
    $last = ['Sharma','Patel','Reddy','Nair','Kapoor','Mehta','Iyer','Khan','Rao','Das','Menon','Joshi','Bose','Gill','Sinha'];
    for ($i = 1; $i <= 90; $i++) {
        $managerIndex = (int) floor(($i - 1) / 9);
        $name = pick($first) . ' ' . pick($last) . ' ' . $i;
        $employeeId = $createUser(
            'employee',
            $name,
            'employee' . $i . '@raptor.test',
            $managers[$managerIndex],
            $teamIds[$managerIndex],
            'Field Sales',
            'Sales Executive'
        );
        updateRow($db, 'employees', ['branch_id' => $branchIds[$managerIndex % count($branchIds)] ?: null], 'user_id', $employeeId);
    }

    echo "[OK] User hierarchy ready.\n";

    echo "[INFO] Seeding clients, campaigns, invoices, and leads...\n";
    $clientIds = [];
    $industries = ['Healthcare', 'Retail', 'SaaS', 'Education', 'Manufacturing', 'Finance', 'Real Estate', 'Logistics', 'Hospitality', 'Automotive'];
    for ($i = 1; $i <= 18; $i++) {
        $clientIds[] = (int) insertRow($db, 'clients', [
            'company_name' => "Demo Client $i " . $industries[($i - 1) % count($industries)],
            'email' => "client$i@example.test",
            'phone' => '+91-90000-' . str_pad((string) $i, 4, '0', STR_PAD_LEFT),
            'status' => 'active',
            'contract_start' => '2026-01-01',
            'contract_end' => '2026-12-31',
            'package_details' => pick(['Growth plan', 'Enterprise retainer', 'Field activation package']),
            'billing_address' => "$i Demo Business Park",
        ]);
    }

    $campaignIds = [];
    $channels = ['LinkedIn', 'Google Search', 'Facebook', 'Instagram', 'Email', 'Referral'];
    foreach ($clientIds as $clientId) {
        for ($i = 1; $i <= 2; $i++) {
            $budget = rand(400000, 1400000) / 10;
            $spend = rand(150000, (int) ($budget * 10)) / 10;
            $campaignIds[] = (int) insertRow($db, 'campaigns', [
                'client_id' => $clientId,
                'name' => pick($channels) . " Growth Campaign $i",
                'channel' => pick($channels),
                'budget' => $budget,
                'spend' => $spend,
                'revenue_influenced' => $spend * (rand(140, 420) / 100),
                'start_date' => dateBetween('2026-01-07', '2026-05-20'),
                'end_date' => dateBetween('2026-06-01', '2026-07-06'),
                'status' => pick(['active', 'active', 'completed', 'paused']),
            ]);
        }
    }

    $leadIds = [];
    $leadStatuses = ['new', 'contacted', 'qualified', 'proposal', 'converted', 'lost'];
    $qualities = ['hot', 'warm', 'cold'];
    for ($i = 1; $i <= 900; $i++) {
        $status = pick($leadStatuses);
        $created = dateBetween('2026-01-07', '2026-07-06');
        $employeeId = $employees[array_rand($employees)];
        $leadIds[] = (int) insertRow($db, 'leads', [
            'client_id' => $clientIds[array_rand($clientIds)],
            'assigned_to_user_id' => $employeeId,
            'team_id' => $teamIds[array_rand($teamIds)] ?: null,
            'first_name' => pick($first),
            'last_name' => pick($last),
            'company_name' => 'Prospect Co ' . $i,
            'email' => "prospect$i@example.test",
            'phone' => '+91-98888-' . str_pad((string) $i, 4, '0', STR_PAD_LEFT),
            'status' => $status,
            'lead_quality' => pick($qualities),
            'conversion_probability' => rand(10, 95),
            'probability' => rand(10, 95),
            'lead_value' => rand(25000, 750000),
            'lead_source' => pick($channels),
            'campaign_source' => 'Demo Campaign',
            'priority' => pick(['low', 'medium', 'high', 'urgent']),
            'created_at' => dt($created, rand(9, 18), rand(0, 59)),
            'converted_at' => $status === 'converted' ? dt(dateBetween($created, '2026-07-06'), rand(10, 18)) : null,
        ]);
    }

    for ($i = 1; $i <= 72; $i++) {
        $clientId = $clientIds[array_rand($clientIds)];
        $status = $i % 5 === 0 ? 'overdue' : ($i % 3 === 0 ? 'unpaid' : 'paid');
        insertRow($db, 'invoices', [
            'client_id' => $clientId,
            'invoice_number' => 'INV-DEMO-' . str_pad((string) $i, 4, '0', STR_PAD_LEFT),
            'amount' => rand(30000, 250000),
            'status' => $status,
            'due_date' => dateBetween('2026-01-15', '2026-07-20'),
            'created_at' => dt(dateBetween('2026-01-07', '2026-07-06'), 10),
        ]);
    }

    echo "[INFO] Seeding six months of attendance and field activity...\n";
    $start = new DateTime('2026-01-07');
    $end = new DateTime('2026-07-06');
    $attendanceCount = 0;
    for ($date = clone $start; $date <= $end; $date->modify('+1 day')) {
        $day = (int) $date->format('N');
        if ($day >= 7) {
            continue;
        }
        $workDate = $date->format('Y-m-d');
        foreach ($employees as $employeeId) {
            if (rand(1, 100) <= 8) {
                continue;
            }
            $late = rand(1, 100) <= 14;
            $loginHour = $late ? rand(10, 11) : rand(8, 9);
            $loginMinute = $late ? rand(0, 45) : rand(0, 55);
            $logoutHour = rand(17, 19);
            $attendanceId = insertRow($db, 'attendance', [
                'user_id' => $employeeId,
                'work_date' => $workDate,
                'login_at' => dt($workDate, $loginHour, $loginMinute),
                'logout_at' => dt($workDate, $logoutHour, rand(0, 55)),
                'login_lat' => 12.9716 + (rand(-1000, 1000) / 100000),
                'login_lng' => 77.5946 + (rand(-1000, 1000) / 100000),
                'logout_lat' => 12.9716 + (rand(-1000, 1000) / 100000),
                'logout_lng' => 77.5946 + (rand(-1000, 1000) / 100000),
                'status' => 'present',
                'is_late' => $late ? 1 : 0,
                'worked_minutes' => rand(420, 560),
                'approval_status' => rand(1, 100) <= 4 ? 'pending' : 'approved',
            ]);
            $attendanceCount++;
            for ($p = 0; $p < 3; $p++) {
                insertRow($db, 'location_logs', [
                    'user_id' => $employeeId,
                    'captured_at' => dt($workDate, 10 + ($p * 2), rand(0, 59)),
                    'lat' => 12.9716 + (rand(-2000, 2000) / 100000),
                    'lng' => 77.5946 + (rand(-2000, 2000) / 100000),
                    'accuracy_m' => rand(8, 80),
                    'source' => 'periodic',
                ]);
            }
            insertRow($db, 'travel_summary', [
                'user_id' => $employeeId,
                'work_date' => $workDate,
                'distance_km' => rand(20, 180) / 10,
                'points_count' => 3,
                'first_at' => dt($workDate, $loginHour, $loginMinute),
                'last_at' => dt($workDate, $logoutHour, 0),
                'route_polyline' => json_encode([[12.9716, 77.5946], [12.9816, 77.6046]]),
            ]);
        }
    }

    echo "[INFO] Seeding tasks, follow-ups, communications, meetings, targets, and dashboards...\n";
    $taskTitles = ['Visit prospect', 'Submit quotation', 'Update lead notes', 'Collect payment proof', 'Product demo', 'Follow-up call', 'Market survey'];
    $taskIds = [];
    for ($i = 1; $i <= 1800; $i++) {
        $employeeId = $employees[array_rand($employees)];
        $managerId = $managers[(int) floor(array_search($employeeId, $employees, true) / 9)] ?? $managers[array_rand($managers)];
        $deadline = dateBetween('2026-01-07', '2026-07-06');
        $status = pick(['pending', 'in_progress', 'completed', 'completed', 'completed']);
        $taskIds[] = (int) insertRow($db, 'tasks', [
            'assigned_to_user_id' => $employeeId,
            'created_by_user_id' => $managerId,
            'title' => pick($taskTitles),
            'description' => 'Demo task generated for six-month testing.',
            'start_date' => dt($deadline, 9),
            'priority' => pick(['low', 'medium', 'high']),
            'deadline' => dt($deadline, rand(15, 18)),
            'completed_at' => $status === 'completed' ? dt($deadline, rand(15, 19)) : null,
            'status' => $status,
            'progress_percent' => $status === 'completed' ? 100 : rand(0, 85),
            'estimated_hours' => rand(1, 8),
            'actual_hours' => $status === 'completed' ? rand(1, 9) : 0,
            'review_status' => $status === 'completed' ? pick(['pending_review', 'approved', 'approved', 'rejected']) : 'not_submitted',
            'remarks' => 'Seeded demo task.',
        ]);
    }

    for ($i = 1; $i <= 1200; $i++) {
        $employeeId = $employees[array_rand($employees)];
        $leadId = $leadIds[array_rand($leadIds)];
        $due = dateBetween('2026-01-07', '2026-07-06');
        insertRow($db, 'follow_ups', [
            'lead_id' => $leadId,
            'assigned_to_user_id' => $employeeId,
            'created_by_user_id' => $managers[array_rand($managers)],
            'channel' => pick(['call', 'whatsapp', 'email', 'meeting']),
            'due_at' => dt($due, rand(9, 18), rand(0, 59)),
            'status' => pick(['scheduled', 'completed', 'completed', 'missed']),
            'note' => 'Seeded follow-up.',
            'completed_at' => rand(1, 100) <= 65 ? dt($due, rand(10, 19)) : null,
        ]);
    }

    for ($i = 1; $i <= 2200; $i++) {
        $employeeId = $employees[array_rand($employees)];
        $happened = dateBetween('2026-01-07', '2026-07-06');
        insertRow($db, 'communications', [
            'lead_id' => $leadIds[array_rand($leadIds)],
            'user_id' => $employeeId,
            'channel' => pick(['call', 'whatsapp', 'sms', 'email']),
            'direction' => pick(['made', 'received', 'sent']),
            'duration_seconds' => rand(60, 1800),
            'outcome' => pick(['Interested', 'No response', 'Demo booked', 'Negotiation', 'Follow-up needed']),
            'note' => 'Seeded communication log.',
            'happened_at' => dt($happened, rand(9, 19), rand(0, 59)),
        ]);
    }

    for ($i = 1; $i <= 750; $i++) {
        $employeeId = $employees[array_rand($employees)];
        $day = dateBetween('2026-01-07', '2026-07-06');
        $meetingId = insertRow($db, 'meetings', [
            'lead_id' => $leadIds[array_rand($leadIds)],
            'assigned_to_user_id' => $employeeId,
            'created_by_user_id' => $managers[array_rand($managers)],
            'type' => pick(['meeting', 'demo']),
            'title' => pick(['Product demo', 'Discovery meeting', 'Proposal review', 'Renewal discussion']),
            'scheduled_start' => dt($day, rand(10, 17), 0),
            'scheduled_end' => dt($day, rand(11, 18), 0),
            'location' => 'Client office',
            'status' => pick(['scheduled', 'completed', 'completed', 'cancelled']),
            'outcome' => 'Seeded meeting outcome.',
        ]);
        insertRow($db, 'meeting_checkins', [
            'meeting_id' => $meetingId,
            'user_id' => $employeeId,
            'type' => 'in',
            'lat' => 12.9716 + (rand(-2000, 2000) / 100000),
            'lng' => 77.5946 + (rand(-2000, 2000) / 100000),
            'accuracy_m' => rand(10, 90),
            'checked_at' => dt($day, rand(10, 17), 5),
        ]);
    }

    foreach ($managers as $idx => $managerId) {
        $teamId = $teamIds[$idx];
        $targetId = insertRow($db, 'targets', [
            'title' => 'Six-month sales target Team ' . ($idx + 1),
            'owner_type' => 'team',
            'team_id' => $teamId,
            'owner_user_id' => $managerId,
            'created_by_user_id' => $admins[0],
            'start_date' => '2026-01-07',
            'end_date' => '2026-07-06',
            'status' => 'approved',
        ]);
        $categoryIds = [];
        if (tableExists($db, 'target_categories')) {
            $stmt = $db->query('SELECT category_key, category_id FROM target_categories');
            foreach ($stmt->fetchAll(PDO::FETCH_OBJ) as $category) {
                $categoryIds[$category->category_key] = (int) $category->category_id;
            }
        }
        foreach (['leads', 'revenue', 'meetings'] as $metric) {
            if (empty($categoryIds[$metric])) {
                continue;
            }
            $planned = $metric === 'revenue' ? rand(2500000, 7000000) : rand(80, 250);
            $itemId = insertRow($db, 'target_items', [
                'target_id' => $targetId,
                'category_id' => $categoryIds[$metric],
                'planned_value' => $planned,
                'weight_percent' => 33.33,
            ]);
            insertRow($db, 'target_progress', [
                'target_item_id' => $itemId,
                'achieved_value' => (int) ($planned * (rand(55, 125) / 100)),
                'completion_percent' => rand(55, 125),
                'computed_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    foreach (array_merge($admins, $managers, $analysts) as $userId) {
        foreach (['executive', 'sales_command', 'field_activity', 'pipeline_revenue', 'performance_targets'] as $dashboardKey) {
            insertRow($db, 'dashboard_preferences', [
                'user_id' => $userId,
                'dashboard_key' => $dashboardKey,
                'widget_order' => json_encode([]),
                'hidden_widgets' => json_encode([]),
                'theme_accent' => pick(['indigo', 'cyan', 'emerald']),
                'date_range_days' => 180,
            ]);
        }
    }

    foreach ($analysts as $i => $analystId) {
        insertRow($db, 'dashboard_templates', [
            'name' => 'Analyst Six-Month Template ' . ($i + 1),
            'description' => 'Seeded template for dashboard customization testing.',
            'base_dashboard_key' => 'executive',
            'widgets' => json_encode(['revenue', 'pipeline', 'lead_funnel', 'activity_mix']),
            'visibility' => 'role',
            'allowed_roles' => json_encode(['analyst']),
            'created_by_user_id' => $analystId,
            'status' => 'active',
        ]);
    }

    for ($i = 1; $i <= 40; $i++) {
        insertRow($db, 'notifications', [
            'user_id' => pick(array_merge($admins, $managers, $employees)),
            'title' => pick(['Task overdue', 'Follow-up due', 'Attendance exception', 'High-value lead']),
            'message' => 'Seeded notification for E2E testing.',
            'type' => pick(['task', 'followup', 'attendance', 'lead']),
            'severity' => pick(['info', 'warning', 'critical']),
            'category' => 'demo',
            'is_read' => rand(0, 1),
            'created_at' => dt(dateBetween('2026-01-07', '2026-07-06'), rand(9, 18)),
        ]);
    }

    for ($i = 1; $i <= 12; $i++) {
        insertRow($db, 'data_edit_requests', [
            'entity_type' => 'lead',
            'entity_id' => $leadIds[array_rand($leadIds)],
            'requested_action' => pick(['update', 'archive']),
            'proposed_changes' => json_encode(['status' => pick(['contacted', 'qualified', 'proposal'])]),
            'manager_comment' => 'Seeded manager request for governance testing.',
            'status' => $i <= 6 ? 'pending' : pick(['approved', 'rejected']),
            'requested_by_user_id' => $managers[array_rand($managers)],
            'reviewed_by_user_id' => $i <= 6 ? null : $admins[0],
            'reviewed_comment' => $i <= 6 ? null : 'Seeded admin review.',
            'requested_at' => dt(dateBetween('2026-05-01', '2026-07-06'), rand(9, 18)),
            'reviewed_at' => $i <= 6 ? null : dt(dateBetween('2026-06-01', '2026-07-06'), rand(9, 18)),
        ]);
    }

    echo "[DONE] Six-month demo dataset loaded.\n";
    echo "Users: 2 admin, 2 HR, 2 analyst, 10 managers, 90 employees\n";
    echo "Default password for all demo users: Raptor@12345\n";
    echo "Sample logins: admin1@raptor.test, hr1@raptor.test, analyst1@raptor.test, manager1@raptor.test, employee1@raptor.test\n";
    echo "Attendance rows seeded: {$attendanceCount}\n";
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    fwrite(STDERR, "[FATAL] " . $e->getMessage() . "\n");
    exit(1);
}
