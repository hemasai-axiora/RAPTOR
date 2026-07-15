<?php
$csrf = $_SESSION['csrf_token'];
$fileUrl = function ($key) { return 'index.php?route=file/show&key=' . urlencode($key); };
?>

<div class="pulse-card">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="text-white mb-0">Attendance Approvals</h4>
        <span class="badge bg-warning-subtle text-warning border border-warning-subtle"><?php echo count($pending); ?> pending</span>
    </div>

    <?php if (empty($pending)): ?>
        <div class="text-center text-secondary py-5">
            <i class="fa-solid fa-circle-check fs-1 mb-3 d-block text-success"></i>
            No pending attendance exceptions in your team. All clear.
        </div>
    <?php else: ?>
    <div class="table-scroll">
        <table class="table table-dark table-hover align-middle table-stack">
            <thead>
                <tr class="text-secondary">
                    <th>Employee</th><th>Date</th><th>Check-in</th><th>Flags</th><th>Location</th><th>Selfie</th><th class="text-end">Decision</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($pending as $p): ?>
                <tr>
                    <td data-label="Employee" class="text-white fw-semibold"><?php echo htmlspecialchars($p->employee_name); ?></td>
                    <td data-label="Date"><?php echo htmlspecialchars($p->work_date); ?></td>
                    <td data-label="Check-in"><?php echo $p->login_at ? date('h:i A', strtotime($p->login_at)) : '—'; ?></td>
                    <td data-label="Flags">
                        <?php if ($p->is_late): ?><span class="badge bg-danger-subtle text-danger">LATE</span> <?php endif; ?>
                        <?php if ($p->geofence_ok === '0' || $p->geofence_ok === 0): ?><span class="badge bg-warning-subtle text-warning">OUT OF FENCE</span><?php endif; ?>
                        <?php if (!$p->logout_at): ?><span class="badge bg-secondary-subtle text-secondary">NO LOGOUT</span><?php endif; ?>
                    </td>
                    <td data-label="Location">
                        <?php if ($p->login_lat !== null): ?>
                            <a class="link-info small" target="_blank" rel="noopener"
                               href="https://www.openstreetmap.org/?mlat=<?php echo $p->login_lat; ?>&mlon=<?php echo $p->login_lng; ?>#map=17/<?php echo $p->login_lat; ?>/<?php echo $p->login_lng; ?>">
                               <i class="fa-solid fa-map-pin me-1"></i>View
                            </a>
                        <?php else: ?><span class="text-secondary">—</span><?php endif; ?>
                    </td>
                    <td data-label="Selfie">
                        <?php if ($p->login_selfie_url): ?>
                            <a href="<?php echo $fileUrl($p->login_selfie_url); ?>" target="_blank">
                                <img src="<?php echo $fileUrl($p->login_selfie_url); ?>" style="width:44px;height:44px;object-fit:cover;border-radius:8px;">
                            </a>
                        <?php else: ?><span class="text-secondary">—</span><?php endif; ?>
                    </td>
                    <td data-label="Decision" class="text-end">
                        <div class="d-inline-flex gap-2">
                            <form action="index.php?route=attendance/approve/<?php echo (int)$p->attendance_id; ?>" method="POST">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                                <button class="btn btn-outline-success btn-sm"><i class="fa-solid fa-check"></i> Approve</button>
                            </form>
                            <button class="btn btn-outline-danger btn-sm btn-reject"
                                    data-id="<?php echo (int)$p->attendance_id; ?>"
                                    data-name="<?php echo htmlspecialchars($p->employee_name); ?>"
                                    data-bs-toggle="modal" data-bs-target="#rejectModal">
                                <i class="fa-solid fa-xmark"></i> Reject
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

<!-- Reject modal -->
<div class="modal fade" id="rejectModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content bg-dark text-white border-secondary">
    <div class="modal-header border-secondary"><h5 class="modal-title">Reject Attendance</h5><button class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
    <form id="rejectForm" method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
        <div class="modal-body">
            <p class="text-secondary small mb-2">Rejecting attendance for <strong id="rej-name" class="text-white"></strong>.</p>
            <label class="form-label text-secondary">Reason / remark</label>
            <textarea name="remark" rows="3" class="form-control bg-dark border-secondary text-white" placeholder="e.g. Selfie unclear, location mismatch"></textarea>
        </div>
        <div class="modal-footer border-secondary"><button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-danger">Confirm Reject</button></div>
    </form>
</div></div></div>

<script>
$(function () {
    $('.btn-reject').on('click', function () {
        $('#rej-name').text($(this).data('name'));
        $('#rejectForm').attr('action', 'index.php?route=attendance/reject/' + $(this).data('id'));
    });
});
</script>
