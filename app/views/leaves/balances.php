<div class="row justify-content-center">
    <div class="col-12 col-xl-11">
        <?php if (!empty($success_msg)): ?>
            <div class="alert alert-success alert-dismissible fade show bg-success bg-opacity-15 border-success border-opacity-25 text-success shadow-lg mb-4" role="alert" style="border-radius:12px;">
                <i class="fa-solid fa-circle-check me-2"></i><?php echo htmlspecialchars($success_msg); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($error_msg)): ?>
            <div class="alert alert-danger alert-dismissible fade show bg-danger bg-opacity-15 border-danger border-opacity-25 text-danger shadow-lg mb-4" role="alert" style="border-radius:12px;">
                <i class="fa-solid fa-triangle-exclamation me-2"></i><?php echo htmlspecialchars($error_msg); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Stat Cards Bar -->
        <?php 
            $totalEmployeesCount = count($pivoted_balances);
            $lowBalanceCount = count(array_filter($pivoted_balances, fn($p) => $p->is_low_balance));
            $totalAvailableAll = array_sum(array_column($pivoted_balances, 'total_available'));
            $avgAvailable = $totalEmployeesCount > 0 ? round($totalAvailableAll / $totalEmployeesCount, 1) : 0;
        ?>
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="pulse-card p-3 d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-secondary small">Total Active Staff</div>
                        <div class="fs-4 fw-bold text-white"><?php echo $totalEmployeesCount; ?></div>
                    </div>
                    <div class="rounded-circle bg-primary bg-opacity-15 p-3 text-primary">
                        <i class="fa-solid fa-users fs-4"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="pulse-card p-3 d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-secondary small">Low Balance Risk (<3 Days)</div>
                        <div class="fs-4 fw-bold text-warning"><?php echo $lowBalanceCount; ?></div>
                    </div>
                    <div class="rounded-circle bg-warning bg-opacity-15 p-3 text-warning">
                        <i class="fa-solid fa-triangle-exclamation fs-4"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="pulse-card p-3 d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-secondary small">Total Available Days</div>
                        <div class="fs-4 fw-bold text-success"><?php echo number_format($totalAvailableAll, 1); ?></div>
                    </div>
                    <div class="rounded-circle bg-success bg-opacity-15 p-3 text-success">
                        <i class="fa-solid fa-scale-balanced fs-4"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="pulse-card p-3 d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-secondary small">Avg Days Per Staff</div>
                        <div class="fs-4 fw-bold text-info"><?php echo $avgAvailable; ?> days</div>
                    </div>
                    <div class="rounded-circle bg-info bg-opacity-15 p-3 text-info">
                        <i class="fa-solid fa-chart-line fs-4"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="pulse-card">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 mb-4">
                <div>
                    <h4 class="text-white mb-1"><i class="fa-solid fa-scale-balanced text-primary me-2"></i>Employee Leave Balances &amp; Ledger</h4>
                    <p class="text-secondary small mb-0">Consolidated admin view of leave quotas, carry-forward days, pending holds, and consumption.</p>
                </div>
                <div class="d-flex gap-2">
                    <?php if (in_array($_SESSION['user_role'], ['admin', 'hr'], true)): ?>
                        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#adjustBalanceModal">
                            <i class="fa-solid fa-sliders me-1"></i>Adjust Balance
                        </button>
                    <?php endif; ?>
                    <a href="index.php?route=leaves/exportBalancesCsv&<?php echo http_build_query($filters); ?>" class="btn btn-outline-secondary btn-sm text-white">
                        <i class="fa-solid fa-file-csv me-1"></i>Export CSV
                    </a>
                </div>
            </div>

            <!-- Filters Bar (Auto-submits instantly on selection change) -->
            <form action="index.php" method="GET" class="row g-2 mb-4" id="leave-balances-filter-form">
                <input type="hidden" name="route" value="leaves/balances">
                <div class="col-md-3">
                    <input type="text" name="search" class="form-control form-control-sm bg-dark text-white border-secondary"
                           placeholder="Search employee, email, code..." value="<?php echo htmlspecialchars($filters['search']); ?>" onchange="this.form.submit()">
                </div>
                <div class="col-md-3">
                    <select name="department" class="form-select form-select-sm bg-dark text-white border-secondary" onchange="this.form.submit()">
                        <option value="">All Departments</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?php echo htmlspecialchars($dept); ?>" <?php echo $filters['department'] === $dept ? 'selected' : ''; ?>><?php echo htmlspecialchars($dept); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="leave_type" class="form-select form-select-sm bg-dark text-white border-secondary" onchange="this.form.submit()">
                        <option value="">All Leave Types</option>
                        <option value="Casual Leave" <?php echo $filters['leave_type'] === 'Casual Leave' ? 'selected' : ''; ?>>Casual Leave</option>
                        <option value="Sick Leave" <?php echo $filters['leave_type'] === 'Sick Leave' ? 'selected' : ''; ?>>Sick Leave</option>
                        <option value="Earned Leave" <?php echo $filters['leave_type'] === 'Earned Leave' ? 'selected' : ''; ?>>Earned Leave</option>
                        <option value="Comp-Off" <?php echo $filters['leave_type'] === 'Comp-Off' ? 'selected' : ''; ?>>Comp-Off</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="leave_year" class="form-select form-select-sm bg-dark text-white border-secondary" onchange="this.form.submit()">
                        <option value="2026" <?php echo $filters['leave_year'] == 2026 ? 'selected' : ''; ?>>Year 2026</option>
                        <option value="2025" <?php echo $filters['leave_year'] == 2025 ? 'selected' : ''; ?>>Year 2025</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-center">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="low_balance" id="low_balance_check" value="1" <?php echo $filters['low_balance'] ? 'checked' : ''; ?> onchange="this.form.submit()">
                        <label class="form-check-label text-warning small" for="low_balance_check">Low Balance (<3d)</label>
                    </div>
                </div>
            </form>

            <!-- Pivoted Leave Balances Table -->
            <div class="table-responsive">
                <table class="table table-dark table-hover align-middle mb-0" style="font-size:0.88rem;">
                    <thead>
                        <tr class="text-secondary border-secondary">
                            <th>Employee Code &amp; Name</th>
                            <th>Department</th>
                            <th class="text-center">Casual Leave<br><span class="fw-normal text-muted" style="font-size:0.72rem;">(Avail / Used / Alloc)</span></th>
                            <th class="text-center">Sick Leave<br><span class="fw-normal text-muted" style="font-size:0.72rem;">(Avail / Used / Alloc)</span></th>
                            <th class="text-center">Earned Leave<br><span class="fw-normal text-muted" style="font-size:0.72rem;">(Avail / Used / Alloc)</span></th>
                            <th class="text-center">Comp-Off<br><span class="fw-normal text-muted" style="font-size:0.72rem;">(Avail / Used / Alloc)</span></th>
                            <th class="text-center">Total Available</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($pivoted_balances)): ?>
                            <tr>
                                <td colspan="8" class="text-center text-secondary py-4">
                                    <i class="fa-solid fa-inbox fs-3 mb-2 d-block"></i>
                                    No employee leave balances match the filter criteria.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($pivoted_balances as $row): ?>
                                <tr>
                                    <td>
                                        <div class="text-white fw-bold"><?php echo htmlspecialchars($row->employee_name); ?></div>
                                        <div class="text-secondary small">
                                            <span class="badge bg-secondary me-1"><?php echo htmlspecialchars($row->emp_code); ?></span>
                                            <?php echo htmlspecialchars($row->email); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="px-2 py-1 rounded-pill fw-bold" style="background: #EEF2FF; color: #3730A3; font-size: 0.8rem; border: 1px solid #C7D2FE; display: inline-flex; align-items: center;">
                                            <i class="fa-solid fa-building me-1 style-opacity-75" style="color: #4F46E5;"></i><?php echo htmlspecialchars($row->department); ?>
                                        </span>
                                    </td>

                                    <!-- Casual Leave -->
                                    <?php $cl = $row->balances['Casual Leave'] ?? null; ?>
                                    <td class="text-center">
                                        <?php if ($cl): ?>
                                            <span class="fw-bold <?php echo $cl->available_days < 3 ? 'text-warning' : 'text-success'; ?>">
                                                <?php echo number_format($cl->available_days, 1); ?>
                                            </span>
                                            <span class="text-secondary">/ <?php echo number_format($cl->consumed_days, 1); ?> / <?php echo number_format($cl->allocated_days, 1); ?></span>
                                            <?php if ($cl->pending_days > 0): ?>
                                                <div class="text-info style-italic" style="font-size:0.72rem;">(<?php echo $cl->pending_days; ?>d pending)</div>
                                            <?php endif; ?>
                                        <?php else: ?>-<?php endif; ?>
                                    </td>

                                    <!-- Sick Leave -->
                                    <?php $sl = $row->balances['Sick Leave'] ?? null; ?>
                                    <td class="text-center">
                                        <?php if ($sl): ?>
                                            <span class="fw-bold <?php echo $sl->available_days < 3 ? 'text-warning' : 'text-success'; ?>">
                                                <?php echo number_format($sl->available_days, 1); ?>
                                            </span>
                                            <span class="text-secondary">/ <?php echo number_format($sl->consumed_days, 1); ?> / <?php echo number_format($sl->allocated_days, 1); ?></span>
                                            <?php if ($sl->pending_days > 0): ?>
                                                <div class="text-info style-italic" style="font-size:0.72rem;">(<?php echo $sl->pending_days; ?>d pending)</div>
                                            <?php endif; ?>
                                        <?php else: ?>-<?php endif; ?>
                                    </td>

                                    <!-- Earned Leave -->
                                    <?php $el = $row->balances['Earned Leave'] ?? null; ?>
                                    <td class="text-center">
                                        <?php if ($el): ?>
                                            <span class="fw-bold <?php echo $el->available_days < 3 ? 'text-warning' : 'text-success'; ?>">
                                                <?php echo number_format($el->available_days, 1); ?>
                                            </span>
                                            <span class="text-secondary">/ <?php echo number_format($el->consumed_days, 1); ?> / <?php echo number_format($el->allocated_days, 1); ?></span>
                                            <?php if ($el->pending_days > 0): ?>
                                                <div class="text-info style-italic" style="font-size:0.72rem;">(<?php echo $el->pending_days; ?>d pending)</div>
                                            <?php endif; ?>
                                        <?php else: ?>-<?php endif; ?>
                                    </td>

                                    <!-- Comp-Off -->
                                    <?php $co = $row->balances['Comp-Off'] ?? null; ?>
                                    <td class="text-center">
                                        <?php if ($co): ?>
                                            <span class="fw-bold <?php echo $co->available_days < 3 ? 'text-warning' : 'text-success'; ?>">
                                                <?php echo number_format($co->available_days, 1); ?>
                                            </span>
                                            <span class="text-secondary">/ <?php echo number_format($co->consumed_days, 1); ?> / <?php echo number_format($co->allocated_days, 1); ?></span>
                                            <?php if ($co->pending_days > 0): ?>
                                                <div class="text-info style-italic" style="font-size:0.72rem;">(<?php echo $co->pending_days; ?>d pending)</div>
                                            <?php endif; ?>
                                        <?php else: ?>-<?php endif; ?>
                                    </td>

                                    <!-- Total Available -->
                                    <td class="text-center">
                                        <span class="badge <?php echo $row->is_low_balance ? 'bg-warning text-dark' : 'bg-success'; ?> px-3 py-2 fs-6">
                                            <?php echo number_format($row->total_available, 1); ?> days
                                        </span>
                                    </td>

                                    <!-- Action -->
                                    <td class="text-end">
                                        <?php if (in_array($_SESSION['user_role'], ['admin', 'hr'], true)): ?>
                                            <button type="button" class="btn btn-sm btn-outline-primary btn-adjust-user"
                                                    data-uid="<?php echo $row->user_id; ?>"
                                                    data-uname="<?php echo htmlspecialchars($row->employee_name); ?>"
                                                    data-bs-toggle="modal" data-bs-target="#adjustBalanceModal">
                                                <i class="fa-solid fa-pen-to-square me-1"></i>Adjust
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Adjust Balance (Admin / HR) -->
<div class="modal fade" id="adjustBalanceModal" tabindex="-1" aria-labelledby="adjustBalanceModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark text-white border-secondary">
            <form action="index.php?route=leaves/adjustBalance" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title" id="adjustBalanceModalLabel"><i class="fa-solid fa-sliders text-primary me-2"></i>Manual Leave Balance Adjustment</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="target_user_id" class="form-label text-secondary">Target Employee *</label>
                        <select name="target_user_id" id="target_user_id" class="form-select bg-dark text-white border-secondary" required>
                            <option value="">Select Employee...</option>
                            <?php foreach ($all_users as $u): ?>
                                <option value="<?php echo $u->user_id; ?>"><?php echo htmlspecialchars($u->name); ?> (<?php echo htmlspecialchars($u->email); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="leave_type_name" class="form-label text-secondary">Leave Type *</label>
                        <select name="leave_type_name" id="leave_type_name" class="form-select bg-dark text-white border-secondary" required>
                            <option value="Casual Leave">Casual Leave (CL)</option>
                            <option value="Sick Leave">Sick Leave (SL)</option>
                            <option value="Earned Leave">Earned Leave (EL)</option>
                            <option value="Comp-Off">Comp-Off (CO)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="transaction_type" class="form-label text-secondary">Adjustment Type *</label>
                        <select name="transaction_type" id="transaction_type" class="form-select bg-dark text-white border-secondary" required>
                            <option value="Manual Adjustment">Manual Adjustment (+ / -)</option>
                            <option value="Accrual">Accrual (+ Days)</option>
                            <option value="Carry-Forward">Carry-Forward (+ Days)</option>
                            <option value="Encashment">Encashment (- Days)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="days" class="form-label text-secondary">Days Count (+ for addition, - for deduction) *</label>
                        <input type="number" step="0.5" name="days" id="days" class="form-control bg-dark text-white border-secondary" placeholder="e.g. 2.0 or -1.5" required>
                    </div>
                    <div class="mb-3">
                        <label for="remarks" class="form-label text-secondary">Mandatory Remarks / Reason *</label>
                        <textarea name="remarks" id="remarks" rows="3" class="form-control bg-dark text-white border-secondary" placeholder="Explain why balance is being adjusted..." required></warning></textarea>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk me-1"></i>Save Adjustment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('.btn-adjust-user').on('click', function() {
        const uid = $(this).data('uid');
        if (uid) {
            $('#target_user_id').val(uid);
        }
    });
});
</script>
