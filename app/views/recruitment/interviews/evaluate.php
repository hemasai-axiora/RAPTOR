<?php
/**
 * Raptor CRM — Blind Interview Scorecard Evaluation View (Sprint 5)
 * -------------------------------------------------------------------------
 */
?>
<div class="container-fluid py-4">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php?route=recruitment">Recruitment ATS</a></li>
            <li class="breadcrumb-item"><a href="index.php?route=recruitment_interview/index">Interview Calendar</a></li>
            <li class="breadcrumb-item active" aria-current="page">Blind Evaluation: <?= htmlspecialchars($interview['round_name']) ?></li>
        </ol>
    </nav>

    <!-- Top Interview Banner -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-4">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                <div>
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <h3 class="fw-bold mb-0"><?= htmlspecialchars($interview['round_name']) ?></h3>
                        <span class="badge bg-primary text-uppercase"><?= htmlspecialchars($interview['interview_type']) ?></span>
                        <span class="badge bg-light text-dark border"><?= htmlspecialchars($interview['status']) ?></span>
                    </div>
                    <p class="text-muted mb-2">
                        Candidate: <a href="index.php?route=recruitment_candidate/show/<?= (int)$interview['candidate_id'] ?>" class="fw-bold text-dark text-decoration-none">
                            <?= htmlspecialchars($interview['first_name'] . ' ' . $interview['last_name']) ?>
                        </a>
                        &bull; Position: <span class="fw-semibold text-dark"><?= htmlspecialchars($interview['job_title']) ?></span> (<?= htmlspecialchars($interview['job_department']) ?>)
                    </p>
                    <div class="small text-muted">
                        <i class="bi bi-clock me-1"></i> <?= date('D, d M Y h:i A', strtotime($interview['scheduled_start'])) ?>
                        &bull; Mode: <span class="text-capitalize"><?= htmlspecialchars($interview['interview_mode']) ?></span>
                        <?php if (!empty($interview['meeting_link'])): ?>
                            &bull; <a href="<?= htmlspecialchars($interview['meeting_link']) ?>" target="_blank" rel="noopener"><i class="bi bi-camera-video me-1"></i>Join Video Call</a>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="text-end">
                    <span class="small text-muted d-block mb-1">Panel Members:</span>
                    <?php foreach ($interview['panel'] as $p): ?>
                        <span class="badge bg-secondary-subtle text-secondary me-1">
                            <?= htmlspecialchars($p['interviewer_name']) ?>
                        </span>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Blind Evaluation Notification Banner -->
    <?php if (!empty($feedback_data['blind_lock_active'])): ?>
        <div class="alert alert-warning border-warning d-flex align-items-center mb-4 shadow-xs" role="alert">
            <i class="bi bi-shield-lock-fill fs-3 text-warning me-3"></i>
            <div>
                <h5 class="alert-heading fw-bold mb-1">Blind Evaluation Gate Active</h5>
                <p class="mb-0 small">
                    <?= htmlspecialchars($feedback_data['message']) ?>
                    To eliminate cognitive bias, peer panel scorecards remain strictly locked until you record and lock your scorecard.
                </p>
            </div>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Left: Evaluation Form (if can submit) -->
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent py-3">
                    <h5 class="fw-bold mb-0"><i class="bi bi-pencil-square text-primary me-2"></i> Submit Interview Scorecard</h5>
                </div>
                <div class="card-body p-4">
                    <form method="POST" action="index.php?route=recruitment_interview/submitEvaluation">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                        <input type="hidden" name="interview_id" value="<?= (int)$interview['interview_id'] ?>">

                        <!-- Rating Criteria 1-5 -->
                        <h6 class="fw-bold text-dark mb-3">Structured Evaluation Rubric (1 = Poor, 5 = Exceptional)</h6>

                        <div class="mb-3 border-bottom pb-3">
                            <label class="form-label small fw-semibold d-block mb-2">1. Technical Depth & Domain Expertise</label>
                            <div class="btn-group w-100" role="group">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <input type="radio" class="btn-check" name="technical_rating" id="tech_<?= $i ?>" value="<?= $i ?>" <?= $i === 3 ? 'checked' : '' ?>>
                                    <label class="btn btn-outline-secondary" for="tech_<?= $i ?>"><?= $i ?> <?= $i === 5 ? '★' : '' ?></label>
                                <?php endfor; ?>
                            </div>
                        </div>

                        <div class="mb-3 border-bottom pb-3">
                            <label class="form-label small fw-semibold d-block mb-2">2. Problem Solving & Database Design</label>
                            <div class="btn-group w-100" role="group">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <input type="radio" class="btn-check" name="problem_solving_rating" id="prob_<?= $i ?>" value="<?= $i ?>" <?= $i === 3 ? 'checked' : '' ?>>
                                    <label class="btn btn-outline-secondary" for="prob_<?= $i ?>"><?= $i ?> <?= $i === 5 ? '★' : '' ?></label>
                                <?php endfor; ?>
                            </div>
                        </div>

                        <div class="mb-3 border-bottom pb-3">
                            <label class="form-label small fw-semibold d-block mb-2">3. Communication & Articulation</label>
                            <div class="btn-group w-100" role="group">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <input type="radio" class="btn-check" name="communication_rating" id="comm_<?= $i ?>" value="<?= $i ?>" <?= $i === 3 ? 'checked' : '' ?>>
                                    <label class="btn btn-outline-secondary" for="comm_<?= $i ?>"><?= $i ?> <?= $i === 5 ? '★' : '' ?></label>
                                <?php endfor; ?>
                            </div>
                        </div>

                        <div class="mb-3 border-bottom pb-3">
                            <label class="form-label small fw-semibold d-block mb-2">4. Cultural Alignment & Collaboration</label>
                            <div class="btn-group w-100" role="group">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <input type="radio" class="btn-check" name="cultural_fit_rating" id="fit_<?= $i ?>" value="<?= $i ?>" <?= $i === 3 ? 'checked' : '' ?>>
                                    <label class="btn btn-outline-secondary" for="fit_<?= $i ?>"><?= $i ?> <?= $i === 5 ? '★' : '' ?></label>
                                <?php endfor; ?>
                            </div>
                        </div>

                        <div class="mb-4 border-bottom pb-3">
                            <label class="form-label small fw-semibold d-block mb-2">5. Overall Candidate Potential</label>
                            <div class="btn-group w-100" role="group">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <input type="radio" class="btn-check" name="overall_rating" id="over_<?= $i ?>" value="<?= $i ?>" <?= $i === 3 ? 'checked' : '' ?>>
                                    <label class="btn btn-outline-primary fw-bold" for="over_<?= $i ?>"><?= $i ?> <?= $i === 5 ? '★' : '' ?></label>
                                <?php endfor; ?>
                            </div>
                        </div>

                        <!-- Hiring Recommendation -->
                        <h6 class="fw-bold text-dark mb-2">Hiring Recommendation</h6>
                        <div class="row g-2 mb-4">
                            <div class="col-md-6">
                                <div class="form-check p-3 border rounded">
                                    <input class="form-check-input" type="radio" name="recommendation" id="rec_strong" value="strongly_recommend">
                                    <label class="form-check-label fw-bold text-success" for="rec_strong">
                                        Strongly Recommend
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check p-3 border rounded">
                                    <input class="form-check-input" type="radio" name="recommendation" id="rec_hire" value="recommend" checked>
                                    <label class="form-check-label fw-bold text-primary" for="rec_hire">
                                        Recommend
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check p-3 border rounded">
                                    <input class="form-check-input" type="radio" name="recommendation" id="rec_hold" value="hold">
                                    <label class="form-check-label fw-bold text-warning" for="rec_hold">
                                        Hold / Further Assessment
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check p-3 border rounded">
                                    <input class="form-check-input" type="radio" name="recommendation" id="rec_no" value="do_not_recommend">
                                    <label class="form-check-label fw-bold text-danger" for="rec_no">
                                        Do Not Recommend
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Qualitative Feedback -->
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Key Strengths Observed</label>
                            <textarea name="strengths" class="form-control" rows="2" placeholder="Highlight technical problem solving, domain depth, articulate explanations..."></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Areas of Concern / Red Flags</label>
                            <textarea name="concerns" class="form-control" rows="2" placeholder="Note any missing domain knowledge, reluctance on requirements, or gaps..."></textarea>
                        </div>

                        <div class="mb-4">
                            <label class="form-label small fw-semibold">Detailed Evaluation Summary <span class="text-danger">*</span></label>
                            <textarea name="detailed_comments" class="form-control" rows="3" required placeholder="Comprehensive evaluation synthesis supporting your recommendation..."></textarea>
                        </div>

                        <div class="d-flex justify-content-end gap-2">
                            <a href="index.php?route=recruitment_interview/index" class="btn btn-outline-secondary">Cancel</a>
                            <button type="submit" class="btn btn-primary px-4">
                                <i class="bi bi-shield-check me-1"></i> Submit & Lock Evaluation Scorecard
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Right: Peer Evaluations (Unlocked or Blind Locked) -->
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent py-3">
                    <h5 class="fw-bold mb-0"><i class="bi bi-people text-secondary me-2"></i> Panel Scorecards</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($feedback_data['blind_lock_active'])): ?>
                        <div class="text-center py-5 text-muted">
                            <i class="bi bi-lock-fill display-4 text-warning d-block mb-3"></i>
                            <h6 class="fw-bold text-dark">Peer Evaluations Hidden</h6>
                            <p class="small text-muted mb-0">
                                This panel operates under strict blind evaluation governance. Submit your evaluation to unlock peer feedback.
                            </p>
                        </div>
                    <?php else: ?>
                        <?php if (empty($feedback_data['feedbacks'])): ?>
                            <div class="text-center py-4 text-muted small">No scorecards submitted yet.</div>
                        <?php else: ?>
                            <?php foreach ($feedback_data['feedbacks'] as $fb): ?>
                                <div class="border rounded p-3 mb-3 bg-light">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <div>
                                            <span class="fw-bold text-dark"><?= htmlspecialchars($fb['interviewer_name']) ?></span>
                                            <span class="badge bg-secondary ms-1"><?= htmlspecialchars($fb['role_name'] ?? 'panel') ?></span>
                                        </div>
                                        <div>
                                            <span class="badge bg-primary">Score: <?= (int)$fb['overall_rating'] ?> / 5</span>
                                        </div>
                                    </div>

                                    <div class="mb-2">
                                        <span class="badge bg-info-subtle text-info border border-info-subtle text-capitalize">
                                            <?= htmlspecialchars(str_replace('_', ' ', $fb['recommendation'])) ?>
                                        </span>
                                    </div>

                                    <?php if (!empty($fb['strengths'])): ?>
                                        <div class="small mb-1"><strong class="text-success">Strengths:</strong> <?= htmlspecialchars($fb['strengths']) ?></div>
                                    <?php endif; ?>

                                    <?php if (!empty($fb['concerns'])): ?>
                                        <div class="small mb-1"><strong class="text-danger">Concerns:</strong> <?= htmlspecialchars($fb['concerns']) ?></div>
                                    <?php endif; ?>

                                    <div class="small text-muted mt-2 pt-2 border-top">
                                        <?= nl2br(htmlspecialchars($fb['detailed_comments'])) ?>
                                    </div>
                                    <div class="text-end small text-muted mt-1 fst-italic">
                                        Submitted <?= date('d M Y, h:i A', strtotime($fb['submitted_at'])) ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

