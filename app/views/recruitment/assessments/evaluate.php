<?php
/**
 * Raptor CRM — Assessment Evaluation & Grading View (Sprint 6)
 * -------------------------------------------------------------------------
 */
?>
<div class="container-fluid py-4">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php?route=recruitment">Recruitment ATS</a></li>
            <li class="breadcrumb-item"><a href="index.php?route=recruitment_assessment/index">Assessments</a></li>
            <li class="breadcrumb-item active" aria-current="page">Evaluate Submission #<?= (int)$sub['submission_id'] ?></li>
        </ol>
    </nav>

    <!-- Header Summary Card -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-4">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                <div>
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <h3 class="fw-bold mb-0"><?= htmlspecialchars($sub['assessment_title']) ?></h3>
                        <span class="badge bg-primary text-capitalize"><?= htmlspecialchars(str_replace('_', ' ', $sub['assessment_type'])) ?></span>
                        <?php if ($sub['status'] === 'submitted_late'): ?>
                            <span class="badge bg-warning text-dark"><i class="bi bi-clock-history me-1"></i>Submitted Late</span>
                        <?php else: ?>
                            <span class="badge bg-light text-dark border text-capitalize"><?= htmlspecialchars($sub['status']) ?></span>
                        <?php endif; ?>
                    </div>
                    <p class="text-muted mb-2">
                        Candidate: <a href="index.php?route=recruitment_candidate/show/<?= (int)$sub['candidate_id'] ?>" class="fw-bold text-dark text-decoration-none">
                            <?= htmlspecialchars($sub['first_name'] . ' ' . $sub['last_name']) ?>
                        </a>
                        (<?= htmlspecialchars($sub['candidate_code']) ?>) &bull; Position: <span class="fw-semibold text-dark"><?= htmlspecialchars($sub['job_title']) ?></span> (<?= htmlspecialchars($sub['job_department']) ?>)
                    </p>
                    <div class="small text-muted">
                        Assigned: <?= date('d M Y, h:i A', strtotime($sub['assigned_at'])) ?>
                        &bull; Due: <span class="fw-semibold text-dark"><?= date('d M Y, h:i A', strtotime($sub['due_date'])) ?></span>
                        <?php if (!empty($sub['submitted_at'])): ?>
                            &bull; Submitted: <?= date('d M Y, h:i A', strtotime($sub['submitted_at'])) ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card bg-light border p-3 text-end" style="min-width: 200px;">
                    <div class="small text-muted fw-semibold">Passing Threshold</div>
                    <div class="h4 fw-bold text-dark mb-0"><?= (int)$sub['passing_score'] ?> / <?= (int)$sub['max_score'] ?> pts</div>
                    <?php if ($sub['score_awarded'] !== null): ?>
                        <div class="small fw-bold mt-1 <?= (int)$sub['score_awarded'] >= (int)$sub['passing_score'] ? 'text-success' : 'text-danger' ?>">
                            Awarded: <?= (int)$sub['score_awarded'] ?> pts
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Left: Candidate Submission Deliverables -->
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-transparent py-3">
                    <h5 class="fw-bold mb-0"><i class="bi bi-file-earmark-code text-primary me-2"></i> Candidate Submission Deliverables</h5>
                </div>
                <div class="card-body p-4">
                    <div class="mb-4">
                        <label class="form-label small fw-semibold text-muted d-block">Repository / Deliverable URL</label>
                        <?php if (!empty($sub['submission_url'])): ?>
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="bi bi-link-45deg"></i></span>
                                <input type="text" class="form-control bg-white" readonly value="<?= htmlspecialchars($sub['submission_url']) ?>">
                                <a href="<?= htmlspecialchars($sub['submission_url']) ?>" target="_blank" rel="noopener" class="btn btn-outline-primary">
                                    <i class="bi bi-box-arrow-up-right me-1"></i> Open Repository
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="text-muted small fst-italic">No external URL provided by candidate.</div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <h6 class="fw-bold text-dark mb-2">Exercise Instructions & Constraints</h6>
                        <div class="border rounded p-3 bg-light text-secondary small">
                            <?= nl2br(htmlspecialchars($sub['instructions'] ?: $sub['description'])) ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right: Evaluator Grading Scorecard -->
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent py-3">
                    <h5 class="fw-bold mb-0"><i class="bi bi-check2-circle text-success me-2"></i> Evaluator Grading Scorecard</h5>
                </div>
                <div class="card-body p-4">
                    <form method="POST" action="index.php?route=recruitment_assessment/grade">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                        <input type="hidden" name="submission_id" value="<?= (int)$sub['submission_id'] ?>">

                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Score Awarded (Max: <?= (int)$sub['max_score'] ?>) <span class="text-danger">*</span></label>
                            <input type="number" name="score_awarded" class="form-control form-control-lg" 
                                   min="0" max="<?= (int)$sub['max_score'] ?>" 
                                   value="<?= $sub['score_awarded'] !== null ? (int)$sub['score_awarded'] : '' ?>" required>
                            <div class="form-text">Passing threshold: <?= (int)$sub['passing_score'] ?> pts. Scoring &gt;= threshold advances candidate.</div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label small fw-semibold">Evaluator Code Review & Qualitative Comments <span class="text-danger">*</span></label>
                            <textarea name="evaluator_comments" class="form-control" rows="6" required 
                                      placeholder="Synthesize architectural strengths, coding style, error handling, performance considerations..."><?= htmlspecialchars($sub['evaluator_comments'] ?? '') ?></textarea>
                        </div>

                        <div class="d-flex justify-content-end gap-2">
                            <a href="index.php?route=recruitment_assessment/index" class="btn btn-outline-secondary">Cancel</a>
                            <button type="submit" class="btn btn-primary px-4">
                                <i class="bi bi-check-circle me-1"></i> Submit Score & Advance
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

