<?php
/**
 * Raptor CRM — Candidate 360 Profile View (Sprint 4)
 * -------------------------------------------------------------------------
 */
?>
<div class="container-fluid py-4">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php?route=recruitment">Recruitment ATS</a></li>
            <li class="breadcrumb-item"><a href="index.php?route=recruitment_candidate/index">Candidate Directory</a></li>
            <li class="breadcrumb-item active" aria-current="page"><?= htmlspecialchars($candidate['candidate_code']) ?></li>
        </ol>
    </nav>

    <!-- Top Candidate Summary Card -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-4">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                <div class="d-flex align-items-center">
                    <div class="avatar-lg bg-primary text-white rounded-circle d-flex align-items-center justify-content-center fw-bold fs-2 me-4" style="width: 72px; height: 72px;">
                        <?= strtoupper(substr($candidate['first_name'], 0, 1) . substr($candidate['last_name'], 0, 1)) ?>
                    </div>
                    <div>
                        <div class="d-flex align-items-center gap-2">
                            <h2 class="h3 fw-bold mb-0"><?= htmlspecialchars($candidate['first_name'] . ' ' . $candidate['last_name']) ?></h2>
                            <span class="badge bg-light text-secondary border"><?= htmlspecialchars($candidate['candidate_code']) ?></span>
                            <?php if ((int)$candidate['is_blacklisted'] === 1): ?>
                                <span class="badge bg-danger">Blacklisted</span>
                            <?php endif; ?>
                        </div>
                        <p class="text-muted mb-2 fs-6">
                            <?= htmlspecialchars($candidate['current_designation'] ?: 'Applicant') ?> 
                            <?php if (!empty($candidate['current_company'])): ?>
                                &bull; <span class="text-dark fw-semibold"><?= htmlspecialchars($candidate['current_company']) ?></span>
                            <?php endif; ?>
                            &bull; <i class="bi bi-geo-alt"></i> <?= htmlspecialchars($candidate['city'] ?: 'India') ?>
                        </p>
                        <div class="d-flex flex-wrap gap-3 small text-muted">
                            <span><i class="bi bi-envelope me-1"></i><?= htmlspecialchars($candidate['email']) ?></span>
                            <span><i class="bi bi-telephone me-1"></i><?= htmlspecialchars($candidate['phone']) ?></span>
                            <span><i class="bi bi-briefcase me-1"></i><?= (float)$candidate['total_experience_years'] ?> Years Experience</span>
                            <?php if ($candidate['notice_period_days'] !== null): ?>
                                <span><i class="bi bi-clock me-1"></i><?= (int)$candidate['notice_period_days'] ?> Days Notice</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="d-flex flex-column align-items-end gap-2">
                    <div class="d-flex gap-2">
                        <a href="index.php?route=recruitment_application/index" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-kanban me-1"></i> ATS Kanban
                        </a>
                        <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#blacklistModal">
                            <i class="bi bi-slash-circle me-1"></i> <?= (int)$candidate['is_blacklisted'] === 1 ? 'Update Blacklist' : 'Blacklist' ?>
                        </button>
                    </div>

                    <!-- Salary Protection Block -->
                    <div class="card bg-light border p-2 text-end" style="min-width: 220px;">
                        <div class="small text-muted fw-semibold">Compensation Details</div>
                        <?php if (!empty($candidate['is_salary_masked'])): ?>
                            <div class="text-secondary small fst-italic">
                                <i class="bi bi-lock-fill text-warning me-1"></i> Restricted (HR Only)
                            </div>
                        <?php else: ?>
                            <div class="small">Current: <strong>₹ <?= number_format((float)($candidate['current_ctc'] ?? 0), 2) ?></strong></div>
                            <div class="small">Expected: <strong class="text-success">₹ <?= number_format((float)($candidate['expected_ctc'] ?? 0), 2) ?></strong></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Blacklist Alert Banner if applicable -->
    <?php if ((int)$candidate['is_blacklisted'] === 1): ?>
        <div class="alert alert-danger d-flex align-items-center mb-4" role="alert">
            <i class="bi bi-exclamation-triangle-fill fs-4 me-3"></i>
            <div>
                <strong>Candidate Profile is Blacklisted:</strong> <?= htmlspecialchars($candidate['blacklist_reason'] ?? 'Flagged by recruiter.') ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Navigation Tabs -->
    <ul class="nav nav-tabs nav-fill mb-4" id="candidateTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active fw-semibold" id="overview-tab" data-bs-toggle="tab" data-bs-target="#overview" type="button" role="tab">
                <i class="bi bi-person me-1"></i> Overview & Skills
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link fw-semibold" id="applications-tab" data-bs-toggle="tab" data-bs-target="#applications" type="button" role="tab">
                <i class="bi bi-file-earmark-text me-1"></i> Applications (<?= count($candidate['applications']) ?>)
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link fw-semibold" id="documents-tab" data-bs-toggle="tab" data-bs-target="#documents" type="button" role="tab">
                <i class="bi bi-folder2 me-1"></i> Documents & Resumes (<?= count($candidate['documents']) ?>)
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link fw-semibold" id="notes-tab" data-bs-toggle="tab" data-bs-target="#notes" type="button" role="tab">
                <i class="bi bi-chat-left-text me-1"></i> Recruiter Notes (<?= count($candidate['notes']) ?>)
            </button>
        </li>
    </ul>

    <!-- Tab Contents -->
    <div class="tab-content" id="candidateTabsContent">
        <!-- Tab 1: Overview -->
        <div class="tab-pane fade show active" id="overview" role="tabpanel">
            <div class="row g-4">
                <div class="col-md-8">
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-body">
                            <h5 class="fw-bold mb-3">Skills & Capabilities</h5>
                            <div class="mb-3">
                                <label class="small text-muted fw-semibold d-block mb-1">Primary Skills</label>
                                <?php 
                                $pskills = array_filter(array_map('trim', explode(',', (string)$candidate['primary_skills'])));
                                if (!empty($pskills)): 
                                    foreach ($pskills as $s): ?>
                                        <span class="badge bg-primary me-1 mb-1 fs-6"><?= htmlspecialchars($s) ?></span>
                                    <?php endforeach; 
                                else: ?>
                                    <span class="text-muted small">None documented</span>
                                <?php endif; ?>
                            </div>

                            <?php if (!empty($candidate['secondary_skills'])): ?>
                                <div class="mb-3">
                                    <label class="small text-muted fw-semibold d-block mb-1">Secondary / Supplementary Skills</label>
                                    <?php foreach (array_filter(array_map('trim', explode(',', (string)$candidate['secondary_skills']))) as $s): ?>
                                        <span class="badge bg-secondary me-1 mb-1"><?= htmlspecialchars($s) ?></span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <hr>

                            <h5 class="fw-bold mb-3">Education & Background</h5>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <span class="small text-muted d-block">Highest Education</span>
                                    <strong><?= htmlspecialchars($candidate['highest_education'] ?: 'Not Provided') ?></strong>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <span class="small text-muted d-block">Profile Source</span>
                                    <span class="badge bg-info-subtle text-info border text-capitalize">
                                        <?= htmlspecialchars(str_replace('_', ' ', $candidate['source'])) ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-body">
                            <h5 class="fw-bold mb-3">Professional Links</h5>
                            <ul class="list-unstyled mb-0">
                                <li class="mb-2">
                                    <i class="bi bi-linkedin text-primary me-2"></i>
                                    <?php if (!empty($candidate['linkedin_url'])): ?>
                                        <a href="<?= htmlspecialchars($candidate['linkedin_url']) ?>" target="_blank" rel="noopener">LinkedIn Profile</a>
                                    <?php else: ?>
                                        <span class="text-muted small">Not provided</span>
                                    <?php endif; ?>
                                </li>
                                <li class="mb-2">
                                    <i class="bi bi-globe text-secondary me-2"></i>
                                    <?php if (!empty($candidate['portfolio_url'])): ?>
                                        <a href="<?= htmlspecialchars($candidate['portfolio_url']) ?>" target="_blank" rel="noopener">Portfolio / Website</a>
                                    <?php else: ?>
                                        <span class="text-muted small">Not provided</span>
                                    <?php endif; ?>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <!-- DPDP Consent Log -->
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <h6 class="fw-bold mb-2"><i class="bi bi-shield-check text-success me-1"></i> DPDP Consent Verification</h6>
                            <?php if (!empty($candidate['consents'])): ?>
                                <?php foreach ($candidate['consents'] as $cons): ?>
                                    <div class="small border-bottom py-2">
                                        <div class="fw-semibold text-dark"><?= htmlspecialchars($cons['consent_type']) ?></div>
                                        <div class="text-muted">Granted at: <?= htmlspecialchars($cons['granted_at']) ?></div>
                                        <div class="text-muted">IP: <?= htmlspecialchars($cons['ip_address']) ?></div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="small text-muted">No explicit consent records recorded.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab 2: Applications -->
        <div class="tab-pane fade" id="applications" role="tabpanel">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4">Application Code</th>
                                    <th>Job Position</th>
                                    <th>Department</th>
                                    <th>Current ATS Stage</th>
                                    <th>Applied On</th>
                                    <th class="text-end pe-4">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($candidate['applications'])): ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-4 text-muted">No job applications recorded for this candidate.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($candidate['applications'] as $app): ?>
                                        <tr>
                                            <td class="ps-4 fw-bold"><?= htmlspecialchars($app['application_code']) ?></td>
                                            <td>
                                                <div class="fw-semibold"><?= htmlspecialchars($app['job_title']) ?></div>
                                                <div class="small text-muted"><?= htmlspecialchars($app['job_code']) ?> &bull; <?= htmlspecialchars($app['requisition_code']) ?></div>
                                            </td>
                                            <td><?= htmlspecialchars($app['job_department']) ?></td>
                                            <td>
                                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle fs-6 text-capitalize">
                                                    <?= htmlspecialchars(str_replace('_', ' ', $app['current_stage'])) ?>
                                                </span>
                                            </td>
                                            <td><?= date('d M Y', strtotime($app['created_at'])) ?></td>
                                            <td class="text-end pe-4">
                                                <a href="index.php?route=recruitment_application/index&job_id=<?= $app['job_id'] ?>" class="btn btn-sm btn-outline-primary">
                                                    <i class="bi bi-kanban me-1"></i> View on Kanban
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
        </div>

        <!-- Tab 3: Documents -->
        <div class="tab-pane fade" id="documents" role="tabpanel">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4">Document Type</th>
                                    <th>File Name</th>
                                    <th>Size</th>
                                    <th>Uploaded</th>
                                    <th class="text-end pe-4">Download</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($candidate['documents'])): ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-4 text-muted">No documents uploaded.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($candidate['documents'] as $doc): ?>
                                        <tr>
                                            <td class="ps-4">
                                                <span class="badge bg-secondary text-uppercase"><?= htmlspecialchars($doc['document_type']) ?></span>
                                                <?php if ((int)$doc['is_primary_resume'] === 1): ?>
                                                    <span class="badge bg-success ms-1">Primary Resume</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="fw-semibold"><?= htmlspecialchars($doc['file_name']) ?></td>
                                            <td><?= number_format($doc['file_size_bytes'] / 1024, 1) ?> KB</td>
                                            <td><?= date('d M Y', strtotime($doc['created_at'])) ?></td>
                                            <td class="text-end pe-4">
                                                <a href="index.php?route=recruitment_candidate/downloadDocument&id=<?= $doc['document_id'] ?>" target="_blank" class="btn btn-sm btn-primary">
                                                    <i class="bi bi-cloud-arrow-down me-1"></i> Secure Download
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
        </div>

        <!-- Tab 4: Recruiter Notes -->
        <div class="tab-pane fade" id="notes" role="tabpanel">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-transparent py-3">
                    <h5 class="card-title fw-bold mb-0">Add Recruiter Note</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="index.php?route=recruitment_candidate/addNote">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                        <input type="hidden" name="candidate_id" value="<?= (int)$candidate['candidate_id'] ?>">

                        <div class="mb-3">
                            <textarea name="note_text" class="form-control" rows="3" placeholder="Enter confidential screening notes, call summaries, or candidate feedback..." required></textarea>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_confidential" value="1" id="isConfidentialCheck">
                                <label class="form-check-label small fw-semibold" for="isConfidentialCheck">
                                    <i class="bi bi-lock-fill text-warning me-1"></i> Confidential (Visible to HR & Admin only)
                                </label>
                            </div>
                            <button type="submit" class="btn btn-primary"><i class="bi bi-send me-1"></i> Save Note</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Notes Timeline -->
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h5 class="fw-bold mb-4">Historical Notes (<?= count($candidate['notes']) ?>)</h5>
                    <?php if (empty($candidate['notes'])): ?>
                        <div class="text-muted text-center py-4">No internal notes logged yet.</div>
                    <?php else: ?>
                        <div class="timeline">
                            <?php foreach ($candidate['notes'] as $n): ?>
                                <div class="border-start border-3 ps-3 mb-4 <?= (int)$n['is_confidential'] === 1 ? 'border-warning' : 'border-primary' ?>">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="fw-bold text-dark">
                                            <?= htmlspecialchars($n['author_name'] ?: 'Recruitment System') ?>
                                            <span class="badge bg-light text-muted border ms-1"><?= htmlspecialchars($n['author_role'] ?? 'staff') ?></span>
                                            <?php if ((int)$n['is_confidential'] === 1): ?>
                                                <span class="badge bg-warning text-dark ms-1"><i class="bi bi-lock-fill"></i> Confidential</span>
                                            <?php endif; ?>
                                        </span>
                                        <span class="small text-muted"><?= htmlspecialchars($n['created_at']) ?></span>
                                    </div>
                                    <p class="mb-0 text-secondary"><?= nl2br(htmlspecialchars($n['note_text'])) ?></p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Blacklist Modal -->
<div class="modal fade" id="blacklistModal" tabindex="-1" aria-labelledby="blacklistModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="index.php?route=recruitment_candidate/blacklist">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <input type="hidden" name="candidate_id" value="<?= (int)$candidate['candidate_id'] ?>">

            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="blacklistModalLabel">
                        <i class="bi bi-exclamation-octagon text-danger me-2"></i> Blacklist Candidate
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small">
                        Blacklisting will flag this candidate across all current and future job applications.
                    </p>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Reason for Blacklisting</label>
                        <textarea name="reason" class="form-control" rows="3" placeholder="Provide mandatory reason (e.g. fraudulent credentials, offer revocation, behavioral misconduct)..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Confirm Blacklist</button>
                </div>
            </div>
        </form>
    </div>
</div>

