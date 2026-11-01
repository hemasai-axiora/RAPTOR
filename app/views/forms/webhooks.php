<div class="pulse-card mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h4 class="text-white mb-1"><i class="fa-solid fa-network-wired me-2 text-info"></i>Inbound Webhook Endpoints</h4>
            <div class="text-secondary" style="font-size: 0.9rem;">Connect external lead generators (WordPress Elementor, Contact Form 7, Webflow, Meta Lead Ads, Zapier) to Raptor.</div>
        </div>
        <div class="d-flex gap-2">
            <a href="index.php?route=forms/index" class="btn btn-outline-secondary btn-sm px-3 py-2">
                <i class="fa-solid fa-arrow-left me-1"></i> Back to Forms
            </a>
            <button class="btn btn-primary btn-sm px-3 py-2" data-bs-toggle="modal" data-bs-target="#newWebhookModal">
                <i class="fa-solid fa-key me-2"></i>Generate Webhook Key
            </button>
        </div>
    </div>

    <?php if (!empty($_SESSION['new_webhook_secret'])): ?>
        <div class="alert alert-warning alert-dismissible fade show border-warning" role="alert">
            <div class="d-flex align-items-center">
                <i class="fa-solid fa-triangle-exclamation fa-2x me-3 text-warning"></i>
                <div>
                    <strong>New Webhook Secret Generated!</strong> Copy this secret now. It will not be shown again:
                    <div class="mt-1 font-monospace bg-black text-warning p-2 rounded small select-all">
                        <?= htmlspecialchars($_SESSION['new_webhook_secret']); ?>
                    </div>
                </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['new_webhook_secret']); ?>
    <?php endif; ?>

    <!-- Webhook Instructions Card -->
    <div class="card bg-dark border-secondary p-3 mb-4" style="border-radius: 8px;">
        <h6 class="text-white mb-2"><i class="fa-solid fa-circle-info me-2 text-primary"></i>How to Integrate External Webhooks</h6>
        <div class="text-secondary small">
            Configure your external form (e.g. Elementor Form Webhook action or Webflow webhook) to send a <code>POST</code> request to:
            <div class="my-2 p-2 bg-black text-info font-monospace rounded small">
                <?= defined('URLROOT') ? URLROOT : ''; ?>/index.php?route=api_lead_capture/webhook&key=&lt;ENDPOINT_KEY&gt;
            </div>
            Pass header <code>X-Raptor-Signature: &lt;HMAC-SHA256&gt;</code> or <code>Authorization: Bearer &lt;SECRET&gt;</code>.
        </div>
    </div>

    <!-- Endpoints Table -->
    <div class="table-responsive">
        <table class="table table-dark table-hover align-middle mb-0" style="border-radius: 8px; overflow: hidden;">
            <thead class="table-dark" style="border-bottom: 2px solid #333;">
                <tr class="text-secondary small">
                    <th>NAME & PROVIDER</th>
                    <th>ENDPOINT KEY</th>
                    <th>SOURCE ATTRIBUTION</th>
                    <th>ASSIGNED SALES REP</th>
                    <th>TOTAL RECEIVED</th>
                    <th>LAST RECEIVED</th>
                    <th>STATUS</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($endpoints)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-4 text-muted">
                            <i class="fa-solid fa-plug-circle-xmark fa-2x mb-2 d-block"></i>
                            No external webhook endpoints configured yet.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($endpoints as $ep): ?>
                        <tr>
                            <td>
                                <div class="fw-bold text-white"><?= htmlspecialchars($ep->name); ?></div>
                                <span class="badge bg-secondary text-uppercase small"><?= htmlspecialchars($ep->provider); ?></span>
                            </td>
                            <td>
                                <code class="text-info font-monospace"><?= htmlspecialchars($ep->endpoint_key); ?></code>
                            </td>
                            <td>
                                <code><?= htmlspecialchars($ep->default_lead_source); ?></code>
                            </td>
                            <td class="small text-secondary">
                                <?= htmlspecialchars($ep->assigned_user_name ?: 'Round Robin'); ?>
                            </td>
                            <td>
                                <span class="badge bg-dark border border-secondary text-white font-monospace">
                                    <?= number_format((int)$ep->total_received); ?>
                                </span>
                            </td>
                            <td class="small text-secondary">
                                <?= $ep->last_received_at ? date('M d, Y H:i', strtotime($ep->last_received_at)) : 'Never'; ?>
                            </td>
                            <td>
                                <?php if ((int)$ep->is_active === 1): ?>
                                    <span class="badge bg-success-subtle text-success border border-success">Active</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Disabled</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: New Webhook -->
<div class="modal fade" id="newWebhookModal" tabindex="-1" aria-labelledby="newWebhookModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content bg-dark text-white border-secondary">
            <form method="POST" action="index.php?route=forms/createWebhook">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title" id="newWebhookModalLabel"><i class="fa-solid fa-key me-2 text-primary"></i>Generate Webhook Key</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label text-secondary small">Endpoint Name *</label>
                        <input type="text" name="name" class="form-control bg-dark text-white border-secondary" placeholder="e.g. WordPress Main Website Contact Form" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-secondary small">Provider Type</label>
                        <select name="provider" class="form-select bg-dark text-white border-secondary">
                            <option value="wordpress">WordPress (Elementor / CF7 / Gravity)</option>
                            <option value="webflow">Webflow Forms</option>
                            <option value="meta_lead_ads">Meta Lead Ads Webhook</option>
                            <option value="zapier">Zapier / Make Webhook</option>
                            <option value="custom">Custom Application Webhook</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-secondary small">Default Lead Source</label>
                        <input type="text" name="default_lead_source" class="form-control bg-dark text-white border-secondary" value="wordpress_webhook">
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-secondary small">Assign Leads To</label>
                        <select name="assigned_user_id" class="form-select bg-dark text-white border-secondary">
                            <option value="">Unassigned / Round Robin</option>
                            <?php foreach ($users as $u): ?>
                                <option value="<?= $u->user_id; ?>"><?= htmlspecialchars($u->name); ?> (<?= ucfirst($u->role); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Generate Endpoint Key & Secret</button>
                </div>
            </form>
        </div>
    </div>
</div>

