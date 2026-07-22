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
    <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-3" role="alert" style="background: rgba(239, 68, 68, 0.1); color: var(--danger);">
        <i class="fa-solid fa-triangle-exclamation me-2"></i> <?php echo htmlspecialchars($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>
<?php if (!empty($_SESSION['report_error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-3" role="alert" style="background: rgba(239, 68, 68, 0.1); color: var(--danger);">
        <i class="fa-solid fa-triangle-exclamation me-2"></i> <?php echo htmlspecialchars($_SESSION['report_error']); unset($_SESSION['report_error']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<!-- Filters (Theme-aware Card) -->
<div class="pulse-card mb-4" style="background: var(--panel-dark); border: 1px solid var(--border-color); border-radius: 14px; padding: 1.5rem; box-shadow: var(--shadow-soft);">
    <form method="GET" class="row g-3 align-items-end">
        <input type="hidden" name="route" value="attendance/report">
        <div class="col-6 col-md-3">
            <label class="form-label" style="display:block; font-size:0.76rem; font-weight:700; color: var(--text-secondary); text-transform:uppercase; letter-spacing:0.4px; margin-bottom:0.35rem;">From Date</label>
            <input type="date" name="from" id="attendance_from" value="<?php echo htmlspecialchars($from); ?>" class="form-control" style="border-radius: 8px; border-color: var(--border-strong);">
        </div>
        <div class="col-6 col-md-3">
            <label class="form-label" style="display:block; font-size:0.76rem; font-weight:700; color: var(--text-secondary); text-transform:uppercase; letter-spacing:0.4px; margin-bottom:0.35rem;">To Date</label>
            <input type="date" name="to" id="attendance_to" value="<?php echo htmlspecialchars($to); ?>" class="form-control" style="border-radius: 8px; border-color: var(--border-strong);">
        </div>
        <div class="col-12 col-md-6 d-flex gap-2">
            <button type="submit" class="btn btn-primary px-4" style="background: var(--primary); border: none; border-radius: 8px; font-weight: 600;"
                    onmouseover="this.style.background='var(--primary-strong)'" onmouseout="this.style.background='var(--primary)'">
                <i class="fa-solid fa-filter me-2"></i>Apply Filter
            </button>
            <a class="btn btn-outline-success px-4" href="index.php?route=attendance/exportReport&from=<?php echo urlencode($from); ?>&to=<?php echo urlencode($to); ?>"
               style="border-color: var(--success); color: var(--success); border-radius: 8px; font-weight: 600;"
               onmouseover="this.style.background='var(--success)'; this.style.color='#fff';" onmouseout="this.style.background='transparent'; this.style.color='var(--success)';">
                <i class="fa-solid fa-file-csv me-2"></i>Export CSV
            </a>
        </div>
    </form>
</div>

<!-- Summary Tiles (Theme-aware) -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="pulse-card text-center" style="background: var(--panel-dark); border: 1px solid var(--border-color); border-radius: 12px; padding: 1.25rem; box-shadow: var(--shadow-soft);">
            <div class="card-title mb-1" style="font-size:0.75rem; font-weight:700; color: var(--text-secondary); text-transform:uppercase; letter-spacing:0.5px;">Total Records</div>
            <div class="fs-3 fw-bold" style="color: var(--text-primary); font-weight: 800;"><?php echo count($rows); ?></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="pulse-card text-center" style="background: var(--panel-dark); border: 1px solid var(--border-color); border-radius: 12px; padding: 1.25rem; box-shadow: var(--shadow-soft);">
            <div class="card-title mb-1" style="font-size:0.75rem; font-weight:700; color: var(--text-secondary); text-transform:uppercase; letter-spacing:0.5px;">Late Logins</div>
            <div class="fs-3 fw-bold text-danger" style="font-weight: 800;"><?php echo $totLate; ?></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="pulse-card text-center" style="background: var(--panel-dark); border: 1px solid var(--border-color); border-radius: 12px; padding: 1.25rem; box-shadow: var(--shadow-soft);">
            <div class="card-title mb-1" style="font-size:0.75rem; font-weight:700; color: var(--text-secondary); text-transform:uppercase; letter-spacing:0.5px;">Early Logouts</div>
            <div class="fs-3 fw-bold text-warning" style="font-weight: 800;"><?php echo $totEarly; ?></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="pulse-card text-center" style="background: var(--panel-dark); border: 1px solid var(--border-color); border-radius: 12px; padding: 1.25rem; box-shadow: var(--shadow-soft);">
            <div class="card-title mb-1" style="font-size:0.75rem; font-weight:700; color: var(--text-secondary); text-transform:uppercase; letter-spacing:0.5px;">Total Hours</div>
            <div class="fs-3 fw-bold" style="color: var(--text-primary); font-weight: 800;"><?php echo $totHrs; ?></div>
        </div>
    </div>
</div>

<!-- Table Container -->
<div class="pulse-card" style="background: var(--panel-dark); border: 1px solid var(--border-color); border-radius: 14px; padding: 1.75rem; box-shadow: var(--shadow-soft);">
    
    <!-- Table Header Section -->
    <div style="display:flex; align-items:center; gap:0.6rem; background: var(--primary-soft); padding:0.85rem 1.1rem; border-radius:10px; border-left:4px solid var(--primary); margin-bottom:1.5rem;">
        <i class="fa-solid fa-list-check" style="color: var(--primary); font-size:1.05rem;"></i>
        <h5 style="margin:0; font-size:0.98rem; font-weight:700; color: var(--primary);">
            Attendance Logs (<?php echo htmlspecialchars($from); ?> &rarr; <?php echo htmlspecialchars($to); ?>)
        </h5>
    </div>

    <div class="table-responsive">
        <table class="table align-middle mb-0" id="attendance-report-table" style="border-collapse: separate; border-spacing: 0;">
            <thead>
                <tr style="background: var(--primary-soft);">
                    <th style="background: var(--primary-soft); color: var(--text-secondary); font-size:0.77rem; font-weight:700; text-transform:uppercase; letter-spacing:0.4px; padding:0.85rem 1rem; border-bottom:2px solid var(--border-color);">Date</th>
                    <th style="background: var(--primary-soft); color: var(--text-secondary); font-size:0.77rem; font-weight:700; text-transform:uppercase; letter-spacing:0.4px; padding:0.85rem 1rem; border-bottom:2px solid var(--border-color);">Employee</th>
                    <th style="background: var(--primary-soft); color: var(--text-secondary); font-size:0.77rem; font-weight:700; text-transform:uppercase; letter-spacing:0.4px; padding:0.85rem 1rem; border-bottom:2px solid var(--border-color);">Team</th>
                    <th style="background: var(--primary-soft); color: var(--text-secondary); font-size:0.77rem; font-weight:700; text-transform:uppercase; letter-spacing:0.4px; padding:0.85rem 1rem; border-bottom:2px solid var(--border-color);">In</th>
                    <th style="background: var(--primary-soft); color: var(--text-secondary); font-size:0.77rem; font-weight:700; text-transform:uppercase; letter-spacing:0.4px; padding:0.85rem 1rem; border-bottom:2px solid var(--border-color);">Out</th>
                    <th style="background: var(--primary-soft); color: var(--text-secondary); font-size:0.77rem; font-weight:700; text-transform:uppercase; letter-spacing:0.4px; padding:0.85rem 1rem; border-bottom:2px solid var(--border-color);">Worked</th>
                    <th style="background: var(--primary-soft); color: var(--text-secondary); font-size:0.77rem; font-weight:700; text-transform:uppercase; letter-spacing:0.4px; padding:0.85rem 1rem; border-bottom:2px solid var(--border-color);">Break</th>
                    <th style="background: var(--primary-soft); color: var(--text-secondary); font-size:0.77rem; font-weight:700; text-transform:uppercase; letter-spacing:0.4px; padding:0.85rem 1rem; border-bottom:2px solid var(--border-color);">Flags</th>
                    <th style="background: var(--primary-soft); color: var(--text-secondary); font-size:0.77rem; font-weight:700; text-transform:uppercase; letter-spacing:0.4px; padding:0.85rem 1rem; border-bottom:2px solid var(--border-color);">Status</th>
                    <th style="background: var(--primary-soft); color: var(--text-secondary); font-size:0.77rem; font-weight:700; text-transform:uppercase; letter-spacing:0.4px; padding:0.85rem 1rem; border-bottom:2px solid var(--border-color);">Approval</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($rows)): ?>
                <tr>
                    <td colspan="10" class="text-center py-5" style="color: var(--text-secondary);">
                        No attendance records for this date range.
                    </td>
                </tr>
            <?php else: foreach ($rows as $i => $r):
                $rowBg = ($i % 2 === 0) ? 'var(--panel-dark)' : 'var(--surface-soft)';
            ?>
                <tr style="background: <?php echo $rowBg; ?>; border-bottom: 1px solid var(--border-color); transition: background 0.15s;"
                    onmouseover="this.style.background='var(--primary-soft)'" onmouseout="this.style.background='<?php echo $rowBg; ?>'">
                    <td style="padding:0.85rem 1rem; color: var(--text-secondary); font-size:0.88rem;">
                        <?php echo date('M d, Y', strtotime($r->work_date)); ?>
                    </td>
                    <td style="padding:0.85rem 1rem; font-weight:600; color: var(--text-primary); font-size:0.88rem;">
                        <?php echo htmlspecialchars($r->employee_name); ?>
                        <a class="text-primary small ms-1" title="View route details"
                           href="index.php?route=location/member/<?php echo (int)$r->user_id; ?>&date=<?php echo urlencode($r->work_date); ?>"
                           style="text-decoration: none;">
                            <i class="fa-solid fa-route"></i>
                        </a>
                    </td>
                    <td style="padding:0.85rem 1rem; color: var(--text-secondary); font-size:0.88rem;">
                        <?php echo htmlspecialchars($r->team_name ?? '—'); ?>
                    </td>
                    <td style="padding:0.85rem 1rem; color: var(--text-primary); font-size:0.88rem; font-weight: 500;">
                        <?php echo $r->login_at ? formatToLocalTime($r->login_at, 'h:i A') : '—'; ?>
                    </td>
                    <td style="padding:0.85rem 1rem; color: var(--text-primary); font-size:0.88rem; font-weight: 500;">
                        <?php echo $r->logout_at ? formatToLocalTime($r->logout_at, 'h:i A') : '—'; ?>
                    </td>
                    <td style="padding:0.85rem 1rem; color: var(--text-primary); font-size:0.88rem; font-weight: 600;">
                        <?php echo floor($r->worked_minutes / 60) . 'h ' . ($r->worked_minutes % 60) . 'm'; ?>
                    </td>
                    <td style="padding:0.85rem 1rem; color: var(--text-secondary); font-size:0.88rem;">
                        <?php echo (int)$r->break_minutes; ?>m
                    </td>
                    <td style="padding:0.85rem 1rem;">
                        <div class="d-flex gap-1 flex-wrap">
                            <?php if ($r->is_late): ?><span class="badge bg-danger-subtle text-danger" style="font-weight:700;">L</span> <?php endif; ?>
                            <?php if ($r->is_early_logout): ?><span class="badge bg-warning-subtle text-warning" style="font-weight:700;">E</span> <?php endif; ?>
                            <?php if ($r->geofence_ok === '0' || $r->geofence_ok === 0): ?><span class="badge bg-info-subtle text-info" style="font-weight:700;">G</span><?php endif; ?>
                        </div>
                    </td>
                    <td style="padding:0.85rem 1rem;">
                        <?php
                            $statusClass = [
                                'present'  => 'success',
                                'half_day' => 'warning',
                                'wfh'      => 'info',
                                'absent'   => 'danger'
                            ][strtolower($r->status)] ?? 'secondary';
                        ?>
                        <span class="badge bg-<?php echo $statusClass; ?>-subtle text-<?php echo $statusClass; ?>" style="font-weight:700;">
                            <?php echo strtoupper($r->status); ?>
                        </span>
                    </td>
                    <td style="padding:0.85rem 1rem;">
                        <?php
                            $acClass = [
                                'Approved' => 'success',
                                'Pending'  => 'warning',
                                'Rejected' => 'danger'
                            ][$r->attendance_status] ?? 'secondary';
                        ?>
                        <span class="badge bg-<?php echo $acClass; ?>-subtle text-<?php echo $acClass; ?>" style="font-weight:700;">
                            <?php echo strtoupper($r->attendance_status ?? ''); ?>
                        </span>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <div style="border-top: 1px solid var(--border-color); margin-top: 1.5rem; padding-top: 1rem; color: var(--text-secondary); font-size: 0.8rem;">
        <strong>Flags Guide:</strong> <span class="badge bg-danger-subtle text-danger me-1">L</span> Late Login &middot; <span class="badge bg-warning-subtle text-warning me-1">E</span> Early Logout &middot; <span class="badge bg-info-subtle text-info">G</span> Outside Geofence Boundary
    </div>
</div>
