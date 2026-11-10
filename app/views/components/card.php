<?php
/**
 * Component: Card
 * Usage: $this->component('card', ['title' => '...', 'body' => '...', 'glass' => true, 'actions' => '...'])
 */
$glass = $glass ?? false;
$cardClass = ($glass ? 'raptor-glass-card' : 'raptor-solid-card') . ' raptor-card';
$extraClass = $class ?? '';
$title = $title ?? null;
$actions = $actions ?? null;
$footer = $footer ?? null;
?>
<div class="<?php echo $cardClass . ' ' . $extraClass; ?>">
    <?php if ($title || $actions): ?>
        <div class="d-flex justify-content-between align-items-center p-3 border-bottom border-secondary border-opacity-15">
            <?php if ($title): ?>
                <h5 class="text-white mb-0 fw-semibold" style="font-size: var(--raptor-text-lg);"><?php echo $title; ?></h5>
            <?php endif; ?>
            <?php if ($actions): ?>
                <div class="d-flex gap-2 align-items-center"><?php echo $actions; ?></div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    <div class="p-3">
        <?php echo $content ?? $body ?? ''; ?>
    </div>
    <?php if ($footer): ?>
        <div class="p-3 border-top border-secondary border-opacity-15 bg-black bg-opacity-20 rounded-bottom">
            <?php echo $footer; ?>
        </div>
    <?php endif; ?>
</div>
