<div class="pulse-card mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h4 class="text-white mb-1">Dashboard Templates</h4>
            <p class="text-secondary mb-0">Analysts can publish reusable dashboard layouts for their role or keep templates private.</p>
        </div>
        <a href="index.php?route=dashboard/index" class="btn btn-outline-secondary btn-sm">Back to Dashboards</a>
    </div>

    <form action="index.php?route=dashboard/createTemplate" method="POST" class="row g-3 p-3 rounded-3 mb-4" style="background: var(--surface-soft); border: 1px solid var(--border-color);">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
        <div class="col-md-4">
            <label class="form-label text-secondary">Template Name</label>
            <input type="text" name="name" class="form-control" required>
        </div>
        <div class="col-md-4">
            <label class="form-label text-secondary">Base Dashboard</label>
            <select name="base_dashboard_key" class="form-select" id="base-dashboard-select" required>
                <?php foreach ($dashboards as $key => $dashboard): ?>
                    <option value="<?php echo htmlspecialchars($key); ?>"><?php echo htmlspecialchars($dashboard['label']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label text-secondary">Visibility</label>
            <select name="visibility" class="form-select">
                <option value="role">My Role</option>
                <option value="private">Private</option>
                <?php if ($_SESSION['user_role'] === 'admin'): ?><option value="global">Global</option><?php endif; ?>
            </select>
        </div>
        <div class="col-12">
            <label class="form-label text-secondary">Description</label>
            <input type="text" name="description" class="form-control" placeholder="What this dashboard is optimized for">
        </div>
        <div class="col-12">
            <label class="form-label text-secondary">Widgets</label>
            <?php foreach ($dashboards as $key => $dashboard): ?>
                <div class="dashboard-widget-group" data-dashboard="<?php echo htmlspecialchars($key); ?>" style="<?php echo $key === array_key_first($dashboards) ? '' : 'display:none;'; ?>">
                    <div class="d-flex flex-wrap gap-2">
                        <?php foreach ($dashboard['widgets'] as $widget): ?>
                            <label class="px-3 py-2 rounded-3" style="background: var(--panel-dark); border: 1px solid var(--border-color);">
                                <input type="checkbox" name="widgets[]" value="<?php echo htmlspecialchars($widget); ?>" checked>
                                <?php echo htmlspecialchars($widget_meta[$widget]['label'] ?? ucwords(str_replace('_', ' ', $widget))); ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <input type="hidden" name="allowed_roles[]" value="<?php echo htmlspecialchars($_SESSION['user_role']); ?>">
        <div class="col-12 text-end">
            <button type="submit" class="btn btn-primary">Create Template</button>
        </div>
    </form>

    <div class="row g-3">
        <?php foreach ($templates as $template): ?>
            <?php $widgets = json_decode($template->widgets ?: '[]', true) ?: []; ?>
            <div class="col-md-6 col-xl-4">
                <div class="p-3 rounded-3 h-100" style="background: var(--surface-soft); border: 1px solid var(--border-color);">
                    <div class="d-flex justify-content-between gap-3">
                        <h5 class="text-white mb-1"><?php echo htmlspecialchars($template->name); ?></h5>
                        <span class="badge bg-primary bg-opacity-10 text-white border border-primary"><?php echo htmlspecialchars($template->visibility); ?></span>
                    </div>
                    <p class="text-secondary small mb-3"><?php echo htmlspecialchars($template->description ?: 'No description provided.'); ?></p>
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <?php foreach ($widgets as $widget): ?>
                            <span class="badge bg-secondary bg-opacity-10 text-white border border-secondary"><?php echo htmlspecialchars($widget_meta[$widget]['label'] ?? $widget); ?></span>
                        <?php endforeach; ?>
                    </div>
                    <div class="text-secondary small">
                        Base: <?php echo htmlspecialchars($template->base_dashboard_key); ?> · By <?php echo htmlspecialchars($template->created_by_name); ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        <?php if (empty($templates)): ?>
            <div class="col-12 text-center text-secondary py-4">No dashboard templates yet.</div>
        <?php endif; ?>
    </div>
</div>

<script>
$(function () {
    $('#base-dashboard-select').on('change', function () {
        $('.dashboard-widget-group').hide().find('input[type="checkbox"]').prop('disabled', true);
        $('.dashboard-widget-group[data-dashboard="' + this.value + '"]').show().find('input[type="checkbox"]').prop('disabled', false);
    }).trigger('change');
});
</script>
