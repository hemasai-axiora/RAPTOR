<div class="pulse-card mb-4">
    <!-- Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h4 class="text-white mb-1"><i class="fa-brands fa-meta me-2 text-info"></i>Meta Lead Ads Auto-Synchronization</h4>
            <div class="text-secondary" style="font-size: 0.9rem;">Real-time ingestion of prospects from Facebook & Instagram Instant Forms into Customer 360, automated CRM lead creation, and journey triggers.</div>
        </div>
        <div class="d-flex gap-2">
            <a href="index.php?route=socialPublisher/index" class="btn btn-outline-secondary btn-sm px-3 py-2">
                <i class="fa-solid fa-arrow-left me-1"></i> Back to Publisher
            </a>
        </div>
    </div>

    <!-- Webhook Integration Instruction Card -->
    <div class="p-3 mb-4 rounded bg-dark border border-secondary border-opacity-25 d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <div class="text-white small fw-bold mb-1"><i class="fa-solid fa-link text-info me-1"></i>Meta Webhook Subscription Endpoint:</div>
            <code class="text-info font-monospace small">https://your-domain.com/index.php?route=socialPublisher/webhook</code>
        </div>
        <div>
            <span class="badge bg-secondary font-monospace">Verify Token: raptor_meta_webhook_secret</span>
        </div>
    </div>

    <!-- Synced Forms Table -->
    <h6 class="text-secondary small text-uppercase fw-bold mb-3">Connected Instant Lead Forms</h6>
    <div class="table-responsive mb-4">
        <table class="table table-dark table-hover align-middle mb-0" style="border-radius: 8px; overflow: hidden;">
            <thead class="table-dark" style="border-bottom: 2px solid #333;">
                <tr class="text-secondary small">
                    <th>CAMPAIGN NAME</th>
                    <th>PLATFORM</th>
                    <th>EXTERNAL FORM ID</th>
                    <th>LEADS SYNCED</th>
                    <th>LAST SYNCED AT</th>
                    <th>STATUS</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($campaigns)): ?>
                    <tr>
                        <td colspan="6" class="text-center py-4 text-muted">
                            <i class="fa-brands fa-meta fa-2x mb-2 d-block opacity-50"></i>
                            No Meta Lead Ad forms synced yet. Webhook events will automatically register active forms here.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($campaigns as $camp): ?>
                        <tr>
                            <td>
                                <div class="fw-bold text-white"><?= htmlspecialchars($camp->campaign_name); ?></div>
                            </td>
                            <td>
                                <span class="badge bg-primary-subtle text-primary border border-primary text-uppercase font-monospace" style="font-size: 0.72rem;">
                                    <?= htmlspecialchars($camp->platform); ?>
                                </span>
                            </td>
                            <td>
                                <span class="font-monospace text-secondary small"><?= htmlspecialchars($camp->form_id_external); ?></span>
                            </td>
                            <td>
                                <span class="font-monospace text-info fw-bold"><?= number_format((int)$camp->total_leads_synced); ?> leads</span>
                            </td>
                            <td>
                                <div class="text-secondary small"><?= !empty($camp->last_synced_at) ? date('M d, Y H:i', strtotime($camp->last_synced_at)) : 'Pending'; ?></div>
                            </td>
                            <td>
                                <span class="badge bg-success-subtle text-success border border-success text-uppercase" style="font-size: 0.7rem;">
                                    SYNCED
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Recent Synced Leads Stream -->
    <h6 class="text-secondary small text-uppercase fw-bold mb-3">Recently Captured Social Leads (Customer 360 Stream)</h6>
    <div class="table-responsive">
        <table class="table table-dark table-hover align-middle mb-0" style="border-radius: 8px; overflow: hidden;">
            <thead class="table-dark" style="border-bottom: 2px solid #333;">
                <tr class="text-secondary small">
                    <th>LEAD NAME</th>
                    <th>COMPANY</th>
                    <th>EMAIL</th>
                    <th>PHONE</th>
                    <th>CRM LEAD CODE</th>
                    <th>SCORE</th>
                    <th>CAPTURED AT</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recent_leads)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">
                            <i class="fa-solid fa-user-plus fa-3x mb-3 d-block opacity-50"></i>
                            No social ad leads received yet.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($recent_leads as $rl): ?>
                        <tr>
                            <td>
                                <div class="fw-bold text-white"><?= htmlspecialchars($rl->first_name . ' ' . $rl->last_name); ?></div>
                            </td>
                            <td>
                                <div class="text-secondary small"><?= htmlspecialchars($rl->company_name ?? '—'); ?></div>
                            </td>
                            <td>
                                <span class="font-monospace text-info small"><?= htmlspecialchars($rl->primary_email ?? '—'); ?></span>
                            </td>
                            <td>
                                <span class="font-monospace text-white small"><?= htmlspecialchars($rl->primary_phone ?? '—'); ?></span>
                            </td>
                            <td>
                                <span class="badge bg-dark border border-secondary text-warning font-monospace" style="font-size: 0.72rem;">
                                    <?= htmlspecialchars($rl->lead_code); ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-success font-monospace" style="font-size: 0.75rem;">
                                    <?= (int)$rl->lead_score; ?> pts
                                </span>
                            </td>
                            <td>
                                <div class="text-secondary small"><?= date('M d, H:i', strtotime($rl->created_at)); ?></div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

