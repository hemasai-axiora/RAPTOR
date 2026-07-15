<?php
$isPrivileged     = in_array($role, ['admin', 'hr', 'finance', 'manager', 'team_leader'], true);
$canManagerApprove= in_array($role, ['admin', 'manager', 'team_leader', 'hr'], true);
$canFinanceApprove= in_array($role, ['admin', 'hr', 'finance'], true);
$colCount         = $isPrivileged ? 8 : 7;
?>
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

    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="text-white mb-1">
                <i class="fa-solid fa-hand-holding-dollar me-2" style="color: var(--primary);"></i>Reimbursements Center
            </h4>
            <div class="text-secondary small">Submit expense claims and track the approval workflow.</div>
        </div>
        <button class="btn btn-primary btn-sm px-3 py-2" data-bs-toggle="modal" data-bs-target="#claimReimbursementModal"
                style="background: var(--primary); border: none; border-radius: 8px;">
            <i class="fa-solid fa-plus me-1"></i> Add Claim
        </button>
    </div>

    <!-- Workflow legend -->
    <div class="d-flex align-items-center gap-2 mb-4 flex-wrap">
        <small class="text-secondary fw-semibold">Approval flow:</small>
        <span class="badge bg-warning-subtle text-warning border border-warning-subtle" style="font-size:0.7rem;">⏳ Pending (Employee)</span>
        <i class="fa-solid fa-arrow-right text-secondary" style="font-size:0.6rem;"></i>
        <span class="badge bg-info-subtle text-info border border-info-subtle" style="font-size:0.7rem;">✓ Manager Approved</span>
        <i class="fa-solid fa-arrow-right text-secondary" style="font-size:0.6rem;"></i>
        <span class="badge bg-success-subtle text-success border border-success-subtle" style="font-size:0.7rem;">✓✓ Finance Approved</span>
        <i class="fa-solid fa-arrow-right text-secondary" style="font-size:0.6rem;"></i>
        <span class="badge bg-primary-subtle text-primary border border-primary-subtle" style="font-size:0.7rem;">💰 Added to Payroll</span>
    </div>

    <!-- Claims Table -->
    <div class="table-responsive">
        <table class="table table-dark table-hover align-middle border-secondary text-white">
            <thead>
                <tr class="text-secondary" style="border-bottom:1px solid #2a2d3e;">
                    <th>Date</th>
                    <?php if ($isPrivileged): ?><th>Employee</th><?php endif; ?>
                    <th>Category</th>
                    <th>Amount</th>
                    <th>Description</th>
                    <th class="text-center">Receipt</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($claims)): ?>
                <tr>
                    <td colspan="<?php echo $colCount; ?>" class="text-center py-5 text-secondary">
                        <i class="fa-solid fa-receipt fa-2x mb-3 d-block" style="opacity:0.25;"></i>
                        No claims found. Click <strong>+ Add Claim</strong> to submit your first expense.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($claims as $c): ?>
                    <?php
                    $toneMap = [
                        'pending'          => 'warning',
                        'manager_approved' => 'info',
                        'finance_approved' => 'success',
                        'rejected'         => 'danger',
                    ];
                    $tone = $toneMap[$c->status] ?? 'secondary';
                    $statusLabel = match($c->status) {
                        'pending'          => '⏳ Pending',
                        'manager_approved' => '✓ Mgr Approved',
                        'finance_approved' => '✓✓ Finance Approved',
                        'rejected'         => '✗ Rejected',
                        default            => ucwords(str_replace('_', ' ', $c->status)),
                    };
                    $catIcon = match($c->claim_type) {
                        'Travel'   => 'fa-plane',
                        'Fuel'     => 'fa-gas-pump',
                        'Food'     => 'fa-utensils',
                        'Internet' => 'fa-wifi',
                        'Medical'  => 'fa-stethoscope',
                        default    => 'fa-receipt',
                    };
                    ?>
                    <tr>
                        <td class="text-secondary small"><?php echo date('d M Y', strtotime($c->created_at)); ?></td>
                        <?php if ($isPrivileged): ?>
                            <td>
                                <span class="text-white fw-semibold"><?php echo htmlspecialchars($c->employee_name ?? '—'); ?></span><br>
                                <span class="text-secondary font-monospace" style="font-size:0.7rem;"><?php echo htmlspecialchars($c->employee_code ?? ''); ?></span>
                            </td>
                        <?php endif; ?>
                        <td>
                            <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">
                                <i class="fa-solid <?php echo $catIcon; ?> me-1"></i><?php echo htmlspecialchars($c->claim_type); ?>
                            </span>
                        </td>
                        <td class="fw-bold font-monospace" style="color:#6bdfb8;">
                            &#8377;<?php echo number_format($c->amount, 2); ?>
                        </td>
                        <td class="text-secondary small" style="max-width:180px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"
                            title="<?php echo htmlspecialchars($c->description ?? ''); ?>">
                            <?php echo htmlspecialchars($c->description ?: '—'); ?>
                        </td>
                        <td class="text-center">
                            <?php if (!empty($c->attachment_url)): ?>
                                <a href="index.php?route=file/show&key=<?php echo urlencode($c->attachment_url); ?>"
                                   target="_blank" class="btn btn-outline-info btn-sm py-0 px-2" style="font-size:0.72rem;">
                                    <i class="fa-solid fa-paperclip me-1"></i>View
                                </a>
                            <?php else: ?>
                                <span class="text-secondary small">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge bg-<?php echo $tone; ?>-subtle text-<?php echo $tone; ?> border border-<?php echo $tone; ?>-subtle"
                                  style="font-size:0.7rem; white-space:nowrap;">
                                <?php echo $statusLabel; ?>
                            </span>
                        </td>
                        <td class="text-end">
                            <div class="d-inline-flex gap-1 flex-wrap justify-content-end">

                                <?php if ($c->status === 'pending' && $canManagerApprove): ?>
                                    <form action="index.php?route=payroll/approve_reimbursement/<?php echo $c->reimbursement_id; ?>" method="POST" class="d-inline">
                                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                                        <input type="hidden" name="action" value="approve">
                                        <button type="submit" class="btn btn-outline-success btn-sm py-0 px-2" style="font-size:0.72rem;" title="Manager Approve">
                                            <i class="fa-solid fa-check"></i> Approve
                                        </button>
                                    </form>
                                    <form action="index.php?route=payroll/approve_reimbursement/<?php echo $c->reimbursement_id; ?>" method="POST" class="d-inline">
                                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                                        <input type="hidden" name="action" value="reject">
                                        <button type="submit" class="btn btn-outline-danger btn-sm py-0 px-2" style="font-size:0.72rem;" title="Reject">
                                            <i class="fa-solid fa-xmark"></i> Reject
                                        </button>
                                    </form>

                                <?php elseif ($c->status === 'manager_approved' && $canFinanceApprove): ?>
                                    <form action="index.php?route=payroll/approve_reimbursement/<?php echo $c->reimbursement_id; ?>" method="POST" class="d-inline">
                                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                                        <input type="hidden" name="action" value="approve">
                                        <button type="submit" class="btn btn-outline-success btn-sm py-0 px-2" style="font-size:0.72rem;" title="Finance Approve">
                                            <i class="fa-solid fa-check-double"></i> Finance OK
                                        </button>
                                    </form>
                                    <form action="index.php?route=payroll/approve_reimbursement/<?php echo $c->reimbursement_id; ?>" method="POST" class="d-inline">
                                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                                        <input type="hidden" name="action" value="reject">
                                        <button type="submit" class="btn btn-outline-danger btn-sm py-0 px-2" style="font-size:0.72rem;" title="Reject">
                                            <i class="fa-solid fa-xmark"></i> Reject
                                        </button>
                                    </form>

                                <?php elseif ($c->status === 'finance_approved'): ?>
                                    <span class="text-success small"><i class="fa-solid fa-circle-check"></i> Payroll</span>

                                <?php elseif ($c->status === 'rejected'): ?>
                                    <span class="text-danger small"><i class="fa-solid fa-ban"></i> Rejected</span>

                                <?php else: ?>
                                    <span class="text-secondary small">—</span>
                                <?php endif; ?>

                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ================================================================
     Add Claim Modal
     ================================================================ -->
<div class="modal fade" id="claimReimbursementModal" tabindex="-1" aria-labelledby="claimModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-secondary" style="background:#12141f;">
            <div class="modal-header border-secondary" style="background:linear-gradient(135deg,#1a1c2e,#0f1118);">
                <h5 class="modal-title text-white" id="claimModalLabel">
                    <i class="fa-solid fa-file-invoice-dollar me-2" style="color:var(--primary);"></i>New Expense Claim
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="index.php?route=payroll/submit_reimbursement" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                <div class="modal-body">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label text-secondary small fw-semibold">
                                <i class="fa-regular fa-calendar me-1"></i>Expense Date *
                            </label>
                            <input type="date" name="claim_date" class="form-control bg-dark border-secondary text-white"
                                   value="<?php echo date('Y-m-d'); ?>" max="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-secondary small fw-semibold">
                                <i class="fa-solid fa-tag me-1"></i>Category *
                            </label>
                            <select name="claim_type" class="form-select bg-dark border-secondary text-white" required>
                                <option value="">— Select —</option>
                                <option value="Travel">✈ Travel</option>
                                <option value="Fuel">⛽ Fuel</option>
                                <option value="Food">🍽 Food</option>
                                <option value="Internet">📡 Internet</option>
                                <option value="Medical">🏥 Medical</option>
                                <option value="Other">📋 Other Expenses</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-secondary small fw-semibold">
                            <i class="fa-solid fa-indian-rupee-sign me-1"></i>Claimed Amount (₹) *
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-dark border-secondary text-secondary">₹</span>
                            <input type="number" step="0.01" min="1" name="amount"
                                   class="form-control bg-dark border-secondary text-white"
                                   placeholder="0.00" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-secondary small fw-semibold">
                            <i class="fa-solid fa-pen-clip me-1"></i>Business Reason *
                        </label>
                        <textarea name="description" class="form-control bg-dark border-secondary text-white" rows="3"
                                  placeholder="Describe the purpose of this expense..." required></textarea>
                    </div>
                    <div class="mb-1">
                        <label class="form-label text-secondary small fw-semibold">
                            <i class="fa-solid fa-paperclip me-1"></i>Receipt / Bill <span class="text-secondary fw-normal">(JPEG, PNG, PDF — optional)</span>
                        </label>
                        <input type="file" name="receipt" class="form-control bg-dark border-secondary text-white"
                               accept="image/*,application/pdf">
                        <div class="form-text text-secondary" style="font-size:0.7rem;">Attach a scanned receipt for faster approval.</div>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" style="background:var(--primary);border:none;min-width:130px;">
                        <i class="fa-solid fa-paper-plane me-2"></i>Submit Claim
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
