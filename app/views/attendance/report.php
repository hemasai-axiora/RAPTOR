<?php
// Quick roll-ups for the summary tiles
$totPresent = 0; $totLate = 0; $totEarly = 0; $totMins = 0;
if (!empty($rows)) {
    foreach ($rows as $r) {
        if (in_array($r->status, ['present', 'half_day', 'wfh'])) { $totPresent++; }
        if ($r->is_late) { $totLate++; }
        if ($r->is_early_logout) { $totEarly++; }
        $totMins += (int) $r->worked_minutes;
    }
}
$totHrs = round($totMins / 60, 1);
?>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger border-0 shadow mb-3" style="background: rgba(220, 53, 69, 0.15); color: #e63946;">
        <i class="fa-solid fa-triangle-exclamation me-2"></i> <?php echo htmlspecialchars($error); ?>
    </div>
<?php endif; ?>
<?php if (!empty($_SESSION['report_error'])): ?>
    <div class="alert alert-danger border-0 shadow mb-3" style="background: rgba(220, 53, 69, 0.15); color: #e63946;">
        <i class="fa-solid fa-triangle-exclamation me-2"></i> <?php echo htmlspecialchars($_SESSION['report_error']); unset($_SESSION['report_error']); ?>
    </div>
<?php endif; ?>

<!-- Filters -->
<div class="pulse-card mb-3">
    <form method="GET" class="row g-2 align-items-end">
        <input type="hidden" name="route" value="attendance/report">
        <div class="col-6 col-md-3">
            <label class="form-label text-secondary small">From</label>
            <input type="date" name="from" id="attendance_from" value="<?php echo htmlspecialchars($from); ?>" class="form-control bg-dark border-secondary text-white">
        </div>
        <div class="col-6 col-md-3">
            <label class="form-label text-secondary small">To</label>
            <input type="date" name="to" id="attendance_to" value="<?php echo htmlspecialchars($to); ?>" class="form-control bg-dark border-secondary text-white">
        </div>
        <div class="col-12 col-md-6 d-flex gap-2">
            <button class="btn btn-primary" style="background: var(--primary); border: none;"><i class="fa-solid fa-filter me-2"></i>Apply</button>
            <a class="btn btn-outline-success" href="index.php?route=attendance/exportReport&from=<?php echo urlencode($from); ?>&to=<?php echo urlencode($to); ?>">
                <i class="fa-solid fa-file-csv me-2"></i>Export CSV
            </a>
        </div>
    </form>
</div>

<!-- Summary tiles -->
<div class="row g-2 mb-3">
    <div class="col-6 col-md-3"><div class="pulse-card text-center"><div class="card-title mb-1">Records</div><div class="fs-3 fw-bold text-white"><?php echo count($rows); ?></div></div></div>
    <div class="col-6 col-md-3"><div class="pulse-card text-center"><div class="card-title mb-1">Late</div><div class="fs-3 fw-bold text-danger"><?php echo $totLate; ?></div></div></div>
    <div class="col-6 col-md-3"><div class="pulse-card text-center"><div class="card-title mb-1">Early Logout</div><div class="fs-3 fw-bold text-warning"><?php echo $totEarly; ?></div></div></div>
    <div class="col-6 col-md-3"><div class="pulse-card text-center"><div class="card-title mb-1">Total Hours</div><div class="fs-3 fw-bold text-white"><?php echo $totHrs; ?></div></div></div>
</div>

<!-- Table -->
<div class="pulse-card">
    <h5 class="text-white mb-3">Attendance <?php echo htmlspecialchars($from); ?> → <?php echo htmlspecialchars($to); ?></h5>
    <div class="table-scroll">
        <table class="table table-dark table-hover align-middle table-stack">
            <thead>
                <tr class="text-secondary">
                    <th>Date</th><th>Employee</th><th>Team</th><th>In</th><th>Out</th><th>Worked</th><th>Break</th><th>Flags</th><th>Status</th><th>Approval</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($rows)): ?>
                <tr><td colspan="10" class="text-center text-secondary py-4">No attendance records for this range.</td></tr>
            <?php else: foreach ($rows as $r): ?>
                <tr>
                    <td data-label="Date"><?php echo htmlspecialchars($r->work_date); ?></td>
                    <td data-label="Employee" class="text-white">
                        <?php echo htmlspecialchars($r->employee_name); ?>
                        <a class="link-info small ms-1" title="View route"
                           href="index.php?route=location/member/<?php echo (int)$r->user_id; ?>&date=<?php echo urlencode($r->work_date); ?>">
                            <i class="fa-solid fa-route"></i>
                        </a>
                    </td>
                    <td data-label="Team"><?php echo htmlspecialchars($r->team_name ?? '—'); ?></td>
                    <td data-label="In"><?php echo $r->login_at ? date('h:i A', strtotime($r->login_at)) : '—'; ?></td>
                    <td data-label="Out"><?php echo $r->logout_at ? date('h:i A', strtotime($r->logout_at)) : '—'; ?></td>
                    <td data-label="Worked"><?php echo floor($r->worked_minutes / 60) . 'h ' . ($r->worked_minutes % 60) . 'm'; ?></td>
                    <td data-label="Break"><?php echo (int)$r->break_minutes; ?>m</td>
                    <td data-label="Flags">
                        <?php if ($r->is_late): ?><span class="badge bg-danger-subtle text-danger">L</span> <?php endif; ?>
                        <?php if ($r->is_early_logout): ?><span class="badge bg-warning-subtle text-warning">E</span> <?php endif; ?>
                        <?php if ($r->geofence_ok === '0' || $r->geofence_ok === 0): ?><span class="badge bg-info-subtle text-info">G</span><?php endif; ?>
                    </td>
                    <td data-label="Status"><span class="badge bg-secondary-subtle text-secondary"><?php echo strtoupper($r->status); ?></span></td>
                    <td data-label="Approval">
                        <?php
                            $ac = ['auto' => 'secondary', 'approved' => 'success', 'pending' => 'warning', 'rejected' => 'danger'][$r->approval_status] ?? 'secondary';
                        ?>
                        <span class="badge bg-<?php echo $ac; ?>-subtle text-<?php echo $ac; ?>"><?php echo strtoupper($r->approval_status); ?></span>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <p class="text-secondary small mt-2 mb-0"><strong>Flags:</strong> L = Late, E = Early logout, G = Outside geofence.</p>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const fromInput = document.getElementById('attendance_from');
    const toInput = document.getElementById('attendance_to');
    
    if (fromInput && toInput) {
        fromInput.addEventListener('change', function() {
            if (fromInput.value) {
                toInput.min = fromInput.value;
            }
        });
        toInput.addEventListener('change', function() {
            if (toInput.value) {
                fromInput.max = toInput.value;
            }
        });
        
        // Initial boundaries
        if (fromInput.value) {
            toInput.min = fromInput.value;
        }
        if (toInput.value) {
            fromInput.max = toInput.value;
        }
    }
});
</script>
