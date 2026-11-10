<?php
/**
 * Raptor CRM — Requisition Management Controller (Sprint 2)
 * -----------------------------------------------------------------------------------
 * Manages job requisition creation, editing, multi-level approvals, and status review.
 */

declare(strict_types=1);

require_once __DIR__ . '/../services/recruitment/RequisitionService.php';

class RecruitmentRequisitionController extends Controller {
    private RequisitionService $requisitionService;
    private PDO $db;

    public function __construct() {
        $this->requireAuth();

        if (!Policy::canAccessRecruitment()) {
            http_response_code(403);
            die("Access Denied: You do not have permission to access Job Requisitions.");
        }

        $this->requisitionService = new RequisitionService();
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * List Requisitions with filters and pagination.
     */
    public function index(): void {
        $userId = (int) $_SESSION['user_id'];
        $role   = (string) ($_SESSION['user_role'] ?? 'employee');

        $filters = [
            'status'     => $_GET['status'] ?? 'all',
            'department' => $_GET['department'] ?? '',
            'search'     => $_GET['search'] ?? ''
        ];
        $page = max(1, (int)($_GET['page'] ?? 1));

        $result = $this->requisitionService->listRequisitions($this->db, $filters, $userId, $role, $page, 15);

        // Fetch distinct departments for filter dropdown
        $stmtDepts = $this->db->query("SELECT DISTINCT department FROM recruitment_requisitions WHERE department != '' ORDER BY department ASC");
        $departments = $stmtDepts->fetchAll(PDO::FETCH_COLUMN) ?: ['Engineering', 'Sales', 'Marketing', 'Human Resources', 'Finance'];

        $data = [
            'title'          => 'Job Requisitions | Raptor ATS',
            'active_tab'     => 'recruitment_requisitions',
            'role'           => $role,
            'requisitions'   => $result['data'],
            'total'          => $result['total'],
            'page'           => $result['page'],
            'pages'          => $result['pages'],
            'filters'        => $filters,
            'departments'    => $departments,
            'csrf_token'     => $this->generateCsrfToken()
        ];

        $this->viewWithLayout('recruitment/requisitions/index', 'main', $data);
    }

    /**
     * Requisition Creation Wizard.
     */
    public function create(): void {
        $userId = (int) $_SESSION['user_id'];
        $role   = (string) ($_SESSION['user_role'] ?? 'employee');

        // Fetch Job Description Templates for quick autofill
        $stmtTpl = $this->db->query("SELECT template_id, title, department FROM recruitment_job_descriptions WHERE is_active = 1 ORDER BY title ASC");
        $templates = $stmtTpl->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Fetch hiring managers (users with manager, hr, admin, or team_leader role)
        $stmtMgrs = $this->db->query("
            SELECT u.user_id, u.name, r.role_name 
            FROM users u 
            JOIN roles r ON u.role_id = r.role_id 
            WHERE r.role_name IN ('admin', 'ceo', 'hr', 'manager', 'team_leader')
            ORDER BY u.name ASC
        ");
        $managers = $stmtMgrs->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Fetch teams and branches
        $stmtTeams = $this->db->query("SELECT team_id, name FROM teams ORDER BY name ASC");
        $teams = $stmtTeams->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $stmtBranches = $this->db->query("SELECT branch_id, name FROM branches ORDER BY name ASC");
        $branches = $stmtBranches->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $data = [
            'title'       => 'Create Job Requisition | Raptor ATS',
            'active_tab'  => 'recruitment_requisitions',
            'role'        => $role,
            'user_id'     => $userId,
            'templates'   => $templates,
            'managers'    => $managers,
            'teams'       => $teams,
            'branches'    => $branches,
            'csrf_token'  => $this->generateCsrfToken()
        ];

        $this->viewWithLayout('recruitment/requisitions/create', 'main', $data);
    }

    /**
     * Store new Requisition.
     */
    public function store(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('index.php?route=recruitment/requisitions');
        }

        $token = $_POST['csrf_token'] ?? '';
        if (!$this->validateCsrfToken($token)) {
            http_response_code(403);
            die("Security error: Invalid CSRF token.");
        }

        $userId = (int) $_SESSION['user_id'];

        try {
            $result = $this->requisitionService->createRequisition($this->db, $_POST, $userId);
            $this->redirect('index.php?route=recruitment_requisition/show/' . $result['requisition_id']);
        } catch (\Throwable $e) {
            http_response_code(400);
            die("Error creating requisition: " . htmlspecialchars($e->getMessage()));
        }
    }

    /**
     * View single Requisition & Approval Timeline.
     */
    public function show($id = null): void {
        $requisitionId = (int) $id;
        if ($requisitionId <= 0) {
            $this->redirect('index.php?route=recruitment/requisitions');
        }

        $userId = (int) $_SESSION['user_id'];
        $role   = (string) ($_SESSION['user_role'] ?? 'employee');

        $requisition = $this->requisitionService->getRequisition($this->db, $requisitionId, $userId, $role);
        if (!$requisition) {
            http_response_code(404);
            die("Requisition not found or you do not have permission to view it.");
        }

        $data = [
            'title'       => $requisition['title'] . ' (' . $requisition['requisition_code'] . ') | Raptor ATS',
            'active_tab'  => 'recruitment_requisitions',
            'role'        => $role,
            'user_id'     => $userId,
            'req'         => $requisition,
            'csrf_token'  => $this->generateCsrfToken()
        ];

        $this->viewWithLayout('recruitment/requisitions/view', 'main', $data);
    }

    /**
     * Alias for show()
     */
    public function detail($id = null): void {
        $this->show($id);
    }

    /**
     * Action an approval decision (Approve / Reject / Changes Requested).
     */
    public function approve($id = null): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('index.php?route=recruitment/requisitions');
        }

        $token = $_POST['csrf_token'] ?? '';
        if (!$this->validateCsrfToken($token)) {
            http_response_code(403);
            die("Security error: Invalid CSRF token.");
        }

        $requisitionId = (int) ($id ?? $_POST['requisition_id'] ?? 0);
        $decision      = (string) ($_POST['decision'] ?? 'approved');
        $comments      = trim((string)($_POST['comments'] ?? ''));
        $userId        = (int) $_SESSION['user_id'];
        $role          = (string) ($_SESSION['user_role'] ?? 'employee');
        $ipAddress     = $_SERVER['REMOTE_ADDR'] ?? null;

        $approvalService = new RequisitionApprovalService();
        try {
            $approvalService->actionApproval(
                $this->db,
                $requisitionId,
                $userId,
                $role,
                $decision,
                $comments,
                $ipAddress
            );
            $this->redirect('index.php?route=recruitment_requisition/show/' . $requisitionId);
        } catch (\Throwable $e) {
            http_response_code(400);
            die("Error recording approval decision: " . htmlspecialchars($e->getMessage()));
        }
    }

    /**
     * Submit draft requisition for approval.
     */
    public function submit($id = null): void {
        $requisitionId = (int) $id;
        $userId = (int) $_SESSION['user_id'];

        try {
            $this->requisitionService->submitForApproval($this->db, $requisitionId, $userId);
            $this->redirect('index.php?route=recruitment_requisition/show/' . $requisitionId);
        } catch (\Throwable $e) {
            http_response_code(400);
            die("Error submitting requisition: " . htmlspecialchars($e->getMessage()));
        }
    }
}
