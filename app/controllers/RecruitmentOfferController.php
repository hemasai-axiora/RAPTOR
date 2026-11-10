<?php
/**
 * Raptor CRM — Recruitment Offer Controller (Sprint 7)
 * -------------------------------------------------------------------------
 * Manages offer compensation packages, multi-step approvals, salary
 * protection gates, offer letter generation, and candidate responses.
 */

declare(strict_types=1);

require_once __DIR__ . '/../services/recruitment/OfferService.php';
require_once __DIR__ . '/../services/recruitment/OfferApprovalService.php';

class RecruitmentOfferController extends Controller {

    private OfferService $offerService;
    private OfferApprovalService $approvalService;

    public function __construct() {
        $this->requireAuth();

        if (!Policy::canAccessRecruitment()) {
            http_response_code(403);
            die("Access Denied: You do not have permission to access Recruitment Offers.");
        }

        $this->approvalService = new OfferApprovalService();
        $this->offerService    = new OfferService($this->approvalService);
    }

    /**
     * Offer list and status directory.
     */
    public function index(): void {
        $db = Database::getInstance();
        $role = (string)($_SESSION['user_role'] ?? 'employee');

        $statusFilter = $_GET['status'] ?? '';
        $deptFilter   = $_GET['department'] ?? '';

        $offers = $this->offerService->listOffers($db, [
            'status'     => $statusFilter,
            'department' => $deptFilter
        ], $role);

        $data = [
            'title'         => 'Offer Management | Raptor ATS',
            'active_tab'    => 'recruitment_offers',
            'offers'        => $offers,
            'role'          => $role,
            'status_filter' => $statusFilter,
            'dept_filter'   => $deptFilter,
            'csrf_token'    => $this->generateCsrfToken()
        ];

        $this->viewWithLayout('recruitment/offers/index', 'main', $data);
    }

    /**
     * Offer builder wizard.
     */
    public function create(): void {
        $db = Database::getInstance();

        // Fetch applications eligible for offer (shortlisted, interview, or offer_prep)
        $stmtApps = $db->query("
            SELECT a.application_id, a.application_code, c.first_name, c.last_name, 
                   j.title AS job_title, j.department, j.location, r.max_salary, r.budget_allocated
            FROM recruitment_applications a
            JOIN recruitment_candidates c ON a.candidate_id = c.candidate_id
            JOIN recruitment_jobs j ON a.job_id = j.job_id
            JOIN recruitment_requisitions r ON j.requisition_id = r.requisition_id
            WHERE a.current_stage NOT IN ('rejected', 'withdrawn', 'hired')
            ORDER BY a.application_id DESC
        ");
        $eligibleApps = $stmtApps->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Fetch managers list
        $stmtMgrs = $db->query("
            SELECT u.user_id, u.name, r.role_name
            FROM users u
            JOIN roles r ON u.role_id = r.role_id
            WHERE r.role_name IN ('admin', 'ceo', 'manager')
            ORDER BY u.name ASC
        ");
        $managers = $stmtMgrs->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $data = [
            'title'         => 'Create Job Offer | Raptor ATS',
            'active_tab'    => 'recruitment_offers',
            'eligible_apps' => $eligibleApps,
            'managers'      => $managers,
            'csrf_token'    => $this->generateCsrfToken()
        ];

        $this->viewWithLayout('recruitment/offers/create', 'main', $data);
    }

    /**
     * Store new offer proposal.
     */
    public function store(): void {
        $this->validateCsrf();

        $db = Database::getInstance();
        $userId = (int)$_SESSION['user_id'];

        $payload = [
            'application_id'          => (int)($_POST['application_id'] ?? 0),
            'job_title'               => trim((string)($_POST['job_title'] ?? '')),
            'department'              => trim((string)($_POST['department'] ?? '')),
            'reporting_manager_id'    => !empty($_POST['reporting_manager_id']) ? (int)$_POST['reporting_manager_id'] : null,
            'work_location'           => trim((string)($_POST['work_location'] ?? '')),
            'employment_type'         => trim((string)($_POST['employment_type'] ?? 'full_time')),
            'annual_ctc'              => (float)($_POST['annual_ctc'] ?? 0.0),
            'fixed_salary'            => (float)($_POST['fixed_salary'] ?? 0.0),
            'variable_pay'            => (float)($_POST['variable_pay'] ?? 0.0),
            'joining_bonus'           => (float)($_POST['joining_bonus'] ?? 0.0),
            'proposed_joining_date'   => trim((string)($_POST['proposed_joining_date'] ?? '')),
            'offer_expiry_date'       => trim((string)($_POST['offer_expiry_date'] ?? '')),
            'probation_period_months' => (int)($_POST['probation_period_months'] ?? 3),
            'notice_period_days'      => (int)($_POST['notice_period_days'] ?? 30)
        ];

        try {
            $result = $this->offerService->createOffer($db, $payload, $userId);
            $this->redirect('index.php?route=recruitment_offer/show/' . $result['offer_id']);
        } catch (Throwable $e) {
            http_response_code(400);
            die("Error creating offer: " . htmlspecialchars($e->getMessage()));
        }
    }

    /**
     * View offer details, compensation, approval timeline.
     */
    public function show($id = null): void {
        $offerId = (int)($id ?? $_GET['id'] ?? 0);
        if ($offerId <= 0) {
            $this->redirect('index.php?route=recruitment_offer/index');
            return;
        }

        $db = Database::getInstance();
        $role = (string)($_SESSION['user_role'] ?? 'employee');

        $offer = $this->offerService->getOfferById($db, $offerId, $role);
        if (!$offer) {
            http_response_code(404);
            die("Offer #{$offerId} not found.");
        }

        $letterHtml = $this->offerService->generateOfferLetterHtml($offer, [
            'first_name' => $offer['first_name'],
            'last_name'  => $offer['last_name']
        ]);

        $data = [
            'title'       => "Offer: {$offer['offer_code']} | Raptor ATS",
            'active_tab'  => 'recruitment_offers',
            'offer'       => $offer,
            'role'        => $role,
            'letter_html' => $letterHtml,
            'csrf_token'  => $this->generateCsrfToken()
        ];

        $this->viewWithLayout('recruitment/offers/show', 'main', $data);
    }

    /**
     * Action an offer approval step.
     */
    public function approve($id = null): void {
        $this->validateCsrf();

        $offerId  = (int)($id ?? $_POST['offer_id'] ?? 0);
        $decision = trim((string)($_POST['decision'] ?? 'approved'));
        $comments = trim((string)($_POST['comments'] ?? ''));

        if ($offerId <= 0) {
            $this->redirect('index.php?route=recruitment_offer/index');
            return;
        }

        $db = Database::getInstance();
        $userId = (int)$_SESSION['user_id'];
        $role   = (string)($_SESSION['user_role'] ?? 'employee');

        try {
            $this->approvalService->actionApproval($db, $offerId, $userId, $role, $decision, $comments ?: null);
            $this->redirect("index.php?route=recruitment_offer/show/{$offerId}");
        } catch (Throwable $e) {
            http_response_code(400);
            die("Error actioning offer approval: " . htmlspecialchars($e->getMessage()));
        }
    }

    /**
     * Release approved offer to candidate.
     */
    public function release($id = null): void {
        $this->validateCsrf();

        $offerId = (int)($id ?? $_POST['offer_id'] ?? 0);
        if ($offerId <= 0) {
            $this->redirect('index.php?route=recruitment_offer/index');
            return;
        }

        $db = Database::getInstance();
        $userId = (int)$_SESSION['user_id'];

        try {
            $this->offerService->releaseOffer($db, $offerId, $userId);
            $this->redirect("index.php?route=recruitment_offer/show/{$offerId}");
        } catch (Throwable $e) {
            http_response_code(400);
            die("Error releasing offer: " . htmlspecialchars($e->getMessage()));
        }
    }

    /**
     * Record candidate acceptance or decline.
     */
    public function respond($id = null): void {
        $this->validateCsrf();

        $offerId  = (int)($id ?? $_POST['offer_id'] ?? 0);
        $decision = trim((string)($_POST['decision'] ?? 'accepted'));
        $reason   = trim((string)($_POST['reason'] ?? ''));

        if ($offerId <= 0) {
            $this->redirect('index.php?route=recruitment_offer/index');
            return;
        }

        $db = Database::getInstance();

        try {
            $this->offerService->candidateResponse($db, $offerId, $decision, $reason ?: null);
            $this->redirect("index.php?route=recruitment_offer/show/{$offerId}");
        } catch (Throwable $e) {
            http_response_code(400);
            die("Error recording response: " . htmlspecialchars($e->getMessage()));
        }
    }
}

