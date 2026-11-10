<?php
/**
 * Raptor CRM — 1-Click Employee Conversion Confirmation Gate View (Sprint 8)
 * -------------------------------------------------------------------------
 */
?>
<div class="container-fluid py-4">
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php?route=recruitment">Recruitment ATS</a></li>
            <li class="breadcrumb-item"><a href="index.php?route=recruitment_onboarding/index">Onboarding & Conversion</a></li>
            <li class="breadcrumb-item active" aria-current="page">Convert to Permanent Employee</li>
        </ol>
    </nav>

    <!-- Confirmation Warning Gate Banner -->
    <div class="alert alert-warning border-warning d-flex align-items-center mb-4 shadow-sm" role="alert">
        <i class="bi bi-shield-exclamation fs-3 text-warning me-3"></i>
        <div>
            <h5 class="alert-heading fw-bold mb-1">CONFIRMATION GATE: Permanent Employee Conversion</h5>
            <p class="mb-0 small">
                This transaction creates a verified, active employee record in Raptor HRMS with automated user account association,
                marks the candidate as <strong>Hired</strong> in ATS pipeline, and triggers onboarding workflows.
            </p>
        </div>
    </div>

    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent py-3">
                    <h4 class="fw-bold mb-0">Convert Accepted Candidate to Permanent Employee</h4>
                    <p class="text-muted small mb-0">
                        Candidate: <strong><?= htmlspecialchars($candidate['first_name'] . ' ' . $candidate['last_name']) ?></strong> (<?= htmlspecialchars($candidate['candidate_code']) ?>)
                        &bull; Offer: <strong><?= htmlspecialchars($offer['offer_code']) ?></strong>
                        &bull; Proposed Joining: <strong><?= htmlspecialchars($offer['proposed_joining_date']) ?></strong>
                    </p>
                </div>
                <div class="card-body p-4">
                    <form method="POST" action="index.php?route=recruitment_onboarding/executeConversion">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                        <input type="hidden" name="application_id" value="<?= (int)$application['application_id'] ?>">

                        <h6 class="fw-bold text-primary mb-3">Mapped Employee Profile Fields</h6>
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Full Legal Name</label>
                                <input type="text" class="form-control bg-light" readonly value="<?= htmlspecialchars($candidate['first_name'] . ' ' . $candidate['last_name']) ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Corporate / Primary Email</label>
                                <input type="email" class="form-control bg-light" readonly value="<?= htmlspecialchars($candidate['email']) ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Employee Reference Code <span class="text-danger">*</span></label>
                                <input type="text" name="employee_code" class="form-control fw-bold" required value="<?= htmlspecialchars($suggested_code) ?>">
                                <div class="form-text">Auto-generated standard sequence. Can be customized if enterprise prefix applies.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Official Designation <span class="text-danger">*</span></label>
                                <input type="text" name="job_title" class="form-control" required value="<?= htmlspecialchars($offer['job_title']) ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Department <span class="text-danger">*</span></label>
                                <input type="text" name="department" class="form-control" required value="<?= htmlspecialchars($offer['department']) ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Work Location <span class="text-danger">*</span></label>
                                <input type="text" name="work_location" class="form-control" required value="<?= htmlspecialchars($offer['work_location']) ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Employment Type</label>
                                <select name="employment_type" class="form-select">
                                    <option value="full_time" <?= $offer['employment_type'] === 'full_time' ? 'selected' : '' ?>>Full-Time Permanent</option>
                                    <option value="part_time" <?= $offer['employment_type'] === 'part_time' ? 'selected' : '' ?>>Part-Time</option>
                                    <option value="contract" <?= $offer['employment_type'] === 'contract' ? 'selected' : '' ?>>Contract</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Effective Date of Joining <span class="text-danger">*</span></label>
                                <input type="date" name="date_of_joining" class="form-control fw-bold text-primary" required value="<?= htmlspecialchars($offer['proposed_joining_date']) ?>">
                            </div>
                            <div class="col-md-12">
                                <div class="card bg-light border p-3">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <span class="small text-muted fw-semibold d-block">Annual Compensation (CTC)</span>
                                            <span class="h4 fw-bold text-success mb-0">₹ <?= number_format((float)($offer['annual_ctc'] ?? 0), 2) ?></span>
                                            <span class="small text-muted ms-2">(Fixed: ₹ <?= number_format((float)($offer['fixed_salary'] ?? 0), 2) ?>)</span>
                                        </div>
                                        <div class="badge bg-success-subtle text-success border border-success-subtle p-2">
                                            <i class="bi bi-check-circle me-1"></i> Pre-Approved in Offer
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center pt-3 border-top">
                            <a href="index.php?route=recruitment_onboarding/index" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-left me-1"></i> Cancel / Return
                            </a>
                            <button type="submit" class="btn btn-primary px-4 py-2 fw-bold">
                                <i class="bi bi-person-check-fill me-1"></i> CONFIRM & CONVERT TO EMPLOYEE
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

