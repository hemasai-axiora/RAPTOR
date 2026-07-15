<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h4 class="text-white mb-1">Target Detail</h4>
        <div class="text-secondary" style="font-size:0.9rem;">
            <?php echo htmlspecialchars(strtoupper($target->period) . ' - ' . $target->start_date . ' to ' . $target->end_date); ?>
        </div>
    </div>
    <div class="d-flex gap-2">
        <form action="index.php?route=targets/recompute/<?php echo $target->target_id; ?>" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
            <button class="btn btn-outline-info btn-sm"><i class="fa-solid fa-rotate me-2"></i>Refresh</button>
        </form>
        <a href="index.php?route=targets/index" class="btn btn-outline-light btn-sm">Back</a>
    </div>
</div>

<div class="pulse-card">
    <div class="table-responsive">
        <table class="table table-dark table-hover align-middle table-stack">
            <thead>
                <tr class="text-secondary">
                    <th>Metric</th>
                    <th>Scope</th>
                    <th class="text-end">Planned</th>
                    <th class="text-end">Achieved</th>
                    <th>Completion</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($items)): ?>
                    <tr><td colspan="5" class="text-center py-4 text-secondary">No line items for this target.</td></tr>
                <?php endif; ?>
                <?php foreach ($items as $item): ?>
                    <tr>
                        <td data-label="Metric">
                            <div class="text-white fw-semibold"><?php echo htmlspecialchars($item->category_name); ?></div>
                            <div class="text-secondary small"><?php echo htmlspecialchars($item->unit); ?></div>
                        </td>
                        <td data-label="Scope">
                            <div><?php echo htmlspecialchars($item->product_name ?: 'Any product'); ?></div>
                            <div class="text-secondary small"><?php echo htmlspecialchars($item->territory_name ?: 'Any territory'); ?></div>
                        </td>
                        <td data-label="Planned" class="text-end"><?php echo number_format((float) $item->planned_value, 2); ?></td>
                        <td data-label="Achieved" class="text-end text-success fw-semibold"><?php echo number_format((float) $item->achieved_value, 2); ?></td>
                        <td data-label="Completion">
                            <div class="d-flex align-items-center gap-2">
                                <div class="progress bg-dark flex-grow-1" style="height:8px; min-width:120px;">
                                    <div class="progress-bar" style="width: <?php echo min(100, (float) $item->completion_percent); ?>%; background: var(--primary);"></div>
                                </div>
                                <span><?php echo number_format((float) $item->completion_percent, 1); ?>%</span>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
