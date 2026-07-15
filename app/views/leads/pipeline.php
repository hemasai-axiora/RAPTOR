<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <h4 class="text-white mb-0">Lead Pipeline</h4>
    <div class="d-flex gap-2">
        <a href="index.php?route=leads/index" class="btn btn-outline-light btn-sm"><i class="fa-solid fa-table-list me-2"></i>List</a>
        <a href="index.php?route=leads/add" class="btn btn-primary btn-sm" style="background: var(--primary); border: none;"><i class="fa-solid fa-user-plus me-2"></i>Capture Lead</a>
    </div>
</div>

<style>
    .pipeline-board {
        display: grid;
        grid-template-columns: repeat(6, minmax(230px, 1fr));
        gap: 1rem;
        overflow-x: auto;
        padding-bottom: 0.5rem;
    }
    .pipeline-column {
        min-height: 520px;
        background: rgba(0,0,0,0.18);
        border: 1px solid var(--border-color);
        border-radius: 12px;
        padding: 1rem;
    }
    @media (max-width: 991.98px) {
        .pipeline-board { display: block; overflow: visible; }
        .pipeline-column { min-height: auto; margin-bottom: 1rem; }
    }
</style>

<div class="pipeline-board">
    <?php foreach ($statuses as $status): ?>
        <?php $items = $pipeline[$status] ?? []; ?>
        <section class="pipeline-column">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="text-white mb-0" style="font-size: 0.95rem;"><?php echo strtoupper($status); ?></h5>
                <span class="badge bg-secondary"><?php echo count($items); ?></span>
            </div>

            <div class="d-flex flex-column gap-3">
                <?php if (empty($items)): ?>
                    <div class="text-secondary small">No leads in this stage.</div>
                <?php endif; ?>

                <?php foreach ($items as $lead): ?>
                    <?php
                        $priorityTone = [
                            'urgent' => 'danger', 'high' => 'warning', 'medium' => 'info', 'low' => 'secondary',
                        ][$lead->priority] ?? 'secondary';
                    ?>
                    <div class="pulse-card p-3" style="border-radius: 10px;">
                        <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                            <a href="index.php?route=leads/view/<?php echo $lead->lead_id; ?>" class="text-white text-decoration-none fw-semibold">
                                <?php echo htmlspecialchars($lead->first_name . ' ' . ($lead->last_name ?? '')); ?>
                            </a>
                            <span class="badge bg-<?php echo $priorityTone; ?>-subtle text-<?php echo $priorityTone; ?>"><?php echo strtoupper($lead->priority); ?></span>
                        </div>
                        <div class="text-secondary small mb-2"><?php echo htmlspecialchars($lead->lead_company_name ?: $lead->client_company_name ?: 'Individual'); ?></div>
                        <div class="d-flex justify-content-between text-secondary small">
                            <span><?php echo htmlspecialchars($lead->assignee_name ?? 'Unassigned'); ?></span>
                            <span><?php echo number_format((float) ($lead->probability ?? $lead->conversion_probability), 0); ?>%</span>
                        </div>
                        <div class="text-success fw-semibold mt-2">$<?php echo number_format((float) $lead->lead_value, 2); ?></div>
                        <div class="d-flex flex-wrap gap-1 mt-3">
                            <?php foreach ($statuses as $target): ?>
                                <?php if ($target === $status) { continue; } ?>
                                <form action="index.php?route=leads/move/<?php echo $lead->lead_id; ?>" method="POST">
                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                                    <input type="hidden" name="status" value="<?php echo $target; ?>">
                                    <input type="hidden" name="return" value="leads/pipeline">
                                    <button class="btn btn-outline-light btn-sm" style="font-size:0.7rem;" title="Move to <?php echo htmlspecialchars($target); ?>">
                                        <?php echo strtoupper(substr($target, 0, 3)); ?>
                                    </button>
                                </form>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endforeach; ?>
</div>
