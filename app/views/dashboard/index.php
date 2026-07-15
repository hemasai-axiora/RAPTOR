<div class="row g-4">
    <div class="col-12">
        <div class="pulse-card card-glow" style="background: linear-gradient(135deg, rgba(31, 95, 174, 0.08) 0%, rgba(255,255,255,0) 100%);">
            <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-4">
                <div class="d-flex align-items-center gap-3">
                    <img src="<?php echo URLROOT; ?>/logo.png" alt="Raptor Logo" style="height: 60px; width: auto; object-fit: contain;">
                    <div>
                        <h4 class="text-white mb-1">Welcome to RAPTOR</h4>
                        <div class="text-secondary" style="font-size: 0.9rem;">Your consolidated digital marketing command center. Customize widgets, inspect channels, and generate digests.</div>
                    </div>
                </div>
                <a href="index.php?route=reports/index" class="btn btn-primary align-self-start align-self-md-center" style="background: var(--primary); border: none; border-radius: 8px; padding: 0.55rem 1.25rem;">
                    <i class="fa-solid fa-chart-pie me-2"></i>Reports Center
                </a>
            </div>
        </div>
    </div>

    <?php foreach ($dashboards as $key => $dashboard): ?>
        <div class="col-md-6 col-xl-4">
            <a href="index.php?route=dashboard/show/<?php echo urlencode($key); ?>" class="text-decoration-none">
                <div class="pulse-card h-100">
                    <div class="d-flex align-items-start justify-content-between gap-3">
                        <div>
                            <h5 class="text-white mb-2"><?php echo htmlspecialchars($dashboard['label']); ?></h5>
                            <p class="text-secondary mb-3"><?php echo htmlspecialchars($dashboard['description']); ?></p>
                            <div class="d-flex flex-wrap gap-2">
                                <?php foreach (array_slice($dashboard['widgets'], 0, 4) as $widget): ?>
                                    <span class="badge bg-primary bg-opacity-10 text-white border border-primary"><?php echo htmlspecialchars(str_replace('_', ' ', $widget)); ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <i class="fa-solid fa-arrow-up-right-from-square text-primary"></i>
                    </div>
                </div>
            </a>
        </div>
    <?php endforeach; ?>
</div>
