<div class="row g-4">
    <!-- Left Column: Start Run & Run List -->
    <div class="col-lg-4">
        <div class="pulse-card mb-4">
            <h5 class="text-white mb-3"><i class="fa-solid fa-folder-plus me-2 text-primary"></i>Start Payroll Run</h5>
            <form action="index.php?route=payroll/run_generate" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                <div class="mb-3">
                    <label class="form-label text-secondary small">Target Month/Year *</label>
                    <input type="month" name="month_year" class="form-control bg-dark border-secondary text-white" value="<?php echo date('Y-m'); ?>" required>
                </div>
                <button type="submit" class="btn btn-primary btn-sm w-100" style="background: var(--primary); border: none;">
                    <i class="fa-solid fa-spinner fa-spin-pulse me-1"></i>Generate Payroll
                </button>
            </form>
        </div>

        <div class="pulse-card">
            <h5 class="text-white mb-3"><i class="fa-solid fa-history me-2 text-secondary"></i>Previous Runs</h5>
            <div class="list-group list-group-flush bg-dark bg-opacity-25" style="max-height: 350px; overflow-y: auto;">
                <?php if (empty($runs)): ?>
                    <p class="text-secondary small p-3 mb-0 text-center">No runs generated yet.</p>
                <?php else: ?>
                    <?php foreach ($runs as $r): ?>
                        <a href="index.php?route=payroll/processing&run_id=<?php echo $r->payroll_run_id; ?>" 
                           class="list-group-item list-group-item-action bg-transparent border-secondary text-white d-flex justify-content-between align-items-center <?php echo $selected_run && (int)$selected_run->payroll_run_id === (int)$r->payroll_run_id ? 'active' : ''; ?>">
                            <span class="font-monospace"><?php echo htmlspecialchars($r->month_year); ?></span>
                            <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle text-uppercase" style="font-size: 0.65rem;">
                                <?php echo htmlspecialchars($r->status); ?>
                            </span>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Right Column: Selected Run Calculations & Processing Actions -->
    <div class="col-lg-8">
        <?php if (!empty($_SESSION['payroll_success'])): ?>
            <div class="alert alert-success alert-dismissible fade show mb-3" role="alert">
                <i class="fa-solid fa-circle-check me-2"></i><?php echo htmlspecialchars($_SESSION['payroll_success']); unset($_SESSION['payroll_success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <?php if (!empty($_SESSION['payroll_error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">
                <i class="fa-solid fa-circle-xmark me-2"></i><?php echo htmlspecialchars($_SESSION['payroll_error']); unset($_SESSION['payroll_error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if ($selected_run): ?>
            <div class="pulse-card">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 border-bottom border-secondary pb-3 mb-4">
                    <div>
                        <h4 class="text-white mb-0">Payroll Run Details: <span class="font-monospace text-primary"><?php echo htmlspecialchars($selected_run->month_year); ?></span></h4>
                        <div class="text-secondary small mt-1">Status: <span class="text-uppercase font-weight-bold text-white"><?php echo htmlspecialchars($selected_run->status); ?></span></div>
                    </div>
                    
                    <div class="d-flex flex-wrap gap-2">
                        <!-- Run State Actions -->
                        <?php if ($selected_run->status === 'generated' && in_array($role, ['admin', 'hr'], true)): ?>
                            <form action="index.php?route=payroll/run_approve/<?php echo $selected_run->payroll_run_id; ?>" method="POST">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                                <button type="submit" class="btn btn-warning btn-sm"><i class="fa-solid fa-check-double me-1"></i>Approve Run</button>
                            </form>
                        <?php endif; ?>

                        <?php if ($selected_run->status === 'approved' && in_array($role, ['admin', 'finance'], true)): ?>
                            <form action="index.php?route=payroll/run_lock/<?php echo $selected_run->payroll_run_id; ?>" method="POST">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                                <button type="submit" class="btn btn-info btn-sm"><i class="fa-solid fa-lock me-1"></i>Lock Payroll</button>
                            </form>
                        <?php endif; ?>

                        <?php if ($selected_run->status === 'locked' && in_array($role, ['admin', 'finance'], true)): ?>
                            <form action="index.php?route=payroll/run_release/<?php echo $selected_run->payroll_run_id; ?>" method="POST" class="d-inline">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                                <button type="submit" class="btn btn-success btn-sm" onclick="return confirm('Release payslips to employees and complete runs?');"><i class="fa-solid fa-circle-check me-1"></i>Release Payslips</button>
                            </form>
                        <?php endif; ?>

                        <?php if (in_array($selected_run->status, ['locked', 'released'], true) && in_array($role, ['admin', 'finance'], true)): ?>
                            <a href="index.php?route=payroll/export_bank_file/<?php echo $selected_run->payroll_run_id; ?>" class="btn btn-outline-info btn-sm">
                                <i class="fa-solid fa-download me-1"></i>Export Bank CSV (RTGS)
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-dark table-hover align-middle border-secondary small">
                        <thead>
                            <tr class="text-secondary">
                                <th>Emp ID</th>
                                <th>Name</th>
                                <th>Bank Account</th>
                                <th class="text-end">Gross</th>
                                <th class="text-end">Deductions</th>
                                <th class="text-end">Net Salary</th>
                                <th class="text-center">Status</th>
                                <th class="text-end">Payslip</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($details as $row): ?>
                                <tr>
                                    <td class="font-monospace text-secondary"><?php echo htmlspecialchars($row->employee_code); ?></td>
                                    <td class="font-weight-bold text-white"><?php echo htmlspecialchars($row->name); ?></td>
                                    <td>
                                        <div class="small">
                                            <strong><?php echo htmlspecialchars($row->bank_name ?: 'N/A'); ?></strong><br>
                                            <span class="text-secondary font-monospace"><?php echo htmlspecialchars($row->account_number ?: 'N/A'); ?></span>
                                        </div>
                                    </td>
                                    <td class="text-end font-monospace">Rs. <?php echo number_format($row->gross_salary, 2); ?></td>
                                    <td class="text-end font-monospace">Rs. <?php echo number_format($row->total_deductions, 2); ?></td>
                                    <td class="text-end font-weight-bold text-success font-monospace">Rs. <?php echo number_format($row->net_salary, 2); ?></td>
                                    <td class="text-center">
                                        <span class="badge bg-<?php echo $row->payment_status === 'paid' ? 'success' : 'danger'; ?>-subtle text-<?php echo $row->payment_status === 'paid' ? 'success' : 'danger'; ?> border border-<?php echo $row->payment_status === 'paid' ? 'success' : 'danger'; ?>-subtle text-uppercase" style="font-size: 0.65rem;">
                                            <?php echo htmlspecialchars($row->payment_status); ?>
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <a href="index.php?route=payroll/payslip_view/<?php echo $row->payroll_detail_id; ?>" target="_blank" class="btn btn-outline-light btn-sm py-0 px-2" style="font-size: 0.75rem;">
                                            <i class="fa-solid fa-receipt me-1"></i>View
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php else: ?>
            <div class="pulse-card text-center py-5">
                <i class="fa-solid fa-receipt mb-3 text-secondary fs-1"></i>
                <h5 class="text-white">Select a Payroll Run</h5>
                <p class="text-secondary small">Please select a payroll run from the left panel to display details, calculate metrics, and process approvals or payments.</p>
            </div>
        <?php endif; ?>
    </div>
</div>
