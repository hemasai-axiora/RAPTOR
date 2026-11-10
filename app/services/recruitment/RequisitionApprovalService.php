<?php
/**
 * Raptor CRM — Requisition Approval Service (Sprint 2)
 * -----------------------------------------------------------------------------------
 * Manages multi-level approval chains, segregation of duties, and decision states.
 */

declare(strict_types=1);

class RequisitionApprovalService {

    /**
     * Initializes the multi-step approval chain for a requisition.
     */
    public function initializeApprovalChain(PDO $db, int $requisitionId, array $requisitionData): array {
        // Step 1: Department Head
        // Step 2: HR Manager
        // Step 3: Finance Director / CEO (if high budget or urgent priority)
        $steps = [
            [1, 'department_head'],
            [2, 'hr_manager']
        ];

        $maxSalary = (float) ($requisitionData['max_salary'] ?? 0);
        $priority  = (string) ($requisitionData['hiring_priority'] ?? 'medium');

        if ($maxSalary >= 1500000 || $priority === 'urgent') {
            $steps[] = [3, 'finance'];
        }

        $stmtIns = $db->prepare("
            INSERT INTO recruitment_requisition_approvals 
            (requisition_id, step_number, approver_role, status, created_at)
            VALUES (:req_id, :step, :role, 'pending', CURRENT_TIMESTAMP)
        ");

        $createdSteps = [];
        foreach ($steps as [$stepNum, $roleName]) {
            $stmtIns->execute([
                ':req_id' => $requisitionId,
                ':step'   => $stepNum,
                ':role'   => $roleName
            ]);
            $createdSteps[] = ['step_number' => $stepNum, 'approver_role' => $roleName, 'status' => 'pending'];
        }

        return $createdSteps;
    }

    /**
     * Actions an approval step (approve, reject, changes_requested).
     */
    public function actionApproval(
        PDO $db,
        int $requisitionId,
        int $userId,
        string $userRole,
        string $decision,
        ?string $comments = null,
        ?string $ipAddress = null
    ): array {
        if (!in_array($decision, ['approved', 'rejected', 'changes_requested'], true)) {
            throw new InvalidArgumentException("Invalid approval decision: {$decision}");
        }

        // Fetch current active pending step
        $stmtCurr = $db->prepare("
            SELECT approval_id, step_number, approver_role, approver_id 
            FROM recruitment_requisition_approvals
            WHERE requisition_id = :req_id AND status = 'pending'
            ORDER BY step_number ASC
            LIMIT 1
        ");
        $stmtCurr->execute([':req_id' => $requisitionId]);
        $currentStep = $stmtCurr->fetch(PDO::FETCH_ASSOC);

        if (!$currentStep) {
            throw new RuntimeException("No pending approval steps found for requisition #{$requisitionId}.");
        }

        // Verify authorization for this step
        $isAuthorized = false;
        if (in_array($userRole, ['admin', 'ceo'], true)) {
            $isAuthorized = true;
        } elseif ($currentStep['approver_role'] === 'department_head' && in_array($userRole, ['manager', 'team_leader', 'hr'], true)) {
            $isAuthorized = true;
        } elseif ($currentStep['approver_role'] === 'hr_manager' && in_array($userRole, ['hr'], true)) {
            $isAuthorized = true;
        } elseif ($currentStep['approver_role'] === 'finance' && in_array($userRole, ['finance', 'ceo'], true)) {
            $isAuthorized = true;
        } elseif (!empty($currentStep['approver_id']) && (int)$currentStep['approver_id'] === $userId) {
            $isAuthorized = true;
        }

        if (!$isAuthorized) {
            throw new RuntimeException("User does not have authorization to act as '{$currentStep['approver_role']}'.");
        }

        // Action the current step
        $stmtUpdate = $db->prepare("
            UPDATE recruitment_requisition_approvals
            SET status = :decision,
                comments = :comments,
                actioned_by = :uid,
                actioned_at = CURRENT_TIMESTAMP,
                ip_address = :ip
            WHERE approval_id = :aid
        ");
        $stmtUpdate->execute([
            ':decision' => $decision,
            ':comments' => $comments,
            ':uid'      => $userId,
            ':ip'       => $ipAddress,
            ':aid'      => (int) $currentStep['approval_id']
        ]);

        $newRequisitionStatus = 'pending_approval';

        if ($decision === 'rejected') {
            $newRequisitionStatus = 'rejected';
        } elseif ($decision === 'changes_requested') {
            $newRequisitionStatus = 'changes_requested';
        } else {
            // Check if more pending steps remain
            $stmtRem = $db->prepare("
                SELECT COUNT(*) FROM recruitment_requisition_approvals
                WHERE requisition_id = :req_id AND status = 'pending'
            ");
            $stmtRem->execute([':req_id' => $requisitionId]);
            $remaining = (int) $stmtRem->fetchColumn();

            if ($remaining === 0) {
                $newRequisitionStatus = 'approved';
            }
        }

        // Update requisition status
        $stmtReq = $db->prepare("
            UPDATE recruitment_requisitions 
            SET status = :status, updated_at = CURRENT_TIMESTAMP 
            WHERE requisition_id = :req_id
        ");
        $stmtReq->execute([
            ':status' => $newRequisitionStatus,
            ':req_id' => $requisitionId
        ]);

        return [
            'success'               => true,
            'actioned_step'         => $currentStep['step_number'],
            'decision'              => $decision,
            'requisition_status'    => $newRequisitionStatus
        ];
    }

    /**
     * Get complete approval history for a requisition.
     */
    public function getApprovalHistory(PDO $db, int $requisitionId): array {
        $stmt = $db->prepare("
            SELECT a.approval_id, a.step_number, a.approver_role, a.status, a.comments, 
                   a.actioned_at, a.ip_address, u.name as actioned_by_name
            FROM recruitment_requisition_approvals a
            LEFT JOIN users u ON a.actioned_by = u.user_id
            WHERE a.requisition_id = :req_id
            ORDER BY a.step_number ASC
        ");
        $stmt->execute([':req_id' => $requisitionId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
