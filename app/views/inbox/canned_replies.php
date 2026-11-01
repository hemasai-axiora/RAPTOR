<div class="pulse-card mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h4 class="text-white mb-1"><i class="fa-solid fa-bolt me-2 text-warning"></i>Canned Quick Replies</h4>
            <div class="text-secondary" style="font-size: 0.9rem;">Fast keyboard shortcuts and standard response snippets for team reps across all channels. Type <code>/shortcut</code> in the inbox to trigger.</div>
        </div>
        <div class="d-flex gap-2">
            <a href="index.php?route=inbox/index" class="btn btn-outline-secondary btn-sm px-3 py-2">
                <i class="fa-solid fa-arrow-left me-1"></i> Back to Shared Inbox
            </a>
            <button class="btn btn-primary btn-sm px-3 py-2" data-bs-toggle="modal" data-bs-target="#newCannedModal">
                <i class="fa-solid fa-plus me-1"></i>New Quick Reply
            </button>
        </div>
    </div>

    <!-- Supported Merge Tags Banner -->
    <div class="p-3 mb-4 rounded bg-dark border border-secondary border-opacity-25 d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div class="small text-secondary">
            <i class="fa-solid fa-circle-info text-info me-2"></i>
            <strong>Merge Variables Supported:</strong> <code>{{first_name}}</code>, <code>{{last_name}}</code>, <code>{{company}}</code>, <code>{{email}}</code>, <code>{{phone}}</code>
        </div>
        <div class="text-muted small">Auto-interpolated when injected into customer chats or emails.</div>
    </div>

    <!-- Canned Replies Table -->
    <div class="table-responsive">
        <table class="table table-dark table-hover align-middle mb-0" style="border-radius: 8px; overflow: hidden;">
            <thead class="table-dark" style="border-bottom: 2px solid #333;">
                <tr class="text-secondary small">
                    <th style="width: 140px;">SHORTCUT</th>
                    <th style="width: 200px;">TITLE</th>
                    <th style="width: 130px;">CATEGORY</th>
                    <th>RESPONSE SNIPPET</th>
                    <th style="width: 150px;">CREATED BY</th>
                    <th style="width: 100px;" class="text-end">ACTION</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($canned)): ?>
                    <tr>
                        <td colspan="6" class="text-center py-5 text-muted">
                            <i class="fa-solid fa-comment-dots fa-3x mb-3 d-block opacity-50"></i>
                            No canned replies configured yet. Click "New Quick Reply" to build your team snippet library.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($canned as $r): ?>
                        <tr>
                            <td>
                                <span class="badge bg-warning text-dark font-monospace fw-bold px-2 py-1" style="font-size: 0.85rem;">
                                    <?= htmlspecialchars($r->shortcut); ?>
                                </span>
                            </td>
                            <td>
                                <div class="fw-semibold text-white"><?= htmlspecialchars($r->title); ?></div>
                            </td>
                            <td>
                                <?php
                                $catBadges = [
                                    'general' => 'secondary',
                                    'sales' => 'primary',
                                    'support' => 'info',
                                    'onboarding' => 'success'
                                ];
                                $cBadge = $catBadges[strtolower($r->category)] ?? 'secondary';
                                ?>
                                <span class="badge bg-<?= $cBadge; ?>-subtle text-<?= $cBadge; ?> border border-<?= $cBadge; ?> text-uppercase" style="font-size: 0.72rem;">
                                    <?= htmlspecialchars($r->category); ?>
                                </span>
                            </td>
                            <td>
                                <div class="text-secondary small font-monospace" style="max-width: 450px; white-space: pre-wrap; word-break: break-word;">
                                    <?= htmlspecialchars($r->body_text); ?>
                                </div>
                            </td>
                            <td>
                                <div class="small text-white"><?= htmlspecialchars($r->creator_name ?? 'System'); ?></div>
                                <div class="text-muted" style="font-size: 0.75rem;"><?= date('M d, Y', strtotime($r->created_at)); ?></div>
                            </td>
                            <td class="text-end">
                                <button class="btn btn-outline-info btn-sm copy-snippet-btn" data-snippet="<?= htmlspecialchars($r->body_text); ?>" title="Copy to clipboard">
                                    <i class="fa-solid fa-copy"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: New Canned Reply -->
<div class="modal fade" id="newCannedModal" tabindex="-1" aria-labelledby="newCannedModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form action="index.php?route=inbox/saveCanned" method="POST" class="modal-content bg-dark text-white border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title" id="newCannedModalLabel"><i class="fa-solid fa-bolt text-warning me-2"></i>Create Canned Reply Shortcut</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small text-secondary">Shortcut Trigger <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-secondary text-white border-secondary">/</span>
                            <input type="text" name="shortcut" class="form-control bg-dark text-white border-secondary" placeholder="pricing" required>
                        </div>
                        <div class="form-text text-muted">Keyboard trigger typed in the chat box (e.g. /pricing, /intro, /demo).</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small text-secondary">Category</label>
                        <select name="category" class="form-select bg-dark text-white border-secondary">
                            <option value="general">General</option>
                            <option value="sales" selected>Sales</option>
                            <option value="support">Support</option>
                            <option value="onboarding">Onboarding</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label small text-secondary">Snippet Title <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control bg-dark text-white border-secondary" placeholder="Standard Pricing & Packages" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label small text-secondary">Response Content <span class="text-danger">*</span></label>
                        <textarea name="body_text" rows="5" class="form-control bg-dark text-white border-secondary font-monospace" placeholder="Hi {{first_name}}, thank you for inquiring about Raptor! Our enterprise plan includes full CRM, multi-channel messaging, and marketing journeys. Would you like a live demo?" required></textarea>
                        <div class="d-flex gap-2 mt-2 flex-wrap">
                            <button type="button" class="btn btn-sm btn-outline-secondary insert-tag" data-tag="{{first_name}}">+ {{first_name}}</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary insert-tag" data-tag="{{company}}">+ {{company}}</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary insert-tag" data-tag="{{email}}">+ {{email}}</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary insert-tag" data-tag="{{phone}}">+ {{phone}}</button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk me-1"></i> Save Shortcut</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Variable tag insertion
    document.querySelectorAll('.insert-tag').forEach(btn => {
        btn.addEventListener('click', function() {
            const textarea = document.querySelector('textarea[name="body_text"]');
            const tag = this.getAttribute('data-tag');
            if (textarea) {
                const start = textarea.selectionStart;
                const end = textarea.selectionEnd;
                textarea.value = textarea.value.substring(0, start) + tag + textarea.value.substring(end);
                textarea.focus();
                textarea.selectionStart = textarea.selectionEnd = start + tag.length;
            }
        });
    });

    // Copy snippet button
    document.querySelectorAll('.copy-snippet-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const snippet = this.getAttribute('data-snippet');
            navigator.clipboard.writeText(snippet).then(() => {
                const originalHtml = this.innerHTML;
                this.innerHTML = '<i class="fa-solid fa-check text-success"></i>';
                setTimeout(() => { this.innerHTML = originalHtml; }, 1500);
            });
        });
    });
});
</script>

