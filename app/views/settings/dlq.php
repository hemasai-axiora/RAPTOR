<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1 text-primary fw-bold">
                <i class="fa-solid fa-triangle-exclamation me-2"></i>Dead-Letter Queue & Failed Deliveries
            </h4>
            <p class="text-secondary small mb-0">Inspect isolated failure events, review provider error payloads, and replay jobs.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="index.php?route=settings/index" class="btn btn-sm btn-outline-secondary">
                <i class="fa-solid fa-arrow-left me-1"></i> Back to Settings
            </a>
            <a href="index.php?route=settings/dlq" class="btn btn-sm btn-primary">
                <i class="fa-solid fa-arrows-rotate me-1"></i> Refresh Queue
            </a>
        </div>
    </div>

    <?php if (!empty($_SESSION['settings_success'])): ?>
        <div class="alert alert-success d-flex align-items-center" role="alert">
            <i class="fa-solid fa-circle-check me-2"></i>
            <div><?php echo htmlspecialchars($_SESSION['settings_success']); unset($_SESSION['settings_success']); ?></div>
        </div>
    <?php endif; ?>

    <?php if (!empty($_SESSION['settings_error'])): ?>
        <div class="alert alert-danger d-flex align-items-center" role="alert">
            <i class="fa-solid fa-circle-exclamation me-2"></i>
            <div><?php echo htmlspecialchars($_SESSION['settings_error']); unset($_SESSION['settings_error']); ?></div>
        </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm" style="background: var(--panel-dark); border-radius: 12px; border: 1px solid var(--border-color) !important;">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="dlqTable" style="color: var(--text-primary);">
                    <thead style="background: var(--surface-soft); font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px;">
                        <tr>
                            <th class="ps-4">Job ID</th>
                            <th>Campaign</th>
                            <th>Contact ID</th>
                            <th>Attempts</th>
                            <th>Last Error Reason</th>
                            <th>Failed At</th>
                            <th class="text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody style="font-size: 0.85rem;">
                        <?php if (empty($failed_jobs)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-5 text-secondary">
                                    <i class="fa-solid fa-circle-check text-success mb-3" style="font-size: 2.5rem; opacity: 0.8;"></i>
                                    <div class="fw-semibold">Dead-Letter Queue is Clean</div>
                                    <div class="small text-muted mt-1">Zero failed messages or broken webhook jobs in quarantine.</div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($failed_jobs as $job): ?>
                                <tr>
                                    <td class="ps-4 fw-bold text-primary">#<?php echo (int)$job->queue_id; ?></td>
                                    <td>
                                        <div class="fw-semibold"><?php echo htmlspecialchars($job->campaign_name); ?></div>
                                        <small class="text-secondary">Camp ID: <?php echo (int)$job->campaign_id; ?></small>
                                    </td>
                                    <td><?php echo (int)$job->contact_id; ?></td>
                                    <td>
                                        <span class="badge bg-danger-subtle text-danger fw-bold">
                                            <?php echo (int)$job->attempts; ?> retries
                                        </span>
                                    </td>
                                    <td style="max-width: 320px;">
                                        <span class="text-danger small font-monospace d-block text-truncate" title="<?php echo htmlspecialchars($job->last_error ?: 'Unknown'); ?>">
                                            <?php echo htmlspecialchars($job->last_error ?: 'Unknown error'); ?>
                                        </span>
                                    </td>
                                    <td class="text-secondary small">
                                        <?php echo htmlspecialchars($job->updated_at ?: $job->created_at); ?>
                                    </td>
                                    <td class="text-end pe-4">
                                        <a href="index.php?route=settings/retryDlq/<?php echo (int)$job->queue_id; ?>" 
                                           class="btn btn-sm btn-outline-primary"
                                           onclick="return confirm('Reset this job to pending for immediate reprocessing?');">
                                            <i class="fa-solid fa-play me-1"></i> Replay
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

