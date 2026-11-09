<?php
// Raptor CRM - Deliverability Alerts Center View
?>
<div class="container-fluid px-0">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold mb-1"><i class="fa-solid fa-triangle-exclamation text-warning me-2"></i>Deliverability Alerts Center</h3>
            <p class="text-secondary small mb-0">Real-time alert lifecycle for deliverability issues, DNS authentication faults, intent conflicts, and quota warnings.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="index.php?route=email_campaigns/domainsHealth" class="btn btn-outline-info btn-sm">
                <i class="fa-solid fa-heart-pulse me-1"></i>Health Dashboard
            </a>
            <a href="index.php?route=email_campaigns/campaigns" class="btn btn-outline-secondary btn-sm">
                <i class="fa-solid fa-paper-plane me-1"></i>Campaigns
            </a>
        </div>
    </div>

    <!-- Mandatory Deliverability Non-Negotiable Notice -->
    <div class="alert alert-dark border-0 shadow-sm d-flex align-items-center gap-3 mb-4">
        <i class="fa-solid fa-circle-info fs-4 text-info"></i>
        <div class="small">
            <strong>Deliverability Guidance Notice:</strong> Observed deliverability signals and test inbox results are sample-specific diagnostics.
            Final mailbox tab classification (Primary, Promotions, Updates) and spam filtering are controlled solely by recipient mailbox providers (Google, Yahoo, Microsoft) and cannot be guaranteed.
        </div>
    </div>

    <!-- Severity Counter Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <a href="index.php?route=email_campaigns/alerts&severity=BLOCK" class="card border-0 shadow-sm text-decoration-none border-start border-4 border-danger">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted small fw-semibold">CRITICAL BLOCKERS</div>
                            <h3 class="fw-bold text-danger mb-0"><?php echo number_format($counts['block'] ?? 0); ?></h3>
                        </div>
                        <div class="bg-danger-subtle p-3 rounded-circle text-danger">
                            <i class="fa-solid fa-ban fs-4"></i>
                        </div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-md-3">
            <a href="index.php?route=email_campaigns/alerts&severity=HIGH" class="card border-0 shadow-sm text-decoration-none border-start border-4 border-warning">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted small fw-semibold">HIGH RISKS</div>
                            <h3 class="fw-bold text-warning mb-0"><?php echo number_format($counts['high'] ?? 0); ?></h3>
                        </div>
                        <div class="bg-warning-subtle p-3 rounded-circle text-warning">
                            <i class="fa-solid fa-triangle-exclamation fs-4"></i>
                        </div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-md-3">
            <a href="index.php?route=email_campaigns/alerts&severity=WARNING" class="card border-0 shadow-sm text-decoration-none border-start border-4 border-primary">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted small fw-semibold">WARNINGS</div>
                            <h3 class="fw-bold text-primary mb-0"><?php echo number_format($counts['warning'] ?? 0); ?></h3>
                        </div>
                        <div class="bg-primary-subtle p-3 rounded-circle text-primary">
                            <i class="fa-solid fa-circle-exclamation fs-4"></i>
                        </div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-md-3">
            <a href="index.php?route=email_campaigns/alerts" class="card border-0 shadow-sm text-decoration-none border-start border-4 border-secondary">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted small fw-semibold">TOTAL ACTIVE</div>
                            <h3 class="fw-bold text-dark mb-0"><?php echo number_format($counts['total'] ?? 0); ?></h3>
                        </div>
                        <div class="bg-light p-3 rounded-circle text-secondary">
                            <i class="fa-solid fa-list-check fs-4"></i>
                        </div>
                    </div>
                </div>
            </a>
        </div>
    </div>

    <!-- Active Alerts Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h6 class="fw-bold mb-0"><i class="fa-solid fa-bell me-2"></i>Active Alerts Ledger</h6>
            <?php if (!empty($current_severity)): ?>
                <span class="badge bg-secondary">Filtered: <?php echo htmlspecialchars($current_severity); ?> <a href="index.php?route=email_campaigns/alerts" class="text-white ms-1 text-decoration-none">&times;</a></span>
            <?php endif; ?>
        </div>
        <div class="card-body p-0">
            <?php if (empty($alerts)): ?>
                <div class="p-5 text-center text-muted">
                    <i class="fa-solid fa-circle-check fs-1 text-success mb-3"></i>
                    <h5>No active deliverability alerts</h5>
                    <p class="small mb-0">All campaign preflight and domain authentication checks are passing with zero unresolved issues.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light small text-muted">
                            <tr>
                                <th>Severity</th>
                                <th>Rule</th>
                                <th>Target</th>
                                <th>State</th>
                                <th>Occurrences</th>
                                <th>Last Detected</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($alerts as $a): ?>
                                <tr>
                                    <td>
                                        <?php if ($a->severity === 'BLOCK'): ?>
                                            <span class="badge bg-danger">BLOCK</span>
                                        <?php elseif ($a->severity === 'HIGH'): ?>
                                            <span class="badge bg-warning text-dark">HIGH</span>
                                        <?php elseif ($a->severity === 'WARNING'): ?>
                                            <span class="badge bg-primary">WARNING</span>
                                        <?php else: ?>
                                            <span class="badge bg-info text-dark">INFO</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark font-monospace"><?php echo htmlspecialchars($a->rule_id); ?></span>
                                    </td>
                                    <td>
                                        <div class="small fw-semibold text-dark"><?php echo ucfirst(htmlspecialchars($a->object_type)); ?> #<?php echo $a->object_id; ?></div>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary-subtle text-secondary"><?php echo htmlspecialchars($a->state); ?></span>
                                    </td>
                                    <td>
                                        <span class="small text-muted"><?php echo (int)$a->affected_count; ?>x</span>
                                    </td>
                                    <td>
                                        <span class="small text-muted"><?php echo htmlspecialchars($a->last_seen_at); ?></span>
                                    </td>
                                    <td class="text-end">
                                        <form method="POST" action="index.php?route=email_campaigns/alertAction" class="d-inline">
                                            <input type="hidden" name="alert_id" value="<?php echo $a->id; ?>">
                                            <?php if ($a->state === 'OPEN' || $a->state === 'REOPENED'): ?>
                                                <input type="hidden" name="action" value="acknowledge">
                                                <button type="submit" class="btn btn-xs btn-outline-warning py-1 px-2" title="Acknowledge">
                                                    <i class="fa-solid fa-eye me-1"></i>Ack
                                                </button>
                                            <?php else: ?>
                                                <input type="hidden" name="action" value="resolve">
                                                <button type="submit" class="btn btn-xs btn-outline-success py-1 px-2" title="Mark Resolved">
                                                    <i class="fa-solid fa-check me-1"></i>Resolve
                                                </button>
                                            <?php endif; ?>
                                        </form>
                                        <?php if ($a->object_type === 'campaign' && $a->object_id > 0): ?>
                                            <a href="index.php?route=email_campaigns/campaignEdit&id=<?php echo $a->object_id; ?>" class="btn btn-xs btn-outline-primary py-1 px-2 ms-1">
                                                <i class="fa-solid fa-wrench me-1"></i>Fix Now
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

