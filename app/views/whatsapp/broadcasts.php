<div class="pulse-card mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h4 class="text-white mb-1"><i class="fa-solid fa-bullhorn me-2 text-primary"></i>WhatsApp Broadcast Campaigns</h4>
            <div class="text-secondary" style="font-size: 0.9rem;">Schedule and dispatch bulk marketing and utility campaigns using approved Meta Message Templates.</div>
        </div>
        <div class="d-flex gap-2">
            <a href="index.php?route=whatsapp/conversations" class="btn btn-outline-secondary btn-sm px-3 py-2">
                <i class="fa-solid fa-arrow-left me-1"></i> Back to Inbox
            </a>
            <button class="btn btn-primary btn-sm px-3 py-2" data-bs-toggle="modal" data-bs-target="#newBroadcastModal">
                <i class="fa-solid fa-plus me-1"></i>New Broadcast
            </button>
        </div>
    </div>

    <!-- Broadcasts Table -->
    <div class="table-responsive">
        <table class="table table-dark table-hover align-middle mb-0" style="border-radius: 8px; overflow: hidden;">
            <thead class="table-dark" style="border-bottom: 2px solid #333;">
                <tr class="text-secondary small">
                    <th>CAMPAIGN NAME</th>
                    <th>TEMPLATE</th>
                    <th>RECIPIENTS</th>
                    <th>SENT</th>
                    <th>DELIVERED</th>
                    <th>READ</th>
                    <th>FAILED</th>
                    <th>STATUS</th>
                    <th>DATE</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($broadcasts)): ?>
                    <tr>
                        <td colspan="9" class="text-center py-4 text-muted">
                            <i class="fa-solid fa-paper-plane fa-2x mb-2 d-block"></i>
                            No WhatsApp broadcasts dispatched yet.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($broadcasts as $b): ?>
                        <tr>
                            <td>
                                <div class="fw-bold text-white"><?= htmlspecialchars($b->name); ?></div>
                            </td>
                            <td>
                                <code><?= htmlspecialchars($b->template_name); ?></code>
                            </td>
                            <td>
                                <span class="badge bg-secondary"><?= number_format((int)$b->total_recipients); ?></span>
                            </td>
                            <td>
                                <span class="text-white font-monospace"><?= number_format((int)$b->sent_count); ?></span>
                            </td>
                            <td>
                                <span class="text-info font-monospace"><?= number_format((int)$b->delivered_count); ?></span>
                            </td>
                            <td>
                                <span class="text-success font-monospace fw-bold"><?= number_format((int)$b->read_count); ?></span>
                            </td>
                            <td>
                                <span class="text-danger font-monospace"><?= number_format((int)$b->failed_count); ?></span>
                            </td>
                            <td>
                                <span class="badge bg-primary-subtle text-primary border border-primary text-uppercase"><?= htmlspecialchars($b->status); ?></span>
                            </td>
                            <td class="small text-secondary">
                                <?= date('M d, Y', strtotime($b->created_at)); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: New Broadcast -->
<div class="modal fade" id="newBroadcastModal" tabindex="-1" aria-labelledby="newBroadcastModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content bg-dark text-white border-secondary">
            <form method="POST" action="index.php?route=whatsapp/createBroadcast">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ($csrf_token ?? ''); ?>">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title" id="newBroadcastModalLabel"><i class="fa-solid fa-bullhorn me-2 text-primary"></i>Schedule Broadcast</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label text-secondary small">Broadcast Campaign Name *</label>
                        <input type="text" name="name" class="form-control bg-dark text-white border-secondary" placeholder="e.g. Q4 Festive Product Offer" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-secondary small">Approved Message Template *</label>
                        <select name="template_id" class="form-select bg-dark text-white border-secondary" required>
                            <option value="">Select an Approved Template...</option>
                            <?php foreach ($templates as $t): ?>
                                <option value="<?= $t->template_id; ?>"><?= htmlspecialchars($t->name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-secondary small">Schedule Time (Optional - leave blank to queue now)</label>
                        <input type="datetime-local" name="scheduled_at" class="form-control bg-dark text-white border-secondary">
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Broadcast Queue</button>
                </div>
            </form>
        </div>
    </div>
</div>
