<?php
// Database Schema & Seed Runner Script
// Run via CLI: php bin/seed_db.php

require_once dirname(dirname(__FILE__)) . '/app/config/config.php';
require_once dirname(dirname(__FILE__)) . '/app/core/Database.php';

echo "=== Raptor CRM Database Seeder ===\n";

try {
    // 1. Establish connection
    $db = Database::getInstance()->getConnection();
    echo "[OK] Connected to database server.\n";

    // 2. Read and run schema SQL
    $schemaFile = dirname(dirname(__FILE__)) . '/dashboard_schema.sql';
    if (!file_exists($schemaFile)) {
        die("[ERROR] dashboard_schema.sql not found at: " . $schemaFile . "\n");
    }
    
    echo "[INFO] Loading schema from dashboard_schema.sql...\n";
    $schemaSql = file_get_contents($schemaFile);
    
    // Execute schema statements
    $db->exec($schemaSql);
    echo "[OK] Schema tables and indexes created successfully.\n";

    // 3. Clear existing seeds if any
    $db->exec("SET FOREIGN_KEY_CHECKS = 0;");
    $db->exec("TRUNCATE TABLE role_permissions;");
    $db->exec("TRUNCATE TABLE users;");
    $db->exec("TRUNCATE TABLE roles;");
    $db->exec("TRUNCATE TABLE permissions;");
    $db->exec("TRUNCATE TABLE customer_journey_stages;");
    $db->exec("SET FOREIGN_KEY_CHECKS = 1;");

    // 4. Seed Roles
    echo "[INFO] Seeding roles...\n";
    $roles = [
        ['role_name' => 'admin', 'description' => 'System Administrator with full access'],
        ['role_name' => 'manager', 'description' => 'Campaign & Account Manager'],
        ['role_name' => 'analyst', 'description' => 'Data Analyst for campaigns and channels'],
        ['role_name' => 'employer', 'description' => 'External Client / Business Owner (Read-only)']
    ];
    
    $roleStmt = $db->prepare('INSERT INTO roles (role_name, description) VALUES (:name, :desc)');
    $roleIds = [];
    foreach ($roles as $r) {
        $roleStmt->execute([':name' => $r['role_name'], ':desc' => $r['description']]);
        $roleIds[$r['role_name']] = $db->lastInsertId();
    }
    echo "[OK] Seeded 4 roles.\n";

    // 5. Seed Permissions
    echo "[INFO] Seeding permissions...\n";
    $permissions = [
        ['name' => 'view_executive_dashboard', 'desc' => 'Allows viewing the Executive Overview Dashboard'],
        ['name' => 'view_channels_dashboard', 'desc' => 'Allows viewing Channel & Campaign Performance Dashboard'],
        ['name' => 'view_customer_dashboard', 'desc' => 'Allows viewing Customer Intelligence Dashboard'],
        ['name' => 'manage_campaigns', 'desc' => 'Create, edit, and adjust campaign parameters'],
        ['name' => 'manage_leads', 'desc' => 'Edit leads and lead stage assignments'],
        ['name' => 'manage_clients', 'desc' => 'Manage client records'],
        ['name' => 'manage_tasks', 'desc' => 'Assign and manage tasks'],
        ['name' => 'manage_finance', 'desc' => 'Generate and review invoices and payments'],
        ['name' => 'manage_reports', 'desc' => 'Create and export PDF/Excel reports'],
        ['name' => 'manage_users', 'desc' => 'Manage users and roles'],
        ['name' => 'manage_settings', 'desc' => 'Global integrations, SMTP and backup configs']
    ];

    $permStmt = $db->prepare('INSERT INTO permissions (permission_name, description) VALUES (:name, :desc)');
    $permIds = [];
    foreach ($permissions as $p) {
        $permStmt->execute([':name' => $p['name'], ':desc' => $p['desc']]);
        $permIds[$p['name']] = $db->lastInsertId();
    }
    echo "[OK] Seeded " . count($permissions) . " permissions.\n";

    // 6. Bind Role Permissions
    echo "[INFO] Binding permissions to roles...\n";
    $bindStmt = $db->prepare('INSERT INTO role_permissions (role_id, permission_id) VALUES (:rid, :pid)');

    // Admin Permissions (All)
    foreach ($permIds as $pid) {
        $bindStmt->execute([':rid' => $roleIds['admin'], ':pid' => $pid]);
    }

    // Manager Permissions (All except global system settings)
    foreach ($permIds as $name => $pid) {
        if ($name !== 'manage_settings' && $name !== 'manage_users') {
            $bindStmt->execute([':rid' => $roleIds['manager'], ':pid' => $pid]);
        }
    }

    // Analyst Permissions (Read dashboards + reports)
    $analystPerms = ['view_channels_dashboard', 'view_customer_dashboard', 'manage_reports'];
    foreach ($analystPerms as $name) {
        if (isset($permIds[$name])) {
            $bindStmt->execute([':rid' => $roleIds['analyst'], ':pid' => $permIds[$name]]);
        }
    }

    // Employer Permissions (Read Executive dashboard only)
    $employerPerms = ['view_executive_dashboard'];
    foreach ($employerPerms as $name) {
        if (isset($permIds[$name])) {
            $bindStmt->execute([':rid' => $roleIds['employer'], ':pid' => $permIds[$name]]);
        }
    }
    echo "[OK] Role permissions linked successfully.\n";

    // 7. Seed Users
    echo "[INFO] Seeding test users...\n";
    $users = [
        [
            'role_id' => $roleIds['admin'],
            'name' => 'Admin User',
            'email' => 'admin@raptor.com',
            'password' => password_hash('admin123', PASSWORD_BCRYPT, ['cost' => 10])
        ],
        [
            'role_id' => $roleIds['manager'],
            'name' => 'Manager User',
            'email' => 'manager@raptor.com',
            'password' => password_hash('manager123', PASSWORD_BCRYPT, ['cost' => 10])
        ],
        [
            'role_id' => $roleIds['analyst'],
            'name' => 'Analyst User',
            'email' => 'analyst@raptor.com',
            'password' => password_hash('analyst123', PASSWORD_BCRYPT, ['cost' => 10])
        ],
        [
            'role_id' => $roleIds['employer'],
            'name' => 'Client Owner',
            'email' => 'employer@raptor.com',
            'password' => password_hash('employer123', PASSWORD_BCRYPT, ['cost' => 10])
        ]
    ];

    $userStmt = $db->prepare('INSERT INTO users (role_id, name, email, password, status) VALUES (:rid, :name, :email, :pass, "active")');
    foreach ($users as $u) {
        $userStmt->execute([
            ':rid' => $u['role_id'],
            ':name' => $u['name'],
            ':email' => $u['email'],
            ':pass' => $u['password']
        ]);
    }
    echo "[OK] Seeded 4 active test users.\n";

    // 8. Seed Customer Journey Stages
    echo "[INFO] Seeding customer journey stages...\n";
    $stages = [
        ['name' => 'Reach', 'order' => 1],
        ['name' => 'Visitors', 'order' => 2],
        ['name' => 'Engaged', 'order' => 3],
        ['name' => 'Leads', 'order' => 4],
        ['name' => 'Qualified', 'order' => 5],
        ['name' => 'Customers', 'order' => 6]
    ];
    $stageStmt = $db->prepare('INSERT INTO customer_journey_stages (stage_name, sort_order) VALUES (:name, :order)');
    foreach ($stages as $s) {
        $stageStmt->execute([':name' => $s['name'], ':order' => $s['order']]);
    }
    echo "[OK] Seeded 6 customer journey stages.\n";

    // 9. Seed Sample Clients, Campaigns, and Leads
    echo "[INFO] Seeding sample CRM data...\n";
    
    $db->exec("SET FOREIGN_KEY_CHECKS = 0;");
    $db->exec("TRUNCATE TABLE customer_journey_log;");
    $db->exec("TRUNCATE TABLE leads;");
    $db->exec("TRUNCATE TABLE campaigns;");
    $db->exec("TRUNCATE TABLE clients;");
    $db->exec("SET FOREIGN_KEY_CHECKS = 1;");

    // Client
    $db->exec("INSERT INTO clients (company_name, email, phone, status, contract_start, contract_end, package_details, billing_address) 
               VALUES ('Axiora Tech', 'contact@axiora.com', '+1-555-0199', 'active', '2026-01-01', '2026-12-31', 'Full stack marketing retainer - $5,000/mo', '123 Innovation Way, Tech Valley')");
    $clientId = $db->lastInsertId();

    // Campaigns
    $campaignStmt = $db->prepare("INSERT INTO campaigns (client_id, name, channel, budget, spend, revenue_influenced, start_date, status) 
                                  VALUES (:cid, :name, :chan, :budget, :spend, :rev, :sdate, :status)");
    
    $campaignStmt->execute([
        ':cid' => $clientId,
        ':name' => 'Q3 LinkedIn Lead Gen',
        ':chan' => 'LinkedIn',
        ':budget' => 10000.00,
        ':spend' => 4200.00,
        ':rev' => 12000.00,
        ':sdate' => '2026-07-01',
        ':status' => 'active'
    ]);

    $campaignStmt->execute([
        ':cid' => $clientId,
        ':name' => 'Google Search Ads 2026',
        ':chan' => 'Website',
        ':budget' => 15000.00,
        ':spend' => 12500.00,
        ':rev' => 38000.00,
        ':sdate' => '2026-05-15',
        ':status' => 'active'
    ]);

    // Fetch a user id for assignee
    $userRow = $db->query('SELECT user_id FROM users WHERE email = "manager@raptor.com"')->fetch(PDO::FETCH_OBJ);
    $managerId = $userRow ? $userRow->user_id : null;

    // Leads
    $leadStmt = $db->prepare("INSERT INTO leads (client_id, assigned_to_user_id, first_name, last_name, email, phone, status, lead_quality, conversion_probability, lead_value, lead_source) 
                              VALUES (:cid, :uid, :fname, :lname, :email, :phone, :status, :quality, :prob, :val, :source)");
    
    $leadStmt->execute([
        ':cid' => $clientId,
        ':uid' => $managerId,
        ':fname' => 'John',
        ':lname' => 'Doe',
        ':email' => 'john@doe.com',
        ':phone' => '+1-555-8833',
        ':status' => 'qualified',
        ':quality' => 'hot',
        ':prob' => 85.0,
        ':val' => 5000.00,
        ':source' => 'LinkedIn'
    ]);
    $lead1Id = $db->lastInsertId();

    $leadStmt->execute([
        ':cid' => $clientId,
        ':uid' => $managerId,
        ':fname' => 'Jane',
        ':lname' => 'Smith',
        ':email' => 'jane@smith.com',
        ':phone' => '+1-555-1122',
        ':status' => 'contacted',
        ':quality' => 'warm',
        ':prob' => 50.0,
        ':val' => 3000.00,
        ':source' => 'Google Search'
    ]);
    $lead2Id = $db->lastInsertId();

    $leadStmt->execute([
        ':cid' => $clientId,
        ':uid' => null,
        ':fname' => 'Bob',
        ':lname' => 'Johnson',
        ':email' => 'bob@johnson.com',
        ':phone' => '+1-555-4455',
        ':status' => 'new',
        ':quality' => 'cold',
        ':prob' => 15.0,
        ':val' => 1000.00,
        ':source' => 'Referral'
    ]);
    $lead3Id = $db->lastInsertId();

    // Journey Logs
    $journeyStmt = $db->prepare("INSERT INTO customer_journey_log (lead_id, from_stage_id, to_stage_id) VALUES (:lid, :from, :to)");
    $journeyStmt->execute([':lid' => $lead1Id, ':from' => null, ':to' => 4]);
    $journeyStmt->execute([':lid' => $lead1Id, ':from' => 4, ':to' => 5]);
    $journeyStmt->execute([':lid' => $lead2Id, ':from' => null, ':to' => 4]);
    $journeyStmt->execute([':lid' => $lead3Id, ':from' => null, ':to' => 4]);

    // Seed Tasks
    $db->exec("SET FOREIGN_KEY_CHECKS = 0;");
    $db->exec("TRUNCATE TABLE tasks;");
    $db->exec("SET FOREIGN_KEY_CHECKS = 1;");
    $taskSeedStmt = $db->prepare("INSERT INTO tasks (assigned_to_user_id, created_by_user_id, title, description, priority, deadline, status) 
                                  VALUES (:assigned, :creator, :title, :desc, :prio, :deadline, :status)");
    $taskSeedStmt->execute([
        ':assigned' => $managerId,
        ':creator' => $managerId,
        ':title' => 'Optimize LinkedIn keywords',
        ':desc' => 'Perform standard audit on campaign keywords and exclude negative key phrases.',
        ':prio' => 'medium',
        ':deadline' => date('Y-m-d H:i:s', strtotime('+3 days')),
        ':status' => 'pending'
    ]);
    $taskSeedStmt->execute([
        ':assigned' => $managerId,
        ':creator' => $managerId,
        ':title' => 'Write YouTube video script',
        ':desc' => 'Create a 3-minute video draft script focusing on product automation benefits.',
        ':prio' => 'high',
        ':deadline' => date('Y-m-d H:i:s', strtotime('+1 days')),
        ':status' => 'in_progress'
    ]);
    $taskSeedStmt->execute([
        ':assigned' => $managerId,
        ':creator' => $managerId,
        ':title' => 'Setup GA4 conversions',
        ':desc' => 'Link GA4 measurement protocol with CRM conversion pipelines.',
        ':prio' => 'low',
        ':deadline' => date('Y-m-d H:i:s', strtotime('-2 days')),
        ':status' => 'completed'
    ]);

    // Seed Invoices
    $db->exec("SET FOREIGN_KEY_CHECKS = 0;");
    $db->exec("TRUNCATE TABLE invoices;");
    $db->exec("SET FOREIGN_KEY_CHECKS = 1;");
    $invoiceSeedStmt = $db->prepare("INSERT INTO invoices (client_id, invoice_number, amount, status, due_date) 
                                     VALUES (:cid, :number, :amount, :status, :due)");
    $invoiceSeedStmt->execute([
        ':cid' => $clientId,
        ':number' => 'INV-2026-001',
        ':amount' => 5000.00,
        ':status' => 'paid',
        ':due' => date('Y-m-d', strtotime('-5 days'))
    ]);
    $invoiceSeedStmt->execute([
        ':cid' => $clientId,
        ':number' => 'INV-2026-002',
        ':amount' => 5000.00,
        ':status' => 'unpaid',
        ':due' => date('Y-m-d', strtotime('+10 days'))
    ]);

    echo "[OK] Seeded sample clients, campaigns, leads, customer journey transition logs, tasks, and billing invoices.\n";

    echo "=== Seeding Complete ===\n";

} catch (Exception $e) {
    die("[FATAL ERROR] " . $e->getMessage() . "\n");
}
