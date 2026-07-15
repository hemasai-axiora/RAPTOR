<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h4 class="text-white mb-1">Target Planning</h4>
        <div class="text-secondary" style="font-size:0.9rem;">Plan daily, weekly, or monthly targets and track achieved values.</div>
    </div>
    <div class="d-flex gap-2">
        <?php if ($can_approve): ?>
            <form action="index.php?route=targets/recompute" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                <button class="btn btn-outline-info btn-sm"><i class="fa-solid fa-rotate me-2"></i>Refresh Progress</button>
            </form>
        <?php endif; ?>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addTargetModal" style="background: var(--primary); border: none;">
            <i class="fa-solid fa-bullseye me-2"></i>Plan Target
        </button>
    </div>
</div>

<form class="pulse-card mb-4" method="GET" action="index.php">
    <input type="hidden" name="route" value="targets/index">
    <div class="row g-3 align-items-end">
        <div class="col-md-3">
            <label class="form-label text-secondary">Status</label>
            <select name="status" class="form-select bg-dark border-secondary text-white">
                <option value="">All</option>
                <?php foreach ($statuses as $status): ?>
                    <option value="<?php echo $status; ?>" <?php echo $filters['status'] === $status ? 'selected' : ''; ?>><?php echo strtoupper(str_replace('_', ' ', $status)); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label text-secondary">Period</label>
            <select name="period" class="form-select bg-dark border-secondary text-white">
                <option value="">All</option>
                <?php foreach ($periods as $period): ?>
                    <option value="<?php echo $period; ?>" <?php echo $filters['period'] === $period ? 'selected' : ''; ?>><?php echo strtoupper($period); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label text-secondary">Owner Type</label>
            <select name="owner_type" class="form-select bg-dark border-secondary text-white">
                <option value="">All</option>
                <option value="employee" <?php echo $filters['owner_type'] === 'employee' ? 'selected' : ''; ?>>Employee</option>
                <option value="team" <?php echo $filters['owner_type'] === 'team' ? 'selected' : ''; ?>>Team</option>
            </select>
        </div>
        <div class="col-md-2">
            <button class="btn btn-outline-light w-100"><i class="fa-solid fa-filter me-2"></i>Filter</button>
        </div>
    </div>
</form>

<div class="pulse-card">
    <div class="table-responsive">
        <table class="table table-dark table-hover align-middle table-stack">
            <thead>
                <tr class="text-secondary">
                    <th>Owner</th>
                    <th>Period</th>
                    <th>Date Range</th>
                    <th>Items</th>
                    <th>Avg Completion</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($targets)): ?>
                    <tr><td colspan="7" class="text-center py-4 text-secondary">No targets planned yet.</td></tr>
                <?php endif; ?>
                <?php foreach ($targets as $target): ?>
                    <?php
                        $tone = [
                            'draft' => 'secondary',
                            'pending_approval' => 'warning',
                            'approved' => 'success',
                            'rejected' => 'danger',
                        ][$target->status] ?? 'secondary';
                    ?>
                    <tr>
                        <td data-label="Owner" class="text-white fw-semibold">
                            <?php echo htmlspecialchars($target->owner_type === 'team' ? ($target->team_name ?? 'Team') : ($target->owner_name ?? 'Employee')); ?>
                            <div class="text-secondary small"><?php echo strtoupper($target->owner_type); ?></div>
                        </td>
                        <td data-label="Period"><?php echo strtoupper($target->period); ?></td>
                        <td data-label="Date Range"><?php echo htmlspecialchars($target->start_date . ' to ' . $target->end_date); ?></td>
                        <td data-label="Items"><?php echo (int) $target->item_count; ?></td>
                        <td data-label="Avg Completion">
                            <div class="d-flex align-items-center gap-2">
                                <div class="progress bg-dark flex-grow-1" style="height:8px; min-width:90px;">
                                    <div class="progress-bar" style="width: <?php echo min(100, (float) $target->avg_completion); ?>%; background: var(--primary);"></div>
                                </div>
                                <span><?php echo number_format((float) $target->avg_completion, 1); ?>%</span>
                            </div>
                        </td>
                        <td data-label="Status"><span class="badge bg-<?php echo $tone; ?>-subtle text-<?php echo $tone; ?> border border-<?php echo $tone; ?>-subtle"><?php echo strtoupper(str_replace('_', ' ', $target->status)); ?></span></td>
                        <td data-label="Actions" class="text-end">
                            <div class="d-inline-flex gap-2 flex-wrap justify-content-end">
                                <a class="btn btn-outline-info btn-sm" href="index.php?route=targets/items/<?php echo $target->target_id; ?>"><i class="fa-solid fa-eye"></i></a>
                                <?php if ($can_approve): ?>
                                    <form action="index.php?route=targets/recompute/<?php echo $target->target_id; ?>" method="POST">
                                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                                        <button class="btn btn-outline-secondary btn-sm" title="Refresh"><i class="fa-solid fa-rotate"></i></button>
                                    </form>
                                <?php endif; ?>
                                <?php if ($can_approve && $target->status === 'pending_approval'): ?>
                                    <form action="index.php?route=targets/review/<?php echo $target->target_id; ?>" method="POST">
                                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                                        <input type="hidden" name="decision" value="approved">
                                        <button class="btn btn-outline-success btn-sm" title="Approve"><i class="fa-solid fa-check"></i></button>
                                    </form>
                                    <form action="index.php?route=targets/review/<?php echo $target->target_id; ?>" method="POST">
                                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                                        <input type="hidden" name="decision" value="rejected">
                                        <button class="btn btn-outline-danger btn-sm" title="Reject"><i class="fa-solid fa-xmark"></i></button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="addTargetModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content bg-dark text-white border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title">Plan Target</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="index.php?route=targets/add" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label text-secondary">Owner Type</label>
                            <select name="owner_type" id="target-owner-type" class="form-select bg-dark border-secondary text-white">
                                <option value="employee">Employee</option>
                                <option value="team">Team</option>
                            </select>
                        </div>
                        <div class="col-md-3 owner-employee">
                            <label class="form-label text-secondary">Employee</label>
                            <select name="owner_user_id" class="form-select bg-dark border-secondary text-white">
                                <?php foreach ($users as $user): ?><option value="<?php echo $user->user_id; ?>" <?php echo (int) $user->user_id === (int) $_SESSION['user_id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($user->name); ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3 owner-team" style="display:none;">
                            <label class="form-label text-secondary">Team</label>
                            <select name="team_id" class="form-select bg-dark border-secondary text-white">
                                <?php foreach ($teams as $team): ?><option value="<?php echo $team->team_id; ?>"><?php echo htmlspecialchars($team->name); ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label text-secondary">Period</label>
                            <select name="period" class="form-select bg-dark border-secondary text-white">
                                <?php foreach ($periods as $period): ?><option value="<?php echo $period; ?>"><?php echo strtoupper($period); ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label text-secondary">Start</label>
                            <input type="date" name="start_date" class="form-control bg-dark border-secondary text-white" value="<?php echo date('Y-m-01'); ?>" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label text-secondary">End</label>
                            <input type="date" name="end_date" class="form-control bg-dark border-secondary text-white" value="<?php echo date('Y-m-t'); ?>" required>
                        </div>
                    </div>

                    <div class="table-responsive mt-4">
                        <table class="table table-dark align-middle">
                            <thead><tr class="text-secondary"><th>Metric</th><th>Planned</th><th>Product</th><th>Territory</th></tr></thead>
                            <tbody>
                                <?php foreach ($categories as $category): ?>
                                    <tr>
                                        <td>
                                            <input type="hidden" name="category_id[]" value="<?php echo $category->category_id; ?>">
                                            <div class="text-white"><?php echo htmlspecialchars($category->name); ?></div>
                                            <div class="text-secondary small"><?php echo htmlspecialchars($category->unit); ?></div>
                                        </td>
                                        <td><input type="number" step="0.01" min="0" name="planned_value[]" class="form-control bg-dark border-secondary text-white" value="0"></td>
                                        <td>
                                            <select name="product_id[]" class="form-select bg-dark border-secondary text-white">
                                                <option value="">Any</option>
                                                <?php foreach ($products as $product): ?><option value="<?php echo $product->product_id; ?>"><?php echo htmlspecialchars($product->name); ?></option><?php endforeach; ?>
                                            </select>
                                        </td>
                                        <td>
                                            <select name="territory_id[]" class="form-select bg-dark border-secondary text-white">
                                                <option value="">Any</option>
                                                <?php foreach ($territories as $territory): ?><option value="<?php echo $territory->territory_id; ?>"><?php echo htmlspecialchars($territory->name); ?></option><?php endforeach; ?>
                                            </select>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-primary" style="background: var(--primary); border: none;">Save Target</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(function() {
    $('#target-owner-type').on('change', function() {
        var team = $(this).val() === 'team';
        $('.owner-team').toggle(team);
        $('.owner-employee').toggle(!team);
    });
});
</script>
