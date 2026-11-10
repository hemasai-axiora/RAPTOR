<?php
/**
 * Component: Slide-Over Drawer
 * Usage: $this->component('drawer', [
 *     'id' => 'leadPreviewDrawer',
 *     'title' => 'Lead Overview',
 *     'subtitle' => 'Quick inspect panel',
 *     'body' => '...',
 *     'footer' => '...'
 * ]);
 */
$id = $id ?? 'raptor-drawer-' . uniqid();
$title = $title ?? '';
$subtitle = $subtitle ?? '';
$body = $body ?? $content ?? '';
$footer = $footer ?? null;
$size = $size ?? '520px';
?>
<div class="raptor-drawer-backdrop" id="<?php echo htmlspecialchars($id); ?>-backdrop"></div>
<div class="raptor-drawer" id="<?php echo htmlspecialchars($id); ?>" style="max-width: <?php echo htmlspecialchars($size); ?>;" tabindex="-1" role="dialog" aria-modal="true" aria-hidden="true">
    <div class="raptor-drawer-header">
        <div>
            <h5 class="text-white mb-0 fw-semibold" style="font-size: var(--raptor-text-lg);" id="<?php echo htmlspecialchars($id); ?>-title">
                <?php echo htmlspecialchars($title); ?>
            </h5>
            <?php if (!empty($subtitle)): ?>
                <div class="text-secondary small mt-0.5" id="<?php echo htmlspecialchars($id); ?>-subtitle">
                    <?php echo htmlspecialchars($subtitle); ?>
                </div>
            <?php endif; ?>
        </div>
        <button type="button" class="btn btn-sm btn-outline-secondary border-0 text-secondary hover-white raptor-drawer-close" data-drawer-dismiss="<?php echo htmlspecialchars($id); ?>" aria-label="Close">
            <i class="fa-solid fa-xmark fs-5"></i>
        </button>
    </div>
    <div class="raptor-drawer-body" id="<?php echo htmlspecialchars($id); ?>-body">
        <?php echo $body; ?>
    </div>
    <?php if ($footer): ?>
        <div class="raptor-drawer-footer" id="<?php echo htmlspecialchars($id); ?>-footer">
            <?php echo $footer; ?>
        </div>
    <?php endif; ?>
</div>

