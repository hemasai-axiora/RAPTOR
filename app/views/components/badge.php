<?php
/**
 * Component: Status Badge
 * Usage: $this->component('badge', ['label' => 'Active', 'variant' => 'success', 'icon' => 'fa-check'])
 */
$variant = $variant ?? 'secondary';
$icon = $icon ?? null;
$label = $label ?? '';
$extraClass = $class ?? '';

$badgeStyles = [
    'primary'   => 'bg-primary bg-opacity-15 text-primary border-primary border-opacity-25',
    'success'   => 'bg-success bg-opacity-15 text-success border-success border-opacity-25',
    'warning'   => 'bg-warning bg-opacity-15 text-warning border-warning border-opacity-25',
    'danger'    => 'bg-danger bg-opacity-15 text-danger border-danger border-opacity-25',
    'info'      => 'bg-info bg-opacity-15 text-info border-info border-opacity-25',
    'secondary' => 'bg-secondary bg-opacity-15 text-secondary border-secondary border-opacity-25',
];

$classes = $badgeStyles[$variant] ?? $badgeStyles['secondary'];
?>
<span class="raptor-badge badge border <?php echo $classes . ' ' . $extraClass; ?> px-2.5 py-1" style="border-radius: var(--raptor-radius-full); font-size: var(--raptor-text-xs); font-weight: var(--raptor-font-medium);">
    <?php if ($icon): ?>
        <i class="fa-solid <?php echo htmlspecialchars($icon); ?> me-1"></i>
    <?php endif; ?>
    <?php echo htmlspecialchars($label); ?>
</span>
