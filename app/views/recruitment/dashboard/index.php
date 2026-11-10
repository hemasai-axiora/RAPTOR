<?php
/**
 * Raptor CRM — Recruitment ATS Dashboard View (Sprint 1)
 * -----------------------------------------------------------------------------------
 * Design System: Bootstrap 5.3 + Raptor Glassmorphism + Theme Tokens
 */
?>

<div class="container-fluid px-4 py-4">
    <!-- Header Banner -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-1 rounded-pill">
                    <i class="fa-solid fa-user-plus me-1"></i> Raptor ATS v2.3
                </span>
                <span class="text-muted small">Correlation ID: <code><?php echo htmlspecialchars($correlation_id ?? 'N/A'); ?></code></span>
            </div>
            <h1 class="h3 fw-bold mt-2 mb-1 text-main">Recruitment Command Center</h1>
            <p class="text-muted mb-0">End-to-end talent acquisition lifecycle, headcount tracking, and hiring pipeline analytics.</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="index.php?route=careers" target="_blank" class="btn btn-outline-secondary px-3">
                <i class="fa-solid fa-arrow-up-right-from-square me-2"></i>Career Portal
            </a>
            <a href="index.php?route=recruitment/requisitions" class="btn btn-primary px-3 shadow-sm">
                <i class="fa-solid fa-plus me-2"></i>New Requisition
            </a>
        </div>
    </div>

    <!-- Stat Tiles Row -->
    <div class="row g-3 mb-4">
        <div class="col-xl-2 col-md-4 col-sm-6">
            <div class="card border-0 shadow-sm rounded-4 h-100 bg-card">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small fw-medium">Active Requisitions</span>
                            <h3 class="fw-bold mt-1 mb-0 text-main"><?php echo (int) ($stats['active_requisitions'] ?? 0); ?></h3>
                        </div>
                        <div class="rounded-3 p-2 bg-primary-subtle text-primary">
                            <i class="fa-solid fa-clipboard-list fa-lg"></i>
                        </div>
                    </div>
                    <div class="mt-2 text-muted small">
                        <span class="text-success fw-medium"><?php echo (int) ($stats['open_vacancies'] ?? 0); ?></span> total vacancies
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-2 col-md-4 col-sm-6">
            <div class="card border-0 shadow-sm rounded-4 h-100 bg-card">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small fw-medium">Talent Pool</span>
                            <h3 class="fw-bold mt-1 mb-0 text-main"><?php echo (int) ($stats['total_candidates'] ?? 0); ?></h3>
                        </div>
                        <div class="rounded-3 p-2 bg-info-subtle text-info">
                            <i class="fa-solid fa-users fa-lg"></i>
                        </div>
                    </div>
                    <div class="mt-2 text-muted small">Master candidate profiles</div>
                </div>
            </div>
        </div>

        <div class="col-xl-2 col-md-4 col-sm-6">
            <div class="card border-0 shadow-sm rounded-4 h-100 bg-card">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small fw-medium">In Pipeline</span>
                            <h3 class="fw-bold mt-1 mb-0 text-main"><?php echo (int) ($stats['active_applications'] ?? 0); ?></h3>
                        </div>
                        <div class="rounded-3 p-2 bg-warning-subtle text-warning">
                            <i class="fa-solid fa-network-wired fa-lg"></i>
                        </div>
                    </div>
                    <div class="mt-2 text-muted small">Active stage applications</div>
                </div>
            </div>
        </div>

        <div class="col-xl-2 col-md-4 col-sm-6">
            <div class="card border-0 shadow-sm rounded-4 h-100 bg-card">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small fw-medium">Interviews Today</span>
                            <h3 class="fw-bold mt-1 mb-0 text-main"><?php echo (int) ($stats['interviews_today'] ?? 0); ?></h3>
                        </div>
                        <div class="rounded-3 p-2 bg-purple-subtle text-purple" style="background:#f3e8ff; color:#9333ea;">
                            <i class="fa-solid fa-calendar-check fa-lg"></i>
                        </div>
                    </div>
                    <div class="mt-2 text-muted small">Scheduled rounds</div>
                </div>
            </div>
        </div>

        <div class="col-xl-2 col-md-4 col-sm-6">
            <div class="card border-0 shadow-sm rounded-4 h-100 bg-card">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small fw-medium">Pending Offers</span>
                            <h3 class="fw-bold mt-1 mb-0 text-main"><?php echo (int) ($stats['pending_offers'] ?? 0); ?></h3>
                        </div>
                        <div class="rounded-3 p-2 bg-danger-subtle text-danger">
                            <i class="fa-solid fa-file-signature fa-lg"></i>
                        </div>
                    </div>
                    <div class="mt-2 text-muted small">Awaiting signoff / release</div>
                </div>
            </div>
        </div>

        <div class="col-xl-2 col-md-4 col-sm-6">
            <div class="card border-0 shadow-sm rounded-4 h-100 bg-card">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small fw-medium">Hired (All-Time)</span>
                            <h3 class="fw-bold mt-1 mb-0 text-main"><?php echo (int) ($stats['hired_count'] ?? 0); ?></h3>
                        </div>
                        <div class="rounded-3 p-2 bg-success-subtle text-success">
                            <i class="fa-solid fa-id-badge fa-lg"></i>
                        </div>
                    </div>
                    <div class="mt-2 text-muted small">Converted to employees</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Pipeline Funnel Visual Strip -->
    <div class="card border-0 shadow-sm rounded-4 mb-4 bg-card">
        <div class="card-header bg-transparent border-0 pt-3 pb-0 px-4">
            <h5 class="fw-bold mb-0 text-main">Talent Pipeline Funnel</h5>
            <span class="text-muted small">Live application status distribution across recruitment stages</span>
        </div>
        <div class="card-body p-4">
            <div class="row g-2 text-center">
                <?php 
                $funnel = $stats['pipeline_funnel'] ?? [];
                $stages = [
                    ['applied',             'Applied',        '#3b82f6', 'fa-inbox'],
                    ['screening',           'Screening',      '#06b6d4', 'fa-filter'],
                    ['shortlisted',         'Shortlisted',    '#8b5cf6', 'fa-user-check'],
                    ['technical_interview', 'Tech Interview', '#eab308', 'fa-code'],
                    ['hr_interview',        'HR Discussion',  '#f97316', 'fa-comments'],
                    ['offer_released',      'Offer Released', '#ec4899', 'fa-file-invoice'],
                    ['hired',               'Hired',          '#10b981', 'fa-badge-check']
                ];
                foreach ($stages as [$stgKey, $stgLabel, $color, $icon]): 
                    $count = (int) ($funnel[$stgKey] ?? 0);
                ?>
                <div class="col">
                    <div class="p-3 rounded-4 border border-light-subtle h-100 d-flex flex-column justify-content-center align-items-center" style="background: rgba(255,255,255,0.02);">
                        <i class="fa-solid <?php echo $icon; ?> mb-2 fs-5" style="color: <?php echo $color; ?>;"></i>
                        <span class="text-muted small fw-medium"><?php echo $stgLabel; ?></span>
                        <h4 class="fw-bold mt-1 mb-0 text-main"><?php echo $count; ?></h4>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Two-Column Operational Lists -->
    <div class="row g-4">
        <!-- Recent Requisitions -->
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm rounded-4 h-100 bg-card">
                <div class="card-header bg-transparent border-0 pt-4 pb-2 px-4 d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="fw-bold mb-0 text-main">Active Job Requisitions</h5>
                        <span class="text-muted small">Approved headcount demands currently open</span>
                    </div>
                    <a href="index.php?route=recruitment/requisitions" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="card-body px-4 pb-4 pt-2">
                    <?php if (empty($stats['recent_requisitions'])): ?>
                        <div class="text-center py-5 text-muted">
                            <i class="fa-solid fa-clipboard-question fa-3x mb-3 text-secondary opacity-50"></i>
                            <p class="mb-2">No active requisitions found.</p>
                            <a href="index.php?route=recruitment/requisitions" class="btn btn-sm btn-primary">Create Requisition</a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="text-muted small">
                                    <tr>
                                        <th>Code</th>
                                        <th>Position</th>
                                        <th>Department</th>
                                        <th>Manager</th>
                                        <th>Vacancies</th>
                                        <th>Priority</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($stats['recent_requisitions'] as $req): ?>
                                    <tr>
                                        <td><code><?php echo htmlspecialchars($req['requisition_code']); ?></code></td>
                                        <td class="fw-semibold text-main"><?php echo htmlspecialchars($req['title']); ?></td>
                                        <td><span class="badge bg-secondary-subtle text-secondary"><?php echo htmlspecialchars($req['department']); ?></span></td>
                                        <td class="text-muted small"><?php echo htmlspecialchars($req['hiring_manager_name'] ?? 'N/A'); ?></td>
                                        <td><span class="fw-bold"><?php echo (int) $req['vacancies']; ?></span></td>
                                        <td>
                                            <?php 
                                            $prio = $req['hiring_priority'] ?? 'medium';
                                            $prioBadge = match($prio) {
                                                'urgent' => 'bg-danger text-white',
                                                'high'   => 'bg-warning text-dark',
                                                'low'    => 'bg-light text-muted border',
                                                default  => 'bg-info-subtle text-info'
                                            };
                                            ?>
                                            <span class="badge <?php echo $prioBadge; ?> text-capitalize"><?php echo htmlspecialchars($prio); ?></span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Upcoming Interviews Today -->
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm rounded-4 h-100 bg-card">
                <div class="card-header bg-transparent border-0 pt-4 pb-2 px-4 d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="fw-bold mb-0 text-main">Upcoming Interviews</h5>
                        <span class="text-muted small">Scheduled candidate evaluations</span>
                    </div>
                    <a href="index.php?route=recruitment/interviews" class="btn btn-sm btn-outline-primary">Calendar</a>
                </div>
                <div class="card-body px-4 pb-4 pt-2">
                    <?php if (empty($stats['upcoming_interviews'])): ?>
                        <div class="text-center py-5 text-muted">
                            <i class="fa-solid fa-calendar-xmark fa-3x mb-3 text-secondary opacity-50"></i>
                            <p class="mb-0">No upcoming interviews scheduled today.</p>
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($stats['upcoming_interviews'] as $int): ?>
                            <div class="list-group-item px-0 py-3 bg-transparent border-bottom">
                                <div class="d-flex justify-content-between align-items-start mb-1">
                                    <h6 class="fw-bold mb-0 text-main">
                                        <?php echo htmlspecialchars(($int['first_name'] ?? '') . ' ' . ($int['last_name'] ?? '')); ?>
                                    </h6>
                                    <span class="badge bg-primary-subtle text-primary">
                                        <?php echo date('h:i A', strtotime($int['scheduled_start'])); ?>
                                    </span>
                                </div>
                                <div class="text-muted small mb-2">
                                    <span><?php echo htmlspecialchars($int['round_name']); ?></span> &bull; 
                                    <span class="text-main fw-medium"><?php echo htmlspecialchars($int['job_title']); ?></span>
                                </div>
                                <?php if (!empty($int['meeting_link'])): ?>
                                <a href="<?php echo htmlspecialchars($int['meeting_link']); ?>" target="_blank" class="btn btn-sm btn-outline-success py-0 px-2 small">
                                    <i class="fa-solid fa-video me-1"></i> Join Meeting
                                </a>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

