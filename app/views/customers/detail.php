<div class="row justify-content-center">
    <div class="col-lg-10">
        <!-- Customer Profile Header Card -->
        <div class="pulse-card mb-4">
            <div class="d-flex justify-content-between align-items-start mb-3">
                <div>
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <span class="badge bg-dark border border-secondary text-primary font-monospace fs-6">
                            <?php echo htmlspecialchars($customer->customer_code ?: ('CUST-' . date('Y') . '-' . sprintf('%05d', $customer->customer_id))); ?>
                        </span>
                        <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">
                            <?php echo htmlspecialchars($customer->customer_type); ?>
                        </span>
                        <?php 
                            $statusBadge = 'bg-success-subtle text-success border-success-subtle';
                            if ($customer->status === 'On Hold') $statusBadge = 'bg-warning-subtle text-warning border-warning-subtle';
                            elseif ($customer->status === 'Churned') $statusBadge = 'bg-danger-subtle text-danger border-danger-subtle';
                            elseif ($customer->status === 'Renewal Due') $statusBadge = 'bg-info-subtle text-info border-info-subtle';
                        ?>
                        <span class="badge border <?php echo $statusBadge; ?>"><?php echo strtoupper($customer->status); ?></span>
                    </div>
                    
                    <h3 class="text-white fw-bold mb-1">
                        <?php echo htmlspecialchars($customer->company_name ?: $customer->first_name); ?>
                    </h3>
                    
                    <?php if (!empty($customer->first_name) && !empty($customer->company_name)): ?>
                        <div class="text-secondary small mb-2"><i class="fa-solid fa-user me-1 text-primary"></i>Key Contact: <strong><?php echo htmlspecialchars($customer->first_name); ?></strong></div>
                    <?php endif; ?>

                    <div class="d-flex flex-wrap gap-3 text-secondary small">
                        <span><i class="fa-solid fa-envelope me-1 text-info"></i><?php echo htmlspecialchars($customer->email); ?></span>
                        <?php if (!empty($customer->phone)): ?>
                            <span><i class="fa-solid fa-phone me-1 text-success"></i><?php echo htmlspecialchars($customer->phone); ?></span>
                        <?php endif; ?>
                        <span><i class="fa-solid fa-user-tie me-1 text-warning"></i>Owner: <strong><?php echo htmlspecialchars($customer->owner_name ?: 'Unassigned'); ?></strong></span>
                    </div>
                </div>

                <div class="d-flex gap-2">
                    <a href="index.php?route=customers/edit/<?php echo $customer->customer_id; ?>" class="btn btn-outline-light btn-sm px-3">
                        <i class="fa-solid fa-pen-to-square me-1"></i>Edit Profile
                    </a>
                    <a href="index.php?route=customers/index" class="btn btn-outline-secondary btn-sm px-3">
                        <i class="fa-solid fa-arrow-left me-1"></i>Back
                    </a>
                </div>
            </div>

            <!-- Traceability Banner: Originating Lead Link -->
            <?php if (!empty($customer->converted_from_lead_id)): ?>
                <div class="p-3 bg-dark border border-info border-opacity-25 rounded-3 d-flex justify-content-between align-items-center mb-3">
                    <div class="text-white small">
                        <i class="fa-solid fa-link text-info me-2 fs-6"></i>
                        Originating Lead Traceability: <strong><?php echo htmlspecialchars($customer->originating_lead_code ?: ('#' . $customer->converted_from_lead_id)); ?></strong>
                        <span class="text-secondary ms-2">(Status: <?php echo htmlspecialchars(strtoupper($customer->originating_lead_status ?? 'Converted')); ?>)</span>
                    </div>
                    <a href="index.php?route=leads/view/<?php echo $customer->converted_from_lead_id; ?>" class="btn btn-outline-info btn-sm">
                        <i class="fa-solid fa-eye me-1"></i>View Original Lead
                    </a>
                </div>
            <?php endif; ?>

            <!-- Key Commercial Metrics Bar -->
            <div class="row g-3">
                <div class="col-md-3 col-6">
                    <div class="p-3 bg-dark rounded border border-secondary text-center">
                        <div class="text-secondary small mb-1"><i class="fa-solid fa-sack-dollar text-success me-1"></i>Contract Value</div>
                        <div class="text-success fw-bold fs-4 font-monospace">$<?php echo number_format((float)$customer->contract_value, 2); ?></div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="p-3 bg-dark rounded border border-secondary text-center">
                        <div class="text-secondary small mb-1"><i class="fa-solid fa-calendar-check text-info me-1"></i>Customer Since</div>
                        <div class="text-white fw-bold fs-5"><?php echo $customer->onboarding_date ? date('M d, Y', strtotime($customer->onboarding_date)) : 'N/A'; ?></div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="p-3 bg-dark rounded border border-secondary text-center">
                        <div class="text-secondary small mb-1"><i class="fa-solid fa-clock-rotate-left text-warning me-1"></i>Renewal Date</div>
                        <div class="text-warning fw-bold fs-5"><?php echo $customer->renewal_date ? date('M d, Y', strtotime($customer->renewal_date)) : 'N/A'; ?></div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="p-3 bg-dark rounded border border-secondary text-center">
                        <div class="text-secondary small mb-1"><i class="fa-solid fa-file-invoice text-primary me-1"></i>Payment Terms</div>
                        <div class="text-primary fw-bold fs-5"><?php echo htmlspecialchars($customer->payment_terms ?: 'Net 30'); ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Subscribed Products & Address Details Card -->
        <div class="row g-4 mb-4">
            <div class="col-md-6">
                <div class="pulse-card h-100">
                    <h5 class="text-white mb-3 border-bottom pb-2 border-secondary"><i class="fa-solid fa-box-open text-warning me-2"></i>Subscribed Products &amp; Services</h5>
                    <?php if (!empty($customer->products_subscribed)): ?>
                        <div class="p-3 bg-dark border border-secondary rounded text-white mb-3">
                            <i class="fa-solid fa-check-double text-success me-2"></i><?php echo htmlspecialchars($customer->products_subscribed); ?>
                        </div>
                    <?php else: ?>
                        <div class="text-secondary small py-2">No active products or services logged.</div>
                    <?php endif; ?>

                    <?php if (!empty($customer->tags)): ?>
                        <div class="mt-3">
                            <span class="text-secondary small me-2">Tags &amp; Segment:</span>
                            <span class="badge bg-info-subtle text-info border border-info-subtle"><i class="fa-solid fa-tag me-1"></i><?php echo htmlspecialchars($customer->tags); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="col-md-6">
                <div class="pulse-card h-100">
                    <h5 class="text-white mb-3 border-bottom pb-2 border-secondary"><i class="fa-solid fa-location-dot text-danger me-2"></i>Addresses</h5>
                    <div class="mb-3">
                        <div class="text-secondary small mb-1">Billing Address</div>
                        <div class="text-white p-2 bg-dark rounded border border-secondary small">
                            <?php echo !empty($customer->billing_address) ? nl2br(htmlspecialchars($customer->billing_address)) : 'No billing address provided.'; ?>
                        </div>
                    </div>
                    <div>
                        <div class="text-secondary small mb-1">Shipping / Service Address</div>
                        <div class="text-white p-2 bg-dark rounded border border-secondary small">
                            <?php echo !empty($customer->shipping_address) ? nl2br(htmlspecialchars($customer->shipping_address)) : 'No shipping address provided.'; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Notes & History Card -->
        <div class="pulse-card mb-4">
            <h5 class="text-white mb-3 border-bottom pb-2 border-secondary"><i class="fa-solid fa-clipboard-list text-info me-2"></i>Account Notes &amp; History</h5>
            <?php if (!empty($customer->notes)): ?>
                <div class="p-3 bg-dark border border-secondary rounded text-white" style="font-size:0.95rem;">
                    <?php echo nl2br(htmlspecialchars($customer->notes)); ?>
                </div>
            <?php else: ?>
                <div class="text-secondary small py-2">No notes recorded for this customer profile.</div>
            <?php endif; ?>
        </div>

        <!-- AI Smart Reply & Communications Copilot (Raptor v2.2 - Sprint 2) -->
        <div class="pulse-card mb-4" style="border: 1px solid rgba(37, 99, 235, 0.35); background: linear-gradient(180deg, rgba(37, 99, 235, 0.05) 0%, rgba(15, 23, 42, 0.6) 100%);">
            <div class="d-flex justify-content-between align-items-center mb-3 border-bottom pb-2 border-secondary border-opacity-25">
                <h5 class="text-white mb-0 d-flex align-items-center gap-2">
                    <i class="fa-solid fa-wand-magic-sparkles text-primary"></i>
                    AI Smart Reply &amp; Communications Copilot
                </h5>
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle font-monospace" style="font-size: 0.7rem;">
                    Universal LLM Engine
                </span>
            </div>

            <p class="text-secondary small mb-3">
                Generate instant, contextual communication drafts tailored to <?php echo htmlspecialchars($customer->company_name ?: $customer->first_name); ?> using Gemini, GPT, or Claude.
            </p>

            <form id="aiSmartReplyForm" class="mb-3">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                <input type="hidden" name="customer_id" value="<?php echo $customer->customer_id; ?>">

                <div class="row g-3 mb-3">
                    <div class="col-md-8">
                        <label class="form-label text-secondary small fw-medium">Customer Context or Last Incoming Message</label>
                        <textarea class="form-control bg-dark border-secondary text-white small" id="replyContext" name="context" rows="3" placeholder="e.g. Customer asking about renewal options or implementation status..."></textarea>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label text-secondary small fw-medium">Desired Tone</label>
                        <select class="form-select bg-dark border-secondary text-white small mb-3" id="replyTone" name="tone">
                            <option value="professional" selected>👔 Professional &amp; Courteous</option>
                            <option value="friendly">🤝 Warm &amp; Friendly</option>
                            <option value="persuasive">🎯 Value-Focused &amp; Persuasive</option>
                            <option value="concise">⚡ Brief &amp; Action-Oriented</option>
                        </select>
                        <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold d-flex align-items-center justify-content-center gap-2" id="generateReplyBtn">
                            <i class="fa-solid fa-wand-magic-sparkles"></i>
                            <span>Generate Draft</span>
                        </button>
                    </div>
                </div>
            </form>

            <!-- Draft Output Preview Container -->
            <div id="aiReplyOutputContainer" style="display: none;">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-secondary small fw-medium"><i class="fa-solid fa-file-lines text-info me-1"></i>Generated Draft:</span>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-xs btn-outline-secondary btn-sm px-2.5 py-1" id="copyReplyBtn">
                            <i class="fa-solid fa-copy me-1"></i>Copy Text
                        </button>
                        <?php if (!empty($customer->email)): ?>
                            <a href="#" id="openMailtoBtn" class="btn btn-xs btn-outline-info btn-sm px-2.5 py-1">
                                <i class="fa-solid fa-paper-plane me-1"></i>Open in Mail
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="p-3 bg-dark border border-secondary rounded text-white" id="aiReplyText" style="white-space: pre-wrap; font-size: 0.9rem; line-height: 1.6;"></div>
                <div class="text-secondary text-end small mt-1" id="aiProviderBadge" style="font-size: 0.72rem;"></div>
            </div>
        </div>

        <?php if (!empty($activities)): ?>
            <!-- Customer 360 Activity Stream Timeline -->
            <div class="pulse-card mb-4">
                <h5 class="text-white mb-3 border-bottom pb-2 border-secondary"><i class="fa-solid fa-timeline text-primary me-2"></i>Customer 360 Activity Stream</h5>
                <div class="timeline-stream">
                    <?php foreach ($activities as $act): ?>
                        <div class="d-flex gap-3 mb-3 pb-2 border-bottom border-secondary border-opacity-15">
                            <div class="text-primary mt-1">
                                <i class="fa-solid <?php echo htmlspecialchars($act->icon ?? 'fa-circle-dot'); ?>"></i>
                            </div>
                            <div class="flex-grow-1">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="text-white fw-semibold small"><?php echo htmlspecialchars($act->activity_type ?? 'Activity'); ?></span>
                                    <span class="text-secondary" style="font-size: 0.75rem;"><?php echo htmlspecialchars($act->created_at ?? ''); ?></span>
                                </div>
                                <div class="text-secondary small"><?php echo htmlspecialchars($act->summary ?? $act->description ?? ''); ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
$(function() {
    const form = document.getElementById('aiSmartReplyForm');
    const genBtn = document.getElementById('generateReplyBtn');
    const outBox = document.getElementById('aiReplyOutputContainer');
    const outText = document.getElementById('aiReplyText');
    const badge = document.getElementById('aiProviderBadge');
    const copyBtn = document.getElementById('copyReplyBtn');
    const mailtoBtn = document.getElementById('openMailtoBtn');
    const customerEmail = <?php echo json_encode($customer->email ?: ''); ?>;

    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            if (!genBtn) return;

            const originalBtnHtml = genBtn.innerHTML;
            genBtn.disabled = true;
            genBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i>Synthesizing...';

            const formData = new FormData(form);

            fetch('index.php?route=customers/smartReply', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                genBtn.disabled = false;
                genBtn.innerHTML = originalBtnHtml;

                if (data.success && data.reply) {
                    outBox.style.display = 'block';
                    outText.textContent = data.reply;
                    badge.textContent = 'Engine: ' + (data.provider || 'Universal AI');
                    if (mailtoBtn && customerEmail) {
                        mailtoBtn.href = 'mailto:' + encodeURIComponent(customerEmail) + '?subject=' + encodeURIComponent('Update from Raptor Platform') + '&body=' + encodeURIComponent(data.reply);
                    }
                    if (window.RaptorToast) {
                        RaptorToast.success('AI draft synthesized successfully.');
                    }
                } else {
                    if (window.RaptorToast) {
                        RaptorToast.error(data.message || 'Failed to synthesize draft.');
                    } else {
                        alert(data.message || 'Failed to synthesize draft.');
                    }
                }
            })
            .catch(() => {
                genBtn.disabled = false;
                genBtn.innerHTML = originalBtnHtml;
                if (window.RaptorToast) {
                    RaptorToast.error('Network error requesting AI draft.');
                }
            });
        });
    }

    if (copyBtn) {
        copyBtn.addEventListener('click', function() {
            if (!outText) return;
            navigator.clipboard.writeText(outText.textContent).then(() => {
                if (window.RaptorToast) {
                    RaptorToast.success('Draft copied to clipboard.');
                } else {
                    alert('Draft copied to clipboard.');
                }
            });
        });
    }
});
</script>
