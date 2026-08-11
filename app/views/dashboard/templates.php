<div class="pulse-card mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h4 class="text-white mb-1">Dashboard Templates & Custom Builder</h4>
            <p class="text-secondary mb-0">Design, customize, and share Power BI-style drag-and-drop dashboards and templates.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="index.php?route=dashboard/index" class="btn btn-outline-secondary btn-sm">
                <i class="fa-solid fa-arrow-left me-1"></i> Back to Dashboards
            </a>
            <a href="index.php?route=dashboard/builder" class="btn btn-primary btn-sm" style="background: var(--primary); border: none;">
                <i class="fa-solid fa-plus me-1"></i> + New Dashboard
            </a>
        </div>
    </div>

<?php if (!empty($_SESSION['template_error'])): ?>
    <div class="alert alert-danger mb-3 border-0 shadow-sm" style="border-radius: 8px;">
        <i class="fa-solid fa-circle-exclamation me-2"></i><?php echo htmlspecialchars($_SESSION['template_error']); unset($_SESSION['template_error']); ?>
    </div>
<?php endif; ?>
<?php if (!empty($_SESSION['template_success'])): ?>
    <div class="alert alert-success mb-3 border-0 shadow-sm" style="border-radius: 8px;">
        <i class="fa-solid fa-check-circle me-2"></i><?php echo htmlspecialchars($_SESSION['template_success']); unset($_SESSION['template_success']); ?>
    </div>
<?php endif; ?>

    <!-- Filter / Search Bar -->
    <div class="row g-2 mb-4 align-items-center">
        <div class="col-md-6 col-lg-4">
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-dark text-secondary border-secondary"><i class="fa-solid fa-magnifying-glass"></i></span>
                <input type="text" id="search-dashboards" class="form-control bg-dark border-secondary text-white" placeholder="Search dashboards by name or owner...">
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <select id="filter-visibility" class="form-select form-select-sm bg-dark border-secondary text-white">
                <option value="">All Visibilities</option>
                <option value="private">Private</option>
                <option value="role">Role-based</option>
                <option value="everyone">Everyone</option>
            </select>
        </div>
    </div>

    <!-- Custom Dashboards Grid -->
    <div class="row g-3" id="dashboards-grid">
        <?php if (empty($custom_dashboards)): ?>
            <div class="col-12 text-center py-5">
                <div class="mb-3 text-secondary" style="font-size: 3rem;"><i class="fa-solid fa-sliders"></i></div>
                <h5 class="text-white">No Custom Dashboards Yet</h5>
                <p class="text-secondary small mb-3">Click "+ New Dashboard" to build your first interactive dashboard layout.</p>
                <a href="index.php?route=dashboard/builder" class="btn btn-primary btn-sm px-4" style="background: var(--primary); border: none;">
                    <i class="fa-solid fa-plus me-1"></i> Build Dashboard Now
                </a>
            </div>
        <?php else: foreach ($custom_dashboards as $dash): ?>
            <div class="col-md-6 col-xl-4 dashboard-card-item" data-name="<?php echo strtolower(htmlspecialchars($dash->name)); ?>" data-owner="<?php echo strtolower(htmlspecialchars($dash->owner_name)); ?>" data-visibility="<?php echo $dash->visibility_type; ?>">
                <div class="p-3 rounded-3 h-100 d-flex flex-column justify-content-between position-relative" style="background: var(--surface-soft); border: 1px solid var(--border-color);">
                    <div>
                        <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                            <h5 class="text-white mb-0 fw-bold text-truncate" style="max-width: 70%;" title="<?php echo htmlspecialchars($dash->name); ?>">
                                <?php echo htmlspecialchars($dash->name); ?>
                            </h5>
                            <div class="d-flex gap-1 flex-wrap justify-content-end">
                                <?php if ($dash->is_default): ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle"><i class="fa-solid fa-star me-1"></i>Default</span>
                                <?php endif; ?>
                                <?php if ($dash->is_template): ?>
                                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle">Template</span>
                                <?php endif; ?>
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle text-capitalize">
                                    <?php echo htmlspecialchars($dash->visibility_type); ?>
                                </span>
                            </div>
                        </div>

                        <p class="text-secondary small mb-3 line-clamp-2" style="font-size: 0.82rem; min-height: 2.4rem;">
                            <?php echo htmlspecialchars($dash->description ?: 'No description provided.'); ?>
                        </p>

                        <div class="d-flex align-items-center gap-3 text-secondary small mb-3" style="font-size: 0.76rem;">
                            <span><i class="fa-solid fa-border-all me-1"></i><?php echo (int)$dash->widget_count; ?> Widgets</span>
                            <span><i class="fa-solid fa-user me-1"></i><?php echo htmlspecialchars($dash->owner_name); ?></span>
                            <span><i class="fa-solid fa-clock me-1"></i><?php echo date('M d, Y', strtotime($dash->updated_at)); ?></span>
                        </div>

                        <?php if (!empty($dash->roles)): ?>
                            <div class="mb-3">
                                <span class="text-secondary small d-block mb-1" style="font-size: 0.72rem;">Shared Roles:</span>
                                <div class="d-flex flex-wrap gap-1">
                                    <?php foreach ($dash->roles as $r): ?>
                                        <span class="badge bg-secondary-subtle text-secondary" style="font-size: 0.68rem;"><?php echo strtoupper($r); ?></span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Action Toolbar -->
                    <div class="pt-3 border-top border-secondary border-opacity-25 d-flex align-items-center justify-content-between gap-1">
                        <div class="d-flex gap-1">
                            <a href="index.php?route=dashboard/builder/<?php echo $dash->id; ?>" class="btn btn-sm btn-outline-primary" title="Open / Edit Builder">
                                <i class="fa-solid fa-pen-to-square me-1"></i>Open
                            </a>
                            <a href="index.php?route=dashboard/duplicateDashboard/<?php echo $dash->id; ?>" class="btn btn-sm btn-outline-secondary" title="Duplicate Dashboard">
                                <i class="fa-solid fa-copy me-1"></i>Duplicate
                            </a>
                        </div>
                        <div class="d-flex gap-1">
                            <?php if (!$dash->is_default): ?>
                                <a href="index.php?route=dashboard/setDefaultDashboard/<?php echo $dash->id; ?>" class="btn btn-sm btn-outline-warning" title="Set as My Default Dashboard">
                                    <i class="fa-solid fa-star"></i>
                                </a>
                            <?php endif; ?>
                            <?php if ($dash->is_owner || $_SESSION['user_role'] === 'admin'): ?>
                                <a href="index.php?route=dashboard/deleteDashboard/<?php echo $dash->id; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to delete this custom dashboard?');" title="Delete Dashboard">
                                    <i class="fa-solid fa-trash"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; endif; ?>
    </div>
</div>

<script>
$(function() {
    function filterCards() {
        var query = $('#search-dashboards').val().toLowerCase().trim();
        var vis = $('#filter-visibility').val();

        $('.dashboard-card-item').each(function() {
            var $item = $(this);
            var name = $item.data('name') || '';
            var owner = $item.data('owner') || '';
            var itemVis = $item.data('visibility') || '';

            var matchSearch = !query || name.indexOf(query) !== -1 || owner.indexOf(query) !== -1;
            var matchVis = !vis || itemVis === vis;

            if (matchSearch && matchVis) {
                $item.show();
            } else {
                $item.hide();
            }
        });
    }

    $('#search-dashboards').on('input', filterCards);
    $('#filter-visibility').on('change', filterCards);
});
</script>
