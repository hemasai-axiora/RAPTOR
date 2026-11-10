<?php
/**
 * Raptor CRM — Recruitment Analytics Dashboard View (Sprint 10)
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
                    <li class="breadcrumb-item active" aria-current="page">Analytics & Funnel Velocity</li>
                </ol>
            </nav>
            <h2 class="h3 fw-bold mb-0">Recruitment Intelligence & Funnel Analytics</h2>
            <p class="text-muted small mb-0">Executive visibility into hiring velocity, candidate drop-off, sourcing channel ROI, and offer conversion.</p>
        </div>

        <div class="d-flex gap-2">
            <a href="index.php?route=recruitment_analytics/exportCsv" class="btn btn-outline-success">
                <i class="bi bi-file-earmark-spreadsheet me-1"></i> Export Sourcing CSV
            </a>
            <a href="index.php?route=recruitment_application/index" class="btn btn-outline-primary">
                <i class="bi bi-kanban me-1"></i> Active Pipeline
            </a>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body py-3">
            <form method="GET" action="index.php" class="row g-3 align-items-center">
                <input type="hidden" name="route" value="recruitment_analytics/index">
                <div class="col-auto">
                    <label class="col-form-label small fw-semibold text-muted">Filter by Department:</label>
                </div>
                <div class="col-md-4">
                    <select name="department" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">All Departments (Entire Organization)</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?= htmlspecialchars($dept) ?>" <?= ($selectedDept === $dept) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($dept) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if (!empty($selectedDept)): ?>
                    <div class="col-auto">
                        <a href="index.php?route=recruitment_analytics/index" class="btn btn-sm btn-link text-decoration-none">Clear Filter</a>
                    </div>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- KPI Metric Tiles -->
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted small text-uppercase fw-bold">Avg Time-to-Hire</span>
                        <div class="badge bg-primary-subtle text-primary p-2 rounded-circle">
                            <i class="bi bi-stopwatch fs-6"></i>
                        </div>
                    </div>
                    <h3 class="fw-bold mb-1"><?= number_format($kpis['avg_days_to_hire'], 1) ?> <small class="text-muted fs-6">days</small></h3>
                    <div class="small text-muted">
                        Target benchmark: <strong>30.0 days</strong>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted small text-uppercase fw-bold">Funnel Conversion</span>
                        <div class="badge bg-success-subtle text-success p-2 rounded-circle">
                            <i class="bi bi-funnel fs-6"></i>
                        </div>
                    </div>
                    <h3 class="fw-bold mb-1"><?= number_format($kpis['funnel_conversion_rate'], 1) ?>%</h3>
                    <div class="small text-muted">
                        From <strong><?= number_format($kpis['total_applications']) ?></strong> total applications
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted small text-uppercase fw-bold">Total Hires</span>
                        <div class="badge bg-info-subtle text-info p-2 rounded-circle">
                            <i class="bi bi-person-check fs-6"></i>
                        </div>
                    </div>
                    <h3 class="fw-bold mb-1"><?= number_format($kpis['total_hires']) ?></h3>
                    <div class="small text-muted">
                        Verified active employees in HRMS
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted small text-uppercase fw-bold">Offer Acceptance</span>
                        <div class="badge bg-warning-subtle text-warning p-2 rounded-circle">
                            <i class="bi bi-award fs-6"></i>
                        </div>
                    </div>
                    <h3 class="fw-bold mb-1"><?= number_format($kpis['offer_acceptance_rate'], 1) ?>%</h3>
                    <div class="small text-muted">
                        Ratio of accepted vs formal offers
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ATS Funnel & Sourcing ROI Row -->
    <div class="row g-4 mb-4">
        <!-- ATS Pipeline Funnel Breakdown -->
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-transparent py-3">
                    <h5 class="fw-bold mb-0">Hiring Pipeline Funnel Velocity</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4">Pipeline Stage</th>
                                    <th>Candidates</th>
                                    <th>% of Total Pool</th>
                                    <th>Step Conversion</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($funnel['funnel_stages'] as $stg): ?>
                                    <tr>
                                        <td class="ps-4">
                                            <span class="fw-semibold"><?= htmlspecialchars($stg['stage_label']) ?></span>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark border px-2 py-1 fs-6">
                                                <?= number_format($stg['candidate_count']) ?>
                                            </span>
                                        </td>
                                        <td style="width: 30%;">
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="progress flex-grow-1" style="height: 6px;">
                                                    <div class="progress-bar bg-primary" role="progressbar" style="width: <?= $stg['pct_of_total'] ?>%"></div>
                                                </div>
                                                <small class="text-muted fw-bold"><?= $stg['pct_of_total'] ?>%</small>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="text-<?= ($stg['step_conversion'] >= 50.0) ? 'success' : 'secondary' ?> small fw-semibold">
                                                <?= $stg['step_conversion'] ?>%
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sourcing Channel Performance -->
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-transparent py-3 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0">Sourcing Channel ROI</h5>
                    <span class="badge bg-secondary-subtle text-secondary small">Volume vs Hires</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3">Source Channel</th>
                                    <th>Apps</th>
                                    <th>Hires</th>
                                    <th class="text-end pe-3">Conversion</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($sourcing)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center py-4 text-muted small">No candidate sourcing data recorded yet.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($sourcing as $src): ?>
                                        <tr>
                                            <td class="ps-3 fw-semibold">
                                                <i class="bi bi-box-arrow-in-right text-primary me-1"></i>
                                                <?= htmlspecialchars($src['channel']) ?>
                                            </td>
                                            <td><?= number_format($src['applications_count']) ?></td>
                                            <td><span class="badge bg-success-subtle text-success"><?= number_format($src['hires_count']) ?></span></td>
                                            <td class="text-end pe-3 fw-bold text-dark"><?= $src['conversion_rate'] ?>%</td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

