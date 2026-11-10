<?php
/**
 * Raptor CRM — Blind Interview Evaluation & Scorecard Service (Sprint 5)
 * -------------------------------------------------------------------------
 * Enforces blind evaluation governance: peer interviewers cannot view
 * candidate evaluations submitted by other panel members until they submit
 * their own independent scorecard.
 */

declare(strict_types=1);

class InterviewEvaluationService {

    public const RECOMMENDATIONS = [
        'strongly_recommend' => 'Strongly Recommend for Hire',
        'recommend'          => 'Recommend for Hire',
        'hold'               => 'Hold / Further Assessment Required',
        'do_not_recommend'   => 'Do Not Recommend'
    ];

    /**
     * Check if an interviewer has already submitted their scorecard for an interview.
     */
    public function hasSubmittedFeedback(PDO $db, int $interviewId, int $userId): bool {
        $stmt = $db->prepare("
            SELECT feedback_id 
            FROM recruitment_interview_feedback 
            WHERE interview_id = :iid AND interviewer_user_id = :uid 
            LIMIT 1
        ");
        $stmt->execute([':iid' => $interviewId, ':uid' => $userId]);
        return (bool)$stmt->fetchColumn();
    }

    /**
     * Submit an independent interview scorecard.
     *
     * @param PDO $db
     * @param int $interviewId
     * @param int $interviewerUserId
     * @param array $data Scorecard payload
     * @return array Submission result
     */
    public function submitFeedback(
        PDO $db,
        int $interviewId,
        int $interviewerUserId,
        array $data
    ): array {
        // Validate panel membership or HR override
        $stmtPanel = $db->prepare("
            SELECT panel_id 
            FROM recruitment_interview_panel 
            WHERE interview_id = :iid AND interviewer_user_id = :uid
        ");
        $stmtPanel->execute([':iid' => $interviewId, ':uid' => $interviewerUserId]);
        $isPanel = (bool)$stmtPanel->fetchColumn();

        if (!$isPanel) {
            // Verify if user is admin / HR
            $stmtUser = $db->prepare("SELECT r.role_name FROM users u JOIN roles r ON u.role_id = r.role_id WHERE u.user_id = :id");
            $stmtUser->execute([':id' => $interviewerUserId]);
            $role = (string)$stmtUser->fetchColumn();
            if (!in_array(strtolower($role), ['admin', 'ceo', 'hr', 'hr_manager'], true)) {
                throw new InvalidArgumentException("User is not an assigned interviewer on this interview panel.");
            }
        }

        $techRating    = (int)($data['technical_rating'] ?? 3);
        $probRating    = (int)($data['problem_solving_rating'] ?? 3);
        $commRating    = (int)($data['communication_rating'] ?? 3);
        $fitRating     = (int)($data['cultural_fit_rating'] ?? 3);
        $overallRating = (int)($data['overall_rating'] ?? 3);

        foreach ([$techRating, $probRating, $commRating, $fitRating, $overallRating] as $r) {
            if ($r < 1 || $r > 5) {
                throw new InvalidArgumentException("All rating criteria must be scored between 1 and 5.");
            }
        }

        $recommendation = trim((string)($data['recommendation'] ?? 'recommend'));
        if (!isset(self::RECOMMENDATIONS[$recommendation])) {
            throw new InvalidArgumentException("Invalid hiring recommendation: '{$recommendation}'.");
        }

        $detailedComments = trim((string)($data['detailed_comments'] ?? ''));
        if (empty($detailedComments)) {
            throw new InvalidArgumentException("Detailed evaluation comments are required.");
        }

        $strengths = trim((string)($data['strengths'] ?? ''));
        $concerns  = trim((string)($data['concerns'] ?? ''));

        $db->beginTransaction();
        try {
            // Check if existing feedback exists
            $stmtCheck = $db->prepare("
                SELECT feedback_id 
                FROM recruitment_interview_feedback 
                WHERE interview_id = :iid AND interviewer_user_id = :uid
            ");
            $stmtCheck->execute([':iid' => $interviewId, ':uid' => $interviewerUserId]);
            $existingId = $stmtCheck->fetchColumn();

            if ($existingId) {
                $stmtUpdate = $db->prepare("
                    UPDATE recruitment_interview_feedback 
                    SET technical_rating = :tech, problem_solving_rating = :prob,
                        communication_rating = :comm, cultural_fit_rating = :fit,
                        overall_rating = :overall, recommendation = :rec,
                        strengths = :str, concerns = :con, detailed_comments = :comm_text,
                        updated_at = CURRENT_TIMESTAMP
                    WHERE feedback_id = :fid
                ");
                $stmtUpdate->execute([
                    ':tech'      => $techRating,
                    ':prob'      => $probRating,
                    ':comm'      => $commRating,
                    ':fit'       => $fitRating,
                    ':overall'   => $overallRating,
                    ':rec'       => $recommendation,
                    ':str'       => $strengths ?: null,
                    ':con'       => $concerns ?: null,
                    ':comm_text' => $detailedComments,
                    ':fid'       => (int)$existingId
                ]);
                $feedbackId = (int)$existingId;
            } else {
                $stmtInsert = $db->prepare("
                    INSERT INTO recruitment_interview_feedback (
                        interview_id, interviewer_user_id, technical_rating, problem_solving_rating,
                        communication_rating, cultural_fit_rating, overall_rating, recommendation,
                        strengths, concerns, detailed_comments, submitted_at, updated_at
                    ) VALUES (
                        :iid, :uid, :tech, :prob,
                        :comm, :fit, :overall, :rec,
                        :str, :con, :comm_text, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
                    )
                ");
                $stmtInsert->execute([
                    ':iid'       => $interviewId,
                    ':uid'       => $interviewerUserId,
                    ':tech'      => $techRating,
                    ':prob'      => $probRating,
                    ':comm'      => $commRating,
                    ':fit'       => $fitRating,
                    ':overall'   => $overallRating,
                    ':rec'       => $recommendation,
                    ':str'       => $strengths ?: null,
                    ':con'       => $concerns ?: null,
                    ':comm_text' => $detailedComments
                ]);
                $feedbackId = (int)$db->lastInsertId();
            }

            // Check if all assigned panel members have now submitted feedback
            $stmtPending = $db->prepare("
                SELECT COUNT(*) 
                FROM recruitment_interview_panel p
                LEFT JOIN recruitment_interview_feedback f 
                  ON p.interview_id = f.interview_id AND p.interviewer_user_id = f.interviewer_user_id
                WHERE p.interview_id = :iid AND f.feedback_id IS NULL
            ");
            $stmtPending->execute([':iid' => $interviewId]);
            $pendingCount = (int)$stmtPending->fetchColumn();

            if ($pendingCount === 0) {
                // All panel members submitted: mark interview as completed
                $db->exec("UPDATE recruitment_interviews SET status = 'completed', updated_at = CURRENT_TIMESTAMP WHERE interview_id = " . (int)$interviewId);
            }

            $db->commit();

            return [
                'success'        => true,
                'feedback_id'    => $feedbackId,
                'interview_id'   => $interviewId,
                'recommendation' => $recommendation,
                'is_completed'   => ($pendingCount === 0)
            ];
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /**
     * Retrieve interview evaluations with strict Blind Feedback enforcement.
     *
     * @param PDO $db
     * @param int $interviewId
     * @param int $requestingUserId
     * @param string $requestingRole
     * @return array Feedback scorecard payload respecting blind lock
     */
    public function getInterviewFeedback(
        PDO $db,
        int $interviewId,
        int $requestingUserId,
        string $requestingRole = 'employee'
    ): array {
        $isHrOrAdmin = in_array(strtolower($requestingRole), ['admin', 'ceo', 'hr', 'hr_manager'], true);
        $hasSubmitted = $this->hasSubmittedFeedback($db, $interviewId, $requestingUserId);

        // Blind Feedback Gate: Non-HR interviewers cannot inspect peer feedback until they have submitted theirs
        if (!$isHrOrAdmin && !$hasSubmitted) {
            return [
                'blind_lock_active' => true,
                'message'           => 'Blind evaluation active. Peer panel feedback is locked until you submit your independent evaluation.',
                'can_submit'        => true,
                'feedbacks'         => []
            ];
        }

        $stmt = $db->prepare("
            SELECT f.*, u.name AS interviewer_name, u.email AS interviewer_email, r.role_name
            FROM recruitment_interview_feedback f
            JOIN users u ON f.interviewer_user_id = u.user_id
            LEFT JOIN roles r ON u.role_id = r.role_id
            WHERE f.interview_id = :iid
            ORDER BY f.submitted_at ASC
        ");
        $stmt->execute([':iid' => $interviewId]);
        $feedbacks = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return [
            'blind_lock_active' => false,
            'message'           => 'All evaluations unlocked.',
            'can_submit'        => !$hasSubmitted,
            'feedbacks'         => $feedbacks
        ];
    }
}

