<div class="pulse-card">
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

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="text-white mb-0"><i class="fa-solid fa-gift me-2" style="color: var(--primary);"></i>Bonuses & Perks</h4>
        <?php if (in_array($role, ['admin', 'hr', 'finance'], true)): ?>
            <button class="btn btn-primary btn-sm px-3 py-2" data-bs-toggle="modal" data-bs-target="#addBonusModal" style="background: var(--primary); border: none; border-radius: 8px;">
                <i class="fa-solid fa-plus me-2"></i>Allocate Bonus
            </button>
        <?php endif; ?>
    </div>

    <!-- Bonuses Ledger Table -->
    <div class="table-responsive">
        <table class="table table-dark table-hover align-middle border-secondary text-white">
            <thead>
                <tr class="text-secondary">
                    <th>Date</th>
                    <?php if (in_array($role, ['admin', 'hr', 'finance'], true)): ?>
                        <th>Employee</th>
                    <?php endif; ?>
                    <th>Bonus Type</th>
                    <th>Amount</th>
                    <th>Description</th>
                    <th>Status</th>
                    <?php if (in_array($role, ['admin', 'hr', 'finance'], true)): ?>
                        <th class="text-end">Actions</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($bonuses)): ?>
                    <tr>
                        <td colspan="<?php echo in_array($role, ['admin', 'hr', 'finance'], true) ? '7' : '5'; ?>" class="text-center py-4 text-secondary">No bonuses assigned.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($bonuses as $b): ?>
                        <tr>
                            <td><?php echo date('Y-m-d', strtotime($b->created_at)); ?></td>
                            <?php if (in_array($role, ['admin', 'hr', 'finance'], true)): ?>
                                <td>
                                    <strong><?php echo htmlspecialchars($b->employee_name); ?></strong><br>
                                    <span class="text-secondary font-monospace small"><?php echo htmlspecialchars($b->employee_code); ?></span>
                                </td>
                            <?php endif; ?>
                            <td><span class="badge bg-info-subtle text-info border border-info-subtle"><?php echo htmlspecialchars($b->bonus_type); ?></span></td>
                            <td class="font-weight-bold text-success font-monospace">Rs. <?php echo number_format($b->amount, 2); ?></td>
                            <td><?php echo htmlspecialchars($b->description ?: 'N/A'); ?></td>
                            <td>
                                <?php
                                    $tone = [
                                        'pending' => 'warning',
                                        'approved' => 'info',
                                        'paid' => 'success'
                                    ][$b->status] ?? 'secondary';
                                ?>
                                <span class="badge bg-<?php echo $tone; ?>-subtle text-<?php echo $tone; ?> border border-<?php echo $tone; ?>-subtle text-uppercase" style="font-size:0.7rem;">
                                    <?php echo htmlspecialchars($b->status); ?>
                                </span>
                            </td>
                            <?php if (in_array($role, ['admin', 'hr', 'finance'], true)): ?>
                                <td class="text-end">
                                    <div class="d-inline-flex gap-2">
                                        <?php if ($b->status === 'pending'): ?>
                                            <form action="index.php?route=payroll/approve_bonus/<?php echo $b->bonus_id; ?>" method="POST">
                                                <input type="hidden" name="status" value="approved">
                                                <button type="submit" class="btn btn-outline-info btn-sm py-1 px-2" style="font-size:0.75rem;">Approve</button>
                                            </form>
                                        <?php endif; ?>
                                        <?php if ($b->status === 'approved' && in_array($role, ['admin', 'finance'], true)): ?>
                                            <form action="index.php?route=payroll/approve_bonus/<?php echo $b->bonus_id; ?>" method="POST">
                                                <input type="hidden" name="status" value="paid">
                                                <button type="submit" class="btn btn-outline-success btn-sm py-1 px-2" style="font-size:0.75rem;"><i class="fa-solid fa-wallet me-1"></i>Pay Now</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Allocate Bonus Modal (Admin/HR/Finance only) -->
<?php if (in_array($role, ['admin', 'hr', 'finance'], true)): ?>
    <div class="modal fade" id="addBonusModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content bg-dark text-white border-secondary">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title">Allocate Bonus & Perks</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="index.php?route=payroll/add_bonus" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label text-secondary">Target Employee *</label>
                            <select name="employee_id" class="form-select bg-dark border-secondary text-white" required>
                                <option value="">-- Choose Employee --</option>
                                <?php foreach ($employees as $e): ?>
                                    <option value="<?php echo $e->employee_id; ?>"><?php echo htmlspecialchars($e->name) . " (" . htmlspecialchars($e->employee_code) . ")"; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-secondary">Bonus Type *</label>
                            <select name="bonus_type" class="form-select bg-dark border-secondary text-white" required>
                                <option value="Performance">Performance Incentive</option>
                                <option value="Festival">Festival Bonus</option>
                                <option value="Sales Incentives">Sales Incentives</option>
                                <option value="Referral">Referral Bonus</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-secondary">Amount (Rs.) *</label>
                            <input type="number" step="0.01" name="amount" class="form-control bg-dark border-secondary text-white" placeholder="0.00" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-secondary">Description / Remarks</label>
                            <textarea name="description" class="form-control bg-dark border-secondary text-white" rows="3" placeholder="Provide reason or comments..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer border-secondary">
                        <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" style="background: var(--primary); border: none;">Allocate</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>
