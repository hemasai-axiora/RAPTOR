<?php
/**
 * Raptor CRM — Application Stage & Kanban Pipeline Service (Sprint 4)
 * -------------------------------------------------------------------------
 * Manages the ATS application lifecycle, stage transitions, stage audit logging,
 * and data aggregation for the interactive drag-and-drop Kanban board.
 */

declare(strict_types=1);

class ApplicationStageService {

    public const STAGES = [
        'applied'             => 'Applied',
        'screening'           => 'Screening',
        'shortlisted'         => 'Shortlisted',
        'assessment'          => 'Assessment',
        'technical_interview' => 'Technical Interview',
        'hr_interview'        => 'HR Interview',
        'final_interview'     => 'Final Interview',
        'offer_preparation'   => 'Offer Preparation',
        'offer_approved'      => 'Offer Approved',
        'offer_released'      => 'Offer Released',
        'offer_accepted'      => 'Offer Accepted',
        'bgv'                 => 'Background Verification',
        'pre_joining'         => 'Pre-Joining',
        'hired'               => 'Hired',
        'rejected'            => 'Rejected',
        'withdrawn'           => 'Withdrawn',
        'offer_declined'      => 'Offer Declined',
    ];

    public const KANBAN_COLUMNS = [
        'applied'             => 'Applied',
        'screening'           => 'Screening',
        'shortlisted'         => 'Shortlisted',
        'technical_interview' => 'Technical Round',
        'hr_interview'        => 'HR Interview',
        'offer_preparation'   => 'Offer Stage',
        'hired'               => 'Hired'
    ];

    /**
     * Verify if a stage transition is valid.
     */
    public function isValidTransition(string $fromStage, string $toStage): bool {
        if (!isset(self::STAGES[$toStage])) {
            return false;
        }

        // Terminal or exit stages can be entered from anywhere
        if (in_array($toStage, ['rejected', 'withdrawn', 'offer_declined'], true)) {
            return true;
        }

        // Once hired, cannot move back
        if ($fromStage === 'hired') {
            return false;
        }

        // Reopening from rejected/withdrawn is permitted
        if (in_array($fromStage, ['rejected', 'withdrawn', 'offer_declined'], true)) {
            return in_array($toStage, ['applied', 'screening', 'shortlisted'], true);
        }

        // Standard progression rules
        return true;
    }

    /**
     * Transition application to a new stage with audit log.
     *
     * @param PDO $db
     * @param int $applicationId
     * @param string $toStage
     * @param int|null $actorUserId
     * @param string|null $reasonCode
     * @param string|null $comments
     * @return array Transition result
     */
    public function transitionStage(
        PDO $db,
        int $applicationId,
        string $toStage,
        ?int $actorUserId = null,
        ?string $reasonCode = null,
        ?string $comments = null
    ): array {
        $stmt = $db->prepare("SELECT * FROM recruitment_applications WHERE application_id = :id");
        $stmt->execute([':id' => $applicationId]);
        $app = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$app) {
            throw new RuntimeException("Application #{$applicationId} not found.");
        }

        $fromStage = (string)$app['current_stage'];

        if (!$this->isValidTransition($fromStage, $toStage)) {
            throw new InvalidArgumentException("Invalid stage transition from '{$fromStage}' to '{$toStage}'.");
        }

        if ($fromStage === $toStage) {
            return [
                'success'        => true,
                'application_id' => $applicationId,
                'from_stage'     => $fromStage,
                'to_stage'       => $toStage,
                'changed'        => false
            ];
        }

        $db->beginTransaction();
        try {
            // Update application stage
            $updateSql = "
                UPDATE recruitment_applications 
                SET current_stage = :toStage, 
                    stage_updated_at = CURRENT_TIMESTAMP, 
                    updated_at = CURRENT_TIMESTAMP
            ";

            if ($toStage === 'rejected') {
                $updateSql .= ", rejection_reason = :reason, rejection_notes = :notes";
            }
            $updateSql .= " WHERE application_id = :id";

            $updateParams = [
                ':toStage' => $toStage,
                ':id'      => $applicationId
            ];
            if ($toStage === 'rejected') {
                $updateParams[':reason'] = $reasonCode ?? 'Not qualified';
                $updateParams[':notes']  = $comments;
            }

            $stmtUpdate = $db->prepare($updateSql);
            $stmtUpdate->execute($updateParams);

            // Insert audit history
            $stmtHist = $db->prepare("
                INSERT INTO recruitment_application_stages (
                    application_id, from_stage, to_stage, actor_user_id, reason_code, comments, created_at
                ) VALUES (
                    :aid, :fromStage, :toStage, :uid, :rcode, :comm, CURRENT_TIMESTAMP
                )
            ");
            $stmtHist->execute([
                ':aid'       => $applicationId,
                ':fromStage' => $fromStage,
                ':toStage'   => $toStage,
                ':uid'       => $actorUserId,
                ':rcode'     => $reasonCode,
                ':comm'      => $comments
            ]);

            $db->commit();

            return [
                'success'        => true,
                'application_id' => $applicationId,
                'from_stage'     => $fromStage,
                'to_stage'       => $toStage,
                'changed'        => true
            ];
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /**
     * Aggregate applications into structured Kanban board data.
     * Strictly excludes salary parameters from card models for role confidentiality.
     *
     * @param PDO $db
     * @param int|null $jobId
     * @param int|null $recruiterId
     * @return array Kanban board schema with columns and card elements
     */
    public function getKanbanBoard(PDO $db, ?int $jobId = null, ?int $recruiterId = null): array {
        $conditions = ["a.current_stage NOT IN ('rejected', 'withdrawn', 'offer_declined')"];
        $params = [];

        if ($jobId) {
            $conditions[] = "a.job_id = :jid";
            $params[':jid'] = $jobId;
        }

        if ($recruiterId) {
            $conditions[] = "(a.assigned_recruiter_id = :rid OR j.created_by = :rid)";
            $params[':rid'] = $recruiterId;
        }

        $whereSql = implode(' AND ', $conditions);

        $stmt = $db->prepare("
            SELECT a.application_id, a.application_code, a.candidate_id, a.job_id,
                   a.current_stage, a.stage_updated_at, a.match_score_advisory,
                   c.first_name, c.last_name, c.candidate_code, c.email, c.phone,
                   c.current_company, c.current_designation, c.total_experience_years,
                   c.primary_skills, c.city,
                   j.title AS job_title, j.department AS job_department, j.job_code
            FROM recruitment_applications a
            JOIN recruitment_candidates c ON a.candidate_id = c.candidate_id
            JOIN recruitment_jobs j ON a.job_id = j.job_id
            WHERE {$whereSql}
            ORDER BY a.stage_updated_at ASC
        ");
        $stmt->execute($params);
        $applications = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Initialize Kanban board columns
        $board = [];
        foreach (self::KANBAN_COLUMNS as $colKey => $colTitle) {
            $board[$colKey] = [
                'key'         => $colKey,
                'title'       => $colTitle,
                'count'       => 0,
                'cards'       => []
            ];
        }

        $now = time();

        foreach ($applications as $app) {
            $stage = $app['current_stage'];
            $targetCol = $stage;

            // Map sub-stages to main Kanban columns
            if (in_array($stage, ['offer_preparation', 'offer_approved', 'offer_released', 'offer_accepted', 'bgv', 'pre_joining'], true)) {
                $targetCol = 'offer_preparation';
            } elseif ($stage === 'final_interview') {
                $targetCol = 'hr_interview';
            } elseif ($stage === 'assessment') {
                $targetCol = 'shortlisted';
            }

            if (!isset($board[$targetCol])) {
                $targetCol = 'applied';
            }

            $stageTime = strtotime((string)$app['stage_updated_at']);
            $daysInStage = (int) max(0, floor(($now - $stageTime) / 86400));

            $card = [
                'application_id'        => (int)$app['application_id'],
                'application_code'      => $app['application_code'],
                'candidate_id'          => (int)$app['candidate_id'],
                'candidate_code'        => $app['candidate_code'],
                'candidate_name'        => trim($app['first_name'] . ' ' . $app['last_name']),
                'current_company'       => $app['current_company'] ?? 'Independent',
                'current_designation'   => $app['current_designation'] ?? 'Applicant',
                'total_experience'      => (float)$app['total_experience_years'],
                'primary_skills'        => array_filter(array_map('trim', explode(',', (string)($app['primary_skills'] ?? '')))),
                'job_title'             => $app['job_title'],
                'job_code'              => $app['job_code'],
                'current_stage'         => $app['current_stage'],
                'days_in_stage'         => $daysInStage,
                'match_score_advisory'  => $app['match_score_advisory'] !== null ? (int)$app['match_score_advisory'] : null
            ];

            $board[$targetCol]['cards'][] = $card;
            $board[$targetCol]['count']++;
        }

        return $board;
    }

    /**
     * Get stage transition history for an application.
     */
    public function getStageHistory(PDO $db, int $applicationId): array {
        $stmt = $db->prepare("
            SELECT s.*, u.name AS actor_name, r.role_name AS actor_role
            FROM recruitment_application_stages s
            LEFT JOIN users u ON s.actor_user_id = u.user_id
            LEFT JOIN roles r ON u.role_id = r.role_id
            WHERE s.application_id = :id
            ORDER BY s.stage_history_id ASC
        ");
        $stmt->execute([':id' => $applicationId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
