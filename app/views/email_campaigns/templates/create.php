<?php
// Raptor CRM - Template Visual Editor with Live Preview
?>
<div class="container-fluid px-0">
    <div class="d-flex align-items-center mb-4 gap-3">
        <a href="index.php?route=email_campaigns/templates" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-arrow-left"></i></a>
        <div>
            <h3 class="fw-bold mb-1"><?php echo $template ? 'Edit Email Template' : 'Create Email Template'; ?></h3>
            <p class="text-secondary small mb-0">Craft responsive, table-based HTML email with auto-generated plain-text alternative.</p>
        </div>
    </div>

    <!-- Flash Messages -->
    <?php if (!empty($_SESSION['flash_error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm" role="alert">
            <i class="fa-solid fa-triangle-exclamation me-2"></i><?php echo $_SESSION['flash_error']; unset($_SESSION['flash_error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <form method="POST" action="index.php?route=email_campaigns/templateSave">
        <?php if ($template): ?>
            <input type="hidden" name="template_id" value="<?php echo $template->template_id; ?>">
        <?php endif; ?>

        <!-- Top Settings Row -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-3">
                <div class="row g-3">
                    <div class="col-md-5">
                        <label class="form-label small fw-semibold">Template Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control form-control-sm" placeholder="e.g. Monthly Newsletter 2026" value="<?php echo htmlspecialchars($template->name ?? ''); ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">Category</label>
                        <select name="category" class="form-select form-select-sm">
                            <option value="newsletter" <?php echo (($template->category ?? '') === 'newsletter') ? 'selected' : ''; ?>>Newsletter</option>
                            <option value="announcement" <?php echo (($template->category ?? '') === 'announcement') ? 'selected' : ''; ?>>Corporate Announcement</option>
                            <option value="promotional" <?php echo (($template->category ?? '') === 'promotional') ? 'selected' : ''; ?>>Product / Promotional</option>
                            <option value="transactional" <?php echo (($template->category ?? '') === 'transactional') ? 'selected' : ''; ?>>Notification / Alerts</option>
                            <option value="general" <?php echo (($template->category ?? 'general') === 'general') ? 'selected' : ''; ?>>General</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Default Subject Line</label>
                        <input type="text" name="subject_default" class="form-control form-control-sm" placeholder="Subject for this template" value="<?php echo htmlspecialchars($template->subject_default ?? ''); ?>">
                    </div>
                </div>
            </div>
        </div>

        <!-- Merge Tags Palette Bar -->
        <div class="card border-0 shadow-sm mb-3 bg-light">
            <div class="card-body p-2 d-flex flex-wrap align-items-center gap-1">
                <span class="small fw-bold text-muted me-2"><i class="fa-solid fa-tags me-1"></i>Insert Tag:</span>
                <?php foreach ($merge_tags as $tag => $label): ?>
                    <button type="button" class="btn btn-xs btn-outline-secondary py-1 px-2 btn-insert-tag" data-tag="<?php echo htmlspecialchars($tag); ?>" title="<?php echo htmlspecialchars($label); ?>" style="font-size: 0.75rem;">
                        <code><?php echo htmlspecialchars($tag); ?></code>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Editor & Live Split-Screen Preview -->
        <div class="row g-3 mb-4">
            <!-- Left: HTML Code / Plain Text Tabs -->
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white py-2">
                        <ul class="nav nav-tabs card-header-tabs" id="editorTabs" role="tablist">
                            <li class="nav-item">
                                <button class="nav-link active small fw-bold" id="html-tab" data-bs-toggle="tab" data-bs-target="#tab-html" type="button"><i class="fa-brands fa-html5 text-danger me-1"></i>HTML Source</button>
                            </li>
                            <li class="nav-item">
                                <button class="nav-link small fw-bold" id="plain-tab" data-bs-toggle="tab" data-bs-target="#tab-plain" type="button"><i class="fa-solid fa-align-left text-primary me-1"></i>Plain Text (Alternative)</button>
                            </li>
                        </ul>
                    </div>
                    <div class="card-body p-0">
                        <div class="tab-content h-100">
                            <!-- HTML Editor Tab -->
                            <div class="tab-pane fade show active p-3" id="tab-html">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="small text-muted">Use table-based HTML with inline styles. Max 100 KB.</span>
                                    <span class="badge bg-secondary-subtle text-secondary" id="html-size-badge">0 KB</span>
                                </div>
                                <textarea name="html_content" id="html_content" rows="22" class="form-control font-monospace small" style="font-size: 13px; line-height: 1.4; resize: vertical;" placeholder="Paste or code table-based HTML template here..." required><?php echo htmlspecialchars($template->html_content ?? CampaignTemplate::getPrebuiltTemplateAnnouncement()); ?></textarea>
                            </div>
                            <!-- Plain Text Tab -->
                            <div class="tab-pane fade p-3" id="tab-plain">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="small text-muted">Mandatory plain-text part for non-HTML mail clients.</span>
                                    <button type="button" class="btn btn-outline-primary btn-xs py-0" id="btn-sync-plain"><i class="fa-solid fa-arrows-rotate me-1"></i>Auto-Generate from HTML</button>
                                </div>
                                <textarea name="plain_text_content" id="plain_text_content" rows="22" class="form-control font-monospace small" style="font-size: 13px; line-height: 1.4; resize: vertical;" placeholder="Plain text alternative will auto-generate if left blank..."><?php echo htmlspecialchars($template->plain_text_content ?? ''); ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right: Live Sandboxed Preview -->
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center">
                        <span class="small fw-bold text-primary"><i class="fa-solid fa-eye me-1"></i>Live Render Preview</span>
                        <div class="btn-group btn-group-sm">
                            <button type="button" class="btn btn-outline-secondary active btn-preview-size" data-width="100%"><i class="fa-solid fa-desktop me-1"></i>Desktop</button>
                            <button type="button" class="btn btn-outline-secondary btn-preview-size" data-width="380px"><i class="fa-solid fa-mobile-screen me-1"></i>Mobile (380px)</button>
                        </div>
                    </div>
                    <div class="card-body p-2 bg-light d-flex justify-content-center align-items-stretch" style="min-height: 480px;">
                        <iframe id="preview-frame" style="width: 100%; height: 520px; border: 1px solid #cbd5e1; border-radius: 6px; background: #ffffff;" sandbox="allow-same-origin"></iframe>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-5">
            <a href="index.php?route=email_campaigns/templates" class="btn btn-outline-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary px-4"><i class="fa-solid fa-check me-2"></i>Save Template</button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const htmlArea = document.getElementById('html_content');
    const plainArea = document.getElementById('plain_text_content');
    const previewFrame = document.getElementById('preview-frame');
    const sizeBadge = document.getElementById('html-size-badge');
    const btnSyncPlain = document.getElementById('btn-sync-plain');

    function updatePreview() {
        const raw = htmlArea.value;
        const bytes = new Blob([raw]).size;
        const kb = (bytes / 1024).toFixed(1);
        sizeBadge.textContent = kb + ' KB / 100 KB';
        if (bytes > 102400) {
            sizeBadge.className = 'badge bg-danger';
        } else {
            sizeBadge.className = 'badge bg-secondary-subtle text-secondary';
        }

        // Mock sample merge tags for live rendering
        let rendered = raw
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

    htmlArea.addEventListener('input', updatePreview);
    updatePreview();

    // Responsive toggle
    document.querySelectorAll('.btn-preview-size').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.btn-preview-size').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            previewFrame.style.width = this.getAttribute('data-width');
        });
    });

    // Tag insertion
    document.querySelectorAll('.btn-insert-tag').forEach(btn => {
        btn.addEventListener('click', function() {
            const tag = this.getAttribute('data-tag');
            const start = htmlArea.selectionStart;
            const end = htmlArea.selectionEnd;
            htmlArea.value = htmlArea.value.substring(0, start) + tag + htmlArea.value.substring(end);
            htmlArea.focus();
            htmlArea.selectionStart = htmlArea.selectionEnd = start + tag.length;
            updatePreview();
        });
    });

    // Auto-generate plain text
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
});
</script>

