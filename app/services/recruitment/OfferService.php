<?php
/**
 * Raptor CRM — Offer Management & Salary Protection Service (Sprint 7)
 * -------------------------------------------------------------------------
 * Manages compensation package building, budget overrun checking, versioned
 * offer letters, salary confidentiality masking, and candidate responses.
 */

declare(strict_types=1);

require_once __DIR__ . '/OfferApprovalService.php';
require_once __DIR__ . '/ApplicationStageService.php';

class OfferService {

    private OfferApprovalService $approvalService;

    public function __construct(?OfferApprovalService $approvalService = null) {
        $this->approvalService = $approvalService ?? new OfferApprovalService();
    }

    /**
     * Generate unique offer reference code: OFF-YYYY-XXXXX
     */
    public function generateOfferCode(PDO $db): string {
        $year = date('Y');
        $prefix = "OFF-{$year}-";

        $stmt = $db->query("
            SELECT offer_code 
            FROM recruitment_offers 
            WHERE offer_code LIKE '{$prefix}%' 
            ORDER BY offer_id DESC 
            LIMIT 1
        ");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $seq = 1;
        if ($row && preg_match('/-(\d+)$/', $row['offer_code'], $m)) {
            $seq = ((int) $m[1]) + 1;
        }

        return sprintf('%s%05d', $prefix, $seq);
    }

    /**
     * Create a formal job offer with compensation breakdown and approval workflow.
     */
    public function createOffer(PDO $db, array $data, int $creatorUserId): array {
        $applicationId = (int)($data['application_id'] ?? 0);
        if ($applicationId <= 0) {
            throw new InvalidArgumentException("A valid application ID is required.");
        }

        // Fetch application, candidate, and requisition budget parameters
        $stmtApp = $db->prepare("
            SELECT a.*, c.first_name, c.last_name, c.email,
                   j.title AS default_job_title, j.department AS default_department,
                   j.location AS default_location,
                   r.requisition_id, r.requisition_code, r.max_salary, r.budget_allocated
            FROM recruitment_applications a
            JOIN recruitment_candidates c ON a.candidate_id = c.candidate_id
            JOIN recruitment_jobs j ON a.job_id = j.job_id
            JOIN recruitment_requisitions r ON j.requisition_id = r.requisition_id
            WHERE a.application_id = :id
        ");
        $stmtApp->execute([':id' => $applicationId]);
        $app = $stmtApp->fetch(PDO::FETCH_ASSOC);

        if (!$app) {
            throw new RuntimeException("Application #{$applicationId} not found.");
        }

        $jobTitle    = trim((string)($data['job_title'] ?? $app['default_job_title']));
        $department  = trim((string)($data['department'] ?? $app['default_department']));
        $location    = trim((string)($data['work_location'] ?? $app['default_location']));
        $annualCtc   = (float)($data['annual_ctc'] ?? 0.0);
        $fixedSalary = (float)($data['fixed_salary'] ?? 0.0);
        $variablePay = (float)($data['variable_pay'] ?? 0.0);
        $bonus       = (float)($data['joining_bonus'] ?? 0.0);

        if ($annualCtc <= 0 || $fixedSalary <= 0) {
            throw new InvalidArgumentException("Annual CTC and Fixed Base Salary must be greater than zero.");
        }

        $joiningDate = trim((string)($data['proposed_joining_date'] ?? ''));
        $expiryDate  = trim((string)($data['offer_expiry_date'] ?? ''));

        if (empty($joiningDate) || empty($expiryDate)) {
            throw new InvalidArgumentException("Proposed joining date and offer expiry date are required.");
        }

        $probationMonths = (int)($data['probation_period_months'] ?? 3);
        $noticeDays      = (int)($data['notice_period_days'] ?? 30);
        $offerCode       = $this->generateOfferCode($db);

        $db->beginTransaction();
        try {
            $stmt = $db->prepare("
                INSERT INTO recruitment_offers (
                    offer_code, application_id, version_number, job_title, department,
                    reporting_manager_id, work_location, employment_type,
                    annual_ctc, fixed_salary, variable_pay, joining_bonus, currency,
                    proposed_joining_date, offer_expiry_date, probation_period_months, notice_period_days,
                    status, created_by, created_at, updated_at
                ) VALUES (
                    :code, :aid, 1, :title, :dept,
                    :mgr, :loc, :etype,
                    :ctc, :fixed, :var, :bonus, 'INR',
                    :jdate, :edate, :prob, :notice,
                    'pending_approval', :uid, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
                )
            ");
            $stmt->execute([
                ':code'   => $offerCode,
                ':aid'    => $applicationId,
                ':title'  => $jobTitle,
                ':dept'   => $department,
                ':mgr'    => !empty($data['reporting_manager_id']) ? (int)$data['reporting_manager_id'] : null,
                ':loc'    => $location,
                ':etype'  => $data['employment_type'] ?? 'full_time',
                ':ctc'    => $annualCtc,
                ':fixed'  => $fixedSalary,
                ':var'    => $variablePay,
                ':bonus'  => $bonus,
                ':jdate'  => $joiningDate,
                ':edate'  => $expiryDate,
                ':prob'   => $probationMonths,
                ':notice' => $noticeDays,
                ':uid'    => $creatorUserId
            ]);

            $offerId = (int)$db->lastInsertId();

            // Initialize multi-tier approval chain with budget overrun check
            $chainResult = $this->approvalService->initializeApprovalChain(
                $db,
                $offerId,
                $annualCtc,
                $app['max_salary'] !== null ? (float)$app['max_salary'] : null,
                $app['budget_allocated'] !== null ? (float)$app['budget_allocated'] : null
            );

            // Advance application stage to 'offer_preparation'
            $stageService = new ApplicationStageService();
            $stageService->transitionStage(
                $db,
                $applicationId,
                'offer_preparation',
                $creatorUserId,
                'OFFER_GENERATED',
                "Generated offer #{$offerCode} with CTC ₹ " . number_format($annualCtc, 2)
            );

            $db->commit();

            return [
                'success'        => true,
                'offer_id'       => $offerId,
                'offer_code'     => $offerCode,
                'status'         => 'pending_approval',
                'is_over_budget' => $chainResult['is_over_budget'],
                'steps_count'    => $chainResult['steps_count']
            ];
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /**
     * Release an approved offer to the candidate.
     */
    public function releaseOffer(PDO $db, int $offerId, int $actorUserId): array {
        $offer = $this->getOfferById($db, $offerId, 'hr');
        if (!$offer) {
            throw new RuntimeException("Offer #{$offerId} not found.");
        }

        if ($offer['status'] !== 'approved') {
            throw new InvalidArgumentException("Offer #{$offerId} cannot be released until all approvals are completed (Current status: '{$offer['status']}').");
        }

        $db->beginTransaction();
        try {
            // Update offer status
            $db->exec("UPDATE recruitment_offers SET status = 'released', updated_at = CURRENT_TIMESTAMP WHERE offer_id = " . (int)$offerId);

            // Transition application stage to 'offer_released'
            $stageService = new ApplicationStageService();
            $stageService->transitionStage(
                $db,
                (int)$offer['application_id'],
                'offer_released',
                $actorUserId,
                'OFFER_RELEASED_TO_CANDIDATE',
                "Offer #{$offer['offer_code']} released to candidate."
            );

            // Enqueue candidate offer email
            $payload = json_encode([
                'offer_id'         => $offerId,
                'offer_code'       => $offer['offer_code'],
                'candidate_name'   => "{$offer['first_name']} {$offer['last_name']}",
                'candidate_email'  => $offer['candidate_email'],
                'job_title'        => $offer['job_title'],
                'annual_ctc'       => (float)$offer['annual_ctc'],
                'joining_date'     => $offer['proposed_joining_date'],
                'offer_expiry_date'=> $offer['offer_expiry_date']
            ]);

            $stmtQ = $db->prepare("
                INSERT INTO recruitment_queue (job_type, payload_json, status, scheduled_at, created_at)
                VALUES ('offer_released', :payload, 'pending', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
            ");
            $stmtQ->execute([':payload' => $payload]);

            $db->commit();

            return [
                'success'    => true,
                'offer_id'   => $offerId,
                'status'     => 'released'
            ];
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /**
     * Record candidate acceptance or decline of an offer.
     */
    public function candidateResponse(
        PDO $db,
        int $offerId,
        string $decision,
        ?string $reason = null
    ): array {
        $decision = strtolower(trim($decision));
        if (!in_array($decision, ['accepted', 'declined'], true)) {
            throw new InvalidArgumentException("Candidate response decision must be 'accepted' or 'declined'.");
        }

        $offer = $this->getOfferById($db, $offerId, 'hr');
        if (!$offer) {
            throw new RuntimeException("Offer #{$offerId} not found.");
        }

        if ($offer['status'] !== 'released') {
            throw new InvalidArgumentException("Offer #{$offerId} must be in 'released' status to record candidate response (Current: '{$offer['status']}').");
        }

        $db->beginTransaction();
        try {
            $stageService = new ApplicationStageService();

            if ($decision === 'accepted') {
                $db->exec("UPDATE recruitment_offers SET status = 'accepted', accepted_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE offer_id = " . (int)$offerId);

                $stageService->transitionStage(
                    $db,
                    (int)$offer['application_id'],
                    'offer_accepted',
                    null,
                    'CANDIDATE_ACCEPTED_OFFER',
                    "Candidate formally accepted offer on " . date('Y-m-d H:i:s')
                );
            } else {
                $stmtDec = $db->prepare("
                    UPDATE recruitment_offers 
                    SET status = 'declined', declined_at = CURRENT_TIMESTAMP, decision_reason = :reason, updated_at = CURRENT_TIMESTAMP
                    WHERE offer_id = :id
                ");
                $stmtDec->execute([
                    ':reason' => $reason ?? 'Candidate declined offer',
                    ':id'     => $offerId
                ]);

                $stageService->transitionStage(
                    $db,
                    (int)$offer['application_id'],
                    'offer_declined',
                    null,
                    'CANDIDATE_DECLINED_OFFER',
                    $reason ?? 'Candidate declined offer'
                );
            }

            $db->commit();

            return [
                'success'  => true,
                'offer_id' => $offerId,
                'decision' => $decision,
                'status'   => $decision
            ];
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /**
     * Fetch offer record with salary confidentiality protection.
     */
    public function getOfferById(
        PDO $db,
        int $offerId,
        string $requestingRole = 'employee'
    ): ?array {
        $stmt = $db->prepare("
            SELECT o.*, 
                   a.candidate_id, a.job_id, a.application_code, a.current_stage,
                   c.first_name, c.last_name, c.email AS candidate_email, c.phone AS candidate_phone, c.candidate_code,
                   j.title AS requisition_job_title, j.department AS requisition_department,
                   u.name AS creator_name
            FROM recruitment_offers o
            JOIN recruitment_applications a ON o.application_id = a.application_id
            JOIN recruitment_candidates c ON a.candidate_id = c.candidate_id
            JOIN recruitment_jobs j ON a.job_id = j.job_id
            LEFT JOIN users u ON o.created_by = u.user_id
            WHERE o.offer_id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $offerId]);
        $offer = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$offer) {
            return null;
        }

        // Apply Salary Confidentiality Masking
        $canViewSalary = in_array(strtolower($requestingRole), ['admin', 'ceo', 'hr', 'hr_manager', 'finance'], true);
        if (!$canViewSalary) {
            $offer['annual_ctc']       = null;
            $offer['fixed_salary']     = null;
            $offer['variable_pay']     = null;
            $offer['joining_bonus']    = null;
            $offer['is_salary_masked'] = true;
        } else {
            $offer['is_salary_masked'] = false;
        }

        // Attach approval steps
        $offer['approvals'] = $this->approvalService->getApprovals($db, $offerId);

        return $offer;
    }

    /**
     * List all offers across requisitions with optional filters.
     */
    public function listOffers(
        PDO $db,
        array $filters = [],
        string $requestingRole = 'employee'
    ): array {
        $conditions = ["1=1"];
        $params = [];

        if (!empty($filters['status'])) {
            $conditions[] = "o.status = :status";
            $params[':status'] = $filters['status'];
        }

        if (!empty($filters['department'])) {
            $conditions[] = "o.department = :dept";
            $params[':dept'] = $filters['department'];
        }

        $whereSql = implode(' AND ', $conditions);

        $stmt = $db->prepare("
            SELECT o.*, 
                   c.first_name, c.last_name, c.candidate_code,
                   j.title AS job_title
            FROM recruitment_offers o
            JOIN recruitment_applications a ON o.application_id = a.application_id
            JOIN recruitment_candidates c ON a.candidate_id = c.candidate_id
            JOIN recruitment_jobs j ON a.job_id = j.job_id
            WHERE {$whereSql}
            ORDER BY o.offer_id DESC
        ");
        $stmt->execute($params);
        $offers = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $canViewSalary = in_array(strtolower($requestingRole), ['admin', 'ceo', 'hr', 'hr_manager', 'finance'], true);
        if (!$canViewSalary) {
            foreach ($offers as &$off) {
                $off['annual_ctc']       = null;
                $off['fixed_salary']     = null;
                $off['variable_pay']     = null;
                $off['joining_bonus']    = null;
                $off['is_salary_masked'] = true;
            }
        }

        return $offers;
    }

    /**
     * Generate HTML printable offer letter document.
     */
    public function generateOfferLetterHtml(array $offer, array $candidate, string $companyName = 'Raptor CRM Enterprise'): string {
        $ctcStr = !empty($offer['is_salary_masked']) ? '[RESTRICTED CONFIDENTIAL]' : '₹ ' . number_format((float)($offer['annual_ctc'] ?? 0), 2);
        $fixedStr = !empty($offer['is_salary_masked']) ? '[RESTRICTED]' : '₹ ' . number_format((float)($offer['fixed_salary'] ?? 0), 2);

        return "
        <div style='font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 800px; margin: 0 auto; padding: 40px; border: 1px solid #e0e0e0;'>
            <div style='text-align: center; border-bottom: 2px solid #0d6efd; padding-bottom: 20px; margin-bottom: 30px;'>
                <h1 style='color: #0d6efd; margin: 0;'>{$companyName}</h1>
                <p style='color: #666; margin: 5px 0 0 0;'>Official Letter of Employment Offer</p>
            </div>
            
            <p><strong>Date:</strong> " . date('F d, Y') . "</p>
            <p><strong>Offer Reference:</strong> {$offer['offer_code']}</p>
            
            <p>Dear <strong>{$candidate['first_name']} {$candidate['last_name']}</strong>,</p>
            
            <p>We are pleased to offer you the position of <strong>{$offer['job_title']}</strong> in our <strong>{$offer['department']}</strong> department at <strong>{$companyName}</strong>.</p>
            
            <h3 style='color: #0d6efd; border-bottom: 1px solid #ddd; padding-bottom: 5px;'>Compensation Terms</h3>
            <table style='width: 100%; border-collapse: collapse; margin-bottom: 20px;'>
                <tr><td style='padding: 8px 0; border-bottom: 1px solid #f0f0f0;'><strong>Total Cost to Company (Annual CTC):</strong></td><td style='text-align: right; font-weight: bold;'>{$ctcStr}</td></tr>
                <tr><td style='padding: 8px 0; border-bottom: 1px solid #f0f0f0;'><strong>Fixed Annual Base Salary:</strong></td><td style='text-align: right;'>{$fixedStr}</td></tr>
                <tr><td style='padding: 8px 0; border-bottom: 1px solid #f0f0f0;'><strong>Work Location:</strong></td><td style='text-align: right;'>{$offer['work_location']}</td></tr>
                <tr><td style='padding: 8px 0; border-bottom: 1px solid #f0f0f0;'><strong>Proposed Joining Date:</strong></td><td style='text-align: right; color: #0d6efd; font-weight: bold;'>{$offer['proposed_joining_date']}</td></tr>
                <tr><td style='padding: 8px 0; border-bottom: 1px solid #f0f0f0;'><strong>Offer Valid Until:</strong></td><td style='text-align: right;'>{$offer['offer_expiry_date']}</td></tr>
            </table>
            
            <p>Please review and sign this offer letter prior to the offer expiry date.</p>
            
            <div style='margin-top: 50px; display: flex; justify-content: space-between;'>
                <div>
                    <p style='margin: 0;'>____________________________</p>
                    <p style='margin: 5px 0 0 0;'><strong>Authorized Signatory</strong><br>{$companyName}</p>
                </div>
                <div>
                    <p style='margin: 0;'>____________________________</p>
                    <p style='margin: 5px 0 0 0;'><strong>Candidate Acceptance</strong><br>{$candidate['first_name']} {$candidate['last_name']}</p>
                </div>
            </div>
        </div>
        ";
    }
}

