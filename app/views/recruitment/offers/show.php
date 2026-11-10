<?php
/**
 * Raptor CRM — Offer 360 & Approval Timeline View (Sprint 7)
 * -------------------------------------------------------------------------
 */
?>
<div class="container-fluid py-4">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php?route=recruitment">Recruitment ATS</a></li>
            <li class="breadcrumb-item"><a href="index.php?route=recruitment_offer/index">Offer Management</a></li>
            <li class="breadcrumb-item active" aria-current="page"><?= htmlspecialchars($offer['offer_code']) ?></li>
        </ol>
    </nav>

    <!-- Header Summary Card -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-4">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                <div>
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <h2 class="h3 fw-bold mb-0"><?= htmlspecialchars($offer['job_title']) ?></h2>
                        <span class="badge bg-light text-dark border"><?= htmlspecialchars($offer['offer_code']) ?></span>
                        <?php 
                        $stClass = match($offer['status']) {
                            'accepted'          => 'bg-success',
                            'approved'          => 'bg-primary',
                            'released'          => 'bg-info',
                            'declined'          => 'bg-danger',
                            'changes_requested' => 'bg-warning text-dark',
                            default             => 'bg-secondary'
                        };
                        ?>
                        <span class="badge <?= $stClass ?> text-capitalize"><?= htmlspecialchars(str_replace('_', ' ', $offer['status'])) ?></span>
                    </div>
                    <p class="text-muted mb-2">
                        Candidate: <a href="index.php?route=recruitment_candidate/show/<?= (int)$offer['candidate_id'] ?>" class="fw-bold text-dark text-decoration-none">
                            <?= htmlspecialchars($offer['first_name'] . ' ' . $offer['last_name']) ?>
                        </a>
                        (<?= htmlspecialchars($offer['candidate_code']) ?>) &bull; Department: <span class="fw-semibold text-dark"><?= htmlspecialchars($offer['department']) ?></span>
                        &bull; Location: <?= htmlspecialchars($offer['work_location']) ?>
                    </p>
                    <div class="small text-muted">
                        Proposed Joining: <strong class="text-dark"><?= date('d M Y', strtotime($offer['proposed_joining_date'])) ?></strong>
                        &bull; Offer Expiry: <strong class="text-danger"><?= date('d M Y', strtotime($offer['offer_expiry_date'])) ?></strong>
                        &bull; Prepared by: <?= htmlspecialchars($offer['creator_name'] ?: 'Recruiter') ?>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="d-flex flex-column align-items-end gap-2">
                    <div class="d-flex gap-2">
                        <?php if ($offer['status'] === 'pending_approval'): ?>
                            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#actionApprovalModal">
                                <i class="bi bi-shield-check me-1"></i> Action Approval Step
                            </button>
                        <?php elseif ($offer['status'] === 'approved'): ?>
                            <form method="POST" action="index.php?route=recruitment_offer/release">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                <input type="hidden" name="offer_id" value="<?= (int)$offer['offer_id'] ?>">
                                <button type="submit" class="btn btn-success btn-sm">
                                    <i class="bi bi-send me-1"></i> Release Offer to Candidate
                                </button>
                            </form>
                        <?php elseif ($offer['status'] === 'released'): ?>
                            <button type="button" class="btn btn-outline-success btn-sm" data-bs-toggle="modal" data-bs-target="#candidateResponseModal">
                                <i class="bi bi-check-circle me-1"></i> Record Candidate Response
                            </button>
                        <?php endif; ?>
                    </div>

                    <!-- Salary Masking / Summary Card -->
                    <div class="card bg-light border p-2 text-end" style="min-width: 220px;">
                        <div class="small text-muted fw-semibold">Offered Annual CTC</div>
                        <?php if (!empty($offer['is_salary_masked'])): ?>
                            <div class="text-secondary small fst-italic">
                                <i class="bi bi-lock-fill text-warning me-1"></i> Restricted (HR Only)
                            </div>
                        <?php else: ?>
                            <div class="h5 fw-bold text-dark mb-0">₹ <?= number_format((float)($offer['annual_ctc'] ?? 0), 2) ?></div>
                            <div class="small text-muted">Fixed: ₹ <?= number_format((float)($offer['fixed_salary'] ?? 0), 2) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Left: Approval Chain Timeline & Breakdown -->
        <div class="col-lg-5">
            <!-- Approval Workflow Card -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-transparent py-3">
                    <h5 class="fw-bold mb-0"><i class="bi bi-diagram-3 text-primary me-2"></i> Approval Chain Progress</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($offer['approvals'])): ?>
                        <div class="text-muted small py-3">No approval steps required.</div>
                    <?php else: ?>
                        <div class="timeline">
                            <?php foreach ($offer['approvals'] as $st): ?>
                                <div class="border-start border-3 ps-3 mb-3 <?= $st['status'] === 'approved' ? 'border-success' : ($st['status'] === 'rejected' ? 'border-danger' : 'border-secondary') ?>">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="fw-bold text-dark">
                                            Step <?= (int)$st['step_number'] ?>: <?= ucwords(str_replace('_', ' ', $st['approver_role'])) ?>
                                        </span>
                                        <?php 
                                        $bClass = match($st['status']) {
                                            'approved'          => 'bg-success',
                                            'rejected'          => 'bg-danger',
                                            'changes_requested' => 'bg-warning text-dark',
                                            default             => 'bg-secondary'
                                        };
                                        ?>
                                        <span class="badge <?= $bClass ?> text-capitalize"><?= htmlspecialchars($st['status']) ?></span>
                                    </div>
                                    <?php if ($st['actioned_by_name']): ?>
                                        <div class="small text-muted">Actioned by <?= htmlspecialchars($st['actioned_by_name']) ?> on <?= htmlspecialchars($st['actioned_at']) ?></div>
                                    <?php endif; ?>
                                    <?php if ($st['comments']): ?>
                                        <div class="small fst-italic text-secondary mt-1">"<?= htmlspecialchars($st['comments']) ?>"</div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Detailed Compensation Breakdown -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent py-3">
                    <h5 class="fw-bold mb-0"><i class="bi bi-cash-stack text-success me-2"></i> Compensation Breakdown</h5>
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0">
                        <tbody>
                            <tr>
                                <td class="ps-3 py-2 text-muted">Fixed Base Salary:</td>
                                <td class="pe-3 py-2 text-end fw-semibold">
                                    <?= !empty($offer['is_salary_masked']) ? '[RESTRICTED]' : '₹ ' . number_format((float)($offer['fixed_salary'] ?? 0), 2) ?>
                                </td>
                            </tr>
                            <tr>
                                <td class="ps-3 py-2 text-muted">Variable / Performance Pay:</td>
                                <td class="pe-3 py-2 text-end fw-semibold">
                                    <?= !empty($offer['is_salary_masked']) ? '[RESTRICTED]' : '₹ ' . number_format((float)($offer['variable_pay'] ?? 0), 2) ?>
                                </td>
                            </tr>
                            <tr>
                                <td class="ps-3 py-2 text-muted">Joining / Sign-On Bonus:</td>
                                <td class="pe-3 py-2 text-end fw-semibold">
                                    <?= !empty($offer['is_salary_masked']) ? '[RESTRICTED]' : '₹ ' . number_format((float)($offer['joining_bonus'] ?? 0), 2) ?>
                                </td>
                            </tr>
                            <tr class="table-light">
                                <td class="ps-3 py-2 fw-bold text-dark">Total Annual CTC:</td>
                                <td class="pe-3 py-2 text-end fw-bold text-primary">
                                    <?= !empty($offer['is_salary_masked']) ? '[RESTRICTED]' : '₹ ' . number_format((float)($offer['annual_ctc'] ?? 0), 2) ?>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Right: Official Offer Letter Preview -->
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent py-3 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0"><i class="bi bi-file-earmark-text text-secondary me-2"></i> Official Offer Letter Preview</h5>
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.print()">
                        <i class="bi bi-printer me-1"></i> Print / Save PDF
                    </button>
                </div>
                <div class="card-body p-4 bg-light">
                    <?= $letter_html ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Action Approval Modal -->
<div class="modal fade" id="actionApprovalModal" tabindex="-1" aria-labelledby="approvalModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="index.php?route=recruitment_offer/approve">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <input type="hidden" name="offer_id" value="<?= (int)$offer['offer_id'] ?>">

            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="approvalModalLabel">
                        <i class="bi bi-shield-check text-primary me-2"></i> Action Offer Approval
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Approval Decision</label>
                        <select name="decision" class="form-select" required>
                            <option value="approved">Approve Offer Proposal</option>
                            <option value="changes_requested">Request Changes / Revised Terms</option>
                            <option value="rejected">Reject Offer Proposal</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Review Comments</label>
                        <textarea name="comments" class="form-control" rows="3" placeholder="Approval justification, requested salary changes, or remarks..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Submit Decision</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Candidate Response Modal -->
<div class="modal fade" id="candidateResponseModal" tabindex="-1" aria-labelledby="responseModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="index.php?route=recruitment_offer/respond">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <input type="hidden" name="offer_id" value="<?= (int)$offer['offer_id'] ?>">

            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="responseModalLabel">
                        <i class="bi bi-check2-circle text-success me-2"></i> Record Candidate Response
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Candidate Decision</label>
                        <select name="decision" class="form-select" required>
                            <option value="accepted">Accepted (Proceed to BGV & Onboarding)</option>
                            <option value="declined">Declined (Offer Refused by Candidate)</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Notes / Reason (if declined)</label>
                        <textarea name="reason" class="form-control" rows="3" placeholder="Candidate feedback, competing offer, or negotiation remarks..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Response</button>
                </div>
            </div>
        </form>
    </div>
</div>

