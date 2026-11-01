<?php
// Raptor CRM - Sending Domains & DNS Authentication Overview
?>
<div class="container-fluid px-0">
    <!-- Header & Subnav -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <h3 class="fw-bold mb-1"><i class="fa-solid fa-shield-halved text-primary me-2"></i>Sending Domains & DNS Authentication</h3>
            <p class="text-secondary small mb-0">Configure dedicated sending subdomains with SPF, 2048-bit DKIM, DMARC, and MX routing to maximize inbox placement.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="index.php?route=email_campaigns/deliverabilityGuidance" class="btn btn-outline-info btn-sm">
                <i class="fa-solid fa-graduation-cap me-1"></i>Reputation & Postmaster Guide
            </a>
            <a href="index.php?route=email_campaigns/healthReport" class="btn btn-outline-secondary btn-sm">
                <i class="fa-solid fa-heart-pulse me-1"></i>Health Report
            </a>
            <button type="button" class="btn btn-primary btn-sm px-3" data-bs-toggle="modal" data-bs-target="#addDomainModal">
                <i class="fa-solid fa-plus me-1"></i>Add Sending Domain
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
            <a class="nav-link fw-medium" href="index.php?route=email_campaigns/analytics">
                <i class="fa-solid fa-chart-pie me-1"></i>Analytics
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link active fw-medium" href="index.php?route=email_campaigns/domains">
                <i class="fa-solid fa-shield-halved me-1"></i>Domains & DNS
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link fw-medium" href="index.php?route=email_campaigns/deliverabilityGuidance">
                <i class="fa-solid fa-book-bookmark me-1"></i>Deliverability Guidance
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link fw-medium" href="index.php?route=email_campaigns/healthReport">
                <i class="fa-solid fa-heart-pulse me-1"></i>Daily Health Report
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
                <i class="fa-solid fa-list me-1"></i>Lists
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link fw-medium text-danger" href="index.php?route=email_campaigns/suppressions">
                <i class="fa-solid fa-ban me-1"></i>Global Suppression
            </a>
        </li>
    </ul>

    <!-- Domain Best Practices Banner -->
    <div class="alert alert-info border-0 shadow-sm mb-4">
        <div class="d-flex align-items-start gap-3">
            <i class="fa-solid fa-circle-info fa-lg mt-1 text-info"></i>
            <div class="small">
                <strong>Deliverability Best Practice: Dedicated Subdomain Isolation</strong><br>
                Never send bulk marketing campaigns from your naked apex domain (e.g. <code>company.com</code>). 
                Always use a dedicated subdomain (such as <code>mail.company.com</code> or <code>marketing.company.com</code>). 
                This protects your critical corporate communication reputation from marketing spam traps and rate throttles.
            </div>
        </div>
    </div>

    <!-- Sending Domains Table -->
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-header bg-transparent border-bottom py-3 d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0 fw-semibold">Configured Sending Subdomains</h5>
            <span class="badge bg-secondary rounded-pill"><?php echo count($domains); ?> Domains</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="min-width: 200px;">Domain Name</th>
                        <th class="text-center">SPF</th>
                        <th class="text-center">DKIM (2048-bit)</th>
                        <th class="text-center">DMARC</th>
                        <th class="text-center">MX</th>
                        <th>Overall Status</th>
                        <th>Last Verified</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($domains)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-5 text-secondary">
                                <i class="fa-solid fa-globe fa-3x mb-3 text-secondary opacity-50"></i>
                                <p class="mb-2 fw-medium">No sending domains configured yet.</p>
                                <p class="small text-muted mb-3">Add your dedicated email marketing subdomain (e.g., <code>mail.company.com</code>) to generate 2048-bit DKIM keys and DNS records.</p>
                                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addDomainModal">
                                    <i class="fa-solid fa-plus me-1"></i>Add Sending Domain
                                </button>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($domains as $d): ?>
                            <?php 
                                $isAllValid = ($d->spf_status === 'valid' && $d->dkim_status === 'valid' && $d->dmarc_status === 'valid');
                                $statusBadge = $isAllValid 
                                    ? '<span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1"><i class="fa-solid fa-check-circle me-1"></i>Fully Verified</span>'
                                    : '<span class="badge bg-warning-subtle text-warning border border-warning-subtle px-2 py-1"><i class="fa-solid fa-triangle-exclamation me-1"></i>Action Needed</span>';
                            ?>
                            <tr>
                                <td>
                                    <div class="fw-bold">
                                        <a href="index.php?route=email_campaigns/domainShow&id=<?php echo $d->domain_id; ?>" class="text-decoration-none">
                                            <?php echo htmlspecialchars($d->domain_name); ?>
                                        </a>
                                        <?php if (!empty($d->is_default)): ?>
                                            <span class="badge bg-primary-subtle text-primary ms-1" style="font-size:10px;">DEFAULT</span>
                                        <?php endif; ?>
                                    </div>
                                    <small class="text-muted">Selector: <code><?php echo htmlspecialchars($d->dkim_selector ?: 'raptor'); ?></code></small>
                                </td>
                                <td class="text-center">
                                    <?php if ($d->spf_status === 'valid'): ?>
                                        <span class="badge bg-success"><i class="fa-solid fa-check"></i> Valid</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary"><?php echo ucfirst($d->spf_status ?? 'unverified'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($d->dkim_status === 'valid'): ?>
                                        <span class="badge bg-success"><i class="fa-solid fa-check"></i> Valid</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary"><?php echo ucfirst($d->dkim_status ?? 'unverified'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($d->dmarc_status === 'valid'): ?>
                                        <span class="badge bg-success"><i class="fa-solid fa-check"></i> Valid</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary"><?php echo ucfirst($d->dmarc_status ?? 'unverified'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($d->mx_status === 'valid'): ?>
                                        <span class="badge bg-success"><i class="fa-solid fa-check"></i> Valid</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary"><?php echo ucfirst($d->mx_status ?? 'unverified'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $statusBadge; ?></td>
                                <td>
                                    <small class="text-secondary">
                                        <?php echo !empty($d->last_checked_at) ? date('M j, Y H:i', strtotime($d->last_checked_at)) : 'Never'; ?>
                                    </small>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <a href="index.php?route=email_campaigns/domainShow&id=<?php echo $d->domain_id; ?>" class="btn btn-outline-primary" title="DNS Records & Setup">
                                            <i class="fa-solid fa-gear me-1"></i>Setup DNS
                                        </a>
                                        <a href="index.php?route=email_campaigns/domainVerify&id=<?php echo $d->domain_id; ?>" class="btn btn-outline-success" title="Run Live DNS Lookup">
                                            <i class="fa-solid fa-rotate me-1"></i>Verify
                                        </a>
                                        <form method="POST" action="index.php?route=email_campaigns/domainDelete" class="d-inline" onsubmit="return confirm('Are you sure you want to delete domain <?php echo htmlspecialchars($d->domain_name); ?>?');">
                                            <input type="hidden" name="domain_id" value="<?php echo $d->domain_id; ?>">
                                            <button type="submit" class="btn btn-outline-danger" title="Delete Domain">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal: Add Sending Domain -->
<div class="modal fade" id="addDomainModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="index.php?route=email_campaigns/domainCreate">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-plus-circle text-primary me-2"></i>Add Dedicated Sending Domain</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-medium">Domain or Subdomain <span class="text-danger">*</span></label>
                        <input type="text" name="domain_name" class="form-control" placeholder="e.g. mail.axiora.com" required>
                        <div class="form-text">Recommended: use a subdomain such as <code>mail.yourcompany.com</code> to protect root domain reputation.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-medium">DKIM Selector</label>
                        <input type="text" name="dkim_selector" class="form-control" value="raptor">
                        <div class="form-text">Default is <code>raptor</code> (e.g. <code>raptor._domainkey.mail.axiora.com</code>).</div>
                    </div>
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" name="is_default" value="1" id="isDefaultCheck" checked>
                        <label class="form-check-label fw-medium" for="isDefaultCheck">Set as Default Sending Domain</label>
                    </div>
                    <div class="alert alert-light border small text-muted mb-0">
                        <i class="fa-solid fa-key me-1 text-primary"></i>
                        A brand-new <strong>2048-bit RSA private & public keypair</strong> will be generated automatically upon creation.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-3">
                        <i class="fa-solid fa-shield me-1"></i>Generate Keys & Add Domain
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

