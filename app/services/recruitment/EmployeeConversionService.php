<?php
/**
 * Raptor CRM — Employee Conversion Service (Sprint 8)
 * -------------------------------------------------------------------------
 * Manages 1-click transaction-safe conversion of accepted candidates into
 * permanent Raptor HRMS employees with strict idempotency guarantees.
 */

declare(strict_types=1);

require_once __DIR__ . '/ApplicationStageService.php';

class EmployeeConversionService {

    /**
     * Generate standard employee code: EMP-YYYY-XXXXX
     */
    public function generateEmployeeCode(PDO $db): string {
        $year = date('Y');
        $prefix = "EMP-{$year}-";

        $stmt = $db->query("
            SELECT employee_code 
            FROM employees 
            WHERE employee_code LIKE '{$prefix}%' 
            ORDER BY employee_id DESC 
            LIMIT 1
        ");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $seq = 1;
        if ($row && preg_match('/-(\d+)$/', (string)$row['employee_code'], $m)) {
            $seq = ((int) $m[1]) + 1;
        }

        return sprintf('%s%05d', $prefix, $seq);
    }

    /**
     * Check if a candidate application is ready for conversion to HRMS employee.
     */
    public function canConvertApplication(PDO $db, int $applicationId): array {
        $stmt = $db->prepare("
            SELECT a.*, c.is_blacklisted, c.first_name, c.last_name,
                   o.offer_id, o.status AS offer_status, o.annual_ctc
            FROM recruitment_applications a
            JOIN recruitment_candidates c ON a.candidate_id = c.candidate_id
            LEFT JOIN recruitment_offers o ON a.application_id = o.application_id AND o.status = 'accepted'
            WHERE a.application_id = :id
        ");
        $stmt->execute([':id' => $applicationId]);
        $app = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$app) {
            return ['can_convert' => false, 'reason' => "Application #{$applicationId} not found."];
        }

        if (!empty($app['converted_employee_id'])) {
            return ['can_convert' => false, 'reason' => "Candidate is already converted to Employee #{$app['converted_employee_id']}."];
        }

        if ((int)$app['is_blacklisted'] === 1) {
            return ['can_convert' => false, 'reason' => "Candidate profile is blacklisted."];
        }

        if (empty($app['offer_id']) || $app['offer_status'] !== 'accepted') {
            return ['can_convert' => false, 'reason' => "Candidate does not have an accepted offer on record."];
        }

        return [
            'can_convert' => true,
            'application' => $app
        ];
    }

    /**
     * Convert an accepted candidate into a permanent Raptor HRMS employee.
     * Strictly idempotent: if called multiple times, creates 0 duplicate records.
     *
     * @param PDO $db
     * @param int $applicationId
     * @param array $overrides Manual override parameters (e.g. employee_code, designation)
     * @param int $actorUserId HR executor user ID
     * @return array Conversion result
     */
    public function convertCandidateToEmployee(
        PDO $db,
        int $applicationId,
        array $overrides = [],
        int $actorUserId = 1
    ): array {
        // Idempotency Check: if already converted, return immediately!
        $stmtCheck = $db->prepare("SELECT converted_employee_id, current_stage FROM recruitment_applications WHERE application_id = :id");
        $stmtCheck->execute([':id' => $applicationId]);
        $existing = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        if (!$existing) {
            throw new RuntimeException("Application #{$applicationId} not found.");
        }

        if (!empty($existing['converted_employee_id'])) {
            $existingEmpId = (int)$existing['converted_employee_id'];
            return [
                'success'           => true,
                'already_converted' => true,
                'employee_id'       => $existingEmpId,
                'message'           => "Idempotency Guard: Candidate was already converted to Employee #{$existingEmpId}."
            ];
        }

        // Validate readiness
        $check = $this->canConvertApplication($db, $applicationId);
        if (!$check['can_convert']) {
            throw new RuntimeException("Cannot convert candidate: " . $check['reason']);
        }

        $appData = $check['application'];

        // Fetch full offer details
        $stmtOff = $db->prepare("SELECT * FROM recruitment_offers WHERE offer_id = :oid");
        $stmtOff->execute([':oid' => $appData['offer_id']]);
        $offer = $stmtOff->fetch(PDO::FETCH_ASSOC);

        // Fetch candidate details
        $stmtCand = $db->prepare("SELECT * FROM recruitment_candidates WHERE candidate_id = :cid");
        $stmtCand->execute([':cid' => $appData['candidate_id']]);
        $cand = $stmtCand->fetch(PDO::FETCH_ASSOC);

        $db->beginTransaction();
        try {
            // 1. Resolve or Create User Account in users table
            $stmtUser = $db->prepare("SELECT user_id FROM users WHERE LOWER(TRIM(email)) = :email LIMIT 1");
            $stmtUser->execute([':email' => strtolower(trim($cand['email']))]);
            $userId = $stmtUser->fetchColumn();

            if (!$userId) {
                // Determine employee role_id
                $stmtRole = $db->query("SELECT role_id FROM roles WHERE role_name IN ('employee', 'staff') LIMIT 1");
                $roleId = $stmtRole->fetchColumn() ?: 1;

                $fullName = trim("{$cand['first_name']} {$cand['last_name']}");
                $stmtNewUser = $db->prepare("
                    INSERT INTO users (name, email, role_id)
                    VALUES (:name, :email, :role)
                ");
                $stmtNewUser->execute([
                    ':name'  => $fullName,
                    ':email' => strtolower(trim($cand['email'])),
                    ':role'  => (int)$roleId
                ]);
                $userId = (int)$db->lastInsertId();
            } else {
                $userId = (int)$userId;
            }

            // 2. Generate Employee Code & Prepare Employee Profile
            $empCode = !empty($overrides['employee_code']) 
                ? trim($overrides['employee_code']) 
                : $this->generateEmployeeCode($db);

            $jobTitle   = $overrides['job_title'] ?? $offer['job_title'];
            $department = $overrides['department'] ?? $offer['department'];
            $location   = $overrides['work_location'] ?? $offer['work_location'];
            $empType    = $overrides['employment_type'] ?? $offer['employment_type'];
            $joiningDate = $overrides['date_of_joining'] ?? $offer['proposed_joining_date'];
            $ctc        = (float)($offer['annual_ctc'] ?? 0.0);
            $managerId  = !empty($offer['reporting_manager_id']) ? (int)$offer['reporting_manager_id'] : null;

            // 3. Insert into employees table
            $stmtEmp = $db->prepare("
                INSERT INTO employees (
                    user_id, employee_code, department, job_title,
                    hire_date, salary, work_location, employment_type,
                    reporting_manager_id
                ) VALUES (
                    :uid, :code, :dept, :title,
                    :hdate, :sal, :loc, :etype,
                    :mgr
                )
            ");
            $stmtEmp->execute([
                ':uid'   => $userId,
                ':code'  => $empCode,
                ':dept'  => $department,
                ':title' => $jobTitle,
                ':hdate' => $joiningDate,
                ':sal'   => $ctc,
                ':loc'   => $location,
                ':etype' => $empType,
                ':mgr'   => $managerId
            ]);

            $newEmployeeId = (int)$db->lastInsertId();

            // 4. Update recruitment_applications: set converted_employee_id and current_stage = 'hired'
            $stmtAppUp = $db->prepare("
                UPDATE recruitment_applications 
                SET converted_employee_id = :empid, 
                    current_stage = 'hired', 
                    stage_updated_at = CURRENT_TIMESTAMP, 
                    updated_at = CURRENT_TIMESTAMP
                WHERE application_id = :aid
            ");
            $stmtAppUp->execute([
                ':empid' => $newEmployeeId,
                ':aid'   => $applicationId
            ]);

            // 5. Insert stage history audit
            $stmtStage = $db->prepare("
                INSERT INTO recruitment_application_stages (
                    application_id, from_stage, to_stage, actor_user_id, reason_code, comments, created_at
                ) VALUES (
                    :aid, :from_stage, 'hired', :uid, 'CONVERTED_TO_EMPLOYEE', :comments, CURRENT_TIMESTAMP
                )
            ");
            $stmtStage->execute([
                ':aid'        => $applicationId,
                ':from_stage' => $appData['current_stage'] ?? 'offer_accepted',
                ':uid'        => $actorUserId,
                ':comments'   => "Converted to permanent employee #{$empCode}."
            ]);

            // 6. Record note in candidate profile
            $stmtNote = $db->prepare("
                INSERT INTO recruitment_candidate_notes (
                    candidate_id, application_id, author_user_id, note_text, is_confidential, created_at
                ) VALUES (
                    :cid, :aid, :uid, :note, 0, CURRENT_TIMESTAMP
                )
            ");
            $stmtNote->execute([
                ':cid'  => (int)$cand['candidate_id'],
                ':aid'  => $applicationId,
                ':uid'  => $actorUserId,
                ':note' => "Candidate successfully converted to permanent employee #{$empCode} ({$jobTitle}) by User #{$actorUserId}."
            ]);

            // 7. Enqueue welcome notification in recruitment_queue
            $payload = json_encode([
                'employee_id'    => $newEmployeeId,
                'employee_code'  => $empCode,
                'user_id'        => $userId,
                'employee_name'  => trim("{$cand['first_name']} {$cand['last_name']}"),
                'employee_email' => $cand['email'],
                'job_title'      => $jobTitle,
                'department'     => $department,
                'joining_date'   => $joiningDate
            ]);

            $stmtQ = $db->prepare("
                INSERT INTO recruitment_queue (job_type, payload_json, status, scheduled_at, created_at)
                VALUES ('employee_welcome', :payload, 'pending', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
            ");
            $stmtQ->execute([':payload' => $payload]);

            $db->commit();

            return [
                'success'           => true,
                'already_converted' => false,
                'employee_id'       => $newEmployeeId,
                'employee_code'     => $empCode,
                'user_id'           => $userId,
                'application_id'    => $applicationId,
                'status'            => 'hired'
            ];
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }
}

