<div class="pulse-card" style="background: #ffffff; border: 1px solid #E2E8F0; border-radius: 14px; padding: 1.75rem; box-shadow: 0 1px 4px rgba(0,0,0,0.05);">

    <!-- Page Header -->
    <div style="display:flex; align-items:center; gap:0.75rem; background:#EFF6FF; padding:0.85rem 1.1rem; border-radius:10px; border-left:4px solid #2563EB; margin-bottom:1.5rem;">
        <i class="fa-solid fa-file-signature" style="color:#2563EB; font-size:1.05rem;"></i>
        <h4 style="margin:0; font-size:1.05rem; font-weight:700; color:#1E40AF;">Pending Leave Requests</h4>
    </div>

    <?php if (isset($_SESSION['leaves_success'])): ?>
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
            <i class="fa-solid fa-circle-check me-2"></i><?php echo $_SESSION['leaves_success']; unset($_SESSION['leaves_success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php if (isset($_SESSION['leaves_error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
            <i class="fa-solid fa-circle-exclamation me-2"></i><?php echo $_SESSION['leaves_error']; unset($_SESSION['leaves_error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="table-responsive">
        <table class="table align-middle mb-0" id="leave-approvals-table"
               style="border-collapse: separate; border-spacing: 0;">
            <thead>
                <tr style="background:#EFF6FF;">
                    <th style="background:#EFF6FF; color:#334155; font-size:0.77rem; font-weight:700; text-transform:uppercase; letter-spacing:0.4px; padding:0.85rem 1rem; border-bottom:2px solid #DBEAFE;">Employee</th>
                    <th style="background:#EFF6FF; color:#334155; font-size:0.77rem; font-weight:700; text-transform:uppercase; letter-spacing:0.4px; padding:0.85rem 1rem; border-bottom:2px solid #DBEAFE;">Department</th>
                    <th style="background:#EFF6FF; color:#334155; font-size:0.77rem; font-weight:700; text-transform:uppercase; letter-spacing:0.4px; padding:0.85rem 1rem; border-bottom:2px solid #DBEAFE;">Leave Type</th>
                    <th style="background:#EFF6FF; color:#334155; font-size:0.77rem; font-weight:700; text-transform:uppercase; letter-spacing:0.4px; padding:0.85rem 1rem; border-bottom:2px solid #DBEAFE;">From</th>
                    <th style="background:#EFF6FF; color:#334155; font-size:0.77rem; font-weight:700; text-transform:uppercase; letter-spacing:0.4px; padding:0.85rem 1rem; border-bottom:2px solid #DBEAFE;">To</th>
                    <th style="background:#EFF6FF; color:#334155; font-size:0.77rem; font-weight:700; text-transform:uppercase; letter-spacing:0.4px; padding:0.85rem 1rem; border-bottom:2px solid #DBEAFE;">Duration</th>
                    <th style="background:#EFF6FF; color:#334155; font-size:0.77rem; font-weight:700; text-transform:uppercase; letter-spacing:0.4px; padding:0.85rem 1rem; border-bottom:2px solid #DBEAFE;">Reason</th>
                    <th style="background:#EFF6FF; color:#334155; font-size:0.77rem; font-weight:700; text-transform:uppercase; letter-spacing:0.4px; padding:0.85rem 1rem; border-bottom:2px solid #DBEAFE; text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($data['pending'])): ?>
                    <tr>
                        <td colspan="8" class="text-center py-5" style="color: #64748B;">
                            <i class="fa-solid fa-circle-check fa-2x mb-2 d-block" style="color:#10B981;"></i>
                            No pending leave requests to review. All caught up!
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($data['pending'] as $i => $req): ?>
                        <?php
                            $days = $req->half_day ? 0.5 : (int)round((strtotime($req->to_date) - strtotime($req->from_date)) / 86400) + 1;
                            $rowBg = ($i % 2 === 0) ? '#ffffff' : '#F8FAFC';
                        ?>
                        <tr style="background:<?php echo $rowBg; ?>; border-bottom: 1px solid #E2E8F0; transition: background 0.15s;"
                            onmouseover="this.style.background='#EFF6FF'" onmouseout="this.style.background='<?php echo $rowBg; ?>'">
                            <td style="padding:0.85rem 1rem; font-weight:600; color:#1E293B; font-size:0.88rem;">
                                <?php echo htmlspecialchars($req->employee_name); ?>
                            </td>
                            <td style="padding:0.85rem 1rem; color:#475569; font-size:0.88rem;">
                                <?php echo htmlspecialchars($req->department ?? 'General'); ?>
                            </td>
                            <td style="padding:0.85rem 1rem; font-size:0.88rem;">
                                <span style="background:#EFF6FF; color:#1D4ED8; padding:0.2rem 0.6rem; border-radius:6px; font-size:0.78rem; font-weight:600;">
                                    <?php echo htmlspecialchars($req->leave_type); ?>
                                </span>
                            </td>
                            <td style="padding:0.85rem 1rem; color:#475569; font-size:0.85rem;">
                                <?php echo date('M d, Y', strtotime($req->from_date)); ?>
                            </td>
                            <td style="padding:0.85rem 1rem; color:#475569; font-size:0.85rem;">
                                <?php echo date('M d, Y', strtotime($req->to_date)); ?>
                            </td>
                            <td style="padding:0.85rem 1rem; font-weight:700; color:#1E293B; font-size:0.88rem;">
                                <?php echo $days; ?> Day<?php echo $days != 1 ? 's' : ''; ?>
                            </td>
                            <td style="padding:0.85rem 1rem; color:#64748B; font-size:0.83rem; max-width:180px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                                <?php echo htmlspecialchars($req->reason); ?>
                            </td>
                            <td style="padding:0.85rem 1rem; text-align:right;">
                                <div class="d-inline-flex gap-2">
                                    <?php if (!empty($req->supporting_document)): ?>
                                        <a href="index.php?route=file/show&key=<?php echo urlencode($req->supporting_document); ?>" target="_blank"
                                           class="btn btn-sm" style="border:1px solid #06B6D4; color:#06B6D4; border-radius:6px; padding:0.25rem 0.6rem;"
                                           title="View Document">
                                            <i class="fa-solid fa-file-invoice"></i>
                                        </a>
                                    <?php endif; ?>
                                    <button class="btn btn-sm btn-review-leave"
                                            data-id="<?php echo $req->leave_request_id; ?>"
                                            data-name="<?php echo htmlspecialchars($req->employee_name); ?>"
                                            data-type="<?php echo htmlspecialchars($req->leave_type); ?>"
                                            data-duration="<?php echo $days; ?> Day<?php echo $days != 1 ? 's' : ''; ?>"
                                            data-reason="<?php echo htmlspecialchars($req->reason); ?>"
                                            data-bs-toggle="modal" data-bs-target="#reviewLeaveModal"
                                            style="background:#2563EB; color:#fff; border:none; border-radius:6px; padding:0.3rem 0.8rem; font-size:0.82rem; font-weight:600;"
                                            onmouseover="this.style.background='#1D4ED8'" onmouseout="this.style.background='#2563EB'">
                                        <i class="fa-solid fa-gavel me-1"></i>Review
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ── Review Leave Modal (Light Theme) ── -->
<div class="modal fade" id="reviewLeaveModal" tabindex="-1" aria-labelledby="reviewLeaveModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="background: #ffffff; border: 1px solid #E2E8F0; border-radius: 16px; box-shadow: 0 20px 60px rgba(0,0,0,0.12);">
            <div class="modal-header" style="border-bottom: 1px solid #E2E8F0; background: #EFF6FF; border-radius: 16px 16px 0 0; padding: 1rem 1.25rem;">
                <div style="display:flex; align-items:center; gap:0.6rem;">
                    <i class="fa-solid fa-gavel" style="color:#2563EB;"></i>
                    <h5 class="modal-title" style="margin:0; font-weight:700; color:#1E40AF;" id="reviewLeaveModalLabel">Review Leave Request</h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" style="padding: 1.5rem;">
                <div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 10px; padding: 1rem; margin-bottom: 1.25rem;">
                    <div class="row">
                        <div class="col-6 mb-2">
                            <small style="color:#64748B; display:block; font-size:0.75rem; font-weight:600; text-transform:uppercase; letter-spacing:0.4px; margin-bottom:0.2rem;">Employee</small>
                            <span style="color:#1E293B; font-weight:700;" id="modal-emp-name"></span>
                        </div>
                        <div class="col-6 mb-2">
                            <small style="color:#64748B; display:block; font-size:0.75rem; font-weight:600; text-transform:uppercase; letter-spacing:0.4px; margin-bottom:0.2rem;">Leave Type</small>
                            <span style="color:#1E293B; font-weight:700;" id="modal-leave-type"></span>
                        </div>
                        <div class="col-12">
                            <small style="color:#64748B; display:block; font-size:0.75rem; font-weight:600; text-transform:uppercase; letter-spacing:0.4px; margin-bottom:0.2rem;">Duration</small>
                            <span style="color:#1E293B; font-weight:700;" id="modal-duration"></span>
                        </div>
                        <div class="col-12 mt-2">
                            <small style="color:#64748B; display:block; font-size:0.75rem; font-weight:600; text-transform:uppercase; letter-spacing:0.4px; margin-bottom:0.2rem;">Reason</small>
                            <span style="color:#475569; font-size:0.87rem;" id="modal-reason"></span>
                        </div>
                    </div>
                </div>

                <form id="reviewForm" method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                    <div class="mb-4">
                        <label for="comments" style="display:block; font-size:0.76rem; font-weight:700; color:#475569; text-transform:uppercase; letter-spacing:0.4px; margin-bottom:0.35rem;">
                            Comments / Remarks
                        </label>
                        <textarea class="form-control" id="comments" name="comments" rows="3"
                                  placeholder="Enter your review remarks (required for rejection)..."
                                  required style="border-color:#CBD5E1; border-radius:8px;"></textarea>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <button type="button" class="btn btn-sm" data-bs-dismiss="modal"
                                style="border:1px solid #CBD5E1; color:#64748B; border-radius:8px; padding:0.45rem 1rem;">
                            Close
                        </button>
                        <button type="submit" id="btn-reject" class="btn btn-sm"
                                style="background:#FEE2E2; color:#991B1B; border:1px solid #FECACA; border-radius:8px; padding:0.45rem 1rem; font-weight:600;"
                                onmouseover="this.style.background='#EF4444';this.style.color='#fff'" onmouseout="this.style.background='#FEE2E2';this.style.color='#991B1B'">
                            <i class="fa-solid fa-circle-xmark me-1"></i>Reject
                        </button>
                        <button type="submit" id="btn-approve" class="btn btn-sm"
                                style="background:#2563EB; color:#fff; border:none; border-radius:8px; padding:0.45rem 1.1rem; font-weight:600;"
                                onmouseover="this.style.background='#1D4ED8'" onmouseout="this.style.background='#2563EB'">
                            <i class="fa-solid fa-circle-check me-1"></i>Approve
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var reviewModal = document.getElementById('reviewLeaveModal');
    if (reviewModal) {
        reviewModal.addEventListener('show.bs.modal', function (e) {
            var btn = e.relatedTarget;
            document.getElementById('modal-emp-name').textContent    = btn.getAttribute('data-name');
            document.getElementById('modal-leave-type').textContent  = btn.getAttribute('data-type');
            document.getElementById('modal-duration').textContent    = btn.getAttribute('data-duration');
            document.getElementById('modal-reason').textContent      = btn.getAttribute('data-reason');
            document.getElementById('comments').value = '';

            var form       = document.getElementById('reviewForm');
            var approveBtn = document.getElementById('btn-approve');
            var rejectBtn  = document.getElementById('btn-reject');
            var id         = btn.getAttribute('data-id');

            approveBtn.onclick = function() { form.action = 'index.php?route=leaves/approve/' + id; };
            rejectBtn.onclick  = function() { form.action = 'index.php?route=leaves/reject/'  + id; };
        });
    }
});
</script>
