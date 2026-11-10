<?php
/**
 * Raptor CRM — Requisition Service (Sprint 2)
 * -----------------------------------------------------------------------------------
 * Orchestrates job requisition lifecycle, headcount validation, and status transitions.
 */

declare(strict_types=1);

require_once __DIR__ . '/RequisitionApprovalService.php';

class RequisitionService {
    private RequisitionApprovalService $approvalService;

    public function __construct(?RequisitionApprovalService $approvalService = null) {
        $this->approvalService = $approvalService ?? new RequisitionApprovalService();
    }

    /**
     * Generate sequential unique requisition code (e.g. REQ-2026-0042).
     */
    public function generateRequisitionCode(PDO $db): string {
        $year = date('Y');
        $prefix = "REQ-{$year}-";

        $stmt = $db->query("
            SELECT requisition_code 
            FROM recruitment_requisitions 
            WHERE requisition_code LIKE '{$prefix}%' 
            ORDER BY requisition_id DESC 
            LIMIT 1
        ");
        $latest = $stmt->fetchColumn();

        if ($latest && preg_match('/REQ-\d{4}-(\d+)/', (string)$latest, $matches)) {
            $seq = ((int) $matches[1]) + 1;
        } else {
            $seq = 1;
        }

        return sprintf("REQ-%s-%04d", $year, $seq);
    }

    /**
     * Create a new Job Requisition.
     */
    public function createRequisition(PDO $db, array $data, int $userId): array {
        // Validate required fields
        $title = trim((string)($data['title'] ?? ''));
        $department = trim((string)($data['department'] ?? ''));
        $jobDescription = trim((string)($data['job_description'] ?? ''));

        if (empty($title) || empty($department) || empty($jobDescription)) {
            throw new InvalidArgumentException("Job Title, Department, and Job Description are required.");
        }

        $vacancies = max(1, (int)($data['vacancies'] ?? 1));
        $code = $this->generateRequisitionCode($db);

        $stmt = $db->prepare("
            INSERT INTO recruitment_requisitions (
                requisition_code, title, department, team_id, branch_id,
                hiring_manager_id, assigned_recruiter_id, reporting_manager_id,
                employment_type, workplace_arrangement, vacancies, hiring_priority,
                hiring_reason, replacement_for_user_id, min_experience_years,
                max_experience_years, min_salary, max_salary, currency,
                is_salary_confidential, budget_allocated, target_hire_date,
                requisition_expiry_date, business_justification, job_description,
                responsibilities, required_skills, status, created_by, created_at
            ) VALUES (
                :code, :title, :dept, :team_id, :branch_id,
                :hiring_mgr, :recruiter, :reporting_mgr,
                :emp_type, :workplace, :vacancies, :priority,
                :reason, :replacement, :min_exp,
                :max_exp, :min_sal, :max_sal, :currency,
                :is_conf, :budget, :target_date,
                :expiry_date, :justification, :job_desc,
                :responsibilities, :skills, :status, :created_by, CURRENT_TIMESTAMP
            )
        ");

        $status = (!empty($data['submit_for_approval'])) ? 'pending_approval' : 'draft';

        $stmt->execute([
            ':code'             => $code,
            ':title'            => $title,
            ':dept'             => $department,
            ':team_id'          => !empty($data['team_id']) ? (int)$data['team_id'] : null,
            ':branch_id'        => !empty($data['branch_id']) ? (int)$data['branch_id'] : null,
            ':hiring_mgr'       => !empty($data['hiring_manager_id']) ? (int)$data['hiring_manager_id'] : $userId,
            ':recruiter'        => !empty($data['assigned_recruiter_id']) ? (int)$data['assigned_recruiter_id'] : null,
            ':reporting_mgr'    => !empty($data['reporting_manager_id']) ? (int)$data['reporting_manager_id'] : null,
            ':emp_type'         => $data['employment_type'] ?? 'full_time',
            ':workplace'        => $data['workplace_arrangement'] ?? 'on_site',
            ':vacancies'        => $vacancies,
            ':priority'         => $data['hiring_priority'] ?? 'medium',
            ':reason'           => $data['hiring_reason'] ?? 'new_position',
            ':replacement'      => !empty($data['replacement_for_user_id']) ? (int)$data['replacement_for_user_id'] : null,
            ':min_exp'          => (float)($data['min_experience_years'] ?? 0.0),
            ':max_exp'          => !empty($data['max_experience_years']) ? (float)$data['max_experience_years'] : null,
            ':min_sal'          => !empty($data['min_salary']) ? (float)$data['min_salary'] : null,
            ':max_sal'          => !empty($data['max_salary']) ? (float)$data['max_salary'] : null,
            ':currency'         => $data['currency'] ?? 'INR',
            ':is_conf'          => isset($data['is_salary_confidential']) ? (int)(bool)$data['is_salary_confidential'] : 1,
            ':budget'           => !empty($data['budget_allocated']) ? (float)$data['budget_allocated'] : null,
            ':target_date'      => !empty($data['target_hire_date']) ? $data['target_hire_date'] : null,
            ':expiry_date'      => !empty($data['requisition_expiry_date']) ? $data['requisition_expiry_date'] : null,
            ':justification'    => $data['business_justification'] ?? null,
            ':job_desc'         => $jobDescription,
            ':responsibilities' => $data['responsibilities'] ?? null,
            ':skills'           => $data['required_skills'] ?? null,
            ':status'           => $status,
            ':created_by'       => $userId
        ]);

        $requisitionId = (int) $db->lastInsertId();

        // If submitted immediately, initialize approval chain
        if ($status === 'pending_approval') {
            $this->approvalService->initializeApprovalChain($db, $requisitionId, $data);
        }

        return [
            'success'          => true,
            'requisition_id'   => $requisitionId,
            'requisition_code' => $code,
            'status'           => $status
        ];
    }

    /**
     * Submit draft requisition for approval.
     */
    public function submitForApproval(PDO $db, int $requisitionId, int $userId): array {
        $stmt = $db->prepare("SELECT * FROM recruitment_requisitions WHERE requisition_id = :id");
        $stmt->execute([':id' => $requisitionId]);
        $req = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$req) {
            throw new RuntimeException("Requisition #{$requisitionId} not found.");
        }

        if (!in_array($req['status'], ['draft', 'changes_requested'], true)) {
            throw new RuntimeException("Only draft or changes_requested requisitions can be submitted for approval.");
        }

        // Initialize or re-initialize approval steps
        $db->exec("DELETE FROM recruitment_requisition_approvals WHERE requisition_id = " . (int)$requisitionId);
        $this->approvalService->initializeApprovalChain($db, $requisitionId, $req);

        $stmtUpd = $db->prepare("
            UPDATE recruitment_requisitions 
            SET status = 'pending_approval', updated_at = CURRENT_TIMESTAMP 
            WHERE requisition_id = :id
        ");
        $stmtUpd->execute([':id' => $requisitionId]);

        return [
            'success'        => true,
            'requisition_id' => $requisitionId,
            'status'         => 'pending_approval'
        ];
    }

    /**
     * Fetch single requisition with authorization and salary masking.
     */
    public function getRequisition(PDO $db, int $requisitionId, int $userId, string $role): ?array {
        $stmt = $db->prepare("
            SELECT r.*, 
                   u.name as hiring_manager_name, 
                   u.email as hiring_manager_email,
                   rec.name as assigned_recruiter_name,
                   rep.name as reporting_manager_name,
                   t.name as team_name,
                   b.name as branch_name
            FROM recruitment_requisitions r
            JOIN users u ON r.hiring_manager_id = u.user_id
            LEFT JOIN users rec ON r.assigned_recruiter_id = rec.user_id
            LEFT JOIN users rep ON r.reporting_manager_id = rep.user_id
            LEFT JOIN teams t ON r.team_id = t.team_id
            LEFT JOIN branches b ON r.branch_id = b.branch_id
            WHERE r.requisition_id = :id
        ");
        $stmt->execute([':id' => $requisitionId]);
        $req = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$req) {
            return null;
        }

        // Check subtree scoping for managers
        if (in_array($role, ['manager', 'team_leader'], true)) {
            $subtreeIds = Policy::getTeamSubtreeUserIds($userId);
            if (!in_array((int)$req['hiring_manager_id'], $subtreeIds, true) && (int)$req['created_by'] !== $userId) {
                return null; // Out of authorized subtree
            }
        }

        // Salary confidentiality check
        $canViewSalary = in_array($role, ['admin', 'ceo', 'hr', 'finance'], true);
        if (!$canViewSalary && (int)$req['is_salary_confidential'] === 1) {
            $req['min_salary'] = null;
            $req['max_salary'] = null;
            $req['budget_allocated'] = null;
            $req['salary_masked'] = true;
        } else {
            $req['salary_masked'] = false;
        }

        $req['approvals'] = $this->approvalService->getApprovalHistory($db, $requisitionId);

        return $req;
    }

    /**
     * List requisitions with filtering, searching, and pagination.
     */
    public function listRequisitions(
        PDO $db,
        array $filters = [],
        int $userId = 0,
        string $role = 'admin',
        int $page = 1,
        int $perPage = 20
    ): array {
        $conditions = [];
        $params = [];

        // Role & Subtree Scoping
        if (in_array($role, ['manager', 'team_leader'], true)) {
            $subtreeIds = Policy::getTeamSubtreeUserIds($userId);
            if (empty($subtreeIds)) {
                $subtreeIds = [$userId];
            }
            $inClause = implode(',', array_map('intval', $subtreeIds));
            $conditions[] = "(r.hiring_manager_id IN ({$inClause}) OR r.created_by = :uid)";
            $params[':uid'] = $userId;
        }

        // Status Filter
        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $conditions[] = "r.status = :status";
            $params[':status'] = $filters['status'];
        }

        // Department Filter
        if (!empty($filters['department'])) {
            $conditions[] = "r.department = :dept";
            $params[':dept'] = $filters['department'];
        }

        // Search Term
        if (!empty($filters['search'])) {
            $conditions[] = "(r.title LIKE :search OR r.requisition_code LIKE :search)";
            $params[':search'] = '%' . trim((string)$filters['search']) . '%';
        }

        $whereSql = $conditions ? ('WHERE ' . implode(' AND ', $conditions)) : '';

        // Total count
        $stmtCnt = $db->prepare("SELECT COUNT(*) FROM recruitment_requisitions r {$whereSql}");
        $stmtCnt->execute($params);
        $total = (int) $stmtCnt->fetchColumn();

        // Paginated rows
        $offset = max(0, ($page - 1) * $perPage);
        $stmtList = $db->prepare("
            SELECT r.requisition_id, r.requisition_code, r.title, r.department, r.vacancies,
                   r.employment_type, r.workplace_arrangement, r.hiring_priority, r.status,
                   r.target_hire_date, r.created_at, r.is_salary_confidential, r.min_salary, r.max_salary,
                   u.name as hiring_manager_name
            FROM recruitment_requisitions r
            JOIN users u ON r.hiring_manager_id = u.user_id
            {$whereSql}
            ORDER BY r.requisition_id DESC
            LIMIT {$perPage} OFFSET {$offset}
        ");
        $stmtList->execute($params);
        $rows = $stmtList->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Apply salary masking
        $canViewSalary = in_array($role, ['admin', 'ceo', 'hr', 'finance'], true);
        foreach ($rows as &$row) {
            if (!$canViewSalary && (int)$row['is_salary_confidential'] === 1) {
                $row['min_salary'] = null;
                $row['max_salary'] = null;
                $row['salary_masked'] = true;
            } else {
                $row['salary_masked'] = false;
            }
        }

        return [
            'data'     => $rows,
            'total'    => $total,
            'page'     => $page,
            'per_page' => $perPage,
            'pages'    => ceil($total / max(1, $perPage))
        ];
    }
}
