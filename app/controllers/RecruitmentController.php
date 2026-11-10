<?php
/**
 * Raptor CRM — Recruitment ATS Core Controller (Sprint 1)
 * -----------------------------------------------------------------------------------
 * Primary controller for the Enterprise Recruitment Management Module (ATS).
 */

declare(strict_types=1);

class RecruitmentController extends Controller {
    private Recruitment $recruitmentModel;

    public function __construct() {
        $this->requireAuth();

        if (!Policy::canAccessRecruitment()) {
            http_response_code(403);
            die("Access Denied: You do not have permission to access the Recruitment ATS module.");
        }

        $this->recruitmentModel = $this->model('Recruitment');
    }

    /**
     * Primary entry point — Recruitment Hub Dashboard.
     */
    public function index(): void {
        $this->dashboard();
    }

    /**
     * Central Recruitment Dashboard & Pipeline Funnel.
     */
    public function dashboard(): void {
        $correlationId = $this->initCorrelationId();
        $userId = (int) $_SESSION['user_id'];
        $role   = (string) ($_SESSION['user_role'] ?? 'employee');

        $stats = $this->recruitmentModel->getDashboardStats($role, $userId);

        $data = [
            'title'          => 'Recruitment Hub | Raptor ATS',
            'active_tab'     => 'recruitment_dashboard',
            'role'           => $role,
            'stats'          => $stats,
            'correlation_id' => $correlationId,
            'csrf_token'     => $this->generateCsrfToken()
        ];

        $this->viewWithLayout('recruitment/dashboard/index', 'main', $data);
    }

    /**
     * Job Requisitions list & management (Sprint 2).
     */
    public function requisitions(): void {
        require_once __DIR__ . '/RecruitmentRequisitionController.php';
        $controller = new RecruitmentRequisitionController();
        $controller->index();
    }

    /**
     * Job Postings & Career Listings (Sprint 3).
     */
    public function jobs(): void {
        require_once __DIR__ . '/RecruitmentJobController.php';
        $controller = new RecruitmentJobController();
        $controller->index();
    }

    /**
     * Candidates placeholder (delegates to Sprint 4).
     */
    public function candidates(): void {
        $this->redirect('index.php?route=recruitment/dashboard#candidates');
    }

    /**
     * Applications / Kanban placeholder (delegates to Sprint 4).
     */
    public function applications(): void {
        $this->redirect('index.php?route=recruitment/dashboard#applications');
    }

    /**
     * Interviews placeholder (delegates to Sprint 5).
     */
    public function interviews(): void {
        $this->redirect('index.php?route=recruitment/dashboard#interviews');
    }

    /**
     * Offers placeholder (delegates to Sprint 7).
     */
    public function offers(): void {
        $this->redirect('index.php?route=recruitment/dashboard#offers');
    }

    /**
     * Pre-joining & Onboarding placeholder (delegates to Sprint 8).
     */
    public function onboarding(): void {
        $this->redirect('index.php?route=recruitment/dashboard#onboarding');
    }

    /**
     * Reports placeholder (delegates to Sprint 10).
     */
    public function reports(): void {
        $this->redirect('index.php?route=recruitment/dashboard#reports');
    }
}

