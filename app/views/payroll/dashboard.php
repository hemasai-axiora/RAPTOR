<div class="pulse-card">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="text-white mb-1"><i class="fa-solid fa-chart-pie me-2" style="color: var(--primary);"></i>Payroll Management</h4>
            <div class="text-secondary small">Oversee salary structures, generate monthly runs, process payslips, track bonuses, and approve reimbursement claims.</div>
        </div>
    </div>

    <!-- Stats Dashboard row -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="pulse-card p-3 h-100" style="border-radius:12px; background: rgba(255,255,255,0.03);">
                <div class="text-secondary small font-weight-bold uppercase mb-2">Total Payroll Runs</div>
                <div class="d-flex align-items-center justify-content-between">
                    <h2 class="text-white mb-0 font-monospace"><?php echo count($runs); ?></h2>
                    <div class="rounded-circle d-flex align-items-center justify-content-center bg-primary bg-opacity-10 text-primary" style="width: 48px; height: 48px;">
                        <i class="fa-solid fa-file-invoice-dollar fs-5"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="pulse-card p-3 h-100" style="border-radius:12px; background: rgba(255,255,255,0.03);">
                <div class="text-secondary small font-weight-bold uppercase mb-2">Pending Claims</div>
                <div class="d-flex align-items-center justify-content-between">
                    <h2 class="text-white mb-0 font-monospace"><?php echo $pending_claims; ?></h2>
                    <div class="rounded-circle d-flex align-items-center justify-content-center bg-warning bg-opacity-10 text-warning" style="width: 48px; height: 48px;">
                        <i class="fa-solid fa-hand-holding-dollar fs-5"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="pulse-card p-3 h-100" style="border-radius:12px; background: rgba(255,255,255,0.03);">
                <div class="text-secondary small font-weight-bold uppercase mb-2">Quick Navigation</div>
                <div class="d-flex flex-wrap gap-2 mt-1">
                    <?php if (in_array($role, ['admin', 'hr'], true)): ?>
                        <a href="index.php?route=payroll/structures" class="btn btn-outline-info btn-sm"><i class="fa-solid fa-calculator me-1"></i>Structures</a>
                    <?php endif; ?>
                    <?php if (in_array($role, ['admin', 'hr', 'finance'], true)): ?>
                        <a href="index.php?route=payroll/processing" class="btn btn-outline-primary btn-sm"><i class="fa-solid fa-gears me-1"></i>Process Run</a>
                    <?php endif; ?>
                    <a href="index.php?route=payroll/reimbursements" class="btn btn-outline-warning btn-sm"><i class="fa-solid fa-receipt me-1"></i>Claims</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Historical Runs List -->
    <h5 class="text-white mb-3"><i class="fa-solid fa-history me-2 text-secondary"></i>Historical Payroll runs</h5>
    <div class="table-responsive">
        <table class="table table-dark table-hover align-middle border-secondary">
            <thead>
                <tr class="text-secondary">
                    <th>Month/Year</th>
                    <th>Status</th>
                    <th>Calculated By</th>
                    <th>Approved By</th>
                    <th>Released By</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($runs)): ?>
                    <tr>
                        <td colspan="6" class="text-center py-4 text-secondary">No payroll runs initialized.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($runs as $run): ?>
                        <tr>
                            <td class="font-weight-bold text-white font-monospace"><?php echo htmlspecialchars($run->month_year); ?></td>
                            <td>
                                <?php
                                    $tone = [
                                        'generated' => 'primary',
                                        'approved' => 'warning',
                                        'locked' => 'info',
                                        'released' => 'success'
                                    ][$run->status] ?? 'secondary';
                                ?>
                                <span class="badge bg-<?php echo $tone; ?>-subtle text-<?php echo $tone; ?> border border-<?php echo $tone; ?>-subtle text-uppercase">
                                    <?php echo htmlspecialchars($run->status); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($run->creator_name ?? 'System'); ?></td>
                            <td><?php echo htmlspecialchars($run->approver_name ?: 'Pending'); ?></td>
                            <td><?php echo htmlspecialchars($run->releaser_name ?: 'Pending'); ?></td>
                            <td class="text-end">
                                <a href="index.php?route=payroll/processing&run_id=<?php echo $run->payroll_run_id; ?>" class="btn btn-outline-light btn-sm">
                                    <i class="fa-solid fa-folder-open me-1"></i>View Details
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
