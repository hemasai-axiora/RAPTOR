<?php
/**
 * Raptor CRM — Public Career Portal Controller (Sprint 3)
 * -----------------------------------------------------------------------------------
 * Public unauthenticated career portal, SEO structured data, and candidate application intake.
 */

declare(strict_types=1);

require_once __DIR__ . '/../services/recruitment/JobPublishingService.php';

class CareersController extends Controller {
    private JobPublishingService $publishingService;
    private PDO $db;

    public function __construct() {
        // Publicly accessible controller (No $this->requireAuth() required)
        $this->publishingService = new JobPublishingService();
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Public Career Portal Landing Page.
     */
    public function index(): void {
        $filters = [
            'department' => $_GET['department'] ?? '',
            'location'   => $_GET['location'] ?? '',
            'type'       => $_GET['type'] ?? '',
            'search'     => $_GET['search'] ?? ''
        ];

        $jobs = $this->publishingService->getPublicJobs($this->db, $filters);

        // Fetch distinct departments for filter buttons
        $stmtDepts = $this->db->query("
            SELECT DISTINCT department FROM recruitment_jobs 
            WHERE status = 'published' AND department != '' 
            ORDER BY department ASC
        ");
        $departments = $stmtDepts->fetchAll(PDO::FETCH_COLUMN) ?: [];

        $data = [
            'title'       => 'Careers & Open Opportunities | Raptor Enterprise',
            'jobs'        => $jobs,
            'departments' => $departments,
            'filters'     => $filters
        ];

        // Render standalone public career view without internal authenticated sidebar
        $this->view('careers/index', $data);
    }

    /**
     * Job Detail View with JSON-LD & Application Form.
     */
    public function job($slug = null): void {
        $slug = trim((string)$slug);
        if (empty($slug)) {
            $this->redirect('index.php?route=careers');
        }

        $job = $this->publishingService->getJobBySlug($this->db, $slug);
        if (!$job) {
            http_response_code(404);
            $this->view('errors/404', ['title' => 'Job Not Found | Careers']);
            return;
        }

        $companyUrl = (defined('URLROOT') ? URLROOT : 'http://localhost');
        $jsonLd = $this->publishingService->generateJsonLd($job, 'Raptor CRM', $companyUrl);

        $data = [
            'title'      => $job['title'] . ' | Careers at Raptor',
            'job'        => $job,
            'json_ld'    => $jsonLd,
            'csrf_token' => $this->generateCsrfToken()
        ];

        $this->view('careers/job', $data);
    }

    /**
     * Process Application Submission.
     */
    public function apply($jobId = null): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('index.php?route=careers');
        }

        $token = $_POST['csrf_token'] ?? '';
        if (!$this->validateCsrfToken($token)) {
            http_response_code(403);
            die("Security error: Invalid session or CSRF token.");
        }

        $jobId = (int) ($jobId ?? $_POST['job_id'] ?? 0);
        if ($jobId <= 0) {
            http_response_code(400);
            die("Invalid job identifier.");
        }

        $uploadedFile = $_FILES['resume'] ?? null;
        $ipAddress    = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $userAgent    = $_SERVER['HTTP_USER_AGENT'] ?? null;

        try {
            $result = $this->publishingService->submitCandidateApplication(
                $this->db,
                $jobId,
                $_POST,
                $uploadedFile,
                $ipAddress,
                $userAgent
            );

            // Return success page or response
            $data = [
                'title'            => 'Application Submitted | Careers',
                'application_code' => $result['application_code'],
                'is_existing'      => $result['is_existing'] ?? false
            ];
            $this->view('careers/success', $data);
        } catch (\Throwable $e) {
            http_response_code(400);
            die("Application submission error: " . htmlspecialchars($e->getMessage()));
        }
    }
}

