<?php
// Raptor CRM - Daily Deliverability Health Report View
$m = $health['metrics_24h'];
$q = $health['queue'];
?>
<div class="container-fluid px-0">
    <!-- Header & Subnav -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <h3 class="fw-bold mb-1"><i class="fa-solid fa-heart-pulse text-danger me-2"></i>Daily Deliverability Health Report</h3>
            <p class="text-secondary small mb-0">Automated monitoring report auditing 24-hour bounce/complaint rates, DNS authentication, queue backlog, and blacklist status.</p>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#sendTestReportModal">
                <i class="fa-solid fa-paper-plane me-1"></i>Dispatch Report Now
            </button>
            <a href="index.php?route=email_campaigns/domains" class="btn btn-outline-secondary btn-sm">
                <i class="fa-solid fa-shield-halved me-1"></i>Domains
            </a>
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
            <a class="nav-link fw-medium" href="index.php?route=email_campaigns/analytics">
                <i class="fa-solid fa-chart-pie me-1"></i>Analytics
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link fw-medium" href="index.php?route=email_campaigns/domains">
                <i class="fa-solid fa-shield-halved me-1"></i>Domains & DNS
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link fw-medium" href="index.php?route=email_campaigns/deliverabilityGuidance">
                <i class="fa-solid fa-book-bookmark me-1"></i>Deliverability Guidance
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link active fw-medium" href="index.php?route=email_campaigns/healthReport">
                <i class="fa-solid fa-heart-pulse me-1"></i>Daily Health Report
            </a>
        </li>
    </ul>

    <!-- 24-Hour Tripwire Alert Banner if Any Issue -->
    <?php if ($m['bounce_status'] === 'critical' || $m['complaint_status'] === 'critical'): ?>
        <div class="alert alert-danger border-0 shadow-sm mb-4">
            <div class="d-flex align-items-center gap-3">
                <i class="fa-solid fa-triangle-exclamation fa-2x"></i>
                <div>
                    <h6 class="fw-bold mb-1">CRITICAL DELIVERABILITY TRIPWIRE ALERT!</h6>
                    <div class="small">
                        Bounce rate (<?php echo $m['bounce_rate']; ?>%) or complaint rate (<?php echo $m['complaint_rate']; ?>%) has exceeded enterprise safety thresholds. 
                        Sending has been protected to preserve your IP/domain sender reputation.
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Health Metrics Grid -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-secondary small fw-medium mb-1">Dispatches (24h)</div>
                    <div class="fs-4 fw-bold text-dark"><?php echo number_format($m['sent']); ?></div>
                    <div class="small text-muted mt-1"><?php echo number_format($m['delivered']); ?> confirmed delivered</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100 <?php echo $m['bounce_status'] === 'critical' ? 'border-danger border-2' : ''; ?>">
                <div class="card-body">
                    <div class="text-secondary small fw-medium mb-1">Bounce Rate (Threshold &lt; 2%)</div>
                    <div class="fs-4 fw-bold <?php echo $m['bounce_status'] === 'critical' ? 'text-danger' : ($m['bounce_status'] === 'warning' ? 'text-warning' : 'text-success'); ?>">
                        <?php echo $m['bounce_rate']; ?>%
                    </div>
                    <div class="small text-muted mt-1"><?php echo $m['bounces']; ?> bounces (<?php echo $m['hard_bounces']; ?> hard / <?php echo $m['soft_bounces']; ?> soft)</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100 <?php echo $m['complaint_status'] === 'critical' ? 'border-danger border-2' : ''; ?>">
                <div class="card-body">
                    <div class="text-secondary small fw-medium mb-1">Complaint Rate (Threshold &lt; 0.1%)</div>
                    <div class="fs-4 fw-bold <?php echo $m['complaint_status'] === 'critical' ? 'text-danger' : ($m['complaint_status'] === 'warning' ? 'text-warning' : 'text-success'); ?>">
                        <?php echo $m['complaint_rate']; ?>%
                    </div>
                    <div class="small text-muted mt-1"><?php echo $m['complaints']; ?> total abuse complaints</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-secondary small fw-medium mb-1">Queue Backlog & Suppressions</div>
                    <div class="fs-4 fw-bold text-primary"><?php echo number_format($q['pending']); ?> Pending</div>
                    <div class="small text-muted mt-1"><?php echo number_format($health['suppressions_total']); ?> globally suppressed addresses</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Domain DNS Status Table -->
    <div class="card border-0 shadow-sm rounded-3 mb-4">
        <div class="card-header bg-transparent border-bottom py-3">
            <h5 class="card-title mb-0 fw-semibold">Sending Domain DNS Health Status</h5>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Domain</th>
                        <th>SPF</th>
                        <th>DKIM (2048-bit)</th>
                        <th>DMARC</th>
                        <th>MX</th>
                        <th>Authentication Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($health['domains'])): ?>
                        <tr><td colspan="6" class="text-center py-4 text-muted">No sending domains configured.</td></tr>
                    <?php else: ?>
                        <?php foreach ($health['domains'] as $d): ?>
                            <tr>
                                <td class="fw-bold"><?php echo htmlspecialchars($d['name']); ?></td>
                                <td>
                                    <span class="badge <?php echo $d['spf'] === 'valid' ? 'bg-success' : 'bg-warning'; ?>">
                                        <?php echo ucfirst($d['spf']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge <?php echo $d['dkim'] === 'valid' ? 'bg-success' : 'bg-warning'; ?>">
                                        <?php echo ucfirst($d['dkim']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge <?php echo $d['dmarc'] === 'valid' ? 'bg-success' : 'bg-warning'; ?>">
                                        <?php echo ucfirst($d['dmarc']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge <?php echo $d['mx'] === 'valid' ? 'bg-success' : 'bg-warning'; ?>">
                                        <?php echo ucfirst($d['mx']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($d['is_ready']): ?>
                                        <span class="text-success fw-bold"><i class="fa-solid fa-check-circle me-1"></i>Production Ready</span>
                                    <?php else: ?>
                                        <span class="text-warning fw-bold"><i class="fa-solid fa-triangle-exclamation me-1"></i>Configuration Incomplete</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Blacklist Summary -->
    <?php if (!empty($health['blacklist'])): ?>
        <div class="card border-0 shadow-sm rounded-3 mb-4">
            <div class="card-header bg-transparent border-bottom py-3 d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0 fw-semibold">Default Domain Blacklist (DNSBL) Monitor</h5>
                <?php if ($health['blacklist']['is_clean']): ?>
                    <span class="badge bg-success"><i class="fa-solid fa-check me-1"></i>All Public DNSBLs Clean</span>
                <?php else: ?>
                    <span class="badge bg-danger"><i class="fa-solid fa-triangle-exclamation me-1"></i>Listed on <?php echo $health['blacklist']['listed_count']; ?> Blacklist(s)</span>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <p class="small text-secondary mb-3">Auditing domain <code><?php echo htmlspecialchars($health['blacklist']['domain']); ?></code> across premier public blocklists:</p>
                <div class="row g-2">
                    <?php foreach ($health['blacklist']['details'] as $host => $bl): ?>
                        <div class="col-md-6">
                            <div class="p-2 px-3 rounded border d-flex justify-content-between align-items-center <?php echo $bl['listed'] ? 'bg-danger-subtle border-danger' : 'bg-light'; ?>">
                                <span class="small fw-medium"><?php echo htmlspecialchars($bl['name']); ?></span>
                                <span class="badge <?php echo $bl['listed'] ? 'bg-danger' : 'bg-success'; ?>">
                                    <?php echo $bl['listed'] ? 'LISTED' : 'CLEAN'; ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Cron Automation Instruction Card -->
    <div class="card border-0 shadow-sm bg-light mb-4">
        <div class="card-body">
            <h6 class="fw-bold mb-1"><i class="fa-solid fa-clock text-primary me-2"></i>Automate via Cron / Server Task Scheduler</h6>
            <p class="small text-secondary mb-2">To have Raptor CRM automatically compile and dispatch this health report to the administrator email every morning at 06:00 UTC, add the following cron job:</p>
            <div class="bg-dark text-white p-2 rounded font-monospace small">
                0 6 * * * php <?php echo dirname(dirname(dirname(dirname(__FILE__)))); ?>/bin/daily_health_report.php &gt;&gt; /var/log/raptor_health.log 2&gt;&amp;1
            </div>
        </div>
    </div>
</div>

<!-- Modal: Send Test Health Report -->
<div class="modal fade" id="sendTestReportModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="index.php?route=email_campaigns/healthReportSendTest">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-paper-plane text-primary me-2"></i>Dispatch Deliverability Health Report</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-secondary">
                        A full deliverability audit report will be compiled and sent via the active mailer adapter.
                    </p>
                    <div class="mb-3">
                        <label class="form-label fw-medium">Recipient Admin Email <span class="text-danger">*</span></label>
                        <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($_SESSION['user_email'] ?? 'admin@raptor.internal'); ?>" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-3">
                        <i class="fa-solid fa-paper-plane me-1"></i>Send Report Email Now
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
