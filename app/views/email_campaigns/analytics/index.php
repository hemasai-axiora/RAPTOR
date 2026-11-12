<?php
// Raptor CRM - Campaign Analytics & Deliverability Health Dashboard
?>
<div class="container-fluid px-0">
    <!-- Header & Subnav -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <h3 class="fw-bold mb-1"><i class="fa-solid fa-chart-line text-primary me-2"></i>Campaign Analytics & Deliverability Health</h3>
            <p class="text-secondary small mb-0">Monitor open/click engagement, bounce/complaint thresholds, and inbox placement across major ESPs.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="index.php?route=email_campaigns/analyticsExport<?php echo $campaign_id ? '&campaign_id=' . $campaign_id : ''; ?>" class="btn btn-outline-primary btn-sm px-3" download>
                <i class="fa-solid fa-file-csv me-1"></i>Export to CSV
            </a>
            <button type="button" class="btn btn-outline-warning btn-sm" data-bs-toggle="modal" data-bs-target="#reengageModal">
                <i class="fa-solid fa-user-clock me-1"></i>Re-engagement Cleanup
            </button>
        </div>
    </div>

    <!-- Sub-navigation Tabs -->
    <ul class="nav nav-pills mb-4 gap-2 border-bottom pb-3">
        <li class="nav-item">
            <a class="nav-link fw-medium" href="index.php?route=email_campaigns/campaigns">
                <i class="fa-solid fa-paper-plane me-1"></i>Campaigns
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link active fw-medium" href="index.php?route=email_campaigns/analytics">
                <i class="fa-solid fa-chart-pie me-1"></i>Analytics & Health
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link fw-medium" href="index.php?route=email_campaigns/templates">
                <i class="fa-solid fa-file-code me-1"></i>Templates
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link fw-medium" href="index.php?route=email_campaigns/contacts">
                <i class="fa-solid fa-users me-1"></i>Contacts
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link fw-medium" href="index.php?route=email_campaigns/lists">
                <i class="fa-solid fa-list me-1"></i>Lists & Payroll Sync
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link fw-medium text-danger" href="index.php?route=email_campaigns/suppressions">
                <i class="fa-solid fa-ban me-1"></i>Global Suppression
            </a>
        </li>
    </ul>

    <!-- Flash Messages -->
    <?php if (!empty($_SESSION['flash_success'])): ?>
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm" role="alert">
            <i class="fa-solid fa-circle-check me-2"></i><?php echo $_SESSION['flash_success']; unset($_SESSION['flash_success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Filter Bar: Campaign Switcher -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-3">
            <form method="GET" action="index.php" class="row g-2 align-items-center">
                <input type="hidden" name="route" value="email_campaigns/analytics">
                <div class="col-md-9">
                    <select name="campaign_id" class="form-select" onchange="this.form.submit()">
                        <option value="">-- All Campaigns (Aggregate Delivery Health) --</option>
                        <?php foreach ($campaigns as $camp): ?>
                            <option value="<?php echo $camp->campaign_id; ?>" <?php echo ($campaign_id == $camp->campaign_id) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($camp->name); ?> [<?php echo ucfirst($camp->status); ?> - <?php echo number_format($camp->sent_count); ?> sent]
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-1">
                    <button type="submit" class="btn btn-primary w-100"><i class="fa-solid fa-filter me-1"></i>Load Analytics</button>
                    <?php if ($campaign_id): ?>
                        <a href="index.php?route=email_campaigns/analytics" class="btn btn-outline-secondary" title="Reset to Global"><i class="fa-solid fa-arrow-rotate-left"></i></a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Deliverability Safeguard Alert if close to thresholds -->
    <?php if ($metrics['rates']['bounce_rate'] > 1.5 || $metrics['rates']['complaint_rate'] > 0.08): ?>
        <div class="alert alert-danger border-0 shadow-sm mb-4">
            <div class="d-flex align-items-center gap-3">
                <i class="fa-solid fa-triangle-exclamation fs-2 text-danger"></i>
                <div>
                    <h6 class="fw-bold mb-1">Reputation Alert: Deliverability Tripwire Warning</h6>
                    <p class="small mb-0">
                        Current bounce rate (<?php echo $metrics['rates']['bounce_rate']; ?>%) or complaint rate (<?php echo $metrics['rates']['complaint_rate']; ?>%) is approaching the 2.0% bounce / 0.1% complaint limit. Review list quality and suppress unengaged subscribers.
                    </p>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- KPI Metric Cards Grid -->
    <div class="row g-3 mb-4">
        <!-- Delivery Rate -->
        <div class="col-6 col-md-4 col-xl-2">
            <div class="card border-0 shadow-sm text-center p-3 h-100">
                <span class="text-secondary small fw-bold text-uppercase">Delivery Rate</span>
                <h3 class="fw-bold text-success my-2"><?php echo $metrics['rates']['delivery_rate']; ?>%</h3>
                <span class="small text-muted"><?php echo number_format($metrics['delivered']); ?> / <?php echo number_format($metrics['sent']); ?> sent</span>
            </div>
        </div>

        <!-- Open Rate -->
        <div class="col-6 col-md-4 col-xl-2">
            <div class="card border-0 shadow-sm text-center p-3 h-100">
                <span class="text-secondary small fw-bold text-uppercase">Open Rate</span>
                <h3 class="fw-bold text-primary my-2"><?php echo $metrics['rates']['open_rate']; ?>%</h3>
                <span class="small text-muted" title="<?php echo $metrics['mpp_opens']; ?> opens via Apple Mail Privacy Protection">
                    <?php echo number_format($metrics['opened']); ?> unique opens
                    <?php if ($metrics['mpp_opens'] > 0): ?>
                        <i class="fa-brands fa-apple text-muted ms-1" title="Includes Apple MPP proxy opens"></i>
                    <?php endif; ?>
                </span>
            </div>
        </div>

        <!-- Click Rate (CTR) -->
        <div class="col-6 col-md-4 col-xl-2">
            <div class="card border-0 shadow-sm text-center p-3 h-100">
                <span class="text-secondary small fw-bold text-uppercase">Click Rate (CTR)</span>
                <h3 class="fw-bold text-info my-2"><?php echo $metrics['rates']['click_rate']; ?>%</h3>
                <span class="small text-muted"><?php echo number_format($metrics['clicked']); ?> unique clicks</span>
            </div>
        </div>

        <!-- Click-to-Open (CTOR) -->
        <div class="col-6 col-md-4 col-xl-2">
            <div class="card border-0 shadow-sm text-center p-3 h-100">
                <span class="text-secondary small fw-bold text-uppercase">Click-To-Open (CTOR)</span>
                <h3 class="fw-bold text-purple my-2" style="color: #8b5cf6;"><?php echo $metrics['rates']['ctor']; ?>%</h3>
                <span class="small text-muted">Engagement depth</span>
            </div>
        </div>

        <!-- Bounce Rate -->
        <div class="col-6 col-md-4 col-xl-2">
            <div class="card border-0 shadow-sm text-center p-3 h-100">
                <span class="text-secondary small fw-bold text-uppercase">Bounce Rate</span>
                <h3 class="fw-bold <?php echo ($metrics['rates']['bounce_rate'] > 2.0) ? 'text-danger' : 'text-dark'; ?> my-2">
                    <?php echo $metrics['rates']['bounce_rate']; ?>%
                </h3>
                <span class="small text-muted">Threshold: &lt; 2.0%</span>
            </div>
        </div>

        <!-- Spam Complaint Rate -->
        <div class="col-6 col-md-4 col-xl-2">
            <div class="card border-0 shadow-sm text-center p-3 h-100">
                <span class="text-secondary small fw-bold text-uppercase">Complaint Rate</span>
                <h3 class="fw-bold <?php echo ($metrics['rates']['complaint_rate'] > 0.1) ? 'text-danger' : 'text-dark'; ?> my-2">
                    <?php echo $metrics['rates']['complaint_rate']; ?>%
                </h3>
                <span class="small text-muted">Threshold: &lt; 0.1%</span>
            </div>
        </div>
    </div>

    <!-- Apple Mail Privacy Protection Notice -->
    <div class="p-3 bg-light rounded border mb-4 small text-secondary">
        <i class="fa-solid fa-circle-info text-primary me-2"></i>
        <strong>Note on Apple Mail Privacy Protection (MPP):</strong> Apple iOS 15+ and macOS Monterey preload email images via cloud proxy servers, which can inflate open rates. True recipient engagement is most accurately assessed via Click-Through Rate (CTR) and Click-to-Open Rate (CTOR).
    </div>

    <!-- Charts & Breakdown Section -->
    <div class="row g-4 mb-4">
        <!-- Left: Major ESP / Domain Family Performance -->
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3">
                    <h6 class="fw-bold mb-0 text-primary"><i class="fa-solid fa-server me-2"></i>Inbox Placement by Email Provider Family</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light small text-uppercase text-secondary">
                                <tr>
                                    <th>Provider Family</th>
                                    <th>Dispatched</th>
                                    <th>Delivered</th>
                                    <th>Opens</th>
                                    <th>Clicks</th>
                                    <th>Bounces</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($domain_breakdown as $provider => $stats): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-bold"><?php echo htmlspecialchars($provider); ?></div>
                                        </td>
                                        <td><?php echo number_format($stats['sent']); ?></td>
                                        <td class="text-success fw-medium"><?php echo number_format($stats['delivered']); ?></td>
                                        <td class="text-primary"><?php echo number_format($stats['opened']); ?></td>
                                        <td class="text-info"><?php echo number_format($stats['clicked']); ?></td>
                                        <td class="<?php echo ($stats['bounced'] > 0) ? 'text-danger fw-bold' : 'text-muted'; ?>">
                                            <?php echo number_format($stats['bounced']); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right: Top Clicked URLs -->
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3">
                    <h6 class="fw-bold mb-0 text-primary"><i class="fa-solid fa-arrow-pointer me-2"></i>Top Clicked Links</h6>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($top_links)): ?>
                        <div class="text-center py-5 text-muted small">
                            <i class="fa-solid fa-link-slash fs-3 mb-2 d-block"></i>
                            No link clicks recorded yet.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light small text-uppercase text-secondary">
                                    <tr>
                                        <th>Target URL</th>
                                        <th class="text-end">Clicks</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($top_links as $l): ?>
                                        <tr>
                                            <td>
                                                <div class="text-truncate small" style="max-width: 280px;" title="<?php echo htmlspecialchars($l['target_url']); ?>">
                                                    <i class="fa-solid fa-link text-muted me-1 small"></i>
                                                    <?php echo htmlspecialchars($l['target_url']); ?>
                                                </div>
                                            </td>
                                            <td class="text-end fw-bold text-primary">
                                                <?php echo number_format($l['click_count']); ?>
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
    </div>
</div>

<!-- Modal: Inactive Subscriber Re-engagement Cleanup -->
<div class="modal fade" id="reengageModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="index.php?route=email_campaigns/reengageInactive">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ($csrf_token ?? ''); ?>">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold text-warning"><i class="fa-solid fa-user-clock me-2"></i>Subscriber Re-Engagement Policy</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning py-2 small mb-3">
                        <i class="fa-solid fa-triangle-exclamation me-1"></i><strong>Deliverability Protection:</strong> Major ESPs (Gmail & Yahoo) penalize senders who persistently email unengaged addresses. Pruning inactive subscribers significantly improves primary inbox placement.
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Inactivity Threshold</label>
                        <select name="inactive_days" class="form-select">
                            <option value="90" selected>90 Days without Opens or Clicks (Standard)</option>
                            <option value="120">120 Days without Opens or Clicks</option>
                            <option value="180">180 Days without Opens or Clicks (Strict)</option>
                        </select>
                    </div>
                    <p class="small text-muted mb-0">
                        Matching contacts will be flagged as <code>unsubscribed</code> and automatically excluded from regular marketing broadcasts.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning btn-sm fw-semibold"><i class="fa-solid fa-check me-1"></i>Flag & Exclude Inactive</button>
                </div>
            </div>
        </form>
    </div>
</div>

