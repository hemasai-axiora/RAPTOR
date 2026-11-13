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
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ($csrf_token ?? ''); ?>">
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
                                <input type="email" name="from_email" id="from_email" class="form-control font-monospace" placeholder="contacts@axioraglobalsolutions.com" value="<?php echo htmlspecialchars((!empty($campaign->from_email) && $campaign->from_email !== 'noreply@axiora.in') ? $campaign->from_email : 'contacts@axioraglobalsolutions.com'); ?>" required>
                                <div class="form-text small"><i class="fa-solid fa-circle-check text-success me-1"></i>Verified sender: <code>contacts@axioraglobalsolutions.com</code></div>
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
                                    <option value="-1" <?php echo (($campaign->list_id ?? 0) == -1) ? 'selected' : ''; ?>>★ All Active Subscribers (Broadcast to All Contacts)</option>
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
                <div class="card border-0 shadow-sm mb-4" id="email-editor-card">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <h6 class="fw-bold mb-0 text-primary"><i class="fa-solid fa-envelope-open-text me-2"></i>4. Email Content & Design</h6>
                        <div class="d-flex align-items-center gap-2">
                            <!-- Editor mode tabs in header -->
                            <ul class="nav nav-pills nav-sm mb-0" id="editor-mode-tabs" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link active py-1 px-3 small fw-bold" id="tab-visual-btn" data-bs-toggle="pill" data-bs-target="#tab-visual" type="button" role="tab" aria-selected="true">
                                        <i class="fa-solid fa-wand-magic-sparkles me-1"></i>Visual
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link py-1 px-3 small fw-bold" id="c-html-tab" data-bs-toggle="pill" data-bs-target="#tab-c-html" type="button" role="tab" aria-selected="false">
                                        <i class="fa-solid fa-code me-1"></i>HTML Source
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link py-1 px-3 small fw-bold" id="c-plain-tab" data-bs-toggle="pill" data-bs-target="#tab-c-plain" type="button" role="tab" aria-selected="false">
                                        <i class="fa-solid fa-align-left me-1"></i>Plain Text
                                    </button>
                                </li>
                            </ul>
                            <!-- Load Template dropdown -->
                            <div class="dropdown">
                                <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                    <i class="fa-solid fa-file-import me-1"></i>Load Template
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
                    </div>
                    <div class="card-body p-0">
                        <!-- Merge tags picker bar -->
                        <div class="px-3 pt-3 pb-2 border-bottom bg-light d-flex flex-wrap align-items-center gap-1">
                            <span class="small fw-bold text-muted me-2"><i class="fa-solid fa-tags me-1"></i>Insert merge tag:</span>
                            <?php foreach ($merge_tags as $tag => $label): ?>
                                <button type="button" class="btn btn-xs btn-outline-secondary py-1 px-2 btn-insert-camp-tag" data-tag="<?php echo htmlspecialchars($tag); ?>" title="<?php echo htmlspecialchars($label); ?>" style="font-size: 0.75rem;">
                                    <code><?php echo htmlspecialchars($tag); ?></code>
                                </button>
                            <?php endforeach; ?>
                        </div>

                        <!-- Tab Panes -->
                        <div class="tab-content">

                            <!-- ===== TAB: VISUAL EDITOR ===== -->
                            <div class="tab-pane fade show active" id="tab-visual" role="tabpanel">
                                <div class="d-flex" style="height:540px;">

                                    <!-- Block Palette (left) -->
                                    <div id="block-palette" style="width:170px;min-width:170px;border-right:1px solid #e2e8f0;overflow-y:auto;padding:10px;background:#f8fafc;">
                                        <div class="text-muted small fw-bold mb-2 text-uppercase" style="font-size:0.68rem;letter-spacing:.05em;">Blocks</div>
                                        <div class="d-grid gap-1">
                                            <button type="button" class="btn btn-outline-secondary btn-sm text-start py-1 px-2 ve-add-block" data-block-type="header" style="font-size:0.78rem;">
                                                <i class="fa-solid fa-heading me-1 text-primary"></i>Header
                                            </button>
                                            <button type="button" class="btn btn-outline-secondary btn-sm text-start py-1 px-2 ve-add-block" data-block-type="hero" style="font-size:0.78rem;">
                                                <i class="fa-solid fa-star me-1 text-warning"></i>Hero Text
                                            </button>
                                            <button type="button" class="btn btn-outline-secondary btn-sm text-start py-1 px-2 ve-add-block" data-block-type="text" style="font-size:0.78rem;">
                                                <i class="fa-solid fa-paragraph me-1 text-secondary"></i>Text Block
                                            </button>
                                            <button type="button" class="btn btn-outline-secondary btn-sm text-start py-1 px-2 ve-add-block" data-block-type="image" style="font-size:0.78rem;">
                                                <i class="fa-solid fa-image me-1 text-success"></i>Image
                                            </button>
                                            <button type="button" class="btn btn-outline-secondary btn-sm text-start py-1 px-2 ve-add-block" data-block-type="button" style="font-size:0.78rem;">
                                                <i class="fa-solid fa-hand-pointer me-1 text-info"></i>Button / CTA
                                            </button>
                                            <button type="button" class="btn btn-outline-secondary btn-sm text-start py-1 px-2 ve-add-block" data-block-type="divider" style="font-size:0.78rem;">
                                                <i class="fa-solid fa-minus me-1 text-secondary"></i>Divider
                                            </button>
                                            <button type="button" class="btn btn-outline-secondary btn-sm text-start py-1 px-2 ve-add-block" data-block-type="columns" style="font-size:0.78rem;">
                                                <i class="fa-solid fa-table-columns me-1 text-purple"></i>2-Column
                                            </button>
                                            <button type="button" class="btn btn-outline-secondary btn-sm text-start py-1 px-2 ve-add-block" data-block-type="footer" style="font-size:0.78rem;">
                                                <i class="fa-solid fa-link-slash me-1 text-danger"></i>Unsub Footer
                                            </button>
                                        </div>
                                        <hr class="my-2">
                                        <div class="text-muted small fw-bold mb-1 text-uppercase" style="font-size:0.68rem;letter-spacing:.05em;">Actions</div>
                                        <button type="button" class="btn btn-outline-danger btn-sm w-100 py-1" id="ve-clear-canvas" style="font-size:0.75rem;">
                                            <i class="fa-solid fa-trash-can me-1"></i>Clear All
                                        </button>
                                    </div>

                                    <!-- Canvas (right) -->
                                    <div id="email-canvas" style="flex:1;overflow-y:auto;padding:16px;background:#f0f4f8;">
                                        <div id="canvas-empty-hint" class="text-center text-muted py-5" style="pointer-events:none;">
                                            <i class="fa-solid fa-wand-magic-sparkles fa-2x mb-3 opacity-25"></i>
                                            <div class="small">Click a block on the left to add it to your email canvas.</div>
                                        </div>
                                        <div id="canvas-blocks"></div>
                                    </div>
                                </div>
                            </div>

                            <!-- ===== TAB: HTML SOURCE ===== -->
                            <div class="tab-pane fade" id="tab-c-html" role="tabpanel">
                                <div class="p-3">
                                    <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                                        <span class="small fw-bold text-muted"><i class="fa-solid fa-code me-1"></i>Email HTML Source</span>
                                        <button type="button" class="btn btn-outline-success btn-sm shadow-sm" id="btn-convert-raw-to-html">
                                            <i class="fa-solid fa-wand-magic-sparkles me-1"></i>Convert Text / Draft to Clean Email HTML
                                        </button>
                                    </div>
                                    <textarea name="html_content" id="camp_html" rows="20" class="form-control font-monospace small" required><?php echo htmlspecialchars($campaign->html_content ?? CampaignTemplate::getPrebuiltTemplateAnnouncement()); ?></textarea>
                                    <div class="form-text small mt-1 text-muted"><i class="fa-solid fa-circle-info me-1"></i>Paste plain text or raw HTML above and click <strong>Convert Text to Clean Email HTML</strong> to auto-wrap it into a responsive, mobile-optimized template with RFC 8058 unsubscribe tags.</div>
                                </div>
                            </div>

                            <!-- ===== TAB: PLAIN TEXT ===== -->
                            <div class="tab-pane fade" id="tab-c-plain" role="tabpanel">
                                <div class="p-3">
                                    <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                                        <span class="small fw-bold text-muted"><i class="fa-solid fa-align-left me-1"></i>Plain Text Alternative</span>
                                        <div class="d-flex gap-2">
                                            <button type="button" class="btn btn-outline-success btn-sm shadow-sm" id="btn-convert-plain-to-html">
                                                <i class="fa-solid fa-wand-magic-sparkles me-1"></i>Convert Plain Text to Email HTML
                                            </button>
                                            <button type="button" class="btn btn-outline-primary btn-sm" id="btn-sync-camp-plain">
                                                <i class="fa-solid fa-arrows-rotate me-1"></i>Auto-Sync from HTML
                                            </button>
                                        </div>
                                    </div>
                                    <textarea name="plain_text_content" id="camp_plain" rows="20" class="form-control font-monospace small"><?php echo htmlspecialchars($campaign->plain_text_content ?? ''); ?></textarea>
                                    <div class="form-text small mt-1 text-muted"><i class="fa-solid fa-circle-info me-1"></i>Draft your email text here and click <strong>Convert Plain Text to Email HTML</strong> to generate professional email markup automatically.</div>
                                </div>
                            </div>

                        </div><!-- /.tab-content -->
                    </div><!-- /.card-body -->
                </div><!-- /#email-editor-card -->
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

                            <?php if ($campaign && in_array($campaign->status, ['queued', 'sending'])): ?>
                                <button type="button" class="btn btn-primary shadow-sm" onclick="document.getElementById('form-process-queue').submit();">
                                    <i class="fa-solid fa-bolt me-2"></i>Process Queue Now
                                </button>
                                <button type="button" class="btn btn-warning shadow-sm" onclick="if(confirm('Pause this campaign?')) document.getElementById('form-pause-campaign').submit();">
                                    <i class="fa-solid fa-pause me-2"></i>Pause Campaign
                                </button>
                            <?php endif; ?>

                            <?php if ($campaign && $campaign->status === 'paused'): ?>
                                <button type="button" class="btn btn-info text-white shadow-sm" onclick="document.getElementById('form-resume-campaign').submit();">
                                    <i class="fa-solid fa-play me-2"></i>Resume Campaign
                                </button>
                            <?php endif; ?>

                            <?php if ($campaign && in_array($campaign->status, ['queued', 'sending', 'paused'])): ?>
                                <button type="button" class="btn btn-outline-danger btn-sm shadow-sm" onclick="if(confirm('Cancel campaign and discard remaining queue?')) document.getElementById('form-cancel-campaign').submit();">
                                    <i class="fa-solid fa-xmark me-2"></i>Cancel Campaign
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

<?php if ($campaign): ?>
<form id="form-process-queue" method="POST" action="index.php?route=email_campaigns/queueProcess" style="display:none;">
    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ($csrf_token ?? ''); ?>">
    <input type="hidden" name="campaign_id" value="<?php echo $campaign->campaign_id; ?>">
</form>
<form id="form-pause-campaign" method="POST" action="index.php?route=email_campaigns/campaignPause" style="display:none;">
    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ($csrf_token ?? ''); ?>">
    <input type="hidden" name="campaign_id" value="<?php echo $campaign->campaign_id; ?>">
</form>
<form id="form-resume-campaign" method="POST" action="index.php?route=email_campaigns/campaignResume" style="display:none;">
    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ($csrf_token ?? ''); ?>">
    <input type="hidden" name="campaign_id" value="<?php echo $campaign->campaign_id; ?>">
</form>
<form id="form-cancel-campaign" method="POST" action="index.php?route=email_campaigns/campaignCancel" style="display:none;">
    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ($csrf_token ?? ''); ?>">
    <input type="hidden" name="campaign_id" value="<?php echo $campaign->campaign_id; ?>">
</form>
<?php endif; ?>

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
    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ($csrf_token ?? ''); ?>">
    <input type="hidden" name="campaign_id" value="<?php echo $campaign->campaign_id; ?>">
</form>
<form method="POST" action="index.php?route=email_campaigns/campaignApprove" id="approve-form">
    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ($csrf_token ?? ''); ?>">
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

    // Convert Plain Text / Draft into Clean, Responsive Email HTML
    function convertRawTextToEmailHtml(rawText) {
        if (!rawText || !rawText.trim()) return '';

        const trimmed = rawText.trim();
        const hasFullTable = /<table[^>]*role=["']presentation["']/i.test(trimmed);

        if (hasFullTable) {
            let out = trimmed;
            if (!/\{\{\s*unsubscribe_url\s*\}\}/i.test(out)) {
                const footer = '\n\n<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top:20px;border-top:1px solid #e2e8f0;padding-top:16px;text-align:center;font-size:12px;color:#64748b;">' +
                    '<tr><td>{{company_address}}</td></tr>' +
                    '<tr><td style="padding-top:6px;"><a href="{{unsubscribe_url}}" style="color:#475569;text-decoration:underline;">Unsubscribe</a></td></tr>' +
                    '</table>';
                if (/<\/body>/i.test(out)) {
                    out = out.replace(/<\/body>/i, footer + '</body>');
                } else {
                    out += footer;
                }
            }
            return out;
        }

        const lines = trimmed.split(/\r?\n/);
        let htmlLines = [];
        let inList = false;

        for (let i = 0; i < lines.length; i++) {
            let line = lines[i].trim();
            if (!line) {
                if (inList) {
                    htmlLines.push('</ul>');
                    inList = false;
                }
                continue;
            }

            // Detect Bullet points
            if (/^[•\-\*\+]\s+/.test(line)) {
                if (!inList) {
                    htmlLines.push('<ul style="margin:8px 0 16px 20px;padding:0;color:#334155;font-size:15px;line-height:1.6;">');
                    inList = true;
                }
                let itemText = line.replace(/^[•\-\*\+]\s+/, '');
                itemText = itemText.replace(/(https?:\/\/[^\s]+)/g, '<a href="$1" style="color:#2563eb;text-decoration:underline;">$1</a>');
                htmlLines.push('<li style="margin-bottom:6px;">' + itemText + '</li>');
                continue;
            } else if (inList) {
                htmlLines.push('</ul>');
                inList = false;
            }

            // Detect Headings (# or <title> or ALL-CAPS short line)
            if (/^#+\s+/.test(line)) {
                let hText = line.replace(/^#+\s+/, '');
                htmlLines.push('<h2 style="color:#0f172a;font-size:20px;font-weight:700;margin:24px 0 12px 0;line-height:1.3;">' + hText + '</h2>');
                continue;
            }

            // Linkify URLs
            line = line.replace(/(https?:\/\/[^\s]+)/g, '<a href="$1" style="color:#2563eb;font-weight:600;text-decoration:underline;">$1</a>');

            // Regular paragraph
            htmlLines.push('<p style="margin:0 0 14px 0;color:#334155;font-size:15px;line-height:1.6;">' + line + '</p>');
        }

        if (inList) {
            htmlLines.push('</ul>');
        }

        const bodyContent = htmlLines.join('\n');

        return `<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Broadcast</title>
</head>
<body style="margin:0;padding:0;background-color:#f1f5f9;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f1f5f9;padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;background-color:#ffffff;border-radius:8px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,0.08);">
                    <!-- Brand Header -->
                    <tr>
                        <td style="padding:24px 32px 16px;border-bottom:1px solid #f1f5f9;">
                            <span style="font-size:18px;font-weight:700;color:#0f172a;letter-spacing:-0.02em;">Axiora Global</span>
                        </td>
                    </tr>
                    <!-- Main Body -->
                    <tr>
                        <td style="padding:28px 32px;">
${bodyContent}
                        </td>
                    </tr>
                    <!-- Compliance Footer -->
                    <tr>
                        <td style="padding:20px 32px;background-color:#f8fafc;border-top:1px solid #e2e8f0;text-align:center;font-size:12px;color:#64748b;line-height:1.5;">
                            <div>{{company_address}}</div>
                            <div style="margin-top:8px;">
                                You received this email because you are a registered subscriber.
                                <br>
                                <a href="{{unsubscribe_url}}" style="color:#475569;text-decoration:underline;">Unsubscribe</a>
                            </div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>`;
    }

    const btnConvertRaw = document.getElementById('btn-convert-raw-to-html');
    if (btnConvertRaw) {
        btnConvertRaw.addEventListener('click', function() {
            const raw = htmlArea.value.trim();
            if (!raw) {
                alert('Please enter or paste some text or email draft into the HTML Source area first.');
                return;
            }
            const formatted = convertRawTextToEmailHtml(raw);
            htmlArea.value = formatted;
            updateCampPreview();
            if (typeof veParseHtmlToBlocks === 'function') {
                const parsed = veParseHtmlToBlocks(formatted);
                if (parsed && parsed.length && window.emailBlocks) {
                    window.emailBlocks = parsed;
                    if (typeof veRenderCanvas === 'function') veRenderCanvas();
                }
            }
            alert('Text converted into clean, responsive email HTML with compliant unsubscribe tags!');
        });
    }

    const btnConvertPlain = document.getElementById('btn-convert-plain-to-html');
    if (btnConvertPlain) {
        btnConvertPlain.addEventListener('click', function() {
            const raw = plainArea.value.trim();
            if (!raw) {
                alert('Please enter or paste text into the Plain Text area first.');
                return;
            }
            const formatted = convertRawTextToEmailHtml(raw);
            htmlArea.value = formatted;
            updateCampPreview();
            if (typeof veParseHtmlToBlocks === 'function') {
                const parsed = veParseHtmlToBlocks(formatted);
                if (parsed && parsed.length && window.emailBlocks) {
                    window.emailBlocks = parsed;
                    if (typeof veRenderCanvas === 'function') veRenderCanvas();
                }
            }
            // Switch to HTML tab to show user
            const htmlTabBtn = document.getElementById('c-html-tab');
            if (htmlTabBtn) {
                const tab = new bootstrap.Tab(htmlTabBtn);
                tab.show();
            }
            alert('Plain text converted to clean email HTML! You can inspect the HTML code or preview below.');
        });
    }

    // Load starter template  – also parse into visual blocks if possible
    document.querySelectorAll('.btn-load-tpl').forEach(item => {
        item.addEventListener('click', function(e) {
            e.preventDefault();
            const html  = this.getAttribute('data-html');
            const plain = this.getAttribute('data-plain');
            htmlArea.value  = html;
            plainArea.value = plain;
            updateCampPreview();
            // Try to seed visual canvas from loaded template HTML
            if (typeof veParseHtmlToBlocks === 'function') {
                const parsed = veParseHtmlToBlocks(html);
                if (parsed && parsed.length) {
                    emailBlocks = parsed;
                    veRenderCanvas();
                }
            }
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

    function ensureComplianceFooter() {
        if (htmlArea && htmlArea.value && htmlArea.value.trim()) {
            const val = htmlArea.value.trim();
            if (!val.includes('{{unsubscribe_url}}') && !val.toLowerCase().includes('unsubscribe')) {
                const footer = '\n\n<div style="margin-top:24px;padding-top:16px;border-top:1px solid #e2e8f0;font-size:12px;color:#718096;text-align:center;">' +
                    'Axiora Global Solutions, Cyber City, Hyderabad, India | <a href="{{unsubscribe_url}}" style="color:#4a5568;text-decoration:underline;">Unsubscribe</a>' +
                    '</div>';
                if (val.toLowerCase().includes('</body>')) {
                    htmlArea.value = val.replace(/<\/body>/i, footer + '</body>');
                } else {
                    htmlArea.value = val + footer;
                }
                updateCampPreview();
            }
        }
        if (plainArea && plainArea.value && plainArea.value.trim()) {
            const pVal = plainArea.value.trim();
            if (!pVal.includes('{{unsubscribe_url}}') && !pVal.toLowerCase().includes('unsubscribe')) {
                plainArea.value = pVal + '\n\nAxiora Global Solutions, Cyber City, Hyderabad, India | Unsubscribe: {{unsubscribe_url}}';
            }
        }
    }

    const campaignFormEl = document.getElementById('campaign-form');
    if (campaignFormEl) {
        campaignFormEl.addEventListener('submit', function() {
            ensureComplianceFooter();
        });
    }

    if (btnRunAudit) {
        btnRunAudit.addEventListener('click', function() {
            ensureComplianceFooter();
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
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                body: fd
            })
            .then(res => {
                if (!res.ok) {
                    return res.text().then(text => { throw new Error(text || ('HTTP ' + res.status)); });
                }
                return res.json();
            })
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
            .catch((err) => {
                const msg = (err && err.message) ? err.message : 'Failed to connect to preflight endpoint.';
                items.innerHTML = '<div class="text-danger">' + msg + '</div>';
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
            ensureComplianceFooter();
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

    // =========================================================================
    // VISUAL DRAG-AND-DROP EMAIL EDITOR
    // =========================================================================
    (function() {
        'use strict';

        // ── State ──────────────────────────────────────────────────────────────
        let emailBlocks = [];
        let veBlockCounter = 0;
        let veEditingId = null; // block currently being edited inline

        // Expose globally so btn-load-tpl handler above can call them
        window.emailBlocks = emailBlocks;

        function genId() { return 'blk_' + (++veBlockCounter); }

        // ── Default data for each block type ──────────────────────────────────
        const blockDefaults = {
            header:  { logo_url: '', company_name: 'Axiora Global', bg_color: '#1a56db', text_color: '#ffffff' },
            hero:    { heading: 'Welcome, {{first_name}}!', subtext: 'Thanks for being with us. Here\'s what\'s new.', bg_color: '#f0f7ff', text_color: '#1e293b' },
            text:    { content: 'Dear {{first_name}},\n\nWe have an important update to share with you.\n\nBest regards,\nThe Axiora Team' },
            image:   { src: 'https://placehold.co/560x200/e2e8f0/94a3b8?text=Email+Banner', alt: 'Email image', link: '' },
            button:  { label: 'View Details', url: '#', bg_color: '#1a56db', text_color: '#ffffff' },
            divider: { color: '#e2e8f0', height: '1' },
            columns: { left: 'Left column text goes here.', right: 'Right column text goes here.' },
            footer:  { company_name: 'Axiora Global Solutions', address: '{{company_address}}', extra: '' }
        };

        // ── HTML Generator ────────────────────────────────────────────────────
        function generateEmailHtml(blocks) {
            if (!blocks || blocks.length === 0) {
                return document.getElementById('camp_html') ? document.getElementById('camp_html').value : '';
            }

            let rows = '';
            blocks.forEach(function(blk) {
                rows += renderBlockHtmlRow(blk);
            });

            return '<!DOCTYPE html>\n<html lang="en">\n<head>\n<meta charset="UTF-8">\n<meta name="viewport" content="width=device-width,initial-scale=1">\n<title>Email</title>\n</head>\n' +
                '<body style="margin:0;padding:0;background:#f4f4f5;font-family:Arial,Helvetica,sans-serif;">\n' +
                '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f4f4f5;">\n' +
                '<tbody><tr><td align="center" style="padding:16px 8px;">\n' +
                '<table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.08);">\n' +
                '<tbody>\n' + rows + '</tbody>\n</table>\n' +
                '</td></tr></tbody>\n</table>\n</body>\n</html>';
        }

        function renderBlockHtmlRow(blk) {
            const d = blk.data || {};
            switch (blk.type) {
                case 'header': {
                    const bg   = d.bg_color   || '#1a56db';
                    const tc   = d.text_color  || '#ffffff';
                    const name = d.company_name || 'Axiora Global';
                    const logo = d.logo_url ? '<img src="' + escHtml(d.logo_url) + '" alt="Logo" style="height:40px;vertical-align:middle;margin-right:10px;">' : '';
                    return '<tr><td style="background:' + bg + ';padding:20px 24px;text-align:left;">' +
                        logo + '<span style="color:' + tc + ';font-size:20px;font-weight:700;vertical-align:middle;">' + escHtml(name) + '</span>' +
                        '</td></tr>\n';
                }
                case 'hero': {
                    const bg  = d.bg_color  || '#f0f7ff';
                    const tc  = d.text_color || '#1e293b';
                    return '<tr><td style="background:' + bg + ';padding:36px 32px;text-align:center;">' +
                        '<h1 style="margin:0 0 12px;font-size:28px;font-weight:700;color:' + tc + ';line-height:1.3;">' + escHtml(d.heading || 'Welcome!') + '</h1>' +
                        '<p style="margin:0;font-size:16px;color:' + tc + ';line-height:1.6;opacity:.85;">' + escHtml(d.subtext || '') + '</p>' +
                        '</td></tr>\n';
                }
                case 'text': {
                    const lines = (d.content || '').split('\n').map(function(l){ return l ? '<p style="margin:0 0 10px;">' + escHtml(l) + '</p>' : '<br>'; }).join('');
                    return '<tr><td style="padding:24px 32px;font-size:15px;color:#374151;line-height:1.7;">' + lines + '</td></tr>\n';
                }
                case 'image': {
                    const wrap = d.link ? '<a href="' + escHtml(d.link) + '" style="display:block;">' : '';
                    const wrapEnd = d.link ? '</a>' : '';
                    return '<tr><td style="padding:0;text-align:center;">' + wrap +
                        '<img src="' + escHtml(d.src || '') + '" alt="' + escHtml(d.alt || '') + '" style="width:100%;max-width:600px;display:block;border:0;">' +
                        wrapEnd + '</td></tr>\n';
                }
                case 'button': {
                    const bg = d.bg_color   || '#1a56db';
                    const tc = d.text_color  || '#ffffff';
                    return '<tr><td style="padding:20px 32px;text-align:center;">' +
                        '<a href="' + escHtml(d.url || '#') + '" style="display:inline-block;background:' + bg + ';color:' + tc + ';text-decoration:none;font-size:16px;font-weight:600;padding:12px 32px;border-radius:6px;">' +
                        escHtml(d.label || 'Click Here') + '</a>' +
                        '</td></tr>\n';
                }
                case 'divider': {
                    const color = d.color || '#e2e8f0';
                    return '<tr><td style="padding:4px 32px;"><hr style="border:none;border-top:' + escHtml(d.height || '1') + 'px solid ' + color + ';margin:0;"></td></tr>\n';
                }
                case 'columns': {
                    return '<tr><td style="padding:20px 24px;">' +
                        '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tbody><tr>' +
                        '<td width="50%" style="padding:0 8px 0 0;vertical-align:top;font-size:14px;color:#374151;line-height:1.6;">' + escHtml(d.left || '') + '</td>' +
                        '<td width="50%" style="padding:0 0 0 8px;vertical-align:top;font-size:14px;color:#374151;line-height:1.6;">' + escHtml(d.right || '') + '</td>' +
                        '</tr></tbody></table></td></tr>\n';
                }
                case 'footer': {
                    const companyName = d.company_name || 'Axiora Global Solutions';
                    const address     = d.address      || '{{company_address}}';
                    const extra       = d.extra        || '';
                    return '<tr><td style="background:#f8fafc;padding:20px 32px;text-align:center;border-top:1px solid #e2e8f0;">' +
                        '<p style="margin:0 0 6px;font-size:12px;color:#94a3b8;">' + escHtml(companyName) + (extra ? '<br>' + escHtml(extra) : '') + '</p>' +
                        '<p style="margin:0 0 6px;font-size:12px;color:#94a3b8;">' + escHtml(address) + '</p>' +
                        '<p style="margin:0;font-size:12px;"><a href="{{unsubscribe_url}}" style="color:#6366f1;text-decoration:underline;">Unsubscribe</a> &nbsp;|&nbsp; <a href="#" style="color:#6366f1;text-decoration:underline;">View in Browser</a></p>' +
                        '</td></tr>\n';
                }
                default: return '';
            }
        }

        function escHtml(str) {
            if (!str) return '';
            return String(str)
                .replace(/&/g, '&amp;').replace(/</g, '&lt;')
                .replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
        }

        // ── Sync HTML textarea ────────────────────────────────────────────────
        function syncHtmlFromBlocks() {
            const area = document.getElementById('camp_html');
            if (!area) return;
            if (emailBlocks.length === 0) return; // Don't wipe a hand-written template
            area.value = generateEmailHtml(emailBlocks);
            if (typeof updateCampPreview === 'function') updateCampPreview();
        }

        // ── Canvas Rendering ──────────────────────────────────────────────────
        function veRenderCanvas() {
            const canvas   = document.getElementById('canvas-blocks');
            const emptyHint = document.getElementById('canvas-empty-hint');
            if (!canvas) return;

            if (emailBlocks.length === 0) {
                canvas.innerHTML = '';
                if (emptyHint) emptyHint.style.display = 'block';
                return;
            }
            if (emptyHint) emptyHint.style.display = 'none';

            canvas.innerHTML = '';
            emailBlocks.forEach(function(blk, idx) {
                const card = document.createElement('div');
                card.className = 'mb-2 border rounded bg-white position-relative';
                card.setAttribute('data-block-id', blk.id);
                card.style.cssText = 'transition:box-shadow .15s;';

                // ── toolbar ──
                const toolbar = document.createElement('div');
                toolbar.className = 'd-flex align-items-center gap-1 px-2 py-1 border-bottom bg-light';
                toolbar.style.cssText = 'border-radius:4px 4px 0 0;';
                toolbar.innerHTML =
                    '<span class="badge bg-primary-subtle text-primary small" style="font-size:0.68rem;">' + blk.type.toUpperCase() + '</span>' +
                    '<div class="ms-auto d-flex gap-1">' +
                    (idx > 0 ? '<button type="button" class="btn btn-xs btn-outline-secondary py-0 px-1 ve-move-up" data-id="' + blk.id + '" title="Move up" style="font-size:0.72rem;">&#8593;</button>' : '') +
                    (idx < emailBlocks.length - 1 ? '<button type="button" class="btn btn-xs btn-outline-secondary py-0 px-1 ve-move-down" data-id="' + blk.id + '" title="Move down" style="font-size:0.72rem;">&#8595;</button>' : '') +
                    '<button type="button" class="btn btn-xs btn-outline-primary py-0 px-1 ve-edit-block" data-id="' + blk.id + '" title="Edit block" style="font-size:0.72rem;">&#9998; Edit</button>' +
                    '<button type="button" class="btn btn-xs btn-outline-danger py-0 px-1 ve-delete-block" data-id="' + blk.id + '" title="Delete block" style="font-size:0.72rem;">&#128465;</button>' +
                    '</div>';
                card.appendChild(toolbar);

                // ── preview ──
                const preview = document.createElement('div');
                preview.className = 'p-2';
                preview.style.cssText = 'pointer-events:none;overflow:hidden;max-height:120px;';
                preview.innerHTML = renderBlockPreview(blk);
                card.appendChild(preview);

                // ── inline edit panel (hidden initially) ──
                const editPanel = document.createElement('div');
                editPanel.className = 've-edit-panel p-2 border-top bg-light';
                editPanel.style.display = (veEditingId === blk.id) ? 'block' : 'none';
                editPanel.innerHTML = buildEditFields(blk);
                card.appendChild(editPanel);

                canvas.appendChild(card);
            });

            wireCanvasEvents();
            syncHtmlFromBlocks();
        }

        // ── Block canvas preview ──────────────────────────────────────────────
        function renderBlockPreview(blk) {
            const d = blk.data || {};
            switch (blk.type) {
                case 'header':
                    return '<div style="background:' + (d.bg_color||'#1a56db') + ';padding:10px 14px;border-radius:4px;">' +
                        (d.logo_url ? '<img src="' + escHtml(d.logo_url) + '" style="height:28px;vertical-align:middle;margin-right:8px;">' : '<i class="fa-solid fa-building" style="color:' + (d.text_color||'#fff') + ';margin-right:8px;"></i>') +
                        '<strong style="color:' + (d.text_color||'#fff') + ';font-size:14px;">' + escHtml(d.company_name||'Company Name') + '</strong></div>';
                case 'hero':
                    return '<div style="background:' + (d.bg_color||'#f0f7ff') + ';padding:14px;border-radius:4px;text-align:center;">' +
                        '<div style="font-weight:700;font-size:16px;color:' + (d.text_color||'#1e293b') + ';">' + escHtml(d.heading||'Hero Heading') + '</div>' +
                        '<div style="font-size:12px;color:' + (d.text_color||'#1e293b') + ';opacity:.75;margin-top:4px;">' + escHtml((d.subtext||'').substring(0,80)) + '</div></div>';
                case 'text':
                    return '<div style="font-size:13px;color:#374151;line-height:1.5;">' + escHtml((d.content||'').substring(0,120)) + (((d.content||'').length>120)?'…':'') + '</div>';
                case 'image':
                    return '<div style="text-align:center;"><img src="' + escHtml(d.src||'') + '" style="max-width:100%;max-height:80px;border-radius:4px;" onerror="this.src=\'https://placehold.co/400x80/e2e8f0/94a3b8?text=Image\'"></div>';
                case 'button':
                    return '<div style="text-align:center;padding:6px 0;"><span style="display:inline-block;background:' + (d.bg_color||'#1a56db') + ';color:' + (d.text_color||'#fff') + ';padding:7px 20px;border-radius:5px;font-size:13px;font-weight:600;">' + escHtml(d.label||'Button') + '</span></div>';
                case 'divider':
                    return '<hr style="border:none;border-top:' + escHtml(d.height||'1') + 'px solid ' + (d.color||'#e2e8f0') + ';margin:4px 0;">';
                case 'columns':
                    return '<table width="100%" style="font-size:12px;color:#374151;"><tr>' +
                        '<td width="50%" style="padding-right:8px;border-right:1px dashed #e2e8f0;">' + escHtml((d.left||'').substring(0,60)) + '</td>' +
                        '<td width="50%" style="padding-left:8px;">' + escHtml((d.right||'').substring(0,60)) + '</td>' +
                        '</tr></table>';
                case 'footer':
                    return '<div style="background:#f8fafc;padding:8px;text-align:center;border-radius:4px;font-size:11px;color:#94a3b8;">' +
                        escHtml(d.company_name||'Company') + ' &bull; ' + escHtml(d.address||'{{company_address}}') +
                        '<br><span style="color:#6366f1;text-decoration:underline;">Unsubscribe</span></div>';
                default: return '<span class="text-muted small">' + blk.type + '</span>';
            }
        }

        // ── Inline edit field builder ─────────────────────────────────────────
        function buildEditFields(blk) {
            const d = blk.data || {};
            const id = blk.id;
            let html = '<div class="row g-2" style="font-size:0.8rem;">';

            const fld = function(label, name, val, type) {
                type = type || 'text';
                if (type === 'textarea') {
                    return '<div class="col-12"><label class="form-label small mb-1 fw-semibold">' + label + '</label>' +
                        '<textarea class="form-control form-control-sm ve-field" data-block-id="' + id + '" data-field="' + name + '" rows="3">' + escHtml(val||'') + '</textarea></div>';
                }
                return '<div class="col-md-6"><label class="form-label small mb-1 fw-semibold">' + label + '</label>' +
                    '<input type="' + type + '" class="form-control form-control-sm ve-field" data-block-id="' + id + '" data-field="' + name + '" value="' + escHtml(val||'') + '"></div>';
            };
            const colorFld = function(label, name, val) {
                return '<div class="col-md-6"><label class="form-label small mb-1 fw-semibold">' + label + '</label>' +
                    '<div class="d-flex gap-1"><input type="color" class="form-control form-control-sm form-control-color ve-field" data-block-id="' + id + '" data-field="' + name + '" value="' + escHtml(val||'#000000') + '" style="width:40px;padding:1px 2px;">' +
                    '<input type="text" class="form-control form-control-sm ve-field-color-text" data-block-id="' + id + '" data-field="' + name + '" value="' + escHtml(val||'#000000') + '"></div></div>';
            };

            switch (blk.type) {
                case 'header':
                    html += fld('Logo URL', 'logo_url', d.logo_url) + fld('Company Name', 'company_name', d.company_name) + colorFld('Background', 'bg_color', d.bg_color) + colorFld('Text Color', 'text_color', d.text_color);
                    break;
                case 'hero':
                    html += fld('Heading', 'heading', d.heading) + fld('Subtext', 'subtext', d.subtext) + colorFld('Background', 'bg_color', d.bg_color) + colorFld('Text Color', 'text_color', d.text_color);
                    break;
                case 'text':
                    html += fld('Content', 'content', d.content, 'textarea').replace('col-12', 'col-12');
                    // Fix: make textarea full-width
                    html = html.replace('col-md-6', 'col-12');
                    break;
                case 'image':
                    html += fld('Image URL', 'src', d.src) + fld('Alt Text', 'alt', d.alt) + fld('Link URL (optional)', 'link', d.link);
                    break;
                case 'button':
                    html += fld('Button Label', 'label', d.label) + fld('URL', 'url', d.url) + colorFld('Background', 'bg_color', d.bg_color) + colorFld('Text Color', 'text_color', d.text_color);
                    break;
                case 'divider':
                    html += fld('Line Color', 'color', d.color) + fld('Height (px)', 'height', d.height);
                    break;
                case 'columns':
                    html += fld('Left Column', 'left', d.left, 'textarea') + fld('Right Column', 'right', d.right, 'textarea');
                    break;
                case 'footer':
                    html += fld('Company Name', 'company_name', d.company_name) + fld('Address', 'address', d.address) + fld('Extra Line', 'extra', d.extra);
                    break;
            }

            html += '</div>';
            html += '<div class="mt-2 d-flex gap-1"><button type="button" class="btn btn-primary btn-sm ve-apply-edit" data-id="' + id + '"><i class="fa-solid fa-check me-1"></i>Apply</button>' +
                '<button type="button" class="btn btn-outline-secondary btn-sm ve-cancel-edit" data-id="' + id + '">Cancel</button></div>';
            return html;
        }

        // ── Wire canvas event listeners ───────────────────────────────────────
        function wireCanvasEvents() {
            // Move up
            document.querySelectorAll('.ve-move-up').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    const idx = emailBlocks.findIndex(function(b){ return b.id === id; });
                    if (idx > 0) {
                        const tmp = emailBlocks[idx - 1];
                        emailBlocks[idx - 1] = emailBlocks[idx];
                        emailBlocks[idx] = tmp;
                        veRenderCanvas();
                    }
                });
            });

            // Move down
            document.querySelectorAll('.ve-move-down').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    const idx = emailBlocks.findIndex(function(b){ return b.id === id; });
                    if (idx < emailBlocks.length - 1) {
                        const tmp = emailBlocks[idx + 1];
                        emailBlocks[idx + 1] = emailBlocks[idx];
                        emailBlocks[idx] = tmp;
                        veRenderCanvas();
                    }
                });
            });

            // Delete
            document.querySelectorAll('.ve-delete-block').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    emailBlocks = emailBlocks.filter(function(b){ return b.id !== id; });
                    if (veEditingId === id) veEditingId = null;
                    veRenderCanvas();
                });
            });

            // Open edit panel
            document.querySelectorAll('.ve-edit-block').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    veEditingId = (veEditingId === id) ? null : id;
                    veRenderCanvas();
                    // Scroll to card
                    const card = document.querySelector('[data-block-id="' + id + '"]');
                    if (card) card.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                });
            });

            // Apply edit
            document.querySelectorAll('.ve-apply-edit').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    const blk = emailBlocks.find(function(b){ return b.id === id; });
                    if (!blk) return;

                    // Gather all ve-field inputs for this block
                    const card = document.querySelector('[data-block-id="' + id + '"]');
                    if (card) {
                        card.querySelectorAll('.ve-field').forEach(function(input) {
                            const field = input.getAttribute('data-field');
                            if (field) blk.data[field] = input.value;
                        });
                    }
                    veEditingId = null;
                    veRenderCanvas();
                });
            });

            // Cancel edit
            document.querySelectorAll('.ve-cancel-edit').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    veEditingId = null;
                    veRenderCanvas();
                });
            });

            // Sync color pickers ↔ text inputs
            document.querySelectorAll('input[type="color"].ve-field').forEach(function(picker) {
                picker.addEventListener('input', function() {
                    const field = this.getAttribute('data-field');
                    const blockId = this.getAttribute('data-block-id');
                    const textInput = this.closest('.d-flex') ? this.closest('.d-flex').querySelector('.ve-field-color-text') : null;
                    if (textInput) textInput.value = this.value;
                });
            });
        }

        // ── Add block from palette ─────────────────────────────────────────────
        function addBlock(type) {
            const defaults = JSON.parse(JSON.stringify(blockDefaults[type] || {}));
            emailBlocks.push({ id: genId(), type: type, data: defaults });
            veEditingId = null;
            veRenderCanvas();
        }

        // ── Palette click handlers ─────────────────────────────────────────────
        document.querySelectorAll('.ve-add-block').forEach(function(btn) {
            btn.addEventListener('click', function() {
                addBlock(this.getAttribute('data-block-type'));
            });
        });

        // Clear canvas
        const veClearBtn = document.getElementById('ve-clear-canvas');
        if (veClearBtn) {
            veClearBtn.addEventListener('click', function() {
                if (emailBlocks.length === 0) return;
                if (confirm('Clear all blocks from the canvas? The HTML Source tab will retain its content.')) {
                    emailBlocks = [];
                    veEditingId = null;
                    veRenderCanvas();
                }
            });
        }

        // ── Tab switch: Visual → HTML: sync blocks → html ─────────────────────
        const htmlTabBtn = document.getElementById('c-html-tab');
        if (htmlTabBtn) {
            htmlTabBtn.addEventListener('click', function() {
                if (emailBlocks.length > 0) syncHtmlFromBlocks();
            });
        }

        // ── Tab switch: HTML → Visual: parse HTML → blocks (best effort) ──────
        const visualTabBtn = document.getElementById('tab-visual-btn');
        if (visualTabBtn) {
            visualTabBtn.addEventListener('click', function() {
                if (emailBlocks.length === 0) {
                    // try to seed from current HTML
                    const area = document.getElementById('camp_html');
                    if (area && area.value && area.value.trim()) {
                        const parsed = veParseHtmlToBlocks(area.value);
                        if (parsed && parsed.length) {
                            emailBlocks = parsed;
                            veRenderCanvas();
                        }
                    }
                }
            });
        }

        // ── Best-effort HTML parser ───────────────────────────────────────────
        function veParseHtmlToBlocks(html) {
            // We detect known Raptor-generated block markers via inline-style signatures
            const blocks = [];
            try {
                const parser = new DOMParser();
                const doc    = parser.parseFromString(html, 'text/html');

                doc.querySelectorAll('table[role="presentation"] > tbody > tr > td[style]').forEach(function(td) {
                    const style = td.getAttribute('style') || '';
                    const text  = (td.textContent || '').trim();

                    // Header: contains strong + bg color in blue-ish range
                    if (style.includes('background:#1a56db') || style.includes('background: #1a56db')) {
                        const strong = td.querySelector('strong');
                        blocks.push({ id: genId(), type: 'header', data: Object.assign({}, blockDefaults.header, {
                            company_name: strong ? strong.textContent.trim() : 'Company',
                            bg_color: '#1a56db'
                        })});
                        return;
                    }
                    // Hero: has h1
                    if (td.querySelector('h1')) {
                        const h1  = td.querySelector('h1');
                        const sub = td.querySelector('p');
                        blocks.push({ id: genId(), type: 'hero', data: Object.assign({}, blockDefaults.hero, {
                            heading: h1 ? h1.textContent.trim() : '',
                            subtext: sub ? sub.textContent.trim() : ''
                        })});
                        return;
                    }
                    // Button: has an <a> with display:inline-block
                    const a = td.querySelector('a[style*="display:inline-block"]');
                    if (a) {
                        blocks.push({ id: genId(), type: 'button', data: Object.assign({}, blockDefaults.button, {
                            label: a.textContent.trim(),
                            url: a.getAttribute('href') || '#'
                        })});
                        return;
                    }
                    // Image: has img and no other significant text
                    if (td.querySelector('img') && text.length < 5) {
                        const img = td.querySelector('img');
                        blocks.push({ id: genId(), type: 'image', data: Object.assign({}, blockDefaults.image, {
                            src: img.getAttribute('src') || '',
                            alt: img.getAttribute('alt') || ''
                        })});
                        return;
                    }
                    // Divider: has hr
                    if (td.querySelector('hr')) {
                        blocks.push({ id: genId(), type: 'divider', data: Object.assign({}, blockDefaults.divider)});
                        return;
                    }
                    // Footer: has unsubscribe link
                    if (html.toLowerCase().includes('unsubscribe') && style.includes('background:#f8fafc')) {
                        blocks.push({ id: genId(), type: 'footer', data: Object.assign({}, blockDefaults.footer)});
                        return;
                    }
                    // 2-column: has nested table with 2 tds width=50%
                    const innerTable = td.querySelector('table');
                    if (innerTable) {
                        const cols = innerTable.querySelectorAll('td[width="50%"]');
                        if (cols.length === 2) {
                            blocks.push({ id: genId(), type: 'columns', data: {
                                left:  cols[0].textContent.trim(),
                                right: cols[1].textContent.trim()
                            }});
                            return;
                        }
                    }
                    // Generic text block (has non-trivial text)
                    if (text.length > 10) {
                        blocks.push({ id: genId(), type: 'text', data: { content: text.substring(0, 800) }});
                    }
                });
            } catch(e) {
                // Parsing failed; return empty so canvas keeps existing HTML
                return [];
            }
            return blocks;
        }

        // Expose for btn-load-tpl handler
        window.veParseHtmlToBlocks = veParseHtmlToBlocks;
        window.emailBlocks = emailBlocks; // keep reference

        // ── Init: if editing an existing campaign, seed from stored HTML ───────
        (function initVisualEditor() {
            const area = document.getElementById('camp_html');
            if (area && area.value && area.value.trim()) {
                const parsed = veParseHtmlToBlocks(area.value);
                if (parsed && parsed.length) {
                    emailBlocks = parsed;
                }
            }
            veRenderCanvas();
        })();

    })(); // end IIFE visual editor
    // =========================================================================
    // END VISUAL EDITOR
    // =========================================================================
});
</script>

