<?php
/**
 * Raptor CRM — Candidate Master Service (Sprint 4)
 * -------------------------------------------------------------------------
 * Manages the Candidate 360 profile master entity, manual profile intake,
 * resume and document attachments, internal recruiter notes, and blacklisting.
 */

declare(strict_types=1);

require_once __DIR__ . '/CandidateDeduplicationService.php';

class CandidateService {

    private CandidateDeduplicationService $dedupService;

    public function __construct(?CandidateDeduplicationService $dedupService = null) {
        $this->dedupService = $dedupService ?? new CandidateDeduplicationService();
    }

    /**
     * Generate unique candidate code: CAN-YYYY-XXXXX
     */
    public function generateCandidateCode(PDO $db): string {
        $year = date('Y');
        $prefix = "CAN-{$year}-";

        $stmt = $db->query("
            SELECT candidate_code 
            FROM recruitment_candidates 
            WHERE candidate_code LIKE '{$prefix}%' 
            ORDER BY candidate_id DESC 
            LIMIT 1
        ");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $seq = 1;
        if ($row && preg_match('/-(\d+)$/', $row['candidate_code'], $m)) {
            $seq = ((int) $m[1]) + 1;
        }

        return sprintf('%s%05d', $prefix, $seq);
    }

    /**
     * Create or update candidate record with deduplication checks.
     *
     * @param PDO $db
     * @param array $data Candidate profile payload
     * @param int|null $ownerId Assigned recruiter/owner user ID
     * @return array Result containing candidate_id, candidate_code, and duplicate status
     */
    public function createOrUpdateCandidate(PDO $db, array $data, ?int $ownerId = null): array {
        $email = trim((string)($data['email'] ?? ''));
        $phone = trim((string)($data['phone'] ?? ''));

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException("A valid candidate email address is required.");
        }
        if (empty($phone)) {
            throw new InvalidArgumentException("Candidate phone number is required.");
        }

        $firstName = trim((string)($data['first_name'] ?? ''));
        $lastName  = trim((string)($data['last_name'] ?? ''));
        if (empty($firstName) || empty($lastName)) {
            throw new InvalidArgumentException("Candidate first and last names are required.");
        }

        // Deduplication check
        $duplicates = $this->dedupService->findDuplicates($db, $email, $phone, "{$firstName} {$lastName}");

        if (!empty($duplicates)) {
            $primaryDup = $duplicates[0];
            $candId = (int)$primaryDup['candidate']['candidate_id'];
            $candCode = (string)$primaryDup['candidate']['candidate_code'];

            // Update profile with newest non-empty details
            $updateFields = [];
            $params = [':id' => $candId];

            if (!empty($data['current_company'])) {
                $updateFields[] = "current_company = :comp";
                $params[':comp'] = trim($data['current_company']);
            }
            if (!empty($data['current_designation'])) {
                $updateFields[] = "current_designation = :desig";
                $params[':desig'] = trim($data['current_designation']);
            }
            if (isset($data['total_experience_years'])) {
                $updateFields[] = "total_experience_years = :exp";
                $params[':exp'] = (float)$data['total_experience_years'];
            }
            if (!empty($data['primary_skills'])) {
                $updateFields[] = "primary_skills = :skills";
                $params[':skills'] = trim($data['primary_skills']);
            }
            if (!empty($data['city'])) {
                $updateFields[] = "city = :city";
                $params[':city'] = trim($data['city']);
            }
            if (isset($data['current_ctc'])) {
                $updateFields[] = "current_ctc = :cctc";
                $params[':cctc'] = (float)$data['current_ctc'];
            }
            if (isset($data['expected_ctc'])) {
                $updateFields[] = "expected_ctc = :ectc";
                $params[':ectc'] = (float)$data['expected_ctc'];
            }

            if (!empty($updateFields)) {
                $updateFields[] = "updated_at = CURRENT_TIMESTAMP";
                $sql = "UPDATE recruitment_candidates SET " . implode(', ', $updateFields) . " WHERE candidate_id = :id";
                $stmt = $db->prepare($sql);
                $stmt->execute($params);
            }

            return [
                'candidate_id'   => $candId,
                'candidate_code' => $candCode,
                'is_duplicate'   => true,
                'match_score'    => $primaryDup['confidence_score'],
                'match_reasons'  => $primaryDup['match_reasons']
            ];
        }

        // New Candidate Intake
        $candCode = $this->generateCandidateCode($db);

        $stmt = $db->prepare("
            INSERT INTO recruitment_candidates (
                candidate_code, first_name, last_name, email, phone,
                current_company, current_designation, total_experience_years,
                primary_skills, secondary_skills, highest_education,
                city, country, notice_period_days, current_ctc, expected_ctc,
                linkedin_url, portfolio_url, source, source_detail,
                is_blacklisted, candidate_owner_id, created_at, updated_at
            ) VALUES (
                :code, :fname, :lname, :email, :phone,
                :comp, :desig, :exp,
                :pskills, :sskills, :edu,
                :city, :country, :notice, :cctc, :ectc,
                :linkedin, :portfolio, :source, :src_detail,
                0, :owner_id, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
            )
        ");

        $stmt->execute([
            ':code'       => $candCode,
            ':fname'      => $firstName,
            ':lname'      => $lastName,
            ':email'      => $email,
            ':phone'      => $phone,
            ':comp'       => $data['current_company'] ?? null,
            ':desig'      => $data['current_designation'] ?? null,
            ':exp'        => (float)($data['total_experience_years'] ?? 0.0),
            ':pskills'    => $data['primary_skills'] ?? null,
            ':sskills'    => $data['secondary_skills'] ?? null,
            ':edu'        => $data['highest_education'] ?? null,
            ':city'       => $data['city'] ?? null,
            ':country'    => $data['country'] ?? 'India',
            ':notice'     => isset($data['notice_period_days']) ? (int)$data['notice_period_days'] : null,
            ':cctc'       => isset($data['current_ctc']) ? (float)$data['current_ctc'] : null,
            ':ectc'       => isset($data['expected_ctc']) ? (float)$data['expected_ctc'] : null,
            ':linkedin'   => $data['linkedin_url'] ?? null,
            ':portfolio'  => $data['portfolio_url'] ?? null,
            ':source'     => $data['source'] ?? 'career_portal',
            ':src_detail' => $data['source_detail'] ?? null,
            ':owner_id'   => $ownerId
        ]);

        $candId = (int) $db->lastInsertId();

        return [
            'candidate_id'   => $candId,
            'candidate_code' => $candCode,
            'is_duplicate'   => false,
            'match_score'    => 0,
            'match_reasons'  => []
        ];
    }

    /**
     * Fetch Candidate 360 profile with all associations and confidential salary masking.
     */
    public function getCandidateById(
        PDO $db,
        int $candidateId,
        ?int $requestingUserId = null,
        string $requestingRole = 'employee'
    ): ?array {
        $stmt = $db->prepare("SELECT * FROM recruitment_candidates WHERE candidate_id = :id LIMIT 1");
        $stmt->execute([':id' => $candidateId]);
        $cand = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$cand) {
            return null;
        }

        // Apply Salary & PII Confidentiality Protection
        $canViewSalary = in_array(strtolower($requestingRole), ['admin', 'ceo', 'hr', 'hr_manager'], true);
        if (!$canViewSalary) {
            $cand['current_ctc'] = null;
            $cand['expected_ctc'] = null;
            $cand['is_salary_masked'] = true;
        } else {
            $cand['is_salary_masked'] = false;
        }

        // Fetch applications for this candidate
        $stmtApps = $db->prepare("
            SELECT a.*, j.title AS job_title, j.department AS job_department, 
                   j.job_code, j.location AS job_location, r.requisition_code
            FROM recruitment_applications a
            JOIN recruitment_jobs j ON a.job_id = j.job_id
            JOIN recruitment_requisitions r ON j.requisition_id = r.requisition_id
            WHERE a.candidate_id = :id
            ORDER BY a.application_id DESC
        ");
        $stmtApps->execute([':id' => $candidateId]);
        $cand['applications'] = $stmtApps->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Fetch candidate documents
        $stmtDocs = $db->prepare("
            SELECT document_id, document_type, file_name, file_size_bytes, mime_type, 
                   is_primary_resume, created_at
            FROM recruitment_candidate_documents
            WHERE candidate_id = :id
            ORDER BY is_primary_resume DESC, document_id DESC
        ");
        $stmtDocs->execute([':id' => $candidateId]);
        $cand['documents'] = $stmtDocs->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Fetch candidate notes (segregate confidential notes)
        $confidentialSql = $canViewSalary ? "" : " AND is_confidential = 0";
        $stmtNotes = $db->prepare("
            SELECT n.*, u.name AS author_name, r.role_name AS author_role
            FROM recruitment_candidate_notes n
            LEFT JOIN users u ON n.author_user_id = u.user_id
            LEFT JOIN roles r ON u.role_id = r.role_id
            WHERE n.candidate_id = :id {$confidentialSql}
            ORDER BY n.note_id DESC
        ");
        $stmtNotes->execute([':id' => $candidateId]);
        $cand['notes'] = $stmtNotes->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Fetch candidate consents
        $stmtCons = $db->prepare("
            SELECT * FROM recruitment_candidate_consents
            WHERE candidate_id = :id
            ORDER BY consent_id DESC
        ");
        $stmtCons->execute([':id' => $candidateId]);
        $cand['consents'] = $stmtCons->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return $cand;
    }

    /**
     * List candidates with filtering, pagination, and total count.
     */
    public function listCandidates(PDO $db, array $filters = [], int $limit = 50, int $offset = 0): array {
        $conditions = ["1=1"];
        $params = [];

        if (!empty($filters['search'])) {
            $conditions[] = "(first_name LIKE :s OR last_name LIKE :s OR email LIKE :s OR phone LIKE :s OR primary_skills LIKE :s)";
            $params[':s'] = '%' . trim((string)$filters['search']) . '%';
        }

        if (!empty($filters['source'])) {
            $conditions[] = "source = :source";
            $params[':source'] = $filters['source'];
        }

        if (isset($filters['is_blacklisted']) && $filters['is_blacklisted'] !== '') {
            $conditions[] = "is_blacklisted = :bl";
            $params[':bl'] = (int)$filters['is_blacklisted'];
        }

        if (isset($filters['min_exp']) && (float)$filters['min_exp'] > 0) {
            $conditions[] = "total_experience_years >= :min_exp";
            $params[':min_exp'] = (float)$filters['min_exp'];
        }

        $whereSql = implode(' AND ', $conditions);

        // Count total matching candidates
        $stmtCount = $db->prepare("SELECT COUNT(*) FROM recruitment_candidates WHERE {$whereSql}");
        $stmtCount->execute($params);
        $totalCount = (int) $stmtCount->fetchColumn();

        // Fetch page of candidates
        $stmt = $db->prepare("
            SELECT c.*, 
                   (SELECT COUNT(*) FROM recruitment_applications a WHERE a.candidate_id = c.candidate_id) AS applications_count,
                   (SELECT d.file_name FROM recruitment_candidate_documents d WHERE d.candidate_id = c.candidate_id AND d.is_primary_resume = 1 LIMIT 1) AS primary_resume_name
            FROM recruitment_candidates c
            WHERE {$whereSql}
            ORDER BY c.candidate_id DESC
            LIMIT {$limit} OFFSET {$offset}
        ");
        $stmt->execute($params);
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return [
            'total'   => $totalCount,
            'limit'   => $limit,
            'offset'  => $offset,
            'records' => $records
        ];
    }

    /**
     * Blacklist candidate profile.
     */
    public function blacklistCandidate(PDO $db, int $candidateId, string $reason, int $actorUserId): bool {
        $stmt = $db->prepare("
            UPDATE recruitment_candidates 
            SET is_blacklisted = 1, blacklist_reason = :reason, updated_at = CURRENT_TIMESTAMP
            WHERE candidate_id = :id
        ");
        $success = $stmt->execute([
            ':id'     => $candidateId,
            ':reason' => $reason
        ]);

        if ($success) {
            $stmtNote = $db->prepare("
                INSERT INTO recruitment_candidate_notes (candidate_id, author_user_id, note_text, is_confidential, created_at)
                VALUES (:cid, :uid, :note, 1, CURRENT_TIMESTAMP)
            ");
            $stmtNote->execute([
                ':cid'  => $candidateId,
                ':uid'  => $actorUserId,
                ':note' => "Candidate blacklisted by User #{$actorUserId}. Reason: {$reason}"
            ]);
        }

        return $success;
    }

    /**
     * Un-blacklist candidate profile.
     */
    public function unblacklistCandidate(PDO $db, int $candidateId, int $actorUserId): bool {
        $stmt = $db->prepare("
            UPDATE recruitment_candidates 
            SET is_blacklisted = 0, blacklist_reason = NULL, updated_at = CURRENT_TIMESTAMP
            WHERE candidate_id = :id
        ");
        return $stmt->execute([':id' => $candidateId]);
    }

    /**
     * Add internal recruiter note to candidate profile.
     */
    public function addNote(
        PDO $db,
        int $candidateId,
        string $noteText,
        int $authorUserId,
        ?int $applicationId = null,
        bool $isConfidential = false
    ): int {
        $stmt = $db->prepare("
            INSERT INTO recruitment_candidate_notes (
                candidate_id, application_id, author_user_id, note_text, is_confidential, created_at
            ) VALUES (
                :cid, :aid, :uid, :note, :confidential, CURRENT_TIMESTAMP
            )
        ");
        $stmt->execute([
            ':cid'          => $candidateId,
            ':aid'          => $applicationId,
            ':uid'          => $authorUserId,
            ':note'         => trim($noteText),
            ':confidential' => $isConfidential ? 1 : 0
        ]);
        return (int) $db->lastInsertId();
    }

    /**
     * Attach a document to a candidate profile.
     */
    public function attachDocument(
        PDO $db,
        int $candidateId,
        string $documentType,
        string $storageKey,
        string $fileName,
        int $fileSizeBytes,
        string $mimeType,
        bool $isPrimaryResume = false,
        ?int $uploadedByUserId = null
    ): int {
        if ($isPrimaryResume) {
            // Unset previous primary resume
            $db->exec("
                UPDATE recruitment_candidate_documents 
                SET is_primary_resume = 0 
                WHERE candidate_id = {$candidateId} AND is_primary_resume = 1
            ");
        }

        $stmt = $db->prepare("
            INSERT INTO recruitment_candidate_documents (
                candidate_id, document_type, storage_key, file_name, file_size_bytes,
                mime_type, is_primary_resume, uploaded_by_user_id, created_at
            ) VALUES (
                :cid, :type, :key, :fname, :size, :mime, :primary, :uid, CURRENT_TIMESTAMP
            )
        ");
        $stmt->execute([
            ':cid'     => $candidateId,
            ':type'    => $documentType,
            ':key'     => $storageKey,
            ':fname'   => $fileName,
            ':size'    => $fileSizeBytes,
            ':mime'    => $mimeType,
            ':primary' => $isPrimaryResume ? 1 : 0,
            ':uid'     => $uploadedByUserId
        ]);

        return (int) $db->lastInsertId();
    }

    /**
     * Retrieve document record for secure authorized download.
     */
    public function getDocumentById(PDO $db, int $documentId): ?array {
        $stmt = $db->prepare("SELECT * FROM recruitment_candidate_documents WHERE document_id = :id LIMIT 1");
        $stmt->execute([':id' => $documentId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}
