<?php
/**
 * Raptor CRM — Add Candidate Intake Form (Sprint 4)
 * -------------------------------------------------------------------------
 */
?>
<div class="container-fluid py-4">
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php?route=recruitment">Recruitment ATS</a></li>
            <li class="breadcrumb-item"><a href="index.php?route=recruitment_candidate/index">Candidate Directory</a></li>
            <li class="breadcrumb-item active" aria-current="page">Manual Candidate Intake</li>
        </ol>
    </nav>

    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent py-3">
                    <h4 class="fw-bold mb-0">Add Candidate to Talent Master</h4>
                    <p class="text-muted small mb-0">Direct manual intake for referral, sourced, or agency candidates with automated deduplication.</p>
                </div>
                <div class="card-body p-4">
                    <form method="POST" action="index.php?route=recruitment_candidate/store" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

                        <!-- Basic Information -->
                        <h6 class="fw-bold text-primary mb-3">1. Personal Information</h6>
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">First Name <span class="text-danger">*</span></label>
                                <input type="text" name="first_name" class="form-control" required placeholder="e.g. Rahul">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Last Name <span class="text-danger">*</span></label>
                                <input type="text" name="last_name" class="form-control" required placeholder="e.g. Verma">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Email Address <span class="text-danger">*</span></label>
                                <input type="email" name="email" class="form-control" required placeholder="rahul.verma@example.com">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Phone Number <span class="text-danger">*</span></label>
                                <input type="tel" name="phone" class="form-control" required placeholder="+91 98765 43210">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">City</label>
                                <input type="text" name="city" class="form-control" placeholder="e.g. Bengaluru">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Country</label>
                                <input type="text" name="country" class="form-control" value="India">
                            </div>
                        </div>

                        <!-- Professional Background -->
                        <h6 class="fw-bold text-primary mb-3">2. Professional Profile</h6>
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Current Company</label>
                                <input type="text" name="current_company" class="form-control" placeholder="e.g. Infosys Ltd">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Current Designation</label>
                                <input type="text" name="current_designation" class="form-control" placeholder="e.g. Senior Software Engineer">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold">Total Experience (Years)</label>
                                <input type="number" step="0.5" min="0" name="total_experience_years" class="form-control" value="0.0">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold">Notice Period (Days)</label>
                                <input type="number" name="notice_period_days" class="form-control" placeholder="e.g. 30">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold">Highest Education</label>
                                <input type="text" name="highest_education" class="form-control" placeholder="e.g. B.Tech Computer Science">
                            </div>
                            <div class="col-md-12">
                                <label class="form-label small fw-semibold">Primary Skills (comma separated)</label>
                                <input type="text" name="primary_skills" class="form-control" placeholder="PHP 8.2, MySQL, Redis, AWS, Docker">
                            </div>
                        </div>

                        <!-- Compensation & Confidential Details -->
                        <h6 class="fw-bold text-primary mb-3">3. Compensation (HR Protected)</h6>
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Current Annual CTC (₹)</label>
                                <input type="number" step="1000" name="current_ctc" class="form-control" placeholder="e.g. 1200000">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Expected Annual CTC (₹)</label>
                                <input type="number" step="1000" name="expected_ctc" class="form-control" placeholder="e.g. 1500000">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Candidate Source</label>
                                <select name="source" class="form-select">
                                    <option value="direct">Direct Sourcing</option>
                                    <option value="linkedin">LinkedIn Recruiter</option>
                                    <option value="referral">Employee Referral</option>
                                    <option value="agency">External Agency</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Source Detail / Referrer Name</label>
                                <input type="text" name="source_detail" class="form-control" placeholder="e.g. Referred by Alex Vance">
                            </div>
                        </div>

                        <!-- Document Attachment -->
                        <h6 class="fw-bold text-primary mb-3">4. Resume / CV Attachment</h6>
                        <div class="row g-3 mb-4">
                            <div class="col-md-12">
                                <label class="form-label small fw-semibold">Upload Candidate Resume (PDF, DOCX)</label>
                                <input type="file" name="resume" class="form-control" accept=".pdf,.doc,.docx">
                                <div class="form-text">Stored securely in private authenticated storage.</div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-end gap-2">
                            <a href="index.php?route=recruitment_candidate/index" class="btn btn-outline-secondary">Cancel</a>
                            <button type="submit" class="btn btn-primary px-4">
                                <i class="bi bi-check-circle me-1"></i> Save Candidate Profile
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

