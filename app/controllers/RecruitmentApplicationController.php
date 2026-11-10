<?php
/**
 * Raptor CRM — Recruitment Application & Kanban Controller (Sprint 4)
 * -------------------------------------------------------------------------
 * Serves the interactive ATS Kanban pipeline board, stage advancement API,
 * and tabular application views with CSRF verification and RBAC scoping.
 */

declare(strict_types=1);

require_once __DIR__ . '/../services/recruitment/ApplicationStageService.php';

class RecruitmentApplicationController extends Controller {

    private ApplicationStageService $stageService;

    public function __construct() {
        $this->requireAuth();

        if (!Policy::canAccessRecruitment()) {
            http_response_code(403);
            die("Access Denied: You do not have permission to access Recruitment Applications.");
        }

        $this->stageService = new ApplicationStageService();
    }

    /**
     * Primary ATS Kanban Pipeline Board.
     */
    public function index(): void {
        $db = Database::getInstance();
        $jobId = !empty($_GET['job_id']) ? (int)$_GET['job_id'] : null;
        $recruiterId = !empty($_GET['recruiter_id']) ? (int)$_GET['recruiter_id'] : null;

        $board = $this->stageService->getKanbanBoard($db, $jobId, $recruiterId);

        // Fetch active published jobs for filter dropdown
        $stmtJobs = $db->query("
            SELECT job_id, job_code, title, department 
            FROM recruitment_jobs 
            WHERE status IN ('published', 'draft', 'paused')
            ORDER BY job_id DESC
        ");
        $activeJobs = $stmtJobs->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $data = [
            'title'           => 'Recruitment Pipeline Kanban | Raptor ATS',
            'active_tab'      => 'recruitment_applications',
            'board'           => $board,
            'active_jobs'     => $activeJobs,
            'selected_job_id' => $jobId,
            'csrf_token'      => $this->generateCsrfToken()
        ];

        $this->viewWithLayout('recruitment/applications/index', 'main', $data);
    }

    /**
     * Drag-and-drop / AJAX stage transition endpoint.
     */
    public function stage($id = null): void {
        $applicationId = (int) ($id ?? $_POST['application_id'] ?? 0);

        // Accept JSON payload or form POST
        $input = json_decode(file_get_contents('php://input'), true);
        if (is_array($input)) {
            $toStage    = trim((string)($input['to_stage'] ?? ''));
            $reasonCode = trim((string)($input['reason_code'] ?? ''));
            $comments   = trim((string)($input['comments'] ?? ''));
            $token      = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($input['csrf_token'] ?? '');
        } else {
            $toStage    = trim((string)($_POST['to_stage'] ?? ''));
            $reasonCode = trim((string)($_POST['reason_code'] ?? ''));
            $comments   = trim((string)($_POST['comments'] ?? ''));
            $token      = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        }

        // Verify CSRF
        if (empty($token) || !hash_equals((string)($_SESSION['csrf_token'] ?? ''), (string)$token)) {
            header('Content-Type: application/json');
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'error'   => ['message' => 'CSRF token mismatch. Please reload the page.']
            ]);
            return;
        }

        if ($applicationId <= 0 || empty($toStage)) {
            header('Content-Type: application/json');
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error'   => ['message' => 'Application ID and destination stage are required.']
            ]);
            return;
        }

        $db = Database::getInstance();
        $userId = (int) $_SESSION['user_id'];

        try {
            $result = $this->stageService->transitionStage(
                $db,
                $applicationId,
                $toStage,
                $userId,
                $reasonCode ?: null,
                $comments ?: null
            );

            header('Content-Type: application/json');
            echo json_encode($result);
        } catch (Throwable $e) {
            header('Content-Type: application/json');
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error'   => ['message' => $e->getMessage()]
            ]);
        }
    }
}

