<?php
/**
 * Raptor CRM — Offer Approval Workflow Service (Sprint 7)
 * -------------------------------------------------------------------------
 * Multi-tier approval chain engine with automated budget variance escalation
 * and segregation of duties (Hiring Manager -> HR -> Finance/CEO).
 */

declare(strict_types=1);

require_once __DIR__ . '/ApplicationStageService.php';

class OfferApprovalService {

    public const THRESHOLD_EXECUTIVE_SALARY = 1500000.00; // >= 15 Lakhs INR

    /**
     * Initialize multi-step approval workflow chain for an offer.
     * Escalates to Executive/CEO if CTC exceeds requisition budget or threshold.
     */
    public function initializeApprovalChain(
        PDO $db,
        int $offerId,
        float $annualCtc,
        ?float $requisitionMaxSalary = null,
        ?float $budgetAllocated = null
    ): array {
        // Step 1: Department Head
        // Step 2: HR Manager
        $steps = [
            ['step' => 1, 'role' => 'department_head'],
            ['step' => 2, 'role' => 'hr_manager']
        ];

        // Budget Overrun Rule: If proposed CTC exceeds requisition budget, or is >= 15L
        $isOverBudget = false;
        if ($requisitionMaxSalary !== null && $annualCtc > $requisitionMaxSalary) {
            $isOverBudget = true;
        } elseif ($budgetAllocated !== null && $annualCtc > $budgetAllocated) {
            $isOverBudget = true;
        }

        if ($isOverBudget || $annualCtc >= self::THRESHOLD_EXECUTIVE_SALARY) {
            $steps[] = ['step' => 3, 'role' => 'ceo'];
        }

        $stmt = $db->prepare("
            INSERT INTO recruitment_offer_approvals (
                offer_id, step_number, approver_role, status, created_at
            ) VALUES (
                :oid, :step, :role, 'pending', CURRENT_TIMESTAMP
            )
        ");

        foreach ($steps as $st) {
            $stmt->execute([
                ':oid'  => $offerId,
                ':step' => $st['step'],
                ':role' => $st['role']
            ]);
        }

        return [
            'steps_count'    => count($steps),
            'is_over_budget' => $isOverBudget,
            'steps'          => $steps
        ];
    }

    /**
     * Retrieve all approval steps for an offer.
     */
    public function getApprovals(PDO $db, int $offerId): array {
        $stmt = $db->prepare("
            SELECT a.*, u.name AS actioned_by_name, r.role_name
            FROM recruitment_offer_approvals a
            LEFT JOIN users u ON a.actioned_by = u.user_id
            LEFT JOIN roles r ON u.role_id = r.role_id
            WHERE a.offer_id = :oid
            ORDER BY a.step_number ASC
        ");
        $stmt->execute([':oid' => $offerId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Action an offer approval step.
     */
    public function actionApproval(
        PDO $db,
        int $offerId,
        int $actorUserId,
        string $actorRole,
        string $decision,
        ?string $comments = null
    ): array {
        $decision = strtolower(trim($decision));
        if (!in_array($decision, ['approved', 'rejected', 'changes_requested'], true)) {
            throw new InvalidArgumentException("Decision must be 'approved', 'rejected', or 'changes_requested'.");
        }

        $db->beginTransaction();
        try {
            // Fetch next pending approval step
            $stmtStep = $db->prepare("
                SELECT * FROM recruitment_offer_approvals 
                WHERE offer_id = :oid AND status = 'pending' 
                ORDER BY step_number ASC 
                LIMIT 1
            ");
            $stmtStep->execute([':oid' => $offerId]);
            $currentStep = $stmtStep->fetch(PDO::FETCH_ASSOC);

            if (!$currentStep) {
                throw new RuntimeException("No pending approval steps found for Offer #{$offerId}.");
            }

            // Role verification
            $reqRole = $currentStep['approver_role'];
            $normalizedActorRole = strtolower($actorRole);

            $authorized = false;
            if (in_array($normalizedActorRole, ['admin', 'super_admin'], true)) {
                $authorized = true;
            } elseif ($reqRole === 'department_head' && in_array($normalizedActorRole, ['manager', 'department_head', 'ceo'], true)) {
                $authorized = true;
            } elseif ($reqRole === 'hr_manager' && in_array($normalizedActorRole, ['hr', 'hr_manager', 'ceo'], true)) {
                $authorized = true;
            } elseif ($reqRole === 'ceo' && in_array($normalizedActorRole, ['ceo', 'finance', 'cfo'], true)) {
                $authorized = true;
            }

            if (!$authorized) {
                throw new RuntimeException("Role '{$actorRole}' is not authorized to action step '{$reqRole}'.");
            }

            // Update current step
            $stmtUpStep = $db->prepare("
                UPDATE recruitment_offer_approvals 
                SET status = :decision, comments = :comm, actioned_by = :uid, actioned_at = CURRENT_TIMESTAMP
                WHERE approval_id = :aid
            ");
            $stmtUpStep->execute([
                ':decision' => $decision,
                ':comm'     => $comments,
                ':uid'      => $actorUserId,
                ':aid'      => (int)$currentStep['approval_id']
            ]);

            // Fetch offer record to get linked application
            $stmtOffer = $db->prepare("SELECT * FROM recruitment_offers WHERE offer_id = :id");
            $stmtOffer->execute([':id' => $offerId]);
            $offer = $stmtOffer->fetch(PDO::FETCH_ASSOC);

            $stageService = new ApplicationStageService();

            if ($decision === 'rejected') {
                $db->exec("UPDATE recruitment_offers SET status = 'rejected', decision_reason = " . $db->quote($comments ?? 'Rejected') . ", updated_at = CURRENT_TIMESTAMP WHERE offer_id = " . (int)$offerId);
                $stageService->transitionStage($db, (int)$offer['application_id'], 'rejected', $actorUserId, 'OFFER_REJECTED_BY_APPROVER', $comments);

                $db->commit();
                return ['success' => true, 'offer_status' => 'rejected', 'all_approved' => false];
            }

            if ($decision === 'changes_requested') {
                $db->exec("UPDATE recruitment_offers SET status = 'changes_requested', decision_reason = " . $db->quote($comments ?? 'Changes requested') . ", updated_at = CURRENT_TIMESTAMP WHERE offer_id = " . (int)$offerId);

                $db->commit();
                return ['success' => true, 'offer_status' => 'changes_requested', 'all_approved' => false];
            }

            // Check if further pending steps exist
            $stmtPending = $db->prepare("SELECT COUNT(*) FROM recruitment_offer_approvals WHERE offer_id = :oid AND status = 'pending'");
            $stmtPending->execute([':oid' => $offerId]);
            $pendingCount = (int)$stmtPending->fetchColumn();

            if ($pendingCount === 0) {
                // All steps approved!
                $db->exec("UPDATE recruitment_offers SET status = 'approved', updated_at = CURRENT_TIMESTAMP WHERE offer_id = " . (int)$offerId);
                $stageService->transitionStage($db, (int)$offer['application_id'], 'offer_approved', $actorUserId, 'ALL_OFFER_APPROVALS_COMPLETED', 'Offer approved by all stakeholders.');

                $db->commit();
                return ['success' => true, 'offer_status' => 'approved', 'all_approved' => true];
            }

            $db->commit();
            return ['success' => true, 'offer_status' => 'pending_approval', 'all_approved' => false];
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }
}

