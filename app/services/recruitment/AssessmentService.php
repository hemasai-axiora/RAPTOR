<?php
/**
 * Raptor CRM — Assessment & Assignment Evaluation Service (Sprint 6)
 * -------------------------------------------------------------------------
 * Manages assessment templates, candidate assignment dispatch, due-date
 * enforcement, submission handling, and recruiter grading rubrics.
 */

declare(strict_types=1);

require_once __DIR__ . '/ApplicationStageService.php';

class AssessmentService {

    public const TYPES = [
        'coding'                => 'Technical Coding Challenge',
        'take_home_assignment'  => 'Take-Home Architectural Assignment',
        'domain_knowledge'      => 'Domain & Case Study Exercise',
        'writing_sample'        => 'Writing / Strategy Proposal',
        'aptitude'              => 'Analytical & Aptitude Assessment'
    ];

    /**
     * Create a new assessment template definition.
     */
    public function createAssessment(PDO $db, array $data): int {
        $title = trim((string)($data['title'] ?? ''));
        if (empty($title)) {
            throw new InvalidArgumentException("Assessment title is required.");
        }

        $type = trim((string)($data['assessment_type'] ?? 'take_home_assignment'));
        if (!isset(self::TYPES[$type])) {
            throw new InvalidArgumentException("Invalid assessment type: '{$type}'.");
        }

        $description  = trim((string)($data['description'] ?? ''));
        $instructions = trim((string)($data['instructions'] ?? ''));
        $durationMin  = max(15, (int)($data['duration_minutes'] ?? 120));
        $maxScore     = max(1, (int)($data['max_score'] ?? 100));
        $passingScore = min($maxScore, max(1, (int)($data['passing_score'] ?? 60)));

        $stmt = $db->prepare("
            INSERT INTO recruitment_assessments (
                title, assessment_type, description, instructions, duration_minutes,
                max_score, passing_score, is_active, created_at
            ) VALUES (
                :title, :type, :desc, :inst, :dur,
                :max, :pass, 1, CURRENT_TIMESTAMP
            )
        ");
        $stmt->execute([
            ':title' => $title,
            ':type'  => $type,
            ':desc'  => $description,
            ':inst'  => $instructions,
            ':dur'   => $durationMin,
            ':max'   => $maxScore,
            ':pass'  => $passingScore
        ]);

        return (int) $db->lastInsertId();
    }

    /**
     * Fetch list of assessment templates.
     */
    public function listAssessments(PDO $db, bool $activeOnly = true): array {
        $whereSql = $activeOnly ? "WHERE is_active = 1" : "";
        $stmt = $db->query("SELECT * FROM recruitment_assessments {$whereSql} ORDER BY assessment_id DESC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Fetch single assessment by ID.
     */
    public function getAssessmentById(PDO $db, int $assessmentId): ?array {
        $stmt = $db->prepare("SELECT * FROM recruitment_assessments WHERE assessment_id = :id LIMIT 1");
        $stmt->execute([':id' => $assessmentId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Assign assessment to a candidate application with due date enforcement.
     */
    public function assignAssessment(
        PDO $db,
        int $assessmentId,
        int $applicationId,
        string $dueDate,
        int $assignedByUserId
    ): array {
        $assessment = $this->getAssessmentById($db, $assessmentId);
        if (!$assessment || (int)$assessment['is_active'] !== 1) {
            throw new RuntimeException("Assessment #{$assessmentId} is inactive or not found.");
        }

        $stmtApp = $db->prepare("
            SELECT a.*, c.first_name, c.last_name, c.email, j.title AS job_title 
            FROM recruitment_applications a
            JOIN recruitment_candidates c ON a.candidate_id = c.candidate_id
            JOIN recruitment_jobs j ON a.job_id = j.job_id
            WHERE a.application_id = :id
        ");
        $stmtApp->execute([':id' => $applicationId]);
        $app = $stmtApp->fetch(PDO::FETCH_ASSOC);

        if (!$app) {
            throw new RuntimeException("Application #{$applicationId} not found.");
        }

        if (strtotime($dueDate) <= time()) {
            throw new InvalidArgumentException("Assessment due date must be set in the future.");
        }

        $db->beginTransaction();
        try {
            $stmt = $db->prepare("
                INSERT INTO recruitment_assessment_submissions (
                    assessment_id, application_id, assigned_at, due_date, status, created_at
                ) VALUES (
                    :aid, :appid, CURRENT_TIMESTAMP, :due, 'assigned', CURRENT_TIMESTAMP
                )
            ");
            $stmt->execute([
                ':aid'   => $assessmentId,
                ':appid' => $applicationId,
                ':due'   => $dueDate
            ]);

            $submissionId = (int)$db->lastInsertId();

            // Auto-advance application stage to 'assessment' if not already there
            $stageService = new ApplicationStageService();
            if ($app['current_stage'] !== 'assessment') {
                $stageService->transitionStage(
                    $db,
                    $applicationId,
                    'assessment',
                    $assignedByUserId,
                    'ASSESSMENT_ASSIGNED',
                    "Assigned assessment '{$assessment['title']}'. Due by {$dueDate}."
                );
            }

            // Enqueue notification in recruitment_queue
            $payload = json_encode([
                'submission_id'   => $submissionId,
                'application_id'  => $applicationId,
                'candidate_name'  => "{$app['first_name']} {$app['last_name']}",
                'candidate_email' => $app['email'],
                'job_title'       => $app['job_title'],
                'assessment_title'=> $assessment['title'],
                'due_date'        => $dueDate,
                'instructions'    => $assessment['instructions']
            ]);

            $stmtQ = $db->prepare("
                INSERT INTO recruitment_queue (job_type, payload_json, status, scheduled_at, created_at)
                VALUES ('assessment_assigned', :payload, 'pending', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
            ");
            $stmtQ->execute([':payload' => $payload]);

            $db->commit();

            return [
                'success'       => true,
                'submission_id' => $submissionId,
                'due_date'      => $dueDate,
                'status'        => 'assigned'
            ];
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /**
     * Submit candidate assessment solution (link or file).
     * Enforces late submission flagging if submitted past due date.
     */
    public function submitAssessmentWork(
        PDO $db,
        int $submissionId,
        ?string $submissionUrl = null,
        ?string $storageFileKey = null
    ): array {
        $stmt = $db->prepare("SELECT * FROM recruitment_assessment_submissions WHERE submission_id = :id");
        $stmt->execute([':id' => $submissionId]);
        $sub = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$sub) {
            throw new RuntimeException("Assessment submission record #{$submissionId} not found.");
        }

        $now = time();
        $dueTimestamp = strtotime((string)$sub['due_date']);
        $isLate = ($now > $dueTimestamp);

        $status = $isLate ? 'submitted_late' : 'submitted';

        $stmtUp = $db->prepare("
            UPDATE recruitment_assessment_submissions 
            SET submitted_at = CURRENT_TIMESTAMP,
                submission_url = :url,
                submission_file_key = :fkey,
                status = :status
            WHERE submission_id = :id
        ");
        $stmtUp->execute([
            ':url'    => $submissionUrl ?: null,
            ':fkey'   => $storageFileKey ?: null,
            ':status' => $status,
            ':id'     => $submissionId
        ]);

        return [
            'success'       => true,
            'submission_id' => $submissionId,
            'is_late'       => $isLate,
            'status'        => $status,
            'submitted_at'  => date('Y-m-d H:i:s', $now)
        ];
    }

    /**
     * Grade and evaluate a submitted candidate assessment.
     */
    public function evaluateSubmission(
        PDO $db,
        int $submissionId,
        int $scoreAwarded,
        string $evaluatorComments,
        int $evaluatorUserId
    ): array {
        $stmt = $db->prepare("
            SELECT s.*, a.max_score, a.passing_score, a.title AS assessment_title,
                   app.current_stage, app.match_score_advisory, app.application_id
            FROM recruitment_assessment_submissions s
            JOIN recruitment_assessments a ON s.assessment_id = a.assessment_id
            JOIN recruitment_applications app ON s.application_id = app.application_id
            WHERE s.submission_id = :id
        ");
        $stmt->execute([':id' => $submissionId]);
        $sub = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$sub) {
            throw new RuntimeException("Submission #{$submissionId} not found.");
        }

        $maxScore = (int)$sub['max_score'];
        $passingScore = (int)$sub['passing_score'];

        if ($scoreAwarded < 0 || $scoreAwarded > $maxScore) {
            throw new InvalidArgumentException("Score awarded must be between 0 and {$maxScore}.");
        }

        $passed = ($scoreAwarded >= $passingScore);

        $db->beginTransaction();
        try {
            $stmtUp = $db->prepare("
                UPDATE recruitment_assessment_submissions 
                SET score_awarded = :score,
                    evaluator_comments = :comments,
                    evaluator_user_id = :uid,
                    status = 'evaluated'
                WHERE submission_id = :id
            ");
            $stmtUp->execute([
                ':score'    => $scoreAwarded,
                ':comments' => trim($evaluatorComments),
                ':uid'      => $evaluatorUserId,
                ':id'       => $submissionId
            ]);

            // Update application advisory score if passed
            $stmtScore = $db->prepare("
                UPDATE recruitment_applications 
                SET match_score_advisory = :score, updated_at = CURRENT_TIMESTAMP 
                WHERE application_id = :id
            ");
            $stmtScore->execute([
                ':score' => (int) round(($scoreAwarded / $maxScore) * 100),
                ':id'    => (int)$sub['application_id']
            ]);

            // If passed, suggest advancement
            $stageService = new ApplicationStageService();
            if ($passed && $sub['current_stage'] === 'assessment') {
                $stageService->transitionStage(
                    $db,
                    (int)$sub['application_id'],
                    'technical_interview',
                    $evaluatorUserId,
                    'PASSED_ASSESSMENT',
                    "Passed assessment '{$sub['assessment_title']}' with score {$scoreAwarded}/{$maxScore}."
                );
            }

            $db->commit();

            return [
                'success'       => true,
                'submission_id' => $submissionId,
                'score_awarded' => $scoreAwarded,
                'max_score'     => $maxScore,
                'passed'        => $passed
            ];
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /**
     * List all candidate submissions across applications.
     */
    public function listSubmissions(PDO $db, array $filters = []): array {
        $conditions = ["1=1"];
        $params = [];

        if (!empty($filters['status'])) {
            $conditions[] = "s.status = :status";
            $params[':status'] = $filters['status'];
        }

        if (!empty($filters['assessment_id'])) {
            $conditions[] = "s.assessment_id = :aid";
            $params[':aid'] = (int)$filters['assessment_id'];
        }

        $whereSql = implode(' AND ', $conditions);

        $stmt = $db->prepare("
            SELECT s.*, 
                   a.title AS assessment_title, a.assessment_type, a.max_score, a.passing_score,
                   app.application_code, app.current_stage,
                   c.first_name, c.last_name, c.email, c.candidate_code,
                   j.title AS job_title, j.department AS job_department,
                   u.name AS evaluator_name
            FROM recruitment_assessment_submissions s
            JOIN recruitment_assessments a ON s.assessment_id = a.assessment_id
            JOIN recruitment_applications app ON s.application_id = app.application_id
            JOIN recruitment_candidates c ON app.candidate_id = c.candidate_id
            JOIN recruitment_jobs j ON app.job_id = j.job_id
            LEFT JOIN users u ON s.evaluator_user_id = u.user_id
            WHERE {$whereSql}
            ORDER BY s.submission_id DESC
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Fetch single submission by ID.
     */
    public function getSubmissionById(PDO $db, int $submissionId): ?array {
        $stmt = $db->prepare("
            SELECT s.*, 
                   a.title AS assessment_title, a.assessment_type, a.description, a.instructions,
                   a.max_score, a.passing_score,
                   app.application_code, app.current_stage, app.candidate_id,
                   c.first_name, c.last_name, c.email, c.candidate_code,
                   j.title AS job_title, j.department AS job_department,
                   u.name AS evaluator_name
            FROM recruitment_assessment_submissions s
            JOIN recruitment_assessments a ON s.assessment_id = a.assessment_id
            JOIN recruitment_applications app ON s.application_id = app.application_id
            JOIN recruitment_candidates c ON app.candidate_id = c.candidate_id
            JOIN recruitment_jobs j ON app.job_id = j.job_id
            LEFT JOIN users u ON s.evaluator_user_id = u.user_id
            WHERE s.submission_id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $submissionId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}

