<?php
/**
 * Raptor CRM — Recruitment Onboarding & Conversion Controller (Sprint 8)
 * -------------------------------------------------------------------------
 * Manages background verification, pre-joining document checklists, and
 * idempotent conversion of hired candidates into permanent employees.
 */

declare(strict_types=1);

require_once __DIR__ . '/../services/recruitment/BackgroundVerificationService.php';
require_once __DIR__ . '/../services/recruitment/EmployeeConversionService.php';

class RecruitmentOnboardingController extends Controller {

    private BackgroundVerificationService $bgvService;
    private EmployeeConversionService $conversionService;

    public function __construct() {
        $this->requireAuth();

        if (!Policy::canAccessRecruitment()) {
            http_response_code(403);
            die("Access Denied: You do not have permission to access Recruitment Onboarding.");
        }

        $this->bgvService        = new BackgroundVerificationService();
        $this->conversionService = new EmployeeConversionService();
    }

    /**
     * Onboarding, BGV, and pre-joining dashboard.
     */
    public function index(): void {
        $db = Database::getInstance();

        // Fetch applications currently in bgv, pre_joining, offer_accepted, or hired
        $stmtApps = $db->query("
            SELECT a.application_id, a.application_code, a.current_stage, a.converted_employee_id,
                   c.candidate_id, c.first_name, c.last_name, c.email, c.phone, c.candidate_code,
                   j.title AS job_title, j.department,
                   o.offer_code, o.proposed_joining_date, o.annual_ctc, o.status AS offer_status
            FROM recruitment_applications a
            JOIN recruitment_candidates c ON a.candidate_id = c.candidate_id
            JOIN recruitment_jobs j ON a.job_id = j.job_id
            LEFT JOIN recruitment_offers o ON a.application_id = o.application_id AND o.status IN ('accepted', 'released', 'approved')
            WHERE a.current_stage IN ('offer_accepted', 'bgv', 'pre_joining', 'hired')
            ORDER BY a.application_id DESC
        ");
        $candidates = $stmtApps->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $data = [
            'title'       => 'Onboarding & Employee Conversion | Raptor ATS',
            'active_tab'  => 'recruitment_onboarding',
            'candidates'  => $candidates,
            'csrf_token'  => $this->generateCsrfToken()
        ];

        $this->viewWithLayout('recruitment/onboarding/index', 'main', $data);
    }

    /**
     * View 1-click employee conversion confirmation gate.
     */
    public function convert($id = null): void {
        $applicationId = (int)($id ?? $_GET['id'] ?? 0);
        if ($applicationId <= 0) {
            $this->redirect('index.php?route=recruitment_onboarding/index');
            return;
        }

        $db = Database::getInstance();
        $check = $this->conversionService->canConvertApplication($db, $applicationId);

        if (!$check['can_convert']) {
            http_response_code(400);
            die("Cannot convert: " . htmlspecialchars($check['reason']));
        }

        $appData = $check['application'];

        // Fetch candidate and offer details
        $stmtCand = $db->prepare("SELECT * FROM recruitment_candidates WHERE candidate_id = :id");
        $stmtCand->execute([':id' => $appData['candidate_id']]);
        $cand = $stmtCand->fetch(PDO::FETCH_ASSOC);

        $stmtOff = $db->prepare("SELECT * FROM recruitment_offers WHERE offer_id = :id");
        $stmtOff->execute([':id' => $appData['offer_id']]);
        $offer = $stmtOff->fetch(PDO::FETCH_ASSOC);

        $suggestedCode = $this->conversionService->generateEmployeeCode($db);

        $data = [
            'title'          => "Convert Candidate: {$cand['first_name']} {$cand['last_name']} | Raptor ATS",
            'active_tab'     => 'recruitment_onboarding',
            'application'    => $appData,
            'candidate'      => $cand,
            'offer'          => $offer,
            'suggested_code' => $suggestedCode,
            'csrf_token'     => $this->generateCsrfToken()
        ];

        $this->viewWithLayout('recruitment/onboarding/convert', 'main', $data);
    }

    /**
     * Execute 1-click conversion into Raptor HRMS employees.
     */
    public function executeConversion(): void {
        $this->validateCsrf();

        $db = Database::getInstance();
        $userId = (int)$_SESSION['user_id'];

        $applicationId = (int)($_POST['application_id'] ?? 0);
        if ($applicationId <= 0) {
            $this->redirect('index.php?route=recruitment_onboarding/index');
            return;
        }

        $overrides = [
            'employee_code'   => trim((string)($_POST['employee_code'] ?? '')),
            'job_title'       => trim((string)($_POST['job_title'] ?? '')),
            'department'      => trim((string)($_POST['department'] ?? '')),
            'work_location'   => trim((string)($_POST['work_location'] ?? '')),
            'date_of_joining' => trim((string)($_POST['date_of_joining'] ?? ''))
        ];

        try {
            $result = $this->conversionService->convertCandidateToEmployee($db, $applicationId, $overrides, $userId);
            $this->redirect('index.php?route=recruitment_onboarding/index');
        } catch (Throwable $e) {
            http_response_code(400);
            die("Conversion failed: " . htmlspecialchars($e->getMessage()));
        }
    }

    /**
     * Check off pre-joining document checklist item.
     */
    public function verifyChecklist(): void {
        $this->validateCsrf();

        $db = Database::getInstance();
        $userId = (int)$_SESSION['user_id'];

        $itemId        = (int)($_POST['item_id'] ?? 0);
        $applicationId = (int)($_POST['application_id'] ?? 0);

        if ($itemId > 0) {
            $this->bgvService->verifyChecklistItem($db, $itemId, $userId);
        }

        $this->redirect("index.php?route=recruitment_onboarding/index");
    }
}

