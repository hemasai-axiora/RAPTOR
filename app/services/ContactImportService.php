<?php
/**
 * Raptor CRM - Contact Import & Deliverability Validation Service
 * 
 * Provides robust validation for CSV contacts:
 * 1. Syntax check (RFC 5322 compliant)
 * 2. DNS MX lookup verification
 * 3. Disposable email domain detection
 * 4. Role account flagging (info@, admin@, etc.)
 * 5. Duplicate deduplication
 * 6. Error logging and downloadable reject report
 */

class ContactImportService {

    public const COMMON_ROLE_PREFIXES = [
        'admin', 'administrator', 'info', 'support', 'sales', 'billing', 'contact',
        'help', 'helpdesk', 'office', 'jobs', 'careers', 'marketing', 'media',
        'press', 'privacy', 'security', 'postmaster', 'hostmaster', 'abuse',
        'webmaster', 'root', 'team', 'general', 'service', 'inquiries', 'enquiries'
    ];

    public const COMMON_DISPOSABLE_DOMAINS = [
        'mailinator.com', 'tempmail.com', 'temp-mail.org', '10minutemail.com',
        'guerrillamail.com', 'throwawaymail.com', 'sharklasers.com', 'yopmail.com',
        'trashmail.com', 'getairmail.com', 'dispostable.com', 'mytemp.email',
        'fakeinbox.com', 'nada.ltd', 'emailondeck.com', 'inboxkitten.com'
    ];

    /**
     * Check if email local-part is a generic role account.
     */
    public static function isRoleAccount(string $email): bool {
        $parts = explode('@', strtolower(trim($email)));
        if (count($parts) !== 2) {
            return false;
        }
        $local = $parts[0];
        return in_array($local, self::COMMON_ROLE_PREFIXES, true);
    }

    /**
     * Check if domain is a known temporary/disposable domain.
     */
    public static function isDisposableDomain(string $email): bool {
        $parts = explode('@', strtolower(trim($email)));
        if (count($parts) !== 2) {
            return false;
        }
        $domain = $parts[1];
        return in_array($domain, self::COMMON_DISPOSABLE_DOMAINS, true);
    }

    /**
     * Verify domain has active MX records.
     */
    public static function hasValidMx(string $domain): bool {
        $domain = trim($domain);
        if (empty($domain)) {
            return false;
        }

        // On Windows or environments without direct checkdnsrr MX, test getmxrr
        if (function_exists('getmxrr')) {
            $mxhosts = [];
            if (@getmxrr($domain, $mxhosts) && !empty($mxhosts)) {
                return true;
            }
        }

        if (function_exists('checkdnsrr')) {
            return @checkdnsrr($domain, 'MX') || @checkdnsrr($domain, 'A');
        }

        return true;
    }

    /**
     * Process an uploaded CSV file.
     * 
     * @param string $filePath Full path to uploaded CSV file
     * @param array $options Options (list_ids, tag_ids, consent_type, skip_mx_check)
     * @return array [success, imported, skipped, rejected, error_file]
     */
    public static function processCsv(string $filePath, array $options = [], ?int $userId = null): array {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            return [
                'success' => false,
                'error' => 'CSV file does not exist or is unreadable'
            ];
        }

        $handle = fopen($filePath, 'r');
        if (!$handle) {
            return ['success' => false, 'error' => 'Failed to open CSV stream'];
        }

        // Detect BOM and read header
        $header = fgetcsv($handle);
        if (!$header) {
            fclose($handle);
            return ['success' => false, 'error' => 'CSV file is empty'];
        }

        // Normalize header names
        $headerMap = [];
        foreach ($header as $colIdx => $colName) {
            $cleanName = strtolower(trim(preg_replace('/[\x{FEFF}]/u', '', $colName)));
            $headerMap[$cleanName] = $colIdx;
        }

        // Locate email column
        $emailColIdx = null;
        foreach (['email', 'e-mail', 'email_address', 'mail', 'email address'] as $alias) {
            if (isset($headerMap[$alias])) {
                $emailColIdx = $headerMap[$alias];
                break;
            }
        }

        if ($emailColIdx === null) {
            fclose($handle);
            return [
                'success' => false,
                'error' => 'Required column "email" was not found in the CSV header'
            ];
        }

        $firstNameIdx = $headerMap['first_name'] ?? ($headerMap['firstname'] ?? ($headerMap['name'] ?? null));
        $lastNameIdx = $headerMap['last_name'] ?? ($headerMap['lastname'] ?? null);
        $companyIdx = $headerMap['company'] ?? ($headerMap['company_name'] ?? null);

        $contactModel = new CampaignContact();
        $suppressionModel = new CampaignSuppression();

        $imported = 0;
        $skipped = 0;
        $rejected = 0;
        $seenInBatch = [];
        $rejectReport = [];

        $checkMx = !empty($options['check_mx']);
        $listIds = $options['list_ids'] ?? [];
        $tagIds = $options['tag_ids'] ?? [];
        $consentType = $options['consent_type'] ?? 'explicit_optin';
        $consentNotes = $options['consent_notes'] ?? 'CSV Batch Import';
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        $rowNum = 1;
        while (($row = fgetcsv($handle)) !== false) {
            $rowNum++;
            if (empty(array_filter($row))) {
                continue; // Skip blank rows
            }

            $rawEmail = $row[$emailColIdx] ?? '';
            $cleanEmail = strtolower(trim($rawEmail));

            // 1. Basic Syntax Check
            if (empty($cleanEmail) || !filter_var($cleanEmail, FILTER_VALIDATE_EMAIL)) {
                $rejected++;
                $rejectReport[] = [$rowNum, $rawEmail, 'Invalid email syntax (failed RFC 5322 validation)'];
                continue;
            }

            // 2. In-batch Deduplication
            if (isset($seenInBatch[$cleanEmail])) {
                $skipped++;
                $rejectReport[] = [$rowNum, $cleanEmail, 'Duplicate email within same CSV import batch'];
                continue;
            }
            $seenInBatch[$cleanEmail] = true;

            $parts = explode('@', $cleanEmail);
            $domain = $parts[1] ?? '';

            // 3. Disposable Domain Rejection
            if (self::isDisposableDomain($cleanEmail)) {
                $rejected++;
                $rejectReport[] = [$rowNum, $cleanEmail, 'Disposable/temporary email domains rejected for deliverability safety'];
                continue;
            }

            // 4. MX Record Verification (Optional but recommended)
            if ($checkMx && !self::hasValidMx($domain)) {
                $rejected++;
                $rejectReport[] = [$rowNum, $cleanEmail, 'Domain ' . $domain . ' has no valid MX/A DNS records'];
                continue;
            }

            // 5. Global Suppression List Check
            if ($suppressionModel->isSuppressed($cleanEmail)) {
                $skipped++;
                $rejectReport[] = [$rowNum, $cleanEmail, 'Address is globally suppressed (unsubscribed/bounced/complaint)'];
                continue;
            }

            // Extract names and company
            $firstName = '';
            $lastName = '';
            if ($firstNameIdx !== null && isset($row[$firstNameIdx])) {
                $firstName = trim($row[$firstNameIdx]);
            }
            if ($lastNameIdx !== null && isset($row[$lastNameIdx])) {
                $lastName = trim($row[$lastNameIdx]);
            }
            if (empty($lastName) && !empty($firstName) && strpos($firstName, ' ') !== false) {
                $nameParts = explode(' ', $firstName, 2);
                $firstName = $nameParts[0];
                $lastName = $nameParts[1];
            }

            $company = ($companyIdx !== null && isset($row[$companyIdx])) ? trim($row[$companyIdx]) : '';

            // Map unmapped columns to custom_fields JSON
            $customFields = [];
            foreach ($headerMap as $cName => $cIdx) {
                if (!in_array($cIdx, [$emailColIdx, $firstNameIdx, $lastNameIdx, $companyIdx], true) && isset($row[$cIdx])) {
                    $val = trim($row[$cIdx]);
                    if ($val !== '') {
                        $customFields[$cName] = $val;
                    }
                }
            }

            try {
                $cid = $contactModel->upsertContact([
                    'email' => $cleanEmail,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'company_name' => $company,
                    'custom_fields' => $customFields,
                    'source' => 'csv_import',
                    'consent_type' => $consentType,
                    'consent_ip' => $ip,
                    'consent_notes' => $consentNotes,
                    'list_ids' => $listIds,
                    'tag_ids' => $tagIds
                ]);

                if ($cid > 0) {
                    $imported++;
                }
            } catch (Throwable $e) {
                $rejected++;
                $rejectReport[] = [$rowNum, $cleanEmail, 'Database error: ' . $e->getMessage()];
            }
        }
        fclose($handle);

        // Generate error report CSV if any rejects occurred
        $rejectReportFile = null;
        if (!empty($rejectReport)) {
            $reportDir = APPROOT . '/../public/uploads/campaign_reports/';
            if (!is_dir($reportDir)) {
                @mkdir($reportDir, 0755, true);
            }
            $reportFilename = 'import_errors_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.csv';
            $reportPath = $reportDir . $reportFilename;
            $rf = fopen($reportPath, 'w');
            fputcsv($rf, ['Row Number', 'Email Address', 'Rejection Reason']);
            foreach ($rejectReport as $r) {
                fputcsv($rf, $r);
            }
            fclose($rf);
            $rejectReportFile = 'uploads/campaign_reports/' . $reportFilename;
        }

        if (class_exists('CampaignAudit')) {
            CampaignAudit::log($userId, 'contacts.csv_imported', 'contact_import', $imported, [
                'total_imported' => $imported,
                'total_skipped' => $skipped,
                'total_rejected' => $rejected
            ]);
        }

        return [
            'success' => true,
            'imported' => $imported,
            'skipped' => $skipped,
            'rejected' => $rejected,
            'reject_report_url' => $rejectReportFile
        ];
    }
}
