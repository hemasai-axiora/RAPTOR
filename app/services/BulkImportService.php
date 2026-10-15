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

        // Auto-detect line endings and CSV delimiter
        @ini_set('auto_detect_line_endings', true);

        $firstLine = fgets($handle);
        rewind($handle);
        $delimiter = ',';
        if ($firstLine !== false) {
            $commas = substr_count($firstLine, ',');
            $semicolons = substr_count($firstLine, ';');
            $tabs = substr_count($firstLine, "\t");
            if ($semicolons > $commas && $semicolons > $tabs) {
                $delimiter = ';';
            } elseif ($tabs > $commas && $tabs > $semicolons) {
                $delimiter = "\t";
            }
        }

        while (($data = fgetcsv($handle, 4096, $delimiter)) !== false) {
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
            
            // CSV / Excel Formula Injection Prevention & Scientific Notation Normalization:
            foreach ($row as $key => $val) {
                $val = trim($val);
                // Convert scientific notation e.g. 9.19877E+11 or 1.23457E+11 to standard integer digits
                if (preg_match('/^[+-]?[0-9]*\.?[0-9]+[eE][+-]?[0-9]+$/', $val)) {
                    $val = sprintf('%.0f', (float)$val);
                }
                // Strip leading =, +, -, @ from all cells to prevent execution in spreadsheet viewers
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

            $globalAliases = [
                'employee_code' => ['employee_code', 'employee_id', 'emp_code', 'emp_id', 'empid', 'code', 'id'],
                'first_name' => ['first_name', 'firstname', 'first'],
                'last_name' => ['last_name', 'lastname', 'last', 'surname'],
                'email' => ['email', 'email_address', 'e_mail', 'mail'],
                'phone_number' => ['phone_number', 'phone', 'phone_no', 'mobile', 'mobile_number', 'contact_number', 'contact'],
                'department' => ['department', 'dept'],
                'job_title' => ['job_title', 'job', 'title', 'designation', 'position', 'role_title'],
                'reporting_manager_email' => ['reporting_manager_email', 'reporting_manager', 'reporting_id', 'reporting_to', 'manager_email', 'manager_id', 'manager', 'reports_to'],
                'date_of_joining' => ['date_of_joining', 'joining_date', 'doj', 'hire_date', 'date_of_join', 'joining'],
                'employment_type' => ['employment_type', 'employment', 'employmer', 'employmertype', 'type_of_employment'],
                'status' => ['status', 'user_status', 'state', 'active_status'],
                'work_location' => ['work_location', 'location', 'worklocation', 'work_place', 'workplace'],
                'date_of_birth' => ['date_of_birth', 'birth_date', 'dob', 'date_of_birth_dd_mm_yyyy'],
                'bio' => ['bio', 'notes', 'about', 'description', 'employee_bio'],
                'emergency_contact' => ['emergency_contact', 'emergency', 'emergency_phone', 'emergency_contact_number', 'emergency_no'],
                'account_holder_name' => ['account_holder_name', 'account_holder', 'holder_name', 'ac_holder_name', 'acc_holder_name'],
                'bank_name' => ['bank_name', 'bank'],
                'account_number' => ['account_number', 'account_no', 'acc_number', 'acc_no', 'ac_number', 'ac_no', 'bank_account_number'],
                'ifsc_code' => ['ifsc_code', 'ifsc', 'ifsc_no'],
                'branch_name' => ['branch_name', 'branch', 'branch_address', 'bank_branch'],
                'account_type' => ['account_type', 'acct_type', 'acc_type', 'type_of_account'],
                'pan_number' => ['pan_number', 'pan', 'pan_no', 'pancard', 'pan_card'],
                'aadhaar_number' => ['aadhaar_number', 'aadhaar', 'aadhaar_no', 'aadhar_number', 'aadhar', 'aadhar_no', 'uidai'],
                'uan' => ['uan', 'uan_number', 'uan_no', 'pf_uan'],
                'esic_number' => ['esic_number', 'esic', 'esic_no', 'esi_number', 'esi', 'esi_no'],
                'salary' => ['salary', 'ctc', 'salary_ctc', 'monthly_salary', 'basic_salary', 'gross_salary'],
                'pay_grade' => ['pay_grade', 'pay_band', 'salary_band', 'grade', 'band'],
                'pf_applicable' => ['pf_applicable', 'pf', 'is_pf_applicable', 'pf_eligible', 'provident_fund'],
                'role_in_system' => ['role_in_system', 'system_role', 'role', 'user_role', 'system_role_name'],
                'temp_password' => ['temp_password', 'temporary_password', 'password', 'new_password', 'pass']
            ];

            // Map keys of row to match config (case insensitive match + aliases)
            $mappedRow = [];
            foreach ($config['fields'] as $fieldName => $fieldSpec) {
                $foundKey = null;
                $aliases = $globalAliases[$fieldName] ?? [$fieldName];
                foreach (array_keys($row) as $csvKey) {
                    $cleanCsvKey = preg_replace('/[^a-z0-9]+/', '_', strtolower(trim($csvKey)));
                    $cleanCsvKey = trim($cleanCsvKey, '_');
                    if ($cleanCsvKey === strtolower($fieldName) || in_array($cleanCsvKey, $aliases, true)) {
                        $foundKey = $csvKey;
                        break;
                    }
                }
                $mappedRow[$fieldName] = ($foundKey !== null) ? trim($row[$foundKey]) : '';
            }

            // Fallback for single full name column if first_name is empty
            if (empty($mappedRow['first_name'])) {
                foreach (array_keys($row) as $csvKey) {
                    $cleanCsvKey = preg_replace('/[^a-z0-9]+/', '_', strtolower(trim($csvKey)));
                    $cleanCsvKey = trim($cleanCsvKey, '_');
                    if (in_array($cleanCsvKey, ['name', 'full_name', 'fullname', 'employee_name'], true) && !empty(trim($row[$csvKey]))) {
                        $parts = explode(' ', trim($row[$csvKey]), 2);
                        $mappedRow['first_name'] = $parts[0];
                        if (empty($mappedRow['last_name']) && isset($parts[1])) {
                            $mappedRow['last_name'] = $parts[1];
                        }
                        break;
                    }
                }
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
                    $cleanEmail = strtolower(trim($val));
                    $mappedRow[$fieldName] = $cleanEmail;
                    if (!filter_var($cleanEmail, FILTER_VALIDATE_EMAIL)) {
                        $errors[$fieldName] = "Invalid email format '$val'. Expected a valid email address.";
                    }
                } elseif ($fieldSpec['type'] === 'date') {
                    // Flexibly accept DD-MM-YYYY, YYYY-MM-DD, DD/MM/YYYY, YYYY/MM/DD, etc.
                    $cleanDate = trim(str_replace('/', '-', $val));
                    $parsed = null;
                    foreach (['d-m-Y', 'Y-m-d', 'm-d-Y', 'd.m.Y', 'd-M-Y', 'd-m-y', 'Y/m/d', 'd/m/Y'] as $fmt) {
                        $d = DateTime::createFromFormat($fmt, $cleanDate);
                        if ($d !== false) {
                            $parsed = $d->format('Y-m-d');
                            break;
                        }
                    }
                    if (!$parsed) {
                        $ts = strtotime($cleanDate);
                        if ($ts && $ts > 0) {
                            $parsed = date('Y-m-d', $ts);
                        }
                    }
                    if (!$parsed) {
                        $errors[$fieldName] = "Invalid date format '$val'. Expected DD-MM-YYYY or YYYY-MM-DD.";
                    } else {
                        $mappedRow[$fieldName] = $parsed;
                    }
                } elseif ($fieldSpec['type'] === 'number') {
                    $cleanNum = str_replace([',', ' ', '$', '₹'], '', $val);
                    if (!is_numeric($cleanNum)) {
                        $errors[$fieldName] = "Must be a number.";
                    } else {
                        $mappedRow[$fieldName] = $cleanNum;
                    }
                } elseif ($fieldSpec['type'] === 'enum') {
                    $allowed = array_map('strtolower', $fieldSpec['allowed_values'] ?? []);
                    // Convert yes/no or true/false to 1/0 for boolean-style enums
                    if ($allowed === ['0', '1'] || $allowed === ['1', '0']) {
                        if (in_array(strtolower($val), ['1', 'yes', 'true', 'y'], true)) {
                            $val = '1';
                        } elseif (in_array(strtolower($val), ['0', 'no', 'false', 'n'], true)) {
                            $val = '0';
                        }
                        $mappedRow[$fieldName] = $val;
                    }
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

            // 5. Custom row-level business rules
            if (empty($errors) && isset($callbacks['validate_row']) && is_callable($callbacks['validate_row'])) {
                $rowErrors = call_user_func($callbacks['validate_row'], $mappedRow);
                if (!empty($rowErrors) && is_array($rowErrors)) {
                    $errors = array_merge($errors, $rowErrors);
                }
            }

            // 6. Database duplicate check (only if there are no parsing errors so far)
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

                $sp = 'sp_' . $row['index'];
                $this->db->exec("SAVEPOINT $sp");

                try {
                    // Call the custom module-specific import callback
                    $success = call_user_func($importCallback, $row['data'], $row['action'], $duplicateStrategy);
                    if ($success) {
                        $this->db->exec("RELEASE SAVEPOINT $sp");
                        $imported++;
                    } else {
                        $this->db->exec("ROLLBACK TO SAVEPOINT $sp");
                        $failed++;
                        $errors[] = [
                            'row' => $row['index'],
                            'data' => $row['data'],
                            'reason' => 'Database insert/update failed.'
                        ];
                    }
                } catch (Throwable $e) {
                    $this->db->exec("ROLLBACK TO SAVEPOINT $sp");
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
        } catch (Throwable $e) {
            // Rollback everything on general system transaction failures
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
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
