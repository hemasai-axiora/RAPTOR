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
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div class="d-flex align-items-center gap-2">
                            <h6 class="fw-bold mb-0 text-primary"><i class="fa-solid fa-flask-vial me-2"></i>2. Subject Line & A/B Testing</h6>
                            <button type="button" class="btn btn-outline-primary btn-sm py-1 px-2" id="btn-open-ai-subject" data-bs-toggle="modal" data-bs-target="#aiSubjectModal">
                                <i class="fa-solid fa-wand-magic-sparkles text-primary me-1"></i>AI Subject Copilot
                            </button>
                        </div>
                        <div class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" id="ab_toggle" <?php echo (!empty($campaign->subject_b)) ? 'checked' : ''; ?>>
                            <label class="form-check-label small fw-semibold" for="ab_toggle">Enable A/B Subject Test</label>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label class="form-label small fw-semibold mb-0">Subject Line (Variant A) <span class="text-danger">*</span></label>
                                <span class="badge bg-secondary-subtle text-secondary small" id="subject-char-count">0 / 55 chars</span>
                            </div>
                            <div class="input-group">
                                <input type="text" name="subject_a" id="subject_a" class="form-control" placeholder="Primary compelling subject line" value="<?php echo htmlspecialchars($campaign->subject_a ?? ''); ?>" required>
                                <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#aiSubjectModal" title="AI Subject Line Suggestions">
                                    <i class="fa-solid fa-wand-magic-sparkles"></i>
                                </button>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mt-1">
                                <div class="form-text small mb-0">Recommended: 30–55 chars. Avoid ALL-CAPS and spam triggers like "FREE", "GUARANTEED".</div>
                                <div id="subject-spam-indicator" class="small"></div>
                            </div>
                        </div>

                        <!-- A/B Test Container -->
                        <div id="ab-container" style="display: <?php echo (!empty($campaign->subject_b)) ? 'block' : 'none'; ?>;" class="p-3 bg-light rounded border border-warning-subtle">
                            <div class="mb-3">
                                <label class="form-label small fw-semibold text-warning-emphasis">Subject Line (Variant B)</label>
                                <div class="input-group">
                                    <input type="text" name="subject_b" id="subject_b" class="form-control" placeholder="Alternative test subject line" value="<?php echo htmlspecialchars($campaign->subject_b ?? ''); ?>">
                                    <button type="button" class="btn btn-outline-warning" data-bs-toggle="modal" data-bs-target="#aiSubjectModal" title="Use AI Suggestions for Variant B">
                                        <i class="fa-solid fa-wand-magic-sparkles"></i>
                                    </button>
                                </div>
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

            <!-- Right 4 Cols: Live Preview & Deliverability Health Meter Action Center -->
            <div class="col-lg-4">
                <!-- Visual Deliverability Health Meter & Quality Gate Action Card -->
                <div class="card border-0 shadow-sm mb-4 border-start border-4 border-primary">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center gap-2">
                            <h6 class="fw-bold mb-0 text-primary"><i class="fa-solid fa-shield-halved me-2"></i>Deliverability Intelligence</h6>
                        </div>
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

                        <!-- Visual Deliverability Health Meter (0-100 Score Gauge) -->
                        <div class="p-3 mb-3 bg-light rounded-3 border text-center" id="health-meter-box">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="small fw-bold text-uppercase text-muted" style="letter-spacing: 0.05em; font-size: 0.72rem;">Deliverability Health Score</span>
                                <span class="badge bg-secondary" id="health-meter-badge">Audit Pending</span>
                            </div>

                            <div class="d-flex align-items-center justify-content-center my-2 gap-3">
                                <div class="position-relative d-inline-flex align-items-center justify-content-center" style="width: 100px; height: 100px;">
                                    <svg width="100" height="100" viewBox="0 0 100 100" style="transform: rotate(-90deg);">
                                        <circle cx="50" cy="50" r="42" fill="none" stroke="#e2e8f0" stroke-width="8"></circle>
                                        <circle id="meter-circle" cx="50" cy="50" r="42" fill="none" stroke="#64748b" stroke-width="8" stroke-dasharray="263.89" stroke-dashoffset="263.89" stroke-linecap="round" style="transition: stroke-dashoffset 0.8s ease, stroke 0.4s ease;"></circle>
                                    </svg>
                                    <div class="position-absolute text-center">
                                        <div class="fs-3 fw-bold lh-1 text-dark" id="meter-score-text">--</div>
                                        <small class="text-muted" style="font-size: 0.65rem;">/ 100</small>
                                    </div>
                                </div>
                                <div class="text-start small">
                                    <div class="mb-1"><i class="fa-solid fa-shield text-primary me-1"></i><strong>20</strong> Rules Checked</div>
                                    <div class="mb-1"><span class="badge bg-danger rounded-pill me-1" id="meter-blocks-count">0</span> Blockers</div>
                                    <div><span class="badge bg-warning text-dark rounded-pill me-1" id="meter-warnings-count">0</span> Warnings</div>
                                </div>
                            </div>
                            <div class="small text-muted mt-1" id="meter-status-note">Run preflight check to calculate real-time deliverability score.</div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="d-grid gap-2 mb-3">
                            <button type="button" class="btn btn-primary btn-sm shadow-sm" id="btn-run-audit" data-campaign-id="<?php echo $campaign ? $campaign->campaign_id : 0; ?>">
                                <i class="fa-solid fa-stethoscope me-1"></i>Run Preflight Engine (D-001 - D-020)
                            </button>
                            <button type="button" class="btn btn-outline-dark btn-sm shadow-sm" id="btn-open-test-preview">
                                <i class="fa-solid fa-magnifying-glass-chart text-danger me-1"></i>1-Click Test Preview & Spam Scanner
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#testSendModal">
                                <i class="fa-solid fa-paper-plane me-1"></i>Quick Send Test Email
                            </button>
                        </div>

                        <!-- Audit Results Accordion / Breakdown -->
                        <div id="audit-results-card" style="display: none;" class="p-3 bg-light rounded border mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="fw-bold small mb-0" id="audit-status-heading">Preflight Status</h6>
                                <div class="btn-group btn-group-sm" role="group" id="pillar-filter-group">
                                    <button type="button" class="btn btn-outline-secondary py-0 px-2 active" data-pillar="all">All</button>
                                    <button type="button" class="btn btn-outline-secondary py-0 px-2" data-pillar="auth">Auth</button>
                                    <button type="button" class="btn btn-outline-secondary py-0 px-2" data-pillar="legal">Legal</button>
                                    <button type="button" class="btn btn-outline-secondary py-0 px-2" data-pillar="content">Content</button>
                                </div>
                            </div>
                            <div id="audit-checklist-items" class="small"></div>
                        </div>

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
                    <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center">
                        <span class="small fw-bold text-muted"><i class="fa-solid fa-eye me-1"></i>Mini Preview</span>
                        <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none small" id="btn-mini-preview-popout">Full Screen Preview</button>
                    </div>
                    <div class="card-body p-2 bg-light">
                        <iframe id="camp-preview-frame" style="width: 100%; height: 350px; border: 1px solid #cbd5e1; border-radius: 4px; background: #fff;" sandbox="allow-same-origin"></iframe>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<!-- Modal 1: AI Subject Line & Pre-Check Copilot -->
<div class="modal fade" id="aiSubjectModal" tabindex="-1" aria-labelledby="aiSubjectModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-light py-3 border-bottom">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded p-2 bg-primary-subtle text-primary">
                        <i class="fa-solid fa-wand-magic-sparkles"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold mb-0" id="aiSubjectModalLabel">AI Subject Line Copilot & Deliverability Pre-Check</h5>
                        <p class="text-muted small mb-0">Generates 5 high-converting, spam-free subject lines using multi-model intelligence.</p>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-3 mb-3">
                    <div class="col-md-7">
                        <label class="form-label small fw-semibold">Campaign Topic / Value Offer <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="ai_topic_input" placeholder="e.g. Q4 Modernization Release & Feature Walkthrough">
                    </div>
                    <div class="col-md-5">
                        <label class="form-label small fw-semibold">Desired Copywriting Tone</label>
                        <select class="form-select" id="ai_tone_select">
                            <option value="compelling">Compelling & Benefit-Driven (High Open Rate)</option>
                            <option value="urgent">Urgent & Timeline-Sensitive (Action Oriented)</option>
                            <option value="curiosity">Curiosity & Thought-Provoking (Engagement)</option>
                            <option value="b2b">Executive & B2B Briefing (Enterprise Professional)</option>
                        </select>
                    </div>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="small text-muted">
                        <i class="fa-solid fa-shield-check text-success me-1"></i>Enforces spam word filtering, character length limits (30-55 chars), and mobile inbox fit.
                    </div>
                    <button type="button" class="btn btn-primary px-3 shadow-sm" id="btn-generate-ai-subjects">
                        <i class="fa-solid fa-bolt me-1"></i>Generate AI Subjects
                    </button>
                </div>

                <!-- Suggestions Result Container -->
                <div id="ai-subject-results-container">
                    <div class="text-center py-4 text-muted border rounded bg-light" id="ai-subject-placeholder">
                        <i class="fa-solid fa-lightbulb fs-2 text-secondary mb-2"></i>
                        <p class="mb-0">Click <strong>Generate AI Subjects</strong> to craft 5 spam-tested subject line variants tailored to your campaign topic.</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light py-2">
                <span class="small text-muted me-auto" id="ai-provider-badge"><i class="fa-solid fa-robot me-1"></i>Powered by Universal AI Service (Gemini / Claude / GPT)</span>
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal 2: 1-Click Test Broadcast Preview with Automated Spam Trigger Word Highlighting -->
<div class="modal fade" id="testPreviewModal" tabindex="-1" aria-labelledby="testPreviewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-light py-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded p-2 bg-danger-subtle text-danger">
                        <i class="fa-solid fa-magnifying-glass-chart"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold mb-0" id="testPreviewModalLabel">1-Click Test Broadcast Preview & Spam Scanner</h5>
                        <p class="text-muted small mb-0">High-fidelity inbox simulator with automated spam trigger phrase detection.</p>
                    </div>
                </div>
                <!-- Device & Highlighter Controls -->
                <div class="d-flex align-items-center gap-3">
                    <div class="btn-group btn-group-sm" role="group">
                        <button type="button" class="btn btn-outline-secondary active" id="btn-view-desktop" title="Desktop View (Full Width)">
                            <i class="fa-solid fa-desktop me-1"></i>Desktop
                        </button>
                        <button type="button" class="btn btn-outline-secondary" id="btn-view-mobile" title="Mobile Inbox View (375px)">
                            <i class="fa-solid fa-mobile-screen-button me-1"></i>Mobile (375px)
                        </button>
                    </div>
                    <div class="form-check form-switch mb-0">
                        <input class="form-check-input" type="checkbox" id="toggle-spam-highlight" checked>
                        <label class="form-check-label small fw-semibold text-danger" for="toggle-spam-highlight">Highlight Spam Triggers</label>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
            </div>
            <div class="modal-body p-3 bg-light">
                <!-- Spam Scanner Intelligence Banner -->
                <div class="card border-0 shadow-sm mb-3" id="spam-scanner-summary-card">
                    <div class="card-body p-3">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge fs-6 px-3 py-2" id="spam-score-pill">Score: --</span>
                                <div>
                                    <h6 class="fw-bold mb-0" id="spam-status-headline">Evaluating content against 200+ known spam triggers...</h6>
                                    <small class="text-muted" id="spam-triggers-summary-text">Scan completed across subject line and rendered HTML alternative.</small>
                                </div>
                            </div>
                            <div class="d-flex align-items-center gap-1" id="spam-keywords-list"></div>
                        </div>
                    </div>
                </div>

                <!-- Inbox Header Card -->
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-body p-3">
                        <div class="row g-2 small">
                            <div class="col-md-6">
                                <span class="text-muted">From:</span>
                                <strong id="preview-from-header">Axiora Global &lt;broadcast@mail.axiora.com&gt;</strong>
                            </div>
                            <div class="col-md-6 text-md-end">
                                <span class="text-muted">To:</span>
                                <strong>Jane Doe &lt;jane.doe@example.com&gt;</strong> (Sample Recipient)
                            </div>
                            <div class="col-12 mt-2 pt-2 border-top">
                                <span class="text-muted me-1">Subject:</span>
                                <span class="fw-bold fs-6" id="preview-subject-header">--</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Simulated Email Display Container -->
                <div class="d-flex justify-content-center">
                    <div id="simulated-device-frame" style="width: 100%; transition: width 0.3s ease; background: #fff; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); overflow: hidden; min-height: 480px;">
                        <iframe id="test-preview-iframe" style="width: 100%; height: 480px; border: none; background: #fff;" sandbox="allow-same-origin"></iframe>
                    </div>
                </div>

                <!-- 1-Click Live Test Dispatcher Toolbar -->
                <div class="card border-0 shadow-sm mt-3 border-start border-3 border-success">
                    <div class="card-body p-3">
                        <div class="row g-2 align-items-center">
                            <div class="col-md-5">
                                <h6 class="fw-bold mb-1 text-success"><i class="fa-solid fa-paper-plane me-1"></i>Dispatch 1-Click Test Broadcast</h6>
                                <p class="text-muted small mb-0">Sends complete email with test tokens to inspect in your actual mail client.</p>
                            </div>
                            <div class="col-md-5">
                                <input type="email" class="form-control form-control-sm" id="quick_test_email" placeholder="recipient@example.com" value="<?php echo htmlspecialchars($_SESSION['user_email'] ?? 'admin@axiora.in'); ?>">
                            </div>
                            <div class="col-md-2 d-grid">
                                <button type="button" class="btn btn-success btn-sm shadow-sm" id="btn-quick-dispatch-test">
                                    <i class="fa-solid fa-paper-plane me-1"></i>Send Now
                                </button>
                            </div>
                            <div class="col-12" id="quick-test-feedback"></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close Preview</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Send Test Email (Direct Modal) -->
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
                <button type="button" class="btn btn-primary btn-sm" id="btn-dispatch-test" data-campaign-id="<?php echo $campaign ? $campaign->campaign_id : 0; ?>">
                    <i class="fa-solid fa-play me-1"></i>Send Test Email
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Forms: Launch and Approve Campaign Confirmation -->
<?php if ($campaign): ?>
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
    const subjectA = document.getElementById('subject_a');
    const subjectB = document.getElementById('subject_b');
    const subjectCharCount = document.getElementById('subject-char-count');
    const subjectSpamIndicator = document.getElementById('subject-spam-indicator');

    // Known spam phrases for fast client-side typing feedback
    const quickSpamKeywords = [
        '100% free', 'act now', 'buy direct', 'cheap', 'click here', 'click now',
        'earn extra cash', 'fast cash', 'free gift', 'free trial', 'guaranteed',
        'make money', 'no hidden costs', 'risk-free', 'winner', 'miracle', 'urgent'
    ];

    function evaluateSubjectA() {
        if (!subjectA) return;
        const text = subjectA.value.trim();
        const len = text.length;
        if (subjectCharCount) {
            subjectCharCount.textContent = len + ' / 55 chars';
            if (len >= 30 && len <= 55) {
                subjectCharCount.className = 'badge bg-success-subtle text-success small';
            } else if (len > 55) {
                subjectCharCount.className = 'badge bg-warning-subtle text-warning small';
            } else {
                subjectCharCount.className = 'badge bg-secondary-subtle text-secondary small';
            }
        }

        if (subjectSpamIndicator) {
            const lower = text.toLowerCase();
            let found = null;
            for (let kw of quickSpamKeywords) {
                if (lower.includes(kw)) {
                    found = kw;
                    break;
                }
            }
            if (found) {
                subjectSpamIndicator.innerHTML = '<span class="text-danger fw-semibold"><i class="fa-solid fa-triangle-exclamation me-1"></i>Spam trigger: "' + found + '"</span>';
            } else {
                subjectSpamIndicator.innerHTML = '';
            }
        }
    }

    if (subjectA) {
        subjectA.addEventListener('input', evaluateSubjectA);
        evaluateSubjectA();
    }

    function updateCampPreview() {
        if (!previewFrame || !htmlArea) return;
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

    // =========================================================================
    // SPRINT 4: AI SUBJECT LINE COPILOT (AiService::generate)
    // =========================================================================
    const btnGenerateAi = document.getElementById('btn-generate-ai-subjects');
    const aiTopicInput = document.getElementById('ai_topic_input');
    const aiToneSelect = document.getElementById('ai_tone_select');
    const aiResultsContainer = document.getElementById('ai-subject-results-container');
    const aiProviderBadge = document.getElementById('ai-provider-badge');

    // Auto-prefill topic when AI modal is opened
    const aiModalEl = document.getElementById('aiSubjectModal');
    if (aiModalEl) {
        aiModalEl.addEventListener('show.bs.modal', function() {
            if (aiTopicInput && !aiTopicInput.value) {
                const campNameInput = document.querySelector('input[name="name"]');
                const existingSub = subjectA ? subjectA.value.trim() : '';
                if (existingSub) {
                    aiTopicInput.value = existingSub;
                } else if (campNameInput && campNameInput.value) {
                    aiTopicInput.value = campNameInput.value.trim();
                }
            }
        });
    }

    if (btnGenerateAi) {
        btnGenerateAi.addEventListener('click', function() {
            const topic = (aiTopicInput ? aiTopicInput.value.trim() : '') || 'Product Update Announcement';
            const tone = aiToneSelect ? aiToneSelect.value : 'compelling';
            const intentEl = document.getElementById('communication_intent');
            const intent = intentEl ? intentEl.value : 'promotional';

            btnGenerateAi.disabled = true;
            btnGenerateAi.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i>Generating...';
            aiResultsContainer.innerHTML = '<div class="text-center py-4"><i class="fa-solid fa-circle-notch fa-spin fs-3 text-primary mb-2"></i><div class="text-muted small">Generating 5 spam-tested subject lines with deliverability rationale...</div></div>';

            fetch('index.php?route=email_campaigns/aiSubjectLines', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ topic: topic, tone: tone, intent: intent })
            })
            .then(res => res.json())
            .then(data => {
                btnGenerateAi.disabled = false;
                btnGenerateAi.innerHTML = '<i class="fa-solid fa-bolt me-1"></i>Generate AI Subjects';

                if (!data.success || !data.suggestions || data.suggestions.length === 0) {
                    aiResultsContainer.innerHTML = '<div class="alert alert-danger py-2">' + (data.error || 'Failed to generate suggestions.') + '</div>';
                    return;
                }

                if (aiProviderBadge) {
                    aiProviderBadge.innerHTML = '<i class="fa-solid fa-microchip me-1"></i>Engine: ' + (data.provider || 'AI Copilot') + ' (' + data.suggestions.length + ' variants)';
                }

                let html = '<div class="list-group gap-2">';
                data.suggestions.forEach((item, idx) => {
                    html += `
                    <div class="list-group-item list-group-item-action border rounded-3 p-3 position-relative">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle small">${item.angle || 'Variant ' + (idx + 1)}</span>
                            <span class="badge bg-success-subtle text-success small"><i class="fa-solid fa-gauge-high me-1"></i>${item.predicted_strength || 'High Strength'}</span>
                        </div>
                        <h6 class="fw-bold mb-1 text-dark" id="sugg-sub-${idx}">${escapeHtml(item.subject)}</h6>
                        <p class="text-muted small mb-2" id="sugg-pre-${idx}"><span class="fw-semibold">Preview Text:</span> ${escapeHtml(item.preview || 'Open to review latest announcements.')}</p>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-sm btn-outline-primary py-1 btn-apply-sub-a" data-idx="${idx}">
                                <i class="fa-solid fa-check me-1"></i>Use as Subject A
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-warning py-1 btn-apply-sub-b" data-idx="${idx}">
                                <i class="fa-solid fa-flask me-1"></i>Use for A/B Test (B)
                            </button>
                        </div>
                    </div>`;
                });
                html += '</div>';
                aiResultsContainer.innerHTML = html;

                // Wire Apply Buttons
                document.querySelectorAll('.btn-apply-sub-a').forEach(btn => {
                    btn.addEventListener('click', function() {
                        const idx = this.getAttribute('data-idx');
                        const sub = document.getElementById('sugg-sub-' + idx).textContent;
                        const pre = document.getElementById('sugg-pre-' + idx).textContent.replace(/^Preview Text:\s*/, '');
                        if (subjectA) {
                            subjectA.value = sub;
                            evaluateSubjectA();
                        }
                        const preInput = document.querySelector('input[name="preheader"]');
                        if (preInput) preInput.value = pre;

                        // Close modal
                        const modalInstance = bootstrap.Modal.getInstance(aiModalEl);
                        if (modalInstance) modalInstance.hide();
                    });
                });

                document.querySelectorAll('.btn-apply-sub-b').forEach(btn => {
                    btn.addEventListener('click', function() {
                        const idx = this.getAttribute('data-idx');
                        const sub = document.getElementById('sugg-sub-' + idx).textContent;
                        if (abToggle && !abToggle.checked) {
                            abToggle.checked = true;
                            if (abContainer) abContainer.style.display = 'block';
                        }
                        if (subjectB) {
                            subjectB.value = sub;
                        }
                        const modalInstance = bootstrap.Modal.getInstance(aiModalEl);
                        if (modalInstance) modalInstance.hide();
                    });
                });
            })
            .catch(() => {
                btnGenerateAi.disabled = false;
                btnGenerateAi.innerHTML = '<i class="fa-solid fa-bolt me-1"></i>Generate AI Subjects';
                aiResultsContainer.innerHTML = '<div class="alert alert-danger py-2">Error connecting to AI Copilot service.</div>';
            });
        });
    }

    // =========================================================================
    // SPRINT 4: VISUAL DELIVERABILITY HEALTH METER (0–100 GAUGE)
    // =========================================================================
    const btnRunAudit = document.getElementById('btn-run-audit');
    const meterCircle = document.getElementById('meter-circle');
    const meterScoreText = document.getElementById('meter-score-text');
    const healthMeterBadge = document.getElementById('health-meter-badge');
    const meterBlocksCount = document.getElementById('meter-blocks-count');
    const meterWarningsCount = document.getElementById('meter-warnings-count');
    const meterStatusNote = document.getElementById('meter-status-note');
    let currentFindings = [];

    function updateDeliverabilityMeter(score, blocks, warnings, status) {
        if (!meterCircle || !meterScoreText) return;
        const boundedScore = Math.max(0, Math.min(100, Math.round(score)));
        meterScoreText.textContent = boundedScore;
        const circumference = 263.89; // 2 * PI * 42
        const offset = circumference * (1 - (boundedScore / 100));
        meterCircle.style.strokeDashoffset = offset;

        if (boundedScore >= 80) {
            meterCircle.style.stroke = '#10b981'; // Green
            if (healthMeterBadge) {
                healthMeterBadge.className = 'badge bg-success';
                healthMeterBadge.textContent = 'Optimal / Ready';
            }
            if (meterStatusNote) meterStatusNote.innerHTML = '<span class="text-success"><i class="fa-solid fa-circle-check me-1"></i>Deliverability is excellent. Campaign meets compliance standards.</span>';
        } else if (boundedScore >= 60) {
            meterCircle.style.stroke = '#f59e0b'; // Amber
            if (healthMeterBadge) {
                healthMeterBadge.className = 'badge bg-warning text-dark';
                healthMeterBadge.textContent = 'Needs Attention';
            }
            if (meterStatusNote) meterStatusNote.innerHTML = '<span class="text-warning-emphasis"><i class="fa-solid fa-triangle-exclamation me-1"></i>Actionable warnings detected. Review findings below.</span>';
        } else {
            meterCircle.style.stroke = '#ef4444'; // Red
            if (healthMeterBadge) {
                healthMeterBadge.className = 'badge bg-danger';
                healthMeterBadge.textContent = 'Launch Blocked';
            }
            if (meterStatusNote) meterStatusNote.innerHTML = '<span class="text-danger"><i class="fa-solid fa-circle-xmark me-1"></i>Critical blockers identified. You must resolve blockers before sending.</span>';
        }

        if (meterBlocksCount) meterBlocksCount.textContent = blocks;
        if (meterWarningsCount) meterWarningsCount.textContent = warnings;
    }

    if (btnRunAudit) {
        btnRunAudit.addEventListener('click', function() {
            const cid = this.getAttribute('data-campaign-id');
            const card = document.getElementById('audit-results-card');
            const heading = document.getElementById('audit-status-heading');
            const items = document.getElementById('audit-checklist-items');

            card.style.display = 'block';
            items.innerHTML = '<div class="text-center py-2"><i class="fa-solid fa-spinner fa-spin me-1"></i>Evaluating 20 deliverability policies...</div>';

            // Gather current form values for real-time audit
            const formEl = document.getElementById('campaign-form');
            const fd = new FormData(formEl);

            fetch('index.php?route=email_campaigns/preflightAjax', {
                method: 'POST',
                body: fd
            })
            .then(res => res.json())
            .then(data => {
                const score = (typeof data.score === 'number') ? data.score : 85;
                const summary = data.summary || { block: 0, high: 0, warning: 0, info: 0 };
                updateDeliverabilityMeter(score, summary.block || 0, (summary.high || 0) + (summary.warning || 0), data.status);

                currentFindings = data.findings || [];
                renderPillarFindings('all');

                if (data.status === 'ready') {
                    heading.innerHTML = '<span class="text-success"><i class="fa-solid fa-circle-check me-1"></i>Preflight Ready (Score: ' + score + '/100)</span>';
                } else if (data.status === 'blocked') {
                    heading.innerHTML = '<span class="text-danger"><i class="fa-solid fa-triangle-exclamation me-1"></i>Blocked: ' + summary.block + ' Blocker(s)</span>';
                } else {
                    heading.innerHTML = '<span class="text-warning"><i class="fa-solid fa-triangle-exclamation me-1"></i>Review Needed: ' + summary.high + ' Warning(s)</span>';
                }
            })
            .catch(() => {
                items.innerHTML = '<div class="text-danger">Failed to connect to preflight endpoint.</div>';
            });
        });
    }

    // Pillar Filter clicks
    document.querySelectorAll('#pillar-filter-group button').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('#pillar-filter-group button').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            const pillar = this.getAttribute('data-pillar');
            renderPillarFindings(pillar);
        });
    });

    const rulePillarMap = {
        'D-001': 'auth', 'D-002': 'auth', 'D-016': 'auth', 'D-020': 'auth',
        'D-003': 'legal', 'D-004': 'legal', 'D-005': 'legal', 'D-006': 'legal',
        'D-007': 'content', 'D-008': 'content', 'D-009': 'content', 'D-010': 'content',
        'D-013': 'content', 'D-014': 'content', 'D-015': 'content',
        'D-011': 'delivery', 'D-012': 'delivery', 'D-017': 'delivery', 'D-018': 'delivery', 'D-019': 'delivery'
    };

    function renderPillarFindings(pillar) {
        const items = document.getElementById('audit-checklist-items');
        if (!items) return;

        let filtered = currentFindings;
        if (pillar !== 'all') {
            filtered = currentFindings.filter(f => (rulePillarMap[f.rule_id] || 'content') === pillar);
        }

        if (filtered.length === 0) {
            items.innerHTML = '<div class="alert alert-success py-2 mb-0 mt-2 small"><i class="fa-solid fa-circle-check me-1"></i>All evaluated rules in this category are fully compliant.</div>';
            return;
        }

        let html = '<div class="list-group list-group-flush mt-2">';
        filtered.forEach(f => {
            const badgeClass = f.severity === 'BLOCK' ? 'bg-danger' : (f.severity === 'HIGH' ? 'bg-warning text-dark' : 'bg-info text-dark');
            html += `
            <div class="list-group-item px-0 py-2">
                <div class="d-flex align-items-center gap-1 mb-1">
                    <span class="badge ${badgeClass} small">${f.severity}</span>
                    <code class="text-primary fw-bold">${escapeHtml(f.rule_id)}</code>
                    <span class="small fw-semibold text-dark">${escapeHtml(f.message)}</span>
                </div>
                <div class="text-muted small ps-3 border-start border-2 border-secondary-subtle">
                    <span class="fw-semibold">Fix:</span> ${escapeHtml(f.remediation || 'Review campaign settings.')}
                </div>
            </div>`;
        });
        html += '</div>';
        items.innerHTML = html;
    }

    // =========================================================================
    // SPRINT 4: 1-CLICK TEST BROADCAST PREVIEW & SPAM SCANNER
    // =========================================================================
    const btnOpenTestPreview = document.getElementById('btn-open-test-preview');
    const testPreviewModalEl = document.getElementById('testPreviewModal');
    const testPreviewIframe = document.getElementById('test-preview-iframe');
    const simulatedDeviceFrame = document.getElementById('simulated-device-frame');
    const toggleSpamHighlight = document.getElementById('toggle-spam-highlight');
    const spamScorePill = document.getElementById('spam-score-pill');
    const spamStatusHeadline = document.getElementById('spam-status-headline');
    const spamTriggersSummary = document.getElementById('spam-triggers-summary-text');
    const spamKeywordsList = document.getElementById('spam-keywords-list');
    const previewSubjectHeader = document.getElementById('preview-subject-header');
    const previewFromHeader = document.getElementById('preview-from-header');
    const btnViewDesktop = document.getElementById('btn-view-desktop');
    const btnViewMobile = document.getElementById('btn-view-mobile');
    const btnQuickDispatch = document.getElementById('btn-quick-dispatch-test');
    const quickTestFeedback = document.getElementById('quick-test-feedback');

    let cachedPreviewData = null;

    if (btnOpenTestPreview && testPreviewModalEl) {
        btnOpenTestPreview.addEventListener('click', function() {
            const modal = new bootstrap.Modal(testPreviewModalEl);
            modal.show();

            const formEl = document.getElementById('campaign-form');
            const fd = new FormData(formEl);

            spamStatusHeadline.textContent = 'Scanning broadcast content for deliverability and spam triggers...';
            spamScorePill.className = 'badge bg-secondary';
            spamScorePill.textContent = 'Scanning...';
            spamKeywordsList.innerHTML = '';

            fetch('index.php?route=email_campaigns/testPreview', {
                method: 'POST',
                body: fd
            })
            .then(res => res.json())
            .then(data => {
                if (!data.success) {
                    spamStatusHeadline.textContent = 'Error: ' + (data.error || 'Failed to scan preview');
                    return;
                }
                cachedPreviewData = data;
                renderTestPreviewModal(data);
            })
            .catch(() => {
                spamStatusHeadline.textContent = 'Failed to connect to preview scanner endpoint.';
            });
        });
    }

    function renderTestPreviewModal(data) {
        if (!data) return;
        const score = parseFloat(data.spam_score) || 0.0;
        const triggerCount = data.trigger_count || 0;

        if (score >= 4.0 || triggerCount > 2) {
            spamScorePill.className = 'badge bg-danger fs-6 px-3 py-2';
            spamScorePill.textContent = 'Spam Score: ' + score.toFixed(1) + ' / 10 (Elevated)';
            spamStatusHeadline.textContent = '⚠️ Elevated Spam Risk: ' + triggerCount + ' spam triggers detected in copy.';
        } else if (score > 0 || triggerCount > 0) {
            spamScorePill.className = 'badge bg-warning text-dark fs-6 px-3 py-2';
            spamScorePill.textContent = 'Spam Score: ' + score.toFixed(1) + ' / 10 (Moderate)';
            spamStatusHeadline.textContent = 'Notice: ' + triggerCount + ' potential trigger word(s) identified.';
        } else {
            spamScorePill.className = 'badge bg-success fs-6 px-3 py-2';
            spamScorePill.textContent = 'Spam Score: 0.0 / 10 (Clean)';
            spamStatusHeadline.textContent = '✓ Deliverability Clean: Zero high-risk spam keywords detected.';
        }

        if (previewFromHeader) {
            previewFromHeader.textContent = (data.from_name || 'Axiora Global') + ' <' + (data.from_email || 'broadcast@mail.axiora.com') + '>';
        }

        // Render Trigger Badges
        if (spamKeywordsList) {
            if (data.spam_triggers && data.spam_triggers.length > 0) {
                let badgeHtml = '';
                data.spam_triggers.slice(0, 6).forEach(kw => {
                    badgeHtml += '<span class="badge bg-danger-subtle text-danger border border-danger-subtle small me-1">"' + escapeHtml(kw) + '"</span>';
                });
                spamKeywordsList.innerHTML = badgeHtml;
            } else {
                spamKeywordsList.innerHTML = '<span class="badge bg-success-subtle text-success small"><i class="fa-solid fa-shield-check me-1"></i>Spam-Free</span>';
            }
        }

        updateIframePreviewContent();
    }

    function updateIframePreviewContent() {
        if (!cachedPreviewData || !testPreviewIframe) return;
        const isHighlight = toggleSpamHighlight && toggleSpamHighlight.checked;
        const htmlToRender = isHighlight ? cachedPreviewData.highlighted_html : cachedPreviewData.rendered_html;
        const subjectToRender = isHighlight ? cachedPreviewData.highlighted_subject : cachedPreviewData.subject;

        if (previewSubjectHeader) {
            previewSubjectHeader.innerHTML = subjectToRender || '(No Subject Line)';
        }

        const doc = testPreviewIframe.contentDocument || testPreviewIframe.contentWindow.document;
        doc.open();
        doc.write(htmlToRender);
        doc.close();
    }

    if (toggleSpamHighlight) {
        toggleSpamHighlight.addEventListener('change', updateIframePreviewContent);
    }

    if (btnViewDesktop && btnViewMobile && simulatedDeviceFrame) {
        btnViewDesktop.addEventListener('click', function() {
            btnViewDesktop.classList.add('active');
            btnViewMobile.classList.remove('active');
            simulatedDeviceFrame.style.width = '100%';
        });
        btnViewMobile.addEventListener('click', function() {
            btnViewMobile.classList.add('active');
            btnViewDesktop.classList.remove('active');
            simulatedDeviceFrame.style.width = '375px';
        });
    }

    // 1-Click Quick Test Send
    if (btnQuickDispatch) {
        btnQuickDispatch.addEventListener('click', function() {
            const emailInput = document.getElementById('quick_test_email');
            const email = emailInput ? emailInput.value.trim() : '';
            if (!email) {
                alert('Please provide a valid test recipient email address.');
                return;
            }

            btnQuickDispatch.disabled = true;
            btnQuickDispatch.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i>Sending...';
            quickTestFeedback.innerHTML = '';

            const fd = new FormData(document.getElementById('campaign-form'));
            fd.append('test_email', email);

            fetch('index.php?route=email_campaigns/campaignTestSend', {
                method: 'POST',
                body: fd
            })
            .then(res => res.json())
            .then(data => {
                btnQuickDispatch.disabled = false;
                btnQuickDispatch.innerHTML = '<i class="fa-solid fa-paper-plane me-1"></i>Send Now';
                if (data.success) {
                    quickTestFeedback.innerHTML = '<div class="alert alert-success py-2 mb-0 mt-2 small"><i class="fa-solid fa-circle-check me-1"></i>' + data.message + '</div>';
                } else {
                    quickTestFeedback.innerHTML = '<div class="alert alert-danger py-2 mb-0 mt-2 small"><i class="fa-solid fa-triangle-exclamation me-1"></i>' + (data.error || 'Failed to dispatch test send.') + '</div>';
                }
            })
            .catch(() => {
                btnQuickDispatch.disabled = false;
                btnQuickDispatch.innerHTML = '<i class="fa-solid fa-paper-plane me-1"></i>Send Now';
                quickTestFeedback.innerHTML = '<div class="alert alert-danger py-2 mb-0 mt-2 small">Error dispatching test send.</div>';
            });
        });
    }

    // Direct modal dispatch test
    const btnDispatchTest = document.getElementById('btn-dispatch-test');
    if (btnDispatchTest) {
        btnDispatchTest.addEventListener('click', function() {
            const cid = this.getAttribute('data-campaign-id');
            const email = document.getElementById('modal_test_email').value;
            const fb = document.getElementById('test-send-feedback');

            fb.innerHTML = '<div class="text-primary"><i class="fa-solid fa-spinner fa-spin me-1"></i>Sending test email to ' + email + '...</div>';
            this.disabled = true;

            const fd = new FormData(document.getElementById('campaign-form'));
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

    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }
});
</script>

