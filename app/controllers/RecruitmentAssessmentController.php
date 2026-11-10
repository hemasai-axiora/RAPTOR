<?php
/**
 * Raptor CRM — Recruitment Assessment Controller (Sprint 6)
 * -------------------------------------------------------------------------
 * Manages assessment templates, candidate assignment dispatch, submission
 * review, and recruiter scoring rubrics.
 */

declare(strict_types=1);

require_once __DIR__ . '/../services/recruitment/AssessmentService.php';

class RecruitmentAssessmentController extends Controller {

    private AssessmentService $assessmentService;

    public function __construct() {
        $this->requireAuth();

        if (!Policy::canAccessRecruitment()) {
            http_response_code(403);
            die("Access Denied: You do not have permission to access Recruitment Assessments.");
        }

        $this->assessmentService = new AssessmentService();
    }

    /**
     * Assessment catalog & submissions overview.
     */
    public function index(): void {
        $db = Database::getInstance();

        $statusFilter = $_GET['status'] ?? '';
        $submissions = $this->assessmentService->listSubmissions($db, ['status' => $statusFilter]);
        $templates   = $this->assessmentService->listAssessments($db, false);

        // Fetch active applications for assignment dropdown
        $stmtApps = $db->query("
            SELECT a.application_id, a.application_code, c.first_name, c.last_name, j.title AS job_title
            FROM recruitment_applications a
            JOIN recruitment_candidates c ON a.candidate_id = c.candidate_id
            JOIN recruitment_jobs j ON a.job_id = j.job_id
            WHERE a.current_stage NOT IN ('rejected', 'withdrawn', 'hired')
            ORDER BY a.application_id DESC
        ");
        $activeApplications = $stmtApps->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $data = [
            'title'               => 'Assessments & Assignments | Raptor ATS',
            'active_tab'          => 'recruitment_assessments',
            'submissions'         => $submissions,
            'templates'           => $templates,
            'active_applications' => $activeApplications,
            'status_filter'       => $statusFilter,
            'csrf_token'          => $this->generateCsrfToken()
        ];

        $this->viewWithLayout('recruitment/assessments/index', 'main', $data);
    }

    /**
     * Create a new assessment template definition.
     */
    public function store(): void {
        $this->validateCsrf();

        $db = Database::getInstance();

        $payload = [
            'title'            => trim($_POST['title'] ?? ''),
            'assessment_type'  => trim($_POST['assessment_type'] ?? 'take_home_assignment'),
            'description'      => trim($_POST['description'] ?? ''),
            'instructions'     => trim($_POST['instructions'] ?? ''),
            'duration_minutes' => (int)($_POST['duration_minutes'] ?? 120),
            'max_score'        => (int)($_POST['max_score'] ?? 100),
            'passing_score'    => (int)($_POST['passing_score'] ?? 60)
        ];

        try {
            $this->assessmentService->createAssessment($db, $payload);
            $this->redirect('index.php?route=recruitment_assessment/index#templates');
        } catch (Throwable $e) {
            http_response_code(400);
            die("Error creating assessment: " . htmlspecialchars($e->getMessage()));
        }
    }

    /**
     * Assign assessment to candidate application.
     */
    public function assign(): void {
        $this->validateCsrf();

        $db = Database::getInstance();
        $userId = (int)$_SESSION['user_id'];

        $assessmentId  = (int)($_POST['assessment_id'] ?? 0);
        $applicationId = (int)($_POST['application_id'] ?? 0);
        $dueDate       = trim((string)($_POST['due_date'] ?? ''));

        if ($assessmentId <= 0 || $applicationId <= 0 || empty($dueDate)) {
            $this->redirect('index.php?route=recruitment_assessment/index');
            return;
        }

        try {
            $this->assessmentService->assignAssessment($db, $assessmentId, $applicationId, $dueDate, $userId);
            $this->redirect('index.php?route=recruitment_assessment/index');
        } catch (Throwable $e) {
            http_response_code(400);
            die("Error assigning assessment: " . htmlspecialchars($e->getMessage()));
        }
    }

    /**
     * Review & grade candidate submission view.
     */
    public function evaluate($id = null): void {
        $submissionId = (int)($id ?? $_GET['id'] ?? 0);
        if ($submissionId <= 0) {
            $this->redirect('index.php?route=recruitment_assessment/index');
            return;
        }

        $db = Database::getInstance();
        $submission = $this->assessmentService->getSubmissionById($db, $submissionId);

        if (!$submission) {
            http_response_code(404);
            die("Submission #{$submissionId} not found.");
        }

        $data = [
            'title'      => "Grade Assessment: {$submission['first_name']} {$submission['last_name']} | Raptor ATS",
            'active_tab' => 'recruitment_assessments',
            'sub'        => $submission,
            'csrf_token' => $this->generateCsrfToken()
        ];

        $this->viewWithLayout('recruitment/assessments/evaluate', 'main', $data);
    }

    /**
     * Submit grade & evaluation feedback for candidate submission.
     */
    public function grade(): void {
        $this->validateCsrf();

        $db = Database::getInstance();
        $userId = (int)$_SESSION['user_id'];

        $submissionId = (int)($_POST['submission_id'] ?? 0);
        $scoreAwarded = (int)($_POST['score_awarded'] ?? 0);
        $comments     = trim((string)($_POST['evaluator_comments'] ?? ''));

        if ($submissionId <= 0) {
            $this->redirect('index.php?route=recruitment_assessment/index');
            return;
        }

        try {
            $this->assessmentService->evaluateSubmission($db, $submissionId, $scoreAwarded, $comments, $userId);
            $this->redirect("index.php?route=recruitment_assessment/evaluate/{$submissionId}");
        } catch (Throwable $e) {
            http_response_code(400);
            die("Error grading submission: " . htmlspecialchars($e->getMessage()));
        }
    }
}

