<?php
/**
 * Raptor CRM — Interview Calendar & Scheduling View (Sprint 5)
 * -------------------------------------------------------------------------
 */
?>
<div class="container-fluid py-4">
    <!-- Breadcrumb & Header -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="index.php?route=recruitment">Recruitment ATS</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Interview Calendar</li>
                </ol>
            </nav>
            <h2 class="h3 fw-bold mb-0">Interview Calendar & Coordination</h2>
            <p class="text-muted small mb-0">Schedule multi-round interviews with automated calendar conflict detection and panel coordination.</p>
        </div>

        <div class="d-flex gap-2">
            <a href="index.php?route=recruitment_application/index" class="btn btn-outline-primary">
                <i class="bi bi-kanban me-1"></i> ATS Kanban
            </a>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#scheduleInterviewModal">
                <i class="bi bi-calendar-plus me-1"></i> Schedule Interview
            </button>
        </div>
    </div>

    <!-- Filter Toolbar -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" action="index.php" class="row g-3 align-items-end">
                <input type="hidden" name="route" value="recruitment_interview/index">

                <div class="col-md-3">
                    <label class="form-label small fw-semibold">From Date</label>
                    <input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($start_date) ?>">
                </div>

                <div class="col-md-3">
                    <label class="form-label small fw-semibold">To Date</label>
                    <input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($end_date) ?>">
                </div>

                <div class="col-md-3">
                    <label class="form-label small fw-semibold">Interviewer</label>
                    <select name="interviewer_id" class="form-select">
                        <option value="">All Interviewers</option>
                        <?php foreach ($interviewers as $u): ?>
                            <option value="<?= (int)$u['user_id'] ?>" <?= ($selected_user_id === (int)$u['user_id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($u['name']) ?> (<?= htmlspecialchars($u['role_name']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-dark w-100"><i class="bi bi-funnel me-1"></i> Filter Calendar</button>
                    <a href="index.php?route=recruitment_interview/index" class="btn btn-outline-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Scheduled Interviews List -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-transparent border-0 d-flex justify-content-between align-items-center py-3">
            <h5 class="card-title fw-bold mb-0">Scheduled Rounds: <span class="badge bg-primary"><?= count($events) ?></span></h5>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">Date & Time</th>
                        <th>Candidate</th>
                        <th>Position</th>
                        <th>Round & Mode</th>
                        <th>Panel Members</th>
                        <th>Status</th>
                        <th class="text-end pe-4">Scorecard</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($events)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="bi bi-calendar-x display-6 d-block mb-2"></i>
                                No interviews scheduled for the selected period.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($events as $ev): ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="fw-bold text-dark"><?= date('D, d M Y', strtotime($ev['scheduled_start'])) ?></div>
                                    <div class="small text-muted">
                                        <?= date('h:i A', strtotime($ev['scheduled_start'])) ?> - <?= date('h:i A', strtotime($ev['scheduled_end'])) ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark"><?= htmlspecialchars($ev['first_name'] . ' ' . $ev['last_name']) ?></div>
                                    <div class="small text-muted"><?= htmlspecialchars($ev['candidate_code']) ?></div>
                                </td>
                                <td>
                                    <div class="fw-semibold"><?= htmlspecialchars($ev['job_title']) ?></div>
                                    <div class="small text-muted"><?= htmlspecialchars($ev['job_department']) ?></div>
                                </td>
                                <td>
                                    <div class="fw-semibold"><?= htmlspecialchars($ev['round_name']) ?></div>
                                    <span class="badge bg-light text-secondary border text-capitalize"><?= htmlspecialchars($ev['interview_mode']) ?></span>
                                </td>
                                <td>
                                    <?php foreach ($ev['panel_members'] as $p): ?>
                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle me-1">
                                            <?= htmlspecialchars($p['name']) ?> <?= (int)$p['is_lead'] === 1 ? '(Lead)' : '' ?>
                                        </span>
                                    <?php endforeach; ?>
                                </td>
                                <td>
                                    <?php 
                                    $stClass = match($ev['status']) {
                                        'completed'   => 'bg-success',
                                        'cancelled'   => 'bg-danger',
                                        'rescheduled' => 'bg-warning text-dark',
                                        default       => 'bg-info'
                                    };
                                    ?>
                                    <span class="badge <?= $stClass ?> text-capitalize"><?= htmlspecialchars($ev['status']) ?></span>
                                </td>
                                <td class="text-end pe-4">
                                    <a href="index.php?route=recruitment_interview/evaluate/<?= (int)$ev['interview_id'] ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-clipboard-check me-1"></i> Blind Scorecard
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

<!-- Schedule Interview Modal -->
<div class="modal fade" id="scheduleInterviewModal" tabindex="-1" aria-labelledby="scheduleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form method="POST" action="index.php?route=recruitment_interview/store">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="scheduleModalLabel">
                        <i class="bi bi-calendar-plus text-primary me-2"></i> Schedule Interview Round
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label small fw-semibold">Active Candidate Application <span class="text-danger">*</span></label>
                            <select name="application_id" class="form-select" required>
                                <option value="">Select Candidate Application...</option>
                                <?php foreach ($activeApplications as $app): ?>
                                    <option value="<?= (int)$app['application_id'] ?>">
                                        <?= htmlspecialchars($app['first_name'] . ' ' . $app['last_name']) ?> &bull; <?= htmlspecialchars($app['job_title']) ?> (<?= htmlspecialchars($app['application_code']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Round Number</label>
                            <input type="number" name="round_number" class="form-control" value="1" min="1" max="10" required>
                        </div>

                        <div class="col-md-8">
                            <label class="form-label small fw-semibold">Round Name <span class="text-danger">*</span></label>
                            <input type="text" name="round_name" class="form-control" value="Technical Round 1" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Interview Type</label>
                            <select name="interview_type" class="form-select">
                                <option value="technical">Technical Depth & Architecture</option>
                                <option value="functional">Functional & Domain</option>
                                <option value="hr">HR & Culture Fit</option>
                                <option value="leadership">Leadership & Executive</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Interview Mode</label>
                            <select name="interview_mode" class="form-select">
                                <option value="video">Video Conference (Google Meet / Zoom)</option>
                                <option value="in_person">In-Person (On-Site Office)</option>
                                <option value="telephone">Telephone Discussion</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Scheduled Start Time <span class="text-danger">*</span></label>
                            <input type="datetime-local" name="scheduled_start" class="form-control" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Scheduled End Time <span class="text-danger">*</span></label>
                            <input type="datetime-local" name="scheduled_end" class="form-control" required>
                        </div>

                        <div class="col-md-12">
                            <label class="form-label small fw-semibold">Interview Panel Members <span class="text-danger">*</span></label>
                            <select name="panel_user_ids[]" class="form-select" multiple size="4" required>
                                <?php foreach ($interviewers as $u): ?>
                                    <option value="<?= (int)$u['user_id'] ?>">
                                        <?= htmlspecialchars($u['name']) ?> (<?= htmlspecialchars($u['role_name']) ?> &bull; <?= htmlspecialchars($u['email']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Hold Ctrl / Cmd to select multiple panel members. Conflict detection engine automatically verifies availability.</div>
                        </div>

                        <div class="col-md-12">
                            <label class="form-label small fw-semibold">Meeting Link / Video Room URL</label>
                            <input type="url" name="meeting_link" class="form-control" placeholder="https://meet.google.com/abc-defg-hij">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-calendar-check me-1"></i> Verify & Schedule Interview
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

