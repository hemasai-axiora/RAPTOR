<?php
/**
 * Raptor CRM — Recruitment Candidate Controller (Sprint 4)
 * -------------------------------------------------------------------------
 * Manages the Candidate master directory, Candidate 360 profile view,
 * manual profile intake, secure document downloads, and recruiter notes.
 */

declare(strict_types=1);

require_once __DIR__ . '/../services/recruitment/CandidateService.php';
require_once __DIR__ . '/../services/recruitment/CandidateDeduplicationService.php';
require_once __DIR__ . '/../core/Storage.php';

class RecruitmentCandidateController extends Controller {

    private CandidateService $candidateService;
    private CandidateDeduplicationService $dedupService;

    public function __construct() {
        $this->requireAuth();

        if (!Policy::canAccessRecruitment()) {
            http_response_code(403);
            die("Access Denied: You do not have permission to access Recruitment Candidates.");
        }

        $this->dedupService = new CandidateDeduplicationService();
        $this->candidateService = new CandidateService($this->dedupService);
    }

    /**
     * Candidate master directory.
     */
    public function index(): void {
        $db = Database::getInstance();
        $filters = [
            'search'         => $_GET['search'] ?? '',
            'source'         => $_GET['source'] ?? '',
            'is_blacklisted' => $_GET['is_blacklisted'] ?? '',
            'min_exp'        => $_GET['min_exp'] ?? ''
        ];

        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = 25;
        $offset = ($page - 1) * $limit;

        $results = $this->candidateService->listCandidates($db, $filters, $limit, $offset);

        $data = [
            'title'       => 'Candidate Directory | Raptor ATS',
            'active_tab'  => 'recruitment_candidates',
            'candidates'  => $results['records'],
            'total'       => $results['total'],
            'page'        => $page,
            'limit'       => $limit,
            'filters'     => $filters,
            'csrf_token'  => $this->generateCsrfToken()
        ];

        $this->viewWithLayout('recruitment/candidates/index', 'main', $data);
    }

    /**
     * Candidate 360 profile.
     */
    public function show($id = null): void {
        $candidateId = (int) ($id ?? $_GET['id'] ?? 0);
        if ($candidateId <= 0) {
            $this->redirect('index.php?route=recruitment/candidates');
            return;
        }

        $db = Database::getInstance();
        $userId = (int) $_SESSION['user_id'];
        $role   = (string) ($_SESSION['user_role'] ?? 'employee');

        $candidate = $this->candidateService->getCandidateById($db, $candidateId, $userId, $role);

        if (!$candidate) {
            http_response_code(404);
            die("Candidate not found.");
        }

        $data = [
            'title'      => "Candidate: {$candidate['first_name']} {$candidate['last_name']} | Raptor ATS",
            'active_tab' => 'recruitment_candidates',
            'candidate'  => $candidate,
            'role'       => $role,
            'csrf_token' => $this->generateCsrfToken()
        ];

        $this->viewWithLayout('recruitment/candidates/profile', 'main', $data);
    }

    /**
     * Profile alias for show().
     */
    public function profile($id = null): void {
        $this->show($id);
    }

    /**
     * Add manual candidate intake form.
     */
    public function create(): void {
        $data = [
            'title'      => 'Add Candidate | Raptor ATS',
            'active_tab' => 'recruitment_candidates',
            'csrf_token' => $this->generateCsrfToken()
        ];
        $this->viewWithLayout('recruitment/candidates/create', 'main', $data);
    }

    /**
     * Store manual candidate intake.
     */
    public function store(): void {
        $this->validateCsrf();

        $db = Database::getInstance();
        $userId = (int) $_SESSION['user_id'];

        $payload = [
            'first_name'             => trim($_POST['first_name'] ?? ''),
            'last_name'              => trim($_POST['last_name'] ?? ''),
            'email'                  => trim($_POST['email'] ?? ''),
            'phone'                  => trim($_POST['phone'] ?? ''),
            'current_company'        => trim($_POST['current_company'] ?? ''),
            'current_designation'    => trim($_POST['current_designation'] ?? ''),
            'total_experience_years' => (float)($_POST['total_experience_years'] ?? 0.0),
            'primary_skills'         => trim($_POST['primary_skills'] ?? ''),
            'secondary_skills'       => trim($_POST['secondary_skills'] ?? ''),
            'highest_education'      => trim($_POST['highest_education'] ?? ''),
            'city'                   => trim($_POST['city'] ?? ''),
            'country'                => trim($_POST['country'] ?? 'India'),
            'notice_period_days'     => !empty($_POST['notice_period_days']) ? (int)$_POST['notice_period_days'] : null,
            'current_ctc'            => !empty($_POST['current_ctc']) ? (float)$_POST['current_ctc'] : null,
            'expected_ctc'           => !empty($_POST['expected_ctc']) ? (float)$_POST['expected_ctc'] : null,
            'linkedin_url'           => trim($_POST['linkedin_url'] ?? ''),
            'portfolio_url'          => trim($_POST['portfolio_url'] ?? ''),
            'source'                 => trim($_POST['source'] ?? 'direct'),
            'source_detail'          => trim($_POST['source_detail'] ?? '')
        ];

        try {
            $result = $this->candidateService->createOrUpdateCandidate($db, $payload, $userId);

            // Handle optional resume upload
            if (!empty($_FILES['resume']['name']) && $_FILES['resume']['error'] === UPLOAD_ERR_OK) {
                $storageKey = Storage::put($_FILES['resume'], 'recruitment_resumes');
                $this->candidateService->attachDocument(
                    $db,
                    (int)$result['candidate_id'],
                    'resume',
                    $storageKey,
                    $_FILES['resume']['name'],
                    (int)$_FILES['resume']['size'],
                    $_FILES['resume']['type'],
                    true,
                    $userId
                );
            }

            $this->redirect('index.php?route=recruitment_candidate/show/' . $result['candidate_id']);
        } catch (Throwable $e) {
            http_response_code(400);
            die("Error creating candidate: " . htmlspecialchars($e->getMessage()));
        }
    }

    /**
     * Add internal recruiter note.
     */
    public function addNote($id = null): void {
        $this->validateCsrf();
        $candidateId = (int) ($id ?? $_POST['candidate_id'] ?? 0);
        $noteText = trim($_POST['note_text'] ?? '');
        $isConfidential = !empty($_POST['is_confidential']);
        $applicationId = !empty($_POST['application_id']) ? (int)$_POST['application_id'] : null;

        if ($candidateId <= 0 || empty($noteText)) {
            $this->redirect('index.php?route=recruitment/candidates');
            return;
        }

        $db = Database::getInstance();
        $userId = (int) $_SESSION['user_id'];

        $this->candidateService->addNote($db, $candidateId, $noteText, $userId, $applicationId, $isConfidential);
        $this->redirect('index.php?route=recruitment_candidate/show/' . $candidateId . '#notes');
    }

    /**
     * Blacklist candidate profile.
     */
    public function blacklist($id = null): void {
        $this->validateCsrf();
        $candidateId = (int) ($id ?? $_POST['candidate_id'] ?? 0);
        $reason = trim($_POST['reason'] ?? 'Flagged by recruiter');

        if ($candidateId > 0) {
            $db = Database::getInstance();
            $userId = (int) $_SESSION['user_id'];
            $this->candidateService->blacklistCandidate($db, $candidateId, $reason, $userId);
        }

        $this->redirect('index.php?route=recruitment_candidate/show/' . $candidateId);
    }

    /**
     * Secure candidate document download endpoint.
     * Enforces active authenticated session and returns 403 Forbidden if unauthenticated.
     */
    public function downloadDocument($id = null): void {
        // Enforce active session
        if (empty($_SESSION['user_id'])) {
            http_response_code(403);
            die("403 Forbidden: Active session required to access candidate documents.");
        }

        $documentId = (int) ($id ?? $_GET['id'] ?? 0);
        if ($documentId <= 0) {
            http_response_code(404);
            die("Document not found.");
        }

        $db = Database::getInstance();
        $doc = $this->candidateService->getDocumentById($db, $documentId);

        if (!$doc) {
            http_response_code(404);
            die("Document not found.");
        }

        $filePath = Storage::path($doc['storage_key']);
        if (!$filePath || !file_exists($filePath)) {
            http_response_code(404);
            die("Document file missing from storage.");
        }

        header('Content-Description: File Transfer');
        header('Content-Type: ' . ($doc['mime_type'] ?: 'application/octet-stream'));
        header('Content-Disposition: inline; filename="' . basename($doc['file_name']) . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($filePath));
        readfile($filePath);
        exit;
    }
}

