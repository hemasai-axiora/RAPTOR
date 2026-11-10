<?php
/**
 * Raptor CRM — Onboarding & Employee Conversion Dashboard (Sprint 8)
 * -------------------------------------------------------------------------
 */
?>
<div class="container-fluid py-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="index.php?route=recruitment">Recruitment ATS</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Onboarding & Conversion</li>
                </ol>
            </nav>
            <h2 class="h3 fw-bold mb-0">Pre-Joining, BGV & Employee Conversion</h2>
            <p class="text-muted small mb-0">Oversee background verification, pre-onboarding document verification, and 1-click employee conversion.</p>
        </div>

        <div class="d-flex gap-2">
            <a href="index.php?route=recruitment_offer/index" class="btn btn-outline-primary">
                <i class="bi bi-file-earmark-check me-1"></i> Offers Directory
            </a>
            <a href="index.php?route=recruitment_application/index" class="btn btn-outline-secondary">
                <i class="bi bi-kanban me-1"></i> ATS Kanban
            </a>
        </div>
    </div>

    <!-- Candidate Onboarding Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-transparent py-3">
            <h5 class="fw-bold mb-0">Active Onboarding Candidates (<?= count($candidates) ?>)</h5>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">Candidate</th>
                        <th>Position & Dept</th>
                        <th>Offer Details</th>
                        <th>Joining Date</th>
                        <th>Current ATS Stage</th>
                        <th class="text-end pe-4">HRMS Conversion Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($candidates)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="bi bi-person-check display-6 d-block mb-2"></i>
                                No candidates currently in onboarding or conversion pipeline.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($candidates as $c): ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="fw-bold text-dark"><?= htmlspecialchars($c['first_name'] . ' ' . $c['last_name']) ?></div>
                                    <div class="small text-muted"><?= htmlspecialchars($c['candidate_code']) ?> &bull; <?= htmlspecialchars($c['email']) ?></div>
                                </td>
                                <td>
                                    <div class="fw-semibold"><?= htmlspecialchars($c['job_title']) ?></div>
                                    <div class="small text-muted"><?= htmlspecialchars($c['department']) ?></div>
                                </td>
                                <td>
                                    <?php if (!empty($c['offer_code'])): ?>
                                        <span class="badge bg-light text-dark border"><?= htmlspecialchars($c['offer_code']) ?></span>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle text-capitalize ms-1">
                                            <?= htmlspecialchars($c['offer_status'] ?? 'accepted') ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted small">No Offer</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($c['proposed_joining_date'])): ?>
                                        <div class="small fw-bold text-dark"><?= date('d M Y', strtotime($c['proposed_joining_date'])) ?></div>
                                    <?php else: ?>
                                        <span class="text-muted small fst-italic">Pending</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php 
                                    $stClass = match($c['current_stage']) {
                                        'hired'          => 'bg-success',
                                        'offer_accepted' => 'bg-primary',
                                        'bgv'            => 'bg-info',
                                        'pre_joining'    => 'bg-warning text-dark',
                                        default          => 'bg-secondary'
                                    };
                                    ?>
                                    <span class="badge <?= $stClass ?> text-capitalize fs-6">
                                        <?= htmlspecialchars(str_replace('_', ' ', $c['current_stage'])) ?>
                                    </span>
                                </td>
                                <td class="text-end pe-4">
                                    <?php if ($c['current_stage'] === 'hired' || !empty($c['converted_employee_id'])): ?>
                                        <span class="badge bg-success fs-6">
                                            <i class="bi bi-check-all me-1"></i> Converted (Emp #<?= (int)$c['converted_employee_id'] ?>)
                                        </span>
                                    <?php elseif ($c['current_stage'] === 'offer_accepted' || $c['current_stage'] === 'pre_joining' || $c['current_stage'] === 'bgv'): ?>
                                        <a href="index.php?route=recruitment_onboarding/convert/<?= (int)$c['application_id'] ?>" class="btn btn-sm btn-primary">
                                            <i class="bi bi-person-plus-fill me-1"></i> Convert to Employee
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted small">Awaiting Offer Acceptance</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

