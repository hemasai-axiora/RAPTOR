<?php
/**
 * Raptor CRM — Recruitment Interview & Scorecard Controller (Sprint 5)
 * -------------------------------------------------------------------------
 * Manages interview calendar, multi-round scheduling, conflict detection,
 * and blind interview scorecard evaluations.
 */

declare(strict_types=1);

require_once __DIR__ . '/../services/recruitment/InterviewService.php';
require_once __DIR__ . '/../services/recruitment/InterviewEvaluationService.php';
require_once __DIR__ . '/../services/recruitment/ScreeningService.php';

class RecruitmentInterviewController extends Controller {

    private InterviewService $interviewService;
    private InterviewEvaluationService $evalService;
    private ScreeningService $screeningService;

    public function __construct() {
        $this->requireAuth();

        if (!Policy::canAccessRecruitment()) {
            http_response_code(403);
            die("Access Denied: You do not have permission to access Recruitment Interviews.");
        }

        $this->interviewService = new InterviewService();
        $this->evalService      = new InterviewEvaluationService();
        $this->screeningService = new ScreeningService();
    }

    /**
     * Interview calendar and schedule view.
     */
    public function index(): void {
        $db = Database::getInstance();

        // Calculate current week date range (Monday to Sunday)
        $startOfWeek = date('Y-m-d', strtotime('monday this week'));
        $endOfWeek   = date('Y-m-d', strtotime('sunday this week'));

        $startDate   = $_GET['start_date'] ?? $startOfWeek;
        $endDate     = $_GET['end_date'] ?? $endOfWeek;
        $interviewer = !empty($_GET['interviewer_id']) ? (int)$_GET['interviewer_id'] : null;
        $department  = !empty($_GET['department']) ? trim((string)$_GET['department']) : null;

        $events = $this->interviewService->getCalendar($db, $startDate, $endDate, $interviewer, $department);

        // Fetch interviewers list (users with HR, manager, team_leader roles)
        $stmtUsers = $db->query("
            SELECT u.user_id, u.name, u.email, r.role_name
            FROM users u
            JOIN roles r ON u.role_id = r.role_id
            WHERE r.role_name IN ('admin', 'ceo', 'hr', 'manager', 'team_leader')
            ORDER BY u.name ASC
        ");
        $interviewers = $stmtUsers->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Fetch active applications eligible for interview
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
            'title'               => 'Interview Calendar & Coordination | Raptor ATS',
            'active_tab'          => 'recruitment_interviews',
            'events'              => $events,
            'interviewers'        => $interviewers,
            'active_applications' => $activeApplications,
            'start_date'          => $startDate,
            'end_date'            => $endDate,
            'selected_user_id'    => $interviewer,
            'selected_dept'       => $department,
            'csrf_token'          => $this->generateCsrfToken()
        ];

        $this->viewWithLayout('recruitment/interviews/index', 'main', $data);
    }

    /**
     * Schedule an interview round with conflict check.
     */
    public function store(): void {
        $this->validateCsrf();

        $db = Database::getInstance();
        $userId = (int)$_SESSION['user_id'];

        $applicationId  = (int)($_POST['application_id'] ?? 0);
        $roundNumber    = (int)($_POST['round_number'] ?? 1);
        $roundName      = trim((string)($_POST['round_name'] ?? 'Technical Round'));
        $interviewType  = trim((string)($_POST['interview_type'] ?? 'technical'));
        $interviewMode  = trim((string)($_POST['interview_mode'] ?? 'video'));
        $scheduledStart = trim((string)($_POST['scheduled_start'] ?? ''));
        $scheduledEnd   = trim((string)($_POST['scheduled_end'] ?? ''));
        $meetingLink    = trim((string)($_POST['meeting_link'] ?? ''));
        $panelUserIds   = array_map('intval', (array)($_POST['panel_user_ids'] ?? []));

        try {
            $this->interviewService->scheduleInterview(
                $db,
                [
                    'application_id'  => $applicationId,
                    'round_number'    => $roundNumber,
                    'round_name'      => $roundName,
                    'interview_type'  => $interviewType,
                    'interview_mode'  => $interviewMode,
                    'scheduled_start' => $scheduledStart,
                    'scheduled_end'   => $scheduledEnd,
                    'meeting_link'    => $meetingLink
                ],
                $panelUserIds,
                $userId
            );

            $this->redirect('index.php?route=recruitment_interview/index');
        } catch (Throwable $e) {
            http_response_code(400);
            die("Error scheduling interview: " . htmlspecialchars($e->getMessage()));
        }
    }

    /**
     * Blind interview scorecard submission view.
     */
    public function evaluate($id = null): void {
        $interviewId = (int)($id ?? $_GET['id'] ?? 0);
        if ($interviewId <= 0) {
            $this->redirect('index.php?route=recruitment_interview/index');
            return;
        }

        $db = Database::getInstance();
        $userId = (int)$_SESSION['user_id'];
        $role   = (string)($_SESSION['user_role'] ?? 'employee');

        $interview = $this->interviewService->getInterviewById($db, $interviewId);
        if (!$interview) {
            http_response_code(404);
            die("Interview #{$interviewId} not found.");
        }

        $feedbackData = $this->evalService->getInterviewFeedback($db, $interviewId, $userId, $role);

        $data = [
            'title'         => "Interview Evaluation: {$interview['round_name']} | Raptor ATS",
            'active_tab'    => 'recruitment_interviews',
            'interview'     => $interview,
            'feedback_data' => $feedbackData,
            'csrf_token'    => $this->generateCsrfToken()
        ];

        $this->viewWithLayout('recruitment/interviews/evaluate', 'main', $data);
    }

    /**
     * Submit interview evaluation scorecard.
     */
    public function submitEvaluation(): void {
        $this->validateCsrf();

        $db = Database::getInstance();
        $userId = (int)$_SESSION['user_id'];

        $interviewId = (int)($_POST['interview_id'] ?? 0);
        if ($interviewId <= 0) {
            $this->redirect('index.php?route=recruitment_interview/index');
            return;
        }

        $payload = [
            'technical_rating'       => (int)($_POST['technical_rating'] ?? 3),
            'problem_solving_rating' => (int)($_POST['problem_solving_rating'] ?? 3),
            'communication_rating'   => (int)($_POST['communication_rating'] ?? 3),
            'cultural_fit_rating'    => (int)($_POST['cultural_fit_rating'] ?? 3),
            'overall_rating'         => (int)($_POST['overall_rating'] ?? 3),
            'recommendation'         => trim((string)($_POST['recommendation'] ?? 'recommend')),
            'strengths'              => trim((string)($_POST['strengths'] ?? '')),
            'concerns'               => trim((string)($_POST['concerns'] ?? '')),
            'detailed_comments'      => trim((string)($_POST['detailed_comments'] ?? ''))
        ];

        try {
            $this->evalService->submitFeedback($db, $interviewId, $userId, $payload);
            $this->redirect("index.php?route=recruitment_interview/evaluate/{$interviewId}");
        } catch (Throwable $e) {
            http_response_code(400);
            die("Error submitting evaluation: " . htmlspecialchars($e->getMessage()));
        }
    }

    /**
     * Initial recruiter screening submission.
     */
    public function screen($id = null): void {
        $this->validateCsrf();

        $db = Database::getInstance();
        $userId = (int)$_SESSION['user_id'];
        $applicationId = (int)($id ?? $_POST['application_id'] ?? 0);

        if ($applicationId <= 0) {
            $this->redirect('index.php?route=recruitment_application/index');
            return;
        }

        $payload = [
            'skills_rating'        => (int)($_POST['skills_rating'] ?? 3),
            'experience_rating'    => (int)($_POST['experience_rating'] ?? 3),
            'communication_rating' => (int)($_POST['communication_rating'] ?? 3),
            'recommendation'       => trim((string)($_POST['recommendation'] ?? 'hold')),
            'evaluation_comments'  => trim((string)($_POST['evaluation_comments'] ?? ''))
        ];

        try {
            $this->screeningService->submitScreening($db, $applicationId, $userId, $payload);
            $this->redirect('index.php?route=recruitment_application/index');
        } catch (Throwable $e) {
            http_response_code(400);
            die("Error submitting screening: " . htmlspecialchars($e->getMessage()));
        }
    }
}

