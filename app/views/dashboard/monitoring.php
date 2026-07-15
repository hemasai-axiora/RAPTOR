<?php
$fmtInt = fn($v) => number_format((float) $v, 0);
$stateTone = [
    'working' => 'success',
    'late' => 'warning',
    'checked_out' => 'secondary',
    'no_login' => 'danger',
];
$stateLabel = [
    'working' => 'Working',
    'late' => 'Late',
    'checked_out' => 'Checked Out',
    'no_login' => 'No Login',
];
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h4 class="text-white mb-1">Sales Monitoring Command Center</h4>
        <div class="text-secondary" style="font-size:0.9rem;">Today: attendance, activity, pipeline, targets, follow-ups, and field visibility.</div>
    </div>
    <a href="index.php?route=performance/index" class="btn btn-outline-info btn-sm"><i class="fa-solid fa-ranking-star me-2"></i>Performance Ranking</a>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="pulse-card card-glow">
            <div class="card-title">Working Now</div>
            <div class="h2 text-white mb-0"><?php echo $fmtInt($rollup['attendance']['working']); ?></div>
            <div class="text-warning small mt-2"><?php echo $fmtInt($rollup['attendance']['late']); ?> late</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="pulse-card card-glow">
            <div class="card-title">No Login</div>
            <div class="h2 text-white mb-0"><?php echo $fmtInt($rollup['attendance']['no_login']); ?></div>
            <div class="text-secondary small mt-2">Expected field users</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="pulse-card card-glow">
            <div class="card-title">Target Completion</div>
            <div class="h2 text-white mb-0"><?php echo number_format((float) $rollup['targets']['completion'], 1); ?>%</div>
            <div class="text-secondary small mt-2"><?php echo $fmtInt($rollup['targets']['achieved']); ?> achieved</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="pulse-card card-glow">
            <div class="card-title">Forecast Pipeline</div>
            <div class="h2 text-success mb-0">$<?php echo number_format((float) $pipeline['forecast'], 0); ?></div>
            <div class="text-secondary small mt-2">Weighted by probability</div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-xl-8">
        <div class="pulse-card h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="text-white mb-0">Live Team Board</h5>
                <span class="text-secondary small">Location is foreground-only; stale after 30 minutes.</span>
            </div>
            <div class="row g-3">
                <?php if (empty($live_board)): ?>
                    <div class="col-12 text-center text-secondary py-4">No scoped sales users found.</div>
                <?php endif; ?>
                <?php foreach ($live_board as $row): ?>
                    <?php $tone = $stateTone[$row->state] ?? 'secondary'; ?>
                    <div class="col-md-6">
                        <div class="p-3 border border-secondary border-opacity-10 rounded-3 bg-dark bg-opacity-25 h-100">
                            <div class="d-flex justify-content-between gap-2">
                                <div>
                                    <a class="text-white fw-semibold text-decoration-none" href="index.php?route=dashboard/day/<?php echo $row->user_id; ?>">
                                        <?php echo htmlspecialchars($row->name); ?>
                                    </a>
                                    <div class="text-secondary small"><?php echo htmlspecialchars($row->team_name ?: 'No team'); ?></div>
                                </div>
                                <span class="badge bg-<?php echo $tone; ?>-subtle text-<?php echo $tone; ?> border border-<?php echo $tone; ?>-subtle align-self-start">
                                    <?php echo htmlspecialchars($stateLabel[$row->state] ?? $row->state); ?>
                                </span>
                            </div>
                            <div class="d-flex flex-wrap gap-3 mt-3 text-secondary small">
                                <span><i class="fa-regular fa-clock me-1"></i><?php echo $row->login_at ? htmlspecialchars(date('h:i A', strtotime($row->login_at))) : 'No login'; ?></span>
                                <span><i class="fa-solid fa-stopwatch me-1"></i><?php echo floor($row->worked_minutes / 60) . 'h ' . ($row->worked_minutes % 60) . 'm'; ?></span>
                                <span class="<?php echo $row->location_off ? 'text-danger' : 'text-success'; ?>">
                                    <i class="fa-solid fa-location-dot me-1"></i><?php echo $row->location_off ? 'Location stale/off' : 'Location active'; ?>
                                </span>
                            </div>
                            <?php if ($row->lat !== null && $row->lng !== null): ?>
                                <div class="d-flex gap-2 mt-3">
                                    <a class="btn btn-outline-info btn-sm" href="index.php?route=location/member/<?php echo $row->user_id; ?>">
                                        <i class="fa-solid fa-route me-1"></i>Route
                                    </a>
                                    <a class="btn btn-outline-light btn-sm" target="_blank" href="https://www.openstreetmap.org/?mlat=<?php echo urlencode($row->lat); ?>&mlon=<?php echo urlencode($row->lng); ?>#map=16/<?php echo urlencode($row->lat); ?>/<?php echo urlencode($row->lng); ?>">
                                        <i class="fa-solid fa-map-location-dot me-1"></i>Last Pin
                                    </a>
                                </div>
                                <div class="text-secondary small mt-2">Last fix: <?php echo htmlspecialchars(date('M d, h:i A', strtotime($row->last_location_at))); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="pulse-card h-100">
            <h5 class="text-white mb-3">Today Rollup</h5>
            <div class="d-grid gap-3">
                <div class="d-flex justify-content-between"><span class="text-secondary">Tasks pending / in progress</span><span class="text-white"><?php echo $rollup['tasks']['pending']; ?> / <?php echo $rollup['tasks']['in_progress']; ?></span></div>
                <div class="d-flex justify-content-between"><span class="text-secondary">Tasks completed today</span><span class="text-success"><?php echo $rollup['tasks']['completed_today']; ?></span></div>
                <div class="d-flex justify-content-between"><span class="text-secondary">Pending task reviews</span><span class="text-warning"><?php echo $rollup['tasks']['pending_review']; ?></span></div>
                <hr class="border-secondary border-opacity-10 my-1">
                <div class="d-flex justify-content-between"><span class="text-secondary">Communications</span><span class="text-white"><?php echo $rollup['activity']['communications']; ?></span></div>
                <div class="d-flex justify-content-between"><span class="text-secondary">Meetings / demos</span><span class="text-white"><?php echo $rollup['activity']['meetings']; ?> / <?php echo $rollup['activity']['demos']; ?></span></div>
                <hr class="border-secondary border-opacity-10 my-1">
                <div class="d-flex justify-content-between"><span class="text-secondary">Leads gen / followed / converted</span><span class="text-white"><?php echo $rollup['leads']['generated']; ?> / <?php echo $rollup['leads']['followed']; ?> / <?php echo $rollup['leads']['converted']; ?></span></div>
                <div class="d-flex justify-content-between"><span class="text-secondary">Follow-ups pending / missed</span><span class="text-white"><?php echo $rollup['followups']['pending']; ?> / <span class="text-danger"><?php echo $rollup['followups']['missed']; ?></span></span></div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-xl-7">
        <div class="pulse-card h-100">
            <h5 class="text-white mb-3">Pipeline & Forecast</h5>
            <?php if (empty($pipeline['by_status'])): ?>
                <div class="text-secondary py-4 text-center">No active pipeline in scope.</div>
            <?php endif; ?>
            <?php foreach ($pipeline['by_status'] as $stage): ?>
                <?php $pct = $pipeline['forecast'] > 0 ? min(100, ((float) $stage->forecast_sum / (float) $pipeline['forecast']) * 100) : 0; ?>
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-white"><?php echo strtoupper(htmlspecialchars($stage->status)); ?> <span class="text-secondary small">(<?php echo (int) $stage->count; ?>)</span></span>
                        <span class="text-success">$<?php echo number_format((float) $stage->forecast_sum, 0); ?></span>
                    </div>
                    <div class="progress bg-dark" style="height:8px;">
                        <div class="progress-bar" style="width: <?php echo $pct; ?>%; background: var(--primary);"></div>
                    </div>
                    <div class="text-secondary small mt-1">Raw value: $<?php echo number_format((float) $stage->value_sum, 0); ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="col-xl-5">
        <div class="pulse-card h-100">
            <h5 class="text-white mb-3">Fast Drill-downs</h5>
            <div class="row g-2">
                <div class="col-6"><a class="btn btn-outline-light w-100" href="index.php?route=attendance/approvals">Approvals</a></div>
                <div class="col-6"><a class="btn btn-outline-light w-100" href="index.php?route=followups/index">Follow-ups</a></div>
                <div class="col-6"><a class="btn btn-outline-light w-100" href="index.php?route=tasks/index">Tasks</a></div>
                <div class="col-6"><a class="btn btn-outline-light w-100" href="index.php?route=targets/index">Targets</a></div>
                <div class="col-6"><a class="btn btn-outline-light w-100" href="index.php?route=meetings/index">Meetings</a></div>
                <div class="col-6"><a class="btn btn-outline-light w-100" href="index.php?route=communications/index">Comms</a></div>
            </div>
        </div>
    </div>
</div>
