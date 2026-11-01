<?php
// Raptor CRM - Domain Setup & DNS Records Detail View
?>
<div class="container-fluid px-0">
    <!-- Breadcrumb & Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <a href="index.php?route=email_campaigns/domains" class="text-decoration-none text-secondary small">
                    <i class="fa-solid fa-arrow-left me-1"></i>Back to Domains
                </a>
            </div>
            <h3 class="fw-bold mb-1">
                <i class="fa-solid fa-globe text-primary me-2"></i>DNS Setup: <?php echo htmlspecialchars($domain->domain_name); ?>
                <?php if (!empty($domain->is_default)): ?>
                    <span class="badge bg-primary fs-6 align-middle ms-1">DEFAULT</span>
                <?php endif; ?>
            </h3>
            <p class="text-secondary small mb-0">Add the DNS records below into your DNS host provider (Cloudflare, AWS Route 53, GoDaddy, Namecheap, etc.) to authenticate your sending domain.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="index.php?route=email_campaigns/domainVerify&id=<?php echo $domain->domain_id; ?>" class="btn btn-success btn-sm px-3">
                <i class="fa-solid fa-rotate me-1"></i>Verify DNS Records Now
            </a>
            <a href="index.php?route=email_campaigns/domains" class="btn btn-outline-secondary btn-sm">
                <i class="fa-solid fa-list me-1"></i>All Domains
            </a>
        </div>
    </div>

    <!-- Status Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-secondary small fw-medium mb-1">SPF Status</div>
                    <?php if ($domain->spf_status === 'valid'): ?>
                        <div class="fs-5 fw-bold text-success"><i class="fa-solid fa-check-circle me-1"></i>Verified</div>
                    <?php else: ?>
                        <div class="fs-5 fw-bold text-warning"><i class="fa-solid fa-clock me-1"></i>Pending / Invalid</div>
                    <?php endif; ?>
                    <div class="small text-muted mt-1">Sender Policy Framework</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-secondary small fw-medium mb-1">DKIM (2048-bit)</div>
                    <?php if ($domain->dkim_status === 'valid'): ?>
                        <div class="fs-5 fw-bold text-success"><i class="fa-solid fa-check-circle me-1"></i>Verified</div>
                    <?php else: ?>
                        <div class="fs-5 fw-bold text-warning"><i class="fa-solid fa-clock me-1"></i>Pending / Invalid</div>
                    <?php endif; ?>
                    <div class="small text-muted mt-1">Cryptographic RSA Signature</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-secondary small fw-medium mb-1">DMARC Policy</div>
                    <?php if ($domain->dmarc_status === 'valid'): ?>
                        <div class="fs-5 fw-bold text-success"><i class="fa-solid fa-check-circle me-1"></i>Verified</div>
                    <?php else: ?>
                        <div class="fs-5 fw-bold text-warning"><i class="fa-solid fa-clock me-1"></i>Pending / Invalid</div>
                    <?php endif; ?>
                    <div class="small text-muted mt-1">Policy: <code>p=<?php echo htmlspecialchars($domain->dmarc_policy ?? 'none'); ?></code></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-secondary small fw-medium mb-1">MX Record</div>
                    <?php if ($domain->mx_status === 'valid'): ?>
                        <div class="fs-5 fw-bold text-success"><i class="fa-solid fa-check-circle me-1"></i>Verified</div>
                    <?php else: ?>
                        <div class="fs-5 fw-bold text-warning"><i class="fa-solid fa-clock me-1"></i>Pending / Invalid</div>
                    <?php endif; ?>
                    <div class="small text-muted mt-1">Reply / Inbound Routing</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Required DNS Records Table -->
    <div class="card border-0 shadow-sm rounded-3 mb-4">
        <div class="card-header bg-transparent border-bottom py-3 d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0 fw-semibold">Required DNS Records to Add</h5>
            <span class="small text-muted">DNS changes can take anywhere from 5 minutes up to 24-48 hours to propagate globally.</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 90px;">Type</th>
                        <th style="width: 220px;">Host / Name</th>
                        <th>Required Value</th>
                        <th style="width: 140px;">Status</th>
                        <th style="width: 100px;" class="text-end">Copy</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($records as $key => $rec): ?>
                        <tr>
                            <td>
                                <span class="badge bg-dark-subtle text-dark border"><?php echo htmlspecialchars($rec['type']); ?></span>
                            </td>
                            <td>
                                <div class="fw-semibold font-monospace small"><?php echo htmlspecialchars($rec['host']); ?></div>
                                <div class="text-muted" style="font-size: 11px;">FQDN: <code><?php echo htmlspecialchars($rec['full_host']); ?></code></div>
                            </td>
                            <td>
                                <div class="font-monospace small text-break bg-light p-2 rounded border" id="val-<?php echo $key; ?>">
                                    <?php echo htmlspecialchars($rec['value']); ?>
                                </div>
                                <div class="text-secondary" style="font-size: 11px; margin-top: 3px;">
                                    <i class="fa-solid fa-circle-question me-1 text-primary"></i><?php echo htmlspecialchars($rec['purpose']); ?>
                                </div>
                            </td>
                            <td>
                                <?php if ($rec['status'] === 'valid'): ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                                        <i class="fa-solid fa-check me-1"></i>Verified
                                    </span>
                                <?php elseif ($rec['status'] === 'info'): ?>
                                    <span class="badge bg-info-subtle text-info border border-info-subtle px-2 py-1">
                                        <i class="fa-solid fa-info me-1"></i>Optional Branded
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-2 py-1">
                                        <i class="fa-solid fa-clock me-1"></i>Unverified
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="copyToClipboard('val-<?php echo $key; ?>', this)" title="Copy Value">
                                    <i class="fa-solid fa-copy"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Blacklist (DBL) Monitoring Status for this Domain -->
    <div class="card border-0 shadow-sm rounded-3 mb-4">
        <div class="card-header bg-transparent border-bottom py-3 d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0 fw-semibold">
                <i class="fa-solid fa-shield-virus text-primary me-2"></i>Domain Blacklist (DNSBL / DBL) Reputation Check
            </h5>
            <?php if (!empty($blacklist) && $blacklist['is_clean']): ?>
                <span class="badge bg-success"><i class="fa-solid fa-check-circle me-1"></i>CLEAN / UNLISTED</span>
            <?php else: ?>
                <span class="badge bg-danger"><i class="fa-solid fa-triangle-exclamation me-1"></i>LISTED ON BLACKLIST</span>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <?php if (!empty($blacklist['details'])): ?>
                    <?php foreach ($blacklist['details'] as $host => $bl): ?>
                        <div class="col-md-6">
                            <div class="p-3 rounded border <?php echo $bl['listed'] ? 'border-danger bg-danger-subtle' : 'bg-light'; ?>">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <strong class="small"><?php echo htmlspecialchars($bl['name']); ?></strong>
                                    <?php if ($bl['listed']): ?>
                                        <span class="badge bg-danger">LISTED</span>
                                    <?php else: ?>
                                        <span class="badge bg-success">CLEAN</span>
                                    <?php endif; ?>
                                </div>
                                <div class="small text-muted font-monospace"><?php echo htmlspecialchars($host); ?></div>
                                <?php if ($bl['listed']): ?>
                                    <div class="small text-danger mt-1">
                                        Listed with response code: <code><?php echo htmlspecialchars($bl['response_code']); ?></code>
                                        <a href="<?php echo htmlspecialchars($bl['delist_url']); ?>" target="_blank" class="ms-2 fw-medium">Request Delisting &rarr;</a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function copyToClipboard(elementId, btn) {
    const text = document.getElementById(elementId).innerText.trim();
    navigator.clipboard.writeText(text).then(function() {
        const originalHtml = btn.innerHTML;
        btn.innerHTML = '<i class="fa-solid fa-check text-success"></i>';
        btn.classList.add('border-success');
        setTimeout(() => {
            btn.innerHTML = originalHtml;
            btn.classList.remove('border-success');
        }, 1500);
    });
}
</script>
