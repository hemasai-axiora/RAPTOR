<?php
/**
 * Component: Empty State
 * Usage: $this->component('empty_state', ['title' => 'No Leads Found', 'message' => 'Create a lead to start tracking.', 'action_label' => 'Create Lead', 'action_url' => '...'])
 */
$icon = $icon ?? 'fa-folder-open';
$title = $title ?? 'No records found';
$message = $message ?? $description ?? 'Get started by creating your first entry.';
$action_label = $action_label ?? null;
$action_url = $action_url ?? null;
?>
<div class="raptor-empty-state raptor-solid-card p-5 text-center my-3">
    <div class="mb-3 text-secondary opacity-50">
        <i class="fa-solid <?php echo htmlspecialchars($icon); ?> fa-3x"></i>
    </div>
    <h5 class="text-white mb-2 fw-semibold" style="font-size: var(--raptor-text-lg);"><?php echo htmlspecialchars($title); ?></h5>
    <p class="text-secondary small mb-3 mx-auto" style="max-width: 420px; font-size: var(--raptor-text-sm);"><?php echo htmlspecialchars($message); ?></p>
    <?php if ($action_label && $action_url): ?>
        <a href="<?php echo htmlspecialchars($action_url); ?>" class="btn btn-sm btn-primary px-3">
            <i class="fa-solid fa-plus me-1"></i><?php echo htmlspecialchars($action_label); ?>
        </a>
    <?php endif; ?>
</div>
