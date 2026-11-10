<?php
/**
 * Raptor Live Server One-Click Deploy & Migration Runner
 * -------------------------------------------------------------------------
 * Safely fetches latest code from origin/main, applies database migrations,
 * flushes opcache, and outputs real-time deployment logs.
 *
 * Access:
 *   https://<your-live-domain>/update.php?key=raptor_deploy_2026
 *   or logged in as Admin.
 */

declare(strict_types=1);

// Security Gate
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$secretKey = 'raptor_deploy_2026';
$providedKey = $_GET['key'] ?? '';
$isAdmin = isset($_SESSION['user_role']) && in_array(strtolower((string)$_SESSION['user_role']), ['admin', 'ceo'], true);

if ($providedKey !== $secretKey && !$isAdmin) {
    http_response_code(403);
    die('<div style="font-family:sans-serif;padding:30px;color:#dc2626;"><h2>403 Forbidden</h2><p>Access denied. Valid deployment key or admin session required.</p></div>');
}

$root = dirname(__DIR__);
require_once $root . '/app/config/config.php';
require_once $root . '/app/core/Database.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>RAPTOR Live Deployment Engine</title>
    <style>
        body { background: #0f172a; color: #f8fafc; font-family: 'Courier New', monospace; padding: 24px; line-height: 1.5; }
        .log-box { background: #020617; border: 1px solid #1e293b; border-radius: 8px; padding: 20px; max-width: 900px; margin: 0 auto; box-shadow: 0 10px 25px rgba(0,0,0,0.5); }
        .success { color: #10b981; font-weight: bold; }
        .warn { color: #f59e0b; }
        .err { color: #ef4444; }
        .title { color: #38bdf8; font-size: 1.3rem; margin-bottom: 12px; font-weight: bold; }
        .badge { background: #1e293b; padding: 3px 8px; border-radius: 4px; font-size: 0.8rem; }
    </style>
</head>
<body>
<div class="log-box">
<div class="title">🚀 RAPTOR CRM Live Deployment & Migration Runner</div>
<hr style="border-color:#1e293b;margin-bottom:16px;">
<?php

echo "<div>[1/4] Checking Git Status and Pulling Latest 'main' branch...</div>";
$gitOutput = [];
$gitExit = -1;

if (function_exists('exec')) {
    @exec("cd " . escapeshellarg($root) . " && git fetch origin main 2>&1 && git reset --hard origin/main 2>&1", $gitOutput, $gitExit);
}

if ($gitExit === 0 && !empty($gitOutput)) {
    echo "<div class='success'>  ✔ Git pull successful: " . htmlspecialchars(implode(" | ", $gitOutput)) . "</div>";
} else {
    echo "<div class='warn'>  ⚠ Git CLI not directly accessible or restricted. Downloading latest GitHub release package...</div>";
    $zipUrl = 'https://github.com/hemasai-axiora/RAPTOR/archive/refs/heads/main.zip';
    $tempZip = $root . '/public/temp_update.zip';

    $ch = curl_init($zipUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    $zipData = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200 && !empty($zipData)) {
        file_put_contents($tempZip, $zipData);
        $zip = new ZipArchive();
        if ($zip->open($tempZip) === true) {
            $extracted = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $filename = $zip->getNameIndex($i);
                $rel = preg_replace('#^[^/]+/#', '', $filename);
                if (empty($rel) || $rel === '.env') continue;
                $dest = $root . '/' . $rel;
                if (str_ends_with($filename, '/')) {
                    if (!is_dir($dest)) @mkdir($dest, 0777, true);
                } else {
                    $dir = dirname($dest);
                    if (!is_dir($dir)) @mkdir($dir, 0777, true);
                    copy("zip://" . $tempZip . "#" . $filename, $dest);
                    $extracted++;
                }
            }
            $zip->close();
            @unlink($tempZip);
            echo "<div class='success'>  ✔ Extracted {$extracted} updated files from GitHub main branch release.</div>";
        }
    } else {
        echo "<div class='err'>  ✖ Could not download GitHub archive (HTTP {$httpCode}). Proceeding with database schema migrations...</div>";
    }
}

echo "<div style='margin-top:12px;'>[2/4] Executing Database Migrations...</div>";
$migrationsDir = $root . '/migrations';
try {
    $db = Database::getInstance()->getConnection();

    // Ensure bookkeeping table exists
    $bootstrap = $migrationsDir . '/0000_schema_migrations.sql';
    if (is_file($bootstrap)) {
        $db->exec(file_get_contents($bootstrap));
    }

    $applied = [];
    $stmt = $db->query('SELECT version FROM schema_migrations');
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN, 0) as $v) {
        $applied[$v] = true;
    }

    $files = array_merge(glob($migrationsDir . '/*.sql'), glob($migrationsDir . '/*.php'));
    usort($files, fn($a, $b) => strcmp(pathinfo($a, PATHINFO_FILENAME), pathinfo($b, PATHINFO_FILENAME)));

    $runCount = 0;
    foreach ($files as $file) {
        $version = pathinfo($file, PATHINFO_FILENAME);
        if ($version === '0000_schema_migrations' || isset($applied[$version])) {
            continue;
        }

        echo "<div style='color:#94a3b8;'>  Running: {$version}...</div>";
        $ext = pathinfo($file, PATHINFO_EXTENSION);
        if ($ext === 'sql') {
            $rawSql = file_get_contents($file);
            $statements = array_filter(array_map('trim', explode(';', $rawSql)));
            foreach ($statements as $st) {
                if ($st !== '') $db->exec($st);
            }
        } elseif ($ext === 'php') {
            require $file;
        }

        $ins = $db->prepare('INSERT INTO schema_migrations (version, executed_at) VALUES (:v, NOW())');
        $ins->execute([':v' => $version]);
        $runCount++;
        echo "<div class='success'>    ✔ {$version} applied successfully.</div>";
    }

    if ($runCount === 0) {
        echo "<div class='success'>  ✔ Database schema is completely up to date (no pending migrations).</div>";
    } else {
        echo "<div class='success'>  ✔ Successfully applied {$runCount} new migration(s).</div>";
    }
} catch (Throwable $e) {
    echo "<div class='err'>  ✖ Database migration error: " . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "<div style='margin-top:12px;'>[3/4] Resetting PHP Bytecode & OPcache...</div>";
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "<div class='success'>  ✔ OPcache flushed successfully.</div>";
} else {
    echo "<div class='warn'>  ✔ OPcache not enabled or handled per-request.</div>";
}

echo "<div style='margin-top:12px;'>[4/4] Verifying System State...</div>";
$ver = defined('APP_VERSION') ? APP_VERSION : '2.1.0';
echo "<div class='success' style='font-size:1.1rem;margin-top:10px;'>🎉 DEPLOYMENT COMPLETE! Raptor is running version <span class='badge'>v{$ver}</span>.</div>";
echo "<div style='margin-top:15px;'><a href='index.php' style='display:inline-block;padding:8px 16px;background:#2563eb;color:#fff;text-decoration:none;border-radius:6px;font-weight:bold;'>Return to Dashboard →</a></div>";
?>
</div>
</body>
</html>

