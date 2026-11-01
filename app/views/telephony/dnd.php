<div class="pulse-card mb-4">
    <!-- Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h4 class="text-white mb-1"><i class="fa-solid fa-shield-halved me-2 text-warning"></i>TRAI DND Compliance Registry</h4>
            <div class="text-secondary" style="font-size: 0.9rem;">National Customer Preference Register (NCPR) scrubbing to prevent regulatory telemarketing violations and protect customer privacy.</div>
        </div>
        <div class="d-flex gap-2">
            <a href="index.php?route=telephony/index" class="btn btn-outline-secondary btn-sm px-3 py-2">
                <i class="fa-solid fa-arrow-left me-1"></i> Back to Calls
            </a>
            <button class="btn btn-primary btn-sm px-3 py-2" data-bs-toggle="modal" data-bs-target="#addDndModal">
                <i class="fa-solid fa-plus me-1"></i> Register DND Number
            </button>
        </div>
    </div>

    <!-- Batch Scrubbing Section -->
    <div class="row g-4 mb-4">
        <div class="col-lg-6">
            <div class="p-3 rounded bg-dark border border-secondary border-opacity-25 h-100">
                <h6 class="text-white mb-2"><i class="fa-solid fa-filter me-2 text-info"></i>Batch Phone List Scrubbing</h6>
                <div class="text-secondary small mb-3">Paste phone numbers (one per line or comma-separated) to check against the DND registry before launching an outbound sales campaign.</div>
                
                <form action="index.php?route=telephony/scrubList" method="POST">
                    <div class="mb-3">
                        <textarea name="numbers_text" rows="5" class="form-control bg-dark text-white border-secondary font-monospace" placeholder="+919876543210&#10;+919812345678&#10;+919900112233" required></textarea>
                    </div>
                    <button type="submit" class="btn btn-info btn-sm px-3 py-2">
                        <i class="fa-solid fa-check-double me-1"></i> Scrub Numbers Against Registry
                    </button>
                </form>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="p-3 rounded bg-dark border border-secondary border-opacity-25 h-100">
                <h6 class="text-white mb-2"><i class="fa-solid fa-clipboard-check me-2 text-success"></i>Scrubbing Results</h6>
                <?php if (empty($results)): ?>
                    <div class="text-center py-4 text-muted">
                        <i class="fa-solid fa-list-check fa-2x mb-2 d-block opacity-50"></i>
                        Paste numbers on the left and click "Scrub Numbers" to audit.
                    </div>
                <?php else: ?>
                    <div class="d-flex gap-3 mb-3">
                        <div class="badge bg-success-subtle text-success border border-success p-2">
                            Clean (Callable): <?= count($results['clean']); ?>
                        </div>
                        <div class="badge bg-danger-subtle text-danger border border-danger p-2">
                            Restricted (DND): <?= count($results['restricted']); ?>
                        </div>
                    </div>
                    
                    <?php if (!empty($results['restricted'])): ?>
                        <div class="mb-2 text-danger small fw-bold">BLOCKED DND NUMBERS (DO NOT CALL):</div>
                        <div class="p-2 rounded bg-danger bg-opacity-10 border border-danger font-monospace small mb-3" style="max-height: 120px; overflow-y: auto;">
                            <?= implode("<br>", array_map('htmlspecialchars', $results['restricted'])); ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($results['clean'])): ?>
                        <div class="mb-2 text-success small fw-bold">CLEAN NUMBERS (APPROVED FOR OUTREACH):</div>
                        <div class="p-2 rounded bg-success bg-opacity-10 border border-success font-monospace small" style="max-height: 120px; overflow-y: auto;">
                            <?= implode("<br>", array_map('htmlspecialchars', $results['clean'])); ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Registered Numbers Table -->
    <h5 class="text-white mb-3"><i class="fa-solid fa-ban text-danger me-2"></i>Active DND Restricted Phone Numbers</h5>
    <div class="table-responsive">
        <table class="table table-dark table-hover align-middle mb-0" style="border-radius: 8px; overflow: hidden;">
            <thead class="table-dark" style="border-bottom: 2px solid #333;">
                <tr class="text-secondary small">
                    <th>PHONE NUMBER</th>
                    <th>DND RESTRICTION TYPE</th>
                    <th>SOURCE</th>
                    <th>DATE REGISTERED</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($numbers)): ?>
                    <tr>
                        <td colspan="4" class="text-center py-5 text-muted">
                            <i class="fa-solid fa-shield-halved fa-3x mb-3 d-block opacity-50"></i>
                            No phone numbers registered in DND database yet.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($numbers as $n): ?>
                        <tr>
                            <td>
                                <span class="font-monospace text-white fw-bold"><?= htmlspecialchars($n->phone_number); ?></span>
                            </td>
                            <td>
                                <span class="badge bg-warning-subtle text-warning border border-warning text-uppercase" style="font-size: 0.72rem;">
                                    <?= htmlspecialchars($n->dnd_type); ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-secondary font-monospace" style="font-size: 0.72rem;">
                                    <?= htmlspecialchars($n->source); ?>
                                </span>
                            </td>
                            <td>
                                <div class="text-secondary small"><?= date('M d, Y H:i', strtotime($n->registered_at)); ?></div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Add DND Number -->
<div class="modal fade" id="addDndModal" tabindex="-1" aria-labelledby="addDndModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form action="index.php?route=telephony/addDnd" method="POST" class="modal-content bg-dark text-white border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title" id="addDndModalLabel"><i class="fa-solid fa-shield-halved text-warning me-2"></i>Add Number to DND Registry</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label small text-secondary">Phone Number <span class="text-danger">*</span></label>
                    <input type="text" name="phone_number" class="form-control bg-dark text-white border-secondary font-monospace" placeholder="+919876543210" required>
                </div>
                <div class="mb-3">
                    <label class="form-label small text-secondary">Restriction Type</label>
                    <select name="dnd_type" class="form-select bg-dark text-white border-secondary">
                        <option value="full" selected>Full Do-Not-Call (All outbound calls blocked)</option>
                        <option value="promotional_only">Promotional Only (Transactional permitted)</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-warning"><i class="fa-solid fa-ban me-1"></i> Register Restriction</button>
            </div>
        </form>
    </div>
</div>

