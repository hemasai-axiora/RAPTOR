<?php
/**
 * Raptor CRM — Background Verification (BGV) & Pre-Joining Service (Sprint 8)
 * -------------------------------------------------------------------------
 * Manages candidate background checks (Employment, Criminal, Education),
 * verification provider tracking, and pre-joining document checklists.
 */

declare(strict_types=1);

require_once __DIR__ . '/ApplicationStageService.php';

class BackgroundVerificationService {

    public const CHECK_TYPES = [
        'employment' => 'Prior Employment & Experience Verification',
        'education'  => 'Educational Credentials & Degree Authenticity',
        'criminal'   => 'Criminal Background & Police Record Check',
        'address'    => 'Permanent Address Verification',
        'reference'  => 'Professional Reference Check'
    ];

    public const DEFAULT_CHECKLIST_ITEMS = [
        'Signed Official Offer Letter',
        'Government Photo Identity (Aadhaar / Passport / Voter ID)',
        'Permanent Account Number (PAN Card)',
        'Relieving & Experience Certificates from Prior Employer',
        'Last 3 Months Salary Slips or Form 16',
        'Highest Educational Degree & Marksheets'
    ];

    /**
     * Initiate background verification checks for a candidate application.
     */
    public function initiateChecks(
        PDO $db,
        int $applicationId,
        array $checkTypes = ['employment', 'criminal', 'education'],
        string $providerName = 'internal'
    ): array {
        $stmtApp = $db->prepare("SELECT * FROM recruitment_applications WHERE application_id = :id");
        $stmtApp->execute([':id' => $applicationId]);
        $app = $stmtApp->fetch(PDO::FETCH_ASSOC);

        if (!$app) {
            throw new RuntimeException("Application #{$applicationId} not found.");
        }

        $db->beginTransaction();
        try {
            $stmt = $db->prepare("
                INSERT INTO recruitment_background_checks (
                    application_id, check_type, provider_name, status, created_at
                ) VALUES (
                    :aid, :type, :provider, 'initiated', CURRENT_TIMESTAMP
                )
            ");

            $initiated = [];
            foreach ($checkTypes as $type) {
                if (isset(self::CHECK_TYPES[$type])) {
                    $stmt->execute([
                        ':aid'      => $applicationId,
                        ':type'     => $type,
                        ':provider' => $providerName
                    ]);
                    $initiated[] = [
                        'bgv_id'     => (int)$db->lastInsertId(),
                        'check_type' => $type,
                        'status'     => 'initiated'
                    ];
                }
            }

            // Transition stage to 'bgv'
            $stageService = new ApplicationStageService();
            if ($app['current_stage'] !== 'bgv') {
                $stageService->transitionStage(
                    $db,
                    $applicationId,
                    'bgv',
                    null,
                    'BGV_INITIATED',
                    "Initiated " . count($initiated) . " background verification checks."
                );
            }

            // Initialize pre-joining checklist
            $this->initiatePreJoiningChecklist($db, $applicationId);

            $db->commit();

            return [
                'success'   => true,
                'initiated' => $initiated
            ];
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /**
     * Update status and findings of a background verification check.
     */
    public function updateCheckStatus(
        PDO $db,
        int $bgvId,
        string $status,
        ?string $findings = null,
        int $verifiedByUserId = 1
    ): array {
        $status = strtolower(trim($status));
        $validStatuses = ['initiated', 'in_progress', 'passed', 'failed', 'flagged'];
        if (!in_array($status, $validStatuses, true)) {
            throw new InvalidArgumentException("Invalid BGV status: '{$status}'.");
        }

        $stmt = $db->prepare("
            UPDATE recruitment_background_checks 
            SET status = :status, findings_summary = :findings,
                verified_by_user_id = :uid, verified_at = CURRENT_TIMESTAMP
            WHERE bgv_id = :id
        ");
        $stmt->execute([
            ':status'   => $status,
            ':findings' => $findings,
            ':uid'      => $verifiedByUserId,
            ':id'       => $bgvId
        ]);

        return [
            'success' => true,
            'bgv_id'  => $bgvId,
            'status'  => $status
        ];
    }

    /**
     * Fetch all background verification checks for an application.
     */
    public function getChecksByApplication(PDO $db, int $applicationId): array {
        $stmt = $db->prepare("
            SELECT b.*, u.name AS verified_by_name
            FROM recruitment_background_checks b
            LEFT JOIN users u ON b.verified_by_user_id = u.user_id
            WHERE b.application_id = :id
            ORDER BY b.bgv_id ASC
        ");
        $stmt->execute([':id' => $applicationId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getChecksForApplication(PDO $db, int $applicationId): array {
        return $this->getChecksByApplication($db, $applicationId);
    }

    /**
     * Initialize standard pre-joining document checklist.
     */
    public function initiatePreJoiningChecklist(PDO $db, int $applicationId): array {
        $stmtCheck = $db->prepare("SELECT COUNT(*) FROM recruitment_pre_joining_checklist WHERE application_id = :id");
        $stmtCheck->execute([':id' => $applicationId]);
        if ((int)$stmtCheck->fetchColumn() > 0) {
            return $this->getChecklistByApplication($db, $applicationId);
        }

        $stmt = $db->prepare("
            INSERT INTO recruitment_pre_joining_checklist (
                application_id, item_name, is_mandatory, is_completed, created_at
            ) VALUES (
                :aid, :item, 1, 0, CURRENT_TIMESTAMP
            )
        ");

        foreach (self::DEFAULT_CHECKLIST_ITEMS as $item) {
            $stmt->execute([
                ':aid'  => $applicationId,
                ':item' => $item
            ]);
        }

        return $this->getChecklistByApplication($db, $applicationId);
    }

    /**
     * Fetch pre-joining checklist for an application.
     */
    public function getChecklistByApplication(PDO $db, int $applicationId): array {
        $stmt = $db->prepare("
            SELECT c.*, u.name AS verified_by_name
            FROM recruitment_pre_joining_checklist c
            LEFT JOIN users u ON c.verified_by_user_id = u.user_id
            WHERE c.application_id = :id
            ORDER BY c.item_id ASC
        ");
        $stmt->execute([':id' => $applicationId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getPreJoiningChecklist(PDO $db, int $applicationId): array {
        return $this->getChecklistByApplication($db, $applicationId);
    }

    /**
     * Mark a pre-joining checklist item as completed/verified.
     */
    public function verifyChecklistItem(
        PDO $db,
        int $itemId,
        int $verifiedByUserId,
        ?int $documentId = null
    ): bool {
        $stmt = $db->prepare("
            UPDATE recruitment_pre_joining_checklist 
            SET is_completed = 1, verified_document_id = :did,
                verified_by_user_id = :uid, verified_at = CURRENT_TIMESTAMP
            WHERE item_id = :id
        ");
        return $stmt->execute([
            ':did' => $documentId,
            ':uid' => $verifiedByUserId,
            ':id'  => $itemId
        ]);
    }

    /**
     * Check if all mandatory onboarding checklist items are verified.
     */
    public function isChecklistComplete(PDO $db, int $applicationId): bool {
        $stmt = $db->prepare("
            SELECT COUNT(*) 
            FROM recruitment_pre_joining_checklist 
            WHERE application_id = :id AND is_mandatory = 1 AND is_completed = 0
        ");
        $stmt->execute([':id' => $applicationId]);
        return ((int)$stmt->fetchColumn() === 0);
    }
}
