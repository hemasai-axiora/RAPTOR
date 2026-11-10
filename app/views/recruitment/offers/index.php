<?php
/**
 * Raptor CRM — Offer Management Directory View (Sprint 7)
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
                    <li class="breadcrumb-item active" aria-current="page">Offer Management</li>
                </ol>
            </nav>
            <h2 class="h3 fw-bold mb-0">Offer Management & Compensation Approvals</h2>
            <p class="text-muted small mb-0">Manage compensation packages, multi-step approval workflows, and versioned offer releases.</p>
        </div>

        <div class="d-flex gap-2">
            <a href="index.php?route=recruitment_application/index" class="btn btn-outline-primary">
                <i class="bi bi-kanban me-1"></i> ATS Kanban
            </a>
            <a href="index.php?route=recruitment_offer/create" class="btn btn-primary">
                <i class="bi bi-file-earmark-plus me-1"></i> Create Offer Proposal
            </a>
        </div>
    </div>

    <!-- Filter Toolbar -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" action="index.php" class="row g-3 align-items-end">
                <input type="hidden" name="route" value="recruitment_offer/index">

                <div class="col-md-4">
                    <label class="form-label small fw-semibold">Offer Status</label>
                    <select name="status" class="form-select">
                        <option value="">All Statuses</option>
                        <option value="pending_approval" <?= ($status_filter ?? '') === 'pending_approval' ? 'selected' : '' ?>>Pending Approval</option>
                        <option value="approved" <?= ($status_filter ?? '') === 'approved' ? 'selected' : '' ?>>Approved (Ready to Release)</option>
                        <option value="released" <?= ($status_filter ?? '') === 'released' ? 'selected' : '' ?>>Released to Candidate</option>
                        <option value="accepted" <?= ($status_filter ?? '') === 'accepted' ? 'selected' : '' ?>>Accepted</option>
                        <option value="declined" <?= ($status_filter ?? '') === 'declined' ? 'selected' : '' ?>>Declined</option>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label small fw-semibold">Department</label>
                    <input type="text" name="department" class="form-control" placeholder="e.g. Engineering, Sales" value="<?= htmlspecialchars($dept_filter ?? '') ?>">
                </div>

                <div class="col-md-4 d-flex gap-2">
                    <button type="submit" class="btn btn-dark w-100"><i class="bi bi-funnel me-1"></i> Filter Offers</button>
                    <a href="index.php?route=recruitment_offer/index" class="btn btn-outline-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Offers List -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-transparent border-0 d-flex justify-content-between align-items-center py-3">
            <h5 class="card-title fw-bold mb-0">Total Offers: <span class="badge bg-secondary"><?= count($offers) ?></span></h5>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">Offer Code</th>
                        <th>Candidate</th>
                        <th>Position & Dept</th>
                        <th>Annual CTC</th>
                        <th>Proposed Joining</th>
                        <th>Status</th>
                        <th class="text-end pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($offers)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="bi bi-file-earmark-medical display-6 d-block mb-2"></i>
                                No job offers generated yet.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($offers as $o): ?>
                            <tr>
                                <td class="ps-4 fw-bold">
                                    <a href="index.php?route=recruitment_offer/show/<?= $o['offer_id'] ?>" class="text-decoration-none">
                                        <?= htmlspecialchars($o['offer_code']) ?>
                                    </a>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark"><?= htmlspecialchars($o['first_name'] . ' ' . $o['last_name']) ?></div>
                                    <div class="small text-muted"><?= htmlspecialchars($o['candidate_code']) ?></div>
                                </td>
                                <td>
                                    <div class="fw-semibold"><?= htmlspecialchars($o['job_title']) ?></div>
                                    <div class="small text-muted"><?= htmlspecialchars($o['department']) ?></div>
                                </td>
                                <td>
                                    <?php if (!empty($o['is_salary_masked'])): ?>
                                        <span class="badge bg-light text-secondary border">
                                            <i class="bi bi-lock-fill text-warning me-1"></i> Restricted (HR)
                                        </span>
                                    <?php else: ?>
                                        <span class="fw-bold text-dark">
                                            ₹ <?= number_format((float)($o['annual_ctc'] ?? 0), 2) ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="small fw-semibold"><?= date('d M Y', strtotime($o['proposed_joining_date'])) ?></div>
                                    <div class="small text-muted">Expires: <?= date('d M Y', strtotime($o['offer_expiry_date'])) ?></div>
                                </td>
                                <td>
                                    <?php 
                                    $stClass = match($o['status']) {
                                        'accepted'          => 'bg-success',
                                        'approved'          => 'bg-primary',
                                        'released'          => 'bg-info',
                                        'declined'          => 'bg-danger',
                                        'changes_requested' => 'bg-warning text-dark',
                                        default             => 'bg-secondary'
                                    };
                                    ?>
                                    <span class="badge <?= $stClass ?> text-capitalize">
                                        <?= htmlspecialchars(str_replace('_', ' ', $o['status'])) ?>
                                    </span>
                                </td>
                                <td class="text-end pe-4">
                                    <a href="index.php?route=recruitment_offer/show/<?= $o['offer_id'] ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-eye me-1"></i> View Offer 360
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

