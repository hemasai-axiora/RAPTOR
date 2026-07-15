<?php
/**
 * Raptor CRM Nightly AWS S3 Backup
 * Dumps the database using pure PHP PDO and syncs local storage files to S3.
 *
 * Usage:
 *   php bin/backup_to_s3.php
 */

require_once dirname(__DIR__) . '/app/config/config.php';
require_once APPROOT . '/core/Database.php';

// Bucket configuration
$bucket = defined('S3_BUCKET') ? S3_BUCKET : 'app-frontend-hosting-dev-847013096108';

echo "=== Raptor CRM S3 Backup started at " . date('Y-m-d H:i:s') . " ===\n";
echo "Target S3 Bucket: s3://{$bucket}\n\n";

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    echo "[ERROR] Database connection failed: " . $e->getMessage() . "\n";
    exit(1);
}

// 1. Perform database dump to .sql file
echo "Step 1: Generating database SQL dump...\n";
$tables = [];
$stmt = $db->query("SHOW TABLES");
while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
    $tables[] = $row[0];
}

$tempDir = sys_get_temp_dir();
$timestamp = date('Y-m-d_H-i-s');
$sqlFilename = "raptor_backup_{$timestamp}.sql";
$sqlPath = $tempDir . '/' . $sqlFilename;
$gzPath = $sqlPath . '.gz';

$fp = fopen($sqlPath, 'w');
if (!$fp) {
    echo "[ERROR] Failed to create temp SQL file at {$sqlPath}\n";
    exit(1);
}

// Write file header
fwrite($fp, "-- Raptor CRM Database Backup\n");
fwrite($fp, "-- Generated at: " . date('Y-m-d H:i:s') . "\n");
fwrite($fp, "-- Host: " . DB_HOST . "\n");
fwrite($fp, "-- Database: " . DB_NAME . "\n\n");
fwrite($fp, "SET FOREIGN_KEY_CHECKS=0;\n\n");

foreach ($tables as $table) {
    echo "  Dumping table structure & data: {$table}...\n";
    
    // Structure
    $createStmt = $db->query("SHOW CREATE TABLE `{$table}`")->fetch(PDO::FETCH_ASSOC);
    fwrite($fp, "\n-- Table structure for `{$table}`\n");
    fwrite($fp, "DROP TABLE IF EXISTS `{$table}`;\n");
    fwrite($fp, $createStmt['Create Table'] . ";\n\n");
    
    // Data
    $rows = $db->query("SELECT * FROM `{$table}`");
    $columns = null;
    while ($row = $rows->fetch(PDO::FETCH_ASSOC)) {
        if ($columns === null) {
            $columns = array_map(function($c) { return "`{$c}`"; }, array_keys($row));
            $columnsStr = implode(', ', $columns);
        }
        
        $values = array_map(function($v) use ($db) {
            if ($v === null) return 'NULL';
            return $db->quote($v);
        }, array_values($row));
        
        $valuesStr = implode(', ', $values);
        fwrite($fp, "INSERT INTO `{$table}` ({$columnsStr}) VALUES ({$valuesStr});\n");
    }
    
    $columns = null; // Reset for next table
}

fwrite($fp, "\nSET FOREIGN_KEY_CHECKS=1;\n");
fclose($fp);

echo "Database dump complete. Compressing SQL file...\n";

// 2. Compress the SQL dump using gzip
$gz = gzopen($gzPath, 'w9');
if (!$gz) {
    echo "[ERROR] Failed to create gzip file at {$gzPath}\n";
    @unlink($sqlPath);
    exit(1);
}

$sqlFp = fopen($sqlPath, 'r');
while (!feof($sqlFp)) {
    gzwrite($gz, fread($sqlFp, 65536));
}
fclose($sqlFp);
gzclose($gz);

// Delete the uncompressed SQL file
@unlink($sqlPath);
echo "Compressed backup created: {$gzPath} (" . number_format(filesize($gzPath)) . " bytes)\n";

// 3. Upload database backup to AWS S3 using AWS CLI
echo "\nStep 2: Uploading database backup to AWS S3...\n";
$s3Key = "backups/db/{$sqlFilename}.gz";
$s3Dest = "s3://{$bucket}/{$s3Key}";
$cmd = "aws s3 cp " . escapeshellarg($gzPath) . " " . escapeshellarg($s3Dest);

$output = [];
$retval = 0;
exec($cmd, $output, $retval);

foreach ($output as $line) {
    echo "  {$line}\n";
}

@unlink($gzPath); // Clean up temp file

if ($retval !== 0) {
    echo "[ERROR] Database upload failed with code {$retval}.\n";
} else {
    echo "[SUCCESS] Database backup uploaded to S3.\n";
}

// 4. Sync local storage/ directory with S3 using AWS CLI
echo "\nStep 3: Syncing storage folder with AWS S3...\n";
$storagePath = rtrim(STORAGE_PATH, '/\\');
$s3StorageDest = "s3://{$bucket}/backups/storage/";

// Sync command, excluding backup folders or temp files to avoid circular backup
$syncCmd = "aws s3 sync " . escapeshellarg($storagePath) . " " . escapeshellarg($s3StorageDest) . " --exclude \"tmp/*\" --exclude \"*.gz\"";

$syncOutput = [];
$syncRetval = 0;
exec($syncCmd, $syncOutput, $syncRetval);

foreach ($syncOutput as $line) {
    echo "  {$line}\n";
}

if ($syncRetval !== 0) {
    echo "[ERROR] Storage synchronization failed with code {$syncRetval}.\n";
} else {
    echo "[SUCCESS] Storage synchronization complete.\n";
}

echo "\n=== Raptor CRM S3 Backup completed at " . date('Y-m-d H:i:s') . " ===\n";
