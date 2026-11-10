<div class="pulse-card mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h4 class="text-white mb-1 d-flex align-items-center gap-2">
                <i class="fa-solid fa-scale-balanced text-primary"></i>
                Month-over-Month Payroll Reconciliation
            </h4>
            <div class="text-secondary" style="font-size: 0.9rem;">
                Variance audit comparing <strong><?php echo htmlspecialchars($current_month); ?></strong> against prior cycle (<strong><?php echo htmlspecialchars($prev_month); ?></strong>).
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <form method="GET" action="index.php" class="d-flex align-items-center gap-2">
                <input type="hidden" name="route" value="payroll/reconciliation">
                <label class="text-secondary small text-nowrap">Target Month:</label>
                <select name="month_year" class="form-select form-select-sm bg-dark border-secondary text-white" onchange="this.form.submit()">
                    <?php if (!empty($runs)): ?>
                        <?php foreach ($runs as $r): ?>
                            <option value="<?php echo htmlspecialchars($r->month_year); ?>" <?php echo $r->month_year === $current_month ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars(PayslipHelper::formatMonthYear($r->month_year)); ?>
                            </option>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <option value="<?php echo htmlspecialchars($current_month); ?>" selected><?php echo htmlspecialchars($current_month); ?></option>
                    <?php endif; ?>
                </select>
            </form>
            <a href="index.php?route=payroll/processing" class="btn btn-outline-secondary btn-sm px-3">
                <i class="fa-solid fa-calculator me-1"></i>Payroll Runs
            </a>
        </div>
    </div>

    <!-- Summary KPI Variance Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3 col-6">
            <div class="p-3 rounded bg-dark border border-secondary text-center">
                <div class="text-secondary small mb-1">Total Net Payout</div>
                <div class="text-white fw-bold fs-5 font-monospace">$<?php echo number_format($totals['curr_net'], 2); ?></div>
                <div class="small mt-1 <?php echo $totals['diff_net'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                    <i class="fa-solid <?php echo $totals['diff_net'] >= 0 ? 'fa-arrow-trend-up' : 'fa-arrow-trend-down'; ?> me-1"></i>
                    <?php echo ($totals['diff_net'] >= 0 ? '+' : '') . number_format($totals['diff_net'], 2); ?> MoM
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="p-3 rounded bg-dark border border-secondary text-center">
                <div class="text-secondary small mb-1">Total Gross Earnings</div>
                <div class="text-white fw-bold fs-5 font-monospace">$<?php echo number_format($totals['curr_gross'], 2); ?></div>
                <div class="small mt-1 <?php echo $totals['diff_gross'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                    <i class="fa-solid <?php echo $totals['diff_gross'] >= 0 ? 'fa-arrow-trend-up' : 'fa-arrow-trend-down'; ?> me-1"></i>
                    <?php echo ($totals['diff_gross'] >= 0 ? '+' : '') . number_format($totals['diff_gross'], 2); ?> MoM
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="p-3 rounded bg-dark border border-secondary text-center">
                <div class="text-secondary small mb-1">Active Headcount</div>
                <div class="text-white fw-bold fs-5 font-monospace"><?php echo $totals['headcount_curr']; ?> employees</div>
                <div class="text-secondary small mt-1">
                    Prior: <?php echo $totals['headcount_prev']; ?> (<?php echo ($totals['headcount_curr'] - $totals['headcount_prev'] >= 0 ? '+' : '') . ($totals['headcount_curr'] - $totals['headcount_prev']); ?>)
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="p-3 rounded bg-dark border border-secondary text-center">
                <div class="text-secondary small mb-1">Variance Breakdown</div>
                <div class="d-flex justify-content-center gap-1.5 flex-wrap mt-1">
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle font-monospace" style="font-size: 0.68rem;">+<?php echo $totals['new_joinees']; ?> Join</span>
                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle font-monospace" style="font-size: 0.68rem;"><?php echo $totals['revised']; ?> Rev</span>
                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle font-monospace" style="font-size: 0.68rem;">-<?php echo $totals['exited']; ?> Exit</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Sticky Header Matrix Table -->
    <style>
        .table-sticky-header thead th {
            position: sticky;
            top: 0;
            background: #111827 !important;
            z-index: 10;
            box-shadow: 0 2px 4px rgba(0,0,0,0.4);
        }
    </style>

    <div class="table-responsive" style="max-height: 600px; overflow-y: auto;">
        <table class="table table-dark table-hover align-middle border-secondary table-sticky-header" id="reconciliation-table">
            <thead>
                <tr class="text-secondary small" style="border-bottom: 2px solid var(--border-color);">
                    <th>Employee</th>
                    <th>Department</th>
                    <th class="text-center">Audit Status</th>
                    <th class="text-end">Prior Gross</th>
                    <th class="text-end">Current Gross</th>
                    <th class="text-end">Gross Variance</th>
                    <th class="text-end">Prior Net</th>
                    <th class="text-end">Current Net</th>
                    <th class="text-end">Net Variance</th>
                    <th class="text-center">LOP (Prev &rarr; Curr)</th>
                    <th>Audit Remarks</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($matrix)): ?>
                    <tr>
                        <td colspan="11" class="text-center py-5 text-secondary">
                            <i class="fa-solid fa-folder-open fa-2x mb-2 d-block opacity-50"></i>
                            No payroll run data available for the selected month to reconcile.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($matrix as $row): ?>
                        <?php
                            $statusBadge = [
                                'New Joinee' => 'bg-primary-subtle text-primary border-primary-subtle',
                                'Separated'  => 'bg-danger-subtle text-danger border-danger-subtle',
                                'Revised'    => 'bg-warning-subtle text-warning border-warning-subtle',
                                'Unchanged'  => 'bg-secondary-subtle text-secondary border-secondary-subtle',
                            ][$row['status']] ?? 'bg-secondary-subtle text-secondary';

                            $diffNetClass = $row['diff_net'] > 0.5 ? 'text-success' : ($row['diff_net'] < -0.5 ? 'text-danger' : 'text-secondary');
                            $diffGrossClass = $row['diff_gross'] > 0.5 ? 'text-success' : ($row['diff_gross'] < -0.5 ? 'text-danger' : 'text-secondary');
                        ?>
                        <tr style="border-bottom: 1px solid var(--border-color);">
                            <td>
                                <div class="fw-semibold text-white"><?php echo htmlspecialchars($row['name']); ?></div>
                                <span class="badge bg-dark border border-secondary text-primary font-monospace" style="font-size:0.68rem;">
                                    <?php echo htmlspecialchars($row['employee_code'] ?: ('#' . $row['employee_id'])); ?>
                                </span>
                            </td>
                            <td class="text-secondary small"><?php echo htmlspecialchars($row['department']); ?></td>
                            <td class="text-center">
                                <span class="badge border <?php echo $statusBadge; ?>" style="font-size:0.7rem;">
                                    <?php echo htmlspecialchars($row['status']); ?>
                                </span>
                            </td>
                            <td class="text-end font-monospace text-secondary small">$<?php echo number_format((float)$row['prev_gross'], 2); ?></td>
                            <td class="text-end font-monospace text-white small">$<?php echo number_format((float)$row['curr_gross'], 2); ?></td>
                            <td class="text-end font-monospace fw-semibold <?php echo $diffGrossClass; ?> small">
                                <?php echo ($row['diff_gross'] > 0 ? '+' : '') . number_format((float)$row['diff_gross'], 2); ?>
                            </td>
                            <td class="text-end font-monospace text-secondary small">$<?php echo number_format((float)$row['prev_net'], 2); ?></td>
                            <td class="text-end font-monospace text-white fw-semibold small">$<?php echo number_format((float)$row['curr_net'], 2); ?></td>
                            <td class="text-end font-monospace fw-bold <?php echo $diffNetClass; ?> small">
                                <?php echo ($row['diff_net'] > 0 ? '+' : '') . number_format((float)$row['diff_net'], 2); ?>
                            </td>
                            <td class="text-center font-monospace small">
                                <?php echo (int)$row['prev_lop']; ?> &rarr; <strong class="<?php echo $row['curr_lop'] > 0 ? 'text-warning' : 'text-secondary'; ?>"><?php echo (int)$row['curr_lop']; ?></strong>
                            </td>
                            <td class="text-secondary small" style="max-width: 220px;">
                                <?php echo htmlspecialchars($row['remarks']); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
$(function() {
    if ($('#reconciliation-table tbody tr').length > 1) {
        $('#reconciliation-table').DataTable({
            pageLength: 25,
            lengthChange: false,
            info: true,
            searching: true,
            dom: '<"d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3"<"d-flex align-items-center gap-2"B><"d-flex align-items-center"f>>rt<"d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3"ip>',
            buttons: [
                {
                    extend: 'csvHtml5',
                    text: '<i class="fa-solid fa-file-csv me-1 text-info"></i> CSV Export',
                    className: 'btn btn-sm btn-outline-secondary',
                    title: 'Raptor_Payroll_Reconciliation_<?php echo htmlspecialchars($current_month); ?>'
                },
                {
                    extend: 'excelHtml5',
                    text: '<i class="fa-solid fa-file-excel me-1 text-success"></i> Excel Export',
                    className: 'btn btn-sm btn-outline-secondary',
                    title: 'Raptor_Payroll_Reconciliation_<?php echo htmlspecialchars($current_month); ?>'
                }
            ],
            language: { 
                search: '',
                searchPlaceholder: 'Search variance records...' 
            }
        });
    }
});
</script>

