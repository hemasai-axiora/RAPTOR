<?php
/**
 * Raptor CRM — Assessments & Assignments Index View (Sprint 6)
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
                    <li class="breadcrumb-item active" aria-current="page">Assessments & Assignments</li>
                </ol>
            </nav>
            <h2 class="h3 fw-bold mb-0">Candidate Assessments & Assignments</h2>
            <p class="text-muted small mb-0">Manage coding challenges, take-home architecture assignments, and candidate grading rubrics.</p>
        </div>

        <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#newAssessmentModal">
                <i class="bi bi-plus-circle me-1"></i> New Assessment Template
            </button>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#assignAssessmentModal">
                <i class="bi bi-send-check me-1"></i> Assign to Candidate
            </button>
        </div>
    </div>

    <!-- Navigation Tabs -->
    <ul class="nav nav-tabs nav-fill mb-4" id="assessmentTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active fw-semibold" id="submissions-tab" data-bs-toggle="tab" data-bs-target="#submissions" type="button" role="tab">
                <i class="bi bi-inbox me-1"></i> Candidate Submissions (<?= count($submissions) ?>)
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link fw-semibold" id="templates-tab" data-bs-toggle="tab" data-bs-target="#templates" type="button" role="tab">
                <i class="bi bi-card-checklist me-1"></i> Assessment Templates (<?= count($templates) ?>)
            </button>
        </li>
    </ul>

    <!-- Tab Contents -->
    <div class="tab-content" id="assessmentTabsContent">
        <!-- Tab 1: Submissions -->
        <div class="tab-pane fade show active" id="submissions" role="tabpanel">
            <div class="card border-0 shadow-sm">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4">Candidate</th>
                                <th>Position</th>
                                <th>Assessment</th>
                                <th>Due Date</th>
                                <th>Status</th>
                                <th>Score</th>
                                <th class="text-end pe-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($submissions)): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-5 text-muted">
                                        <i class="bi bi-journal-x display-6 d-block mb-2"></i>
                                        No assessment assignments currently dispatched.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($submissions as $s): ?>
                                    <tr>
                                        <td class="ps-4">
                                            <div class="fw-bold text-dark"><?= htmlspecialchars($s['first_name'] . ' ' . $s['last_name']) ?></div>
                                            <div class="small text-muted"><?= htmlspecialchars($s['candidate_code']) ?> &bull; <?= htmlspecialchars($s['application_code']) ?></div>
                                        </td>
                                        <td>
                                            <div class="fw-semibold"><?= htmlspecialchars($s['job_title']) ?></div>
                                            <div class="small text-muted"><?= htmlspecialchars($s['job_department']) ?></div>
                                        </td>
                                        <td>
                                            <div class="fw-semibold"><?= htmlspecialchars($s['assessment_title']) ?></div>
                                            <span class="badge bg-light text-secondary border text-capitalize"><?= htmlspecialchars(str_replace('_', ' ', $s['assessment_type'])) ?></span>
                                        </td>
                                        <td>
                                            <div class="small fw-semibold"><?= date('d M Y, h:i A', strtotime($s['due_date'])) ?></div>
                                            <?php if (strtotime($s['due_date']) < time() && !in_array($s['status'], ['submitted', 'evaluated'], true)): ?>
                                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle small">Overdue</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php 
                                            $stClass = match($s['status']) {
                                                'evaluated'      => 'bg-success',
                                                'submitted'      => 'bg-primary',
                                                'submitted_late' => 'bg-warning text-dark',
                                                default          => 'bg-secondary'
                                            };
                                            ?>
                                            <span class="badge <?= $stClass ?> text-capitalize">
                                                <?= htmlspecialchars(str_replace('_', ' ', $s['status'])) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($s['score_awarded'] !== null): ?>
                                                <span class="fw-bold <?= (int)$s['score_awarded'] >= (int)$s['passing_score'] ? 'text-success' : 'text-danger' ?>">
                                                    <?= (int)$s['score_awarded'] ?> / <?= (int)$s['max_score'] ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted small fst-italic">Pending</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end pe-4">
                                            <a href="index.php?route=recruitment_assessment/evaluate/<?= (int)$s['submission_id'] ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-pencil-square me-1"></i> Grade & Review
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

        <!-- Tab 2: Templates -->
        <div class="tab-pane fade" id="templates" role="tabpanel">
            <div class="row g-4">
                <?php foreach ($templates as $t): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card h-100 border-0 shadow-sm">
                            <div class="card-body d-flex flex-column">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <h5 class="fw-bold mb-0 text-dark"><?= htmlspecialchars($t['title']) ?></h5>
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle text-capitalize">
                                        <?= htmlspecialchars(str_replace('_', ' ', $t['assessment_type'])) ?>
                                    </span>
                                </div>
                                <p class="text-muted small mb-3 flex-grow-1"><?= htmlspecialchars($t['description']) ?></p>

                                <div class="border-top pt-3 small text-muted">
                                    <div class="d-flex justify-content-between mb-1">
                                        <span>Allocated Duration:</span>
                                        <strong><?= (int)$t['duration_minutes'] ?> Mins</strong>
                                    </div>
                                    <div class="d-flex justify-content-between mb-1">
                                        <span>Pass Threshold:</span>
                                        <strong class="text-success"><?= (int)$t['passing_score'] ?> / <?= (int)$t['max_score'] ?> pts</strong>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- Assign Assessment Modal -->
<div class="modal fade" id="assignAssessmentModal" tabindex="-1" aria-labelledby="assignModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="index.php?route=recruitment_assessment/assign">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="assignModalLabel">
                        <i class="bi bi-send-check text-primary me-2"></i> Assign Assessment to Candidate
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Candidate Application <span class="text-danger">*</span></label>
                        <select name="application_id" class="form-select" required>
                            <option value="">Select Candidate...</option>
                            <?php foreach ($active_applications as $app): ?>
                                <option value="<?= (int)$app['application_id'] ?>">
                                    <?= htmlspecialchars($app['first_name'] . ' ' . $app['last_name']) ?> &bull; <?= htmlspecialchars($app['job_title']) ?> (<?= htmlspecialchars($app['application_code']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Assessment Definition <span class="text-danger">*</span></label>
                        <select name="assessment_id" class="form-select" required>
                            <option value="">Select Assessment Template...</option>
                            <?php foreach ($templates as $t): ?>
                                <option value="<?= (int)$t['assessment_id'] ?>">
                                    <?= htmlspecialchars($t['title']) ?> (Max: <?= (int)$t['max_score'] ?> pts)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Submission Deadline (Due Date) <span class="text-danger">*</span></label>
                        <input type="datetime-local" name="due_date" class="form-control" required>
                        <div class="form-text">Candidate notification email will be automatically enqueued.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Dispatch Assignment</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- New Assessment Template Modal -->
<div class="modal fade" id="newAssessmentModal" tabindex="-1" aria-labelledby="newAssModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form method="POST" action="index.php?route=recruitment_assessment/store">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="newAssModalLabel">
                        <i class="bi bi-plus-circle text-primary me-2"></i> Create Assessment Template
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label small fw-semibold">Assessment Title <span class="text-danger">*</span></label>
                            <input type="text" name="title" class="form-control" required placeholder="e.g. Senior PHP Backend Architectural Exercise">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Assessment Type</label>
                            <select name="assessment_type" class="form-select">
                                <option value="take_home_assignment">Take-Home Architecture Assignment</option>
                                <option value="coding">Technical Coding Challenge</option>
                                <option value="domain_knowledge">Domain & Case Study Exercise</option>
                                <option value="writing_sample">Writing / Strategy Proposal</option>
                                <option value="aptitude">Analytical & Aptitude</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Duration (Minutes)</label>
                            <input type="number" name="duration_minutes" class="form-control" value="120" min="15">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Maximum Score</label>
                            <input type="number" name="max_score" class="form-control" value="100" min="10">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Passing Threshold Score</label>
                            <input type="number" name="passing_score" class="form-control" value="60" min="10">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label small fw-semibold">Exercise Description & Problem Statement</label>
                            <textarea name="description" class="form-control" rows="3" placeholder="Brief summary of what this exercise assesses..."></textarea>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label small fw-semibold">Detailed Instructions & Submission Guidelines</label>
                            <textarea name="instructions" class="form-control" rows="4" placeholder="Instructions sent to the candidate: repository format, deliverables, constraints..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Template</button>
                </div>
            </div>
        </form>
    </div>
</div>

