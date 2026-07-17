<?php
$csrf = $_SESSION['csrf_token'];
$fileUrl = function ($key) { return 'index.php?route=file/show&key=' . urlencode($key); };
?>

<div class="pulse-card" style="background: var(--panel-dark); border: 1px solid var(--border-color); border-radius: 14px; padding: 1.75rem; box-shadow: var(--shadow-soft);">

    <!-- Header Section -->
    <div style="display:flex; align-items:center; justify-content:space-between; gap:0.75rem; background: var(--primary-soft); padding:0.85rem 1.1rem; border-radius:10px; border-left:4px solid var(--primary); margin-bottom:1.5rem;">
        <div style="display:flex; align-items:center; gap:0.6rem;">
            <i class="fa-solid fa-user-check" style="color: var(--primary); font-size:1.05rem;"></i>
            <h4 style="margin:0; font-size:1.05rem; font-weight:700; color: var(--primary);">Attendance Approvals</h4>
        </div>
        <span class="badge bg-warning-subtle text-warning border border-warning-subtle" style="font-weight:700;">
            <?php echo count($pending); ?> Pending Request<?php echo count($pending) !== 1 ? 's' : ''; ?>
        </span>
    </div>

    <?php if (empty($pending)): ?>
        <div class="text-center py-5">
            <i class="fa-solid fa-circle-check fa-2x mb-3 d-block" style="color: var(--success);"></i>
            <p style="color: var(--text-secondary); margin: 0; font-size: 0.93rem;">No pending attendance exceptions in your team. All caught up!</p>
        </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table align-middle mb-0" id="attendance-approvals-table" style="border-collapse: separate; border-spacing: 0;">
            <thead>
                <tr style="background: var(--primary-soft);">
                    <th style="background: var(--primary-soft); color: var(--text-secondary); font-size:0.77rem; font-weight:700; text-transform:uppercase; letter-spacing:0.4px; padding:0.85rem 1rem; border-bottom:2px solid var(--border-color);">Employee</th>
                    <th style="background: var(--primary-soft); color: var(--text-secondary); font-size:0.77rem; font-weight:700; text-transform:uppercase; letter-spacing:0.4px; padding:0.85rem 1rem; border-bottom:2px solid var(--border-color);">Date</th>
                    <th style="background: var(--primary-soft); color: var(--text-secondary); font-size:0.77rem; font-weight:700; text-transform:uppercase; letter-spacing:0.4px; padding:0.85rem 1rem; border-bottom:2px solid var(--border-color);">Check-in</th>
                    <th style="background: var(--primary-soft); color: var(--text-secondary); font-size:0.77rem; font-weight:700; text-transform:uppercase; letter-spacing:0.4px; padding:0.85rem 1rem; border-bottom:2px solid var(--border-color);">Flags</th>
                    <th style="background: var(--primary-soft); color: var(--text-secondary); font-size:0.77rem; font-weight:700; text-transform:uppercase; letter-spacing:0.4px; padding:0.85rem 1rem; border-bottom:2px solid var(--border-color);">Location</th>
                    <th style="background: var(--primary-soft); color: var(--text-secondary); font-size:0.77rem; font-weight:700; text-transform:uppercase; letter-spacing:0.4px; padding:0.85rem 1rem; border-bottom:2px solid var(--border-color);">Selfie</th>
                    <th style="background: var(--primary-soft); color: var(--text-secondary); font-size:0.77rem; font-weight:700; text-transform:uppercase; letter-spacing:0.4px; padding:0.85rem 1rem; border-bottom:2px solid var(--border-color); text-align:right;">Decision</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($pending as $i => $p):
                $rowBg = ($i % 2 === 0) ? 'var(--panel-dark)' : 'var(--surface-soft)';
            ?>
                <tr style="background: <?php echo $rowBg; ?>; border-bottom: 1px solid var(--border-color); transition: background 0.15s;"
                    onmouseover="this.style.background='var(--primary-soft)'" onmouseout="this.style.background='<?php echo $rowBg; ?>'">
                    <td style="padding:0.85rem 1rem; font-weight:600; color: var(--text-primary); font-size:0.88rem;">
                        <?php echo htmlspecialchars($p->employee_name); ?>
                    </td>
                    <td style="padding:0.85rem 1rem; color: var(--text-secondary); font-size:0.88rem;">
                        <?php echo date('M d, Y', strtotime($p->work_date)); ?>
                    </td>
                    <td style="padding:0.85rem 1rem; color: var(--text-primary); font-size:0.88rem; font-weight: 500;">
                        <?php echo $p->login_at ? date('h:i A', strtotime($p->login_at)) : '—'; ?>
                    </td>
                    <td style="padding:0.85rem 1rem;">
                        <div class="d-flex gap-1 flex-wrap">
                            <?php if ($p->is_late): ?>
                                <span class="badge bg-danger-subtle text-danger" style="font-weight:700;">LATE</span> 
                            <?php endif; ?>
                            <?php if ($p->geofence_ok === '0' || $p->geofence_ok === 0): ?>
                                <span class="badge bg-warning-subtle text-warning" style="font-weight:700;">OUT OF FENCE</span>
                            <?php endif; ?>
                            <?php if (!$p->logout_at): ?>
                                <span class="badge bg-secondary-subtle text-secondary" style="font-weight:700;">NO LOGOUT</span>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td style="padding:0.85rem 1rem; font-size:0.88rem;">
                        <?php if ($p->login_lat !== null): ?>
                            <a class="text-primary small text-decoration-none fw-semibold" target="_blank" rel="noopener"
                               href="https://www.openstreetmap.org/?mlat=<?php echo $p->login_lat; ?>&mlon=<?php echo $p->login_lng; ?>#map=17/<?php echo $p->login_lat; ?>/<?php echo $p->login_lng; ?>">
                               <i class="fa-solid fa-map-pin me-1"></i>View Map
                            </a>
                        <?php else: ?>
                            <span style="color: var(--text-muted);">—</span>
                        <?php endif; ?>
                    </td>
                    <td style="padding:0.85rem 1rem;">
                        <?php if ($p->login_selfie_url): ?>
                            <a href="<?php echo $fileUrl($p->login_selfie_url); ?>" target="_blank" style="display: inline-block;">
                                <img src="<?php echo $fileUrl($p->login_selfie_url); ?>" style="width:40px; height:40px; object-fit:cover; border-radius:8px; border: 1px solid var(--border-color);">
                            </a>
                        <?php else: ?>
                            <span style="color: var(--text-muted);">—</span>
                        <?php endif; ?>
                    </td>
                    <td style="padding:0.85rem 1rem; text-align:right;">
                        <div class="d-inline-flex gap-2">
                            <form action="index.php?route=attendance/approve/<?php echo (int)$p->attendance_id; ?>" method="POST">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                                <button type="submit" class="btn btn-sm" style="background: var(--primary); color: #fff; border: none; border-radius: 6px; padding: 0.3rem 0.8rem; font-size: 0.82rem; font-weight: 600;"
                                        onmouseover="this.style.background='var(--primary-strong)'" onmouseout="this.style.background='var(--primary)'">
                                    <i class="fa-solid fa-check me-1"></i>Approve
                                </button>
                            </form>
                            <button class="btn btn-sm btn-reject"
                                    data-id="<?php echo (int)$p->attendance_id; ?>"
                                    data-name="<?php echo htmlspecialchars($p->employee_name); ?>"
                                    data-bs-toggle="modal" data-bs-target="#rejectModal"
                                    style="background: #FEE2E2; color: #991B1B; border: 1px solid #FECACA; border-radius: 6px; padding: 0.3rem 0.8rem; font-size: 0.82rem; font-weight: 600;"
                                    onmouseover="this.style.background='#EF4444'; this.style.color='#fff';" onmouseout="this.style.background='#FEE2E2'; this.style.color='#991B1B';">
                                <i class="fa-solid fa-xmark me-1"></i>Reject
                            </button>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- Reject Modal (Theme-aware) -->
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="background: var(--panel-dark); border: 1px solid var(--border-color); border-radius: 16px; box-shadow: var(--shadow-soft);">
            <div class="modal-header" style="background: var(--primary-soft); border-bottom: 1px solid var(--border-color); border-radius: 16px 16px 0 0; padding: 1rem 1.25rem;">
                <div class="d-flex align-items-center gap-2">
                    <i class="fa-solid fa-user-xmark" style="color: var(--primary);"></i>
                    <h5 class="modal-title" style="margin:0; font-weight:700; color: var(--primary);">Reject Attendance</h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="rejectForm" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                <div class="modal-body" style="padding: 1.5rem;">
                    <p style="color: var(--text-secondary); font-size: 0.9rem; margin-bottom: 1rem;">
                        Rejecting attendance for <strong id="rej-name" style="color: var(--text-primary);"></strong>.
                    </p>
                    <div class="mb-3">
                        <label class="form-label" style="display:block; font-size:0.76rem; font-weight:700; color: var(--text-secondary); text-transform:uppercase; letter-spacing:0.4px; margin-bottom:0.35rem;">Reason / Remark</label>
                        <textarea name="remark" rows="3" class="form-control" placeholder="e.g. Selfie unclear, location mismatch" required style="border-radius: 8px; border-color: var(--border-strong);"></textarea>
                    </div>
                </div>
                <div class="modal-footer" style="border-top: 1px solid var(--border-color); padding: 1rem 1.25rem;">
                    <button type="button" class="btn btn-sm" data-bs-dismiss="modal" style="border:1px solid var(--border-strong); color: var(--text-secondary); border-radius:8px; padding:0.45rem 1rem;">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-danger" style="background: var(--danger); border: none; border-radius: 8px; padding: 0.45rem 1.2rem; font-weight: 600;">Confirm Reject</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(function () {
    $('.btn-reject').on('click', function () {
        $('#rej-name').text($(this).data('name'));
        $('#rejectForm').attr('action', 'index.php?route=attendance/reject/' + $(this).data('id'));
    });
});
</script>
