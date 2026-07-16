<div class="pulse-card">
    <h4 class="text-white mb-4"><i class="fa-solid fa-file-signature text-primary me-2"></i>Pending Leave Requests</h4>

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
        <table class="table table-dark table-hover align-middle border-secondary mb-0" id="leave-approvals-table">
            <thead>
                <tr class="text-secondary">
                    <th>Employee</th>
                    <th>Department</th>
                    <th>Leave Type</th>
                    <th>From</th>
                    <th>To</th>
                    <th>Duration</th>
                    <th>Reason</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($data['pending'])): ?>
                    <tr>
                        <td colspan="8" class="text-center py-4 text-secondary">No pending leave requests to review.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($data['pending'] as $req): ?>
                        <?php 
                            $days = $req->half_day ? 0.5 : (int)round((strtotime($req->to_date) - strtotime($req->from_date)) / 86400) + 1;
                        ?>
                        <tr style="border-bottom: 1px solid var(--border-color);">
                            <td class="font-weight-bold text-white"><?php echo htmlspecialchars($req->employee_name); ?></td>
                            <td><?php echo htmlspecialchars($req->department ?? 'Sales'); ?></td>
                            <td><?php echo htmlspecialchars($req->leave_type); ?></td>
                            <td><?php echo htmlspecialchars($req->from_date); ?></td>
                            <td><?php echo htmlspecialchars($req->to_date); ?></td>
                            <td class="text-white fw-bold"><?php echo $days; ?> Days</td>
                            <td class="text-secondary small"><?php echo htmlspecialchars($req->reason); ?></td>
                            <td class="text-end">
                                <div class="d-inline-flex gap-2">
                                    <?php if (!empty($req->supporting_document)): ?>
                                        <a href="index.php?route=file/show&key=<?php echo urlencode($req->supporting_document); ?>" target="_blank" class="btn btn-outline-info btn-sm" title="View Document">
                                            <i class="fa-solid fa-file-invoice"></i>
                                        </a>
                                    <?php endif; ?>
                                    <button class="btn btn-primary btn-sm btn-review-leave"
                                            data-id="<?php echo $req->leave_request_id; ?>"
                                            data-name="<?php echo htmlspecialchars($req->employee_name); ?>"
                                            data-type="<?php echo htmlspecialchars($req->leave_type); ?>"
                                            data-duration="<?php echo $days; ?> Days"
                                            data-reason="<?php echo htmlspecialchars($req->reason); ?>"
                                            data-bs-toggle="modal"
                                            data-bs-target="#reviewLeaveModal">
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

<!-- Review Leave Modal -->
<div class="modal fade" id="reviewLeaveModal" tabindex="-1" aria-labelledby="reviewLeaveModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="background: #1a1d27; border: 1px solid rgba(255,255,255,0.1); border-radius: 16px;">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title text-white mb-0" id="reviewLeaveModalLabel">Review Leave Request</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-3">
                <div class="p-3 mb-3 rounded" style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.05);">
                    <div class="row">
                        <div class="col-6 mb-2">
                            <small class="text-secondary block">Employee</small>
                            <span class="text-white fw-bold" id="modal-emp-name"></span>
                        </div>
                        <div class="col-6 mb-2">
                            <small class="text-secondary block">Leave Type</small>
                            <span class="text-white fw-bold" id="modal-leave-type"></span>
                        </div>
                        <div class="col-12">
                            <small class="text-secondary block">Duration</small>
                            <span class="text-white fw-bold" id="modal-duration"></span>
                        </div>
                        <div class="col-12 mt-2">
                            <small class="text-secondary block">Reason</small>
                            <span class="text-secondary small" id="modal-reason"></span>
                        </div>
                    </div>
                </div>

                <form id="reviewForm" method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                    <div class="mb-3">
                        <label for="comments" class="form-label text-secondary small fw-bold">Comments / Remarks</label>
                        <textarea class="form-control border-secondary bg-dark text-white" id="comments" name="comments" rows="3" placeholder="Enter review remarks..." required></textarea>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                        <button type="submit" id="btn-reject" class="btn btn-danger btn-sm px-3">
                            <i class="fa-solid fa-circle-xmark me-1"></i>Reject
                        </button>
                        <button type="submit" id="btn-approve" class="btn btn-success btn-sm px-3">
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
            var id = btn.getAttribute('data-id');
            var name = btn.getAttribute('data-name');
            var type = btn.getAttribute('data-type');
            var duration = btn.getAttribute('data-duration');
            var reason = btn.getAttribute('data-reason');

            document.getElementById('modal-emp-name').textContent = name;
            document.getElementById('modal-leave-type').textContent = type;
            document.getElementById('modal-duration').textContent = duration;
            document.getElementById('modal-reason').textContent = reason;
            document.getElementById('comments').value = '';

            // Setup actions dynamically
            var form = document.getElementById('reviewForm');
            var approveBtn = document.getElementById('btn-approve');
            var rejectBtn = document.getElementById('btn-reject');

            approveBtn.onclick = function() {
                form.action = 'index.php?route=leaves/approve/' + id;
            };
            rejectBtn.onclick = function() {
                form.action = 'index.php?route=leaves/reject/' + id;
            };
        });
    }
});
</script>
