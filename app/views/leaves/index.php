<div class="pulse-card">
    <!-- Leave Balances Section -->
    <div class="row mb-4">
        <div class="col-md-4 mb-3">
            <div class="card border-0 shadow-sm text-center p-3" style="background: rgba(25, 135, 84, 0.15); border-radius: 12px; border: 1px solid rgba(25, 135, 84, 0.25) !important;">
                <div class="text-success fs-3 mb-2"><i class="fa-solid fa-notes-medical"></i></div>
                <h6 class="text-secondary small fw-bold text-uppercase">Sick Leave Balance</h6>
                <h3 class="text-white fw-bold mb-0"><?php echo number_format((float)($data['balances']->sick_leave ?? 12.00), 1); ?> Days</h3>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="card border-0 shadow-sm text-center p-3" style="background: rgba(13, 110, 253, 0.15); border-radius: 12px; border: 1px solid rgba(13, 110, 253, 0.25) !important;">
                <div class="text-primary fs-3 mb-2"><i class="fa-solid fa-mug-hot"></i></div>
                <h6 class="text-secondary small fw-bold text-uppercase">Casual Leave Balance</h6>
                <h3 class="text-white fw-bold mb-0"><?php echo number_format((float)($data['balances']->casual_leave ?? 12.00), 1); ?> Days</h3>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="card border-0 shadow-sm text-center p-3" style="background: rgba(255, 193, 7, 0.15); border-radius: 12px; border: 1px solid rgba(255, 193, 7, 0.25) !important;">
                <div class="text-warning fs-3 mb-2"><i class="fa-solid fa-umbrella-beach"></i></div>
                <h6 class="text-secondary small fw-bold text-uppercase">Earned Leave Balance</h6>
                <h3 class="text-white fw-bold mb-0"><?php echo number_format((float)($data['balances']->earned_leave ?? 15.00), 1); ?> Days</h3>
            </div>
        </div>
    </div>

    <!-- Alert Notifications -->
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

    <div class="row">
        <!-- Apply Leave Form (Left 5 Columns) -->
        <div class="col-lg-5 mb-4">
            <div class="card border-0 shadow-sm p-4" style="background: #1a1d27; border: 1px solid rgba(255,255,255,0.1); border-radius: 16px;">
                <h5 class="fw-bold text-white mb-3"><i class="fa-solid fa-paper-plane text-primary me-2"></i>Apply for Leave</h5>
                <form action="index.php?route=leaves/apply" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                    
                    <div class="mb-3">
                        <label for="leave_type" class="form-label small fw-bold text-secondary text-uppercase">Leave Type</label>
                        <select class="form-select py-2 border-secondary bg-dark text-white" id="leave_type" name="leave_type" required>
                            <option value="Sick Leave">Sick Leave</option>
                            <option value="Casual Leave">Casual Leave</option>
                            <option value="Earned Leave">Earned Leave</option>
                        </select>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="from_date" class="form-label small fw-bold text-secondary text-uppercase">From Date</label>
                            <input type="date" class="form-control py-2 border-secondary bg-dark text-white" id="from_date" name="from_date" required>
                        </div>
                        <div class="col-md-6">
                            <label for="to_date" class="form-label small fw-bold text-secondary text-uppercase">To Date</label>
                            <input type="date" class="form-control py-2 border-secondary bg-dark text-white" id="to_date" name="to_date" required>
                        </div>
                    </div>

                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="half_day" name="half_day">
                        <label class="form-check-label text-white small" for="half_day">Apply for Half Day</label>
                    </div>

                    <div class="mb-3">
                        <label for="reason" class="form-label small fw-bold text-secondary text-uppercase">Reason</label>
                        <textarea class="form-control border-secondary bg-dark text-white" id="reason" name="reason" rows="3" placeholder="Brief explanation of your leave..." required></textarea>
                    </div>

                    <div class="mb-4">
                        <label for="supporting_doc" class="form-label small fw-bold text-secondary text-uppercase">Supporting Document (Optional)</label>
                        <input class="form-control border-secondary bg-dark text-white" type="file" id="supporting_doc" name="supporting_doc" accept=".pdf,.png,.jpg,.jpeg">
                        <div class="form-text text-secondary mt-1 small">Accepted formats: PDF, PNG, JPG, JPEG (Max 5MB).</div>
                    </div>

                    <button type="submit" class="btn btn-primary py-2 w-100 fw-bold border-0 shadow-sm" style="background: var(--primary);">
                        <i class="fa-solid fa-check-circle me-1"></i>Submit Leave Application
                    </button>
                </form>
            </div>
        </div>

        <!-- Leave History & Holidays (Right 7 Columns) -->
        <div class="col-lg-7 mb-4">
            <!-- Leave Requests History Card -->
            <div class="card border-0 shadow-sm p-4 mb-4" style="background: #1a1d27; border: 1px solid rgba(255,255,255,0.1); border-radius: 16px;">
                <h5 class="fw-bold text-white mb-3"><i class="fa-solid fa-clock-rotate-left text-primary me-2"></i>My Leave History</h5>
                <div class="table-responsive">
                    <table class="table table-dark table-hover align-middle border-secondary mb-0">
                        <thead>
                            <tr class="text-secondary small">
                                <th>Type</th>
                                <th>From</th>
                                <th>To</th>
                                <th>Status</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($data['requests'])): ?>
                                <tr>
                                    <td colspan="5" class="text-center text-secondary py-3">No leave requests found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($data['requests'] as $req): ?>
                                    <?php 
                                        $statusClass = match($req->status) {
                                            'approved'        => 'success',
                                            'rejected'        => 'danger',
                                            'cancelled'       => 'secondary',
                                            'pending_hr'      => 'info',
                                            'pending_manager' => 'warning',
                                            default           => 'primary'
                                        };
                                    ?>
                                    <tr class="small">
                                        <td class="text-white fw-bold"><?php echo htmlspecialchars($req->leave_type); ?></td>
                                        <td><?php echo htmlspecialchars($req->from_date); ?></td>
                                        <td><?php echo htmlspecialchars($req->to_date); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $statusClass; ?>-subtle text-<?php echo $statusClass; ?> border border-<?php echo $statusClass; ?>-subtle">
                                                <?php echo strtoupper(str_replace('_', ' ', $req->status)); ?>
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            <?php if (in_array($req->status, ['pending_manager', 'pending_hr'], true)): ?>
                                                <form action="index.php?route=leaves/cancel/<?php echo $req->leave_request_id; ?>" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to cancel this pending leave?');">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                                                    <button type="submit" class="btn btn-outline-danger btn-sm px-2 py-1">
                                                        <i class="fa-solid fa-ban"></i> Cancel
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <span class="text-secondary">—</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Holidays List Card -->
            <div class="card border-0 shadow-sm p-4" style="background: #1a1d27; border: 1px solid rgba(255,255,255,0.1); border-radius: 16px;">
                <h5 class="fw-bold text-white mb-3"><i class="fa-solid fa-umbrella-beach text-primary me-2"></i>Holiday List (2026)</h5>
                <div class="row">
                    <?php foreach ($data['holidays'] as $h): ?>
                        <div class="col-md-6 mb-2">
                            <div class="d-flex justify-content-between align-items-center p-2 rounded" style="background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.05);">
                                <span class="text-white small fw-bold"><?php echo htmlspecialchars($h->holiday_name); ?></span>
                                <span class="text-secondary small"><?php echo date('M d, Y', strtotime($h->holiday_date)); ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>
