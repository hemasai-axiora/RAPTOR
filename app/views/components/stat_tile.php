<?php
/**
 * Component: Stat / Metric Tile
 * Usage: $this->component('stat_tile', ['label' => 'Total Leads', 'value' => '1,420', 'icon' => 'fa-users', 'delta' => '+12.4%'])
 */
$label = $label ?? $title ?? '';
$value = $value ?? '0';
$icon  = $icon ?? 'fa-chart-line';
$delta = $delta ?? null;
$deltaUp = $deltaUp ?? true;
$color = $color ?? 'primary';
?>
<div class="raptor-stat-tile raptor-glass-card p-3 h-100">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <span class="text-secondary small fw-medium text-uppercase" style="font-size: var(--raptor-text-xs); letter-spacing: 0.5px;"><?php echo htmlspecialchars($label); ?></span>
        <div class="p-2 rounded-2 bg-<?php echo $color; ?> bg-opacity-15 text-<?php echo $color; ?>" style="width: 34px; height: 34px; display: flex; align-items: center; justify-content: center;">
            <i class="fa-solid <?php echo htmlspecialchars($icon); ?>"></i>
        </div>
    </div>
    <div class="d-flex align-items-baseline gap-2">
        <h3 class="text-white mb-0 fw-bold" style="font-size: var(--raptor-text-2xl);"><?php echo htmlspecialchars($value); ?></h3>
        <?php if ($delta): ?>
            <span class="badge bg-<?php echo $deltaUp ? 'success' : 'danger'; ?> bg-opacity-15 text-<?php echo $deltaUp ? 'success' : 'danger'; ?> border border-<?php echo $deltaUp ? 'success' : 'danger'; ?> border-opacity-25" style="font-size: 0.7rem;">
                <i class="fa-solid fa-arrow-<?php echo $deltaUp ? 'up' : 'down'; ?> me-0.5"></i><?php echo htmlspecialchars($delta); ?>
            </span>
        <?php endif; ?>
    </div>
</div>
