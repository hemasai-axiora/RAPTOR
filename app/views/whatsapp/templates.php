<div class="pulse-card mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h4 class="text-white mb-1"><i class="fa-solid fa-layer-group me-2 text-info"></i>WhatsApp Message Templates</h4>
            <div class="text-secondary" style="font-size: 0.9rem;">Pre-approved Meta templates for customer notifications, outbound broadcasts, and session restarts.</div>
        </div>
        <div class="d-flex gap-2">
            <a href="index.php?route=whatsapp/conversations" class="btn btn-outline-secondary btn-sm px-3 py-2">
                <i class="fa-solid fa-arrow-left me-1"></i> Back to Inbox
            </a>
            <button class="btn btn-primary btn-sm px-3 py-2" data-bs-toggle="modal" data-bs-target="#newTemplateModal">
                <i class="fa-solid fa-plus me-1"></i>Create Template
            </button>
        </div>
    </div>

    <!-- Templates Table -->
    <div class="table-responsive">
        <table class="table table-dark table-hover align-middle mb-0" style="border-radius: 8px; overflow: hidden;">
            <thead class="table-dark" style="border-bottom: 2px solid #333;">
                <tr class="text-secondary small">
                    <th>TEMPLATE NAME</th>
                    <th>CATEGORY</th>
                    <th>LANGUAGE</th>
                    <th>HEADER</th>
                    <th>BODY CONTENT</th>
                    <th>STATUS</th>
                    <th>DATE CREATED</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($templates)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-4 text-muted">
                            <i class="fa-solid fa-file-lines fa-2x mb-2 d-block"></i>
                            No templates configured yet. Click "Create Template" to add one.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($templates as $t): ?>
                        <tr>
                            <td>
                                <div class="fw-bold text-white font-monospace"><?= htmlspecialchars($t->name); ?></div>
                            </td>
                            <td>
                                <?php
                                $catColors = ['MARKETING' => 'primary', 'UTILITY' => 'info', 'AUTHENTICATION' => 'warning'];
                                $cColor = $catColors[$t->category] ?? 'secondary';
                                ?>
                                <span class="badge bg-<?= $cColor; ?>-subtle text-<?= $cColor; ?> border border-<?= $cColor; ?>">
                                    <?= htmlspecialchars($t->category); ?>
                                </span>
                            </td>
                            <td>
                                <span class="text-secondary small"><?= htmlspecialchars($t->language); ?></span>
                            </td>
                            <td>
                                <span class="badge bg-secondary"><?= htmlspecialchars($t->header_type); ?></span>
                            </td>
                            <td style="max-width: 320px;">
                                <div class="small text-secondary text-truncate"><?= htmlspecialchars($t->body_text); ?></div>
                            </td>
                            <td>
                                <?php if ($t->status === 'APPROVED'): ?>
                                    <span class="badge bg-success-subtle text-success border border-success">Approved</span>
                                <?php elseif ($t->status === 'PENDING'): ?>
                                    <span class="badge bg-warning-subtle text-warning border border-warning">Pending Meta Review</span>
                                <?php else: ?>
                                    <span class="badge bg-danger"><?= htmlspecialchars($t->status); ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="small text-secondary">
                                <?= date('M d, Y', strtotime($t->created_at)); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: New Template -->
<div class="modal fade" id="newTemplateModal" tabindex="-1" aria-labelledby="newTemplateModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content bg-dark text-white border-secondary">
            <form method="POST" action="index.php?route=whatsapp/saveTemplate">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title" id="newTemplateModalLabel"><i class="fa-solid fa-plus-circle me-2 text-primary"></i>Create Message Template</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label text-secondary small">Template Name (lower_case_only) *</label>
                            <input type="text" name="name" class="form-control bg-dark text-white border-secondary font-monospace" placeholder="e.g. lead_followup_intro" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label text-secondary small">Category</label>
                            <select name="category" class="form-select bg-dark text-white border-secondary">
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat; ?>"><?= $cat; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label text-secondary small">Language</label>
                            <select name="language" class="form-select bg-dark text-white border-secondary">
                                <option value="en_US">English (en_US)</option>
                                <option value="en_GB">English UK (en_GB)</option>
                                <option value="hi">Hindi (hi)</option>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label text-secondary small">Header Type</label>
                            <select name="header_type" class="form-select bg-dark text-white border-secondary">
                                <?php foreach ($header_types as $ht): ?>
                                    <option value="<?= $ht; ?>"><?= $ht; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label text-secondary small">Header Text (if Text selected)</label>
                            <input type="text" name="header_content" class="form-control bg-dark text-white border-secondary" placeholder="Optional header text">
                        </div>

                        <div class="col-md-12">
                            <label class="form-label text-secondary small">Body Content * (Use {{1}}, {{2}} for dynamic variables)</label>
                            <textarea name="body_text" class="form-control bg-dark text-white border-secondary" rows="4" placeholder="Hello {{1}}, thank you for reaching out to {{2}}. We are pleased to assist you." required></textarea>
                        </div>

                        <div class="col-md-12">
                            <label class="form-label text-secondary small">Footer Text (Optional)</label>
                            <input type="text" name="footer_text" class="form-control bg-dark text-white border-secondary" placeholder="e.g. Reply STOP to unsubscribe">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Template</button>
                </div>
            </form>
        </div>
    </div>
</div>
