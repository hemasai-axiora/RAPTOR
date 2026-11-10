<?php
/**
 * Raptor CRM — Create Job Offer Wizard View (Sprint 7)
 * -------------------------------------------------------------------------
 */
?>
<div class="container-fluid py-4">
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php?route=recruitment">Recruitment ATS</a></li>
            <li class="breadcrumb-item"><a href="index.php?route=recruitment_offer/index">Offer Management</a></li>
            <li class="breadcrumb-item active" aria-current="page">Create Offer Proposal</li>
        </ol>
    </nav>

    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent py-3">
                    <h4 class="fw-bold mb-0">Create Job Offer Proposal</h4>
                    <p class="text-muted small mb-0">Construct compensation packages with multi-tier approval chains and automated budget variance escalation.</p>
                </div>
                <div class="card-body p-4">
                    <form method="POST" action="index.php?route=recruitment_offer/store">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

                        <!-- Position & Candidate Alignment -->
                        <h6 class="fw-bold text-primary mb-3">1. Candidate & Position Alignment</h6>
                        <div class="row g-3 mb-4">
                            <div class="col-md-12">
                                <label class="form-label small fw-semibold">Candidate Application <span class="text-danger">*</span></label>
                                <select name="application_id" class="form-select" required>
                                    <option value="">Select Candidate Application...</option>
                                    <?php foreach ($eligible_apps as $app): ?>
                                        <option value="<?= (int)$app['application_id'] ?>">
                                            <?= htmlspecialchars($app['first_name'] . ' ' . $app['last_name']) ?> &bull; <?= htmlspecialchars($app['job_title']) ?> (<?= htmlspecialchars($app['application_code']) ?> &bull; Budget: ₹ <?= number_format((float)($app['max_salary'] ?? 0), 2) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Offered Position Title <span class="text-danger">*</span></label>
                                <input type="text" name="job_title" class="form-control" required placeholder="e.g. Senior Software Engineer">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Department <span class="text-danger">*</span></label>
                                <input type="text" name="department" class="form-control" required placeholder="e.g. Engineering">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold">Work Location <span class="text-danger">*</span></label>
                                <input type="text" name="work_location" class="form-control" required placeholder="e.g. Hyderabad Tech Park / Hybrid">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold">Employment Type</label>
                                <select name="employment_type" class="form-select">
                                    <option value="full_time">Full-Time Permanent</option>
                                    <option value="part_time">Part-Time</option>
                                    <option value="contract">Fixed-Term Contract</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold">Reporting Manager</label>
                                <select name="reporting_manager_id" class="form-select">
                                    <option value="">Select Manager...</option>
                                    <?php foreach ($managers as $m): ?>
                                        <option value="<?= (int)$m['user_id'] ?>">
                                            <?= htmlspecialchars($m['name']) ?> (<?= htmlspecialchars($m['role_name']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <!-- Compensation Breakdown -->
                        <h6 class="fw-bold text-primary mb-3">2. Compensation Package Breakdown (INR)</h6>
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Total Annual Cost to Company (CTC) <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white">₹</span>
                                    <input type="number" step="1000" name="annual_ctc" class="form-control form-control-lg fw-bold" required placeholder="e.g. 1500000">
                                </div>
                                <div class="form-text">Offers exceeding requisition budget trigger mandatory executive / CEO approval.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Fixed Base Salary (Annual) <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white">₹</span>
                                    <input type="number" step="1000" name="fixed_salary" class="form-control form-control-lg" required placeholder="e.g. 1300000">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Variable / Performance Pay (Annual)</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white">₹</span>
                                    <input type="number" step="1000" name="variable_pay" class="form-control" value="0.0">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Joining / Sign-On Bonus</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white">₹</span>
                                    <input type="number" step="1000" name="joining_bonus" class="form-control" value="0.0">
                                </div>
                            </div>
                        </div>

                        <!-- Timeline & Key Dates -->
                        <h6 class="fw-bold text-primary mb-3">3. Onboarding & Expiry Terms</h6>
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Proposed Joining Date <span class="text-danger">*</span></label>
                                <input type="date" name="proposed_joining_date" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Offer Expiry Date <span class="text-danger">*</span></label>
                                <input type="date" name="offer_expiry_date" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Probation Period (Months)</label>
                                <input type="number" name="probation_period_months" class="form-control" value="3" min="0" max="12">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Standard Notice Period (Days)</label>
                                <input type="number" name="notice_period_days" class="form-control" value="30" min="0" max="90">
                            </div>
                        </div>

                        <div class="d-flex justify-content-end gap-2">
                            <a href="index.php?route=recruitment_offer/index" class="btn btn-outline-secondary">Cancel</a>
                            <button type="submit" class="btn btn-primary px-4">
                                <i class="bi bi-file-earmark-check me-1"></i> Submit Offer for Approval
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

