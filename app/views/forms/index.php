<div class="pulse-card mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h4 class="text-white mb-1"><i class="fa-solid fa-file-waveform me-2 text-primary"></i>Lead Capture Forms</h4>
            <div class="text-secondary" style="font-size: 0.9rem;">Create, embed, and analyze high-converting lead capture forms across your websites.</div>
        </div>
        <div class="d-flex gap-2">
            <a href="index.php?route=forms/webhooks" class="btn btn-outline-info btn-sm px-3 py-2">
                <i class="fa-solid fa-network-wired me-2"></i>Inbound Webhooks
            </a>
            <a href="index.php?route=forms/add" class="btn btn-primary btn-sm px-3 py-2">
                <i class="fa-solid fa-plus me-2"></i>Create New Form
            </a>
        </div>
    </div>

    <!-- Forms Table -->
    <div class="table-responsive">
        <table class="table table-dark table-hover align-middle mb-0" style="border-radius: 8px; overflow: hidden;">
            <thead class="table-dark" style="border-bottom: 2px solid #333;">
                <tr class="text-secondary small">
                    <th>FORM NAME</th>
                    <th>TYPE</th>
                    <th>VIEWS</th>
                    <th>SUBMISSIONS</th>
                    <th>CONVERSION RATE</th>
                    <th>ASSIGNED REP</th>
                    <th>STATUS</th>
                    <th class="text-end">ACTION</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($forms)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-4 text-muted">
                            <i class="fa-solid fa-file-circle-question fa-2x mb-2 d-block"></i>
                            No lead capture forms created yet. Click "Create New Form" to get started.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($forms as $f): ?>
                        <tr>
                            <td>
                                <div class="fw-bold text-white">
                                    <a href="index.php?route=forms/view/<?= $f->form_id; ?>" class="text-decoration-none text-info">
                                        <?= htmlspecialchars($f->name); ?>
                                    </a>
                                </div>
                                <div class="small text-secondary">
                                    Source: <code><?= htmlspecialchars($f->default_lead_source); ?></code>
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-secondary text-uppercase small"><?= htmlspecialchars($f->form_type); ?></span>
                            </td>
                            <td>
                                <span class="text-white font-monospace"><?= number_format((int)$f->views_count); ?></span>
                            </td>
                            <td>
                                <span class="text-white font-monospace fw-bold"><?= number_format((int)$f->submissions_count); ?></span>
                            </td>
                            <td>
                                <?php
                                $cr = (float)($f->conversion_rate ?? 0);
                                $crColor = ($cr >= 15) ? 'success' : (($cr >= 5) ? 'info' : 'warning');
                                ?>
                                <span class="badge bg-<?= $crColor; ?>-subtle text-<?= $crColor; ?> border border-<?= $crColor; ?> px-2 py-1">
                                    <i class="fa-solid fa-chart-line me-1"></i><?= $cr; ?>%
                                </span>
                            </td>
                            <td class="small text-secondary">
                                <?= htmlspecialchars($f->assigned_user_name ?: 'Round Robin / Unassigned'); ?>
                            </td>
                            <td>
                                <?php if ((int)$f->is_active === 1): ?>
                                    <span class="badge bg-success-subtle text-success border border-success">Active</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Disabled</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <a href="index.php?route=forms/view/<?= $f->form_id; ?>" class="btn btn-outline-info btn-sm px-2 py-1" title="View Embed Codes & Analytics">
                                    <i class="fa-solid fa-code me-1"></i>Embed & Stats
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
