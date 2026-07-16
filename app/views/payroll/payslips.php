<div class="pulse-card">
    <h4 class="text-white mb-4"><i class="fa-solid fa-receipt me-2" style="color: var(--primary);"></i>Payslips Directory</h4>

    <?php if (in_array($role, ['admin', 'hr', 'finance'], true)): ?>
        <!-- Admin/HR/Finance Selection Panel -->
        <form method="GET" action="index.php" class="row g-3 align-items-end mb-4 border-bottom border-secondary pb-4">
            <input type="hidden" name="route" value="payroll/payslips">
            <div class="col-md-8">
                <label for="run_id" class="form-label text-secondary font-weight-bold">Select Payroll Period</label>
                <select name="run_id" id="run_id" class="form-select bg-dark border-secondary text-white" onchange="this.form.submit()">
                    <option value="">-- Select Released Month --</option>
                    <?php foreach ($runs as $r): ?>
                        <?php if ($r->status === 'released'): ?>
                            <option value="<?php echo $r->payroll_run_id; ?>" <?php echo (int)$selected_run_id === (int)$r->payroll_run_id ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($r->month_year); ?>
                            </option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-outline-light w-100">Load Payslips</button>
            </div>
        </form>

        <!-- Selected Run Payslips -->
        <?php if ((int)$selected_run_id > 0): ?>
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="text-white mb-0">Employee Payslips</h5>
                <form action="index.php?route=payroll/bulk_email_payslips" method="POST" class="d-inline">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                    <input type="hidden" name="run_id" value="<?php echo $selected_run_id; ?>">
                    <button type="submit" class="btn btn-primary btn-sm px-3" style="background: var(--primary); border: none; border-radius: 8px;" onclick="return confirm('Are you sure you want to send payslip email notifications to all employees in this run?');">
                        <i class="fa-solid fa-paper-plane me-1"></i> Bulk Email Payslips
                    </button>
                </form>
            </div>
            <div class="table-responsive">
                <table class="table table-dark table-hover align-middle border-secondary small" id="admin-payslips-table">
                    <thead>
                        <tr class="text-secondary">
                            <th>Emp ID</th>
                            <th>Name</th>
                            <th>Job Title</th>
                            <th class="text-end">Gross Salary</th>
                            <th class="text-end">Deductions</th>
                            <th class="text-end">Net Salary</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($all_payslips)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-4 text-secondary">No payslips calculated for this run.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($all_payslips as $slip): ?>
                                <tr>
                                    <td class="font-monospace text-secondary"><?php echo htmlspecialchars($slip->employee_code); ?></td>
                                    <td class="font-weight-bold text-white"><?php echo htmlspecialchars($slip->name); ?></td>
                                    <td><?php echo htmlspecialchars($slip->job_title ?: 'Staff'); ?></td>
                                    <td class="text-end">Rs. <?php echo number_format($slip->gross_salary, 2); ?></td>
                                    <td class="text-end">Rs. <?php echo number_format($slip->total_deductions, 2); ?></td>
                                    <td class="text-end text-success font-weight-bold">Rs. <?php echo number_format($slip->net_salary, 2); ?></td>
                                    <td class="text-end">
                                        <a href="index.php?route=payroll/payslip_view/<?php echo $slip->payroll_detail_id; ?>" target="_blank" class="btn btn-outline-light btn-sm py-1 px-3">
                                            <i class="fa-solid fa-receipt me-1"></i>View Payslip
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center py-4 text-secondary">
                <i class="fa-solid fa-folder-open mb-2 fs-4"></i>
                <p>Select a released payroll run to view all employee payslips.</p>
            </div>
        <?php endif; ?>

    <?php else: ?>
        <!-- Standard Employee Payslips View -->
        <div class="table-responsive">
            <table class="table table-dark table-hover align-middle border-secondary text-white">
                <thead>
                    <tr class="text-secondary">
                        <th>Month / Year</th>
                        <th>Working Days</th>
                        <th>Present Days</th>
                        <th class="text-end">Gross Payout</th>
                        <th class="text-end">Deductions</th>
                        <th class="text-end">Net Pay</th>
                        <th class="text-center">Status</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($my_payslips)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-4 text-secondary">No payslips have been released for your account yet.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($my_payslips as $slip): ?>
                            <tr>
                                <td class="font-weight-bold text-white font-monospace"><?php echo htmlspecialchars($slip->month_year); ?></td>
                                <td><?php echo htmlspecialchars($slip->working_days); ?></td>
                                <td><?php echo htmlspecialchars($slip->present_days); ?></td>
                                <td class="font-monospace text-end">Rs. <?php echo number_format($slip->gross_salary, 2); ?></td>
                                <td class="text-end font-monospace">Rs. <?php echo number_format($slip->total_deductions, 2); ?></td>
                                <td class="text-end font-weight-bold text-success font-monospace">Rs. <?php echo number_format($slip->net_salary, 2); ?></td>
                                <td class="text-center">
                                    <span class="badge bg-success-subtle text-success border border-success-subtle text-uppercase" style="font-size: 0.65rem;">
                                        <?php echo htmlspecialchars($slip->payment_status); ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <a href="index.php?route=payroll/payslip_view/<?php echo $slip->payroll_detail_id; ?>" target="_blank" class="btn btn-outline-info btn-sm py-1 px-3">
                                        <i class="fa-solid fa-receipt me-1"></i>View Payslip
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<script>
$(document).ready(function() {
    if ($('#admin-payslips-table').length) {
        $('#admin-payslips-table').DataTable({
            "pageLength": 10,
            "lengthChange": false,
            "info": false,
            "searching": true,
            "language": {
                "search": "Filter Payslips:"
            }
        });
    }
});
</script>
