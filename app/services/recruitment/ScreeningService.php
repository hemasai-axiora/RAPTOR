<?php
/**
 * Raptor CRM — Recruitment Screening Service (Sprint 5)
 * -------------------------------------------------------------------------
 * Manages recruiter initial candidate screening scorecards, weighted rubric
 * calculations, and stage progression recommendations.
 */

declare(strict_types=1);

require_once __DIR__ . '/ApplicationStageService.php';

class ScreeningService {

    public const RECOMMENDATIONS = [
        'shortlist'    => 'Shortlist for Interview',
        'reject'       => 'Reject Application',
        'hold'         => 'Put on Hold',
        'request_info' => 'Request Additional Information'
    ];

    /**
     * Submit an application screening evaluation.
     *
     * @param PDO $db
     * @param int $applicationId
     * @param int $screenerUserId
     * @param array $data
     * @return array Result containing screening_id, overall_score, and recommendation
     */
    public function submitScreening(
        PDO $db,
        int $applicationId,
        int $screenerUserId,
        array $data
    ): array {
        $skillsRating = (int)($data['skills_rating'] ?? 3);
        $expRating    = (int)($data['experience_rating'] ?? 3);
        $commRating   = (int)($data['communication_rating'] ?? 3);

        if ($skillsRating < 1 || $skillsRating > 5 || $expRating < 1 || $expRating > 5 || $commRating < 1 || $commRating > 5) {
            throw new InvalidArgumentException("All evaluation criteria ratings must be between 1 and 5.");
        }

        $recommendation = trim((string)($data['recommendation'] ?? 'hold'));
        if (!isset(self::RECOMMENDATIONS[$recommendation])) {
            throw new InvalidArgumentException("Invalid screening recommendation: '{$recommendation}'.");
        }

        $comments = trim((string)($data['evaluation_comments'] ?? ''));

        // Calculate weighted percentage score: Skills (40%), Experience (35%), Communication (25%)
        $weightedPoints = ($skillsRating * 0.40) + ($expRating * 0.35) + ($commRating * 0.25);
        $overallScore = round(($weightedPoints / 5.0) * 100.0, 2);

        $db->beginTransaction();
        try {
            // Verify application exists
            $stmtApp = $db->prepare("SELECT * FROM recruitment_applications WHERE application_id = :id");
            $stmtApp->execute([':id' => $applicationId]);
            $app = $stmtApp->fetch(PDO::FETCH_ASSOC);

            if (!$app) {
                throw new RuntimeException("Application #{$applicationId} not found.");
            }

            // Insert screening record
            $stmt = $db->prepare("
                INSERT INTO recruitment_application_screenings (
                    application_id, screener_user_id, skills_rating, experience_rating,
                    communication_rating, overall_score, recommendation, evaluation_comments, created_at
                ) VALUES (
                    :aid, :uid, :skills, :exp, :comm, :score, :rec, :comments, CURRENT_TIMESTAMP
                )
            ");
            $stmt->execute([
                ':aid'      => $applicationId,
                ':uid'      => $screenerUserId,
                ':skills'   => $skillsRating,
                ':exp'      => $expRating,
                ':comm'     => $commRating,
                ':score'    => $overallScore,
                ':rec'      => $recommendation,
                ':comments' => $comments ?: null
            ]);

            $screeningId = (int)$db->lastInsertId();

            // Update application advisory match score
            $stmtUpdateScore = $db->prepare("
                UPDATE recruitment_applications 
                SET match_score_advisory = :score, updated_at = CURRENT_TIMESTAMP 
                WHERE application_id = :id
            ");
            $stmtUpdateScore->execute([
                ':score' => (int) round($overallScore),
                ':id'    => $applicationId
            ]);

            // Auto-advance application stage if recommended
            $stageService = new ApplicationStageService();
            if ($recommendation === 'shortlist' && in_array($app['current_stage'], ['applied', 'screening'], true)) {
                $stageService->transitionStage(
                    $db,
                    $applicationId,
                    'shortlisted',
                    $screenerUserId,
                    'SCREENING_PASSED',
                    "Passed screening with score {$overallScore}%. {$comments}"
                );
            } elseif ($recommendation === 'reject') {
                $stageService->transitionStage(
                    $db,
                    $applicationId,
                    'rejected',
                    $screenerUserId,
                    'SCREENING_FAILED',
                    "Rejected at screening stage. Score: {$overallScore}%. {$comments}"
                );
            }

            $db->commit();

            return [
                'success'        => true,
                'screening_id'   => $screeningId,
                'application_id' => $applicationId,
                'overall_score'  => $overallScore,
                'recommendation' => $recommendation
            ];
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /**
     * Fetch screening evaluations for an application.
     */
    public function getScreeningsByApplication(PDO $db, int $applicationId): array {
        $stmt = $db->prepare("
            SELECT s.*, u.name AS screener_name, r.role_name AS screener_role
            FROM recruitment_application_screenings s
            LEFT JOIN users u ON s.screener_user_id = u.user_id
            LEFT JOIN roles r ON u.role_id = r.role_id
            WHERE s.application_id = :id
            ORDER BY s.screening_id DESC
        ");
        $stmt->execute([':id' => $applicationId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}

