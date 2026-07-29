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
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
                <div>
                    <h5 class="text-white mb-0 d-inline-block me-2">Live Team Board</h5>
                    <span class="badge bg-dark border border-secondary text-secondary"><?php echo count($live_board); ?> members</span>
                </div>
                <div style="min-width: 200px;" class="ms-auto">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-dark border-secondary text-secondary"><i class="fa-solid fa-magnifying-glass"></i></span>
                        <input type="text" id="team-search-input" class="form-control form-control-sm bg-dark text-white border-secondary" placeholder="Search team member...">
                    </div>
                </div>
            </div>

            <?php if (empty($live_board)): ?>
                <div class="text-center text-secondary py-4">No scoped sales users found.</div>
            <?php else: ?>
                <!-- Compact Name-Only Grid -->
                <div class="row row-cols-2 row-cols-sm-3 row-cols-md-4 row-cols-xl-6 g-2" id="live-team-grid">
                    <?php foreach ($live_board as $row): ?>
                        <?php 
                        $dotClass = match($row->state) {
                            'working' => 'bg-success shadow-success-glow',
                            'late' => 'bg-warning shadow-warning-glow',
                            'checked_out' => 'bg-secondary',
                            default => 'bg-danger'
                        };
                        $jsonPayload = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8');
                        ?>
                        <div class="col team-member-col" data-member-name="<?php echo strtolower(htmlspecialchars($row->name)); ?>">
                            <button type="button" class="btn btn-dark btn-sm text-start w-100 p-2 border border-secondary border-opacity-25 rounded-3 d-flex align-items-center gap-2 team-member-card shadow-sm"
                                    data-bs-toggle="modal" data-bs-target="#employeeDetailModal" data-member='<?php echo $jsonPayload; ?>'>
                                <span class="rounded-circle d-inline-block flex-shrink-0 <?php echo $dotClass; ?>" style="width: 10px; height: 10px;"></span>
                                <span class="text-white text-truncate small fw-semibold flex-grow-1" style="font-size: 0.84rem;" title="<?php echo htmlspecialchars($row->name); ?>">
                                    <?php echo htmlspecialchars($row->name); ?>
                                </span>
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div id="no-team-members-found" class="text-center text-secondary py-4 d-none">
                    <i class="fa-solid fa-user-slash fs-4 d-block mb-1"></i> No matching team members found.
                </div>
            <?php endif; ?>
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

<!-- Click-to-Expand Employee Detail Modal -->
<div class="modal fade" id="employeeDetailModal" tabindex="-1" aria-labelledby="employeeDetailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark text-white border-secondary shadow-lg">
            <div class="modal-header border-secondary">
                <div class="d-flex align-items-center gap-2">
                    <span id="modal-status-dot" class="rounded-circle d-inline-block" style="width: 12px; height: 12px;"></span>
                    <h5 class="modal-title mb-0 fw-bold" id="modal-employee-name">Employee Detail</h5>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="p-3 bg-dark bg-opacity-50 rounded border border-secondary border-opacity-25 mb-3">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="fw-bold text-white fs-6" id="modal-emp-name-title">-</div>
                            <div class="text-secondary small mt-1"><i class="fa-solid fa-people-group me-1 text-primary"></i><span id="modal-team-name">No team</span></div>
                            <div class="text-secondary small"><i class="fa-solid fa-envelope me-1 text-info"></i><span id="modal-emp-email">-</span></div>
                        </div>
                        <span class="badge" id="modal-status-badge">No Login</span>
                    </div>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <div class="p-2.5 bg-dark bg-opacity-25 border border-secondary border-opacity-10 rounded">
                            <div class="text-secondary small"><i class="fa-regular fa-clock me-1 text-warning"></i>Last Login Time</div>
                            <div class="fw-semibold text-white mt-1" id="modal-login-time">No login</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-2.5 bg-dark bg-opacity-25 border border-secondary border-opacity-10 rounded">
                            <div class="text-secondary small"><i class="fa-solid fa-stopwatch me-1 text-success"></i>Session Duration</div>
                            <div class="fw-semibold text-white mt-1" id="modal-session-duration">0h 0m</div>
                        </div>
                    </div>
                </div>

                <div class="p-3 bg-dark bg-opacity-25 border border-secondary border-opacity-10 rounded mb-3">
                    <div class="text-secondary small mb-1"><i class="fa-solid fa-location-dot me-1 text-danger"></i>Location Status</div>
                    <div class="fw-semibold" id="modal-location-status">Location stale/off</div>
                    <div class="text-secondary small mt-1 d-none" id="modal-location-time-wrap">Last Fix: <span id="modal-location-time">-</span></div>
                </div>

                <div class="d-flex gap-2 flex-wrap">
                    <a id="modal-day-activity-btn" href="#" class="btn btn-primary btn-sm flex-fill">
                        <i class="fa-solid fa-calendar-day me-1"></i>Today Activity
                    </a>
                    <a id="modal-route-btn" href="#" class="btn btn-outline-info btn-sm flex-fill">
                        <i class="fa-solid fa-route me-1"></i>View Route
                    </a>
                    <a id="modal-last-pin-btn" href="#" target="_blank" class="btn btn-outline-light btn-sm d-none">
                        <i class="fa-solid fa-map-location-dot me-1"></i>Last Pin
                    </a>
                </div>
            </div>
            <div class="modal-footer border-secondary py-2">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<style>
.team-member-card {
    transition: all 0.18s ease-in-out;
    background-color: rgba(30, 41, 59, 0.4) !important;
}
.team-member-card:hover {
    border-color: var(--primary) !important;
    transform: translateY(-1px);
    background-color: rgba(30, 41, 59, 0.8) !important;
}
.shadow-success-glow {
    box-shadow: 0 0 6px rgba(34, 197, 94, 0.7);
}
.shadow-warning-glow {
    box-shadow: 0 0 6px rgba(234, 179, 8, 0.7);
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // 1. Lightweight Real-Time Search Filter
    const searchInput = document.getElementById('team-search-input');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const query = this.value.toLowerCase().trim();
            const cols = document.querySelectorAll('.team-member-col');
            let visibleCount = 0;
            cols.forEach(col => {
                const name = col.getAttribute('data-member-name') || '';
                if (name.includes(query)) {
                    col.classList.remove('d-none');
                    visibleCount++;
                } else {
                    col.classList.add('d-none');
                }
            });
            const noMatchMsg = document.getElementById('no-team-members-found');
            if (noMatchMsg) {
                noMatchMsg.classList.toggle('d-none', visibleCount > 0);
            }
        });
    }

    // 2. Click-to-Expand Employee Detail Modal
    const detailModal = document.getElementById('employeeDetailModal');
    if (detailModal) {
        detailModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            if (!button) return;
            
            const rawData = button.getAttribute('data-member');
            if (!rawData) return;
            const data = JSON.parse(rawData);

            document.getElementById('modal-employee-name').textContent = data.name + ' - Overview';
            document.getElementById('modal-emp-name-title').textContent = data.name;
            document.getElementById('modal-team-name').textContent = data.team_name || 'No team assigned';
            document.getElementById('modal-emp-email').textContent = data.email || 'N/A';

            // Status Dot & Badge
            const dot = document.getElementById('modal-status-dot');
            const badge = document.getElementById('modal-status-badge');
            
            let tone = 'danger';
            let label = 'No Login';
            if (data.state === 'working') { tone = 'success'; label = 'Working'; }
            else if (data.state === 'late') { tone = 'warning'; label = 'Late'; }
            else if (data.state === 'checked_out') { tone = 'secondary'; label = 'Checked Out'; }

            dot.className = 'rounded-circle d-inline-block bg-' + tone;
            badge.className = 'badge bg-' + tone + '-subtle text-' + tone + ' border border-' + tone + '-subtle';
            badge.textContent = label;

            // Times
            document.getElementById('modal-login-time').textContent = data.login_at ? new Date(data.login_at).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}) : 'No login';
            const hours = Math.floor(data.worked_minutes / 60);
            const mins = data.worked_minutes % 60;
            document.getElementById('modal-session-duration').textContent = hours + 'h ' + mins + 'm';

            // Location
            const locEl = document.getElementById('modal-location-status');
            const locWrap = document.getElementById('modal-location-time-wrap');
            const locTime = document.getElementById('modal-location-time');
            const pinBtn = document.getElementById('modal-last-pin-btn');

            if (data.location_off) {
                locEl.className = 'fw-semibold text-danger';
                locEl.innerHTML = '<i class="fa-solid fa-location-crosshairs me-1"></i>Location stale or off';
            } else {
                locEl.className = 'fw-semibold text-success';
                locEl.innerHTML = '<i class="fa-solid fa-location-dot me-1"></i>Location active';
            }

            if (data.last_location_at) {
                locWrap.classList.remove('d-none');
                locTime.textContent = data.last_location_at;
            } else {
                locWrap.classList.add('d-none');
            }

            if (data.lat !== null && data.lng !== null) {
                pinBtn.classList.remove('d-none');
                pinBtn.href = `https://www.openstreetmap.org/?mlat=${encodeURIComponent(data.lat)}&mlon=${encodeURIComponent(data.lng)}#map=16/${encodeURIComponent(data.lat)}/${encodeURIComponent(data.lng)}`;
            } else {
                pinBtn.classList.add('d-none');
            }

            // Action Buttons
            document.getElementById('modal-day-activity-btn').href = `index.php?route=dashboard/day/${data.user_id}`;
            document.getElementById('modal-route-btn').href = `index.php?route=location/member/${data.user_id}`;
        });
    }
});
</script>
