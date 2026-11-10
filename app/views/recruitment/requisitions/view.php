<?php
/**
 * Raptor CRM — Job Requisition Details & Approval View (Sprint 2)
 * -----------------------------------------------------------------------------------
 * Design System: Bootstrap 5.3 + Glassmorphic Cards + Approval Timeline
 */
?>

<div class="container-fluid px-4 py-4">
    <!-- Header with Breadcrumbs & Status -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 small">
                    <li class="breadcrumb-item"><a href="index.php?route=recruitment/dashboard" class="text-decoration-none">Recruitment</a></li>
                    <li class="breadcrumb-item"><a href="index.php?route=recruitment/requisitions" class="text-decoration-none">Requisitions</a></li>
                    <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($req['requisition_code']); ?></li>
                </ol>
            </nav>
            <div class="d-flex align-items-center gap-2">
                <h1 class="h3 fw-bold mb-0 text-main"><?php echo htmlspecialchars($req['title']); ?></h1>
                <code class="fs-6"><?php echo htmlspecialchars($req['requisition_code']); ?></code>
            </div>
            <p class="text-muted mb-0">Department: <span class="fw-semibold text-main"><?php echo htmlspecialchars($req['department']); ?></span> &bull; Hiring Manager: <span class="fw-semibold text-main"><?php echo htmlspecialchars($req['hiring_manager_name']); ?></span></p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <?php 
            $st = $req['status'] ?? 'draft';
            $stBadge = match($st) {
                'approved'          => 'bg-success text-white',
                'open'              => 'bg-primary text-white',
                'pending_approval'  => 'bg-warning-subtle text-warning-emphasis border border-warning-subtle',
                'changes_requested' => 'bg-info-subtle text-info-emphasis border border-info-subtle',
                'rejected'          => 'bg-danger-subtle text-danger',
                'closed', 'filled'  => 'bg-secondary text-white',
                default             => 'bg-light text-dark border'
            };
            ?>
            <span class="badge <?php echo $stBadge; ?> text-capitalize px-3 py-2 fs-6">
                Status: <?php echo str_replace('_', ' ', $st); ?>
            </span>
            <a href="index.php?route=recruitment/requisitions" class="btn btn-outline-secondary">
                <i class="fa-solid fa-arrow-left me-1"></i>Back
            </a>
        </div>
    </div>

    <div class="row g-4">
        <!-- Left Column: Position Specs & Details -->
        <div class="col-lg-8">
            <!-- Position Specifications -->
            <div class="card border-0 shadow-sm rounded-4 mb-4 bg-card">
                <div class="card-header bg-transparent border-0 pt-4 pb-2 px-4">
                    <h5 class="fw-bold mb-0 text-main">
                        <i class="fa-solid fa-file-lines text-primary me-2"></i>Job Description & Responsibilities
                    </h5>
                </div>
                <div class="card-body p-4 pt-2">
                    <div class="mb-4">
                        <h6 class="fw-bold text-muted small text-uppercase">Role Summary</h6>
                        <div class="p-3 rounded-3 bg-light-subtle border border-light-subtle text-main" style="white-space: pre-line;">
                            <?php echo htmlspecialchars($req['job_description']); ?>
                        </div>
                    </div>

                    <?php if (!empty($req['responsibilities'])): ?>
                    <div class="mb-4">
                        <h6 class="fw-bold text-muted small text-uppercase">Key Responsibilities</h6>
                        <div class="p-3 rounded-3 bg-light-subtle border border-light-subtle text-main" style="white-space: pre-line;">
                            <?php echo htmlspecialchars($req['responsibilities']); ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($req['required_skills'])): ?>
                    <div class="mb-4">
                        <h6 class="fw-bold text-muted small text-uppercase">Required Skills & Technologies</h6>
                        <div class="p-3 rounded-3 bg-light-subtle border border-light-subtle text-main" style="white-space: pre-line;">
                            <?php echo htmlspecialchars($req['required_skills']); ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($req['business_justification'])): ?>
                    <div>
                        <h6 class="fw-bold text-muted small text-uppercase">Business Justification</h6>
                        <div class="p-3 rounded-3 bg-light-subtle border border-light-subtle text-muted" style="white-space: pre-line;">
                            <?php echo htmlspecialchars($req['business_justification']); ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Parameters Grid -->
            <div class="card border-0 shadow-sm rounded-4 bg-card">
                <div class="card-header bg-transparent border-0 pt-4 pb-2 px-4">
                    <h5 class="fw-bold mb-0 text-main">
                        <i class="fa-solid fa-sliders text-info me-2"></i>Employment Parameters
                    </h5>
                </div>
                <div class="card-body p-4 pt-2">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <span class="text-muted small">Vacancies Authorized</span>
                            <h5 class="fw-bold text-main mt-1"><?php echo (int) $req['vacancies']; ?> Positions</h5>
                        </div>
                        <div class="col-md-4">
                            <span class="text-muted small">Workplace Model</span>
                            <h5 class="fw-bold text-main mt-1 text-capitalize"><?php echo str_replace('_', ' ', $req['workplace_arrangement']); ?></h5>
                        </div>
                        <div class="col-md-4">
                            <span class="text-muted small">Employment Type</span>
                            <h5 class="fw-bold text-main mt-1 text-capitalize"><?php echo str_replace('_', ' ', $req['employment_type']); ?></h5>
                        </div>
                        <div class="col-md-4">
                            <span class="text-muted small">Minimum Experience</span>
                            <h5 class="fw-bold text-main mt-1"><?php echo (float) $req['min_experience_years']; ?> Years</h5>
                        </div>
                        <div class="col-md-4">
                            <span class="text-muted small">Hiring Priority</span>
                            <h5 class="fw-bold text-main mt-1 text-capitalize"><?php echo htmlspecialchars($req['hiring_priority']); ?></h5>
                        </div>
                        <div class="col-md-4">
                            <span class="text-muted small">Target Hire Date</span>
                            <h5 class="fw-bold text-main mt-1">
                                <?php echo !empty($req['target_hire_date']) ? date('M d, Y', strtotime($req['target_hire_date'])) : 'Flexible'; ?>
                            </h5>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column: Approval Chain & Compensation -->
        <div class="col-lg-4">
            <!-- Approval Timeline Card -->
            <div class="card border-0 shadow-sm rounded-4 mb-4 bg-card">
                <div class="card-header bg-transparent border-0 pt-4 pb-2 px-4">
                    <h5 class="fw-bold mb-0 text-main">
                        <i class="fa-solid fa-signature text-success me-2"></i>Approval Workflow Chain
                    </h5>
                </div>
                <div class="card-body p-4 pt-2">
                    <?php if (empty($req['approvals'])): ?>
                        <div class="text-muted text-center py-4">
                            <i class="fa-solid fa-clock-rotate-left fa-2x mb-2 text-secondary opacity-50"></i>
                            <p class="mb-0 small">Requisition has not yet entered formal approval routing.</p>
                        </div>
                    <?php else: ?>
                        <div class="timeline">
                            <?php foreach ($req['approvals'] as $appr): ?>
                            <div class="d-flex gap-3 mb-3 pb-3 border-bottom border-light-subtle">
                                <div class="mt-1">
                                    <?php 
                                    $astat = $appr['status'];
                                    $iconClass = match($astat) {
                                        'approved'          => 'fa-circle-check text-success',
                                        'rejected'          => 'fa-circle-xmark text-danger',
                                        'changes_requested' => 'fa-circle-question text-info',
                                        default             => 'fa-circle text-muted'
                                    };
                                    ?>
                                    <i class="fa-solid <?php echo $iconClass; ?> fs-5"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h6 class="fw-bold mb-0 text-main text-capitalize">
                                            Step <?php echo $appr['step_number']; ?>: <?php echo str_replace('_', ' ', $appr['approver_role']); ?>
                                        </h6>
                                        <span class="badge text-capitalize <?php echo ($astat === 'approved') ? 'bg-success-subtle text-success' : (($astat === 'rejected') ? 'bg-danger-subtle text-danger' : 'bg-secondary-subtle text-secondary'); ?>">
                                            <?php echo str_replace('_', ' ', $astat); ?>
                                        </span>
                                    </div>
                                    <?php if (!empty($appr['actioned_by_name'])): ?>
                                    <div class="text-muted small mt-1">
                                        Actioned by: <span class="fw-medium text-main"><?php echo htmlspecialchars($appr['actioned_by_name']); ?></span>
                                        <?php if (!empty($appr['actioned_at'])): ?>
                                        on <?php echo date('M d, Y h:i A', strtotime($appr['actioned_at'])); ?>
                                        <?php endif; ?>
                                    </div>
                                    <?php endif; ?>
                                    <?php if (!empty($appr['comments'])): ?>
                                    <div class="p-2 rounded bg-light-subtle small mt-2 fst-italic text-main">
                                        "<?php echo htmlspecialchars($appr['comments']); ?>"
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Action Controls -->
                    <div class="mt-3">
                        <?php if (in_array($req['status'], ['draft', 'changes_requested'], true)): ?>
                        <form method="POST" action="index.php?route=recruitment_requisition/submit/<?php echo (int)$req['requisition_id']; ?>">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                            <button type="submit" class="btn btn-primary w-100 py-2 shadow-sm">
                                <i class="fa-solid fa-paper-plane me-2"></i>Submit for Approval
                            </button>
                        </form>
                        <?php elseif ($req['status'] === 'pending_approval' && (Policy::isAdmin() || Policy::isHr() || Policy::isManager())): ?>
                        <button type="button" class="btn btn-success w-100 py-2 shadow-sm" data-bs-toggle="modal" data-bs-target="#approvalModal">
                            <i class="fa-solid fa-gavel me-2"></i>Action Approval Decision
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Compensation Parameters Card -->
            <div class="card border-0 shadow-sm rounded-4 bg-card">
                <div class="card-header bg-transparent border-0 pt-4 pb-2 px-4">
                    <h5 class="fw-bold mb-0 text-main">
                        <i class="fa-solid fa-coins text-warning me-2"></i>Budget & Compensation
                    </h5>
                </div>
                <div class="card-body p-4 pt-2">
                    <?php if (!empty($req['salary_masked'])): ?>
                    <div class="alert alert-light border small text-muted mb-0">
                        <i class="fa-solid fa-shield-halved me-1 text-primary"></i>
                        Compensation details are confidential and masked for your access level.
                    </div>
                    <?php else: ?>
                    <div class="mb-3">
                        <span class="text-muted small">Approved Salary Range</span>
                        <h4 class="fw-bold text-main mt-1">
                            <?php 
                            if (!empty($req['min_salary']) || !empty($req['max_salary'])) {
                                echo '₹ ' . number_format((float)($req['min_salary'] ?? 0)) . ' - ₹ ' . number_format((float)($req['max_salary'] ?? 0));
                            } else {
                                echo 'Negotiable';
                            }
                            ?>
                        </h4>
                    </div>
                    <?php if (!empty($req['budget_allocated'])): ?>
                    <div>
                        <span class="text-muted small">Budget Allocated</span>
                        <h5 class="fw-semibold text-main mt-1">₹ <?php echo number_format((float)$req['budget_allocated']); ?></h5>
                    </div>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Approval Action Modal -->
<div class="modal fade" id="approvalModal" tabindex="-1" aria-labelledby="approvalModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow rounded-4 bg-card">
            <form method="POST" action="index.php?route=recruitment_requisition/approve/<?php echo (int)$req['requisition_id']; ?>">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <input type="hidden" name="requisition_id" value="<?php echo (int)$req['requisition_id']; ?>">

                <div class="modal-header border-0 pb-0 pt-4 px-4">
                    <h5 class="modal-title fw-bold text-main" id="approvalModalLabel">Action Requisition Approval</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <p class="text-muted small mb-3">Record your formal decision for Requisition <code><?php echo htmlspecialchars($req['requisition_code']); ?></code>.</p>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Decision <span class="text-danger">*</span></label>
                        <div class="d-flex gap-3">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="decision" id="decApprove" value="approved" checked>
                                <label class="form-check-label text-success fw-semibold" for="decApprove">
                                    <i class="fa-solid fa-check me-1"></i>Approve
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="decision" id="decChanges" value="changes_requested">
                                <label class="form-check-label text-info fw-semibold" for="decChanges">
                                    <i class="fa-solid fa-rotate-left me-1"></i>Request Changes
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="decision" id="decReject" value="rejected">
                                <label class="form-check-label text-danger fw-semibold" for="decReject">
                                    <i class="fa-solid fa-xmark me-1"></i>Reject
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Comments & Review Notes</label>
                        <textarea name="comments" class="form-control" rows="3" placeholder="Add specific notes, justification, or required modifications..."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0 pb-4 px-4">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4">Submit Decision</button>
                </div>
            </form>
        </div>
    </div>
</div>

