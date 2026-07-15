<?php $empName = $employee ? $employee->name : 'Employee'; ?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h4 class="text-white mb-1"><?php echo htmlspecialchars($empName); ?> - Day Drill-down</h4>
        <div class="text-secondary" style="font-size:0.9rem;"><?php echo htmlspecialchars($date); ?><?php echo $employee && $employee->team_name ? ' · ' . htmlspecialchars($employee->team_name) : ''; ?></div>
    </div>
    <div class="d-flex gap-2">
        <form method="GET" action="index.php" class="d-flex gap-2">
            <input type="hidden" name="route" value="dashboard/day/<?php echo (int) $user_id; ?>">
            <input type="date" name="date" value="<?php echo htmlspecialchars($date); ?>" class="form-control bg-dark border-secondary text-white">
            <button class="btn btn-outline-light">Go</button>
        </form>
        <a class="btn btn-outline-info" href="index.php?route=location/member/<?php echo (int) $user_id; ?>&date=<?php echo urlencode($date); ?>">Route</a>
        <a class="btn btn-outline-secondary" href="index.php?route=dashboard/monitoring">Back</a>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="pulse-card">
            <div class="card-title">Attendance</div>
            <?php if ($attendance): ?>
                <div class="text-white fw-semibold"><?php echo strtoupper($attendance->status); ?></div>
                <div class="text-secondary small mt-2">In: <?php echo $attendance->login_at ? htmlspecialchars(date('h:i A', strtotime($attendance->login_at))) : '-'; ?></div>
                <div class="text-secondary small">Out: <?php echo $attendance->logout_at ? htmlspecialchars(date('h:i A', strtotime($attendance->logout_at))) : '-'; ?></div>
                <div class="text-secondary small">Worked: <?php echo floor((int) $attendance->worked_minutes / 60) . 'h ' . ((int) $attendance->worked_minutes % 60) . 'm'; ?></div>
            <?php else: ?>
                <div class="text-danger">No login</div>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-md-3"><div class="pulse-card"><div class="card-title">Tasks</div><div class="h3 text-white"><?php echo count($tasks); ?></div></div></div>
    <div class="col-md-3"><div class="pulse-card"><div class="card-title">Communications</div><div class="h3 text-white"><?php echo count($communications); ?></div></div></div>
    <div class="col-md-3"><div class="pulse-card"><div class="card-title">Meetings/Demos</div><div class="h3 text-white"><?php echo count($meetings); ?></div></div></div>
</div>

<div class="row g-4">
    <div class="col-xl-6">
        <div class="pulse-card h-100">
            <h5 class="text-white mb-3">Tasks</h5>
            <?php if (empty($tasks)): ?><div class="text-secondary">No tasks for this day.</div><?php endif; ?>
            <?php foreach ($tasks as $task): ?>
                <div class="border-bottom border-secondary border-opacity-10 pb-3 mb-3">
                    <div class="d-flex justify-content-between">
                        <span class="text-white fw-semibold"><?php echo htmlspecialchars($task->title); ?></span>
                        <span class="badge bg-secondary bg-opacity-25 text-white border border-secondary border-opacity-25"><?php echo strtoupper($task->status); ?></span>
                    </div>
                    <div class="text-secondary small"><?php echo (int) $task->progress_percent; ?>% · Review <?php echo htmlspecialchars(str_replace('_', ' ', $task->review_status)); ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="col-xl-6">
        <div class="pulse-card h-100">
            <h5 class="text-white mb-3">Communications</h5>
            <?php if (empty($communications)): ?><div class="text-secondary">No communications logged.</div><?php endif; ?>
            <?php foreach ($communications as $comm): ?>
                <div class="border-bottom border-secondary border-opacity-10 pb-3 mb-3">
                    <div class="text-white"><?php echo strtoupper(htmlspecialchars($comm->channel)); ?> - <?php echo htmlspecialchars($comm->outcome ?: $comm->direction); ?></div>                    <div class="text-secondary small"><?php echo htmlspecialchars(formatToLocalTime($comm->happened_at, 'h:i A')); ?><?php echo $comm->lead_id ? ' · ' . htmlspecialchars(trim($comm->first_name . ' ' . ($comm->last_name ?? ''))) : ''; ?></div>
                    <?php if ($comm->note): ?><div class="text-secondary small"><?php echo htmlspecialchars($comm->note); ?></div><?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
 
    <div class="col-xl-6">
        <div class="pulse-card h-100">
            <h5 class="text-white mb-3">Meetings & Demos</h5>
            <?php if (empty($meetings)): ?><div class="text-secondary">No meetings or demos scheduled.</div><?php endif; ?>
            <?php foreach ($meetings as $meeting): ?>
                <div class="border-bottom border-secondary border-opacity-10 pb-3 mb-3">
                    <div class="d-flex justify-content-between">
                        <span class="text-white fw-semibold"><?php echo htmlspecialchars($meeting->title); ?></span>
                        <span class="badge bg-info bg-opacity-25 text-info border border-info border-opacity-25"><?php echo strtoupper($meeting->type); ?></span>
                    </div>
                    <div class="text-secondary small"><?php echo htmlspecialchars(formatToLocalTime($meeting->scheduled_start, 'h:i A')); ?> · <?php echo strtoupper($meeting->status); ?></div>
                    <?php if ($meeting->outcome): ?><div class="text-secondary small"><?php echo htmlspecialchars($meeting->outcome); ?></div><?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
 
    <div class="col-xl-6">
        <div class="pulse-card h-100">
            <h5 class="text-white mb-3">Leads & Follow-ups</h5>
            <div class="row g-3">
                <div class="col-md-6">
                    <h6 class="text-secondary">Leads Created</h6>
                    <?php if (empty($leads)): ?><div class="text-secondary small">None</div><?php endif; ?>
                    <?php foreach ($leads as $lead): ?>
                        <div class="mb-2"><a class="text-white" href="index.php?route=leads/view/<?php echo $lead->lead_id; ?>"><?php echo htmlspecialchars($lead->first_name . ' ' . ($lead->last_name ?? '')); ?></a><div class="text-secondary small"><?php echo strtoupper($lead->status); ?></div></div>
                    <?php endforeach; ?>
                </div>
                <div class="col-md-6">
                    <h6 class="text-secondary">Follow-ups</h6>
                    <?php if (empty($followups)): ?><div class="text-secondary small">None</div><?php endif; ?>
                    <?php foreach ($followups as $f): ?>
                        <div class="mb-2"><span class="text-white"><?php echo htmlspecialchars($f->first_name . ' ' . ($f->last_name ?? '')); ?></span><div class="text-secondary small"><?php echo htmlspecialchars(formatToLocalTime($f->due_at, 'h:i A')) . ' · ' . strtoupper($f->status); ?></div></div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
 
    <div class="col-12">
        <div class="pulse-card">
            <h5 class="text-white mb-3">Recent Location Points</h5>
            <?php if (empty($locations)): ?><div class="text-secondary">No location points for this date.</div><?php endif; ?>
            <div class="table-responsive">
                <table class="table table-dark table-hover align-middle table-stack">
                    <tbody>
                        <?php foreach ($locations as $loc): ?>
                            <tr>
                                <td data-label="Time"><?php echo htmlspecialchars(formatToLocalTime($loc->captured_at, 'h:i A')); ?></td>
                                <td data-label="Source"><?php echo htmlspecialchars($loc->source); ?></td>
                                <td data-label="Coords" class="font-monospace small"><?php echo htmlspecialchars($loc->lat . ', ' . $loc->lng); ?></td>
                                <td data-label="Accuracy"><?php echo $loc->accuracy_m !== null ? (int) $loc->accuracy_m . 'm' : '-'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
