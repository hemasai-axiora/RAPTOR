<?php
// Raptor CRM - Campaign Builder with Pre-Send Quality Gate
?>
<div class="container-fluid px-0">
    <div class="d-flex align-items-center mb-4 gap-3">
        <a href="index.php?route=email_campaigns/campaigns" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-arrow-left"></i></a>
        <div>
            <h3 class="fw-bold mb-1"><?php echo $campaign ? 'Campaign Builder: ' . htmlspecialchars($campaign->name) : 'Create New Email Campaign'; ?></h3>
            <p class="text-secondary small mb-0">Compose broadcast, configure A/B subject tests, select audiences, and run deliverability pre-send verification.</p>
        </div>
    </div>

    <!-- Flash Messages -->
    <?php if (!empty($_SESSION['flash_error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm" role="alert">
            <i class="fa-solid fa-triangle-exclamation me-2"></i><?php echo $_SESSION['flash_error']; unset($_SESSION['flash_error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if (!empty($_SESSION['flash_success'])): ?>
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm" role="alert">
            <i class="fa-solid fa-circle-check me-2"></i><?php echo $_SESSION['flash_success']; unset($_SESSION['flash_success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <form method="POST" action="index.php?route=email_campaigns/campaignSave" id="campaign-form">
        <?php if ($campaign): ?>
            <input type="hidden" name="campaign_id" value="<?php echo $campaign->campaign_id; ?>">
        <?php endif; ?>

        <div class="row g-4">
            <!-- Left 8 Cols: Setup, Audience, Content -->
            <div class="col-lg-8">
                <!-- 1. Campaign Identity & Senders -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white py-3">
                        <h6 class="fw-bold mb-0 text-primary"><i class="fa-solid fa-id-badge me-2"></i>1. Campaign Details & Sender Info</h6>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-8">
                                <label class="form-label small fw-semibold">Internal Campaign Name <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control" placeholder="e.g. Q4 Product Release Announcement" value="<?php echo htmlspecialchars($campaign->name ?? ''); ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold">Communication Intent <span class="badge bg-secondary-subtle text-secondary">Guidance Only</span></label>
                                <select name="communication_intent" class="form-select" id="communication_intent">
                                    <option value="promotional" <?php echo (($campaign->communication_intent ?? 'promotional') === 'promotional') ? 'selected' : ''; ?>>Promotional / Marketing</option>
                                    <option value="personal_style" <?php echo (($campaign->communication_intent ?? '') === 'personal_style') ? 'selected' : ''; ?>>Personal-Style Correspondence</option>
                                    <option value="operational" <?php echo (($campaign->communication_intent ?? '') === 'operational') ? 'selected' : ''; ?>>Operational / Transactional</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <div class="p-2 bg-light rounded small text-muted border-start border-3 border-info">
                                    <i class="fa-solid fa-circle-info text-info me-1"></i>
                                    <strong>Intent Guidance:</strong> Communication intent guides preflight rule checklists (D-001–D-020).
                                    Observed results are sample-specific; inbox tab and spam-folder placement are not guaranteed by any email sender.
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">From Name <span class="text-danger">*</span></label>
                                <input type="text" name="from_name" class="form-control" placeholder="Axiora Global" value="<?php echo htmlspecialchars($campaign->from_name ?? 'Axiora Global'); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">From Email Address <span class="text-danger">*</span></label>
                                <input type="email" name="from_email" id="from_email" class="form-control" placeholder="updates@mail.axiora.in" value="<?php echo htmlspecialchars($campaign->from_email ?? 'noreply@axiora.in'); ?>" required>
                                <div class="form-text small">Recommended: Use a dedicated subdomain (e.g. <code>mail.company.com</code>) to isolate domain reputation.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Reply-To Email</label>
                                <input type="email" name="reply_to" class="form-control" placeholder="support@axiora.in" value="<?php echo htmlspecialchars($campaign->reply_to ?? ''); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Preheader Snippet</label>
                                <input type="text" name="preheader" class="form-control" placeholder="Preview text shown next to subject line in inbox" value="<?php echo htmlspecialchars($campaign->preheader ?? ''); ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 2. Subject Line & Optional A/B Split Testing -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <h6 class="fw-bold mb-0 text-primary"><i class="fa-solid fa-flask-vial me-2"></i>2. Subject Line & A/B Testing</h6>
                        <div class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" id="ab_toggle" <?php echo (!empty($campaign->subject_b)) ? 'checked' : ''; ?>>
                            <label class="form-check-label small fw-semibold" for="ab_toggle">Enable A/B Subject Test</label>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Subject Line (Variant A) <span class="text-danger">*</span></label>
                            <input type="text" name="subject_a" id="subject_a" class="form-control" placeholder="Primary compelling subject line" value="<?php echo htmlspecialchars($campaign->subject_a ?? ''); ?>" required>
                            <div class="form-text small">Avoid spam keywords like "FREE", "100% GUARANTEED", and all-caps words.</div>
                        </div>

                        <!-- A/B Test Container -->
                        <div id="ab-container" style="display: <?php echo (!empty($campaign->subject_b)) ? 'block' : 'none'; ?>;" class="p-3 bg-light rounded border border-warning-subtle">
                            <div class="mb-3">
                                <label class="form-label small fw-semibold text-warning-emphasis">Subject Line (Variant B)</label>
                                <input type="text" name="subject_b" class="form-control" placeholder="Alternative test subject line" value="<?php echo htmlspecialchars($campaign->subject_b ?? ''); ?>">
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold">Test Sample Size (%)</label>
                                    <input type="number" name="ab_split_percent" min="10" max="50" step="5" class="form-control form-control-sm" value="<?php echo $campaign->ab_split_percent ?? 20; ?>">
                                    <div class="form-text small">Split test across 10-50% of the audience. Winner will be auto-sent to remaining contacts.</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold">Winning Metric</label>
                                    <select name="ab_winner_metric" class="form-select form-select-sm">
                                        <option value="opens" <?php echo (($campaign->ab_winner_metric ?? 'opens') === 'opens') ? 'selected' : ''; ?>>Highest Open Rate</option>
                                        <option value="clicks" <?php echo (($campaign->ab_winner_metric ?? '') === 'clicks') ? 'selected' : ''; ?>>Highest Click-Through Rate</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 3. Target Audience -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white py-3">
                        <h6 class="fw-bold mb-0 text-primary"><i class="fa-solid fa-users-viewfinder me-2"></i>3. Target Audience Selection</h6>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Select Subscriber List</label>
                                <select name="list_id" id="audience_list" class="form-select">
                                    <option value="">-- None / Use Dynamic Segment --</option>
                                    <?php foreach ($lists as $l): ?>
                                        <option value="<?php echo $l->list_id; ?>" <?php echo (($campaign->list_id ?? 0) == $l->list_id) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($l->name); ?> (<?php echo number_format($l->active_subscribers); ?> subscribers)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Or Select Dynamic Segment</label>
                                <select name="segment_id" id="audience_segment" class="form-select">
                                    <option value="">-- None / Use List --</option>
                                    <?php foreach ($segments as $s): ?>
                                        <option value="<?php echo $s->segment_id; ?>" <?php echo (($campaign->segment_id ?? 0) == $s->segment_id) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($s->name); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 4. Template & Content Editor -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <h6 class="fw-bold mb-0 text-primary"><i class="fa-solid fa-envelope-open-text me-2"></i>4. Email Content & Design</h6>
                        <div class="dropdown">
                            <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                Load Starter Template
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <?php foreach ($templates as $t): ?>
                                    <li>
                                        <a class="dropdown-item btn-load-tpl" href="#" data-id="<?php echo $t->template_id; ?>" data-html="<?php echo htmlspecialchars($t->html_content); ?>" data-plain="<?php echo htmlspecialchars($t->plain_text_content); ?>">
                                            <?php echo htmlspecialchars($t->name); ?>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Merge tags picker bar -->
                        <div class="mb-3 p-2 bg-light rounded d-flex flex-wrap align-items-center gap-1">
                            <span class="small fw-bold text-muted me-2"><i class="fa-solid fa-tags me-1"></i>Insert:</span>
                            <?php foreach ($merge_tags as $tag => $label): ?>
                                <button type="button" class="btn btn-xs btn-outline-secondary py-1 px-2 btn-insert-camp-tag" data-tag="<?php echo htmlspecialchars($tag); ?>" title="<?php echo htmlspecialchars($label); ?>" style="font-size: 0.75rem;">
                                    <code><?php echo htmlspecialchars($tag); ?></code>
                                </button>
                            <?php endforeach; ?>
                        </div>

                        <!-- Tabs: HTML vs Plain Text -->
                        <ul class="nav nav-tabs mb-3" role="tablist">
                            <li class="nav-item">
                                <button class="nav-link active small fw-bold" id="c-html-tab" data-bs-toggle="tab" data-bs-target="#tab-c-html" type="button">HTML Source</button>
                            </li>
                            <li class="nav-item">
                                <button class="nav-link small fw-bold" id="c-plain-tab" data-bs-toggle="tab" data-bs-target="#tab-c-plain" type="button">Mandatory Plain-Text</button>
                            </li>
                        </ul>

                        <div class="tab-content">
                            <div class="tab-pane fade show active" id="tab-c-html">
                                <textarea name="html_content" id="camp_html" rows="18" class="form-control font-monospace small" required><?php echo htmlspecialchars($campaign->html_content ?? CampaignTemplate::getPrebuiltTemplateAnnouncement()); ?></textarea>
                            </div>
                            <div class="tab-pane fade" id="tab-c-plain">
                                <div class="d-flex justify-content-end mb-2">
                                    <button type="button" class="btn btn-outline-primary btn-xs" id="btn-sync-camp-plain"><i class="fa-solid fa-arrows-rotate me-1"></i>Auto-Sync Plain Text</button>
                                </div>
                                <textarea name="plain_text_content" id="camp_plain" rows="18" class="form-control font-monospace small"><?php echo htmlspecialchars($campaign->plain_text_content ?? ''); ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right 4 Cols: Live Preview & Quality Gate Audit Action Center -->
            <div class="col-lg-4">
                <!-- Quality Gate Action Card -->
                <div class="card border-0 shadow-sm mb-4 border-start border-4 border-primary">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <h6 class="fw-bold mb-0 text-primary"><i class="fa-solid fa-shield-halved me-2"></i>Deliverability Intelligence</h6>
                        <a href="index.php?route=email_campaigns/alerts" class="badge bg-warning text-dark text-decoration-none">Alert Center</a>
                    </div>
                    <div class="card-body">
                        <?php if ($campaign && !empty($campaign->approved_snapshot_hash)): ?>
                            <div class="alert alert-success py-2 mb-3 small d-flex align-items-center gap-2">
                                <i class="fa-solid fa-lock text-success fs-5"></i>
                                <div>
                                    <strong>Campaign Approved & Snapshot Locked</strong><br>
                                    <code class="text-dark small">SHA-256: <?php echo substr($campaign->approved_snapshot_hash, 0, 16); ?>...</code>
                                </div>
                            </div>
                        <?php endif; ?>

                        <p class="small text-muted mb-3">
                            Deliverability rule engine evaluates 20 preflight policies (D-001 through D-020). Blockers prevent launch; warnings offer actionable guidance.
                        </p>

                        <?php if ($campaign): ?>
                            <div class="d-grid gap-2 mb-3">
                                <button type="button" class="btn btn-outline-primary btn-sm" id="btn-run-audit" data-campaign-id="<?php echo $campaign->campaign_id; ?>">
                                    <i class="fa-solid fa-stethoscope me-1"></i>Run Preflight Engine (D-001 - D-020)
                                </button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#testSendModal">
                                    <i class="fa-solid fa-paper-plane me-1"></i>Send Test Email to Self
                                </button>
                            </div>

                            <div id="audit-results-card" style="display: none;" class="p-3 bg-light rounded border mb-3">
                                <h6 class="fw-bold small mb-2" id="audit-status-heading">Preflight Status</h6>
                                <div id="audit-checklist-items" class="small"></div>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info py-2 small mb-3">
                                Save campaign draft first to run the live pre-send quality gate and test dispatcher.
                            </div>
                        <?php endif; ?>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary shadow-sm"><i class="fa-solid fa-floppy-disk me-2"></i>Save Draft</button>

                            <?php if ($campaign && in_array($campaign->status, ['draft', 'needs_review'])): ?>
                                <button type="button" class="btn btn-outline-success shadow-sm" id="btn-approve-snapshot" data-campaign-id="<?php echo $campaign->campaign_id; ?>">
                                    <i class="fa-solid fa-stamp me-2"></i>Approve Campaign Snapshot
                                </button>
                            <?php endif; ?>

                            <?php if ($campaign && in_array($campaign->status, ['draft', 'approved', 'scheduled'])): ?>
                                <button type="button" class="btn btn-success shadow-sm" id="btn-launch-campaign" data-campaign-id="<?php echo $campaign->campaign_id; ?>">
                                    <i class="fa-solid fa-rocket me-2"></i>Verify & Launch Campaign
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Live Mini Preview -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white py-2">
                        <span class="small fw-bold text-muted"><i class="fa-solid fa-eye me-1"></i>Mini Preview</span>
                    </div>
                    <div class="card-body p-2 bg-light">
                        <iframe id="camp-preview-frame" style="width: 100%; height: 350px; border: 1px solid #cbd5e1; border-radius: 4px; background: #fff;" sandbox="allow-same-origin"></iframe>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<!-- Modal: Send Test Email -->
<?php if ($campaign): ?>
<div class="modal fade" id="testSendModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-paper-plane text-primary me-2"></i>Send Test Email to Self</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-3">
                    Dispatches a live rendering of this campaign with populated test merge tags to your inbox. Verify subject line, layout, and mobile rendering before launching.
                </p>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Test Recipient Email <span class="text-danger">*</span></label>
                    <input type="email" id="modal_test_email" class="form-control" value="<?php echo htmlspecialchars($_SESSION['user_email'] ?? 'admin@axiora.in'); ?>" required>
                </div>
                <div id="test-send-feedback" class="small"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary btn-sm" id="btn-dispatch-test" data-campaign-id="<?php echo $campaign->campaign_id; ?>">
                    <i class="fa-solid fa-play me-1"></i>Send Test Email
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Forms: Launch and Approve Campaign Confirmation -->
<form method="POST" action="index.php?route=email_campaigns/campaignLaunch" id="launch-form">
    <input type="hidden" name="campaign_id" value="<?php echo $campaign->campaign_id; ?>">
</form>
<form method="POST" action="index.php?route=email_campaigns/campaignApprove" id="approve-form">
    <input type="hidden" name="campaign_id" value="<?php echo $campaign->campaign_id; ?>">
</form>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const htmlArea = document.getElementById('camp_html');
    const plainArea = document.getElementById('camp_plain');
    const previewFrame = document.getElementById('camp-preview-frame');
    const abToggle = document.getElementById('ab_toggle');
    const abContainer = document.getElementById('ab-container');
    const btnSyncPlain = document.getElementById('btn-sync-camp-plain');

    function updateCampPreview() {
        if (!previewFrame) return;
        let rendered = htmlArea.value
            .replace(/\{\{\s*first_name(?:\|([^}]+))?\s*\}\}/g, '$1' || 'John')
            .replace(/\{\{\s*last_name\s*\}\}/g, 'Doe')
            .replace(/\{\{\s*company_name\s*\}\}/g, 'Acme Corp')
            .replace(/\{\{\s*unsubscribe_url\s*\}\}/g, '#preview-unsubscribe')
            .replace(/\{\{\s*company_address\s*\}\}/g, 'Axiora Global Solutions, Cyber City, Hyderabad, India')
            .replace(/\{\{\s*current_year\s*\}\}/g, '2026');

        const doc = previewFrame.contentDocument || previewFrame.contentWindow.document;
        doc.open();
        doc.write(rendered);
        doc.close();
    }

    if (htmlArea) {
        htmlArea.addEventListener('input', updateCampPreview);
        updateCampPreview();
    }

    if (abToggle && abContainer) {
        abToggle.addEventListener('change', function() {
            abContainer.style.display = this.checked ? 'block' : 'none';
        });
    }

    // Insert Merge Tag
    document.querySelectorAll('.btn-insert-camp-tag').forEach(btn => {
        btn.addEventListener('click', function() {
            const tag = this.getAttribute('data-tag');
            const start = htmlArea.selectionStart;
            const end = htmlArea.selectionEnd;
            htmlArea.value = htmlArea.value.substring(0, start) + tag + htmlArea.value.substring(end);
            htmlArea.focus();
            htmlArea.selectionStart = htmlArea.selectionEnd = start + tag.length;
            updateCampPreview();
        });
    });

    // Auto sync plain text
    if (btnSyncPlain) {
        btnSyncPlain.addEventListener('click', function() {
            let text = htmlArea.value
                .replace(/<style[^>]*>.*?<\/style>/gis, '')
                .replace(/<script[^>]*>.*?<\/script>/gis, '')
                .replace(/<a\s+[^>]*href=["']([^"']*)["'][^>]*>(.*?)<\/a>/gis, '$2 ($1)')
                .replace(/<\/(h[1-6]|p|div|tr)>/gi, '\n\n')
                .replace(/<br\s*\/?>/gi, '\n')
                .replace(/<li[^>]*>/gi, '• ')
                .replace(/<\/li>/gi, '\n');
            const temp = document.createElement('div');
            temp.innerHTML = text;
            plainArea.value = (temp.textContent || temp.innerText || '').trim();
        });
    }

    // Load starter template
    document.querySelectorAll('.btn-load-tpl').forEach(item => {
        item.addEventListener('click', function(e) {
            e.preventDefault();
            htmlArea.value = this.getAttribute('data-html');
            plainArea.value = this.getAttribute('data-plain');
            updateCampPreview();
        });
    });

    // Run Pre-Send Audit
    const btnRunAudit = document.getElementById('btn-run-audit');
    if (btnRunAudit) {
        btnRunAudit.addEventListener('click', function() {
            const cid = this.getAttribute('data-campaign-id');
            const card = document.getElementById('audit-results-card');
            const heading = document.getElementById('audit-status-heading');
            const items = document.getElementById('audit-checklist-items');

            card.style.display = 'block';
            items.innerHTML = '<div class="text-center py-2"><i class="fa-solid fa-spinner fa-spin me-1"></i>Evaluating 8 deliverability checks...</div>';

            fetch('index.php?route=email_campaigns/campaignChecklist&id=' + cid)
                .then(res => res.json())
                .then(data => {
                    if (!data.success) {
                        items.innerHTML = '<div class="text-danger">' + (data.error || 'Failed to run audit') + '</div>';
                        return;
                    }
                    const cl = data.checklist;
                    if (cl.passed) {
                        heading.innerHTML = '<span class="text-success"><i class="fa-solid fa-circle-check me-1"></i>All Checks Passed (' + data.recipients.reachable + ' Reachable Subscribers)</span>';
                    } else {
                        heading.innerHTML = '<span class="text-danger"><i class="fa-solid fa-triangle-exclamation me-1"></i>Launch Blocked: ' + cl.blockers.length + ' Issue(s)</span>';
                    }

                    let html = '<ul class="list-unstyled mb-2">';
                    for (const key in cl.checks) {
                        const check = cl.checks[key];
                        const icon = check.passed ? '<i class="fa-solid fa-check text-success me-1"></i>' : (check.critical ? '<i class="fa-solid fa-xmark text-danger me-1"></i>' : '<i class="fa-solid fa-triangle-exclamation text-warning me-1"></i>');
                        html += '<li class="mb-1">' + icon + '<strong>' + check.name + ':</strong> <span class="text-secondary">' + check.message + '</span></li>';
                    }
                    html += '</ul>';

                    if (cl.preflight && cl.preflight.findings && cl.preflight.findings.length > 0) {
                        html += '<div class="mt-2 pt-2 border-top"><div class="fw-bold small mb-1 text-primary"><i class="fa-solid fa-satellite-dish me-1"></i>Deliverability Rule Findings:</div>';
                        cl.preflight.findings.forEach(f => {
                            const badgeClass = f.severity === 'BLOCK' ? 'bg-danger' : (f.severity === 'HIGH' ? 'bg-warning text-dark' : 'bg-info text-dark');
                            html += '<div class="p-1 mb-1 rounded bg-white border small"><span class="badge ' + badgeClass + ' me-1">' + f.severity + '</span> <code>' + f.rule_id + '</code> ' + f.message + '<div class="text-muted" style="font-size: 0.75rem;">Fix: ' + f.remediation + '</div></div>';
                        });
                        html += '</div>';
                    }
                    items.innerHTML = html;
                })
                .catch(() => {
                    items.innerHTML = '<div class="text-danger">Failed to connect to audit endpoint.</div>';
                });
        });
    }

    // Dispatch Test Email
    const btnDispatchTest = document.getElementById('btn-dispatch-test');
    if (btnDispatchTest) {
        btnDispatchTest.addEventListener('click', function() {
            const cid = this.getAttribute('data-campaign-id');
            const email = document.getElementById('modal_test_email').value;
            const fb = document.getElementById('test-send-feedback');

            fb.innerHTML = '<div class="text-primary"><i class="fa-solid fa-spinner fa-spin me-1"></i>Sending test email to ' + email + '...</div>';
            this.disabled = true;

            const fd = new FormData();
            fd.append('campaign_id', cid);
            fd.append('test_email', email);

            fetch('index.php?route=email_campaigns/campaignTestSend', {
                method: 'POST',
                body: fd
            })
            .then(res => res.json())
            .then(data => {
                btnDispatchTest.disabled = false;
                if (data.success) {
                    fb.innerHTML = '<div class="alert alert-success py-2 mb-0 mt-2"><i class="fa-solid fa-circle-check me-1"></i>' + data.message + '</div>';
                } else {
                    fb.innerHTML = '<div class="alert alert-danger py-2 mb-0 mt-2">' + data.error + '</div>';
                }
            })
            .catch(() => {
                btnDispatchTest.disabled = false;
                fb.innerHTML = '<div class="alert alert-danger py-2 mb-0 mt-2">Error dispatching test send.</div>';
            });
        });
    }

    // Approve Campaign Snapshot Trigger
    const btnApprove = document.getElementById('btn-approve-snapshot');
    if (btnApprove) {
        btnApprove.addEventListener('click', function() {
            if (confirm('Approve campaign snapshot? This will freeze the content hash and record maker-checker compliance signoff.')) {
                document.getElementById('approve-form').submit();
            }
        });
    }

    // Launch Campaign Trigger
    const btnLaunch = document.getElementById('btn-launch-campaign');
    if (btnLaunch) {
        btnLaunch.addEventListener('click', function() {
            if (confirm('Launch this campaign? The pre-send deliverability checklist will be verified and messages will be queued for worker dispatch.')) {
                document.getElementById('launch-form').submit();
            }
        });
    }
});
</script>

