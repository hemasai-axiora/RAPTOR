<?php
/**
 * Raptor CRM — Internal Job Postings Controller (Sprint 3)
 * -----------------------------------------------------------------------------------
 * Manages career portal publishing, active job postings, and applicant volume.
 */

declare(strict_types=1);

require_once __DIR__ . '/../services/recruitment/JobPublishingService.php';

class RecruitmentJobController extends Controller {
    private JobPublishingService $publishingService;
    private PDO $db;

    public function __construct() {
        $this->requireAuth();

        if (!Policy::canAccessRecruitment()) {
            http_response_code(403);
            die("Access Denied: You do not have permission to manage Job Postings.");
        }

        $this->publishingService = new JobPublishingService();
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * List all Job Postings (Internal ATS View).
     */
    public function index(): void {
        $role = (string) ($_SESSION['user_role'] ?? 'employee');

        $stmt = $this->db->query("
            SELECT j.*, r.requisition_code, r.vacancies, u.name as publisher_name
            FROM recruitment_jobs j
            JOIN recruitment_requisitions r ON j.requisition_id = r.requisition_id
            JOIN users u ON j.created_by = u.user_id
            ORDER BY j.job_id DESC
        ");
        $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Also fetch approved requisitions eligible for publishing
        $stmtAppr = $this->db->query("
            SELECT r.requisition_id, r.requisition_code, r.title, r.department, r.vacancies
            FROM recruitment_requisitions r
            LEFT JOIN recruitment_jobs j ON r.requisition_id = j.requisition_id
            WHERE r.status = 'approved' AND j.job_id IS NULL
            ORDER BY r.requisition_id DESC
        ");
        $pendingPublish = $stmtAppr->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $data = [
            'title'          => 'Job Postings & Career Listings | Raptor ATS',
            'active_tab'     => 'recruitment_jobs',
            'role'           => $role,
            'jobs'           => $jobs,
            'pending_publish'=> $pendingPublish,
            'csrf_token'     => $this->generateCsrfToken()
        ];

        $this->viewWithLayout('recruitment/jobs/index', 'main', $data);
    }

    /**
     * Publish an approved Requisition.
     */
    public function publish($id = null): void {
        $requisitionId = (int) ($id ?? $_POST['requisition_id'] ?? 0);
        if ($requisitionId <= 0) {
            $this->redirect('index.php?route=recruitment_job/index');
        }

        $userId = (int) $_SESSION['user_id'];

        try {
            $this->publishingService->publishJob($this->db, $requisitionId, $_POST, $userId);
            $this->redirect('index.php?route=recruitment_job/index');
        } catch (\Throwable $e) {
            http_response_code(400);
            die("Error publishing job: " . htmlspecialchars($e->getMessage()));
        }
    }

    /**
     * Pause a published job.
     */
    public function pause($id = null): void {
        $jobId = (int) $id;
        $this->publishingService->pauseJob($this->db, $jobId);
        $this->redirect('index.php?route=recruitment_job/index');
    }

    /**
     * Close a job.
     */
    public function close($id = null): void {
        $jobId = (int) $id;
        $this->publishingService->closeJob($this->db, $jobId);
        $this->redirect('index.php?route=recruitment_job/index');
    }
}

