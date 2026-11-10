<?php
/**
 * Raptor CRM — Candidate Deduplication Service (Sprint 4)
 * -------------------------------------------------------------------------
 * Detects duplicate candidate records by email, phone, and profile similarity.
 * Facilitates safe merging of duplicate candidate profiles while preserving
 * applications, documents, consents, and audit history.
 */

declare(strict_types=1);

class CandidateDeduplicationService {

    /**
     * Normalize email address for comparison.
     */
    public function normalizeEmail(string $email): string {
        return strtolower(trim($email));
    }

    /**
     * Normalize phone number to digits only (e.g. +91 98765-43210 -> 9876543210).
     */
    public function normalizePhone(string $phone): string {
        $digits = preg_replace('/\D+/', '', $phone);
        // If 12 digits starting with 91, extract national 10 digits
        if (strlen($digits) === 12 && str_starts_with($digits, '91')) {
            $digits = substr($digits, 2);
        }
        return $digits;
    }

    /**
     * Find potential duplicate candidates in the database.
     *
     * @param PDO $db
     * @param string $email
     * @param string $phone
     * @param string|null $fullName
     * @param int|null $excludeCandidateId
     * @return array Array of potential duplicates with match scores and metadata
     */
    public function findDuplicates(
        PDO $db,
        string $email,
        string $phone,
        ?string $fullName = null,
        ?int $excludeCandidateId = null
    ): array {
        $duplicates = [];
        $normEmail = $this->normalizeEmail($email);
        $normPhone = $this->normalizePhone($phone);

        $excludeSql = $excludeCandidateId ? " AND candidate_id != " . (int)$excludeCandidateId : "";

        // 1. Exact Email Match (Score: 100)
        if (!empty($normEmail)) {
            $stmt = $db->prepare("
                SELECT candidate_id, candidate_code, first_name, last_name, email, phone, 
                       current_company, current_designation, total_experience_years, created_at
                FROM recruitment_candidates
                WHERE LOWER(TRIM(email)) = :email {$excludeSql}
            ");
            $stmt->execute([':email' => $normEmail]);
            $matches = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($matches as $match) {
                $cid = (int)$match['candidate_id'];
                $duplicates[$cid] = [
                    'candidate'       => $match,
                    'confidence_score' => 100,
                    'match_reasons'   => ['exact_email'],
                    'primary_match'   => true
                ];
            }
        }

        // 2. Exact or Normalized Phone Match (Score: 95)
        if (!empty($normPhone)) {
            // Form a wildcard query matching digits regardless of spaces/dashes (e.g. %9%8%1%1%1%2%2%2%3%3%)
            $digitPattern = '%' . implode('%', str_split(substr($normPhone, -10))) . '%';

            $stmt = $db->prepare("
                SELECT candidate_id, candidate_code, first_name, last_name, email, phone,
                       current_company, current_designation, total_experience_years, created_at
                FROM recruitment_candidates
                WHERE (phone = :rawPhone OR phone LIKE :likePhone) {$excludeSql}
            ");
            $stmt->execute([
                ':rawPhone'  => $phone,
                ':likePhone' => $digitPattern
            ]);
            $phoneMatches = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($phoneMatches as $match) {
                $cid = (int)$match['candidate_id'];
                $matchPhoneNorm = $this->normalizePhone($match['phone']);

                if ($matchPhoneNorm === $normPhone || substr($matchPhoneNorm, -10) === substr($normPhone, -10)) {
                    if (isset($duplicates[$cid])) {
                        $duplicates[$cid]['match_reasons'][] = 'exact_phone';
                        $duplicates[$cid]['confidence_score'] = 100;
                    } else {
                        $duplicates[$cid] = [
                            'candidate'       => $match,
                            'confidence_score' => 95,
                            'match_reasons'   => ['exact_phone'],
                            'primary_match'   => false
                        ];
                    }
                }
            }
        }

        // 3. Name Similarity Check (Score: 70) if full name provided
        if (!empty($fullName) && empty($duplicates)) {
            $cleanName = trim(strtolower($fullName));
            $stmt = $db->query("
                SELECT candidate_id, candidate_code, first_name, last_name, email, phone,
                       current_company, current_designation, total_experience_years, created_at
                FROM recruitment_candidates
                WHERE 1=1 {$excludeSql}
                LIMIT 100
            ");
            $allCandidates = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($allCandidates as $cand) {
                $candName = trim(strtolower($cand['first_name'] . ' ' . $cand['last_name']));
                similar_text($cleanName, $candName, $percent);
                if ($percent >= 85.0) {
                    $cid = (int)$cand['candidate_id'];
                    $duplicates[$cid] = [
                        'candidate'       => $cand,
                        'confidence_score' => (int) round($percent),
                        'match_reasons'   => ['fuzzy_name_match'],
                        'primary_match'   => false
                    ];
                }
            }
        }

        // Sort results by confidence score descending
        usort($duplicates, static fn($a, $b) => $b['confidence_score'] <=> $a['confidence_score']);

        return array_values($duplicates);
    }

    /**
     * Merge a duplicate candidate record into a primary candidate record.
     * All linked applications, documents, notes, consents, and stages are safely transferred.
     *
     * @param PDO $db
     * @param int $primaryCandidateId
     * @param int $duplicateCandidateId
     * @param int $actorUserId
     * @return array Merge result status
     */
    public function mergeCandidates(
        PDO $db,
        int $primaryCandidateId,
        int $duplicateCandidateId,
        int $actorUserId
    ): array {
        if ($primaryCandidateId === $duplicateCandidateId) {
            throw new InvalidArgumentException("Cannot merge a candidate profile into itself.");
        }

        $db->beginTransaction();
        try {
            // Verify both candidates exist
            $stmtP = $db->prepare("SELECT * FROM recruitment_candidates WHERE candidate_id = :id");
            $stmtP->execute([':id' => $primaryCandidateId]);
            $primary = $stmtP->fetch(PDO::FETCH_ASSOC);

            $stmtD = $db->prepare("SELECT * FROM recruitment_candidates WHERE candidate_id = :id");
            $stmtD->execute([':id' => $duplicateCandidateId]);
            $duplicate = $stmtD->fetch(PDO::FETCH_ASSOC);

            if (!$primary || !$duplicate) {
                throw new RuntimeException("One or both candidate records do not exist.");
            }

            // Transfer applications: check for job_id conflicts
            $stmtApps = $db->prepare("SELECT * FROM recruitment_applications WHERE candidate_id = :id");
            $stmtApps->execute([':id' => $duplicateCandidateId]);
            $dupApps = $stmtApps->fetchAll(PDO::FETCH_ASSOC);

            foreach ($dupApps as $app) {
                $stmtCheck = $db->prepare("
                    SELECT application_id FROM recruitment_applications 
                    WHERE candidate_id = :pid AND job_id = :jid
                ");
                $stmtCheck->execute([
                    ':pid' => $primaryCandidateId,
                    ':jid' => $app['job_id']
                ]);
                $existingApp = $stmtCheck->fetch(PDO::FETCH_ASSOC);

                if ($existingApp) {
                    // Application already exists on primary candidate: transfer history and remove duplicate
                    $db->exec("
                        UPDATE recruitment_application_stages 
                        SET application_id = {$existingApp['application_id']} 
                        WHERE application_id = {$app['application_id']}
                    ");
                    $db->exec("DELETE FROM recruitment_applications WHERE application_id = {$app['application_id']}");
                } else {
                    // Re-link to primary candidate
                    $stmtUp = $db->prepare("UPDATE recruitment_applications SET candidate_id = :pid WHERE application_id = :aid");
                    $stmtUp->execute([':pid' => $primaryCandidateId, ':aid' => $app['application_id']]);
                }
            }

            // Transfer documents
            $stmtDoc = $db->prepare("UPDATE recruitment_candidate_documents SET candidate_id = :pid WHERE candidate_id = :did");
            $stmtDoc->execute([':pid' => $primaryCandidateId, ':did' => $duplicateCandidateId]);

            // Transfer consents
            $stmtCons = $db->prepare("UPDATE recruitment_candidate_consents SET candidate_id = :pid WHERE candidate_id = :did");
            $stmtCons->execute([':pid' => $primaryCandidateId, ':did' => $duplicateCandidateId]);

            // Transfer notes
            $stmtNotes = $db->prepare("UPDATE recruitment_candidate_notes SET candidate_id = :pid WHERE candidate_id = :did");
            $stmtNotes->execute([':pid' => $primaryCandidateId, ':did' => $duplicateCandidateId]);

            // Add system audit note on primary profile
            $mergeNote = sprintf(
                "System Audit: Candidate profile #%s (%s %s, %s) was merged into this record by User #%d.",
                $duplicate['candidate_code'],
                $duplicate['first_name'],
                $duplicate['last_name'],
                $duplicate['email'],
                $actorUserId
            );
            $stmtAddNote = $db->prepare("
                INSERT INTO recruitment_candidate_notes (candidate_id, author_user_id, note_text, is_confidential, created_at)
                VALUES (:cid, :uid, :note, 0, CURRENT_TIMESTAMP)
            ");
            $stmtAddNote->execute([
                ':cid'  => $primaryCandidateId,
                ':uid'  => $actorUserId,
                ':note' => $mergeNote
            ]);

            // Delete merged duplicate master record
            $stmtDel = $db->prepare("DELETE FROM recruitment_candidates WHERE candidate_id = :did");
            $stmtDel->execute([':did' => $duplicateCandidateId]);

            $db->commit();

            return [
                'success'              => true,
                'primary_candidate_id' => $primaryCandidateId,
                'merged_candidate_id'  => $duplicateCandidateId,
                'primary_code'         => $primary['candidate_code'],
                'merged_code'          => $duplicate['candidate_code']
            ];
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }
}
