<?php
/* ── Leave Balance Helpers ── */
$sick   = number_format((float)($data['balances']->sick_leave   ?? 12.00), 1);
$casual = number_format((float)($data['balances']->casual_leave ?? 12.00), 1);
$earned = number_format((float)($data['balances']->earned_leave ?? 15.00), 1);
?>

<style>
/* ── My Leaves — Light HRMS Theme ── */
.leave-balance-card {
    background: #ffffff;
    border: 1px solid #E2E8F0;
    border-radius: 14px;
    padding: 1.25rem 1rem;
    text-align: center;
    box-shadow: 0 1px 4px rgba(0,0,0,0.05);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.leave-balance-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 6px 20px rgba(37,99,235,0.10);
}
.leave-section-header {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    background: #EFF6FF;
    padding: 0.7rem 1rem;
    border-radius: 10px;
    margin-bottom: 1.25rem;
    border-left: 4px solid #2563EB;
}
.leave-section-header i { color: #2563EB; font-size: 1rem; }
.leave-section-header h5 { margin: 0; font-size: 0.95rem; font-weight: 700; color: #1E40AF; }
.leave-form-card, .leave-history-card, .leave-holiday-card {
    background: #ffffff;
    border: 1px solid #E2E8F0;
    border-radius: 14px;
    padding: 1.5rem;
    box-shadow: 0 1px 4px rgba(0,0,0,0.05);
}
.leave-table thead th {
    background: #EFF6FF;
    color: #334155;
    font-size: 0.78rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    padding: 0.85rem 1rem;
    border-bottom: 2px solid #DBEAFE;
}
.leave-table tbody tr:nth-child(even) { background: #F8FAFC; }
.leave-table tbody tr:hover { background: #EFF6FF; }
.leave-table tbody td { padding: 0.75rem 1rem; vertical-align: middle; border-color: #E2E8F0; font-size: 0.88rem; color: #1E293B; }
.leave-holiday-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.6rem 0.9rem;
    border-radius: 8px;
    background: #F8FAFC;
    border: 1px solid #E2E8F0;
    margin-bottom: 0.5rem;
    transition: background 0.15s;
}
.leave-holiday-item:hover { background: #EFF6FF; border-color: #DBEAFE; }
.leave-label { font-size: 0.76rem; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.4px; margin-bottom: 0.35rem; display: block; }
</style>

<div class="pulse-card" style="background: #F8FAFC; border: none; box-shadow: none; padding: 0;">

    <!-- ── Leave Balance Cards ── -->
    <div class="row mb-4 g-3">
        <div class="col-md-4">
            <div class="leave-balance-card" style="border-top: 4px solid #10B981;">
                <div style="color: #10B981; font-size: 1.8rem; margin-bottom: 0.5rem;"><i class="fa-solid fa-notes-medical"></i></div>
                <span class="leave-label">Sick Leave Balance</span>
                <h3 style="color: #1E293B; font-weight: 700; font-size: 1.6rem; margin: 0;"><?php echo $sick; ?></h3>
                <small style="color: #64748B;">Days Available</small>
            </div>
        </div>
        <div class="col-md-4">
            <div class="leave-balance-card" style="border-top: 4px solid #2563EB;">
                <div style="color: #2563EB; font-size: 1.8rem; margin-bottom: 0.5rem;"><i class="fa-solid fa-mug-hot"></i></div>
                <span class="leave-label">Casual Leave Balance</span>
                <h3 style="color: #1E293B; font-weight: 700; font-size: 1.6rem; margin: 0;"><?php echo $casual; ?></h3>
                <small style="color: #64748B;">Days Available</small>
            </div>
        </div>
        <div class="col-md-4">
            <div class="leave-balance-card" style="border-top: 4px solid #F59E0B;">
                <div style="color: #F59E0B; font-size: 1.8rem; margin-bottom: 0.5rem;"><i class="fa-solid fa-umbrella-beach"></i></div>
                <span class="leave-label">Earned Leave Balance</span>
                <h3 style="color: #1E293B; font-weight: 700; font-size: 1.6rem; margin: 0;"><?php echo $earned; ?></h3>
                <small style="color: #64748B;">Days Available</small>
            </div>
        </div>
    </div>

    <!-- ── Flash Alerts ── -->
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

    <div class="row g-4">
        <!-- ── Apply Leave Form (Left) ── -->
        <div class="col-lg-5">
            <div class="leave-form-card">
                <div class="leave-section-header">
                    <i class="fa-solid fa-paper-plane"></i>
                    <h5>Apply for Leave</h5>
                </div>
                <form action="index.php?route=leaves/apply" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">

                    <div class="mb-3">
                        <label for="leave_type" class="leave-label">Leave Type</label>
                        <select class="form-select" id="leave_type" name="leave_type" required
                                style="border-color: #CBD5E1; border-radius: 8px;">
                            <option value="Sick Leave">Sick Leave</option>
                            <option value="Casual Leave">Casual Leave</option>
                            <option value="Earned Leave">Earned Leave</option>
                        </select>
                    </div>

                    <div class="row mb-3">
                        <div class="col-6">
                            <label for="from_date" class="leave-label">From Date</label>
                            <input type="date" class="form-control" id="from_date" name="from_date"
                                   min="<?php echo date('Y-m-d'); ?>" required style="border-color: #CBD5E1; border-radius: 8px;">
                        </div>
                        <div class="col-6">
                            <label for="to_date" class="leave-label">To Date</label>
                            <input type="date" class="form-control" id="to_date" name="to_date"
                                   min="<?php echo date('Y-m-d'); ?>" required style="border-color: #CBD5E1; border-radius: 8px;">
                        </div>
                    </div>

                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="half_day" name="half_day">
                        <label class="form-check-label small" for="half_day" style="color: #475569;">Apply for Half Day</label>
                    </div>

                    <div class="mb-3">
                        <label for="reason" class="leave-label">Reason</label>
                        <textarea class="form-control" id="reason" name="reason" rows="3"
                                  placeholder="Brief explanation of your leave request..."
                                  required style="border-color: #CBD5E1; border-radius: 8px;"></textarea>
                    </div>

                    <div class="mb-4">
                        <label for="supporting_doc" class="leave-label">Supporting Document <span style="color:#94A3B8;">(Optional)</span></label>
                        <input class="form-control" type="file" id="supporting_doc" name="supporting_doc"
                               accept=".pdf,.png,.jpg,.jpeg"
                               style="border-color: #CBD5E1; border-radius: 8px;">
                        <div class="form-text mt-1" style="color:#64748B; font-size:0.78rem;">PDF, PNG, JPG accepted · Max 5 MB</div>
                    </div>

                    <button type="submit" class="btn w-100 fw-bold py-2"
                            style="background: #2563EB; color: #fff; border-radius: 10px; border: none; font-size: 0.93rem; letter-spacing: 0.2px;"
                            onmouseover="this.style.background='#1D4ED8'" onmouseout="this.style.background='#2563EB'">
                        <i class="fa-solid fa-check-circle me-2"></i>Submit Leave Application
                    </button>
                </form>
            </div>
        </div>

        <!-- ── Leave History & Holidays (Right) ── -->
        <div class="col-lg-7">
            <!-- Leave History -->
            <div class="leave-history-card mb-4">
                <div class="leave-section-header">
                    <i class="fa-solid fa-clock-rotate-left"></i>
                    <h5>My Leave History</h5>
                </div>
                <div class="table-responsive">
                    <table class="table leave-table align-middle mb-0">
                        <thead>
                            <tr>
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
                                    <td colspan="5" class="text-center py-4" style="color: #64748B;">
                                        <i class="fa-solid fa-calendar-xmark me-2" style="color:#CBD5E1;"></i>No leave requests found.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($data['requests'] as $req):
                                    $statusMap = [
                                        'approved'        => ['label' => 'Approved',        'bg' => '#D1FAE5', 'color' => '#065F46'],
                                        'rejected'        => ['label' => 'Rejected',        'bg' => '#FEE2E2', 'color' => '#991B1B'],
                                        'cancelled'       => ['label' => 'Cancelled',       'bg' => '#F1F5F9', 'color' => '#475569'],
                                        'pending_hr'      => ['label' => 'Pending HR',      'bg' => '#CFFAFE', 'color' => '#155E75'],
                                        'pending_manager' => ['label' => 'Pending Manager', 'bg' => '#FEF3C7', 'color' => '#92400E'],
                                    ];
                                    $s = $statusMap[$req->status] ?? ['label' => strtoupper($req->status), 'bg' => '#EFF6FF', 'color' => '#1E40AF'];
                                ?>
                                <tr>
                                    <td style="font-weight: 600;"><?php echo htmlspecialchars($req->leave_type); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($req->from_date)); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($req->to_date)); ?></td>
                                    <td>
                                        <span style="display:inline-block; padding: 0.28rem 0.65rem; border-radius: 20px; font-size: 0.74rem; font-weight: 700; background: <?php echo $s['bg']; ?>; color: <?php echo $s['color']; ?>;">
                                            <?php echo $s['label']; ?>
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <?php if (in_array($req->status, ['pending_manager', 'pending_hr'], true)): ?>
                                            <form action="index.php?route=leaves/cancel/<?php echo $req->leave_request_id; ?>" method="POST" class="d-inline"
                                                  onsubmit="return confirm('Cancel this leave request?');">
                                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                                                <button type="submit" class="btn btn-sm" style="border: 1px solid #EF4444; color: #EF4444; border-radius: 6px; background: transparent; font-size: 0.8rem; padding: 0.25rem 0.65rem;"
                                                        onmouseover="this.style.background='#FEE2E2'" onmouseout="this.style.background='transparent'">
                                                    <i class="fa-solid fa-ban me-1"></i>Cancel
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span style="color: #CBD5E1;">—</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Holiday List -->
            <div class="leave-holiday-card">
                <div class="leave-section-header">
                    <i class="fa-solid fa-umbrella-beach"></i>
                    <h5>Holiday List (<?php echo date('Y'); ?>)</h5>
                </div>
                <div class="row g-0">
                    <?php if (empty($data['holidays'])): ?>
                        <p style="color: #64748B; font-size: 0.88rem;">No holidays found for this year.</p>
                    <?php else: ?>
                        <?php foreach ($data['holidays'] as $h): ?>
                            <div class="col-md-6 pe-md-2 mb-2">
                                <div class="leave-holiday-item">
                                    <span style="color: #1E293B; font-size: 0.85rem; font-weight: 600;">
                                        <?php echo htmlspecialchars($h->holiday_name); ?>
                                    </span>
                                    <span style="color: #64748B; font-size: 0.78rem; white-space: nowrap; margin-left: 0.5rem;">
                                        <?php echo date('M d, Y', strtotime($h->holiday_date)); ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
