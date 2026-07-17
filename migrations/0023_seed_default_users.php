<?php
/**
 * Migration 0023 — Default User Seeding & Data Cleanup
 *
 * This migration:
 *  1. Deletes ALL existing users (and their orphaned records).
 *  2. Upserts 6 canonical seed accounts (admin, hr, manager, analyst, finance, employee).
 *  3. Assigns each seed user the correct role from the roles table.
 *  4. Sets force_password_reset = 1 so each user must change password on first login.
 *  5. Seeds leave_balances for each new user.
 *  6. Is fully idempotent — safe to re-run.
 *
 * Default password for all seed accounts: Raptor@2026
 * (bcrypt hash is stored; plain-text password is never persisted)
 *
 * Runs via bin/migrate.php with $db (PDO) in scope.
 */

// ─── Helper closures ──────────────────────────────────────────────────────────

$tableExists = function (PDO $db, string $table): bool {
    $stmt = $db->prepare(
        "SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t"
    );
    $stmt->execute([':t' => $table]);
    return (int) $stmt->fetchColumn() > 0;
};

$columnExists = function (PDO $db, string $table, string $col): bool {
    $stmt = $db->prepare(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c"
    );
    $stmt->execute([':t' => $table, ':c' => $col]);
    return (int) $stmt->fetchColumn() > 0;
};

// ─── 1. Gather role IDs ───────────────────────────────────────────────────────

$roleStmt = $db->query("SELECT role_id, role_name FROM roles");
$roleMap  = [];
foreach ($roleStmt->fetchAll(PDO::FETCH_OBJ) as $r) {
    $roleMap[$r->role_name] = (int) $r->role_id;
}

// Ensure all required roles exist (if they were never seeded by migration 0021)
$requiredRoles = [
    'admin'    => ['Full system administrator with unrestricted access.', true],
    'hr'       => ['HR management: employees, attendance, leave, payroll prep.', false],
    'manager'  => ['Team lead: oversees CRM, projects, team attendance and performance.', false],
    'analyst'  => ['Read-only access across all modules with analytics and reporting.', false],
    'finance'  => ['Finance management: invoices, payments, payroll approval, expenses.', false],
    'employee' => ['Employee self-service: own attendance, payslips, tasks, expenses.', false],
];

foreach ($requiredRoles as $name => [$desc, $isSystem]) {
    if (!isset($roleMap[$name])) {
        $ins = $db->prepare('INSERT INTO roles (role_name, description, is_system) VALUES (:n, :d, :s)');
        $ins->execute([':n' => $name, ':d' => $desc, ':s' => (int) $isSystem]);
        $roleMap[$name] = (int) $db->lastInsertId();
        echo "    + role '{$name}' created\n";
    }
}

// ─── 2. Delete all existing non-seed users (and cascade dependent records) ───

// Collect IDs of users that are NOT the seed accounts we are about to create.
$seedEmails = [
    'admin@raptor.local',
    'hr@raptor.local',
    'manager@raptor.local',
    'analyst@raptor.local',
    'finance@raptor.local',
    'employee@raptor.local',
];

$placeholders = implode(',', array_fill(0, count($seedEmails), '?'));
$existingNonSeed = $db->prepare(
    "SELECT user_id FROM users WHERE email NOT IN ({$placeholders})"
);
$existingNonSeed->execute($seedEmails);
$nonSeedIds = $existingNonSeed->fetchAll(PDO::FETCH_COLUMN);

if (!empty($nonSeedIds)) {
    $idList = implode(',', array_map('intval', $nonSeedIds));

    // Auto-discover ALL foreign keys referencing users(user_id) via information_schema
    $fkStmt = $db->query(
        "SELECT kcu.TABLE_NAME, kcu.COLUMN_NAME, col.IS_NULLABLE
         FROM information_schema.KEY_COLUMN_USAGE kcu
         JOIN information_schema.COLUMNS col
           ON col.TABLE_SCHEMA = kcu.TABLE_SCHEMA
          AND col.TABLE_NAME   = kcu.TABLE_NAME
          AND col.COLUMN_NAME  = kcu.COLUMN_NAME
         WHERE kcu.TABLE_SCHEMA        = DATABASE()
           AND kcu.REFERENCED_TABLE_NAME  = 'users'
           AND kcu.REFERENCED_COLUMN_NAME = 'user_id'
         ORDER BY kcu.TABLE_NAME"
    );

    foreach ($fkStmt->fetchAll(PDO::FETCH_OBJ) as $fk) {
        try {
            if ($fk->IS_NULLABLE === 'YES') {
                $db->exec("UPDATE `{$fk->TABLE_NAME}` SET `{$fk->COLUMN_NAME}` = NULL WHERE `{$fk->COLUMN_NAME}` IN ({$idList})");
                echo "    ~ nulled {$fk->TABLE_NAME}.{$fk->COLUMN_NAME}\n";
            } else {
                $db->exec("DELETE FROM `{$fk->TABLE_NAME}` WHERE `{$fk->COLUMN_NAME}` IN ({$idList})");
                echo "    ~ deleted rows in {$fk->TABLE_NAME} (NOT NULL FK)\n";
            }
        } catch (Exception $e) {
            echo "    ~ skip {$fk->TABLE_NAME}.{$fk->COLUMN_NAME}: " . $e->getMessage() . "\n";
        }
    }

    // Step B: Delete rows that directly belong to these users
    $ownedTables = [
        ['leave_approvals',           'approver_id'],
        ['leave_requests',            'user_id'],
        ['attendance_approvals',      'approver_id'],
        ['attendance',                'user_id'],
        ['leave_balances',            'user_id'],
        ['payroll_payslips',          'user_id'],
        ['user_permission_overrides', 'user_id'],
        ['activity_logs',             'user_id'],
        ['notifications',             'user_id'],
        ['employees',                 'user_id'],
        ['targets',                   'user_id'],
        ['performance_scores',        'user_id'],
        ['social_stats',              'user_id'],
        ['expense_claims',            'user_id'],
        ['documents',                 'uploaded_by'],
        ['followups',                 'user_id'],
    ];
    foreach ($ownedTables as [$tbl, $col]) {
        if (!$tableExists($db, $tbl) || !$columnExists($db, $tbl, $col)) continue;
        try {
            $db->exec("DELETE FROM `{$tbl}` WHERE `{$col}` IN ({$idList})");
            echo "    ~ cleaned {$tbl}\n";
        } catch (Exception $e) {
            echo "    ~ skip delete {$tbl}: " . $e->getMessage() . "\n";
        }
    }

    // Step C: Delete the non-seed users
    $db->exec("DELETE FROM users WHERE user_id IN ({$idList})");
    echo "    ~ deleted " . count($nonSeedIds) . " non-seed user(s)\n";
} else {
    echo "    = no non-seed users to delete\n";
}

// ─── 3. Upsert the 6 seed accounts ───────────────────────────────────────────

/**
 * Default password: Raptor@2026
 * We pre-hash it here. In production, each user should reset via force_password_reset = 1.
 */
$defaultPassword = password_hash('Raptor@2026', PASSWORD_BCRYPT, ['cost' => 12]);

$seedUsers = [
    [
        'email'  => 'admin@raptor.local',
        'name'   => 'Admin User',
        'role'   => 'admin',
        'status' => 'active',
    ],
    [
        'email'  => 'hr@raptor.local',
        'name'   => 'HR User',
        'role'   => 'hr',
        'status' => 'active',
    ],
    [
        'email'  => 'manager@raptor.local',
        'name'   => 'Manager User',
        'role'   => 'manager',
        'status' => 'active',
    ],
    [
        'email'  => 'analyst@raptor.local',
        'name'   => 'Analyst User',
        'role'   => 'analyst',
        'status' => 'active',
    ],
    [
        'email'  => 'finance@raptor.local',
        'name'   => 'Finance User',
        'role'   => 'finance',
        'status' => 'active',
    ],
    [
        'email'  => 'employee@raptor.local',
        'name'   => 'Employee User',
        'role'   => 'employee',
        'status' => 'active',
    ],
];

// Check what columns exist on users table to build correct INSERT
$hasForceReset = $columnExists($db, 'users', 'force_password_reset');

foreach ($seedUsers as $seed) {
    $roleId = $roleMap[$seed['role']] ?? null;
    if ($roleId === null) {
        echo "    ! WARNING: role '{$seed['role']}' not found — skipping {$seed['email']}\n";
        continue;
    }

    // Check if this seed user already exists
    $check = $db->prepare('SELECT user_id FROM users WHERE email = :e LIMIT 1');
    $check->execute([':e' => $seed['email']]);
    $existing = $check->fetchColumn();

    if ($existing !== false) {
        // Update existing seed user to correct role and reset password
        $upd = $db->prepare(
            'UPDATE users SET name = :n, role_id = :r, password = :p, status = :s'
            . ($hasForceReset ? ', force_password_reset = 1' : '')
            . ' WHERE user_id = :id'
        );
        $upd->execute([
            ':n'  => $seed['name'],
            ':r'  => $roleId,
            ':p'  => $defaultPassword,
            ':s'  => $seed['status'],
            ':id' => $existing,
        ]);
        $userId = (int) $existing;
        echo "    ~ updated seed user: {$seed['email']} (role: {$seed['role']})\n";
    } else {
        // Insert new seed user
        $cols   = 'name, email, password, role_id, status';
        $params = ':n, :e, :p, :r, :s';
        $vals   = [':n' => $seed['name'], ':e' => $seed['email'], ':p' => $defaultPassword, ':r' => $roleId, ':s' => $seed['status']];

        if ($hasForceReset) {
            $cols   .= ', force_password_reset';
            $params .= ', 1';
        }

        $ins = $db->prepare("INSERT INTO users ({$cols}) VALUES ({$params})");
        $ins->execute($vals);
        $userId = (int) $db->lastInsertId();
        echo "    + inserted seed user: {$seed['email']} (role: {$seed['role']}, id: {$userId})\n";
    }

    // ─── 4. Seed leave_balances for this user ─────────────────────────────────
    if ($tableExists($db, 'leave_balances')) {
        $balCheck = $db->prepare('SELECT COUNT(*) FROM leave_balances WHERE user_id = :uid');
        $balCheck->execute([':uid' => $userId]);
        if ((int) $balCheck->fetchColumn() === 0) {
            $db->prepare(
                'INSERT INTO leave_balances (user_id, sick_leave, casual_leave, earned_leave)
                 VALUES (:uid, 12.00, 12.00, 15.00)'
            )->execute([':uid' => $userId]);
            echo "      + leave_balances seeded for user_id {$userId}\n";
        }
    }

    // ─── 5. Seed employee profile ─────────────────────────────────────────────
    if ($tableExists($db, 'employees')) {
        $empCheck = $db->prepare('SELECT COUNT(*) FROM employees WHERE user_id = :uid');
        $empCheck->execute([':uid' => $userId]);
        if ((int) $empCheck->fetchColumn() === 0) {
            // Build the INSERT dynamically based on available columns
            $empCols   = 'user_id, name, email, department, position';
            $empParams = ':uid, :name, :email, :dept, :pos';
            $empVals   = [
                ':uid'   => $userId,
                ':name'  => $seed['name'],
                ':email' => $seed['email'],
                ':dept'  => ucfirst($seed['role']),
                ':pos'   => ucfirst($seed['role']) . ' Role',
            ];

            // Add join_date if column exists
            if ($columnExists($db, 'employees', 'join_date')) {
                $empCols   .= ', join_date';
                $empParams .= ', :jd';
                $empVals[':jd'] = date('Y-01-01');
            }

            try {
                $db->prepare("INSERT INTO employees ({$empCols}) VALUES ({$empParams})")
                   ->execute($empVals);
                echo "      + employee profile seeded for {$seed['email']}\n";
            } catch (Exception $e) {
                echo "      ~ employee profile already exists (or insert failed silently) for {$seed['email']}\n";
            }
        }
    }
}

echo "\n  ✔ Migration 0023 complete — 6 seed accounts ready.\n";
echo "  ⚠  Default password: Raptor\@2026 — users must change on first login.\n";
echo "  Login credentials:\n";
foreach ($seedUsers as $s) {
    echo "      " . str_pad($s['role'], 10) . " | " . $s['email'] . "\n";
}
