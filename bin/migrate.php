<?php
/**
 * Raptor CRM — Migration Runner
 * -------------------------------------------------------------------------
 * Applies every *.sql file in /migrations in filename order, exactly once.
 * Each applied file is recorded in the `schema_migrations` table so re-runs
 * are safe and idempotent. This replaces the ad-hoc bin/alter_*.php scripts.
 *
 * Usage (on the server):
 *   php bin/migrate.php            # apply all pending migrations
 *   php bin/migrate.php --status   # list applied vs pending, apply nothing
 *
 * Notes:
 *  - Files are split on ";" at line ends into individual statements so a
 *    single .sql file may contain many statements.
 *  - 0000_schema_migrations.sql (the bookkeeping table) is always run first.
 */

require_once dirname(__DIR__) . '/app/config/config.php';
require_once dirname(__DIR__) . '/app/core/Database.php';

$migrationsDir = dirname(__DIR__) . '/migrations';
$statusOnly    = in_array('--status', $argv, true);

$db = Database::getInstance()->getConnection();

// Ensure the bookkeeping table exists before we query it.
$bootstrap = $migrationsDir . '/0000_schema_migrations.sql';
if (is_file($bootstrap)) {
    $db->exec(file_get_contents($bootstrap));
}

// A brand-new install also needs the legacy base schema before incremental
// migrations can add later CRM modules. Only run it when the core RBAC table is
// absent because dashboard_schema.sql intentionally rebuilds base tables.
$baseSchema = dirname(__DIR__) . '/dashboard_schema.sql';
if (is_file($baseSchema)) {
    $stmt = $db->prepare(
        'SELECT COUNT(*)
           FROM information_schema.TABLES
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = :table_name'
    );
    $stmt->execute([':table_name' => 'roles']);
    if ((int) $stmt->fetchColumn() === 0) {
        echo "Bootstrapping base schema from dashboard_schema.sql ...\n";
        $db->exec(file_get_contents($baseSchema));
        echo "  [OK] Base schema applied.\n";
    }
}

// Which versions have already been applied? (Connection defaults to FETCH_OBJ,
// so fetch the column explicitly to stay fetch-mode independent.)
$applied = [];
try {
    $stmt = $db->query('SELECT version FROM schema_migrations');
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN, 0) as $version) {
        $applied[$version] = true;
    }
} catch (Exception $e) {
    // Table may not exist yet on a brand-new DB — treat as none applied.
}

// Migrations may be *.sql (pure DDL/DML) or *.php (idempotent, for guarded
// column adds where MySQL 8 lacks ADD COLUMN IF NOT EXISTS). A .php migration
// is included with $db in scope and should be safe to define but is only run
// once (tracked in schema_migrations).
$files = array_merge(
    glob($migrationsDir . '/*.sql'),
    glob($migrationsDir . '/*.php')
);
// Sort by version (filename without extension) so 0001.sql < 0002.php.
usort($files, function ($a, $b) {
    return strcmp(pathinfo($a, PATHINFO_FILENAME), pathinfo($b, PATHINFO_FILENAME));
});

$pending = [];
foreach ($files as $file) {
    $version = pathinfo($file, PATHINFO_FILENAME);
    if ($version === '0000_schema_migrations') {
        continue; // bookkeeping table, already ensured above
    }
    if (!isset($applied[$version])) {
        $pending[] = [$version, $file];
    }
}

if ($statusOnly) {
    echo "Applied migrations:\n";
    foreach (array_keys($applied) as $v) { echo "  [x] $v\n"; }
    echo "Pending migrations:\n";
    if (!$pending) { echo "  (none)\n"; }
    foreach ($pending as [$v]) { echo "  [ ] $v\n"; }
    exit(0);
}

if (!$pending) {
    echo "[OK] Database is up to date. No pending migrations.\n";
    exit(0);
}

$record = $db->prepare('INSERT INTO schema_migrations (version) VALUES (:v)');

foreach ($pending as [$version, $file]) {
    echo "Applying $version ...\n";
    $isPhp = (pathinfo($file, PATHINFO_EXTENSION) === 'php');

    try {
        if ($isPhp) {
            // The .php migration uses $db (in scope) and must be idempotent.
            (function ($db) use ($file) { require $file; })($db);
        } else {
            // PDO::exec with the MySQL driver runs the whole multi-statement file.
            $db->exec(file_get_contents($file));
        }
        $record->execute([':v' => $version]);
        echo "  [OK] $version applied.\n";
    } catch (Exception $e) {
        // Fail loud so the operator can inspect; do not mark as applied.
        die("  [FATAL] $version failed: " . $e->getMessage() . "\n");
    }
}

echo "[DONE] All pending migrations applied.\n";
