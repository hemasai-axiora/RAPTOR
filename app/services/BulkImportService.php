<?php
// Generic, Reusable Bulk Import Service for Raptor

class BulkImportService {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->ensureLogsTableExists();
    }

    /**
     * Self-healing DB check for import_logs table.
     */
    private function ensureLogsTableExists() {
        try {
            $this->db->exec("
                CREATE TABLE IF NOT EXISTS import_logs (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    module VARCHAR(50) NOT NULL,
                    filename VARCHAR(255) NOT NULL,
                    rows_imported INT DEFAULT 0,
                    rows_skipped INT DEFAULT 0,
                    rows_failed INT DEFAULT 0,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ");
        } catch (Exception $e) {
            // Best effort table creation
        }
    }

    /**
     * Parses a CSV file into an array of associative arrays using header mapping.
     */
    public function parseCsv(string $filePath, int $maxRows = 5000): array {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            throw new Exception("CSV file not found or not readable.");
        }

        $fileSize = filesize($filePath);
        if ($fileSize > 5 * 1024 * 1024) { // 5MB limit
            throw new Exception("File size exceeds the maximum limit of 5MB.");
        }

        $handle = fopen($filePath, 'r');
        if (!$handle) {
            throw new Exception("Unable to open CSV file.");
        }

        $headers = [];
        $rows = [];
        $rowCount = 0;

        // Auto-detect line endings
        @ini_set('auto_detect_line_endings', true);

        while (($data = fgetcsv($handle, 4096, ',')) !== false) {
            // Strip BOM from headers if present
            if ($rowCount === 0) {
                if (!empty($data) && strpos($data[0], "\xEF\xBB\xBF") === 0) {
                    $data[0] = substr($data[0], 3);
                }
                // Normalize headers to lowercase/trimmed to prevent casing mismatch
                $headers = array_map(function($h) {
                    return strtolower(trim($h));
                }, $data);
                $rowCount++;
                continue;
            }

            if (empty(array_filter($data))) {
                // Skip completely blank rows
                continue;
            }

            // Fill missing columns with empty string or slice extra columns
            if (count($data) < count($headers)) {
                $data = array_pad($data, count($headers), '');
            } elseif (count($data) > count($headers)) {
                $data = array_slice($data, 0, count($headers));
            }

            $row = array_combine($headers, $data);
            
            // CSV / Excel Formula Injection Prevention:
            // Strip leading =, +, -, @ from all cells to prevent execution in spreadsheet viewers
            foreach ($row as $key => $val) {
                $val = trim($val);
                if (strlen($val) > 0 && in_array($val[0], ['=', '+', '-', '@'], true)) {
                    $val = ltrim($val, "=+-@ ");
                }
                $row[$key] = $val;
            }

            $rows[] = $row;
            $rowCount++;

            if ($rowCount > $maxRows) {
                fclose($handle);
                throw new Exception("File exceeds the maximum limit of $maxRows rows.");
            }
        }

        fclose($handle);
        return $rows;
    }

    /**
     * Generic validation function.
     * Takes parsed CSV rows, a field-mapping config, and check callbacks,
     * and returns a validation summary + validated rows.
     */
    public function validate(array $rows, array $config, array $callbacks = []): array {
        $validationResults = [
            'total_rows' => count($rows),
            'valid_count' => 0,
            'error_count' => 0,
            'rows' => []
        ];

        // Track duplicates inside the file itself by checking specific unique fields
        $fileSeen = [];
        $uniqueFields = [];
        foreach ($config['fields'] as $fieldName => $fieldSpec) {
            if (!empty($fieldSpec['unique_in_file'])) {
                $uniqueFields[] = $fieldName;
                $fileSeen[$fieldName] = [];
            }
        }

        foreach ($rows as $index => $row) {
            $errors = [];
            $action = 'create'; // default action
            $matchedUserId = null;

            // Map keys of row to match config (case insensitive match)
            $mappedRow = [];
            foreach ($config['fields'] as $fieldName => $fieldSpec) {
                $foundKey = null;
                // Try exact match, then try match replacing underscores/spaces
                foreach (array_keys($row) as $csvKey) {
                    if (strtolower($csvKey) === strtolower($fieldName) || 
                        str_replace([' ', '-'], '_', strtolower($csvKey)) === strtolower($fieldName)) {
                        $foundKey = $csvKey;
                        break;
                    }
                }
                $mappedRow[$fieldName] = ($foundKey !== null) ? trim($row[$foundKey]) : '';
            }

            // Perform field validations
            foreach ($config['fields'] as $fieldName => $fieldSpec) {
                $val = $mappedRow[$fieldName];

                // 1. Required check
                if ($fieldSpec['required'] && $val === '') {
                    $errors[$fieldName] = "Field is required.";
                    continue;
                }

                // Skip other validations if optional and empty
                if ($val === '') {
                    continue;
                }

                // 2. Type format checks
                if ($fieldSpec['type'] === 'email') {
                    if (!filter_var($val, FILTER_VALIDATE_EMAIL)) {
                        $errors[$fieldName] = "Invalid email format.";
                    }
                } elseif ($fieldSpec['type'] === 'date') {
                    // Expect DD-MM-YYYY
                    if (!preg_match('/^\d{2}-\d{2}-\d{4}$/', $val)) {
                        $errors[$fieldName] = "Invalid date format. Expected DD-MM-YYYY.";
                    } else {
                        // Check if valid date calendar-wise
                        list($d, $m, $y) = explode('-', $val);
                        if (!checkdate((int)$m, (int)$d, (int)$y)) {
                            $errors[$fieldName] = "Invalid calendar date.";
                        } else {
                            // Normalize it to YYYY-MM-DD for database query
                            $mappedRow[$fieldName] = sprintf('%04d-%02d-%02d', (int)$y, (int)$m, (int)$d);
                        }
                    }
                } elseif ($fieldSpec['type'] === 'number') {
                    if (!is_numeric($val)) {
                        $errors[$fieldName] = "Must be a number.";
                    }
                } elseif ($fieldSpec['type'] === 'enum') {
                    $allowed = array_map('strtolower', $fieldSpec['allowed_values'] ?? []);
                    if (!in_array(strtolower($val), $allowed)) {
                        $errors[$fieldName] = "Value must be one of: " . implode(', ', $fieldSpec['allowed_values']);
                    }
                }

                // 3. Custom verification callbacks (e.g. check if department exists)
                if (empty($errors[$fieldName]) && isset($callbacks['validate_field_' . $fieldName])) {
                    $customErr = call_user_func($callbacks['validate_field_' . $fieldName], $val);
                    if ($customErr) {
                        $errors[$fieldName] = $customErr;
                    }
                }
            }

            // 4. File-level uniqueness / duplicate check
            if (empty($errors)) {
                foreach ($uniqueFields as $uField) {
                    $val = $mappedRow[$uField];
                    if ($val !== '') {
                        if (in_array(strtolower($val), $fileSeen[$uField], true)) {
                            $errors[$uField] = "Duplicate record found within this CSV file.";
                        } else {
                            $fileSeen[$uField][] = strtolower($val);
                        }
                    }
                }
            }

            // 5. Database duplicate check (only if there are no parsing errors so far)
            if (empty($errors) && isset($callbacks['db_duplicate_check'])) {
                $duplicateResult = call_user_func($callbacks['db_duplicate_check'], $mappedRow);
                if ($duplicateResult) {
                    // Match found in DB
                    $action = 'duplicate';
                    $matchedUserId = $duplicateResult['user_id'] ?? null;
                    $duplicateMsg = $duplicateResult['message'] ?? 'Record already exists in system.';
                    $mappedRow['_duplicate_message'] = $duplicateMsg;
                }
            }

            $isValid = empty($errors);
            if ($isValid) {
                $validationResults['valid_count']++;
            } else {
                $validationResults['error_count']++;
            }

            $validationResults['rows'][] = [
                'index' => $index + 1,
                'status' => $isValid ? 'valid' : 'error',
                'action' => $action,
                'matched_user_id' => $matchedUserId,
                'errors' => $errors,
                'data' => $mappedRow
            ];
        }

        return $validationResults;
    }

    /**
     * Executes bulk import in a transaction.
     * Delegates row insertion to a custom callback wrapper.
     */
    public function import(array $validRows, string $duplicateStrategy, callable $importCallback, array $logMetadata): array {
        $user_id = $logMetadata['user_id'] ?? 0;
        $module = $logMetadata['module'] ?? 'employees';
        $filename = $logMetadata['filename'] ?? 'upload.csv';

        $imported = 0;
        $skipped = 0;
        $failed = 0;
        $errors = [];

        // Begin transaction
        $this->db->beginTransaction();
        try {
            foreach ($validRows as $row) {
                // If it is flagged as a duplicate in DB:
                if ($row['action'] === 'duplicate') {
                    if ($duplicateStrategy === 'skip') {
                        $skipped++;
                        continue;
                    }
                    // 'update' or 'create' (create anyway) are handled by the strategy setting passed to callback
                }

                try {
                    // Call the custom module-specific import callback
                    $success = call_user_func($importCallback, $row['data'], $row['action'], $duplicateStrategy);
                    if ($success) {
                        $imported++;
                    } else {
                        $failed++;
                        $errors[] = [
                            'row' => $row['index'],
                            'data' => $row['data'],
                            'reason' => 'Database insert/update failed.'
                        ];
                    }
                } catch (Exception $e) {
                    $failed++;
                    $errors[] = [
                        'row' => $row['index'],
                        'data' => $row['data'],
                        'reason' => $e->getMessage()
                    ];
                }
            }

            // Commit transaction
            $this->db->commit();
        } catch (Exception $e) {
            // Rollback everything on general system transaction failures
            $this->db->rollBack();
            throw new Exception("Transaction rolled back: " . $e->getMessage());
        }

        // Write import log
        try {
            $stmt = $this->db->prepare("
                INSERT INTO import_logs (user_id, module, filename, rows_imported, rows_skipped, rows_failed)
                VALUES (:uid, :mod, :file, :imp, :skip, :fail)
            ");
            $stmt->execute([
                ':uid' => $user_id,
                ':mod' => $module,
                ':file' => $filename,
                ':imp' => $imported,
                ':skip' => $skipped,
                ':fail' => $failed
            ]);
        } catch (Exception $logEx) {
            // Log writing is best-effort
        }

        return [
            'imported' => $imported,
            'skipped' => $skipped,
            'failed' => $failed,
            'errors' => $errors
        ];
    }
}
